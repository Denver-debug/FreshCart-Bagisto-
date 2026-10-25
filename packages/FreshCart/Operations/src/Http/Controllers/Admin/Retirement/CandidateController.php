<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Retirement;

use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\RetirementCandidate;
use FreshCart\Operations\Services\RetirementService;
use FreshCart\Operations\Support\Settings;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Review list of obsolete, expiring and slow-moving stock (P10). Nothing here
 * changes stock; nominating only drafts a disposal request.
 */
class CandidateController extends Controller
{
    public function __construct(protected RetirementService $retirement) {}

    public function index(): View
    {
        $filters = request()->validate([
            'criterion' => ['nullable', Rule::in(RetirementCandidate::CRITERIA)],
            'location_id' => 'nullable|integer',
        ]);

        $candidates = RetirementCandidate::with(['product', 'location', 'lot'])
            ->where('status', RetirementCandidate::STATUS_OPEN)
            ->forLocations($this->locations()->ids())
            ->when($filters['criterion'] ?? null, fn ($query, $criterion) => $query->where('criterion', $criterion))
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('inventory_source_id', $id))
            ->orderByRaw("FIELD(criterion, 'expired', 'near_expiry', 'held_stock', 'slow_moving')")
            ->orderByDesc('estimated_value')
            ->paginate(50)
            ->withQueryString();

        $settings = app(Settings::class);

        return view('operations::admin.disposals.candidates', [
            'candidates' => $candidates,
            'filters' => $filters,
            'locationOptions' => $this->locations()->options(),
            'criteria' => [
                'expiry_days' => $settings->expiryWarningDays(),
                'slow_days' => $settings->slowMovingDays(),
                'slow_units' => $settings->slowMovingMaxUnits(),
            ],
        ]);
    }

    public function refresh()
    {
        $count = $this->retirement->detectCandidates();

        session()->flash('success', trans('operations::app.disposals.candidates-refreshed', ['count' => $count]));

        return redirect()->route('admin.operations.candidates.index');
    }

    public function nominate()
    {
        $validated = request()->validate([
            'candidate_ids' => 'required|array|min:1',
            'candidate_ids.*' => 'integer|exists:fc_retirement_candidates,id',
            'reason' => 'required|string|max:2000',
        ]);

        $locationIds = RetirementCandidate::whereIn('id', $validated['candidate_ids'])->pluck('inventory_source_id')->unique();

        foreach ($locationIds as $locationId) {
            $this->locations()->authorize((int) $locationId);
        }

        return $this->attempt(
            fn () => $this->retirement->nominate($validated['candidate_ids'], $validated['reason']),
            trans('operations::app.disposals.nominated'),
            fn ($request) => redirect()->route('admin.operations.disposals.view', $request->id),
        );
    }

    public function dismiss(int $id)
    {
        $candidate = RetirementCandidate::findOrFail($id);

        $this->locations()->authorize($candidate->inventory_source_id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:1000'])['remarks'] ?? null;

        $this->retirement->dismiss($candidate, $remarks);

        session()->flash('success', trans('operations::app.disposals.dismissed'));

        return back();
    }
}
