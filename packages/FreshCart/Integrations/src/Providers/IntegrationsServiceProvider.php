<?php

namespace FreshCart\Integrations\Providers;

use FreshCart\Integrations\Clients\AkauntingClient;
use FreshCart\Integrations\Clients\FrappeClient;
use FreshCart\Integrations\Console\Commands\Backfill;
use FreshCart\Integrations\Console\Commands\IntegrationStatus;
use FreshCart\Integrations\Console\Commands\PullFrappe;
use FreshCart\Integrations\Console\Commands\PushOutbox;
use FreshCart\Integrations\Console\Commands\RetryOutbox;
use FreshCart\Integrations\Listeners\RecordsOutbox;
use FreshCart\Integrations\Services\Outbox;
use FreshCart\Operations\Models\GoodsReceiptProxy;
use FreshCart\Operations\Models\SupplierProxy;
use FreshCart\Operations\Models\SupplierReturnProxy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Connects FreshCart (Bagisto) with Akaunting (accounting) and Frappe HR.
 */
class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/integrations.php', 'freshcart-integrations');

        foreach ([AkauntingClient::class, FrappeClient::class, Outbox::class] as $singleton) {
            $this->app->singleton($singleton);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Database/Migrations');

        $this->loadRoutesFrom(dirname(__DIR__).'/Routes/webhook-routes.php');

        Event::listen('sales.invoice.save.after', [RecordsOutbox::class, 'invoiceSaved']);
        Event::listen('sales.refund.save.after', [RecordsOutbox::class, 'refundSaved']);

        SupplierProxy::modelClass()::saved(fn ($supplier) => app(RecordsOutbox::class)->supplierSaved($supplier));
        SupplierProxy::modelClass()::deleted(fn ($supplier) => app(RecordsOutbox::class)->supplierSaved($supplier));
        GoodsReceiptProxy::modelClass()::created(fn ($receipt) => app(RecordsOutbox::class)->goodsReceiptCreated($receipt));
        SupplierReturnProxy::modelClass()::updated(fn ($return) => app(RecordsOutbox::class)->supplierReturnUpdated($return));

        if ($this->app->runningInConsole()) {
            $this->commands([
                Backfill::class,
                IntegrationStatus::class,
                PullFrappe::class,
                PushOutbox::class,
                RetryOutbox::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('fc:integrations:push')->everyMinute()->withoutOverlapping();
            $schedule->command('fc:integrations:pull-frappe')->everyFifteenMinutes()->withoutOverlapping();
        });
    }
}
