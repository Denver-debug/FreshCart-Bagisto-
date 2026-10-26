<?php

namespace FreshCart\Operations\Support;

use FreshCart\Operations\Exceptions\OperationsException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only product lookups used by the operations workflows.
 *
 * Only simple products carry physical stock in Bagisto (configurable parents,
 * bundles, grouped, virtual, downloadable and booking products do not), so
 * operations documents are restricted to them.
 */
class ProductCatalog
{
    const STOCKABLE_TYPES = ['simple'];

    /**
     * Fetch id, sku, name and type for the given product ids, keyed by id.
     */
    public function findMany(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        return DB::table('products')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', app()->getLocale())
                    ->where('product_flat.channel', core()->getDefaultChannelCode());
            })
            ->whereIn('products.id', $productIds)
            ->select('products.id', 'products.sku', 'products.type', 'product_flat.name', 'product_flat.price')
            ->get()
            ->keyBy('id');
    }

    public function find(int $productId): ?object
    {
        return $this->findMany([$productId])->get($productId);
    }

    /**
     * Resolve a product and make sure it can hold stock.
     */
    public function stockable(int $productId): object
    {
        $product = $this->find($productId);

        if (! $product) {
            throw OperationsException::trans('product-not-found', ['id' => $productId]);
        }

        if (! in_array($product->type, self::STOCKABLE_TYPES, true)) {
            throw OperationsException::trans('product-not-stockable', ['sku' => $product->sku]);
        }

        return $product;
    }

    /**
     * Search stockable products by exact SKU first, then by SKU/name prefix.
     */
    public function search(string $term, int $limit = 15): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $base = DB::table('products')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', app()->getLocale())
                    ->where('product_flat.channel', core()->getDefaultChannelCode());
            })
            ->whereIn('products.type', self::STOCKABLE_TYPES)
            ->select('products.id', 'products.sku', 'product_flat.name', 'product_flat.price');

        $exact = (clone $base)->where('products.sku', $term)->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);

        return $base->where(function ($query) use ($like) {
            $query->where('products.sku', 'like', $like.'%')
                ->orWhere('product_flat.name', 'like', '%'.$like.'%');
        })
            ->orderBy('products.sku')
            ->limit($limit)
            ->get();
    }

    /**
     * Product `cost` attribute (Bagisto stores it as a price-type attribute value).
     */
    public function cost(int $productId): ?float
    {
        $value = DB::table('product_attribute_values')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.code', 'cost')
            ->where('product_attribute_values.product_id', $productId)
            ->value('product_attribute_values.float_value');

        return $value === null ? null : (float) $value;
    }

    public function price(int $productId): ?float
    {
        $value = DB::table('product_attribute_values')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.code', 'price')
            ->where('product_attribute_values.product_id', $productId)
            ->value('product_attribute_values.float_value');

        return $value === null ? null : (float) $value;
    }
}
