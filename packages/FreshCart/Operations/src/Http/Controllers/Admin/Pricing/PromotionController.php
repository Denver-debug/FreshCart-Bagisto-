<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Pricing;

use FreshCart\Operations\DataGrids\Pricing\PromotionDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\PromotionProposal;
use FreshCart\Operations\Services\PricingService;
use FreshCart\Operations\Services\ReportService;
use FreshCart\Operations\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(protected PricingService $pricing) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PromotionDataGrid::class)->process();
        }

        return view('operations::admin.promotions.index');
    }

    public function create(): View
    {
        return $this->form(new PromotionProposal([
            'discount_type' => PromotionProposal::DISCOUNT_PERCENT,
            'starts_on' => today(),
            'ends_on' => today()->addDays(7),
        ]));
    }

    public function store()
    {
        return $this->attempt(
            fn () => $this->pricing->savePromotion($this->validated()),
            trans('operations::app.promotions.saved'),
            fn ($proposal) => redirect()->route('admin.operations.promotions.view', $proposal->id),
        );
    }

    public function edit(int $id): View
    {
        $proposal = PromotionProposal::findOrFail($id);

        abort_unless($proposal->status === PromotionProposal::STATUS_DRAFT && $proposal->requested_by === $this->adminId(), 403);

        return $this->form($proposal);
    }

    public function update(int $id)
    {
        $proposal = PromotionProposal::findOrFail($id);

        abort_unless($proposal->requested_by === $this->adminId(), 403);

        return $this->attempt(
            fn () => $this->pricing->savePromotion($this->validated(), $proposal),
            trans('operations::app.promotions.saved'),
            fn ($proposal) => redirect()->route('admin.operations.promotions.view', $proposal->id),
        );
    }

    public function view(int $id): View
    {
        $proposal = PromotionProposal::with(['catalogRule', 'requester'])->findOrFail($id);

        return view('operations::admin.promotions.view', [
            'proposal' => $proposal,
            'products' => app(ProductCatalog::class)->findMany($proposal->product_ids ?? []),
            'categories' => $this->categoryOptions()->only($proposal->category_ids ?? []),
            'performance' => in_array($proposal->status, [PromotionProposal::STATUS_ACTIVE, PromotionProposal::STATUS_EXPIRED])
                ? app(ReportService::class)->promotionPerformance($proposal)
                : null,
        ]);
    }

    public function submit(int $id)
    {
        $proposal = PromotionProposal::findOrFail($id);

        abort_unless($proposal->requested_by === $this->adminId(), 403);

        return $this->attempt(fn () => $this->pricing->submitPromotion($proposal), trans('operations::app.promotions.submitted'));
    }

    public function approve(int $id)
    {
        $proposal = PromotionProposal::findOrFail($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->pricing->approvePromotion($proposal, $remarks), trans('operations::app.common.approved-message'));
    }

    public function reject(int $id)
    {
        $proposal = PromotionProposal::findOrFail($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(fn () => $this->pricing->rejectPromotion($proposal, $remarks), trans('operations::app.common.rejected-message'));
    }

    public function cancel(int $id)
    {
        $proposal = PromotionProposal::findOrFail($id);

        abort_unless($proposal->requested_by === $this->adminId() || bouncer()->hasPermission('operations.promotions.approve'), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->pricing->cancelPromotion($proposal, $remarks), trans('operations::app.common.cancelled-message'));
    }

    protected function form(PromotionProposal $proposal): View
    {
        $products = app(ProductCatalog::class)->findMany($proposal->product_ids ?? [])->map(fn ($product) => [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'qty' => 1,
        ])->values()->all();

        return view('operations::admin.promotions.form', [
            'proposal' => $proposal,
            'products' => $products,
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    protected function categoryOptions()
    {
        return DB::table('categories')
            ->join('category_translations', 'category_translations.category_id', '=', 'categories.id')
            ->where('category_translations.locale', app()->getLocale())
            ->whereNotNull('categories.parent_id')
            ->orderBy('categories._lft')
            ->pluck('category_translations.name', 'categories.id');
    }

    protected function validated(): array
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'department' => 'required|string|max:100',
            'discount_type' => ['required', Rule::in([PromotionProposal::DISCOUNT_PERCENT, PromotionProposal::DISCOUNT_FIXED])],
            'discount_amount' => 'required|numeric|gt:0|max:100000000',
            'starts_on' => 'required|date|after_or_equal:today',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'items' => 'nullable|array|max:500',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);

        if ($validated['discount_type'] === PromotionProposal::DISCOUNT_PERCENT) {
            request()->validate(['discount_amount' => 'max:100']);
        }

        $validated['product_ids'] = collect($validated['items'] ?? [])->pluck('product_id')->unique()->values()->all();

        return $validated;
    }
}
