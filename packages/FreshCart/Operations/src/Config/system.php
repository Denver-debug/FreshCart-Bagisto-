<?php

/**
 * Configure → Operations. Every business rule threshold is editable here;
 * values are read through FreshCart\Operations\Support\Settings.
 */
return [
    [
        'key' => 'operations',
        'name' => 'operations::app.configuration.title',
        'info' => 'operations::app.configuration.info',
        'sort' => 9,
    ], [
        'key' => 'operations.approvals',
        'name' => 'operations::app.configuration.approvals.title',
        'info' => 'operations::app.configuration.approvals.info',
        'icon' => 'settings/order.svg',
        'sort' => 1,
    ], [
        'key' => 'operations.approvals.general',
        'name' => 'operations::app.configuration.approvals.general',
        'info' => 'operations::app.configuration.approvals.general-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'allow_self_approval',
                'title' => 'operations::app.configuration.approvals.allow-self-approval',
                'info' => 'operations::app.configuration.approvals.allow-self-approval-info',
                'type' => 'boolean',
                'default' => false,
            ],
        ],
    ], [
        'key' => 'operations.approvals.thresholds',
        'name' => 'operations::app.configuration.approvals.thresholds',
        'info' => 'operations::app.configuration.approvals.thresholds-info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'purchase_requests',
                'title' => 'operations::app.configuration.approvals.pr-threshold',
                'type' => 'number',
                'default' => 50000,
                'validation' => 'numeric|min:0',
            ], [
                'name' => 'adjustments',
                'title' => 'operations::app.configuration.approvals.adjustment-threshold',
                'type' => 'number',
                'default' => 10000,
                'validation' => 'numeric|min:0',
            ], [
                'name' => 'disposals',
                'title' => 'operations::app.configuration.approvals.disposal-threshold',
                'type' => 'number',
                'default' => 10000,
                'validation' => 'numeric|min:0',
            ],
        ],
    ], [
        'key' => 'operations.receiving',
        'name' => 'operations::app.configuration.receiving.title',
        'info' => 'operations::app.configuration.receiving.info',
        'icon' => 'settings/shipping.svg',
        'sort' => 2,
    ], [
        'key' => 'operations.receiving.general',
        'name' => 'operations::app.configuration.receiving.general',
        'info' => 'operations::app.configuration.receiving.general-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'over_receipt_tolerance',
                'title' => 'operations::app.configuration.receiving.tolerance',
                'info' => 'operations::app.configuration.receiving.tolerance-info',
                'type' => 'number',
                'default' => 0,
                'validation' => 'numeric|min:0|max:100',
            ],
        ],
    ], [
        'key' => 'operations.inventory',
        'name' => 'operations::app.configuration.inventory.title',
        'info' => 'operations::app.configuration.inventory.info',
        'icon' => 'settings/inventory.svg',
        'sort' => 3,
    ], [
        'key' => 'operations.inventory.alerts',
        'name' => 'operations::app.configuration.inventory.alerts',
        'info' => 'operations::app.configuration.inventory.alerts-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'default_reorder_level',
                'title' => 'operations::app.configuration.inventory.default-reorder-level',
                'info' => 'operations::app.configuration.inventory.default-reorder-level-info',
                'type' => 'number',
                'default' => 0,
                'validation' => 'integer|min:0',
            ], [
                'name' => 'cooldown_hours',
                'title' => 'operations::app.configuration.inventory.cooldown-hours',
                'info' => 'operations::app.configuration.inventory.cooldown-hours-info',
                'type' => 'number',
                'default' => 24,
                'validation' => 'integer|min:1',
            ],
        ],
    ], [
        'key' => 'operations.transfers',
        'name' => 'operations::app.configuration.transfers.title',
        'info' => 'operations::app.configuration.transfers.info',
        'icon' => 'settings/shipping-method.svg',
        'sort' => 4,
    ], [
        'key' => 'operations.transfers.general',
        'name' => 'operations::app.configuration.transfers.general',
        'info' => 'operations::app.configuration.transfers.general-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'require_approval',
                'title' => 'operations::app.configuration.transfers.require-approval',
                'info' => 'operations::app.configuration.transfers.require-approval-info',
                'type' => 'boolean',
                'default' => true,
            ], [
                'name' => 'overdue_hours',
                'title' => 'operations::app.configuration.transfers.overdue-hours',
                'info' => 'operations::app.configuration.transfers.overdue-hours-info',
                'type' => 'number',
                'default' => 24,
                'validation' => 'integer|min:1',
            ],
        ],
    ], [
        'key' => 'operations.retirement',
        'name' => 'operations::app.configuration.retirement.title',
        'info' => 'operations::app.configuration.retirement.info',
        'icon' => 'settings/product.svg',
        'sort' => 5,
    ], [
        'key' => 'operations.retirement.criteria',
        'name' => 'operations::app.configuration.retirement.criteria',
        'info' => 'operations::app.configuration.retirement.criteria-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'expiry_warning_days',
                'title' => 'operations::app.configuration.retirement.expiry-warning-days',
                'type' => 'number',
                'default' => 7,
                'validation' => 'integer|min:0',
            ], [
                'name' => 'slow_moving_days',
                'title' => 'operations::app.configuration.retirement.slow-moving-days',
                'type' => 'number',
                'default' => 90,
                'validation' => 'integer|min:1',
            ], [
                'name' => 'slow_moving_max_units',
                'title' => 'operations::app.configuration.retirement.slow-moving-max-units',
                'info' => 'operations::app.configuration.retirement.slow-moving-max-units-info',
                'type' => 'number',
                'default' => 0,
                'validation' => 'integer|min:0',
            ],
        ],
    ], [
        'key' => 'operations.notifications',
        'name' => 'operations::app.configuration.notifications.title',
        'info' => 'operations::app.configuration.notifications.info',
        'icon' => 'settings/email.svg',
        'sort' => 7,
    ], [
        'key' => 'operations.notifications.general',
        'name' => 'operations::app.configuration.notifications.general',
        'info' => 'operations::app.configuration.notifications.general-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'email_enabled',
                'title' => 'operations::app.configuration.notifications.email-enabled',
                'info' => 'operations::app.configuration.notifications.email-enabled-info',
                'type' => 'boolean',
                'default' => false,
            ],
        ],
    ],
];
