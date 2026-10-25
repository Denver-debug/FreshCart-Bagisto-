<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Services\StockLedgerService;
use FreshCart\Operations\Support\ProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductLookupController extends Controller
{
    public function __construct(
        protected ProductCatalog $productCatalog,
        protected StockLedgerService $stockLedger,
    ) {}

    /**
     * Product search for line-item editors and barcode scans. With `location_id`
     * each result includes units that can leave that location right now.
     */
    public function search(): JsonResponse
    {
        $validated = request()->validate([
            'term' => 'nullable|string|max:100',
            'exact' => 'nullable|boolean',
            'ids' => 'nullable|array|max:200',
            'ids.*' => 'integer',
            'location_id' => 'nullable|integer|exists:inventory_sources,id',
        ]);

        if (! empty($validated['ids'])) {
            $products = $this->productCatalog->findMany($validated['ids'])->values();
        } else {
            $products = $this->productCatalog->search($validated['term'] ?? '');

            if (! empty($validated['exact'])) {
                $products = $products->filter(fn ($product) => strcasecmp($product->sku, trim($validated['term'] ?? '')) === 0)->values();
            }
        }

        $locationId = $validated['location_id'] ?? null;

        $costs = DB::table('product_attribute_values')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.code', 'cost')
            ->whereIn('product_attribute_values.product_id', $products->pluck('id'))
            ->pluck('product_attribute_values.float_value', 'product_attribute_values.product_id');

        return new JsonResponse([
            'data' => $products->map(fn ($product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'price' => $product->price !== null ? (float) $product->price : null,
                'cost' => isset($costs[$product->id]) ? (float) $costs[$product->id] : null,
                'available' => $locationId ? $this->stockLedger->transferableQty($product->id, (int) $locationId) : null,
            ])->values(),
        ]);
    }
}
