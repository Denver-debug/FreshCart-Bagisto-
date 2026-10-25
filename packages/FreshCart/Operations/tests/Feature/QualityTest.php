<?php

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\Incident;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Models\SupplierReturn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

const STORE_STAFF = ['operations.incidents', 'operations.incidents.create'];

const SUPERVISOR = ['operations.incidents', 'operations.incidents.inspect', 'operations.incidents.classify'];

const INVENTORY_MANAGER = [
    'operations.adjustments',
    'operations.adjustments.create',
    'operations.adjustments.approve',
    'operations.adjustments.post',
    'operations.supplier_returns',
    'operations.supplier_returns.manage',
];

beforeEach(function () {
    Storage::fake('local');

    $this->branch = $this->makeLocation();
    $this->product = $this->makeProduct([$this->branch->id => 20]);
    $this->supplier = Supplier::create(['code' => 'SUP-'.Str::random(6), 'name' => 'Calapan Dairy', 'status' => 1]);
});

function reportIncident($test, string $type = 'damaged', int $qty = 5): Incident
{
    $test->actingAsOperator(STORE_STAFF, [$test->branch->id]);

    post(route('admin.operations.incidents.store'), [
        'inventory_source_id' => $test->branch->id,
        'incident_type' => $type,
        'items' => [['product_id' => $test->product->id, 'qty' => $qty]],
        'description' => 'Crushed cartons found on shelf',
        'photos' => [UploadedFile::fake()->image('damage.jpg')],
    ])->assertRedirect();

    return Incident::latest('id')->first();
}

function inspectedIncident($test, string $type = 'damaged', int $qty = 5): Incident
{
    $incident = reportIncident($test, $type, $qty);

    $test->actingAsOperator(SUPERVISOR, [$test->branch->id]);

    post(route('admin.operations.incidents.inspect', $incident->id), [
        'checklist' => ['packaging_intact' => 0, 'seal_intact' => 1],
        'inspection_remarks' => 'Packaging crushed',
    ])->assertRedirect();

    return $incident->fresh();
}

it('quarantines reported stock immediately so it cannot be sold (acceptance 9)', function () {
    $incident = reportIncident($this);

    expect($incident->status)->toBe(Incident::STATUS_QUARANTINED)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(15)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_QUARANTINE))->toBe(5)
        ->and($this->channelIndexQty($this->product->id))->toBe(15)
        ->and($incident->attachments()->count())->toBe(1);
});

it('returns saleable items to the shelf after classification', function () {
    $incident = inspectedIncident($this);

    post(route('admin.operations.incidents.classify', $incident->id), ['classification' => 'saleable'])->assertRedirect();

    expect($incident->fresh()->status)->toBe(Incident::STATUS_CLOSED)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(20)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_QUARANTINE))->toBe(0);
});

it('requires an approved adjustment before damaged stock is written off (acceptance 10)', function () {
    $incident = inspectedIncident($this);

    post(route('admin.operations.incidents.classify', $incident->id), ['classification' => 'damaged', 'remarks' => 'Unsellable'])->assertRedirect();

    $adjustment = Adjustment::find($incident->fresh()->adjustment_id);

    expect($adjustment->status)->toBe(Adjustment::STATUS_SUBMITTED)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_DAMAGED))->toBe(5);

    // Staff without adjustment permissions cannot post or approve.
    $this->actingAsOperator(STORE_STAFF, [$this->branch->id]);
    post(route('admin.operations.adjustments.post', $adjustment->id))->assertUnauthorized();
    post(route('admin.operations.adjustments.approve', $adjustment->id))->assertUnauthorized();

    // An authorized manager cannot post before approval.
    $this->actingAsOperator(INVENTORY_MANAGER, [$this->branch->id]);
    post(route('admin.operations.adjustments.post', $adjustment->id))->assertSessionHas('error');

    expect($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_DAMAGED))->toBe(5);

    post(route('admin.operations.adjustments.approve', $adjustment->id), ['remarks' => 'Confirmed'])->assertRedirect();
    post(route('admin.operations.adjustments.post', $adjustment->id))->assertRedirect();
    post(route('admin.operations.adjustments.post', $adjustment->id));

    expect($adjustment->fresh()->status)->toBe(Adjustment::STATUS_POSTED)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_DAMAGED))->toBe(0)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(15)
        ->and($incident->fresh()->status)->toBe(Incident::STATUS_CLOSED)
        // History is preserved (acceptance 13).
        ->and(StockMovement::where('reference_id', $incident->id)->where('reference_type', $incident->getMorphClass())->count())->toBeGreaterThan(0)
        ->and((int) StockMovement::where('reference_id', $adjustment->id)->where('movement_type', 'adjustment_out')->sum('qty'))->toBe(5)
        ->and(Incident::find($incident->id))->not->toBeNull();
});

it('does not let the reporter classify their own report', function () {
    $this->actingAsOperator(array_merge(STORE_STAFF, SUPERVISOR), [$this->branch->id]);

    post(route('admin.operations.incidents.store'), [
        'inventory_source_id' => $this->branch->id,
        'incident_type' => 'expired',
        'items' => [['product_id' => $this->product->id, 'qty' => 2]],
        'description' => 'Expired yesterday',
    ]);

    $incident = Incident::latest('id')->first();

    post(route('admin.operations.incidents.inspect', $incident->id), ['inspection_remarks' => 'Expired']);
    post(route('admin.operations.incidents.classify', $incident->id), ['classification' => 'expired'])->assertSessionHas('error');

    expect($incident->fresh()->status)->toBe(Incident::STATUS_INSPECTED);
});

it('tracks supplier returns and replacement stock', function () {
    $incident = inspectedIncident($this, 'recall', 4);

    post(route('admin.operations.incidents.classify', $incident->id), [
        'classification' => 'return_to_supplier',
        'supplier_id' => $this->supplier->id,
    ])->assertRedirect();

    $return = SupplierReturn::latest('id')->first();

    expect($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_SUPPLIER_RETURN))->toBe(4);

    $this->actingAsOperator(INVENTORY_MANAGER, [$this->branch->id]);

    post(route('admin.operations.supplier_returns.dispatch', $return->id))->assertRedirect();

    expect($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_SUPPLIER_RETURN))->toBe(0)
        ->and($return->fresh()->status)->toBe(SupplierReturn::STATUS_DISPATCHED);

    post(route('admin.operations.supplier_returns.resolve', $return->id), [
        'replaced' => [$return->items->first()->id => 4],
        'credit_amount' => 0,
    ])->assertRedirect();

    expect($return->fresh()->status)->toBe(SupplierReturn::STATUS_RESOLVED)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(20);
});

it('accepts customer returns straight into quarantine', function () {
    $incident = reportIncident($this, 'customer_return', 2);

    expect($incident->from_stock)->toBeFalse()
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(20)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_QUARANTINE))->toBe(2);
});

it('renders the quality screens', function () {
    $incident = inspectedIncident($this);

    post(route('admin.operations.incidents.classify', $incident->id), ['classification' => 'damaged']);

    $this->actingAsOperator(array_merge(STORE_STAFF, SUPERVISOR, INVENTORY_MANAGER), [$this->branch->id]);

    get(route('admin.operations.incidents.index'))->assertOk();
    get(route('admin.operations.incidents.create'))->assertOk();
    get(route('admin.operations.incidents.view', $incident->id))->assertOk();
    get(route('admin.operations.adjustments.index'))->assertOk();
    get(route('admin.operations.adjustments.create'))->assertOk();
    get(route('admin.operations.adjustments.view', $incident->fresh()->adjustment_id))->assertOk();
    get(route('admin.operations.supplier_returns.index'))->assertOk();
});
