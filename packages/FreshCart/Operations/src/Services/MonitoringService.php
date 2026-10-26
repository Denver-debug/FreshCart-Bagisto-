<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\StockPolicy;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled checks (P8, P5, P1). Alerts are digests per location and each
 * product/location is re-alerted only after the configured cooldown, so a
 * product that stays low does not generate a notification every hour.
 */
class MonitoringService
{
    public function __construct(
        protected ReportService $reports,
        protected NotificationService $notifications,
        protected Settings $settings,
    ) {}

    /**
     * @return int number of product/location pairs newly alerted
     */
    public function checkLowStock(): int
    {
        $cooldown = now()->subHours($this->settings->lowStockCooldownHours());
        $default = $this->settings->defaultReorderLevel();

        $rows = DB::table('product_inventories as pi')
            ->join('products', 'products.id', '=', 'pi.product_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'pi.inventory_source_id')
            ->leftJoin('fc_stock_policies as policy', function ($join) {
                $join->on('policy.product_id', '=', 'pi.product_id')->on('policy.inventory_source_id', '=', 'pi.inventory_source_id');
            })
            ->where('pi.vendor_id', 0)
            ->where('inventory_sources.status', 1)
            ->whereIn('products.type', ProductCatalog::STOCKABLE_TYPES)
            ->whereRaw('COALESCE(policy.reorder_level, ?) > 0', [$default])
            ->whereRaw('pi.qty <= COALESCE(policy.reorder_level, ?)', [$default])
            ->where(fn ($query) => $query->whereNull('policy.id')->orWhereNull('policy.last_alerted_at')->orWhere('policy.last_alerted_at', '<', $cooldown))
            ->select('pi.product_id', 'pi.inventory_source_id', 'pi.qty', 'products.sku', 'inventory_sources.name as location', 'policy.id as policy_id', DB::raw('COALESCE(policy.reorder_level, '.(int) $default.') as reorder_level'))
            ->get();

        $alerted = 0;

        foreach ($rows->groupBy('inventory_source_id') as $locationId => $items) {
            // Products without a policy use the default level; their cooldown lives in the cache.
            $items = $items->filter(function ($item) {
                if ($item->policy_id) {
                    return true;
                }

                return Cache::add('fc-low-stock:'.$item->product_id.':'.$item->inventory_source_id, 1, now()->addHours($this->settings->lowStockCooldownHours()));
            });

            if ($items->isEmpty()) {
                continue;
            }

            $first = $items->first();

            $this->notifications->notifyPermission(
                'operations.stock',
                'low_stock',
                $items->count() === 1
                    ? trans('operations::app.notifications.low-stock', ['sku' => $first->sku, 'location' => $first->location, 'qty' => $first->qty, 'level' => $first->reorder_level])
                    : trans('operations::app.notifications.low-stock-digest', ['count' => $items->count(), 'location' => $first->location]),
                $items->take(10)->map(fn ($item) => $item->sku.' ('.$item->qty.'/'.$item->reorder_level.')')->implode(', '),
                route('admin.operations.reports.low_stock', ['location_id' => $locationId]),
                (int) $locationId,
            );

            StockPolicy::whereIn('id', $items->pluck('policy_id')->filter())->update(['last_alerted_at' => now()]);

            $alerted += $items->count();
        }

        return $alerted;
    }

    public function checkOverdueTransfers(): int
    {
        $overdue = DB::query()
            ->fromSub($this->reports->transfers(null, []), 't')
            ->where('overdue', 1)
            ->pluck('reference');

        $count = 0;

        foreach (Transfer::whereIn('reference', $overdue)->whereNull('overdue_notified_at')->get() as $transfer) {
            $title = $transfer->isTemporary() && $transfer->expected_return_date?->isPast() && in_array($transfer->status, [Transfer::STATUS_RECEIVED, Transfer::STATUS_PARTIALLY_RECEIVED], true)
                ? trans('operations::app.notifications.transfer-return-due', ['reference' => $transfer->reference, 'date' => $transfer->expected_return_date->toDateString()])
                : trans('operations::app.notifications.transfer-overdue', ['reference' => $transfer->reference]);

            $url = route('admin.operations.transfers.view', $transfer->id);

            foreach ([$transfer->source_inventory_source_id, $transfer->destination_inventory_source_id] as $locationId) {
                $this->notifications->notifyPermission('operations.transfers', 'transfer_overdue', $title, null, $url, $locationId);
            }

            $transfer->forceFill(['overdue_notified_at' => now()])->save();

            $count++;
        }

        return $count;
    }

    public function checkOverduePurchaseOrders(): int
    {
        $count = 0;

        $orders = PurchaseOrder::whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)
            ->whereDate('expected_delivery_date', '<', today())
            ->whereNull('overdue_notified_at')
            ->get();

        foreach ($orders as $order) {
            $this->notifications->notifyPermission(
                'operations.purchase_orders.create',
                'purchase_order_overdue',
                trans('operations::app.notifications.po-overdue', ['reference' => $order->reference, 'date' => $order->expected_delivery_date->toDateString()]),
                null,
                route('admin.operations.purchase_orders.view', $order->id),
                $order->inventory_source_id,
            );

            $order->forceFill(['overdue_notified_at' => now()])->save();

            $count++;
        }

        return $count;
    }
}
