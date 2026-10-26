<?php

namespace FreshCart\Operations\Providers;

use FreshCart\Operations\Console\Commands\InstallRoles;
use FreshCart\Operations\Console\Commands\RunMonitoring;
use FreshCart\Operations\Listeners\ProductInventoryObserver;
use FreshCart\Operations\Services\ApprovalService;
use FreshCart\Operations\Services\NotificationService;
use FreshCart\Operations\Services\ReferenceService;
use FreshCart\Operations\Services\StockLedgerService;
use FreshCart\Operations\Support\DocumentRegistry;
use FreshCart\Operations\Support\LocationScope;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Webkul\Product\Models\ProductInventoryProxy;

class OperationsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/system.php', 'core');

        foreach ([
            Settings::class,
            LocationScope::class,
            ProductCatalog::class,
            DocumentRegistry::class,
            ReferenceService::class,
            StockLedgerService::class,
            ApprovalService::class,
            NotificationService::class,
        ] as $singleton) {
            $this->app->singleton($singleton);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Database/Migrations');

        $this->loadRoutesFrom(dirname(__DIR__).'/Routes/admin-routes.php');

        $this->loadTranslationsFrom(dirname(__DIR__).'/Resources/lang', 'operations');

        $this->loadViewsFrom(dirname(__DIR__).'/Resources/views', 'operations');

        Blade::anonymousComponentPath(dirname(__DIR__).'/Resources/views/components', 'operations');

        ProductInventoryProxy::modelClass()::observe(ProductInventoryObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallRoles::class,
                RunMonitoring::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('fc:monitor low-stock')->hourly()->withoutOverlapping();
            $schedule->command('fc:monitor transfers')->hourly()->withoutOverlapping();
            $schedule->command('fc:monitor promotions')->hourly()->withoutOverlapping();
            $schedule->command('fc:monitor price-changes')->everyFifteenMinutes()->withoutOverlapping();
            $schedule->command('fc:monitor purchase-orders')->dailyAt('07:00');
            $schedule->command('fc:monitor retirement')->dailyAt('02:00');
        });
    }
}
