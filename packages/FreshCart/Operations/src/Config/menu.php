<?php

/**
 * Admin sidebar entries. Keys match acl.php so Bagisto hides what a role cannot open.
 */
return [
    [
        'key' => 'operations',
        'name' => 'operations::app.menu.operations',
        'route' => 'admin.operations.dashboard.index',
        'sort' => 3,
        'icon' => 'icon-store',
    ], [
        'key' => 'operations.inbox',
        'name' => 'operations::app.menu.inbox',
        'route' => 'admin.operations.approvals.index',
        'sort' => 1,
        'icon' => '',
    ], [
        'key' => 'operations.stock',
        'name' => 'operations::app.menu.stock',
        'route' => 'admin.operations.stock.index',
        'sort' => 2,
        'icon' => '',
    ], [
        'key' => 'operations.purchase_requests',
        'name' => 'operations::app.menu.purchase-requests',
        'route' => 'admin.operations.purchase_requests.index',
        'sort' => 3,
        'icon' => '',
    ], [
        'key' => 'operations.purchase_orders',
        'name' => 'operations::app.menu.purchase-orders',
        'route' => 'admin.operations.purchase_orders.index',
        'sort' => 4,
        'icon' => '',
    ], [
        'key' => 'operations.receiving',
        'name' => 'operations::app.menu.receiving',
        'route' => 'admin.operations.receiving.index',
        'sort' => 5,
        'icon' => '',
    ], [
        'key' => 'operations.stock_requests',
        'name' => 'operations::app.menu.stock-requests',
        'route' => 'admin.operations.stock_requests.index',
        'sort' => 6,
        'icon' => '',
    ], [
        'key' => 'operations.transfers',
        'name' => 'operations::app.menu.transfers',
        'route' => 'admin.operations.transfers.index',
        'sort' => 7,
        'icon' => '',
    ], [
        'key' => 'operations.incidents',
        'name' => 'operations::app.menu.incidents',
        'route' => 'admin.operations.incidents.index',
        'sort' => 8,
        'icon' => '',
    ], [
        'key' => 'operations.adjustments',
        'name' => 'operations::app.menu.adjustments',
        'route' => 'admin.operations.adjustments.index',
        'sort' => 9,
        'icon' => '',
    ], [
        'key' => 'operations.supplier_returns',
        'name' => 'operations::app.menu.supplier-returns',
        'route' => 'admin.operations.supplier_returns.index',
        'sort' => 10,
        'icon' => '',
    ], [
        'key' => 'operations.disposals',
        'name' => 'operations::app.menu.disposals',
        'route' => 'admin.operations.disposals.index',
        'sort' => 11,
        'icon' => '',
    ], [
        'key' => 'operations.promotions',
        'name' => 'operations::app.menu.promotions',
        'route' => 'admin.operations.promotions.index',
        'sort' => 12,
        'icon' => '',
    ], [
        'key' => 'operations.price_changes',
        'name' => 'operations::app.menu.price-changes',
        'route' => 'admin.operations.price_changes.index',
        'sort' => 13,
        'icon' => '',
    ], [
        'key' => 'operations.reports',
        'name' => 'operations::app.menu.reports',
        'route' => 'admin.operations.reports.index',
        'sort' => 14,
        'icon' => '',
    ], [
        'key' => 'operations.setup',
        'name' => 'operations::app.menu.setup',
        'route' => 'admin.operations.suppliers.index',
        'sort' => 15,
        'icon' => '',
    ], [
        'key' => 'operations.setup.suppliers',
        'name' => 'operations::app.menu.suppliers',
        'route' => 'admin.operations.suppliers.index',
        'sort' => 1,
        'icon' => '',
    ], [
        'key' => 'operations.setup.locations',
        'name' => 'operations::app.menu.locations',
        'route' => 'admin.operations.locations.index',
        'sort' => 2,
        'icon' => '',
    ], [
        'key' => 'operations.setup.stock_policies',
        'name' => 'operations::app.menu.stock-policies',
        'route' => 'admin.operations.stock_policies.index',
        'sort' => 3,
        'icon' => '',
    ],
];
