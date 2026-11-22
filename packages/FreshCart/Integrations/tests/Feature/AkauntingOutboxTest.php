<?php

use FreshCart\Integrations\Models\IntegrationLink;
use FreshCart\Integrations\Models\OutboxMessage;
use FreshCart\Integrations\Services\OutboxProcessor;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\Supplier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\InvoiceItem;
use Webkul\Sales\Models\Order;

use function Pest\Laravel\artisan;
use function Pest\Laravel\post;

/**
 * Fake Akaunting that hands out increasing ids and records what it received.
 */
function fakeAkaunting(): void
{
    $id = 100;

    Http::fake([
        'akaunting.test/api/contacts?*' => Http::response(['data' => []]),
        'akaunting.test/api/*' => function () use (&$id) {
            return Http::response(['data' => ['id' => ++$id]], 201);
        },
    ]);
}

function akauntingRequests(string $method, string $path): array
{
    return Http::recorded(fn (Request $request) => $request->method() === $method
        && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/api/'.$path))
        ->map(fn ($pair) => $pair[0]->data())
        ->values()
        ->all();
}

function integrationIssuedOrder($test, int $qty, float $cost): PurchaseOrder
{
    $test->actingAsOperator(['operations.purchase_requests', 'operations.purchase_requests.create'], [$test->warehouse->id]);

    post(route('admin.operations.purchase_requests.store'), [
        'inventory_source_id' => $test->warehouse->id,
        'priority' => 'normal',
        'justification' => 'Weekly replenishment',
        'items' => [['product_id' => $test->product->id, 'qty' => $qty, 'estimated_unit_cost' => $cost]],
    ]);

    $request = PurchaseRequest::latest('id')->first();

    post(route('admin.operations.purchase_requests.submit', $request->id));

    $test->actingAsOperator(['operations.purchase_requests', 'operations.purchase_requests.approve'], [$test->warehouse->id]);

    post(route('admin.operations.purchase_requests.approve', $request->id), ['remarks' => 'OK']);

    $test->actingAsOperator(['operations.purchase_orders', 'operations.purchase_orders.create'], [$test->warehouse->id]);

    post(route('admin.operations.purchase_orders.store'), [
        'supplier_id' => $test->supplier->id,
        'inventory_source_id' => $test->warehouse->id,
        'lines' => [['selected' => 1, 'purchase_request_item_id' => $request->items->first()->id, 'qty' => $qty, 'unit_cost' => $cost]],
    ]);

    $order = PurchaseOrder::latest('id')->first();

    post(route('admin.operations.purchase_orders.issue', $order->id));

    return $order->fresh('items');
}

beforeEach(function () {
    $this->warehouse = $this->makeLocation(Location::TYPE_WAREHOUSE, true);
    $this->product = $this->makeProduct([$this->warehouse->id => 0]);
    $this->supplier = Supplier::create([
        'code' => 'SUP-'.Str::random(6),
        'name' => 'Mindoro Farms',
        'email' => 'orders@mindoro.test',
        'payment_terms' => 'Net 30',
        'status' => 1,
    ]);
});

it('queues suppliers and pushes them to Akaunting as vendors, updating on edit', function () {
    fakeAkaunting();

    expect(OutboxMessage::where(['action' => 'akaunting.vendor', 'subject_id' => $this->supplier->id])->value('status'))->toBe('pending');

    app(OutboxProcessor::class)->run();

    $vendorId = IntegrationLink::remoteId('akaunting', 'vendor', $this->supplier->id);

    expect($vendorId)->not->toBeNull()
        ->and(akauntingRequests('POST', 'contacts')[0])->toMatchArray(['type' => 'vendor', 'name' => 'Mindoro Farms', 'reference' => $this->supplier->code]);

    $this->supplier->update(['name' => 'Mindoro Farms Co-op']);

    app(OutboxProcessor::class)->run();

    expect(akauntingRequests('PUT', "contacts/{$vendorId}"))->toHaveCount(1)
        ->and(akauntingRequests('PUT', "contacts/{$vendorId}")[0]['name'])->toBe('Mindoro Farms Co-op');
});

it('posts an Akaunting bill for the accepted quantities of a goods receipt', function () {
    fakeAkaunting();

    $order = integrationIssuedOrder($this, 10, 95);

    $this->actingAsOperator(['operations.purchase_orders', 'operations.receiving', 'operations.receiving.create'], [$this->warehouse->id]);

    post(route('admin.operations.receiving.store', $order->id), [
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['purchase_order_item_id' => $order->items->first()->id, 'qty_received' => 6, 'qty_rejected' => 1]],
    ])->assertRedirect();

    $receipt = GoodsReceipt::latest('id')->first();

    expect(OutboxMessage::where(['action' => 'akaunting.purchase_bill', 'subject_id' => $receipt->id])->exists())->toBeTrue();

    app(OutboxProcessor::class)->run();

    $bill = akauntingRequests('POST', 'documents')[0];

    expect($bill)->toMatchArray([
        'type' => 'bill',
        'document_number' => $receipt->reference,
        'order_number' => $order->reference,
        'status' => 'received',
        'amount' => 475.0,
        'category_id' => 5,
        'contact_id' => (int) IntegrationLink::remoteId('akaunting', 'vendor', $this->supplier->id),
    ])
        ->and($bill['items'][0])->toMatchArray(['quantity' => 5, 'price' => 95.0])
        ->and(substr($bill['due_at'], 0, 10))->toBe($receipt->received_at->copy()->addDays(30)->toDateString());

    // A retry never creates a second bill.
    OutboxMessage::where('action', 'akaunting.purchase_bill')->update(['status' => 'pending']);
    app(OutboxProcessor::class)->run();

    expect(akauntingRequests('POST', 'documents'))->toHaveCount(1);
});

it('posts a paid Akaunting invoice for a Bagisto invoice', function () {
    fakeAkaunting();

    $order = Order::factory()->create([
        'customer_email' => 'juan@example.test',
        'customer_first_name' => 'Juan',
        'customer_last_name' => 'Dela Cruz',
        'base_currency_code' => 'PHP',
    ]);

    $invoice = Invoice::factory()->create([
        'order_id' => $order->id,
        'increment_id' => 'INV-77',
        'state' => 'paid',
        'base_currency_code' => 'PHP',
        'base_sub_total' => 200,
        'base_shipping_amount' => 50,
        'base_tax_amount' => 24,
        'base_discount_amount' => 20,
        'base_grand_total' => 254,
        'total_qty' => 2,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Carabao Mango 1kg',
        'sku' => 'MANGO-1KG',
        'qty' => 2,
        'price' => 100, 'base_price' => 100,
        'total' => 200, 'base_total' => 200,
        'discount_amount' => 20, 'base_discount_amount' => 20,
    ]);

    Event::dispatch('sales.invoice.save.after', $invoice);

    app(OutboxProcessor::class)->run();

    $document = akauntingRequests('POST', 'documents')[0];
    $documentId = IntegrationLink::remoteId('akaunting', 'sales_invoice', $invoice->id);

    expect(collect(akauntingRequests('POST', 'contacts'))->firstWhere('type', 'customer'))->toMatchArray(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.test'])
        ->and($document)->toMatchArray([
            'type' => 'invoice',
            'document_number' => 'FC-INV-INV-77',
            'amount' => 254.0,
            'currency_code' => 'PHP',
            'category_id' => 3,
        ])
        ->and(collect($document['items'])->pluck('price', 'name')->all())
        ->toBe(['Carabao Mango 1kg' => 90.0, 'Shipping' => 50.0, 'Tax' => 24.0])
        ->and(akauntingRequests('POST', "documents/{$documentId}/transactions")[0])
        ->toMatchArray(['type' => 'income', 'amount' => 254.0, 'document_id' => (int) $documentId, 'account_id' => 1]);
});

it('keeps failures in the outbox with backoff instead of breaking the business transaction', function () {
    Http::fake(['akaunting.test/*' => Http::response(['message' => 'Server error'], 500)]);

    app(OutboxProcessor::class)->run();

    $message = OutboxMessage::where('action', 'akaunting.vendor')->first();

    expect($message->status)->toBe('failed')
        ->and($message->attempts)->toBe(1)
        ->and($message->last_error)->toContain('HTTP 500')
        ->and($message->available_at->isFuture())->toBeTrue();

    artisan('fc:integrations:retry')->assertSuccessful();

    expect($message->fresh()->status)->toBe('pending');
});

it('leaves messages queued while Akaunting is not configured', function () {
    config(['freshcart-integrations.akaunting.url' => '']);
    Http::fake();

    app(OutboxProcessor::class)->run();

    Http::assertNothingSent();

    expect(OutboxMessage::where('action', 'akaunting.vendor')->value('status'))->toBe('pending');
});
