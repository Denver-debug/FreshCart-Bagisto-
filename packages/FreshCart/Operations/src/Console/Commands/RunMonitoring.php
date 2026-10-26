<?php

namespace FreshCart\Operations\Console\Commands;

use FreshCart\Operations\Services\MonitoringService;
use FreshCart\Operations\Services\PricingService;
use FreshCart\Operations\Services\RetirementService;
use Illuminate\Console\Command;

/**
 * One entry point for every scheduled FreshCart check, so each can also be run by hand:
 *   php artisan fc:monitor low-stock|transfers|purchase-orders|promotions|price-changes|retirement|all
 */
class RunMonitoring extends Command
{
    protected $signature = 'fc:monitor {check=all : low-stock, transfers, purchase-orders, promotions, price-changes, retirement or all}';

    protected $description = 'Run FreshCart Operations scheduled checks (alerts, promotion activation/expiry, scheduled price changes, retirement candidates).';

    public function handle(MonitoringService $monitoring, PricingService $pricing, RetirementService $retirement): int
    {
        $check = $this->argument('check');

        $checks = [
            'low-stock' => fn () => $this->line('Low-stock alerts: '.$monitoring->checkLowStock()),
            'transfers' => fn () => $this->line('Overdue transfers notified: '.$monitoring->checkOverdueTransfers()),
            'purchase-orders' => fn () => $this->line('Overdue purchase orders notified: '.$monitoring->checkOverduePurchaseOrders()),
            'promotions' => function () use ($pricing) {
                $result = $pricing->syncPromotions();

                $this->line("Promotions activated: {$result['activated']}, expired: {$result['expired']}, expiry warnings: {$result['warned']}");
            },
            'price-changes' => fn () => $this->line('Price change batches applied: '.$pricing->applyDuePriceChanges()),
            'retirement' => fn () => $this->line('Open retirement candidates: '.$retirement->detectCandidates()),
        ];

        if ($check !== 'all' && ! isset($checks[$check])) {
            $this->error("Unknown check [$check].");

            return self::INVALID;
        }

        foreach ($check === 'all' ? $checks : [$check => $checks[$check]] as $callback) {
            $callback();
        }

        return self::SUCCESS;
    }
}
