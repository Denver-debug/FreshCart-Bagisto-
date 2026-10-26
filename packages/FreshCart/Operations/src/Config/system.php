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
    ],
];
