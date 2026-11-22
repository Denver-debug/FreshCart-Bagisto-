<?php

namespace FreshCart\Integrations\Tests;

use FreshCart\Operations\Tests\Concerns\OperationsTestBench;
use Tests\TestCase;
use Webkul\Admin\Tests\Concerns\AdminTestBench;
use Webkul\Core\Tests\Concerns\CoreAssertions;

class IntegrationsTestCase extends TestCase
{
    use AdminTestBench, CoreAssertions, OperationsTestBench;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'freshcart-integrations.akaunting.url' => 'https://akaunting.test',
            'freshcart-integrations.akaunting.email' => 'api@freshcart.test',
            'freshcart-integrations.akaunting.password' => 'secret',
            'freshcart-integrations.akaunting.company_id' => 1,
            'freshcart-integrations.akaunting.account_id' => 1,
            'freshcart-integrations.akaunting.categories' => [
                'sales' => 3, 'refunds' => 4, 'purchases' => 5, 'supplier_credits' => 6, 'payroll' => 7, 'payroll_statutory' => 8,
            ],
            'freshcart-integrations.frappe.url' => 'https://frappe.test',
            'freshcart-integrations.frappe.api_key' => 'key',
            'freshcart-integrations.frappe.api_secret' => 'secret',
            'freshcart-integrations.frappe.webhook_secret' => 'hook-secret',
        ]);
    }
}
