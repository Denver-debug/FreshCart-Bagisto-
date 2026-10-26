<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Setup;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\StockPolicy;
use FreshCart\Operations\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Reorder level, minimum and maximum stock per product per location (P3, P8).
 */
class StockPolicyController extends Controller
{
    public function index(): View
    {
        $filters = request()->validate([
            'location_id' => 'nullable|integer',
            'term' => 'nullable|string|max:100',
        ]);

        $policies = StockPolicy::query()
            ->join('products', 'products.id', '=', 'fc_stock_policies.product_id')
            ->with('location')
            ->forLocations($this->locations()->ids())
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('fc_stock_policies.inventory_source_id', $id))
            ->when($filters['term'] ?? null, fn ($query, $term) => $query->where('products.sku', 'like', $term.'%'))
            ->select('fc_stock_policies.*', 'products.sku')
            ->orderBy('products.sku')
            ->paginate(50)
            ->withQueryString();

        return view('operations::admin.setup.stock-policies.index', [
            'policies' => $policies,
            'filters' => $filters,
            'locationOptions' => $this->locations()->options(),
        ]);
    }

    public function store()
    {
        $validated = request()->validate([
            'inventory_source_id' => 'required|integer|exists:inventory_sources,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.reorder_level' => 'required|integer|min:0',
            'items.*.min_stock' => 'required|integer|min:0',
            'items.*.max_stock' => 'nullable|integer|min:0',
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        $catalog = app(ProductCatalog::class);

        try {
            DB::transaction(function () use ($validated, $catalog) {
                foreach ($validated['items'] as $item) {
                    $catalog->stockable((int) $item['product_id']);

                    $this->upsert((int) $item['product_id'], (int) $validated['inventory_source_id'], $item);
                }
            });
        } catch (OperationsException $e) {
            session()->flash('error', $e->getMessage());

            return back()->withInput();
        }

        session()->flash('success', trans('operations::app.stock-policies.saved'));

        return redirect()->route('admin.operations.stock_policies.index');
    }

    /**
     * CSV columns: sku, location_code, reorder_level, min_stock, max_stock.
     */
    public function import()
    {
        request()->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $handle = fopen(request()->file('file')->getRealPath(), 'r');

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), fgetcsv($handle) ?: []);

        $required = ['sku', 'location_code', 'reorder_level', 'min_stock'];

        if (array_diff($required, $header)) {
            fclose($handle);

            return back()->withErrors(['file' => trans('operations::app.stock-policies.bad-header', ['columns' => implode(', ', $required)])]);
        }

        $products = DB::table('products')->whereIn('type', ProductCatalog::STOCKABLE_TYPES)->pluck('id', 'sku');
        $sources = DB::table('inventory_sources')->pluck('id', 'code');

        $errors = [];
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($data, 0, count($header)), count($header), null));

            $validator = Validator::make($row, [
                'sku' => 'required|string',
                'location_code' => 'required|string',
                'reorder_level' => 'required|integer|min:0',
                'min_stock' => 'required|integer|min:0',
                'max_stock' => 'nullable|integer|min:0',
            ]);

            $productId = $products[trim((string) $row['sku'])] ?? null;
            $sourceId = $sources[trim((string) $row['location_code'])] ?? null;

            if ($validator->fails() || ! $productId || ! $sourceId || ! $this->locations()->allows((int) $sourceId)) {
                $errors[] = trans('operations::app.stock-policies.bad-row', [
                    'line' => $line,
                    'reason' => $validator->fails()
                        ? $validator->errors()->first()
                        : (! $productId ? trans('operations::app.errors.unknown-sku') : trans('operations::app.errors.location-forbidden')),
                ]);

                continue;
            }

            $rows[] = [$productId, $sourceId, $row];
        }

        fclose($handle);

        if ($errors) {
            return back()->withErrors(['file' => implode(' ', array_slice($errors, 0, 20))]);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as [$productId, $sourceId, $row]) {
                $this->upsert((int) $productId, (int) $sourceId, $row);
            }
        });

        session()->flash('success', trans('operations::app.stock-policies.imported', ['count' => count($rows)]));

        return redirect()->route('admin.operations.stock_policies.index');
    }

    public function destroy(int $id)
    {
        $policy = StockPolicy::findOrFail($id);

        $this->locations()->authorize($policy->inventory_source_id);

        $policy->delete();

        session()->flash('success', trans('operations::app.stock-policies.deleted'));

        return redirect()->route('admin.operations.stock_policies.index');
    }

    protected function upsert(int $productId, int $sourceId, array $values): void
    {
        StockPolicy::updateOrCreate(
            ['product_id' => $productId, 'inventory_source_id' => $sourceId],
            [
                'reorder_level' => (int) $values['reorder_level'],
                'min_stock' => (int) $values['min_stock'],
                'max_stock' => ($values['max_stock'] ?? '') === '' || $values['max_stock'] === null ? null : (int) $values['max_stock'],
            ],
        );
    }
}
