<?php

use FreshCart\Operations\Http\Controllers\Admin\ProductLookupController;
use FreshCart\Operations\Http\Controllers\Admin\Setup\LocationController;
use FreshCart\Operations\Http\Controllers\Admin\Setup\StockPolicyController;
use FreshCart\Operations\Http\Controllers\Admin\StockController;
use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

Route::group([
    'middleware' => ['web', 'admin', NoCacheMiddleware::class],
    'prefix' => config('app.admin_url').'/operations',
], function () {
    Route::get('products/search', [ProductLookupController::class, 'search'])->name('admin.operations.products.search');

    Route::get('stock', [StockController::class, 'index'])->name('admin.operations.stock.index');

    Route::get('stock/{productId}', [StockController::class, 'view'])->name('admin.operations.stock.view');

    /**
     * Setup.
     */
    Route::prefix('setup')->group(function () {
        Route::controller(LocationController::class)->prefix('locations')->group(function () {
            Route::get('', 'index')->name('admin.operations.locations.index');
            Route::put('', 'update')->name('admin.operations.locations.update');
        });

        Route::controller(StockPolicyController::class)->prefix('stock-policies')->group(function () {
            Route::get('', 'index')->name('admin.operations.stock_policies.index');
            Route::post('', 'store')->name('admin.operations.stock_policies.store');
            Route::post('import', 'import')->name('admin.operations.stock_policies.import');
            Route::delete('{id}', 'destroy')->name('admin.operations.stock_policies.delete');
        });
    });
});
