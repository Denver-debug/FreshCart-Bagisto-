<?php

use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Models\SupplierReturn;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

const PURCHASER = [
    'operations.purchase_requests',
    'operations.purchase_requests.create',
    'operations.purchase_orders',
    'operations.purchase_orders.create',
];

const MANAGER = [
    'operations.purchase_requests',
    'operations.purchase_requests.approve',
];

const RECEIVER = [
    'operations.purchase_orders',
    'operations.receiving',
    'operations.receiving.create',
];

beforeEach(function () {
    $this->warehouse = $this->makeLocation(Location::TYPE_WAREHOUSE, true);
    $this->product = $this->makeProduct([$this->warehouse->id => 0]);
    $this->supplier = Supplier::create(['code' => 'SUP-'.Str::random(6), 'name' => 'Mindoro Farms', 'status' => 1]);

    $this->setOperationsConfig('approvals.thresholds.purchase_requests', 50000);
});

function createDraftRequest($test, int $qty = 10, float $cost = 100): PurchaseRequest
{
    post(route('admin.operations.purchase_requests.store'), [
        'inventory_source_id' => $test->warehouse->id,
        'priority' => 'normal',
        'justification' => 'Weekly replenishment',
        'items' => [['product_id' => $test->product->id, 'qty' => $qty, 'estimated_unit_cost' => $cost]],
    ])->assertRedirect();

    return PurchaseRequest::latest('id')->first();
}

function approvedRequest($test, int $qty = 10): PurchaseRequest
{
    $test->actingAsOperator(PURCHASER, [$test->warehouse->id]);

    $request = createDraftRequest($test, $qty);

    post(route('admin.operations.purchase_requests.submit', $request->id));

    $test->actingAsOperator(MANAGER, [$test->warehouse->id]);

    post(route('admin.operations.purchase_requests.approve', $request->id), ['remarks' => 'OK']);

    return $request->fresh();
}

function issuedOrder($test, int $qty = 10): PurchaseOrder
{
    $request = approvedRequest($test, $qty);

    $test->actingAsOperator(PURCHASER, [$test->warehouse->id]);

    post(route('admin.operations.purchase_orders.store'), [
        'supplier_id' => $test->supplier->id,
        'inventory_source_id' => $test->warehouse->id,
        'lines' => [[
            'selected' => 1,
            'purchase_request_item_id' => $request->items->first()->id,
            'qty' => $qty,
            'unit_cost' => 95,
        ]],
    ])->assertRedirect();

    $order = PurchaseOrder::latest('id')->first();

    post(route('admin.operations.purchase_orders.issue', $order->id));

    return $order->fresh('items');
}

it('blocks users without operations permissions from procurement screens (acceptance 1)', function () {
    $this->actingAsOperator(['operations.stock']);

    get(route('admin.operations.purchase_requests.index'))->assertUnauthorized();
    post(route('admin.operations.purchase_requests.store'), [])->assertUnauthorized();
    get(route('admin.operations.receiving.index'))->assertUnauthorized();
});

it('runs a purchase request through submission and approval with history (acceptance 2)', function () {
    $requester = $this->actingAsOperator(PURCHASER, [$this->warehouse->id]);

    $request = createDraftRequest($this);

    expect($request->status)->toBe(PurchaseRequest::STATUS_DRAFT)
        ->and((float) $request->estimated_total)->toBe(1000.0);

    post(route('admin.operations.purchase_requests.submit', $request->id))->assertRedirect();

    expect($request->fresh()->status)->toBe(PurchaseRequest::STATUS_SUBMITTED);

    // The requester holds no approve permission: the route itself is refused.
    post(route('admin.operations.purchase_requests.approve', $request->id))->assertUnauthorized();

    $approver = $this->actingAsOperator(MANAGER, [$this->warehouse->id]);

    post(route('admin.operations.purchase_requests.approve', $request->id), ['remarks' => 'Within budget'])->assertRedirect();

    $request->refresh();

    expect($request->status)->toBe(PurchaseRequest::STATUS_APPROVED)
        ->and($request->approvals()->pluck('action')->all())->toBe(['created', 'submitted', 'approved'])
        ->and($request->approvals()->where('action', 'approved')->value('admin_id'))->toBe($approver->id);

    $this->assertDatabaseHas('fc_notifications', ['admin_id' => $requester->id, 'type' => 'approval_granted']);
});

it('prevents a requester from approving their own request even with approval rights', function () {
    $this->actingAsOperator(array_merge(PURCHASER, MANAGER), [$this->warehouse->id]);

    $request = createDraftRequest($this);

    post(route('admin.operations.purchase_requests.submit', $request->id));
    post(route('admin.operations.purchase_requests.approve', $request->id))->assertSessionHas('error');

    expect($request->fresh()->status)->toBe(PurchaseRequest::STATUS_SUBMITTED);
});

it('requires a second management approval above the configured threshold', function () {
    $this->actingAsOperator(PURCHASER, [$this->warehouse->id]);

    $request = createDraftRequest($this, 1000, 60); // 60,000 >= 50,000 threshold

    post(route('admin.operations.purchase_requests.submit', $request->id));

    expect($request->fresh()->approval_level_required)->toBe(2);

    $this->actingAsOperator(MANAGER, [$this->warehouse->id]);
    post(route('admin.operations.purchase_requests.approve', $request->id));

    expect($request->fresh()->status)->toBe(PurchaseRequest::STATUS_SUBMITTED);

    // A level-1 approver cannot provide level 2.
    $this->actingAsOperator(MANAGER, [$this->warehouse->id]);
    post(route('admin.operations.purchase_requests.approve', $request->id))->assertSessionHas('error');

    $this->actingAsOperator(array_merge(MANAGER, ['operations.approvals.level2']), [$this->warehouse->id]);
    post(route('admin.operations.purchase_requests.approve', $request->id));

    expect($request->fresh()->status)->toBe(PurchaseRequest::STATUS_APPROVED);
});

it('does not create purchase orders from unapproved requests (acceptance 3)', function () {
    $this->actingAsOperator(PURCHASER, [$this->warehouse->id]);

    $request = createDraftRequest($this);

    post(route('admin.operations.purchase_requests.submit', $request->id));

    post(route('admin.operations.purchase_orders.store'), [
        'supplier_id' => $this->supplier->id,
        'inventory_source_id' => $this->warehouse->id,
        'lines' => [['selected' => 1, 'purchase_request_item_id' => $request->items->first()->id, 'qty' => 10, 'unit_cost' => 100]],
    ])->assertSessionHas('error');

    expect(PurchaseOrder::count())->toBe(0);
});

it('converts an approved request into a purchase order once', function () {
    $order = issuedOrder($this, 10);

    $request = PurchaseRequest::latest('id')->first();

    expect($order->status)->toBe(PurchaseOrder::STATUS_ISSUED)
        ->and($request->status)->toBe(PurchaseRequest::STATUS_CONVERTED);

    post(route('admin.operations.purchase_orders.store'), [
        'supplier_id' => $this->supplier->id,
        'inventory_source_id' => $this->warehouse->id,
        'lines' => [['selected' => 1, 'purchase_request_item_id' => $request->items->first()->id, 'qty' => 1, 'unit_cost' => 100]],
    ])->assertSessionHas('error');

    expect(PurchaseOrder::count())->toBe(1);
});

it('updates stock for partial receipts and tracks outstanding quantities (acceptance 4)', function () {
    $order = issuedOrder($this, 10);

    $this->actingAsOperator(RECEIVER, [$this->warehouse->id]);

    post(route('admin.operations.receiving.store', $order->id), [
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [[
            'purchase_order_item_id' => $order->items->first()->id,
            'qty_received' => 6,
            'qty_rejected' => 1,
            'batch_no' => 'B-001',
            'expiry_date' => now()->addMonths(3)->toDateString(),
        ]],
    ])->assertRedirect();

    expect($this->sellable($this->product->id, $this->warehouse->id))->toBe(5)
        ->and($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and($order->items()->first()->outstandingQty())->toBe(5)
        ->and(GoodsReceipt::first()->has_discrepancy)->toBeTrue()
        ->and(SupplierReturn::count())->toBe(1);

    post(route('admin.operations.receiving.store', $order->id), [
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['purchase_order_item_id' => $order->items->first()->id, 'qty_received' => 5]],
    ])->assertRedirect();

    expect($this->sellable($this->product->id, $this->warehouse->id))->toBe(10)
        ->and($order->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED);
});

it('ignores a duplicate receipt submission (acceptance 5)', function () {
    $order = issuedOrder($this, 10);

    $this->actingAsOperator(RECEIVER, [$this->warehouse->id]);

    $payload = [
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['purchase_order_item_id' => $order->items->first()->id, 'qty_received' => 4]],
    ];

    post(route('admin.operations.receiving.store', $order->id), $payload)->assertRedirect();
    post(route('admin.operations.receiving.store', $order->id), $payload)->assertRedirect();

    expect($this->sellable($this->product->id, $this->warehouse->id))->toBe(4)
        ->and(GoodsReceipt::count())->toBe(1)
        ->and($order->items()->first()->qty_received)->toBe(4);
});

it('blocks over-receipt unless an authorized user records a reason', function () {
    $order = issuedOrder($this, 10);

    $line = ['purchase_order_item_id' => $order->items->first()->id, 'qty_received' => 12];

    $this->actingAsOperator(RECEIVER, [$this->warehouse->id]);

    post(route('admin.operations.receiving.store', $order->id), [
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [$line + ['over_receipt_reason' => 'Supplier bonus stock']],
    ])->assertSessionHas('error');

    expect($this->sellable($this->product->id, $this->warehouse->id))->toBe(0);

    $this->actingAsOperator(array_merge(RECEIVER, ['operations.receiving.override']), [$this->warehouse->id]);

    post(route('admin.operations.receiving.store', $order->id), [
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [$line + ['over_receipt_reason' => 'Supplier bonus stock']],
    ])->assertRedirect();

    expect($this->sellable($this->product->id, $this->warehouse->id))->toBe(12);
});

it('hides other branches documents from branch-scoped users', function () {
    $order = issuedOrder($this, 5);

    $otherBranch = $this->makeLocation();

    $this->actingAsOperator(RECEIVER, [$otherBranch->id]);

    get(route('admin.operations.purchase_orders.view', $order->id))->assertForbidden();
    get(route('admin.operations.receiving.create', $order->id))->assertForbidden();
});

it('renders the procurement screens', function () {
    $order = issuedOrder($this, 5);

    $this->actingAsOperator(array_merge(PURCHASER, RECEIVER, MANAGER), [$this->warehouse->id]);

    get(route('admin.operations.purchase_requests.index'))->assertOk();
    get(route('admin.operations.purchase_requests.create'))->assertOk();
    get(route('admin.operations.purchase_requests.view', PurchaseRequest::first()->id))->assertOk();
    get(route('admin.operations.purchase_orders.view', $order->id))->assertOk();
    get(route('admin.operations.purchase_orders.create'))->assertOk();
    get(route('admin.operations.receiving.index'))->assertOk();
    get(route('admin.operations.receiving.create', $order->id))->assertOk();
    get(route('admin.operations.purchase_orders.print', $order->id))->assertOk();
});
