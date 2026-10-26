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
        ],
        'sort' => 3,
    ],
];
