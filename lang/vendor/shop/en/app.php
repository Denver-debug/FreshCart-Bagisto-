<?php

/**
 * FreshCart branding overrides for Bagisto's storefront (English).
 * Laravel merges these over packages/Webkul/Shop/src/Resources/lang/en/app.php.
 */
$footer = '© :current_year FreshCart Supermarket Corp., Calapan City, Oriental Mindoro. All rights reserved.';

return [
    'customers' => [
        'forgot-password' => ['bagisto' => 'FreshCart', 'footer' => $footer],
        'reset-password' => ['bagisto' => 'FreshCart', 'footer' => $footer],
        'login-form' => ['bagisto' => 'FreshCart', 'footer' => $footer],
        'signup-form' => ['bagisto' => 'FreshCart', 'footer' => $footer],
    ],

    'components' => [
        'layouts' => [
            'header' => [
                'desktop' => ['bottom' => ['bagisto' => 'FreshCart']],
                'mobile' => ['bagisto' => 'FreshCart'],
            ],

            'footer' => [
                'footer-text' => $footer,
            ],
        ],
    ],

    'checkout' => [
        'cart' => [
            'index' => ['bagisto' => 'FreshCart'],
        ],
    ],
];
