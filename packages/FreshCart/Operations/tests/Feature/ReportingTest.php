<?php

use FreshCart\Operations\Console\Commands\InstallRoles;
use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\OperationsNotification;
use FreshCart\Operations\Models\StockPolicy;
use Illuminate\Support\Facades\Artisan;
use Webkul\User\Models\Role;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->warehouse = $this->makeLocation(Location::TYPE_WAREHOUSE, true);
    $this->branch = $this->makeLocation();
    $this->otherBranch = $this->makeLocation();
});

it('sends one low-stock alert per location and respects the cooldown (acceptance 14)', function () {
    $low = $this->makeProduct([$this->branch->id => 2]);
    $fine = $this->makeProduct([$this->branch->id => 50]);

    StockPolicy::create(['product_id' => $low->id, 'inventory_source_id' => $this->branch->id, 'reorder_level' => 5, 'min_stock' => 0]);
    StockPolicy::create(['product_id' => $fine->id, 'inventory_source_id' => $this->branch->id, 'reorder_level' => 5, 'min_stock' => 0]);

    $manager = $this->actingAsOperator(['operations.stock'], [$this->branch->id]);
    $outsider = $this->actingAsOperator(['operations.stock'], [$this->otherBranch->id]);

    Artisan::call('fc:monitor', ['check' => 'low-stock']);

    $alerts = OperationsNotification::where('type', 'low_stock')->where('admin_id', $manager->id)->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->title)->toContain($low->sku)
        ->and(OperationsNotification::where('type', 'low_stock')->where('admin_id', $outsider->id)->count())->toBe(0);

    // Within the cooldown nothing is sent again.
    Artisan::call('fc:monitor', ['check' => 'low-stock']);

    expect(OperationsNotification::where('type', 'low_stock')->where('admin_id', $manager->id)->count())->toBe(1);

    $this->travel(25)->hours();
    Artisan::call('fc:monitor', ['check' => 'low-stock']);

    expect(OperationsNotification::where('type', 'low_stock')->where('admin_id', $manager->id)->count())->toBe(2);
});

it('scopes reports to the user branch and role (acceptance 15)', function () {
    $this->makeProduct([$this->branch->id => 1, $this->otherBranch->id => 1]);

    $this->actingAsOperator(['operations.reports', 'operations.reports.inventory'], [$this->branch->id]);

    get(route('admin.operations.reports.ledger'))->assertOk();
    get(route('admin.operations.reports.ledger', ['location_id' => $this->otherBranch->id]))->assertForbidden();

    // Inventory permission does not open finance or sales reports.
    get(route('admin.operations.reports.valuation'))->assertUnauthorized();
    get(route('admin.operations.reports.branch_sales'))->assertUnauthorized();
    get(route('admin.operations.reports.adjustments'))->assertUnauthorized();
});

it('renders every report and exports CSV for a full-access manager', function () {
    $product = $this->makeProduct([$this->warehouse->id => 3]);

    StockPolicy::create(['product_id' => $product->id, 'inventory_source_id' => $this->warehouse->id, 'reorder_level' => 5, 'min_stock' => 0]);

    $this->actingAsOperator([
        'operations.reports', 'operations.reports.inventory', 'operations.reports.procurement', 'operations.reports.quality',
        'operations.reports.finance', 'operations.reports.sales', 'operations.scope.all_locations',
    ]);

    get(route('admin.operations.reports.index'))->assertOk();

    foreach (['ledger', 'low_stock', 'valuation', 'receiving_discrepancies', 'supplier_performance', 'transfers', 'adjustments', 'held_stock', 'recurring_incidents', 'disposals', 'expiry', 'branch_sales', 'promotion_performance'] as $report) {
        get(route('admin.operations.reports.'.$report))->assertOk();
    }

    $csv = get(route('admin.operations.reports.low_stock', ['export' => 'csv']))->assertOk()->streamedContent();

    expect($csv)->toContain($product->sku);
});

it('builds role dashboards from live data', function () {
    $this->actingAsOperator([
        'operations.inbox', 'operations.stock',
        'operations.approvals.level2', 'operations.receiving.create', 'operations.purchase_orders.create',
        'operations.stock_requests.create', 'operations.reports.finance', 'operations.reports.sales', 'operations.scope.all_locations',
    ]);

    get(route('admin.operations.dashboard.index'))
        ->assertOk()
        ->assertSee(trans('operations::app.dashboard.management'))
        ->assertSee(trans('operations::app.dashboard.finance'));

    get(route('admin.operations.approvals.index'))->assertOk();
    get(route('admin.operations.notifications.index'))->assertOk();
    get(route('admin.operations.stock.index'))->assertOk();
});

it('shows no dashboard sections to a role without them', function () {
    $this->actingAsOperator([]);

    get(route('admin.operations.dashboard.index'))
        ->assertOk()
        ->assertSee(trans('operations::app.dashboard.no-sections'))
        ->assertDontSee(trans('operations::app.dashboard.inventory-value'));
});

it('installs least-privilege roles idempotently', function () {
    Artisan::call('fc:install-roles');
    Artisan::call('fc:install-roles');

    expect(Role::where('name', 'like', 'FreshCart %')->count())->toBe(count(InstallRoles::roles()));

    $warehouse = Role::where('name', 'FreshCart Warehouse Personnel')->first();

    expect($warehouse->permissions)->toContain('operations.receiving.create')
        ->and($warehouse->permissions)->not->toContain('operations.adjustments.approve')
        ->and($warehouse->permissions)->not->toContain('operations.scope.all_locations');
});

it('keeps the setup screens working', function () {
    $this->actingAsOperator([
        'operations.setup', 'operations.setup.suppliers', 'operations.setup.suppliers.create',
        'operations.setup.locations', 'operations.setup.stock_policies', 'operations.scope.all_locations',
    ]);

    get(route('admin.operations.suppliers.index'))->assertOk();
    get(route('admin.operations.suppliers.create'))->assertOk();
    get(route('admin.operations.locations.index'))->assertOk();
    get(route('admin.operations.stock_policies.index'))->assertOk();
});
