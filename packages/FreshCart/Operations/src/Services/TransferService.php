<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockRequest;
use FreshCart\Operations\Models\StockRequestItem;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Models\TransferItem;
use FreshCart\Operations\Models\TransferReceipt;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Module C — inter-branch / temporary / emergency transfers, and the dispatch and
 * receipt legs of Module B replenishments (P4, P5).
 *
 * Stock only moves at two events: dispatch (sellable → in transit at the source)
 * and receipt (in transit → sellable or damaged at the destination). Requests,
 * approvals, picking and scheduling never change stock.
 */
class TransferService
{
    const OPEN_STATUSES = [
        Transfer::STATUS_DRAFT,
        Transfer::STATUS_PENDING_APPROVAL,
        Transfer::STATUS_APPROVED,
        Transfer::STATUS_PICKING,
    ];

    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected StockLedgerService $stockLedger,
        protected NotificationService $notifications,
        protected ProductCatalog $productCatalog,
        protected Settings $settings,
        protected AttachmentService $attachments,
    ) {}

    /**
     * Units at a location already promised to approved-but-not-dispatched transfers.
     */
    public function committedQty(int $productId, int $sourceId, ?int $exceptTransferId = null): int
    {
        return (int) TransferItem::query()
            ->join('fc_transfers', 'fc_transfers.id', '=', 'fc_transfer_items.transfer_id')
            ->where('fc_transfers.source_inventory_source_id', $sourceId)
            ->whereIn('fc_transfers.status', [Transfer::STATUS_APPROVED, Transfer::STATUS_PICKING])
            ->when($exceptTransferId, fn ($query) => $query->where('fc_transfers.id', '!=', $exceptTransferId))
            ->where('fc_transfer_items.product_id', $productId)
            ->sum(DB::raw('fc_transfer_items.qty_requested - fc_transfer_items.qty_dispatched'));
    }

    /**
     * Transferable stock minus what other open transfers have already committed.
     */
    public function uncommittedQty(int $productId, int $sourceId, ?int $exceptTransferId = null): int
    {
        return max(0, $this->stockLedger->transferableQty($productId, $sourceId) - $this->committedQty($productId, $sourceId, $exceptTransferId));
    }

    public function create(array $data, array $items): Transfer
    {
        $this->assertLocations((int) $data['source_inventory_source_id'], (int) $data['destination_inventory_source_id']);

        if ($data['type'] === Transfer::TYPE_TEMPORARY && empty($data['expected_return_date'])) {
            throw OperationsException::trans('expected-return-required');
        }

        return DB::transaction(function () use ($data, $items) {
            $transfer = Transfer::create([
                'reference' => $this->references->next('TR'),
                'type' => $data['type'],
                'source_inventory_source_id' => $data['source_inventory_source_id'],
                'destination_inventory_source_id' => $data['destination_inventory_source_id'],
                'status' => Transfer::STATUS_DRAFT,
                'priority' => $data['priority'] ?? 'normal',
                'reason' => $data['reason'] ?? null,
                'requested_by' => auth()->guard('admin')->id(),
                'approval_required' => $this->needsApproval($data['type']),
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->addItems($transfer, $items);

            $this->approvals->record($transfer, 'created', null, Transfer::STATUS_DRAFT);

            return $transfer->fresh('items');
        });
    }

    /**
     * Submit a draft: either into the approval queue or, when no approval is
     * required, straight to Approved (ready for picking).
     */
    public function submit(Transfer $transfer): Transfer
    {
        $this->assertAvailability($transfer);

        if ($transfer->approval_required) {
            return $this->approvals->submit($transfer, null, [Transfer::STATUS_DRAFT], Transfer::STATUS_PENDING_APPROVAL);
        }

        $transfer = DB::transaction(function () use ($transfer) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, [Transfer::STATUS_DRAFT]);

            $transfer->update([
                'status' => Transfer::STATUS_APPROVED,
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);

            $this->approvals->record($transfer, 'submitted', Transfer::STATUS_DRAFT, Transfer::STATUS_APPROVED);

            return $transfer;
        });

        $this->notifyReadyForPicking($transfer);

        return $transfer;
    }

    public function approve(Transfer $transfer, ?string $remarks): Transfer
    {
        $transfer = DB::transaction(function () use ($transfer, $remarks) {
            $this->assertAvailability($transfer);

            return $this->approvals->approve($transfer, $remarks, Transfer::STATUS_APPROVED, Transfer::STATUS_PENDING_APPROVAL);
        });

        if ($transfer->status === Transfer::STATUS_APPROVED) {
            $this->notifyReadyForPicking($transfer);
        }

        return $transfer;
    }

    public function reject(Transfer $transfer, string $remarks): Transfer
    {
        return $this->approvals->reject($transfer, $remarks, Transfer::STATUS_REJECTED, Transfer::STATUS_PENDING_APPROVAL);
    }

    public function cancel(Transfer $transfer, ?string $remarks): Transfer
    {
        return DB::transaction(function () use ($transfer, $remarks) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, self::OPEN_STATUSES);

            $from = $transfer->status;

            $transfer->update(['status' => Transfer::STATUS_CANCELLED]);

            // Allocations made from a branch request return to its backorder.
            foreach ($transfer->items()->whereNotNull('stock_request_item_id')->get() as $item) {
                StockRequestItem::whereKey($item->stock_request_item_id)
                    ->where('qty_allocated', '>=', $item->qty_requested)
                    ->decrement('qty_allocated', $item->qty_requested);
            }

            if ($transfer->stock_request_id) {
                $this->syncStockRequest($transfer->stock_request_id);
            }

            $this->approvals->record($transfer, 'cancelled', $from, Transfer::STATUS_CANCELLED, $remarks);

            return $transfer;
        });
    }

    /**
     * Record picked/packed quantities (warehouse personnel).
     *
     * @param  array<int, int>  $picked  transfer item id => qty
     */
    public function pick(Transfer $transfer, array $picked): Transfer
    {
        return DB::transaction(function () use ($transfer, $picked) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, [Transfer::STATUS_APPROVED, Transfer::STATUS_PICKING]);

            foreach ($transfer->items as $item) {
                $qty = (int) ($picked[$item->id] ?? 0);

                if ($qty > $item->qty_requested) {
                    throw OperationsException::trans('pick-exceeds', ['sku' => $item->sku]);
                }

                $item->update(['qty_picked' => $qty]);
            }

            $from = $transfer->status;

            $transfer->update([
                'status' => Transfer::STATUS_PICKING,
                'picked_by' => auth()->guard('admin')->id(),
                'picking_started_at' => $transfer->picking_started_at ?? now(),
                'packed_at' => now(),
            ]);

            $this->approvals->record($transfer, 'picked', $from, Transfer::STATUS_PICKING);

            return $transfer->fresh('items');
        });
    }

    /**
     * Logistics: delivery schedule and responsible staff.
     */
    public function schedule(Transfer $transfer, array $data): Transfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, [Transfer::STATUS_APPROVED, Transfer::STATUS_PICKING, Transfer::STATUS_IN_TRANSIT]);

            $transfer->update([
                'scheduled_dispatch_at' => $data['scheduled_dispatch_at'] ?? null,
                'expected_arrival_at' => $data['expected_arrival_at'] ?? null,
                'assigned_staff_id' => $data['assigned_staff_id'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle' => $data['vehicle'] ?? null,
            ]);

            $this->approvals->record($transfer, 'scheduled', $transfer->status, $transfer->status, $data['notes'] ?? null);

            return $transfer;
        });
    }

    /**
     * Dispatch picked units. Replays with the same idempotency key are no-ops.
     */
    public function dispatch(Transfer $transfer, string $idempotencyKey): Transfer
    {
        if (Transfer::where('dispatch_idempotency_key', $idempotencyKey)->exists()) {
            return $transfer->fresh();
        }

        $transfer = DB::transaction(function () use ($transfer, $idempotencyKey) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, [Transfer::STATUS_PICKING]);

            $items = $transfer->items()->lockForUpdate()->get();

            if ($items->sum('qty_picked') <= 0) {
                throw OperationsException::trans('nothing-picked');
            }

            foreach ($items as $item) {
                if ($item->qty_picked <= 0) {
                    continue;
                }

                $this->stockLedger->dispatchTransfer($item->product_id, $transfer->source_inventory_source_id, $item->qty_picked, [
                    'reference' => $transfer,
                    'reason' => trans('operations::app.transfers.ledger-dispatch', ['location' => $transfer->destinationLocation?->name]),
                ]);

                $item->update(['qty_dispatched' => $item->qty_picked]);

                if ($item->stock_request_item_id) {
                    StockRequestItem::whereKey($item->stock_request_item_id)->increment('qty_dispatched', $item->qty_picked);
                }
            }

            $transfer->update([
                'status' => Transfer::STATUS_IN_TRANSIT,
                'dispatched_by' => auth()->guard('admin')->id(),
                'dispatched_at' => now(),
                'dispatch_idempotency_key' => $idempotencyKey,
            ]);

            $this->approvals->record($transfer, 'dispatched', Transfer::STATUS_PICKING, Transfer::STATUS_IN_TRANSIT);

            if ($transfer->stock_request_id) {
                $this->syncStockRequest($transfer->stock_request_id);
            }

            return $transfer;
        });

        $this->notifications->notifyPermission(
            'operations.transfers.receive',
            'transfer_dispatched',
            trans('operations::app.notifications.transfer-dispatched', [
                'reference' => $transfer->reference,
                'location' => $transfer->destinationLocation?->name,
            ]),
            null,
            route('admin.operations.transfers.view', $transfer->id),
            $transfer->destination_inventory_source_id,
        );

        return $transfer;
    }

    /**
     * Record a (possibly partial) receipt at the destination.
     *
     * @param  array  $lines  [transfer_item_id => ['qty_received' => int, 'qty_damaged' => int, 'remarks' => ?string]]
     */
    public function receive(Transfer $transfer, array $data, array $lines, ?UploadedFile $proof = null): TransferReceipt
    {
        if ($existing = TransferReceipt::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing;
        }

        try {
            $receipt = DB::transaction(function () use ($transfer, $data, $lines, $proof) {
                $transfer = $this->approvals->lock($transfer);

                if (! in_array($transfer->status, Transfer::RECEIVABLE_STATUSES, true)) {
                    throw OperationsException::trans('invalid-status', ['status' => trans('operations::app.statuses.'.$transfer->status)]);
                }

                $receipt = TransferReceipt::create([
                    'transfer_id' => $transfer->id,
                    'received_by' => auth()->guard('admin')->id(),
                    'receiver_name' => $data['receiver_name'] ?? null,
                    'received_at' => now(),
                    'remarks' => $data['remarks'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                ]);

                $any = false;

                foreach ($transfer->items()->lockForUpdate()->get() as $item) {
                    $good = (int) ($lines[$item->id]['qty_received'] ?? 0);
                    $damaged = (int) ($lines[$item->id]['qty_damaged'] ?? 0);

                    if ($good + $damaged === 0) {
                        continue;
                    }

                    if ($good < 0 || $damaged < 0 || $good + $damaged > $item->inTransitQty()) {
                        throw OperationsException::trans('receipt-exceeds-transit', ['sku' => $item->sku, 'qty' => $item->inTransitQty()]);
                    }

                    $this->stockLedger->receiveTransfer(
                        $item->product_id,
                        $transfer->source_inventory_source_id,
                        $transfer->destination_inventory_source_id,
                        $good,
                        $damaged,
                        ['reference' => $transfer, 'reason' => $data['remarks'] ?? null],
                    );

                    $item->increment('qty_received', $good);
                    $item->increment('qty_damaged', $damaged);

                    if ($item->stock_request_item_id) {
                        StockRequestItem::whereKey($item->stock_request_item_id)->increment('qty_received', $good);
                    }

                    $receipt->items()->create([
                        'transfer_item_id' => $item->id,
                        'qty_received' => $good,
                        'qty_damaged' => $damaged,
                        'remarks' => $lines[$item->id]['remarks'] ?? null,
                    ]);

                    $any = true;
                }

                if (! $any) {
                    throw OperationsException::trans('no-items');
                }

                if ($proof) {
                    $this->attachments->store($receipt, $proof, 'proof_of_delivery');
                }

                $remaining = $transfer->items()->get()->sum(fn ($item) => $item->inTransitQty());
                $damagedTotal = $transfer->items()->sum('qty_damaged');

                $status = $remaining > 0 ? Transfer::STATUS_PARTIALLY_RECEIVED : Transfer::STATUS_RECEIVED;

                $from = $transfer->status;

                $transfer->update([
                    'status' => $status,
                    'received_at' => $status === Transfer::STATUS_RECEIVED ? now() : null,
                    'has_discrepancy' => $transfer->has_discrepancy || $damagedTotal > 0,
                ]);

                $this->approvals->record($transfer, 'receipt', $from, $status, $data['remarks'] ?? null);

                if ($transfer->stock_request_id) {
                    $this->syncStockRequest($transfer->stock_request_id);
                }

                return $receipt;
            });
        } catch (QueryException $e) {
            if ($existing = TransferReceipt::where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }

            throw $e;
        }

        $transfer = $transfer->fresh();

        $this->notifications->notifyPermission(
            'operations.transfers',
            'transfer_received',
            trans('operations::app.notifications.transfer-received', [
                'reference' => $transfer->reference,
                'status' => trans('operations::app.statuses.'.$transfer->status),
            ]),
            null,
            route('admin.operations.transfers.view', $transfer->id),
            $transfer->source_inventory_source_id,
        );

        return $receipt;
    }

    /**
     * Close a transfer whose remaining in-transit units will not arrive. The
     * shortage becomes a transit-loss adjustment that must be approved before
     * it is written off; until then the units stay visible as in transit.
     */
    public function closeWithDiscrepancy(Transfer $transfer, string $remarks): ?Adjustment
    {
        return DB::transaction(function () use ($transfer, $remarks) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, Transfer::RECEIVABLE_STATUSES);

            $short = $transfer->items->filter(fn ($item) => $item->inTransitQty() > 0);

            if ($short->isEmpty()) {
                throw OperationsException::trans('nothing-in-transit');
            }

            $adjustment = app(AdjustmentService::class)->create([
                'inventory_source_id' => $transfer->source_inventory_source_id,
                'direction' => Adjustment::DIRECTION_OUT,
                'source_bucket' => StockHold::BUCKET_IN_TRANSIT,
                'reason_code' => 'transit_loss',
                'reason' => trans('operations::app.transfers.shortage-reason', ['reference' => $transfer->reference]).' '.$remarks,
                'origin' => $transfer,
            ], $short->map(fn ($item) => [
                'product_id' => $item->product_id,
                'qty' => $item->inTransitQty(),
            ])->values()->all());

            app(AdjustmentService::class)->submit($adjustment);

            foreach ($short as $item) {
                $item->update(['qty_written_off' => $item->qty_written_off + $item->inTransitQty()]);
            }

            $from = $transfer->status;

            $transfer->update([
                'status' => Transfer::STATUS_CLOSED_WITH_DISCREPANCY,
                'has_discrepancy' => true,
            ]);

            $this->approvals->record($transfer, 'closed_with_discrepancy', $from, Transfer::STATUS_CLOSED_WITH_DISCREPANCY, $remarks.' ('.$adjustment->reference.')');

            if ($transfer->stock_request_id) {
                $this->syncStockRequest($transfer->stock_request_id);
            }

            return $adjustment;
        });
    }

    /**
     * Create the return leg of a received temporary transfer (pre-approved).
     */
    public function createReturn(Transfer $transfer): Transfer
    {
        if (! $transfer->isTemporary()) {
            throw OperationsException::trans('not-temporary');
        }

        return DB::transaction(function () use ($transfer) {
            $transfer = $this->approvals->lock($transfer);

            $this->approvals->assertStatus($transfer, [Transfer::STATUS_RECEIVED, Transfer::STATUS_PARTIALLY_RECEIVED]);

            if (Transfer::where('return_of_transfer_id', $transfer->id)->exists()) {
                throw OperationsException::trans('return-exists');
            }

            $return = Transfer::create([
                'reference' => $this->references->next('TR'),
                'type' => Transfer::TYPE_INTER_BRANCH,
                'return_of_transfer_id' => $transfer->id,
                'source_inventory_source_id' => $transfer->destination_inventory_source_id,
                'destination_inventory_source_id' => $transfer->source_inventory_source_id,
                'status' => Transfer::STATUS_APPROVED,
                'priority' => $transfer->priority,
                'reason' => trans('operations::app.transfers.return-of', ['reference' => $transfer->reference]),
                'requested_by' => auth()->guard('admin')->id(),
                'approval_required' => false,
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);

            foreach ($transfer->items as $item) {
                if ($item->qty_received > 0) {
                    $return->items()->create([
                        'product_id' => $item->product_id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'qty_requested' => $item->qty_received,
                    ]);
                }
            }

            $this->approvals->record($return, 'created', null, Transfer::STATUS_APPROVED, $transfer->reference);

            return $return;
        });
    }

    /**
     * Recompute a branch request's status from its lines and transfers.
     */
    public function syncStockRequest(int $stockRequestId): void
    {
        $request = StockRequest::with(['items', 'transfers'])->lockForUpdate()->find($stockRequestId);

        if (! $request || in_array($request->status, [StockRequest::STATUS_REJECTED, StockRequest::STATUS_CANCELLED, StockRequest::STATUS_DRAFT], true)) {
            return;
        }

        $outstanding = $request->items->sum(fn ($item) => $item->outstandingQty());
        $allocated = $request->items->sum('qty_allocated');

        $openTransfers = $request->transfers->whereIn('status', array_merge(self::OPEN_STATUSES, Transfer::RECEIVABLE_STATUSES))->count();

        $status = match (true) {
            $outstanding === 0 && $openTransfers === 0 => StockRequest::STATUS_FULFILLED,
            $outstanding === 0 => StockRequest::STATUS_ALLOCATED,
            $allocated > 0 => StockRequest::STATUS_PARTIALLY_ALLOCATED,
            default => StockRequest::STATUS_SUBMITTED,
        };

        if ($status !== $request->status) {
            $from = $request->status;

            $request->update(['status' => $status]);

            $this->approvals->record($request, $status === StockRequest::STATUS_FULFILLED ? 'completed' : 'allocated', $from, $status);

            if ($request->requested_by) {
                $this->notifications->notifyAdmin(
                    $request->requested_by,
                    'stock_request_status',
                    trans('operations::app.notifications.stock-request-status', [
                        'reference' => $request->reference,
                        'status' => trans('operations::app.statuses.'.$status),
                    ]),
                    null,
                    route('admin.operations.stock_requests.view', $request->id),
                );
            }
        }
    }

    public function needsApproval(string $type): bool
    {
        return $type !== Transfer::TYPE_REPLENISHMENT && $this->settings->interBranchApprovalRequired();
    }

    /**
     * Requested quantities must still be available (net of other commitments).
     */
    public function assertAvailability(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $available = $this->uncommittedQty($item->product_id, $transfer->source_inventory_source_id, $transfer->id);

            if ($item->qty_requested > $available) {
                throw OperationsException::trans('insufficient-stock', [
                    'sku' => $item->sku,
                    'available' => $available,
                    'requested' => $item->qty_requested,
                ]);
            }
        }
    }

    public function addItems(Transfer $transfer, array $items): void
    {
        $merged = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            if ($productId && $qty > 0) {
                $merged[$productId] = ($merged[$productId] ?? 0) + $qty;
            }
        }

        if (! $merged) {
            throw OperationsException::trans('no-items');
        }

        foreach ($merged as $productId => $qty) {
            $product = $this->productCatalog->stockable($productId);

            $transfer->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name ?? $product->sku,
                'qty_requested' => $qty,
            ]);
        }
    }

    protected function assertLocations(int $sourceId, int $destinationId): void
    {
        if ($sourceId === $destinationId) {
            throw OperationsException::trans('same-location');
        }
    }

    protected function notifyReadyForPicking(Transfer $transfer): void
    {
        $this->notifications->notifyPermission(
            'operations.transfers.pick',
            'transfer_ready',
            trans('operations::app.notifications.transfer-ready', ['reference' => $transfer->reference]),
            null,
            route('admin.operations.transfers.view', $transfer->id),
            $transfer->source_inventory_source_id,
        );
    }
}
