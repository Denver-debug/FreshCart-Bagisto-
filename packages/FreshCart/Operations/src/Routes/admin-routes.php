<?php

use FreshCart\Operations\Http\Controllers\Admin\Procurement\GoodsReceiptController;
use FreshCart\Operations\Http\Controllers\Admin\Procurement\PurchaseOrderController;
use FreshCart\Operations\Http\Controllers\Admin\Procurement\PurchaseRequestController;
use FreshCart\Operations\Http\Controllers\Admin\ProductLookupController;
use FreshCart\Operations\Http\Controllers\Admin\Setup\LocationController;
use FreshCart\Operations\Http\Controllers\Admin\Setup\StockPolicyController;
use FreshCart\Operations\Http\Controllers\Admin\Setup\SupplierController;
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
        Route::controller(SupplierController::class)->prefix('suppliers')->group(function () {
            Route::get('', 'index')->name('admin.operations.suppliers.index');
            Route::get('create', 'create')->name('admin.operations.suppliers.create');
            Route::post('create', 'store')->name('admin.operations.suppliers.store');
            Route::get('edit/{id}', 'edit')->name('admin.operations.suppliers.edit');
            Route::put('edit/{id}', 'update')->name('admin.operations.suppliers.update');
            Route::delete('edit/{id}', 'destroy')->name('admin.operations.suppliers.delete');
        });

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

    /**
     * Module A: procurement.
     */
    Route::controller(PurchaseRequestController::class)->prefix('purchase-requests')->group(function () {
        Route::get('', 'index')->name('admin.operations.purchase_requests.index');
        Route::get('create', 'create')->name('admin.operations.purchase_requests.create');
        Route::post('create', 'store')->name('admin.operations.purchase_requests.store');
        Route::get('{id}', 'view')->name('admin.operations.purchase_requests.view');
        Route::get('{id}/edit', 'edit')->name('admin.operations.purchase_requests.edit');
        Route::put('{id}/edit', 'update')->name('admin.operations.purchase_requests.update');
        Route::post('{id}/submit', 'submit')->name('admin.operations.purchase_requests.submit');
        Route::post('{id}/approve', 'approve')->name('admin.operations.purchase_requests.approve');
        Route::post('{id}/reject', 'reject')->name('admin.operations.purchase_requests.reject');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.purchase_requests.cancel');
    });

    Route::controller(PurchaseOrderController::class)->prefix('purchase-orders')->group(function () {
        Route::get('', 'index')->name('admin.operations.purchase_orders.index');
        Route::get('create', 'create')->name('admin.operations.purchase_orders.create');
        Route::post('create', 'store')->name('admin.operations.purchase_orders.store');
        Route::get('{id}', 'view')->name('admin.operations.purchase_orders.view');
        Route::get('{id}/print', 'print')->name('admin.operations.purchase_orders.print');
        Route::post('{id}/issue', 'issue')->name('admin.operations.purchase_orders.issue');
        Route::post('{id}/close', 'close')->name('admin.operations.purchase_orders.close');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.purchase_orders.cancel');
    });

    Route::controller(GoodsReceiptController::class)->prefix('receiving')->group(function () {
        Route::get('', 'index')->name('admin.operations.receiving.index');
        Route::get('create/{purchaseOrderId}', 'create')->name('admin.operations.receiving.create');
        Route::post('create/{purchaseOrderId}', 'store')->name('admin.operations.receiving.store');
        Route::get('{id}', 'view')->name('admin.operations.receiving.view');
    });

});
