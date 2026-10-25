<?php

namespace FreshCart\Operations\Listeners;

use FreshCart\Operations\Services\StockLedgerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Product\Models\ProductInventory;

/**
 * Logs stock changes made by Bagisto itself (shipments, product edits, refunds)
 * to the FreshCart ledger. FreshCart's own stock operations write with the query
 * builder and therefore never reach this observer.
 */
class ProductInventoryObserver
{
    public function __construct(protected StockLedgerService $stockLedger) {}

    public function created(ProductInventory $inventory): void
    {
        $this->log($inventory, (int) $inventory->qty);
    }

    public function updated(ProductInventory $inventory): void
    {
        if ($inventory->wasChanged('qty')) {
            $this->log($inventory, (int) $inventory->qty - (int) $inventory->getOriginal('qty'));
        }
    }

    public function deleted(ProductInventory $inventory): void
    {
        $this->log($inventory, -1 * (int) $inventory->qty);
    }

    protected function log(ProductInventory $inventory, int $delta): void
    {
        if ($delta === 0 || (int) $inventory->vendor_id !== 0) {
            return;
        }

        static $installed;

        try {
            if (! ($installed ??= Schema::hasTable('fc_stock_movements'))) {
                return;
            }

            $this->stockLedger->recordExternalChange(
                (int) $inventory->product_id,
                (int) $inventory->inventory_source_id,
                $delta,
                trans('operations::app.ledger.external', [
                    'origin' => Route::currentRouteName() ?? (app()->runningInConsole() ? 'console' : 'system'),
                ]),
            );
        } catch (Throwable $e) {
            // Never break Bagisto's own stock update because of audit logging.
            Log::error('FreshCart ledger could not record a Bagisto stock change', [
                'product_id' => $inventory->product_id,
                'inventory_source_id' => $inventory->inventory_source_id,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
