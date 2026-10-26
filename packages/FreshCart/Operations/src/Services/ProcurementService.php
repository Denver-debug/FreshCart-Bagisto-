<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\GoodsReceiptItem;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\PurchaseOrderItem;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\PurchaseRequestItem;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Models\SupplierReturn;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Module A — purchase request → approval → purchase order → receiving (P1, P2).
 */
class ProcurementService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected StockLedgerService $stockLedger,
        protected NotificationService $notifications,
        protected ProductCatalog $productCatalog,
        protected Settings $settings,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Purchase requests
    |--------------------------------------------------------------------------
    */

    public function savePurchaseRequest(array $data, array $items, ?PurchaseRequest $request = null): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $items, $request) {
            if ($request) {
                $request = $this->approvals->lock($request);

                if (! $request->isEditable()) {
                    throw OperationsException::trans('not-editable');
                }

                $request->update($data);
                $request->items()->delete();
            } else {
                $request = PurchaseRequest::create($data + [
                    'reference' => $this->references->next('PR'),
                    'status' => PurchaseRequest::STATUS_DRAFT,
                    'requested_by' => auth()->guard('admin')->id(),
                ]);
            }

            $total = 0;

            foreach ($this->normaliseItems($items) as $item) {
                $product = $this->productCatalog->stockable($item['product_id']);

                $cost = $item['estimated_unit_cost'] ?? $this->productCatalog->cost($product->id);

                $request->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name ?? $product->sku,
                    'qty' => $item['qty'],
                    'estimated_unit_cost' => $cost,
                    'preferred_supplier_id' => $item['preferred_supplier_id'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);

                $total += $item['qty'] * (float) $cost;
            }

            $request->update(['estimated_total' => $total]);

            if (! $request->wasRecentlyCreated) {
                $this->approvals->record($request, 'updated', $request->status, $request->status);
            } else {
                $this->approvals->record($request, 'created', null, PurchaseRequest::STATUS_DRAFT);
            }

            return $request->fresh('items');
        });
    }

    public function submitPurchaseRequest(PurchaseRequest $request): PurchaseRequest
    {
        if (! $request->items()->exists()) {
            throw OperationsException::trans('no-items');
        }

        return $this->approvals->submit($request, (float) $request->estimated_total);
    }

    public function approvePurchaseRequest(PurchaseRequest $request, ?string $remarks): PurchaseRequest
    {
        $request = $this->approvals->approve($request, $remarks, PurchaseRequest::STATUS_APPROVED);

        if ($request->isFullyApproved()) {
            $this->notifications->notifyPermission(
                'operations.purchase_orders.create',
                'purchase_request_approved',
                trans('operations::app.notifications.pr-ready-for-po', ['reference' => $request->reference]),
                null,
                route('admin.operations.purchase_requests.view', $request->id),
            );
        }

        return $request;
    }

    public function rejectPurchaseRequest(PurchaseRequest $request, string $remarks): PurchaseRequest
    {
        return $this->approvals->reject($request, $remarks);
    }

    public function cancelPurchaseRequest(PurchaseRequest $request, ?string $remarks): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $remarks) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, [PurchaseRequest::STATUS_DRAFT, PurchaseRequest::STATUS_SUBMITTED]);

            $from = $request->status;

            $request->update(['status' => PurchaseRequest::STATUS_CANCELLED]);

            $this->approvals->record($request, 'cancelled', $from, PurchaseRequest::STATUS_CANCELLED, $remarks);

            return $request;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Purchase orders
    |--------------------------------------------------------------------------
    */

    /**
     * Create one purchase order for a supplier from lines of approved requests.
     *
     * @param  array  $lines  [{purchase_request_item_id, qty, unit_cost}]
     */
    public function createPurchaseOrder(array $data, array $lines): PurchaseOrder
    {
        if (empty($lines)) {
            throw OperationsException::trans('no-items');
        }

        return DB::transaction(function () use ($data, $lines) {
            $supplier = Supplier::where('status', 1)->find($data['supplier_id']);

            if (! $supplier) {
                throw OperationsException::trans('supplier-inactive');
            }

            $requestItems = PurchaseRequestItem::with('purchaseRequest')
                ->whereIn('id', array_column($lines, 'purchase_request_item_id'))
                ->get()
                ->keyBy('id');

            $requestIds = $requestItems->pluck('purchase_request_id')->unique();

            // Lock every source request: only approved requests may be converted (and only once).
            $requests = PurchaseRequest::whereIn('id', $requestIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($requests as $request) {
                if ($request->status !== PurchaseRequest::STATUS_APPROVED) {
                    throw OperationsException::trans('pr-not-approved', ['reference' => $request->reference]);
                }
            }

            $order = PurchaseOrder::create([
                'reference' => $this->references->next('PO'),
                'supplier_id' => $supplier->id,
                'inventory_source_id' => $data['inventory_source_id'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => core()->getBaseCurrencyCode(),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->guard('admin')->id(),
            ]);

            $subtotal = 0;

            foreach ($lines as $line) {
                $requestItem = $requestItems->get($line['purchase_request_item_id']);

                if (! $requestItem) {
                    throw OperationsException::trans('no-items');
                }

                $alreadyOrdered = (int) PurchaseOrderItem::where('purchase_request_item_id', $requestItem->id)
                    ->whereHas('purchaseOrder', fn ($query) => $query->where('status', '!=', PurchaseOrder::STATUS_CANCELLED))
                    ->sum('qty_ordered');

                $remaining = $requestItem->qty - $alreadyOrdered;

                if ($line['qty'] > $remaining) {
                    throw OperationsException::trans('po-exceeds-request', [
                        'sku' => $requestItem->sku,
                        'remaining' => max(0, $remaining),
                    ]);
                }

                $lineTotal = $line['qty'] * $line['unit_cost'];

                $order->items()->create([
                    'purchase_request_item_id' => $requestItem->id,
                    'product_id' => $requestItem->product_id,
                    'sku' => $requestItem->sku,
                    'name' => $requestItem->name,
                    'qty_ordered' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $order->update(['subtotal' => $subtotal]);

            $this->approvals->record($order, 'created', null, PurchaseOrder::STATUS_DRAFT);

            foreach ($requests as $request) {
                if ($this->isFullyOrdered($request)) {
                    $request->update(['status' => PurchaseRequest::STATUS_CONVERTED, 'converted_at' => now()]);

                    $this->approvals->record($request, 'converted', PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_CONVERTED, $order->reference);
                }
            }

            return $order->fresh('items');
        });
    }

    public function issuePurchaseOrder(PurchaseOrder $order): PurchaseOrder
    {
        $order = $this->transition($order, [PurchaseOrder::STATUS_DRAFT], PurchaseOrder::STATUS_ISSUED, 'issued', ['issued_at' => now()]);

        $this->notifications->notifyPermission(
            'operations.receiving.create',
            'purchase_order_issued',
            trans('operations::app.notifications.po-expected', [
                'reference' => $order->reference,
                'date' => $order->expected_delivery_date?->toDateString() ?? '—',
            ]),
            null,
            route('admin.operations.purchase_orders.view', $order->id),
            $order->inventory_source_id,
        );

        return $order;
    }

    public function closePurchaseOrder(PurchaseOrder $order, ?string $remarks): PurchaseOrder
    {
        return $this->transition($order, [PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_ISSUED], PurchaseOrder::STATUS_CLOSED, 'closed', ['closed_at' => now()], $remarks);
    }

    public function cancelPurchaseOrder(PurchaseOrder $order, ?string $remarks): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $remarks) {
            $order = $this->transition($order, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ISSUED], PurchaseOrder::STATUS_CANCELLED, 'cancelled', [], $remarks);

            if ($order->receipts()->exists()) {
                throw OperationsException::trans('po-has-receipts');
            }

            // Lines return to their requests, which become convertible again.
            $requestIds = PurchaseRequestItem::whereIn('id', $order->items()->pluck('purchase_request_item_id')->filter())
                ->pluck('purchase_request_id')
                ->unique();

            foreach (PurchaseRequest::whereIn('id', $requestIds)->lockForUpdate()->get() as $request) {
                if ($request->status === PurchaseRequest::STATUS_CONVERTED) {
                    $request->update(['status' => PurchaseRequest::STATUS_APPROVED, 'converted_at' => null]);

                    $this->approvals->record($request, 'reopened', PurchaseRequest::STATUS_CONVERTED, PurchaseRequest::STATUS_APPROVED, $order->reference);
                }
            }

            return $order;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Receiving
    |--------------------------------------------------------------------------
    */

    /**
     * Post a goods receipt against a purchase order.
     *
     * Each line: purchase_order_item_id, qty_received, qty_rejected, batch_no,
     * expiry_date, storage_location, discrepancy_note, over_receipt_reason.
     * Accepted units (received − rejected) enter sellable stock at the PO's
     * delivery location. A replayed submission (same idempotency key) returns
     * the original receipt without touching stock.
     */
    public function receive(PurchaseOrder $order, array $data, array $lines): GoodsReceipt
    {
        if ($existing = GoodsReceipt::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing;
        }

        $lines = array_values(array_filter($lines, fn ($line) => (int) ($line['qty_received'] ?? 0) > 0));

        if (empty($lines)) {
            throw OperationsException::trans('no-items');
        }

        try {
            return DB::transaction(fn () => $this->postReceipt($order, $data, $lines));
        } catch (QueryException $e) {
            // Unique idempotency key hit by a concurrent duplicate submission.
            if ($existing = GoodsReceipt::where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }

            throw $e;
        }
    }

    protected function postReceipt(PurchaseOrder $order, array $data, array $lines): GoodsReceipt
    {
        $order = $this->approvals->lock($order);

        if (! $order->isReceivable()) {
            throw OperationsException::trans('po-not-receivable', ['status' => trans('operations::app.statuses.'.$order->status)]);
        }

        $orderItems = $order->items()->lockForUpdate()->get()->keyBy('id');

        $tolerance = $this->settings->overReceiptTolerancePercent();
        $canOverride = bouncer()->hasPermission('operations.receiving.override');

        $receipt = GoodsReceipt::create([
            'reference' => $this->references->next('GRN'),
            'purchase_order_id' => $order->id,
            'inventory_source_id' => $order->inventory_source_id,
            'received_by' => auth()->guard('admin')->id(),
            'delivery_note_no' => $data['delivery_note_no'] ?? null,
            'received_at' => now(),
            'remarks' => $data['remarks'] ?? null,
            'idempotency_key' => $data['idempotency_key'],
        ]);

        $hasDiscrepancy = false;
        $rejectedLines = [];

        foreach ($lines as $line) {
            $orderItem = $orderItems->get((int) $line['purchase_order_item_id']);

            if (! $orderItem) {
                throw OperationsException::trans('line-not-on-po');
            }

            $received = (int) $line['qty_received'];
            $rejected = (int) ($line['qty_rejected'] ?? 0);

            if ($rejected > $received) {
                throw OperationsException::trans('rejected-exceeds-received', ['sku' => $orderItem->sku]);
            }

            $accepted = $received - $rejected;

            $allowed = (int) floor($orderItem->qty_ordered * (1 + $tolerance / 100)) - $orderItem->qty_received;

            $overReason = trim((string) ($line['over_receipt_reason'] ?? ''));

            $overReceipt = $accepted > $allowed;

            if ($overReceipt && (! $canOverride || $overReason === '')) {
                throw OperationsException::trans('over-receipt', [
                    'sku' => $orderItem->sku,
                    'allowed' => max(0, $allowed),
                ]);
            }

            $lot = null;

            if ($accepted > 0) {
                $lot = $this->stockLedger->receive($orderItem->product_id, $order->inventory_source_id, $accepted, [
                    'type' => 'purchase_receipt',
                    'reference' => $receipt,
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'unit_cost' => (float) $orderItem->unit_cost,
                    'reason' => $order->reference,
                ]);
            }

            $lineDiscrepancy = $rejected > 0
                || $overReceipt
                || ! empty($line['discrepancy_note']);

            $hasDiscrepancy = $hasDiscrepancy || $lineDiscrepancy;

            $receiptItem = $receipt->items()->create([
                'purchase_order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'sku' => $orderItem->sku,
                'qty_received' => $received,
                'qty_accepted' => $accepted,
                'qty_rejected' => $rejected,
                'batch_no' => $line['batch_no'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'storage_location' => $line['storage_location'] ?? null,
                'inspection_result' => $rejected === 0
                    ? GoodsReceiptItem::INSPECTION_PASSED
                    : ($accepted === 0 ? GoodsReceiptItem::INSPECTION_FAILED : GoodsReceiptItem::INSPECTION_PARTIAL),
                'discrepancy_note' => $line['discrepancy_note'] ?? null,
                'over_receipt_reason' => $overReceipt ? $overReason : null,
                'over_receipt_approved_by' => $overReceipt ? auth()->guard('admin')->id() : null,
                'lot_id' => $lot?->id,
            ]);

            $orderItem->increment('qty_received', $accepted);
            $orderItem->increment('qty_rejected', $rejected);

            if ($rejected > 0) {
                $rejectedLines[] = [$receiptItem, $orderItem, $rejected];
            }
        }

        $receipt->update(['has_discrepancy' => $hasDiscrepancy]);

        $outstanding = $order->items()->get()->sum(fn ($item) => $item->outstandingQty());

        $newStatus = $outstanding > 0 ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED : PurchaseOrder::STATUS_RECEIVED;

        if ($newStatus !== $order->status) {
            $from = $order->status;

            $order->update(['status' => $newStatus]);

            $this->approvals->record($order, 'received', $from, $newStatus, $receipt->reference);
        }

        $this->approvals->record($receipt, 'posted', null, 'posted', $data['remarks'] ?? null);

        if ($rejectedLines) {
            $this->createReturnForRejected($order, $receipt, $rejectedLines);
        }

        if ($hasDiscrepancy) {
            $this->notifications->notifyPermission(
                'operations.purchase_orders.create',
                'receiving_discrepancy',
                trans('operations::app.notifications.receiving-discrepancy', ['reference' => $receipt->reference, 'po' => $order->reference]),
                null,
                route('admin.operations.receiving.view', $receipt->id),
            );
        }

        return $receipt->fresh('items');
    }

    /**
     * Rejected units never entered stock; record them for return/replacement tracking.
     */
    protected function createReturnForRejected(PurchaseOrder $order, GoodsReceipt $receipt, array $rejectedLines): SupplierReturn
    {
        $return = SupplierReturn::create([
            'reference' => $this->references->next('SRT'),
            'supplier_id' => $order->supplier_id,
            'inventory_source_id' => $order->inventory_source_id,
            'status' => SupplierReturn::STATUS_PENDING,
            'created_by' => auth()->guard('admin')->id(),
            'notes' => trans('operations::app.supplier-returns.from-receipt', ['reference' => $receipt->reference]),
        ]);

        foreach ($rejectedLines as [$receiptItem, $orderItem, $qty]) {
            $return->items()->create([
                'product_id' => $orderItem->product_id,
                'sku' => $orderItem->sku,
                'name' => $orderItem->name,
                'qty' => $qty,
                'unit_cost' => $orderItem->unit_cost,
                'origin_type' => $receiptItem->getMorphClass(),
                'origin_id' => $receiptItem->id,
            ]);
        }

        $this->approvals->record($return, 'created', null, SupplierReturn::STATUS_PENDING, $receipt->reference);

        return $return;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function isFullyOrdered(PurchaseRequest $request): bool
    {
        foreach ($request->items as $item) {
            $ordered = (int) PurchaseOrderItem::where('purchase_request_item_id', $item->id)
                ->whereHas('purchaseOrder', fn ($query) => $query->where('status', '!=', PurchaseOrder::STATUS_CANCELLED))
                ->sum('qty_ordered');

            if ($ordered < $item->qty) {
                return false;
            }
        }

        return true;
    }

    protected function transition(PurchaseOrder $order, array $from, string $to, string $action, array $extra = [], ?string $remarks = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $from, $to, $action, $extra, $remarks) {
            $order = $this->approvals->lock($order);

            $this->approvals->assertStatus($order, $from);

            $previous = $order->status;

            $order->forceFill(['status' => $to] + $extra)->save();

            $this->approvals->record($order, $action, $previous, $to, $remarks);

            return $order;
        });
    }

    /**
     * Merge duplicate product rows and drop empty ones.
     */
    protected function normaliseItems(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            if (! $productId || $qty <= 0) {
                continue;
            }

            if (isset($merged[$productId])) {
                $merged[$productId]['qty'] += $qty;

                continue;
            }

            $merged[$productId] = [
                'product_id' => $productId,
                'qty' => $qty,
                'estimated_unit_cost' => isset($item['estimated_unit_cost']) && $item['estimated_unit_cost'] !== '' ? (float) $item['estimated_unit_cost'] : null,
                'preferred_supplier_id' => ! empty($item['preferred_supplier_id']) ? (int) $item['preferred_supplier_id'] : null,
                'notes' => $item['notes'] ?? null,
            ];
        }

        if (empty($merged)) {
            throw OperationsException::trans('no-items');
        }

        return array_values($merged);
    }
}
