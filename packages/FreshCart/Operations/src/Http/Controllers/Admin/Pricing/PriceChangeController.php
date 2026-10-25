<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Pricing;

use FreshCart\Operations\DataGrids\Pricing\PriceChangeDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\PriceChangeBatch;
use FreshCart\Operations\Services\PricingService;
use Illuminate\View\View;

class PriceChangeController extends Controller
{
    public function __construct(protected PricingService $pricing) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PriceChangeDataGrid::class)->process();
        }

        return view('operations::admin.price-changes.index');
    }

    public function create(): View
    {
        return view('operations::admin.price-changes.create');
    }

    public function template()
    {
        return response()->streamDownload(function () {
            echo "sku,new_price\n";
        }, 'price-change-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store()
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'effective_at' => 'required|date|after_or_equal:today',
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        [$batch, $errors] = $this->pricing->importPriceChanges($validated['name'], $validated['effective_at'], request()->file('file'));

        if (! $batch) {
            return back()
                ->withInput()
                ->with('import_errors', $errors)
                ->with('error', trans('operations::app.errors.price-batch-errors', ['count' => count($errors)]));
        }

        session()->flash('success', trans('operations::app.price-changes.imported', ['count' => $batch->items()->count()]));

        return redirect()->route('admin.operations.price_changes.view', $batch->id);
    }

    public function view(int $id): View
    {
        $batch = PriceChangeBatch::with(['items', 'requester'])->findOrFail($id);

        return view('operations::admin.price-changes.view', compact('batch'));
    }

    public function submit(int $id)
    {
        $batch = PriceChangeBatch::with('items')->findOrFail($id);

        abort_unless($batch->requested_by === $this->adminId(), 403);

        return $this->attempt(fn () => $this->pricing->submitPriceChange($batch), trans('operations::app.price-changes.submitted'));
    }

    public function approve(int $id)
    {
        $batch = PriceChangeBatch::findOrFail($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->pricing->approvePriceChange($batch, $remarks), trans('operations::app.common.approved-message'));
    }

    public function reject(int $id)
    {
        $batch = PriceChangeBatch::findOrFail($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(fn () => $this->pricing->rejectPriceChange($batch, $remarks), trans('operations::app.common.rejected-message'));
    }

    public function cancel(int $id)
    {
        $batch = PriceChangeBatch::findOrFail($id);

        abort_unless($batch->requested_by === $this->adminId() || bouncer()->hasPermission('operations.price_changes.approve'), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->pricing->cancelPriceChange($batch, $remarks), trans('operations::app.common.cancelled-message'));
    }
}
