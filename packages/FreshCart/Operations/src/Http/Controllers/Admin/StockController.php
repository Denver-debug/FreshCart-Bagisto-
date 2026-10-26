<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockLot;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Services\StockLedgerService;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        protected ProductCatalog $productCatalog,
        protected StockLedgerService $stockLedger,
    ) {}

    /**
     * Stock by product and location: sellable, reserved-safe transferable and held buckets.
     */
    public function index(): View
    {
        $filters = request()->validate([
            'location_id' => 'nullable|integer',
            'term' => 'nullable|string|max:100',
            'low_only' => 'nullable|boolean',
        ]);

        $locationIds = $this->locations()->ids();

        if (! empty($filters['location_id'])) {
            $this->locations()->authorize((int) $filters['location_id']);

            $locationIds = [(int) $filters['location_id']];
        }

        $holds = DB::table('fc_stock_holds')
            ->select(
                'product_id',
                'inventory_source_id',
                DB::raw("SUM(CASE WHEN bucket = 'quarantine' THEN qty ELSE 0 END) as quarantine_qty"),
                DB::raw("SUM(CASE WHEN bucket = 'damaged' THEN qty ELSE 0 END) as damaged_qty"),
                DB::raw("SUM(CASE WHEN bucket = 'expired' THEN qty ELSE 0 END) as expired_qty"),
                DB::raw("SUM(CASE WHEN bucket = 'supplier_return' THEN qty ELSE 0 END) as supplier_return_qty"),
                DB::raw("SUM(CASE WHEN bucket = 'in_transit' THEN qty ELSE 0 END) as in_transit_qty"),
            )
            ->groupBy('product_id', 'inventory_source_id');

        $defaultReorder = app(Settings::class)->defaultReorderLevel();

        $query = DB::table('product_inventories as pi')
            ->join('products', 'products.id', '=', 'pi.product_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'pi.inventory_source_id')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', app()->getLocale())
                    ->where('product_flat.channel', core()->getDefaultChannelCode());
            })
            ->leftJoinSub($holds, 'holds', function ($join) {
                $join->on('holds.product_id', '=', 'pi.product_id')
                    ->on('holds.inventory_source_id', '=', 'pi.inventory_source_id');
            })
            ->leftJoin('fc_stock_policies as policy', function ($join) {
                $join->on('policy.product_id', '=', 'pi.product_id')
                    ->on('policy.inventory_source_id', '=', 'pi.inventory_source_id');
            })
            ->where('pi.vendor_id', 0)
            ->whereIn('products.type', ProductCatalog::STOCKABLE_TYPES)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('pi.inventory_source_id', $locationIds))
            ->when($filters['term'] ?? null, function ($query, $term) {
                $query->where(fn ($query) => $query->where('products.sku', 'like', $term.'%')
                    ->orWhere('product_flat.name', 'like', '%'.$term.'%'));
            })
            ->when(! empty($filters['low_only']), function ($query) use ($defaultReorder) {
                $query->whereRaw('pi.qty <= COALESCE(policy.reorder_level, ?)', [$defaultReorder]);
            })
            ->select(
                'pi.product_id',
                'pi.inventory_source_id',
                'pi.qty',
                'products.sku',
                'product_flat.name',
                'inventory_sources.name as location_name',
                'policy.reorder_level',
                'policy.min_stock',
                'policy.max_stock',
                DB::raw('COALESCE(holds.quarantine_qty, 0) as quarantine_qty'),
                DB::raw('COALESCE(holds.damaged_qty, 0) as damaged_qty'),
                DB::raw('COALESCE(holds.expired_qty, 0) as expired_qty'),
                DB::raw('COALESCE(holds.supplier_return_qty, 0) as supplier_return_qty'),
                DB::raw('COALESCE(holds.in_transit_qty, 0) as in_transit_qty'),
            )
            ->orderBy('products.sku')
            ->orderBy('inventory_sources.name');

        $rows = $query->paginate(50)->withQueryString();

        return view('operations::admin.stock.index', [
            'rows' => $rows,
            'filters' => $filters,
            'locationOptions' => $this->locations()->options(),
            'defaultReorder' => $defaultReorder,
        ]);
    }

    public function view(int $productId): View
    {
        $product = $this->productCatalog->find($productId);

        abort_unless($product, 404);

        $locationIds = $this->locations()->ids();

        $inventories = DB::table('product_inventories')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'product_inventories.inventory_source_id')
            ->where('product_id', $productId)
            ->where('vendor_id', 0)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('inventory_source_id', $locationIds))
            ->select('inventory_source_id', 'qty', 'inventory_sources.name')
            ->get()
            ->map(function ($row) use ($productId) {
                $row->transferable = $this->stockLedger->transferableQty($productId, $row->inventory_source_id);

                return $row;
            });

        $holds = StockHold::with(['location', 'lot'])
            ->where('product_id', $productId)
            ->forLocations($locationIds)
            ->get();

        $lots = StockLot::with('location')
            ->where('product_id', $productId)
            ->where('qty_on_hand', '>', 0)
            ->forLocations($locationIds)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get();

        $movements = StockMovement::with(['location', 'admin'])
            ->where('product_id', $productId)
            ->forLocations($locationIds)
            ->latest('id')
            ->paginate(25);

        return view('operations::admin.stock.view', compact('product', 'inventories', 'holds', 'lots', 'movements'));
    }
}
