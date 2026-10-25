<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Distribution;

use FreshCart\Operations\DataGrids\Distribution\StockRequestDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\StockRequest;
use FreshCart\Operations\Services\DistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockRequestController extends Controller
{
    public function __construct(protected DistributionService $distribution) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(StockRequestDataGrid::class)->process();
        }

        return view('operations::admin.stock-requests.index');
    }

    public function create(): View
    {
        return view('operations::admin.stock-requests.form', [
            'stockRequest' => new StockRequest(['priority' => 'normal']),
            'branchOptions' => $this->locations()->options(),
        ]);
    }

    public function store()
    {
        [$data, $items] = $this->validated();

        return $this->attempt(
            fn () => $this->distribution->save($data, $items),
            trans('operations::app.stock-requests.saved'),
            fn ($request) => redirect()->route('admin.operations.stock_requests.view', $request->id),
        );
    }

    public function view(int $id): View
    {
        $stockRequest = $this->find($id)->load(['items', 'transfers', 'location', 'sourceLocation', 'requester']);

        return view('operations::admin.stock-requests.view', compact('stockRequest'));
    }

    public function edit(int $id): View
    {
        $stockRequest = $this->findDraft($id)->load('items');

        return view('operations::admin.stock-requests.form', [
            'stockRequest' => $stockRequest,
            'branchOptions' => $this->locations()->options(),
        ]);
    }

    public function update(int $id)
    {
        $stockRequest = $this->findDraft($id);

        [$data, $items] = $this->validated();

        return $this->attempt(
            fn () => $this->distribution->save($data, $items, $stockRequest),
            trans('operations::app.stock-requests.saved'),
            fn ($request) => redirect()->route('admin.operations.stock_requests.view', $request->id),
        );
    }

    public function submit(int $id)
    {
        $stockRequest = $this->findDraft($id);

        return $this->attempt(
            fn () => $this->distribution->submit($stockRequest),
            trans('operations::app.stock-requests.submitted'),
        );
    }

    public function cancel(int $id)
    {
        $stockRequest = $this->find($id);

        abort_unless($stockRequest->requested_by === $this->adminId(), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(
            fn () => $this->distribution->cancel($stockRequest, $remarks),
            trans('operations::app.common.cancelled-message'),
        );
    }

    public function reject(int $id)
    {
        $stockRequest = $this->findForAllocation($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(
            fn () => $this->distribution->reject($stockRequest, $remarks),
            trans('operations::app.common.rejected-message'),
            fn () => redirect()->route('admin.operations.stock_requests.view', $stockRequest->id),
        );
    }

    /**
     * Allocation screen: requested, available, allocated and outstanding per line.
     */
    public function allocation(int $id): View
    {
        $stockRequest = $this->findForAllocation($id)->load(['items', 'location']);

        $warehouses = DB::table('inventory_sources')
            ->join('fc_locations', 'fc_locations.inventory_source_id', '=', 'inventory_sources.id')
            ->where('fc_locations.type', 'warehouse')
            ->where('inventory_sources.status', 1)
            ->where('inventory_sources.id', '!=', $stockRequest->inventory_source_id)
            ->when($this->locations()->ids() !== null, fn ($query) => $query->whereIn('inventory_sources.id', $this->locations()->ids()))
            ->orderByDesc('fc_locations.is_central')
            ->pluck('inventory_sources.name', 'inventory_sources.id');

        $warehouseId = (int) (request('warehouse_id') ?: $stockRequest->source_inventory_source_id ?: $warehouses->keys()->first());

        abort_unless($warehouses->has($warehouseId), 403, trans('operations::app.errors.no-warehouse'));

        return view('operations::admin.stock-requests.allocate', [
            'stockRequest' => $stockRequest,
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'lines' => $this->distribution->suggestions($stockRequest, $warehouseId),
        ]);
    }

    public function allocate(int $id)
    {
        $stockRequest = $this->findForAllocation($id);

        $validated = request()->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('fc_locations', 'inventory_source_id')->where('type', 'warehouse')],
            'allocations' => 'required|array',
            'allocations.*.qty' => 'required|integer|min:0',
            'allocations.*.reason' => 'nullable|string|max:500',
        ]);

        $this->locations()->authorize((int) $validated['warehouse_id']);

        return $this->attempt(
            fn () => $this->distribution->allocate($stockRequest, (int) $validated['warehouse_id'], $validated['allocations']),
            trans('operations::app.stock-requests.allocated'),
            fn ($transfer) => $transfer
                ? redirect()->route('admin.operations.transfers.view', $transfer->id)
                : redirect()->route('admin.operations.stock_requests.view', $stockRequest->id),
        );
    }

    protected function find(int $id): StockRequest
    {
        $stockRequest = StockRequest::findOrFail($id);

        $this->locations()->authorizeAny(array_filter([$stockRequest->inventory_source_id, $stockRequest->source_inventory_source_id]));

        return $stockRequest;
    }

    protected function findDraft(int $id): StockRequest
    {
        $stockRequest = $this->find($id);

        abort_unless($stockRequest->requested_by === $this->adminId() && $stockRequest->status === StockRequest::STATUS_DRAFT, 403);

        return $stockRequest;
    }

    /**
     * Allocators work at a warehouse; the request is the branch's.
     */
    protected function findForAllocation(int $id): StockRequest
    {
        $stockRequest = StockRequest::findOrFail($id);

        $warehouseIds = $this->locations()->warehouseIds();

        if ($this->locations()->ids() !== null) {
            $warehouseIds = array_intersect($warehouseIds, $this->locations()->ids());
        }

        abort_if(empty($warehouseIds), 403, trans('operations::app.errors.location-forbidden'));

        return $stockRequest;
    }

    protected function validated(): array
    {
        $validated = request()->validate([
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'priority' => ['required', Rule::in(PurchaseRequest::PRIORITIES)],
            'needed_by' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:1000000',
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        return [
            collect($validated)->only(['inventory_source_id', 'priority', 'needed_by', 'notes'])->all(),
            $validated['items'],
        ];
    }
}
