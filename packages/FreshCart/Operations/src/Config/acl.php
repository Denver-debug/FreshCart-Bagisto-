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
