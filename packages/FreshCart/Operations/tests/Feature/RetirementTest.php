<?php

use FreshCart\Operations\Models\DisposalRequest;
use FreshCart\Operations\Models\RetirementCandidate;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Services\StockLedgerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

const NOMINATOR = ['operations.disposals', 'operations.disposals.create'];

const DISPOSAL_INSPECTOR = ['operations.disposals', 'operations.disposals.inspect'];

const FINANCE = ['operations.disposals', 'operations.disposals.value'];

const DISPOSAL_APPROVER = ['operations.disposals', 'operations.disposals.approve'];

const DISPOSAL_EXECUTOR = ['operations.disposals', 'operations.disposals.execute'];

beforeEach(function () {
    Storage::fake('local');

    $this->setOperationsConfig('retirement.criteria.expiry_warning_days', 7);
    $this->setOperationsConfig('approvals.thresholds.disposals', 0);

    $this->branch = $this->makeLocation();
    $this->product = $this->makeProduct([$this->branch->id => 0]);

    app(StockLedgerService::class)->receive($this->product->id, $this->branch->id, 6, [
        'batch_no' => 'MILK-01',
        'expiry_date' => now()->addDays(3)->toDateString(),
        'unit_cost' => 50,
    ]);
});

function nominatedDisposal($test): DisposalRequest
{
    $test->actingAsOperator(NOMINATOR, [$test->branch->id]);

    post(route('admin.operations.candidates.refresh'))->assertRedirect();

    $candidate = RetirementCandidate::where('product_id', $test->product->id)->where('criterion', 'near_expiry')->firstOrFail();

    post(route('admin.operations.candidates.nominate'), [
        'candidate_ids' => [$candidate->id],
        'reason' => 'Expires in 3 days',
    ])->assertRedirect();

    $disposal = DisposalRequest::latest('id')->first();

    post(route('admin.operations.disposals.submit', $disposal->id))->assertRedirect();

    return $disposal->fresh();
}

it('lists near-expiry batches for review without touching stock', function () {
    $this->actingAsOperator(NOMINATOR, [$this->branch->id]);

    post(route('admin.operations.candidates.refresh'))->assertRedirect();

    $candidate = RetirementCandidate::where('product_id', $this->product->id)->first();

    expect($candidate->criterion)->toBe('near_expiry')
        ->and($candidate->qty)->toBe(6)
        ->and((float) $candidate->estimated_value)->toBe(300.0)
        ->and($this->sellable($this->product->id, $this->branch->id))->toBe(6);

    get(route('admin.operations.candidates.index'))->assertOk()->assertSee('MILK-01');
});

it('cannot dispose without inspection, valuation and approval (acceptance 12)', function () {
    $disposal = nominatedDisposal($this);

    // Skipping steps is refused: an executor cannot segregate or complete.
    $this->actingAsOperator(DISPOSAL_EXECUTOR, [$this->branch->id]);

    post(route('admin.operations.disposals.segregate', $disposal->id))->assertSessionHas('error');
    post(route('admin.operations.disposals.complete', $disposal->id), [
        'disposal_method' => 'Landfill',
        'evidence' => [UploadedFile::fake()->image('proof.jpg')],
    ])->assertSessionHas('error');

    // Finance cannot value before inspection.
    $this->actingAsOperator(FINANCE, [$this->branch->id]);
    post(route('admin.operations.disposals.value', $disposal->id))->assertSessionHas('error');

    expect($this->sellable($this->product->id, $this->branch->id))->toBe(6);

    $this->actingAsOperator(DISPOSAL_INSPECTOR, [$this->branch->id]);
    post(route('admin.operations.disposals.inspect', $disposal->id), ['inspection_remarks' => 'Bloated packs'])->assertRedirect();

    $this->actingAsOperator(FINANCE, [$this->branch->id]);
    post(route('admin.operations.disposals.value', $disposal->id), ['unit_costs' => [$disposal->items->first()->id => 55]])->assertRedirect();

    expect($disposal->fresh()->status)->toBe(DisposalRequest::STATUS_VALUED)
        ->and((float) $disposal->fresh()->total_value)->toBe(330.0);

    $this->actingAsOperator(DISPOSAL_APPROVER, [$this->branch->id]);
    post(route('admin.operations.disposals.approve', $disposal->id))->assertRedirect();

    $this->actingAsOperator(DISPOSAL_EXECUTOR, [$this->branch->id]);
    post(route('admin.operations.disposals.segregate', $disposal->id))->assertRedirect();

    expect($this->sellable($this->product->id, $this->branch->id))->toBe(0)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_QUARANTINE))->toBe(6);

    // Evidence is mandatory.
    post(route('admin.operations.disposals.complete', $disposal->id), ['disposal_method' => 'Landfill'])->assertSessionHasErrors('evidence');

    post(route('admin.operations.disposals.complete', $disposal->id), [
        'disposal_method' => 'Landfill via accredited hauler',
        'evidence' => [UploadedFile::fake()->image('proof.jpg')],
    ])->assertRedirect();

    $disposal->refresh();

    expect($disposal->status)->toBe(DisposalRequest::STATUS_DISPOSED)
        ->and($this->held($this->product->id, $this->branch->id, StockHold::BUCKET_QUARANTINE))->toBe(0)
        ->and($disposal->attachments()->count())->toBe(1)
        // History is retained (acceptance 13).
        ->and((int) StockMovement::where('reference_type', $disposal->getMorphClass())->where('reference_id', $disposal->id)->where('movement_type', 'disposal')->sum('qty'))->toBe(6)
        ->and(RetirementCandidate::where('disposal_request_id', $disposal->id)->value('status'))->toBe(RetirementCandidate::STATUS_RESOLVED);

    get(route('admin.operations.disposals.print', $disposal->id))->assertOk();
});

it('prevents the nominator from approving their own disposal', function () {
    $this->actingAsOperator(array_merge(NOMINATOR, DISPOSAL_INSPECTOR, FINANCE, DISPOSAL_APPROVER), [$this->branch->id]);

    post(route('admin.operations.candidates.refresh'));

    $candidate = RetirementCandidate::where('product_id', $this->product->id)->first();

    post(route('admin.operations.candidates.nominate'), ['candidate_ids' => [$candidate->id], 'reason' => 'x']);

    $disposal = DisposalRequest::latest('id')->first();

    post(route('admin.operations.disposals.submit', $disposal->id));
    post(route('admin.operations.disposals.inspect', $disposal->id), ['inspection_remarks' => 'ok']);
    post(route('admin.operations.disposals.value', $disposal->id));
    post(route('admin.operations.disposals.approve', $disposal->id))->assertSessionHas('error');

    expect($disposal->fresh()->status)->toBe(DisposalRequest::STATUS_VALUED);
});

it('renders the disposal screens', function () {
    $disposal = nominatedDisposal($this);

    get(route('admin.operations.disposals.index'))->assertOk();
    get(route('admin.operations.disposals.create'))->assertOk();
    get(route('admin.operations.disposals.view', $disposal->id))->assertOk();
});
