<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Quality;

use FreshCart\Operations\DataGrids\Quality\AdjustmentDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Services\AdjustmentService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdjustmentController extends Controller
{
    public function __construct(protected AdjustmentService $adjustments) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(AdjustmentDataGrid::class)->process();
        }

        return view('operations::admin.adjustments.index');
    }

    public function create(): View
    {
        return view('operations::admin.adjustments.create', [
            'locationOptions' => $this->locations()->options(),
        ]);
    }

    public function store()
    {
        $validated = request()->validate([
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'direction' => ['required', Rule::in([Adjustment::DIRECTION_IN, Adjustment::DIRECTION_OUT])],
            'source_bucket' => ['nullable', Rule::in(StockHold::BUCKETS)],
            'reason_code' => ['required', Rule::in(Adjustment::REASONS)],
            'reason' => 'required|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        $items = collect($validated['items'])->map(fn ($item) => [
            'product_id' => (int) $item['product_id'],
            'qty' => (int) $item['qty'],
            'unit_cost' => isset($item['unit_cost']) && $item['unit_cost'] !== '' ? (float) $item['unit_cost'] : null,
        ])->all();

        return $this->attempt(
            fn () => $this->adjustments->create($validated, $items),
            trans('operations::app.adjustments.saved'),
            fn ($adjustment) => redirect()->route('admin.operations.adjustments.view', $adjustment->id),
        );
    }

    public function view(int $id): View
    {
        $adjustment = $this->find($id)->load(['items', 'location', 'requester', 'poster', 'origin']);

        return view('operations::admin.adjustments.view', compact('adjustment'));
    }

    public function submit(int $id)
    {
        $adjustment = $this->find($id);

        abort_unless($adjustment->requested_by === $this->adminId(), 403);

        return $this->attempt(fn () => $this->adjustments->submit($adjustment), trans('operations::app.adjustments.submitted'));
    }

    public function approve(int $id)
    {
        $adjustment = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->adjustments->approve($adjustment, $remarks), trans('operations::app.common.approved-message'));
    }

    public function reject(int $id)
    {
        $adjustment = $this->find($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(fn () => $this->adjustments->reject($adjustment, $remarks), trans('operations::app.common.rejected-message'));
    }

    public function post(int $id)
    {
        $adjustment = $this->find($id);

        return $this->attempt(fn () => $this->adjustments->post($adjustment), trans('operations::app.adjustments.posted'));
    }

    public function cancel(int $id)
    {
        $adjustment = $this->find($id);

        abort_unless($adjustment->requested_by === $this->adminId(), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->adjustments->cancel($adjustment, $remarks), trans('operations::app.common.cancelled-message'));
    }

    protected function find(int $id): Adjustment
    {
        $adjustment = Adjustment::findOrFail($id);

        $this->locations()->authorize($adjustment->inventory_source_id);

        return $adjustment;
    }
}
