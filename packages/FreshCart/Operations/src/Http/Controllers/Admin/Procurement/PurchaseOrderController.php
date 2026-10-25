<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Procurement;

use FreshCart\Operations\DataGrids\Procurement\PurchaseOrderDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\PurchaseOrderItem;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Services\ProcurementService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Core\Traits\PDFHandler;

class PurchaseOrderController extends Controller
{
    use PDFHandler;

    public function __construct(protected ProcurementService $procurement) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PurchaseOrderDataGrid::class)->process();
        }

        return view('operations::admin.purchase-orders.index');
    }

    /**
     * Pick approved request lines to place with one supplier.
     */
    public function create(): View
    {
        $requestIds = array_filter((array) request('request_ids', []), 'is_numeric');

        $requests = PurchaseRequest::with(['items', 'location'])
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->forLocations($this->locations()->ids())
            ->when($requestIds, fn ($query) => $query->whereIn('id', $requestIds))
            ->orderBy('needed_by')
            ->get();

        $ordered = PurchaseOrderItem::whereIn('purchase_request_item_id', $requests->flatMap->items->pluck('id'))
            ->whereHas('purchaseOrder', fn ($query) => $query->where('status', '!=', PurchaseOrder::STATUS_CANCELLED))
            ->selectRaw('purchase_request_item_id, SUM(qty_ordered) as qty')
            ->groupBy('purchase_request_item_id')
            ->pluck('qty', 'purchase_request_item_id');

        return view('operations::admin.purchase-orders.create', [
            'requests' => $requests,
            'ordered' => $ordered,
            'suppliers' => Supplier::where('status', 1)->orderBy('name')->pluck('name', 'id'),
            'locationOptions' => $this->locations()->options(),
        ]);
    }

    public function store()
    {
        $validated = request()->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('fc_suppliers', 'id')->whereNull('deleted_at')],
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'expected_delivery_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.selected' => 'nullable|boolean',
            'lines.*.purchase_request_item_id' => 'required|integer|exists:fc_purchase_request_items,id',
            'lines.*.qty' => 'required|integer|min:0',
            'lines.*.unit_cost' => 'required|numeric|min:0|max:100000000',
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        $lines = collect($validated['lines'])
            ->filter(fn ($line) => ! empty($line['selected']) && (int) $line['qty'] > 0)
            ->map(fn ($line) => [
                'purchase_request_item_id' => (int) $line['purchase_request_item_id'],
                'qty' => (int) $line['qty'],
                'unit_cost' => (float) $line['unit_cost'],
            ])
            ->values()
            ->all();

        return $this->attempt(
            fn () => $this->procurement->createPurchaseOrder($validated, $lines),
            trans('operations::app.purchase-orders.created'),
            fn ($order) => redirect()->route('admin.operations.purchase_orders.view', $order->id),
        );
    }

    public function view(int $id): View
    {
        $purchaseOrder = $this->find($id)->load(['items', 'supplier', 'location', 'receipts.receiver', 'creator']);

        return view('operations::admin.purchase-orders.view', compact('purchaseOrder'));
    }

    public function print(int $id)
    {
        $purchaseOrder = $this->find($id)->load(['items', 'supplier', 'location', 'creator']);

        return $this->downloadPDF(
            view('operations::admin.purchase-orders.pdf', compact('purchaseOrder'))->render(),
            $purchaseOrder->reference
        );
    }

    public function issue(int $id)
    {
        $purchaseOrder = $this->find($id);

        return $this->attempt(
            fn () => $this->procurement->issuePurchaseOrder($purchaseOrder),
            trans('operations::app.purchase-orders.issued'),
        );
    }

    public function close(int $id)
    {
        $purchaseOrder = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(
            fn () => $this->procurement->closePurchaseOrder($purchaseOrder, $remarks),
            trans('operations::app.purchase-orders.closed'),
        );
    }

    public function cancel(int $id)
    {
        $purchaseOrder = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(
            fn () => $this->procurement->cancelPurchaseOrder($purchaseOrder, $remarks),
            trans('operations::app.common.cancelled-message'),
        );
    }

    protected function find(int $id): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        $this->locations()->authorize($purchaseOrder->inventory_source_id);

        return $purchaseOrder;
    }
}
