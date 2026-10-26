<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Procurement;

use FreshCart\Operations\DataGrids\Procurement\GoodsReceiptDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Services\ProcurementService;
use FreshCart\Operations\Support\Settings;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(GoodsReceiptDataGrid::class)->process();
        }

        $expected = PurchaseOrder::with(['supplier', 'location'])
            ->whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)
            ->forLocations($this->locations()->ids())
            ->orderByRaw('expected_delivery_date IS NULL')
            ->orderBy('expected_delivery_date')
            ->limit(50)
            ->get();

        return view('operations::admin.receiving.index', compact('expected'));
    }

    public function create(int $purchaseOrderId): View
    {
        $purchaseOrder = $this->findOrder($purchaseOrderId)->load(['items', 'supplier', 'location']);

        abort_unless($purchaseOrder->isReceivable(), 403, trans('operations::app.errors.po-not-receivable', [
            'status' => trans('operations::app.statuses.'.$purchaseOrder->status),
        ]));

        return view('operations::admin.receiving.create', [
            'purchaseOrder' => $purchaseOrder,
            'tolerance' => app(Settings::class)->overReceiptTolerancePercent(),
            'canOverride' => bouncer()->hasPermission('operations.receiving.override'),
        ]);
    }

    public function store(int $purchaseOrderId)
    {
        $purchaseOrder = $this->findOrder($purchaseOrderId);

        $validated = request()->validate([
            'idempotency_key' => 'required|uuid',
            'delivery_note_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.purchase_order_item_id' => 'required|integer',
            'lines.*.qty_received' => 'nullable|integer|min:0|max:1000000',
            'lines.*.qty_rejected' => 'nullable|integer|min:0|lte:lines.*.qty_received',
            'lines.*.batch_no' => 'nullable|string|max:100',
            'lines.*.expiry_date' => 'nullable|date',
            'lines.*.storage_location' => 'nullable|string|max:100',
            'lines.*.discrepancy_note' => 'nullable|string|max:1000',
            'lines.*.over_receipt_reason' => 'nullable|string|max:1000',
        ]);

        return $this->attempt(
            fn () => $this->procurement->receive($purchaseOrder, $validated, $validated['lines']),
            trans('operations::app.receiving.posted'),
            fn ($receipt) => redirect()->route('admin.operations.receiving.view', $receipt->id),
        );
    }

    public function view(int $id): View
    {
        $receipt = GoodsReceipt::with(['items.purchaseOrderItem', 'purchaseOrder.supplier', 'location', 'receiver'])->findOrFail($id);

        $this->locations()->authorize($receipt->inventory_source_id);

        return view('operations::admin.receiving.view', compact('receipt'));
    }

    protected function findOrder(int $id): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        $this->locations()->authorize($purchaseOrder->inventory_source_id);

        return $purchaseOrder;
    }
}
