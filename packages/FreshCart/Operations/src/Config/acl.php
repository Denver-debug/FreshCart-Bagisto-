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
     * Module B: branch replenishment.
     */
    [
        'key' => 'operations.stock_requests',
        'name' => 'operations::app.acl.stock-requests',
        'route' => ['admin.operations.stock_requests.index', 'admin.operations.stock_requests.view'],
        'sort' => 6,
    ], [
        'key' => 'operations.stock_requests.create',
        'name' => 'operations::app.acl.create',
        'route' => [
            'admin.operations.stock_requests.create',
            'admin.operations.stock_requests.store',
            'admin.operations.stock_requests.edit',
            'admin.operations.stock_requests.update',
            'admin.operations.stock_requests.submit',
            'admin.operations.stock_requests.cancel',
        ],
        'sort' => 1,
    ], [
        'key' => 'operations.stock_requests.allocate',
        'name' => 'operations::app.acl.allocate',
        'route' => [
            'admin.operations.stock_requests.allocation',
            'admin.operations.stock_requests.allocate',
            'admin.operations.stock_requests.reject',
        ],
        'sort' => 2,
    ],

    /**
     * Module C: transfers.
     */
    [
        'key' => 'operations.transfers',
        'name' => 'operations::app.acl.transfers',
        'route' => ['admin.operations.transfers.index', 'admin.operations.transfers.view', 'admin.operations.transfers.print'],
        'sort' => 7,
    ], [
        'key' => 'operations.transfers.create',
        'name' => 'operations::app.acl.create',
        'route' => [
            'admin.operations.transfers.create',
            'admin.operations.transfers.store',
            'admin.operations.transfers.submit',
            'admin.operations.transfers.cancel',
            'admin.operations.transfers.return',
        ],
        'sort' => 1,
    ], [
        'key' => 'operations.transfers.approve',
        'name' => 'operations::app.acl.approve',
        'route' => ['admin.operations.transfers.approve', 'admin.operations.transfers.reject'],
        'sort' => 2,
    ], [
        'key' => 'operations.transfers.pick',
        'name' => 'operations::app.acl.pick',
        'route' => ['admin.operations.transfers.pick'],
        'sort' => 3,
    ], [
        'key' => 'operations.transfers.schedule',
        'name' => 'operations::app.acl.schedule',
        'route' => ['admin.operations.transfers.schedule'],
        'sort' => 4,
    ], [
        'key' => 'operations.transfers.dispatch',
        'name' => 'operations::app.acl.dispatch',
        'route' => ['admin.operations.transfers.dispatch'],
        'sort' => 5,
    ], [
        'key' => 'operations.transfers.receive',
        'name' => 'operations::app.acl.receive',
        'route' => ['admin.operations.transfers.receive'],
        'sort' => 6,
    ], [
        'key' => 'operations.transfers.close',
        'name' => 'operations::app.acl.close-discrepancy',
        'route' => ['admin.operations.transfers.close'],
        'sort' => 7,
    ],

    /**
     * Module D: returns, damages and adjustments.
     */
    [
        'key' => 'operations.incidents',
        'name' => 'operations::app.acl.incidents',
        'route' => ['admin.operations.incidents.index', 'admin.operations.incidents.view', 'admin.operations.incidents.photo'],
        'sort' => 8,
    ], [
        'key' => 'operations.incidents.create',
        'name' => 'operations::app.acl.report',
        'route' => ['admin.operations.incidents.create', 'admin.operations.incidents.store'],
        'sort' => 1,
    ], [
        'key' => 'operations.incidents.inspect',
        'name' => 'operations::app.acl.inspect',
        'route' => ['admin.operations.incidents.inspect'],
        'sort' => 2,
    ], [
        'key' => 'operations.incidents.classify',
        'name' => 'operations::app.acl.classify',
        'route' => ['admin.operations.incidents.classify'],
        'sort' => 3,
    ], [
        'key' => 'operations.adjustments',
        'name' => 'operations::app.acl.adjustments',
        'route' => ['admin.operations.adjustments.index', 'admin.operations.adjustments.view'],
        'sort' => 9,
    ], [
        'key' => 'operations.adjustments.create',
        'name' => 'operations::app.acl.create',
        'route' => [
            'admin.operations.adjustments.create',
            'admin.operations.adjustments.store',
            'admin.operations.adjustments.submit',
            'admin.operations.adjustments.cancel',
        ],
        'sort' => 1,
    ], [
        'key' => 'operations.adjustments.approve',
        'name' => 'operations::app.acl.approve',
        'route' => ['admin.operations.adjustments.approve', 'admin.operations.adjustments.reject'],
        'sort' => 2,
    ], [
        'key' => 'operations.adjustments.post',
        'name' => 'operations::app.acl.post',
        'route' => ['admin.operations.adjustments.post'],
        'sort' => 3,
    ], [
        'key' => 'operations.supplier_returns',
        'name' => 'operations::app.acl.supplier-returns',
        'route' => ['admin.operations.supplier_returns.index', 'admin.operations.supplier_returns.view'],
        'sort' => 10,
    ], [
        'key' => 'operations.supplier_returns.manage',
        'name' => 'operations::app.acl.manage',
        'route' => [
            'admin.operations.supplier_returns.dispatch',
            'admin.operations.supplier_returns.resolve',
            'admin.operations.supplier_returns.cancel',
        ],
        'sort' => 1,
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
