<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Quality;

use FreshCart\Operations\DataGrids\Quality\SupplierReturnDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\SupplierReturn;
use FreshCart\Operations\Services\QualityService;
use Illuminate\View\View;

class SupplierReturnController extends Controller
{
    public function __construct(protected QualityService $quality) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(SupplierReturnDataGrid::class)->process();
        }

        return view('operations::admin.supplier-returns.index');
    }

    public function view(int $id): View
    {
        $supplierReturn = $this->find($id)->load(['items', 'supplier', 'location']);

        return view('operations::admin.supplier-returns.view', [
            'supplierReturn' => $supplierReturn,
            'quality' => $this->quality,
        ]);
    }

    public function dispatchReturn(int $id)
    {
        $supplierReturn = $this->find($id);

        return $this->attempt(fn () => $this->quality->dispatchSupplierReturn($supplierReturn), trans('operations::app.supplier-returns.dispatched'));
    }

    public function resolve(int $id)
    {
        $supplierReturn = $this->find($id);

        $validated = request()->validate([
            'replaced' => 'nullable|array',
            'replaced.*' => 'integer|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        return $this->attempt(
            fn () => $this->quality->resolveSupplierReturn(
                $supplierReturn,
                $validated['replaced'] ?? [],
                isset($validated['credit_amount']) ? (float) $validated['credit_amount'] : null,
                $validated['notes'] ?? null,
            ),
            trans('operations::app.supplier-returns.resolved'),
        );
    }

    public function cancel(int $id)
    {
        $supplierReturn = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->quality->cancelSupplierReturn($supplierReturn, $remarks), trans('operations::app.common.cancelled-message'));
    }

    protected function find(int $id): SupplierReturn
    {
        $supplierReturn = SupplierReturn::findOrFail($id);

        $this->locations()->authorize($supplierReturn->inventory_source_id);

        return $supplierReturn;
    }
}
