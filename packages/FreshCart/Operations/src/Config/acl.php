<?php

/**
 * FreshCart Operations permissions. Every operations route is mapped here so
 * Bagisto's Bouncer middleware enforces it server-side. Keys without routes
 * (`approvals.level2`, `scope.all_locations`, `receiving.override`) are checked
 * explicitly in the services.
 */
return [
    [
        'key' => 'operations',
        'name' => 'operations::app.acl.operations',
        'route' => [
            'admin.operations.products.search',
        ],
        'sort' => 3,
    ], [
        'key' => 'operations.stock',
        'name' => 'operations::app.acl.stock',
        'route' => ['admin.operations.stock.index', 'admin.operations.stock.view'],
        'sort' => 1,
    ],

    /**
     * Setup.
     */
    [
        'key' => 'operations.setup',
        'name' => 'operations::app.acl.setup',
        'route' => [],
        'sort' => 2,
    ], [
        'key' => 'operations.setup.suppliers',
        'name' => 'operations::app.acl.suppliers',
        'route' => ['admin.operations.suppliers.index'],
        'sort' => 1,
    ], [
        'key' => 'operations.setup.suppliers.create',
        'name' => 'operations::app.acl.create',
        'route' => ['admin.operations.suppliers.create', 'admin.operations.suppliers.store'],
        'sort' => 1,
    ], [
        'key' => 'operations.setup.suppliers.edit',
        'name' => 'operations::app.acl.edit',
        'route' => ['admin.operations.suppliers.edit', 'admin.operations.suppliers.update'],
        'sort' => 2,
    ], [
        'key' => 'operations.setup.suppliers.delete',
        'name' => 'operations::app.acl.delete',
        'route' => ['admin.operations.suppliers.delete'],
        'sort' => 3,
    ], [
        'key' => 'operations.setup.locations',
        'name' => 'operations::app.acl.locations',
        'route' => ['admin.operations.locations.index', 'admin.operations.locations.update'],
        'sort' => 2,
    ], [
        'key' => 'operations.setup.stock_policies',
        'name' => 'operations::app.acl.stock-policies',
        'route' => [
            'admin.operations.stock_policies.index',
            'admin.operations.stock_policies.store',
            'admin.operations.stock_policies.import',
            'admin.operations.stock_policies.delete',
        ],
        'sort' => 3,
    ],

    /**
     * Module A: procurement.
     */
    [
        'key' => 'operations.purchase_requests',
        'name' => 'operations::app.acl.purchase-requests',
        'route' => ['admin.operations.purchase_requests.index', 'admin.operations.purchase_requests.view'],
        'sort' => 3,
    ], [
        'key' => 'operations.purchase_requests.create',
        'name' => 'operations::app.acl.create',
        'route' => [
            'admin.operations.purchase_requests.create',
            'admin.operations.purchase_requests.store',
            'admin.operations.purchase_requests.edit',
            'admin.operations.purchase_requests.update',
            'admin.operations.purchase_requests.submit',
            'admin.operations.purchase_requests.cancel',
        ],
        'sort' => 1,
    ], [
        'key' => 'operations.purchase_requests.approve',
        'name' => 'operations::app.acl.approve',
        'route' => ['admin.operations.purchase_requests.approve', 'admin.operations.purchase_requests.reject'],
        'sort' => 2,
    ], [
        'key' => 'operations.purchase_orders',
        'name' => 'operations::app.acl.purchase-orders',
        'route' => [
            'admin.operations.purchase_orders.index',
            'admin.operations.purchase_orders.view',
            'admin.operations.purchase_orders.print',
        ],
        'sort' => 4,
    ], [
        'key' => 'operations.purchase_orders.create',
        'name' => 'operations::app.acl.manage',
        'route' => [
            'admin.operations.purchase_orders.create',
            'admin.operations.purchase_orders.store',
            'admin.operations.purchase_orders.issue',
            'admin.operations.purchase_orders.close',
            'admin.operations.purchase_orders.cancel',
        ],
        'sort' => 1,
    ], [
        'key' => 'operations.receiving',
        'name' => 'operations::app.acl.receiving',
        'route' => ['admin.operations.receiving.index', 'admin.operations.receiving.view'],
        'sort' => 5,
    ], [
        'key' => 'operations.receiving.create',
        'name' => 'operations::app.acl.receive',
        'route' => ['admin.operations.receiving.create', 'admin.operations.receiving.store'],
        'sort' => 1,
    ], [
        'key' => 'operations.receiving.override',
        'name' => 'operations::app.acl.over-receipt',
        'route' => [],
        'sort' => 2,
    ],

    /**
     * Cross-cutting authorities.
     */
    [
        'key' => 'operations.approvals',
        'name' => 'operations::app.acl.approvals',
        'route' => [],
        'sort' => 15,
    ], [
        'key' => 'operations.approvals.level2',
        'name' => 'operations::app.acl.level2',
        'route' => [],
        'sort' => 1,
    ], [
        'key' => 'operations.scope',
        'name' => 'operations::app.acl.scope',
        'route' => [],
        'sort' => 16,
    ], [
        'key' => 'operations.scope.all_locations',
        'name' => 'operations::app.acl.all-locations',
        'route' => [],
        'sort' => 1,
    ],
];
