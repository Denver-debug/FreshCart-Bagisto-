<?php

/**
 * FreshCart ↔ Akaunting / Frappe HR connector settings.
 *
 * Everything is read from .env so credentials never live in the repository.
 * A connector with an empty URL is treated as "not configured": events are
 * still queued in the outbox and are pushed once the URL is set.
 */
return [
    'akaunting' => [
        'url' => rtrim((string) env('AKAUNTING_URL', ''), '/'),
        'email' => env('AKAUNTING_EMAIL'),
        'password' => env('AKAUNTING_PASSWORD'),
        'company_id' => (int) env('AKAUNTING_COMPANY_ID', 1),
        'timeout' => (int) env('AKAUNTING_TIMEOUT', 15),

        /**
         * Akaunting ids that must already exist in the company.
         */
        'account_id' => (int) env('AKAUNTING_ACCOUNT_ID', 1),
        'payment_method' => env('AKAUNTING_PAYMENT_METHOD', 'offline-payments.cash.1'),
        'categories' => [
            'sales' => (int) env('AKAUNTING_CATEGORY_SALES', 0),
            'refunds' => (int) env('AKAUNTING_CATEGORY_REFUNDS', 0),
            'purchases' => (int) env('AKAUNTING_CATEGORY_PURCHASES', 0),
            'supplier_credits' => (int) env('AKAUNTING_CATEGORY_SUPPLIER_CREDITS', 0),
            'payroll' => (int) env('AKAUNTING_CATEGORY_PAYROLL', 0),
            'payroll_statutory' => (int) env('AKAUNTING_CATEGORY_PAYROLL_STATUTORY', 0),
        ],

        /**
         * Used when a payroll summary carries no currency.
         */
        'currency' => env('AKAUNTING_CURRENCY', 'PHP'),

        /**
         * Days after the payroll posting date that SSS / PhilHealth / Pag-IBIG
         * and BIR remittance bills fall due.
         */
        'statutory_due_days' => (int) env('AKAUNTING_STATUTORY_DUE_DAYS', 30),
    ],

    /**
     * NovaCore HR (the modified Frappe HR). Uses its v1 integration API
     * (/api/method/novacore_integrations.api.v1.*) and ERP Event Subscription webhooks.
     */
    'frappe' => [
        'url' => rtrim((string) env('FRAPPE_URL', ''), '/'),
        'api_key' => env('FRAPPE_API_KEY'),
        'api_secret' => env('FRAPPE_API_SECRET'),
        'webhook_secret' => env('FRAPPE_WEBHOOK_SECRET'),
        'timeout' => (int) env('FRAPPE_TIMEOUT', 15),

        /**
         * Payroll company to import (blank = every company).
         */
        'company' => env('FRAPPE_COMPANY'),

        /**
         * Name of the BIR withholding tax deduction component
         * (PH Statutory Settings → Withholding Tax Component).
         */
        'withholding_component' => env('FRAPPE_WITHHOLDING_COMPONENT', 'Withholding Tax'),

        /**
         * Frappe designation (or department) => Bagisto role name. Checked in
         * that order; unmatched employees get `default_role`.
         * Example: FRAPPE_ROLE_MAP={"Store Manager":"FreshCart Store Manager"}
         */
        'role_map' => json_decode((string) env('FRAPPE_ROLE_MAP', '{}'), true) ?: [],
        'default_role' => env('FRAPPE_DEFAULT_ROLE', 'FreshCart Warehouse Personnel'),

        /**
         * Frappe branch => Bagisto inventory source code, used to assign the
         * admin's FreshCart locations. Example: FRAPPE_BRANCH_MAP={"Makati":"makati"}
         */
        'branch_map' => json_decode((string) env('FRAPPE_BRANCH_MAP', '{}'), true) ?: [],
    ],

    /**
     * Individual flows can be switched off without removing credentials.
     */
    'flows' => [
        'sales' => (bool) env('FC_SYNC_SALES', true),
        'procurement' => (bool) env('FC_SYNC_PROCUREMENT', true),
        'employees' => (bool) env('FC_SYNC_EMPLOYEES', true),
        'payroll' => (bool) env('FC_SYNC_PAYROLL', true),
    ],

    'outbox' => [
        'max_attempts' => (int) env('FC_SYNC_MAX_ATTEMPTS', 10),
        'batch' => (int) env('FC_SYNC_BATCH', 50),
    ],
];
