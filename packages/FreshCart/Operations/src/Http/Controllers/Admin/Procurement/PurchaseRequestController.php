<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Procurement;

use FreshCart\Operations\DataGrids\Procurement\PurchaseRequestDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\PurchaseOrderItem;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Services\ProcurementService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PurchaseRequestDataGrid::class)->process();
        }

        return view('operations::admin.purchase-requests.index');
    }

    public function create(): View
    {
        return view('operations::admin.purchase-requests.form', [
            'purchaseRequest' => new PurchaseRequest(['priority' => 'normal']),
            'locationOptions' => $this->locations()->options(),
        ]);
    }

    public function store()
    {
        [$data, $items] = $this->validated();

        return $this->attempt(
            fn () => $this->procurement->savePurchaseRequest($data, $items),
            trans('operations::app.purchase-requests.saved'),
            fn ($request) => redirect()->route('admin.operations.purchase_requests.view', $request->id),
        );
    }

    public function view(int $id): View
    {
        $purchaseRequest = $this->find($id)->load(['items', 'requester', 'location']);

        $orderLines = PurchaseOrderItem::with('purchaseOrder')
            ->whereIn('purchase_request_item_id', $purchaseRequest->items->pluck('id'))
            ->get();

        return view('operations::admin.purchase-requests.view', compact('purchaseRequest', 'orderLines'));
    }

    public function edit(int $id): View
    {
        $purchaseRequest = $this->findEditable($id);

        return view('operations::admin.purchase-requests.form', [
            'purchaseRequest' => $purchaseRequest->load('items'),
            'locationOptions' => $this->locations()->options(),
        ]);
    }

    public function update(int $id)
    {
        $purchaseRequest = $this->findEditable($id);

        [$data, $items] = $this->validated();

        return $this->attempt(
            fn () => $this->procurement->savePurchaseRequest($data, $items, $purchaseRequest),
            trans('operations::app.purchase-requests.saved'),
            fn ($request) => redirect()->route('admin.operations.purchase_requests.view', $request->id),
        );
    }

    public function submit(int $id)
    {
        $purchaseRequest = $this->findEditable($id);

        return $this->attempt(
            fn () => $this->procurement->submitPurchaseRequest($purchaseRequest),
            trans('operations::app.purchase-requests.submitted'),
        );
    }

    public function approve(int $id)
    {
        $purchaseRequest = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(
            fn () => $this->procurement->approvePurchaseRequest($purchaseRequest, $remarks),
            trans('operations::app.common.approved-message'),
        );
    }

    public function reject(int $id)
    {
        $purchaseRequest = $this->find($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(
            fn () => $this->procurement->rejectPurchaseRequest($purchaseRequest, $remarks),
            trans('operations::app.common.rejected-message'),
        );
    }

    public function cancel(int $id)
    {
        $purchaseRequest = $this->find($id);

        abort_unless($purchaseRequest->requested_by === $this->adminId() || bouncer()->hasPermission('operations.purchase_requests.approve'), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(
            fn () => $this->procurement->cancelPurchaseRequest($purchaseRequest, $remarks),
            trans('operations::app.common.cancelled-message'),
        );
    }

    protected function find(int $id): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::findOrFail($id);

        $this->locations()->authorize($purchaseRequest->inventory_source_id);

        return $purchaseRequest;
    }

    /**
     * Drafts may be changed only by their requester.
     */
    protected function findEditable(int $id): PurchaseRequest
    {
        $purchaseRequest = $this->find($id);

        abort_unless($purchaseRequest->requested_by === $this->adminId(), 403);

        abort_unless($purchaseRequest->isEditable(), 403, trans('operations::app.errors.not-editable'));

        return $purchaseRequest;
    }

    protected function validated(): array
    {
        $validated = request()->validate([
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'priority' => ['required', Rule::in(PurchaseRequest::PRIORITIES)],
            'needed_by' => 'nullable|date|after_or_equal:today',
            'justification' => 'required|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:1000000',
            'items.*.estimated_unit_cost' => 'nullable|numeric|min:0|max:100000000',
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        return [
            collect($validated)->only(['inventory_source_id', 'priority', 'needed_by', 'justification'])->all(),
            $validated['items'],
        ];
    }
}
