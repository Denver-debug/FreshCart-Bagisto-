<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\PriceChangeBatch;
use FreshCart\Operations\Models\PriceChangeItem;
use FreshCart\Operations\Models\PriceHistory;
use FreshCart\Operations\Models\PromotionProposal;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\CatalogRule\Repositories\CatalogRuleRepository;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Module E — promotion proposals and bulk price changes (P7).
 *
 * Approved promotions become native Bagisto catalog rules (so storefront, cart and
 * price indexing behave exactly as for manually created rules). Bulk price changes
 * write the product `price` attribute and fire Bagisto's product update event,
 * which refreshes the flat table and price indices.
 */
class PricingService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected NotificationService $notifications,
        protected ProductCatalog $productCatalog,
        protected Settings $settings,
        protected CatalogRuleRepository $catalogRuleRepository,
        protected ProductRepository $productRepository,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Promotions
    |--------------------------------------------------------------------------
    */

    public function savePromotion(array $data, ?PromotionProposal $proposal = null): PromotionProposal
    {
        $this->validatePromotion($data);

        return DB::transaction(function () use ($data, $proposal) {
            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'department' => $data['department'] ?? null,
                'discount_type' => $data['discount_type'],
                'discount_amount' => $data['discount_amount'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'product_ids' => array_values(array_map('intval', $data['product_ids'] ?? [])),
                'category_ids' => array_values(array_map('intval', $data['category_ids'] ?? [])),
            ];

            if ($proposal) {
                $proposal = $this->approvals->lock($proposal);

                $this->approvals->assertStatus($proposal, [PromotionProposal::STATUS_DRAFT]);

                $proposal->update($attributes);

                $this->approvals->record($proposal, 'updated', PromotionProposal::STATUS_DRAFT, PromotionProposal::STATUS_DRAFT);

                return $proposal;
            }

            $proposal = PromotionProposal::create($attributes + [
                'reference' => $this->references->next('PROMO'),
                'requested_by' => auth()->guard('admin')->id(),
                'status' => PromotionProposal::STATUS_DRAFT,
            ]);

            $this->approvals->record($proposal, 'created', null, PromotionProposal::STATUS_DRAFT);

            return $proposal;
        });
    }

    public function submitPromotion(PromotionProposal $proposal): PromotionProposal
    {
        $this->validatePromotion($proposal->toArray() + [
            'starts_on' => $proposal->starts_on->toDateString(),
            'ends_on' => $proposal->ends_on->toDateString(),
        ]);

        $amount = $proposal->discount_type === PromotionProposal::DISCOUNT_PERCENT ? (float) $proposal->discount_amount : null;

        return $this->approvals->submit($proposal, $amount);
    }

    /**
     * Approve; on the final approval the catalog rule is created with the
     * proposal's date window and the campaign becomes Scheduled or Active.
     */
    public function approvePromotion(PromotionProposal $proposal, ?string $remarks): PromotionProposal
    {
        $proposal = DB::transaction(function () use ($proposal, $remarks) {
            $proposal = $this->approvals->approve($proposal, $remarks, PromotionProposal::STATUS_SCHEDULED);

            if (! $proposal->isFullyApproved()) {
                return $proposal;
            }

            if ($proposal->ends_on->lt(today())) {
                throw OperationsException::trans('promotion-expired');
            }

            $rule = $this->catalogRuleRepository->create($this->rulePayload($proposal, true));

            Event::dispatch('promotions.catalog_rule.create.after', $rule);

            $isLive = $proposal->starts_on->lte(today());

            $proposal->update([
                'catalog_rule_id' => $rule->id,
                'status' => $isLive ? PromotionProposal::STATUS_ACTIVE : PromotionProposal::STATUS_SCHEDULED,
                'activated_at' => $isLive ? now() : null,
            ]);

            $this->approvals->record($proposal, $isLive ? 'activated' : 'campaign_scheduled', PromotionProposal::STATUS_SCHEDULED, $proposal->status, 'Catalog rule #'.$rule->id);

            return $proposal;
        });

        return $proposal;
    }

    public function rejectPromotion(PromotionProposal $proposal, string $remarks): PromotionProposal
    {
        return $this->approvals->reject($proposal, $remarks);
    }

    public function cancelPromotion(PromotionProposal $proposal, ?string $remarks): PromotionProposal
    {
        return DB::transaction(function () use ($proposal, $remarks) {
            $proposal = $this->approvals->lock($proposal);

            $this->approvals->assertStatus($proposal, [
                PromotionProposal::STATUS_DRAFT,
                PromotionProposal::STATUS_SUBMITTED,
                PromotionProposal::STATUS_SCHEDULED,
                PromotionProposal::STATUS_ACTIVE,
            ]);

            $from = $proposal->status;

            $this->deactivateRule($proposal);

            $proposal->update(['status' => PromotionProposal::STATUS_CANCELLED]);

            $this->approvals->record($proposal, 'cancelled', $from, PromotionProposal::STATUS_CANCELLED, $remarks);

            return $proposal;
        });
    }

    /**
     * Scheduler: activate due campaigns, expire finished ones and warn before expiry.
     *
     * @return array{activated: int, expired: int, warned: int}
     */
    public function syncPromotions(): array
    {
        $result = ['activated' => 0, 'expired' => 0, 'warned' => 0];

        foreach (PromotionProposal::where('status', PromotionProposal::STATUS_SCHEDULED)->whereDate('starts_on', '<=', today())->get() as $proposal) {
            DB::transaction(function () use ($proposal, &$result) {
                $proposal = $this->approvals->lock($proposal);

                if ($proposal->status !== PromotionProposal::STATUS_SCHEDULED || ! $proposal->catalogRule) {
                    return;
                }

                if ($proposal->ends_on->lt(today())) {
                    return;
                }

                $proposal->update(['status' => PromotionProposal::STATUS_ACTIVE, 'activated_at' => now()]);

                Event::dispatch('promotions.catalog_rule.update.after', $proposal->catalogRule);

                $this->approvals->record($proposal, 'activated', PromotionProposal::STATUS_SCHEDULED, PromotionProposal::STATUS_ACTIVE);

                $result['activated']++;
            });
        }

        foreach (PromotionProposal::whereIn('status', [PromotionProposal::STATUS_ACTIVE, PromotionProposal::STATUS_SCHEDULED])->whereDate('ends_on', '<', today())->get() as $proposal) {
            DB::transaction(function () use ($proposal, &$result) {
                $proposal = $this->approvals->lock($proposal);

                $from = $proposal->status;

                $this->deactivateRule($proposal);

                $proposal->update(['status' => PromotionProposal::STATUS_EXPIRED, 'expired_at' => now()]);

                $this->approvals->record($proposal, 'expired', $from, PromotionProposal::STATUS_EXPIRED);

                $result['expired']++;
            });
        }

        $warnUntil = today()->addDays($this->settings->promotionExpiryWarningDays());

        foreach (PromotionProposal::where('status', PromotionProposal::STATUS_ACTIVE)->whereNull('expiry_warned_at')->whereDate('ends_on', '<=', $warnUntil)->get() as $proposal) {
            $title = trans('operations::app.notifications.promotion-expiring', ['name' => $proposal->name, 'date' => $proposal->ends_on->toDateString()]);
            $url = route('admin.operations.promotions.view', $proposal->id);

            $this->notifications->notifyPermission('operations.promotions.create', 'promotion_expiring', $title, null, $url);

            $proposal->update(['expiry_warned_at' => now()]);

            $result['warned']++;
        }

        return $result;
    }

    protected function deactivateRule(PromotionProposal $proposal): void
    {
        if (! $rule = $proposal->catalogRule) {
            return;
        }

        Event::dispatch('promotions.catalog_rule.update.before', $rule->id);

        // CatalogRuleRepository treats any present `status` key as active, so omit it.
        $rule = $this->catalogRuleRepository->update($this->rulePayload($proposal, false), $rule->id);

        Event::dispatch('promotions.catalog_rule.update.after', $rule);
    }

    protected function rulePayload(PromotionProposal $proposal, bool $active): array
    {
        $conditions = [];

        foreach ($this->productCatalog->findMany($proposal->product_ids ?? []) as $product) {
            $conditions[] = [
                'attribute' => 'product|sku',
                'operator' => '==',
                'attribute_type' => 'text',
                'value' => $product->sku,
            ];
        }

        if (! empty($proposal->category_ids)) {
            $conditions[] = [
                'attribute' => 'product|category_ids',
                'operator' => '{}',
                'attribute_type' => 'multiselect',
                'value' => array_map('intval', $proposal->category_ids),
            ];
        }

        $payload = [
            'name' => '['.$proposal->reference.'] '.$proposal->name,
            'description' => trans('operations::app.promotions.rule-description', ['reference' => $proposal->reference]),
            'starts_from' => $proposal->starts_on->toDateString(),
            'ends_till' => $proposal->ends_on->toDateString(),
            'condition_type' => 2,
            'conditions' => $conditions,
            'end_other_rules' => 0,
            'action_type' => $proposal->discount_type,
            'discount_amount' => $proposal->discount_amount,
            'sort_order' => 0,
            'channels' => DB::table('channels')->pluck('id')->all(),
            'customer_groups' => DB::table('customer_groups')->pluck('id')->all(),
        ];

        if ($active) {
            $payload['status'] = 1;
        }

        return $payload;
    }

    protected function validatePromotion(array $data): void
    {
        $starts = Carbon::parse($data['starts_on'])->startOfDay();
        $ends = Carbon::parse($data['ends_on'])->startOfDay();

        if ($ends->lt($starts)) {
            throw OperationsException::trans('date-range');
        }

        if ($ends->lt(today())) {
            throw OperationsException::trans('promotion-expired');
        }

        if (empty($data['product_ids']) && empty($data['category_ids'])) {
            throw OperationsException::trans('promotion-target-required');
        }

        $max = $this->settings->maxDiscountPercent();

        if ($data['discount_type'] === PromotionProposal::DISCOUNT_PERCENT && (float) $data['discount_amount'] > $max) {
            throw OperationsException::trans('discount-too-high', ['max' => $max]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk price changes
    |--------------------------------------------------------------------------
    */

    /**
     * Parse a CSV (sku, new_price). Returns [batch|null, errors[]]; nothing is
     * saved when any row is invalid.
     */
    public function importPriceChanges(string $name, string $effectiveAt, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $header = array_map(fn ($value) => strtolower(trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($handle) ?: []);

        if (! in_array('sku', $header, true) || ! in_array('new_price', $header, true)) {
            fclose($handle);

            return [null, [trans('operations::app.price-changes.bad-header')]];
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($data, 0, count($header)), count($header), null));

            $sku = trim((string) $row['sku']);
            $price = trim((string) $row['new_price']);

            if ($sku === '') {
                $errors[] = trans('operations::app.price-changes.row-error', ['line' => $line, 'error' => trans('operations::app.price-changes.missing-sku')]);

                continue;
            }

            if (isset($seen[$sku])) {
                $errors[] = trans('operations::app.price-changes.row-error', ['line' => $line, 'error' => trans('operations::app.price-changes.duplicate-sku', ['sku' => $sku, 'line' => $seen[$sku]])]);

                continue;
            }

            $seen[$sku] = $line;

            if (! is_numeric($price) || (float) $price < 0 || (float) $price > 100000000) {
                $errors[] = trans('operations::app.price-changes.row-error', ['line' => $line, 'error' => trans('operations::app.price-changes.bad-price', ['sku' => $sku])]);

                continue;
            }

            $productId = DB::table('products')->where('sku', $sku)->value('id');

            if (! $productId) {
                $errors[] = trans('operations::app.price-changes.row-error', ['line' => $line, 'error' => trans('operations::app.price-changes.unknown-sku', ['sku' => $sku])]);

                continue;
            }

            $rows[] = ['product_id' => (int) $productId, 'sku' => $sku, 'new_price' => round((float) $price, 4)];
        }

        fclose($handle);

        if ($errors || ! $rows) {
            return [null, $errors ?: [trans('operations::app.errors.no-items')]];
        }

        $batch = DB::transaction(function () use ($name, $effectiveAt, $rows) {
            $batch = PriceChangeBatch::create([
                'reference' => $this->references->next('PCB'),
                'name' => $name,
                'requested_by' => auth()->guard('admin')->id(),
                'status' => PriceChangeBatch::STATUS_DRAFT,
                'effective_at' => $effectiveAt,
            ]);

            foreach ($rows as $row) {
                $batch->items()->create($row + [
                    'old_price' => $this->productCatalog->price($row['product_id']),
                    'status' => PriceChangeItem::STATUS_PENDING,
                ]);
            }

            $this->approvals->record($batch, 'created', null, PriceChangeBatch::STATUS_DRAFT, count($rows).' line(s)');

            return $batch;
        });

        return [$batch, []];
    }

    public function submitPriceChange(PriceChangeBatch $batch): PriceChangeBatch
    {
        $largestChange = $batch->items->map(function ($item) {
            $old = (float) $item->old_price;

            return $old > 0 ? abs(((float) $item->new_price - $old) / $old * 100) : 100;
        })->max() ?? 0;

        return $this->approvals->submit($batch, (float) $largestChange);
    }

    public function approvePriceChange(PriceChangeBatch $batch, ?string $remarks): PriceChangeBatch
    {
        $batch = $this->approvals->approve($batch, $remarks, PriceChangeBatch::STATUS_APPROVED);

        if ($batch->status === PriceChangeBatch::STATUS_APPROVED && $batch->effective_at->lte(now())) {
            $batch = $this->applyPriceChange($batch);
        }

        return $batch;
    }

    public function rejectPriceChange(PriceChangeBatch $batch, string $remarks): PriceChangeBatch
    {
        return $this->approvals->reject($batch, $remarks);
    }

    public function cancelPriceChange(PriceChangeBatch $batch, ?string $remarks): PriceChangeBatch
    {
        return DB::transaction(function () use ($batch, $remarks) {
            $batch = $this->approvals->lock($batch);

            $this->approvals->assertStatus($batch, [PriceChangeBatch::STATUS_DRAFT, PriceChangeBatch::STATUS_SUBMITTED, PriceChangeBatch::STATUS_APPROVED]);

            $from = $batch->status;

            $batch->update(['status' => PriceChangeBatch::STATUS_CANCELLED]);

            $this->approvals->record($batch, 'cancelled', $from, PriceChangeBatch::STATUS_CANCELLED, $remarks);

            return $batch;
        });
    }

    /**
     * Apply an approved batch. Each line is applied independently so one bad
     * product does not block the rest; failures are recorded on the line.
     */
    public function applyPriceChange(PriceChangeBatch $batch): PriceChangeBatch
    {
        $batch = DB::transaction(function () use ($batch) {
            $batch = $this->approvals->lock($batch);

            if ($batch->status !== PriceChangeBatch::STATUS_APPROVED || ! $batch->isFullyApproved()) {
                return $batch;
            }

            $priceAttributeId = DB::table('attributes')->where('code', 'price')->value('id');

            $failed = 0;
            $touched = [];

            foreach ($batch->items()->where('status', PriceChangeItem::STATUS_PENDING)->get() as $item) {
                try {
                    DB::transaction(function () use ($item, $batch, $priceAttributeId) {
                        $old = $this->productCatalog->price($item->product_id);

                        DB::table('product_attribute_values')->updateOrInsert(
                            ['product_id' => $item->product_id, 'attribute_id' => $priceAttributeId, 'channel' => null, 'locale' => null],
                            ['float_value' => $item->new_price, 'unique_id' => $item->product_id.'|'.$priceAttributeId],
                        );

                        PriceHistory::create([
                            'product_id' => $item->product_id,
                            'sku' => $item->sku,
                            'old_price' => $old,
                            'new_price' => $item->new_price,
                            'source_type' => $batch->getMorphClass(),
                            'source_id' => $batch->id,
                            'admin_id' => auth()->guard('admin')->id(),
                            'changed_at' => now(),
                        ]);

                        $item->update(['old_price' => $old, 'status' => PriceChangeItem::STATUS_APPLIED, 'error' => null]);
                    });

                    $touched[] = $item->product_id;
                } catch (Throwable $e) {
                    $failed++;

                    $item->update(['status' => PriceChangeItem::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);

                    Log::warning('FreshCart price change line failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);
                }
            }

            $status = $failed ? PriceChangeBatch::STATUS_PARTIALLY_APPLIED : PriceChangeBatch::STATUS_APPLIED;

            $batch->update(['status' => $status, 'applied_at' => now()]);

            $this->approvals->record($batch, 'applied', PriceChangeBatch::STATUS_APPROVED, $status, $failed ? $failed.' line(s) failed' : null);

            DB::afterCommit(function () use ($touched) {
                foreach ($this->productRepository->findWhereIn('id', array_unique($touched)) as $product) {
                    Event::dispatch('catalog.product.update.after', $product);
                }
            });

            return $batch;
        });

        return $batch;
    }

    /**
     * Scheduler: apply approved batches whose effective time has arrived.
     */
    public function applyDuePriceChanges(): int
    {
        $count = 0;

        foreach (PriceChangeBatch::where('status', PriceChangeBatch::STATUS_APPROVED)->where('effective_at', '<=', now())->get() as $batch) {
            $this->applyPriceChange($batch);

            $count++;
        }

        return $count;
    }
}
