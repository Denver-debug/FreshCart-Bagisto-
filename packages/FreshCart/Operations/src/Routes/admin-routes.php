<?php

use FreshCart\Operations\Http\Controllers\Admin\ApprovalQueueController;
use FreshCart\Operations\Http\Controllers\Admin\DashboardController;
use FreshCart\Operations\Http\Controllers\Admin\Distribution\StockRequestController;
use FreshCart\Operations\Http\Controllers\Admin\Distribution\TransferController;
use FreshCart\Operations\Http\Controllers\Admin\NotificationController;
use FreshCart\Operations\Http\Controllers\Admin\Pricing\PriceChangeController;
use FreshCart\Operations\Http\Controllers\Admin\Pricing\PromotionController;
use FreshCart\Operations\Http\Controllers\Admin\Procurement\GoodsReceiptController;
use FreshCart\Operations\Http\Controllers\Admin\Procurement\PurchaseOrderController;
use FreshCart\Operations\Http\Controllers\Admin\Procurement\PurchaseRequestController;
use FreshCart\Operations\Http\Controllers\Admin\ProductLookupController;
use FreshCart\Operations\Http\Controllers\Admin\Quality\AdjustmentController;
use FreshCart\Operations\Http\Controllers\Admin\Quality\IncidentController;
use FreshCart\Operations\Http\Controllers\Admin\Quality\SupplierReturnController;
use FreshCart\Operations\Http\Controllers\Admin\ReportController;
use FreshCart\Operations\Http\Controllers\Admin\Retirement\CandidateController;
use FreshCart\Operations\Http\Controllers\Admin\Retirement\DisposalController;
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
    Route::get('', [DashboardController::class, 'index'])->name('admin.operations.dashboard.index');

    Route::get('products/search', [ProductLookupController::class, 'search'])->name('admin.operations.products.search');

    Route::get('stock', [StockController::class, 'index'])->name('admin.operations.stock.index');

    Route::get('stock/{productId}', [StockController::class, 'view'])->name('admin.operations.stock.view');

    Route::controller(NotificationController::class)->prefix('notifications')->group(function () {
        Route::get('', 'index')->name('admin.operations.notifications.index');
        Route::post('read-all', 'readAll')->name('admin.operations.notifications.read_all');
        Route::get('{id}', 'open')->name('admin.operations.notifications.open');
    });

    Route::get('approvals', [ApprovalQueueController::class, 'index'])->name('admin.operations.approvals.index');

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

    /**
     * Module B: branch replenishment.
     */
    Route::controller(StockRequestController::class)->prefix('stock-requests')->group(function () {
        Route::get('', 'index')->name('admin.operations.stock_requests.index');
        Route::get('create', 'create')->name('admin.operations.stock_requests.create');
        Route::post('create', 'store')->name('admin.operations.stock_requests.store');
        Route::get('{id}', 'view')->name('admin.operations.stock_requests.view');
        Route::get('{id}/edit', 'edit')->name('admin.operations.stock_requests.edit');
        Route::put('{id}/edit', 'update')->name('admin.operations.stock_requests.update');
        Route::post('{id}/submit', 'submit')->name('admin.operations.stock_requests.submit');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.stock_requests.cancel');
        Route::post('{id}/reject', 'reject')->name('admin.operations.stock_requests.reject');
        Route::get('{id}/allocate', 'allocation')->name('admin.operations.stock_requests.allocation');
        Route::post('{id}/allocate', 'allocate')->name('admin.operations.stock_requests.allocate');
    });

    /**
     * Module C: transfers (also carries replenishment dispatches).
     */
    Route::controller(TransferController::class)->prefix('transfers')->group(function () {
        Route::get('', 'index')->name('admin.operations.transfers.index');
        Route::get('create', 'create')->name('admin.operations.transfers.create');
        Route::post('create', 'store')->name('admin.operations.transfers.store');
        Route::get('{id}', 'view')->name('admin.operations.transfers.view');
        Route::get('{id}/print', 'print')->name('admin.operations.transfers.print');
        Route::post('{id}/submit', 'submit')->name('admin.operations.transfers.submit');
        Route::post('{id}/approve', 'approve')->name('admin.operations.transfers.approve');
        Route::post('{id}/reject', 'reject')->name('admin.operations.transfers.reject');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.transfers.cancel');
        Route::post('{id}/pick', 'pick')->name('admin.operations.transfers.pick');
        Route::post('{id}/schedule', 'schedule')->name('admin.operations.transfers.schedule');
        Route::post('{id}/dispatch', 'dispatchTransfer')->name('admin.operations.transfers.dispatch');
        Route::post('{id}/receive', 'receive')->name('admin.operations.transfers.receive');
        Route::post('{id}/close', 'closeWithDiscrepancy')->name('admin.operations.transfers.close');
        Route::post('{id}/return', 'createReturn')->name('admin.operations.transfers.return');
    });

    /**
     * Module D: incidents, adjustments, supplier returns.
     */
    Route::controller(IncidentController::class)->prefix('incidents')->group(function () {
        Route::get('', 'index')->name('admin.operations.incidents.index');
        Route::get('create', 'create')->name('admin.operations.incidents.create');
        Route::post('create', 'store')->name('admin.operations.incidents.store');
        Route::get('{id}', 'view')->name('admin.operations.incidents.view');
        Route::get('{id}/photo/{attachmentId}', 'photo')->name('admin.operations.incidents.photo');
        Route::post('{id}/inspect', 'inspect')->name('admin.operations.incidents.inspect');
        Route::post('{id}/classify', 'classify')->name('admin.operations.incidents.classify');
    });

    Route::controller(AdjustmentController::class)->prefix('adjustments')->group(function () {
        Route::get('', 'index')->name('admin.operations.adjustments.index');
        Route::get('create', 'create')->name('admin.operations.adjustments.create');
        Route::post('create', 'store')->name('admin.operations.adjustments.store');
        Route::get('{id}', 'view')->name('admin.operations.adjustments.view');
        Route::post('{id}/submit', 'submit')->name('admin.operations.adjustments.submit');
        Route::post('{id}/approve', 'approve')->name('admin.operations.adjustments.approve');
        Route::post('{id}/reject', 'reject')->name('admin.operations.adjustments.reject');
        Route::post('{id}/post', 'post')->name('admin.operations.adjustments.post');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.adjustments.cancel');
    });

    Route::controller(SupplierReturnController::class)->prefix('supplier-returns')->group(function () {
        Route::get('', 'index')->name('admin.operations.supplier_returns.index');
        Route::get('{id}', 'view')->name('admin.operations.supplier_returns.view');
        Route::post('{id}/dispatch', 'dispatchReturn')->name('admin.operations.supplier_returns.dispatch');
        Route::post('{id}/resolve', 'resolve')->name('admin.operations.supplier_returns.resolve');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.supplier_returns.cancel');
    });

    /**
     * Module F: retirement and disposal.
     */
    Route::controller(CandidateController::class)->prefix('retirement-candidates')->group(function () {
        Route::get('', 'index')->name('admin.operations.candidates.index');
        Route::post('refresh', 'refresh')->name('admin.operations.candidates.refresh');
        Route::post('nominate', 'nominate')->name('admin.operations.candidates.nominate');
        Route::post('{id}/dismiss', 'dismiss')->name('admin.operations.candidates.dismiss');
    });

    Route::controller(DisposalController::class)->prefix('disposals')->group(function () {
        Route::get('', 'index')->name('admin.operations.disposals.index');
        Route::get('create', 'create')->name('admin.operations.disposals.create');
        Route::post('create', 'store')->name('admin.operations.disposals.store');
        Route::get('{id}', 'view')->name('admin.operations.disposals.view');
        Route::get('{id}/print', 'print')->name('admin.operations.disposals.print');
        Route::get('{id}/evidence/{attachmentId}', 'evidence')->name('admin.operations.disposals.evidence');
        Route::post('{id}/submit', 'submit')->name('admin.operations.disposals.submit');
        Route::post('{id}/inspect', 'inspect')->name('admin.operations.disposals.inspect');
        Route::post('{id}/value', 'value')->name('admin.operations.disposals.value');
        Route::post('{id}/approve', 'approve')->name('admin.operations.disposals.approve');
        Route::post('{id}/reject', 'reject')->name('admin.operations.disposals.reject');
        Route::post('{id}/segregate', 'segregate')->name('admin.operations.disposals.segregate');
        Route::post('{id}/complete', 'complete')->name('admin.operations.disposals.complete');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.disposals.cancel');
    });

    /**
     * Module E: pricing and promotions.
     */
    Route::controller(PromotionController::class)->prefix('promotions')->group(function () {
        Route::get('', 'index')->name('admin.operations.promotions.index');
        Route::get('create', 'create')->name('admin.operations.promotions.create');
        Route::post('create', 'store')->name('admin.operations.promotions.store');
        Route::get('{id}', 'view')->name('admin.operations.promotions.view');
        Route::get('{id}/edit', 'edit')->name('admin.operations.promotions.edit');
        Route::put('{id}/edit', 'update')->name('admin.operations.promotions.update');
        Route::post('{id}/submit', 'submit')->name('admin.operations.promotions.submit');
        Route::post('{id}/approve', 'approve')->name('admin.operations.promotions.approve');
        Route::post('{id}/reject', 'reject')->name('admin.operations.promotions.reject');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.promotions.cancel');
    });

    Route::controller(PriceChangeController::class)->prefix('price-changes')->group(function () {
        Route::get('', 'index')->name('admin.operations.price_changes.index');
        Route::get('create', 'create')->name('admin.operations.price_changes.create');
        Route::post('create', 'store')->name('admin.operations.price_changes.store');
        Route::get('template', 'template')->name('admin.operations.price_changes.template');
        Route::get('{id}', 'view')->name('admin.operations.price_changes.view');
        Route::post('{id}/submit', 'submit')->name('admin.operations.price_changes.submit');
        Route::post('{id}/approve', 'approve')->name('admin.operations.price_changes.approve');
        Route::post('{id}/reject', 'reject')->name('admin.operations.price_changes.reject');
        Route::post('{id}/cancel', 'cancel')->name('admin.operations.price_changes.cancel');
    });

    /**
     * Reports.
     */
    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('', 'index')->name('admin.operations.reports.index');
        Route::get('ledger', 'ledger')->name('admin.operations.reports.ledger');
        Route::get('low-stock', 'lowStock')->name('admin.operations.reports.low_stock');
        Route::get('valuation', 'valuation')->name('admin.operations.reports.valuation');
        Route::get('receiving-discrepancies', 'receivingDiscrepancies')->name('admin.operations.reports.receiving_discrepancies');
        Route::get('supplier-performance', 'supplierPerformance')->name('admin.operations.reports.supplier_performance');
        Route::get('transfers', 'transfers')->name('admin.operations.reports.transfers');
        Route::get('adjustments', 'adjustments')->name('admin.operations.reports.adjustments');
        Route::get('held-stock', 'heldStock')->name('admin.operations.reports.held_stock');
        Route::get('recurring-incidents', 'recurringIncidents')->name('admin.operations.reports.recurring_incidents');
        Route::get('disposals', 'disposals')->name('admin.operations.reports.disposals');
        Route::get('expiry', 'expiry')->name('admin.operations.reports.expiry');
        Route::get('branch-sales', 'branchSales')->name('admin.operations.reports.branch_sales');
        Route::get('promotion-performance', 'promotionPerformance')->name('admin.operations.reports.promotion_performance');
    });
});
