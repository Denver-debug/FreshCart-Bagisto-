<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\DisposalRequest;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\Incident;
use FreshCart\Operations\Models\PriceChangeBatch;
use FreshCart\Operations\Models\PromotionProposal;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockRequest;
use FreshCart\Operations\Models\SupplierReturn;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Support\LocationScope;
use Illuminate\Support\Facades\DB;

/**
 * Role dashboards. A section is built only when the admin holds the permission
 * that defines that role, and all figures are scoped to the admin's locations.
 */
class DashboardService
{
    const SECTIONS = [
        'management' => 'operations.approvals.level2',
        'warehouse' => 'operations.receiving.create',
        'purchasing' => 'operations.purchase_orders.create',
        'branch' => 'operations.stock_requests.create',
        'finance' => 'operations.reports.finance',
    ];

    public function __construct(
        protected ReportService $reports,
        protected LocationScope $locationScope,
        protected ApprovalService $approvals,
    ) {}

    public function build(array $filters): array
    {
        $locationIds = $this->locationScope->ids();

        $sections = [];

        foreach (self::SECTIONS as $section => $permission) {
            if (bouncer()->hasPermission($permission)) {
                $sections[$section] = $this->{$section}($locationIds, $filters);
            }
        }

        return [
            'sections' => $sections,
            'pending_my_approval' => $this->pendingForMe()->count(),
        ];
    }

    /**
     * Documents in an approval status where the current admin may decide.
     */
    public function pendingForMe()
    {
        $documents = collect([
            [PurchaseRequest::query(), 'submitted'],
            [Transfer::query(), 'pending_approval'],
            [Adjustment::query(), 'submitted'],
            [DisposalRequest::query(), 'valued'],
            [PromotionProposal::query(), 'submitted'],
            [PriceChangeBatch::query(), 'submitted'],
        ]);

        $locationIds = $this->locationScope->ids();

        return $documents->flatMap(function ($pair) use ($locationIds) {
            [$query, $status] = $pair;

            $query->where('status', $status);

            if (method_exists($query->getModel(), 'scopeForLocations')) {
                $query->forLocations($locationIds);
            }

            return $query->latest('id')->limit(100)->get()
                ->filter(fn ($document) => $this->approvals->canDecide($document, $status));
        })->values();
    }

    protected function management(?array $locationIds, array $filters): array
    {
        $value = $this->reports->totalInventoryValue($locationIds);

        $lowStock = DB::query()->fromSub($this->reports->lowStock($locationIds), 'l');

        return [
            'inventory_value' => $value['value'],
            'unvalued_products' => $value['unvalued'],
            'low_stock' => (clone $lowStock)->count(),
            'out_of_stock' => (clone $lowStock)->where('out_of_stock', 1)->count(),
            'pending_purchase_approvals' => PurchaseRequest::where('status', PurchaseRequest::STATUS_SUBMITTED)->forLocations($locationIds)->count(),
            'outstanding_orders' => PurchaseOrder::whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)->forLocations($locationIds)->count(),
            'overdue_deliveries' => PurchaseOrder::whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)->forLocations($locationIds)->whereDate('expected_delivery_date', '<', today())->count(),
            'pending_stock_requests' => StockRequest::whereIn('status', StockRequest::ALLOCATABLE_STATUSES)->forLocations($locationIds)->count(),
            'transfers_in_transit' => $this->reports->inTransitTransfers($locationIds)->count(),
            'transfers_overdue' => $this->reports->overdueTransferCount($locationIds),
            'held' => $this->reports->heldSummary($locationIds),
            'promotions' => PromotionProposal::selectRaw('status, COUNT(*) as total')->whereIn('status', ['scheduled', 'active', 'expired'])->groupBy('status')->pluck('total', 'status')->all(),
            'disposals_awaiting_approval' => DisposalRequest::where('status', DisposalRequest::STATUS_VALUED)->forLocations($locationIds)->count(),
            'stock_by_location' => $this->reports->valuation($locationIds)->get(),
            'sales' => bouncer()->hasPermission('operations.reports.sales') ? $this->sales($locationIds, $filters) : null,
        ];
    }

    protected function warehouse(?array $locationIds, array $filters): array
    {
        return [
            'expected_deliveries' => PurchaseOrder::with('supplier', 'location')
                ->whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)
                ->forLocations($locationIds)
                ->where(fn ($query) => $query->whereNull('expected_delivery_date')->orWhereDate('expected_delivery_date', '<=', today()->addDays(7)))
                ->orderByRaw('expected_delivery_date IS NULL')
                ->orderBy('expected_delivery_date')
                ->limit(10)
                ->get(),
            'pending_inspections' => Incident::whereIn('status', [Incident::STATUS_QUARANTINED, Incident::STATUS_INSPECTED])->forLocations($locationIds)->count(),
            'receiving_discrepancies' => GoodsReceipt::where('has_discrepancy', true)->forLocations($locationIds)->where('received_at', '>=', now()->subDays(30))->count(),
            'picking_tasks' => Transfer::with('destinationLocation')
                ->whereIn('status', [Transfer::STATUS_APPROVED, Transfer::STATUS_PICKING])
                ->when($locationIds !== null, fn ($query) => $query->whereIn('source_inventory_source_id', $locationIds))
                ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
                ->limit(10)
                ->get(),
            'pending_replenishment' => StockRequest::whereIn('status', StockRequest::ALLOCATABLE_STATUSES)->count(),
            'awaiting_receipt' => Transfer::whereIn('status', Transfer::RECEIVABLE_STATUSES)
                ->when($locationIds !== null, fn ($query) => $query->whereIn('destination_inventory_source_id', $locationIds))
                ->count(),
            'low_stock' => DB::query()->fromSub($this->reports->lowStock($locationIds), 'l')->count(),
            'held' => $this->reports->heldSummary($locationIds),
        ];
    }

    protected function purchasing(?array $locationIds, array $filters): array
    {
        $warehouseIds = $this->locationScope->warehouseIds();

        if ($locationIds !== null) {
            $warehouseIds = array_values(array_intersect($warehouseIds, $locationIds));
        }

        // Low warehouse lines with no open purchase request for that product.
        $suggestions = DB::query()
            ->fromSub($this->reports->lowStock($warehouseIds ?: [0]), 'l')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fc_purchase_request_items as pri')
                    ->join('fc_purchase_requests as pr', 'pr.id', '=', 'pri.purchase_request_id')
                    ->whereColumn('pri.sku', 'l.sku')
                    ->whereIn('pr.status', ['draft', 'submitted', 'approved']);
            })
            ->limit(10)
            ->get();

        return [
            'pending_requests' => PurchaseRequest::where('status', PurchaseRequest::STATUS_SUBMITTED)->forLocations($locationIds)->count(),
            'approved_not_ordered' => PurchaseRequest::where('status', PurchaseRequest::STATUS_APPROVED)->forLocations($locationIds)->count(),
            'awaiting_delivery' => PurchaseOrder::whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)->forLocations($locationIds)->count(),
            'suggestions' => $suggestions,
            'supplier_performance' => $this->reports->supplierPerformance(['date_from' => now()->subDays(90)->toDateString()])->limit(5)->get(),
            'outstanding_returns' => SupplierReturn::whereIn('status', [SupplierReturn::STATUS_PENDING, SupplierReturn::STATUS_DISPATCHED])->forLocations($locationIds)->count(),
        ];
    }

    protected function branch(?array $locationIds, array $filters): array
    {
        return [
            'low_stock' => $this->reports->lowStock($locationIds)->limit(10)->get(),
            'pending_requests' => StockRequest::whereNotIn('status', [StockRequest::STATUS_FULFILLED, StockRequest::STATUS_CANCELLED, StockRequest::STATUS_REJECTED])->forLocations($locationIds)->count(),
            'incoming' => Transfer::with('sourceLocation')
                ->whereIn('status', array_merge([Transfer::STATUS_APPROVED, Transfer::STATUS_PICKING], Transfer::RECEIVABLE_STATUSES))
                ->when($locationIds !== null, fn ($query) => $query->whereIn('destination_inventory_source_id', $locationIds))
                ->latest('id')
                ->limit(10)
                ->get(),
            'recent_adjustments' => Adjustment::where('status', Adjustment::STATUS_POSTED)->forLocations($locationIds)->latest('posted_at')->limit(5)->get(),
            'sales' => bouncer()->hasPermission('operations.reports.sales') ? $this->reports->branchSales($locationIds, $filters)->get() : null,
        ];
    }

    protected function finance(?array $locationIds, array $filters): array
    {
        $since = ['date_from' => $filters['date_from'] ?? now()->subDays(30)->toDateString(), 'date_to' => $filters['date_to'] ?? null];

        $adjustments = DB::query()->fromSub($this->reports->adjustments($locationIds, $since), 'a');

        $held = DB::query()->fromSub($this->reports->heldStock($locationIds), 'h')
            ->selectRaw('bucket, SUM(value) as value')
            ->groupBy('bucket')
            ->pluck('value', 'bucket');

        $received = DB::table('fc_goods_receipt_items as item')
            ->join('fc_goods_receipts as grn', 'grn.id', '=', 'item.goods_receipt_id')
            ->join('fc_purchase_order_items as poi', 'poi.id', '=', 'item.purchase_order_item_id')
            ->when($locationIds !== null, fn ($query) => $query->whereIn('grn.inventory_source_id', $locationIds))
            ->tap(fn ($query) => $this->reports->dateRange($query, 'grn.received_at', $since))
            ->selectRaw('COUNT(DISTINCT grn.id) as receipts, COALESCE(SUM(item.qty_accepted * poi.unit_cost), 0) as value')
            ->first();

        return [
            'adjustment_value' => (float) (clone $adjustments)->sum('value'),
            'adjustment_lines' => (clone $adjustments)->count(),
            'damaged_value' => (float) ($held[StockHold::BUCKET_DAMAGED] ?? 0),
            'expired_value' => (float) ($held[StockHold::BUCKET_EXPIRED] ?? 0),
            'quarantine_value' => (float) ($held[StockHold::BUCKET_QUARANTINE] ?? 0),
            'disposal_value' => (float) DB::query()->fromSub($this->reports->disposals($locationIds, $since), 'd')->sum('value'),
            'receipts' => (int) $received->receipts,
            'received_value' => (float) $received->value,
            'inventory_value' => $this->reports->totalInventoryValue($locationIds)['value'],
            'since' => $since['date_from'],
        ];
    }

    protected function sales(?array $locationIds, array $filters): array
    {
        $filters += ['date_from' => now()->subDays(30)->toDateString()];

        return [
            'summary' => $this->reports->salesSummary($filters),
            'by_branch' => $this->reports->branchSales($locationIds, $filters)->get(),
            'daily' => $this->reports->dailySales($filters),
        ];
    }
}
