<?php

use FreshCart\Operations\Models\PriceChangeBatch;
use FreshCart\Operations\Models\PriceHistory;
use FreshCart\Operations\Models\PromotionProposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

const MARKETING = ['operations.promotions', 'operations.promotions.create', 'operations.price_changes', 'operations.price_changes.create'];

const PRICING_APPROVER = ['operations.promotions', 'operations.promotions.approve', 'operations.price_changes', 'operations.price_changes.approve'];

beforeEach(function () {
    $this->setOperationsConfig('promotions.general.max_discount_percent', 40);
    $this->setOperationsConfig('approvals.thresholds.promotions', 0);
    $this->setOperationsConfig('approvals.thresholds.price_changes', 0);

    $this->branch = $this->makeLocation();
    $this->product = $this->makeProduct([$this->branch->id => 10]);
});

function proposePromotion($test, array $overrides = []): PromotionProposal
{
    $test->actingAsOperator(MARKETING);

    post(route('admin.operations.promotions.store'), $overrides + [
        'name' => 'Weekend Dairy Sale',
        'department' => 'Marketing',
        'discount_type' => 'by_percent',
        'discount_amount' => 15,
        'starts_on' => today()->toDateString(),
        'ends_on' => today()->addDays(3)->toDateString(),
        'items' => [['product_id' => $test->product->id]],
    ])->assertRedirect();

    $proposal = PromotionProposal::latest('id')->first();

    post(route('admin.operations.promotions.submit', $proposal->id))->assertRedirect();

    return $proposal->fresh();
}

it('validates discount limits and date ranges', function () {
    $this->actingAsOperator(MARKETING);

    post(route('admin.operations.promotions.store'), [
        'name' => 'Too deep',
        'department' => 'Marketing',
        'discount_type' => 'by_percent',
        'discount_amount' => 60,
        'starts_on' => today()->toDateString(),
        'ends_on' => today()->addDay()->toDateString(),
        'items' => [['product_id' => $this->product->id]],
    ])->assertSessionHas('error');

    post(route('admin.operations.promotions.store'), [
        'name' => 'Backwards',
        'department' => 'Marketing',
        'discount_type' => 'by_percent',
        'discount_amount' => 10,
        'starts_on' => today()->addDays(3)->toDateString(),
        'ends_on' => today()->toDateString(),
        'items' => [['product_id' => $this->product->id]],
    ])->assertSessionHasErrors('ends_on');

    expect(PromotionProposal::count())->toBe(0);
});

it('creates no catalog rule until the promotion is approved (acceptance 11)', function () {
    $proposal = proposePromotion($this);

    expect($proposal->status)->toBe(PromotionProposal::STATUS_SUBMITTED)
        ->and($proposal->catalog_rule_id)->toBeNull();

    $this->actingAsOperator(PRICING_APPROVER);

    post(route('admin.operations.promotions.approve', $proposal->id))->assertRedirect();

    $proposal->refresh();

    expect($proposal->status)->toBe(PromotionProposal::STATUS_ACTIVE)
        ->and($proposal->catalogRule)->not->toBeNull()
        ->and((bool) $proposal->catalogRule->status)->toBeTrue()
        ->and($proposal->catalogRule->starts_from)->toBe(today()->toDateString())
        ->and($proposal->catalogRule->action_type)->toBe('by_percent');
});

it('schedules future promotions and activates and expires them by date (acceptance 11)', function () {
    $proposal = proposePromotion($this, [
        'starts_on' => today()->addDays(2)->toDateString(),
        'ends_on' => today()->addDays(5)->toDateString(),
    ]);

    $this->actingAsOperator(PRICING_APPROVER);
    post(route('admin.operations.promotions.approve', $proposal->id));

    expect($proposal->fresh()->status)->toBe(PromotionProposal::STATUS_SCHEDULED);

    Artisan::call('fc:monitor', ['check' => 'promotions']);

    expect($proposal->fresh()->status)->toBe(PromotionProposal::STATUS_SCHEDULED);

    $this->travelTo(today()->addDays(2)->setTime(9, 0));
    Artisan::call('fc:monitor', ['check' => 'promotions']);

    expect($proposal->fresh()->status)->toBe(PromotionProposal::STATUS_ACTIVE);

    $this->travelTo(today()->addDays(4)->setTime(9, 0));
    Artisan::call('fc:monitor', ['check' => 'promotions']);

    $proposal->refresh();

    expect($proposal->status)->toBe(PromotionProposal::STATUS_EXPIRED)
        ->and((bool) $proposal->catalogRule->status)->toBeFalse();
});

it('does not let marketing approve their own promotion', function () {
    $this->actingAsOperator(array_merge(MARKETING, PRICING_APPROVER));

    post(route('admin.operations.promotions.store'), [
        'name' => 'Self approved',
        'department' => 'Marketing',
        'discount_type' => 'by_fixed',
        'discount_amount' => 5,
        'starts_on' => today()->toDateString(),
        'ends_on' => today()->addDay()->toDateString(),
        'items' => [['product_id' => $this->product->id]],
    ]);

    $proposal = PromotionProposal::latest('id')->first();

    post(route('admin.operations.promotions.submit', $proposal->id));
    post(route('admin.operations.promotions.approve', $proposal->id))->assertSessionHas('error');

    expect($proposal->fresh()->catalog_rule_id)->toBeNull();
});

it('validates bulk price files and reports every bad row', function () {
    $this->actingAsOperator(MARKETING);

    $csv = "sku,new_price\n{$this->product->sku},abc\nNO-SUCH-SKU,10\n{$this->product->sku},12\n";

    post(route('admin.operations.price_changes.store'), [
        'name' => 'Bad file',
        'effective_at' => now()->format('Y-m-d H:i'),
        'file' => UploadedFile::fake()->createWithContent('prices.csv', $csv),
    ])->assertSessionHas('import_errors', fn ($errors) => count($errors) === 3); // bad price, unknown SKU, duplicate SKU

    expect(PriceChangeBatch::count())->toBe(0);
});

it('applies approved price changes and keeps price history (acceptance 11)', function () {
    $this->actingAsOperator(MARKETING);

    post(route('admin.operations.price_changes.store'), [
        'name' => 'Rice price update',
        'effective_at' => now()->format('Y-m-d H:i'),
        'file' => UploadedFile::fake()->createWithContent('prices.csv', "sku,new_price\n{$this->product->sku},123.45\n"),
    ])->assertRedirect();

    $batch = PriceChangeBatch::latest('id')->first();

    post(route('admin.operations.price_changes.submit', $batch->id));

    $priceAttributeId = DB::table('attributes')->where('code', 'price')->value('id');
    $price = fn () => (float) DB::table('product_attribute_values')->where('product_id', $this->product->id)->where('attribute_id', $priceAttributeId)->value('float_value');

    expect($price())->not->toBe(123.45);

    $this->actingAsOperator(PRICING_APPROVER);
    post(route('admin.operations.price_changes.approve', $batch->id))->assertRedirect();

    expect($batch->fresh()->status)->toBe(PriceChangeBatch::STATUS_APPLIED)
        ->and($price())->toBe(123.45)
        ->and(PriceHistory::where('product_id', $this->product->id)->where('new_price', 123.45)->exists())->toBeTrue();
});

it('renders the pricing screens', function () {
    $proposal = proposePromotion($this);

    get(route('admin.operations.promotions.index'))->assertOk();
    get(route('admin.operations.promotions.create'))->assertOk();
    get(route('admin.operations.promotions.view', $proposal->id))->assertOk();
    get(route('admin.operations.price_changes.index'))->assertOk();
    get(route('admin.operations.price_changes.create'))->assertOk();
});
