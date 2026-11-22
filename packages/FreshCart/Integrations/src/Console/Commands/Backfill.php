<?php

namespace FreshCart\Integrations\Console\Commands;

use FreshCart\Integrations\Services\Outbox;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Models\SupplierReturn;
use Illuminate\Console\Command;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Refund;

/**
 * Queues records that existed before the integration was installed.
 */
class Backfill extends Command
{
    protected $signature = 'fc:integrations:backfill {--since= : Only records created on or after this date (Y-m-d)}';

    protected $description = 'Queue existing suppliers, invoices, refunds, goods receipts and supplier credits for Akaunting.';

    public function handle(Outbox $outbox): int
    {
        $since = $this->option('since');
        $scope = fn ($query) => $since ? $query->where('created_at', '>=', $since) : $query;

        $sets = [
            ['akaunting.vendor', 'supplier', $scope(Supplier::query())],
            ['akaunting.sales_invoice', 'invoice', $scope(Invoice::query())],
            ['akaunting.sales_refund', 'refund', $scope(Refund::query())],
            ['akaunting.purchase_bill', 'goods_receipt', $scope(GoodsReceipt::query())],
            ['akaunting.supplier_credit', 'supplier_return', $scope(SupplierReturn::where('status', SupplierReturn::STATUS_RESOLVED)->where('credit_amount', '>', 0))],
        ];

        foreach ($sets as [$action, $type, $query]) {
            $count = 0;

            $query->select('id')->chunkById(200, function ($rows) use ($outbox, $action, $type, &$count) {
                foreach ($rows as $row) {
                    $outbox->record($action, $type, $row->id);
                    $count++;
                }
            });

            $this->info("{$action}: {$count} queued.");
        }

        return self::SUCCESS;
    }
}
