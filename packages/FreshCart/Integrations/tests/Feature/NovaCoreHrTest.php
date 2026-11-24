<?php

use FreshCart\Integrations\Models\IntegrationLink;
use FreshCart\Integrations\Models\OutboxMessage;
use FreshCart\Integrations\Services\OutboxProcessor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

use function Pest\Laravel\artisan;
use function Pest\Laravel\call;

/**
 * Sends a webhook exactly as NovaCore's erp/events.py deliver() does.
 */
function novacoreWebhook(string $event, array $data, string $secret = 'hook-secret')
{
    $body = json_encode(['id' => 'd-'.uniqid(), 'event' => $event, 'occurred_at' => '2026-11-16T09:00:00', 'data' => $data], JSON_UNESCAPED_SLASHES);

    return call('POST', '/integrations/novacore-hr', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_NOVACORE_EVENT' => $event,
        'HTTP_X_NOVACORE_DELIVERY' => uniqid(),
        'HTTP_X_NOVACORE_SIGNATURE' => hash_hmac('sha256', $body, $secret),
    ], $body);
}

function novacoreEmployee(array $overrides = []): array
{
    return array_merge([
        'employee_id' => 'HR-EMP-00042',
        'full_name' => 'Maria Santos',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'status' => 'Active',
        'employment_stage' => 'Regular',
        'company' => 'NovaCore Business Solutions Inc.',
        'department' => 'Operations - NBSI',
        'designation' => 'Store Manager',
        'branch' => 'Makati',
        'work_email' => 'maria.santos@novacore.test',
        'user_id' => 'maria.santos@novacore.test',
        'modified' => '2026-11-20 10:00:00.000000',
    ], $overrides);
}

function novacorePayrollSummary(array $overrides = []): array
{
    return array_merge([
        'payroll_entry' => 'HR-PRUN-2026-00011',
        'company' => 'NovaCore Business Solutions Inc.',
        'period' => ['start' => '2026-11-01', 'end' => '2026-11-15'],
        'posting_date' => '2026-11-15',
        'currency' => 'PHP',
        'released' => true,
        'approval_status' => 'Approved',
        'totals' => ['employees' => 12, 'gross_pay' => 180000, 'total_deduction' => 21000, 'net_pay' => 159000],
        'by_department' => [],
        'components' => [
            ['type' => 'earning', 'component' => 'Basic Salary', 'amount' => 180000],
            ['type' => 'deduction', 'component' => 'Withholding Tax', 'amount' => 9000],
            ['type' => 'deduction', 'component' => 'SSS Contribution', 'amount' => 8100],
        ],
        'statutory' => [
            ['agency' => 'SSS', 'employee_share' => 8100, 'employer_share' => 16200, 'ec_share' => 120],
            ['agency' => 'PhilHealth', 'employee_share' => 2250, 'employer_share' => 2250, 'ec_share' => 0],
            ['agency' => 'Pag-IBIG', 'employee_share' => 0, 'employer_share' => 0, 'ec_share' => 0],
        ],
    ], $overrides);
}

beforeEach(function () {
    $this->storeRole = Role::factory()->create(['name' => 'FreshCart Store Manager', 'permission_type' => 'custom', 'permissions' => ['operations']]);
    $this->defaultRole = Role::factory()->create(['name' => 'FreshCart Warehouse Personnel', 'permission_type' => 'custom', 'permissions' => ['operations']]);

    config([
        'freshcart-integrations.frappe.role_map' => ['Store Manager' => 'FreshCart Store Manager'],
        'freshcart-integrations.frappe.default_role' => 'FreshCart Warehouse Personnel',
    ]);
});

it('rejects webhooks with a wrong signature', function () {
    novacoreWebhook('employee.created', novacoreEmployee(), 'wrong-secret')->assertUnauthorized();

    expect(Admin::where('email', 'maria.santos@novacore.test')->exists())->toBeFalse();
});

it('answers the NovaCore test event', function () {
    novacoreWebhook('ping', ['message' => 'test'])->assertOk()->assertJson(['result' => 'pong']);
});

it('creates, updates and disables a Bagisto admin from employee events', function () {
    $branch = $this->makeLocation();
    config(['freshcart-integrations.frappe.branch_map' => ['Makati' => $branch->code]]);

    novacoreWebhook('employee.created', novacoreEmployee())->assertOk()->assertJson(['result' => 'created']);

    $admin = Admin::where('email', 'maria.santos@novacore.test')->first();

    expect($admin->name)->toBe('Maria Santos')
        ->and($admin->role_id)->toBe($this->storeRole->id)
        ->and((int) $admin->status)->toBe(1)
        ->and(IntegrationLink::localId('frappe', 'admin', 'HR-EMP-00042'))->toBe((string) $admin->id)
        ->and(DB::table('fc_admin_locations')->where('admin_id', $admin->id)->pluck('inventory_source_id')->all())->toBe([$branch->id]);

    novacoreWebhook('employee.updated', novacoreEmployee(['designation' => 'Stock Clerk', 'work_email' => 'm.santos@novacore.test']))
        ->assertJson(['result' => 'updated']);

    expect($admin->fresh()->role_id)->toBe($this->defaultRole->id)
        ->and($admin->fresh()->email)->toBe('m.santos@novacore.test');

    novacoreWebhook('employee.updated', novacoreEmployee(['employment_stage' => 'Suspended']));

    expect((int) $admin->fresh()->status)->toBe(0);

    novacoreWebhook('employee.updated', novacoreEmployee());

    expect((int) $admin->fresh()->status)->toBe(1);

    novacoreWebhook('employee.separated', novacoreEmployee(['status' => 'Left', 'employment_stage' => 'Separated']))
        ->assertJson(['result' => 'updated']);

    expect((int) $admin->fresh()->status)->toBe(0);
});

it('never modifies a full-access administrator', function () {
    $superRole = Role::factory()->create(['permission_type' => 'all']);
    $super = Admin::factory()->create(['email' => 'owner@novacore.test', 'role_id' => $superRole->id, 'status' => 1]);

    novacoreWebhook('employee.separated', novacoreEmployee(['work_email' => 'owner@novacore.test', 'status' => 'Left']))
        ->assertJson(['result' => 'linked']);

    expect((int) $super->fresh()->status)->toBe(1)
        ->and($super->fresh()->role_id)->toBe($superRole->id);
});

it('skips employees without an e-mail address', function () {
    novacoreWebhook('employee.created', novacoreEmployee(['work_email' => null, 'user_id' => null]))
        ->assertJson(['result' => 'skipped:no e-mail address in Frappe HR']);
});

it('posts a released payroll run to Akaunting as net pay plus remittance bills', function () {
    $id = 500;
    Http::fake([
        'akaunting.test/api/*' => function (Request $request) use (&$id) {
            return $request->method() === 'GET'
                ? Http::response(['data' => []])
                : Http::response(['data' => ['id' => ++$id]], 201);
        },
    ]);

    novacoreWebhook('payroll.released', novacorePayrollSummary())->assertJson(['result' => 'queued']);
    novacoreWebhook('payroll.released', novacorePayrollSummary())->assertJson(['result' => 'skipped:already queued']);

    app(OutboxProcessor::class)->run();

    $posted = fn (string $path) => Http::recorded(fn (Request $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/api/'.$path))
        ->map(fn ($pair) => $pair[0]->data())->values();

    $net = $posted('transactions')->sole();

    expect($net)->toMatchArray([
        'type' => 'expense',
        'number' => 'PAY-HR-PRUN-2026-00011',
        'amount' => 159000.0,
        'currency_code' => 'PHP',
        'category_id' => 7,
        'reference' => 'HR-PRUN-2026-00011',
    ]);

    $bills = $posted('documents')->keyBy('contact_name');

    expect($bills->keys()->all())->toEqualCanonicalizing(['SSS', 'PhilHealth', 'BIR'])
        ->and($bills['SSS'])->toMatchArray(['type' => 'bill', 'amount' => 24420.0, 'category_id' => 8, 'document_number' => 'HR-PRUN-2026-00011-SSS'])
        ->and(collect($bills['SSS']['items'])->pluck('price', 'name')->all())
        ->toBe(['SSS Employee share' => 8100.0, 'SSS Employer share' => 16200.0, 'SSS EC contribution' => 120.0])
        ->and($bills['PhilHealth']['amount'])->toBe(4500.0)
        ->and($bills['BIR']['amount'])->toBe(9000.0)
        ->and(substr($bills['BIR']['due_at'], 0, 10))->toBe('2026-12-15')
        ->and($posted('contacts')->pluck('name')->all())->toEqualCanonicalizing(['SSS', 'PhilHealth', 'BIR']);

    // A retry posts nothing twice.
    OutboxMessage::where('action', 'akaunting.payroll')->update(['status' => 'pending']);
    app(OutboxProcessor::class)->run();

    expect($posted('transactions'))->toHaveCount(1)
        ->and($posted('documents'))->toHaveCount(3);
});

it('ignores payroll of another company when FRAPPE_COMPANY is set', function () {
    config(['freshcart-integrations.frappe.company' => 'FreshCart Supermarket Corp.']);

    novacoreWebhook('payroll.released', novacorePayrollSummary())->assertJson(['result' => 'skipped:other company']);
});

it('pulls changed employees and released payroll runs through the v1 API', function () {
    Http::fake([
        'frappe.test/api/method/novacore_integrations.api.v1.get_employees*' => Http::response(['message' => [
            'data' => [novacoreEmployee()], 'count' => 1, 'next_offset' => null,
        ]]),
        'frappe.test/api/method/novacore_integrations.api.v1.list_payroll_runs*' => Http::response(['message' => [
            'data' => [['name' => 'HR-PRUN-2026-00011', 'released' => 1]],
        ]]),
        'frappe.test/api/method/novacore_integrations.api.v1.get_payroll_summary*' => Http::response(['message' => novacorePayrollSummary()]),
    ]);

    artisan('fc:integrations:pull-frappe')->assertSuccessful();

    expect(Admin::where('email', 'maria.santos@novacore.test')->exists())->toBeTrue()
        ->and(OutboxMessage::where(['action' => 'akaunting.payroll', 'subject_id' => 'HR-PRUN-2026-00011'])->exists())->toBeTrue()
        ->and(DB::table('fc_integration_cursors')->where('key', 'frappe.employee')->value('value'))->toBe('2026-11-20 10:00:00.000000');

    artisan('fc:integrations:pull-frappe')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'get_employees')
        && ($request->data()['modified_since'] ?? null) === '2026-11-20 10:00:00.000000'
        && $request->header('Authorization')[0] === 'token key:secret');

    // The second run does not fetch the summary of a run that is already queued.
    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), 'get_payroll_summary')))->toHaveCount(1);
});
