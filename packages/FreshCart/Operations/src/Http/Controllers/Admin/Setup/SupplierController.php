<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Setup;

use FreshCart\Operations\DataGrids\SupplierDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(SupplierDataGrid::class)->process();
        }

        return view('operations::admin.setup.suppliers.index');
    }

    public function create(): View
    {
        return view('operations::admin.setup.suppliers.form', ['supplier' => new Supplier(['status' => true])]);
    }

    public function store()
    {
        $supplier = Supplier::create($this->validated());

        session()->flash('success', trans('operations::app.suppliers.saved'));

        return redirect()->route('admin.operations.suppliers.edit', $supplier->id);
    }

    public function edit(int $id): View
    {
        return view('operations::admin.setup.suppliers.form', ['supplier' => Supplier::findOrFail($id)]);
    }

    public function update(int $id)
    {
        $supplier = Supplier::findOrFail($id);

        $supplier->update($this->validated($supplier));

        session()->flash('success', trans('operations::app.suppliers.saved'));

        return redirect()->route('admin.operations.suppliers.edit', $supplier->id);
    }

    /**
     * Soft delete: purchase orders and returns keep pointing at the supplier.
     */
    public function destroy(int $id): JsonResponse
    {
        Supplier::findOrFail($id)->delete();

        return new JsonResponse(['message' => trans('operations::app.suppliers.deleted')]);
    }

    protected function validated(?Supplier $supplier = null): array
    {
        return request()->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('fc_suppliers', 'code')->ignore($supplier?->id)],
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:2000',
            'payment_terms' => 'nullable|string|max:100',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'status' => 'required|boolean',
        ]);
    }
}
