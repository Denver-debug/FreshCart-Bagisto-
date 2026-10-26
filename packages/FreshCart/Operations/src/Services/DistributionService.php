<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\StockPolicy;
use FreshCart\Operations\Models\StockRequest;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Support\LocationScope;
use FreshCart\Operations\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Module B — branch replenishment requests and rule-based allocation (P3).
 *
 * The suggestion is transparent and deterministic:
 *   available  = warehouse transferable stock − units committed to other open transfers
 *   spare      = available − warehouse minimum stock (from its reorder policy)
 *   suggestion = min(outstanding request, spare), further capped so the branch
 *                does not exceed its maximum stock when a maximum is set.
 * Allocating a different quantity requires a recorded reason.
 */
class DistributionService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected TransferService $transfers,
        protected StockLedgerService $stockLedger,
        protected NotificationService $notifications,
        protected ProductCatalog $productCatalog,
        protected LocationScope $locationScope,
    ) {}

    public function save(array $data, array $items, ?StockRequest $request = null): StockRequest
    {
        return DB::transaction(function () use ($data, $items, $request) {
            if ($request) {
                $request = $this->approvals->lock($request);

                $this->approvals->assertStatus($request, [StockRequest::STATUS_DRAFT]);

                $request->update($data);
                $request->items()->delete();
            } else {
                $request = StockRequest::create($data + [
                    'reference' => $this->references->next('SR'),
                    'status' => StockRequest::STATUS_DRAFT,
                    'requested_by' => auth()->guard('admin')->id(),
                ]);
            }

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

                $request->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name ?? $product->sku,
                    'qty_requested' => $qty,
                ]);
            }

            $this->approvals->record($request, $request->wasRecentlyCreated ? 'created' : 'updated', null, StockRequest::STATUS_DRAFT);

            return $request->fresh('items');
        });
    }

    public function submit(StockRequest $request): StockRequest
    {
        $request = DB::transaction(function () use ($request) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, [StockRequest::STATUS_DRAFT]);

            $request->update([
                'status' => StockRequest::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'source_inventory_source_id' => $request->source_inventory_source_id ?? $this->locationScope->centralWarehouseId(),
            ]);

            $this->approvals->record($request, 'submitted', StockRequest::STATUS_DRAFT, StockRequest::STATUS_SUBMITTED);

            return $request;
        });

        $this->notifications->notifyPermission(
            'operations.stock_requests.allocate',
            'stock_request_submitted',
            trans('operations::app.notifications.stock-request-submitted', [
                'reference' => $request->reference,
                'location' => $request->location?->name,
            ]),
            null,
            route('admin.operations.stock_requests.allocation', $request->id),
            $request->source_inventory_source_id,
        );

        return $request;
    }

    public function cancel(StockRequest $request, ?string $remarks): StockRequest
    {
        return DB::transaction(function () use ($request, $remarks) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, [StockRequest::STATUS_DRAFT, StockRequest::STATUS_SUBMITTED]);

            $from = $request->status;

            $request->update(['status' => StockRequest::STATUS_CANCELLED]);

            $this->approvals->record($request, 'cancelled', $from, StockRequest::STATUS_CANCELLED, $remarks);

            return $request;
        });
    }

    public function reject(StockRequest $request, string $remarks): StockRequest
    {
        $request = DB::transaction(function () use ($request, $remarks) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, [StockRequest::STATUS_SUBMITTED]);

            $request->update(['status' => StockRequest::STATUS_REJECTED]);

            $this->approvals->record($request, 'rejected', StockRequest::STATUS_SUBMITTED, StockRequest::STATUS_REJECTED, $remarks);

            return $request;
        });

        if ($request->requested_by) {
            $this->notifications->notifyAdmin(
                $request->requested_by,
                'stock_request_status',
                trans('operations::app.notifications.stock-request-status', [
                    'reference' => $request->reference,
                    'status' => trans('operations::app.statuses.rejected'),
                ]),
                $remarks,
                route('admin.operations.stock_requests.view', $request->id),
            );
        }

        return $request;
    }

    /**
     * Per-line allocation picture for the allocation screen.
     *
     * @return array<int, array> keyed by stock request item id
     */
    public function suggestions(StockRequest $request, int $warehouseId): array
    {
        $policies = StockPolicy::whereIn('product_id', $request->items->pluck('product_id'))
            ->whereIn('inventory_source_id', [$warehouseId, $request->inventory_source_id])
            ->get()
            ->groupBy('inventory_source_id');

        $warehousePolicies = ($policies[$warehouseId] ?? collect())->keyBy('product_id');
        $branchPolicies = ($policies[$request->inventory_source_id] ?? collect())->keyBy('product_id');

        $lines = [];

        foreach ($request->items as $item) {
            $transferable = $this->stockLedger->transferableQty($item->product_id, $warehouseId);
            $committed = $this->transfers->committedQty($item->product_id, $warehouseId);
            $available = max(0, $transferable - $committed);
            $warehouseMin = (int) ($warehousePolicies[$item->product_id]->min_stock ?? 0);
            $spare = max(0, $available - $warehouseMin);
            $outstanding = $item->outstandingQty();

            $suggested = min($outstanding, $spare);

            $branchOnHand = $this->stockLedger->sellableQty($item->product_id, $request->inventory_source_id);
            $branchMax = $branchPolicies[$item->product_id]->max_stock ?? null;

            if ($branchMax !== null) {
                $suggested = min($suggested, max(0, $branchMax - $branchOnHand));
            }

            $lines[$item->id] = [
                'item' => $item,
                'requested' => $item->qty_requested,
                'allocated' => $item->qty_allocated,
                'outstanding' => $outstanding,
                'warehouse_available' => $available,
                'warehouse_committed' => $committed,
                'warehouse_min' => $warehouseMin,
                'branch_on_hand' => $branchOnHand,
                'branch_reorder' => $branchPolicies[$item->product_id]->reorder_level ?? null,
                'branch_max' => $branchMax,
                'suggested' => $suggested,
            ];
        }

        return $lines;
    }

    /**
     * Allocate from a warehouse. Creates an approved replenishment transfer for the
     * allocated units; the rest stays on backorder. No stock moves until dispatch.
     *
     * @param  array  $allocations  [stock request item id => ['qty' => int, 'reason' => ?string]]
     */
    public function allocate(StockRequest $request, int $warehouseId, array $allocations): ?Transfer
    {
        if ($warehouseId === (int) $request->inventory_source_id) {
            throw OperationsException::trans('same-location');
        }

        $transfer = DB::transaction(function () use ($request, $warehouseId, $allocations) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, StockRequest::ALLOCATABLE_STATUSES);

            $request->load('items');

            $suggestions = $this->suggestions($request, $warehouseId);

            $lines = [];

            foreach ($request->items as $item) {
                $qty = (int) ($allocations[$item->id]['qty'] ?? 0);
                $reason = trim((string) ($allocations[$item->id]['reason'] ?? ''));
                $line = $suggestions[$item->id];

                if ($qty < 0 || $qty > $line['outstanding']) {
                    throw OperationsException::trans('over-allocation', ['sku' => $item->sku, 'outstanding' => $line['outstanding']]);
                }

                if ($qty > $line['warehouse_available']) {
                    throw OperationsException::trans('insufficient-stock', [
                        'sku' => $item->sku,
                        'available' => $line['warehouse_available'],
                        'requested' => $qty,
                    ]);
                }

                if ($qty !== $line['suggested'] && $reason === '') {
                    throw OperationsException::trans('override-reason-required', ['sku' => $item->sku]);
                }

                $item->update([
                    'qty_suggested' => $line['suggested'],
                    'qty_allocated' => $item->qty_allocated + $qty,
                    'override_reason' => $qty !== $line['suggested'] ? $reason : $item->override_reason,
                ]);

                if ($qty > 0) {
                    $lines[] = [$item, $qty];
                }
            }

            $transfer = null;

            if ($lines) {
                $transfer = Transfer::create([
                    'reference' => $this->references->next('TR'),
                    'type' => Transfer::TYPE_REPLENISHMENT,
                    'stock_request_id' => $request->id,
                    'source_inventory_source_id' => $warehouseId,
                    'destination_inventory_source_id' => $request->inventory_source_id,
                    'status' => Transfer::STATUS_APPROVED,
                    'priority' => $request->priority,
                    'reason' => trans('operations::app.stock-requests.transfer-reason', ['reference' => $request->reference]),
                    'requested_by' => auth()->guard('admin')->id(),
                    'approval_required' => false,
                    'submitted_at' => now(),
                    'approved_at' => now(),
                ]);

                foreach ($lines as [$item, $qty]) {
                    $transfer->items()->create([
                        'stock_request_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'qty_requested' => $qty,
                    ]);
                }

                $this->approvals->record($transfer, 'created', null, Transfer::STATUS_APPROVED, $request->reference);
            }

            $request->update([
                'source_inventory_source_id' => $warehouseId,
                'allocated_by' => auth()->guard('admin')->id(),
                'allocated_at' => now(),
            ]);

            $this->approvals->record(
                $request,
                'allocated',
                $request->status,
                $request->status,
                collect($lines)->map(fn ($line) => $line[0]->sku.' × '.$line[1])->implode(', ') ?: null,
            );

            $this->transfers->syncStockRequest($request->id);

            return $transfer;
        });

        if ($transfer) {
            $this->notifications->notifyPermission(
                'operations.transfers.pick',
                'transfer_ready',
                trans('operations::app.notifications.transfer-ready', ['reference' => $transfer->reference]),
                null,
                route('admin.operations.transfers.view', $transfer->id),
                $warehouseId,
            );
        }

        return $transfer;
    }
}
