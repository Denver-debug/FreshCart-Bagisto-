<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\PromotionProposal;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Query builders behind reports and dashboards. Every figure comes from live
 * tables; nothing is estimated except where a method says so explicitly.
 *
 * Methods return query builders (paginated or exported by the controller) or
 * small aggregate arrays. `$locationIds = null` means all locations.
 */
class ReportService
{
    public function __construct(
        protected Settings $settings,
        protected ProductCatalog $productCatalog,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    /**
     * Per product/location unit cost: weighted average of costed lots on hand,
     * falling back to the product `cost` attribute.
     */
    public function unitCostSubquery(): Builder
    {
        $costAttributeId = (int) DB::table('attributes')->where('code', 'cost')->value('id');

        $lots = DB::table('fc_stock_lots')
            ->where('qty_on_hand', '>', 0)
            ->whereNotNull('unit_cost')
            ->selectRaw('product_id, inventory_source_id, SUM(qty_on_hand * unit_cost) / NULLIF(SUM(qty_on_hand), 0) as lot_cost')
            ->groupBy('product_id', 'inventory_source_id');

        return DB::table('product_inventories as pi')
            ->leftJoinSub($lots, 'lots', function ($join) {
                $join->on('lots.product_id', '=', 'pi.product_id')->on('lots.inventory_source_id', '=', 'pi.inventory_source_id');
            })
            ->leftJoin('product_attribute_values as cost_attr', function ($join) use ($costAttributeId) {
                $join->on('cost_attr.product_id', '=', 'pi.product_id')->where('cost_attr.attribute_id', $costAttributeId);
            })
            ->where('pi.vendor_id', 0)
            ->selectRaw('pi.product_id, pi.inventory_source_id, pi.qty, COALESCE(lots.lot_cost, cost_attr.float_value) as unit_cost');
    }

    /**
     * Inventory value by location (sellable and held), with the count of
     * stocked products that have no cost and are therefore excluded.
     */
    public function valuation(?array $locationIds, ?int $categoryId = null): Builder
    {
        $held = DB::table('fc_stock_holds')
            ->selectRaw('product_id, inventory_source_id, SUM(qty) as held_qty')
            ->groupBy('product_id', 'inventory_source_id');

        return DB::query()
            ->fromSub($this->unitCostSubquery(), 'c')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'c.inventory_source_id')
            ->join('products', 'products.id', '=', 'c.product_id')
            ->leftJoinSub($held, 'h', function ($join) {
                $join->on('h.product_id', '=', 'c.product_id')->on('h.inventory_source_id', '=', 'c.inventory_source_id');
            })
            ->whereIn('products.type', ProductCatalog::STOCKABLE_TYPES)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('c.inventory_source_id', $locationIds))
            ->when($categoryId, fn ($query) => $this->whereCategory($query, 'c.product_id', $categoryId))
            ->groupBy('c.inventory_source_id', 'inventory_sources.name')
            ->selectRaw('
                inventory_sources.name as location,
                SUM(c.qty) as sellable_units,
                SUM(COALESCE(h.held_qty, 0)) as held_units,
                SUM(c.qty * COALESCE(c.unit_cost, 0)) as sellable_value,
                SUM(COALESCE(h.held_qty, 0) * COALESCE(c.unit_cost, 0)) as held_value,
                SUM(CASE WHEN c.unit_cost IS NULL AND (c.qty > 0 OR COALESCE(h.held_qty, 0) > 0) THEN 1 ELSE 0 END) as unvalued_products
            ')
            ->orderBy('inventory_sources.name');
    }

    public function totalInventoryValue(?array $locationIds): array
    {
        $rows = $this->valuation($locationIds)->get();

        return [
            'value' => (float) $rows->sum('sellable_value') + (float) $rows->sum('held_value'),
            'unvalued' => (int) $rows->sum('unvalued_products'),
        ];
    }

    public function lowStock(?array $locationIds, ?int $categoryId = null): Builder
    {
        $default = $this->settings->defaultReorderLevel();

        return DB::table('product_inventories as pi')
            ->join('products', 'products.id', '=', 'pi.product_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'pi.inventory_source_id')
            ->leftJoin('fc_stock_policies as policy', function ($join) {
                $join->on('policy.product_id', '=', 'pi.product_id')->on('policy.inventory_source_id', '=', 'pi.inventory_source_id');
            })
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'pi.product_id')
                    ->where('product_flat.locale', app()->getLocale())
                    ->where('product_flat.channel', core()->getDefaultChannelCode());
            })
            ->where('pi.vendor_id', 0)
            ->whereIn('products.type', ProductCatalog::STOCKABLE_TYPES)
            ->whereRaw('COALESCE(policy.reorder_level, ?) > 0', [$default])
            ->whereRaw('pi.qty <= COALESCE(policy.reorder_level, ?)', [$default])
            ->when($locationIds !== null, fn ($query) => $query->whereIn('pi.inventory_source_id', $locationIds))
            ->when($categoryId, fn ($query) => $this->whereCategory($query, 'pi.product_id', $categoryId))
            ->selectRaw('
                products.sku,
                product_flat.name,
                inventory_sources.name as location,
                pi.qty as on_hand,
                COALESCE(policy.reorder_level, ?) as reorder_level,
                policy.max_stock,
                CASE WHEN pi.qty <= 0 THEN 1 ELSE 0 END as out_of_stock
            ', [$default])
            ->orderBy('pi.qty')
            ->orderBy('products.sku');
    }

    public function ledger(?array $locationIds, array $filters): Builder
    {
        return DB::table('fc_stock_movements as m')
            ->leftJoin('inventory_sources', 'inventory_sources.id', '=', 'm.inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'm.admin_id')
            ->when($locationIds !== null, fn ($query) => $query->whereIn('m.inventory_source_id', $locationIds))
            ->when($filters['sku'] ?? null, fn ($query, $sku) => $query->where('m.sku', $sku))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('m.movement_type', $type))
            ->when($filters['reference'] ?? null, fn ($query, $reference) => $query->where('m.reference_no', $reference))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $this->whereCategory($query, 'm.product_id', (int) $categoryId))
            ->tap(fn ($query) => $this->dateRange($query, 'm.created_at', $filters))
            ->select(
                'm.created_at',
                'm.sku',
                'inventory_sources.name as location',
                'm.movement_type',
                'm.bucket_from',
                'm.bucket_to',
                'm.qty',
                'm.sellable_delta',
                'm.sellable_after',
                'm.unit_cost',
                'm.reference_no',
                'm.reason',
                'admins.name as actor',
            )
            ->orderByDesc('m.id');
    }

    public function heldStock(?array $locationIds): Builder
    {
        return DB::table('fc_stock_holds as h')
            ->join('products', 'products.id', '=', 'h.product_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'h.inventory_source_id')
            ->leftJoin('fc_stock_lots as lot', 'lot.id', '=', 'h.lot_id')
            ->leftJoinSub($this->unitCostSubquery(), 'c', function ($join) {
                $join->on('c.product_id', '=', 'h.product_id')->on('c.inventory_source_id', '=', 'h.inventory_source_id');
            })
            ->where('h.qty', '>', 0)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('h.inventory_source_id', $locationIds))
            ->selectRaw('
                inventory_sources.name as location,
                h.bucket,
                products.sku,
                lot.batch_no,
                lot.expiry_date,
                h.qty,
                h.qty * COALESCE(lot.unit_cost, c.unit_cost, 0) as value,
                h.created_at as since
            ')
            ->orderBy('h.bucket')
            ->orderBy('inventory_sources.name');
    }

    public function heldSummary(?array $locationIds): array
    {
        return DB::table('fc_stock_holds')
            ->when($locationIds !== null, fn ($query) => $query->whereIn('inventory_source_id', $locationIds))
            ->selectRaw('bucket, SUM(qty) as qty')
            ->groupBy('bucket')
            ->pluck('qty', 'bucket')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    public function expiring(?array $locationIds, int $days): Builder
    {
        return DB::table('fc_stock_lots as lot')
            ->join('products', 'products.id', '=', 'lot.product_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'lot.inventory_source_id')
            ->where('lot.qty_on_hand', '>', 0)
            ->whereNotNull('lot.expiry_date')
            ->whereDate('lot.expiry_date', '<=', now()->addDays($days)->toDateString())
            ->when($locationIds !== null, fn ($query) => $query->whereIn('lot.inventory_source_id', $locationIds))
            ->selectRaw('products.sku, inventory_sources.name as location, lot.batch_no, lot.expiry_date, lot.qty_on_hand, lot.qty_on_hand * COALESCE(lot.unit_cost, 0) as value')
            ->orderBy('lot.expiry_date');
    }

    public function transfers(?array $locationIds, array $filters): Builder
    {
        $overdueHours = $this->settings->transferOverdueHours();

        return DB::table('fc_transfers as tr')
            ->join('inventory_sources as source', 'source.id', '=', 'tr.source_inventory_source_id')
            ->join('inventory_sources as destination', 'destination.id', '=', 'tr.destination_inventory_source_id')
            ->when($locationIds !== null, fn ($query) => $query->where(fn ($query) => $query->whereIn('tr.source_inventory_source_id', $locationIds)->orWhereIn('tr.destination_inventory_source_id', $locationIds)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('tr.status', $status))
            ->tap(fn ($query) => $this->dateRange($query, 'tr.created_at', $filters))
            ->selectRaw("
                tr.reference,
                tr.type,
                source.name as source,
                destination.name as destination,
                tr.status,
                tr.dispatched_at,
                tr.expected_arrival_at,
                tr.received_at,
                tr.has_discrepancy,
                CASE
                    WHEN tr.status IN ('in_transit', 'partially_received')
                        AND COALESCE(tr.expected_arrival_at, DATE_ADD(tr.dispatched_at, INTERVAL ? HOUR)) < NOW() THEN 1
                    WHEN tr.type = 'temporary' AND tr.expected_return_date < CURDATE()
                        AND NOT EXISTS (SELECT 1 FROM fc_transfers r WHERE r.return_of_transfer_id = tr.id AND r.status = 'received') THEN 1
                    ELSE 0
                END as overdue,
                CASE WHEN tr.received_at IS NOT NULL AND tr.dispatched_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, tr.dispatched_at, tr.received_at) END as transit_hours
            ", [$overdueHours])
            ->orderByDesc('tr.id');
    }

    /*
    |--------------------------------------------------------------------------
    | Procurement
    |--------------------------------------------------------------------------
    */

    public function receivingDiscrepancies(?array $locationIds, array $filters): Builder
    {
        return DB::table('fc_goods_receipt_items as item')
            ->join('fc_goods_receipts as grn', 'grn.id', '=', 'item.goods_receipt_id')
            ->join('fc_purchase_orders as po', 'po.id', '=', 'grn.purchase_order_id')
            ->join('fc_suppliers', 'fc_suppliers.id', '=', 'po.supplier_id')
            ->join('fc_purchase_order_items as poi', 'poi.id', '=', 'item.purchase_order_item_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'grn.inventory_source_id')
            ->where(fn ($query) => $query->where('item.qty_rejected', '>', 0)
                ->orWhereNotNull('item.discrepancy_note')
                ->orWhereNotNull('item.over_receipt_reason'))
            ->when($locationIds !== null, fn ($query) => $query->whereIn('grn.inventory_source_id', $locationIds))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('po.supplier_id', $id))
            ->tap(fn ($query) => $this->dateRange($query, 'grn.received_at', $filters))
            ->selectRaw('
                grn.received_at,
                grn.reference as grn,
                po.reference as po,
                fc_suppliers.name as supplier,
                inventory_sources.name as location,
                item.sku,
                poi.qty_ordered,
                item.qty_received,
                item.qty_rejected,
                item.qty_rejected * poi.unit_cost as rejected_value,
                item.discrepancy_note,
                item.over_receipt_reason
            ')
            ->orderByDesc('grn.received_at');
    }

    /**
     * Fill rate and on-time delivery per supplier for POs issued in the window.
     * On time = first receipt on or before the expected delivery date.
     */
    public function supplierPerformance(array $filters): Builder
    {
        $firstReceipt = DB::table('fc_goods_receipts')
            ->selectRaw('purchase_order_id, MIN(received_at) as first_received_at')
            ->groupBy('purchase_order_id');

        $lines = DB::table('fc_purchase_order_items')
            ->selectRaw('purchase_order_id, SUM(qty_ordered) as ordered, SUM(qty_received) as received, SUM(qty_rejected) as rejected')
            ->groupBy('purchase_order_id');

        return DB::table('fc_purchase_orders as po')
            ->join('fc_suppliers', 'fc_suppliers.id', '=', 'po.supplier_id')
            ->joinSub($lines, 'lines', 'lines.purchase_order_id', '=', 'po.id')
            ->leftJoinSub($firstReceipt, 'fr', 'fr.purchase_order_id', '=', 'po.id')
            ->whereNotIn('po.status', ['draft', 'cancelled'])
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('po.supplier_id', $id))
            ->tap(fn ($query) => $this->dateRange($query, 'po.order_date', $filters))
            ->groupBy('fc_suppliers.id', 'fc_suppliers.name')
            ->selectRaw('
                fc_suppliers.name as supplier,
                COUNT(po.id) as orders,
                SUM(lines.ordered) as ordered_units,
                SUM(lines.received) as accepted_units,
                SUM(lines.rejected) as rejected_units,
                ROUND(100 * SUM(lines.received) / NULLIF(SUM(lines.ordered), 0), 1) as fill_rate,
                SUM(CASE WHEN fr.first_received_at IS NOT NULL AND po.expected_delivery_date IS NOT NULL THEN 1 ELSE 0 END) as measurable,
                ROUND(100 * SUM(CASE WHEN fr.first_received_at IS NOT NULL AND po.expected_delivery_date IS NOT NULL AND DATE(fr.first_received_at) <= po.expected_delivery_date THEN 1 ELSE 0 END)
                    / NULLIF(SUM(CASE WHEN fr.first_received_at IS NOT NULL AND po.expected_delivery_date IS NOT NULL THEN 1 ELSE 0 END), 0), 1) as on_time_rate,
                ROUND(AVG(CASE WHEN fr.first_received_at IS NOT NULL AND po.issued_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, po.issued_at, fr.first_received_at) / 24 END), 1) as avg_lead_days
            ')
            ->orderBy('fc_suppliers.name');
    }

    /*
    |--------------------------------------------------------------------------
    | Quality & finance
    |--------------------------------------------------------------------------
    */

    /**
     * Finance-ready list of posted adjustment lines with approver names.
     */
    public function adjustments(?array $locationIds, array $filters): Builder
    {
        $approvers = DB::table('fc_approvals')
            ->join('admins', 'admins.id', '=', 'fc_approvals.admin_id')
            ->where('fc_approvals.approvable_type', (new Adjustment)->getMorphClass())
            ->where('fc_approvals.action', 'approved')
            ->selectRaw("fc_approvals.approvable_id, GROUP_CONCAT(DISTINCT admins.name ORDER BY fc_approvals.level SEPARATOR ', ') as approvers")
            ->groupBy('fc_approvals.approvable_id');

        return DB::table('fc_adjustment_items as item')
            ->join('fc_adjustments as adj', 'adj.id', '=', 'item.adjustment_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'adj.inventory_source_id')
            ->leftJoin('admins as requester', 'requester.id', '=', 'adj.requested_by')
            ->leftJoinSub($approvers, 'ap', 'ap.approvable_id', '=', 'adj.id')
            ->where('adj.status', 'posted')
            ->when($locationIds !== null, fn ($query) => $query->whereIn('adj.inventory_source_id', $locationIds))
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('adj.reason_code', $reason))
            ->tap(fn ($query) => $this->dateRange($query, 'adj.posted_at', $filters))
            ->selectRaw("
                adj.posted_at,
                adj.reference,
                inventory_sources.name as location,
                adj.reason_code,
                adj.direction,
                item.sku,
                item.qty,
                item.unit_cost,
                CASE WHEN adj.direction = 'out' THEN -item.line_value ELSE item.line_value END as value,
                adj.reason,
                requester.name as requested_by,
                ap.approvers
            ")
            ->orderByDesc('adj.posted_at');
    }

    public function recurringIncidents(?array $locationIds, array $filters): Builder
    {
        return DB::table('fc_incidents as inc')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'inc.inventory_source_id')
            ->leftJoin('fc_suppliers', 'fc_suppliers.id', '=', 'inc.supplier_id')
            ->when($locationIds !== null, fn ($query) => $query->whereIn('inc.inventory_source_id', $locationIds))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('inc.incident_type', $type))
            ->tap(fn ($query) => $this->dateRange($query, 'inc.created_at', $filters))
            ->groupBy('inc.sku', 'inc.name')
            ->havingRaw('COUNT(*) >= 2')
            ->selectRaw("
                inc.sku,
                inc.name,
                COUNT(*) as incidents,
                SUM(inc.qty) as units,
                GROUP_CONCAT(DISTINCT inc.incident_type SEPARATOR ', ') as types,
                GROUP_CONCAT(DISTINCT inventory_sources.name SEPARATOR ', ') as locations,
                GROUP_CONCAT(DISTINCT fc_suppliers.name SEPARATOR ', ') as suppliers,
                MAX(inc.created_at) as last_reported
            ")
            ->orderByDesc('incidents');
    }

    public function disposals(?array $locationIds, array $filters): Builder
    {
        return DB::table('fc_disposal_items as item')
            ->join('fc_disposal_requests as dsp', 'dsp.id', '=', 'item.disposal_request_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'dsp.inventory_source_id')
            ->whereIn('dsp.status', ['disposed', 'returned_to_supplier', 'completed'])
            ->when($locationIds !== null, fn ($query) => $query->whereIn('dsp.inventory_source_id', $locationIds))
            ->tap(fn ($query) => $this->dateRange($query, 'dsp.completed_at', $filters))
            ->selectRaw('
                dsp.completed_at,
                dsp.reference,
                inventory_sources.name as location,
                item.sku,
                item.batch_no,
                item.expiry_date,
                item.qty,
                item.reason,
                item.disposition,
                item.unit_cost,
                item.line_value as value,
                dsp.disposal_method
            ')
            ->orderByDesc('dsp.completed_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    */

    /**
     * Shipped sales by fulfilling location. Bagisto records the inventory source
     * on each shipment, so online orders are attributed to the branch that
     * shipped them. In-store (POS) sales are not recorded in Bagisto.
     */
    public function branchSales(?array $locationIds, array $filters): Builder
    {
        return DB::table('shipment_items as si')
            ->join('shipments', 'shipments.id', '=', 'si.shipment_id')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'shipments.inventory_source_id')
            ->when($locationIds !== null, fn ($query) => $query->whereIn('shipments.inventory_source_id', $locationIds))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $this->whereCategory($query, 'si.product_id', (int) $categoryId))
            ->where('orders.status', '!=', 'canceled')
            ->tap(fn ($query) => $this->dateRange($query, 'shipments.created_at', $filters))
            ->groupBy('shipments.inventory_source_id', 'inventory_sources.name')
            ->selectRaw('
                inventory_sources.name as location,
                COUNT(DISTINCT orders.id) as orders,
                SUM(si.qty) as units,
                SUM(si.base_total) as revenue
            ')
            ->orderByDesc('revenue');
    }

    /**
     * Order totals for the window (all channels), including orders not yet shipped.
     */
    public function salesSummary(array $filters): array
    {
        $query = DB::table('orders')->where('status', '!=', 'canceled');

        $this->dateRange($query, 'created_at', $filters);

        $row = $query->selectRaw('COUNT(*) as orders, COALESCE(SUM(base_grand_total), 0) as revenue')->first();

        $unshipped = DB::table('orders')
            ->whereNotIn('status', ['canceled', 'closed', 'completed'])
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('shipments')->whereColumn('shipments.order_id', 'orders.id'))
            ->tap(fn ($query) => $this->dateRange($query, 'created_at', $filters))
            ->count();

        return ['orders' => (int) $row->orders, 'revenue' => (float) $row->revenue, 'unshipped' => $unshipped];
    }

    public function dailySales(array $filters): array
    {
        $query = DB::table('orders')->where('status', '!=', 'canceled');

        $this->dateRange($query, 'created_at', $filters);

        return $query->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(base_grand_total) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->all();
    }

    /**
     * Sales of the promotion's products during the campaign versus the
     * same-length period just before it. An estimate: Bagisto order items do
     * not record which catalog rule priced them.
     */
    public function promotionPerformance(PromotionProposal $proposal): array
    {
        $productIds = collect($proposal->product_ids ?? []);

        if (! empty($proposal->category_ids)) {
            $productIds = $productIds->merge(
                DB::table('product_categories')->whereIn('category_id', $proposal->category_ids)->pluck('product_id')
            );
        }

        $productIds = $productIds->unique()->values()->all();

        $start = $proposal->starts_on->copy()->startOfDay();
        $end = $proposal->ends_on->copy()->endOfDay()->min(now());
        $days = max(1, (int) $start->diffInDays($end) + 1);
        $beforeStart = $start->copy()->subDays($days);

        $period = function (Carbon $from, Carbon $to) use ($productIds) {
            $row = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('order_items.product_id', $productIds ?: [0])
                ->whereNull('order_items.parent_id')
                ->where('orders.status', '!=', 'canceled')
                ->whereBetween('orders.created_at', [$from, $to])
                ->selectRaw('COALESCE(SUM(order_items.qty_ordered), 0) as units, COALESCE(SUM(order_items.base_total - order_items.base_discount_amount), 0) as revenue, COUNT(DISTINCT orders.id) as orders')
                ->first();

            return [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'units' => (int) $row->units,
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ];
        };

        $during = $period($start, $end);
        $before = $period($beforeStart, $start->copy()->subSecond());

        return [
            'during' => $during,
            'before' => $before,
            'uplift_units' => $before['units'] > 0 ? round(($during['units'] - $before['units']) / $before['units'] * 100, 1) : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function dateRange(Builder $query, string $column, array $filters): Builder
    {
        if (! empty($filters['date_from'])) {
            $query->where($column, '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where($column, '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }

    protected function whereCategory(Builder $query, string $productColumn, int $categoryId): Builder
    {
        return $query->whereExists(fn ($sub) => $sub->select(DB::raw(1))
            ->from('product_categories')
            ->whereColumn('product_categories.product_id', $productColumn)
            ->where('product_categories.category_id', $categoryId));
    }

    public function overdueTransferCount(?array $locationIds): int
    {
        return (int) DB::query()->fromSub($this->transfers($locationIds, []), 't')->where('overdue', 1)->count();
    }

    public function inTransitTransfers(?array $locationIds)
    {
        return Transfer::whereIn('status', Transfer::RECEIVABLE_STATUSES)->forLocations($locationIds);
    }

    public function heldBuckets(): array
    {
        return StockHold::BUCKETS;
    }
}
