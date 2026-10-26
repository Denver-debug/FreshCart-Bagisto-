<?php

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockPolicy;
use FreshCart\Operations\Models\StockRequest;
use FreshCart\Operations\Models\Transfer;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

const STORE_MANAGER = [
    'operations.stock_requests',
    'operations.stock_requests.create',
    'operations.transfers',
    'operations.transfers.create',
    'operations.transfers.receive',
];

const WAREHOUSE_STAFF = [
    'operations.stock_requests',
    'operations.stock_requests.allocate',
    'operations.transfers',
    'operations.transfers.pick',
    'operations.transfers.dispatch',
    'operations.transfers.schedule',
];

beforeEach(function () {
    $this->warehouse = $this->makeLocation(Location::TYPE_WAREHOUSE, true);
    $this->branch = $this->makeLocation();
    $this->otherBranch = $this->makeLocation();
    $this->product = $this->makeProduct([$this->warehouse->id => 50, $this->branch->id => 2, $this->otherBranch->id => 20]);

    $this->setOperationsConfig('transfers.general.require_approval', 1);
});

function submittedBranchRequest($test, int $qty): StockRequest
{
    $test->actingAsOperator(STORE_MANAGER, [$test->branch->id]);

    post(route('admin.operations.stock_requests.store'), [
        'inventory_source_id' => $test->branch->id,
        'priority' => 'high',
        'items' => [['product_id' => $test->product->id, 'qty' => $qty]],
    ])->assertRedirect();

    $request = StockRequest::latest('id')->first();

    post(route('admin.operations.stock_requests.submit', $request->id))->assertRedirect();

    return $request->fresh('items');
}

it('suggests allocations from warehouse stock above its minimum and keeps the rest on backorder', function () {
    StockPolicy::create(['product_id' => $this->product->id, 'inventory_source_id' => $this->warehouse->id, 'reorder_level' => 20, 'min_stock' => 10]);

    $request = submittedBranchRequest($this, 60);

    $this->actingAsOperator(WAREHOUSE_STAFF, [$this->warehouse->id]);

    get(route('admin.operations.stock_requests.allocation', $request->id))->assertOk()->assertSee('40');

    $itemId = $request->items->first()->id;

    // Allocating more than the warehouse can spare is refused (acceptance 6).
    post(route('admin.operations.stock_requests.allocate', $request->id), [
        'warehouse_id' => $this->warehouse->id,
        'allocations' => [$itemId => ['qty' => 55, 'reason' => 'Big weekend']],
    ])->assertSessionHas('error');

    // Changing the suggestion needs a reason.
    post(route('admin.operations.stock_requests.allocate', $request->id), [
        'warehouse_id' => $this->warehouse->id,
        'allocations' => [$itemId => ['qty' => 30]],
    ])->assertSessionHas('error');

    post(route('admin.operations.stock_requests.allocate', $request->id), [
        'warehouse_id' => $this->warehouse->id,
        'allocations' => [$itemId => ['qty' => 40]],
    ])->assertRedirect();

    $request->refresh();
    $transfer = Transfer::where('stock_request_id', $request->id)->first();

    expect($request->status)->toBe(StockRequest::STATUS_PARTIALLY_ALLOCATED)
        ->and($request->items->first()->qty_allocated)->toBe(40)
        ->and($request->items->first()->outstandingQty())->toBe(20)
        ->and($transfer->status)->toBe(Transfer::STATUS_APPROVED)
        ->and($transfer->type)->toBe(Transfer::TYPE_REPLENISHMENT)
        // Allocation is a document only: no stock has moved (acceptance 7).
        ->and($this->sellable($this->product->id, $this->warehouse->id))->toBe(50);
});

it('moves stock only at dispatch and receipt (acceptance 7)', function () {
    $request = submittedBranchRequest($this, 10);

    $this->actingAsOperator(WAREHOUSE_STAFF, [$this->warehouse->id]);

    post(route('admin.operations.stock_requests.allocate', $request->id), [
        'warehouse_id' => $this->warehouse->id,
        'allocations' => [$request->items->first()->id => ['qty' => 10]],
    ]);

    $transfer = Transfer::where('stock_request_id', $request->id)->first();
    $item = $transfer->items()->first();

    post(route('admin.operations.transfers.pick', $transfer->id), ['picked' => [$item->id => 10]])->assertRedirect();

    expect($this->sellable($this->product->id, $this->warehouse->id))->toBe(50);

    $key = (string) Str::uuid();

    post(route('admin.operations.transfers.dispatch', $transfer->id), ['idempotency_key' => $key])->assertRedirect();
    post(route('admin.operations.transfers.dispatch', $transfer->id), ['idempotency_key' => $key])->assertRedirect();

    expect($transfer->fresh()->status)->toBe(Transfer::STATUS_IN_TRANSIT)
        ->and($this->sellable($this->product->id, $this->warehouse->id))->toBe(40)
        ->and($this->held($this->product->id, $this->warehouse->id, StockHold::BUCKET_IN_TRANSIT))->toBe(10)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(2);

    $this->actingAsOperator(STORE_MANAGER, [$this->branch->id]);

    $receiptKey = (string) Str::uuid();

    post(route('admin.operations.transfers.receive', $transfer->id), [
        'idempotency_key' => $receiptKey,
        'receiver_name' => 'Branch Clerk',
        'lines' => [$item->id => ['qty_received' => 10, 'qty_damaged' => 0]],
    ])->assertRedirect();

    // Duplicate receipt submission does nothing.
    post(route('admin.operations.transfers.receive', $transfer->id), [
        'idempotency_key' => $receiptKey,
        'receiver_name' => 'Branch Clerk',
        'lines' => [$item->id => ['qty_received' => 10, 'qty_damaged' => 0]],
    ]);

    expect($transfer->fresh()->status)->toBe(Transfer::STATUS_RECEIVED)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(12)
        ->and($this->held($this->product->id, $this->warehouse->id, StockHold::BUCKET_IN_TRANSIT))->toBe(0)
        ->and($request->fresh()->status)->toBe(StockRequest::STATUS_FULFILLED);
});

it('requires approval for inter-branch transfers and refuses more than the source can spare (acceptance 6)', function () {
    $this->actingAsOperator(STORE_MANAGER, [$this->branch->id]);

    post(route('admin.operations.transfers.store'), [
        'type' => Transfer::TYPE_INTER_BRANCH,
        'source_inventory_source_id' => $this->otherBranch->id,
        'destination_inventory_source_id' => $this->branch->id,
        'priority' => 'normal',
        'reason' => 'Out of stock before weekend',
        'items' => [['product_id' => $this->product->id, 'qty' => 25]],
    ])->assertRedirect();

    $transfer = Transfer::latest('id')->first();

    expect($transfer->approval_required)->toBeTrue();

    post(route('admin.operations.transfers.submit', $transfer->id))->assertSessionHas('error');

    $transfer->items()->update(['qty_requested' => 15]);

    post(route('admin.operations.transfers.submit', $transfer->id))->assertRedirect();

    expect($transfer->fresh()->status)->toBe(Transfer::STATUS_PENDING_APPROVAL);

    $this->actingAsOperator(['operations.transfers', 'operations.transfers.approve'], [$this->otherBranch->id]);

    post(route('admin.operations.transfers.approve', $transfer->id))->assertRedirect();

    expect($transfer->fresh()->status)->toBe(Transfer::STATUS_APPROVED)
        ->and($this->sellable($this->product->id, $this->otherBranch->id))->toBe(20);
});

it('records damaged and short units on receipt and raises a transit-loss adjustment (acceptance 8)', function () {
    $this->setOperationsConfig('transfers.general.require_approval', 0);

    $this->actingAsOperator(array_merge(STORE_MANAGER, WAREHOUSE_STAFF, ['operations.transfers.close']), [$this->branch->id, $this->otherBranch->id]);

    post(route('admin.operations.transfers.store'), [
        'type' => Transfer::TYPE_EMERGENCY,
        'source_inventory_source_id' => $this->otherBranch->id,
        'destination_inventory_source_id' => $this->branch->id,
        'priority' => 'urgent',
        'reason' => 'Emergency',
        'items' => [['product_id' => $this->product->id, 'qty' => 10]],
    ]);

    $transfer = Transfer::latest('id')->first();
    $item = $transfer->items()->first();

    post(route('admin.operations.transfers.submit', $transfer->id));
    post(route('admin.operations.transfers.pick', $transfer->id), ['picked' => [$item->id => 10]]);
    post(route('admin.operations.transfers.dispatch', $transfer->id), ['idempotency_key' => (string) Str::uuid()]);

    post(route('admin.operations.transfers.receive', $transfer->id), [
        'idempotency_key' => (string) Str::uuid(),
        'receiver_name' => 'Clerk',
        'lines' => [$item->id => ['qty_received' => 7, 'qty_damaged' => 1]],
    ])->assertRedirect();

    expect($transfer->fresh()->status)->toBe(Transfer::STATUS_PARTIALLY_RECEIVED)
        ->and($transfer->fresh()->has_discrepancy)->toBeTrue()
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(9)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_DAMAGED))->toBe(1)
        ->and($this->held($this->product->id, $this->otherBranch->id, StockHold::BUCKET_IN_TRANSIT))->toBe(2);

    // Receiving more than is still in transit is refused.
    post(route('admin.operations.transfers.receive', $transfer->id), [
        'idempotency_key' => (string) Str::uuid(),
        'receiver_name' => 'Clerk',
        'lines' => [$item->id => ['qty_received' => 3]],
    ])->assertSessionHas('error');

    post(route('admin.operations.transfers.close', $transfer->id), ['remarks' => 'Two cases missing on arrival'])->assertRedirect();

    $adjustment = Adjustment::latest('id')->first();

    expect($transfer->fresh()->status)->toBe(Transfer::STATUS_CLOSED_WITH_DISCREPANCY)
        ->and($adjustment->reason_code)->toBe('transit_loss')
        ->and($adjustment->status)->toBe(Adjustment::STATUS_SUBMITTED)
        ->and($adjustment->items->first()->qty)->toBe(2)
        // Still held in transit until the adjustment is approved and posted.
        ->and($this->held($this->product->id, $this->otherBranch->id, StockHold::BUCKET_IN_TRANSIT))->toBe(2);
});

it('lets a branch only see transfers that involve it', function () {
    $this->actingAsOperator(STORE_MANAGER, [$this->otherBranch->id]);

    post(route('admin.operations.transfers.store'), [
        'type' => Transfer::TYPE_INTER_BRANCH,
        'source_inventory_source_id' => $this->warehouse->id,
        'destination_inventory_source_id' => $this->otherBranch->id,
        'priority' => 'normal',
        'reason' => 'Need stock',
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
    ]);

    $transfer = Transfer::latest('id')->first();

    $this->actingAsOperator(STORE_MANAGER, [$this->branch->id]);

    get(route('admin.operations.transfers.view', $transfer->id))->assertForbidden();
});

it('renders the distribution screens', function () {
    $request = submittedBranchRequest($this, 5);

    $this->actingAsOperator(array_merge(STORE_MANAGER, WAREHOUSE_STAFF), [$this->warehouse->id, $this->branch->id]);

    post(route('admin.operations.stock_requests.allocate', $request->id), [
        'warehouse_id' => $this->warehouse->id,
        'allocations' => [$request->items->first()->id => ['qty' => 5]],
    ]);

    $transfer = Transfer::latest('id')->first();

    get(route('admin.operations.stock_requests.index'))->assertOk();
    get(route('admin.operations.stock_requests.create'))->assertOk();
    get(route('admin.operations.stock_requests.view', $request->id))->assertOk();
    get(route('admin.operations.transfers.index'))->assertOk();
    get(route('admin.operations.transfers.create'))->assertOk();
    get(route('admin.operations.transfers.view', $transfer->id))->assertOk();
    get(route('admin.operations.transfers.print', $transfer->id))->assertOk();
});
