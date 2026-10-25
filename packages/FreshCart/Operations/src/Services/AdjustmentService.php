<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\Incident;
use FreshCart\Operations\Support\ProductCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Controlled inventory adjustments (P6). An adjustment is a document: creating,
 * submitting or approving it never changes stock. Only posting a fully approved
 * adjustment calls the stock ledger, once.
 */
class AdjustmentService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected StockLedgerService $stockLedger,
        protected ProductCatalog $productCatalog,
    ) {}

    /**
     * @param  array  $data  inventory_source_id, direction, source_bucket, reason_code, reason, origin (Model)
     * @param  array  $items  [{product_id, qty, lot_id?, unit_cost?}]
     */
    public function create(array $data, array $items): Adjustment
    {
        return DB::transaction(function () use ($data, $items) {
            $origin = $data['origin'] ?? null;

            $adjustment = Adjustment::create([
                'reference' => $this->references->next('ADJ'),
                'inventory_source_id' => $data['inventory_source_id'],
                'direction' => $data['direction'],
                'source_bucket' => $data['direction'] === Adjustment::DIRECTION_OUT ? ($data['source_bucket'] ?? null) : null,
                'reason_code' => $data['reason_code'],
                'reason' => $data['reason'] ?? null,
                'status' => Adjustment::STATUS_DRAFT,
                'origin_type' => $origin instanceof Model ? $origin->getMorphClass() : null,
                'origin_id' => $origin instanceof Model ? $origin->getKey() : null,
                'requested_by' => auth()->guard('admin')->id(),
            ]);

            $total = 0;

            foreach ($items as $item) {
                $qty = (int) ($item['qty'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $product = $this->productCatalog->stockable((int) $item['product_id']);

                $lotId = ! empty($item['lot_id']) ? (int) $item['lot_id'] : null;

                $unitCost = $item['unit_cost']
                    ?? ($lotId ? DB::table('fc_stock_lots')->where('id', $lotId)->value('unit_cost') : null)
                    ?? $this->latestCost($product->id, $data['inventory_source_id'])
                    ?? $this->productCatalog->cost($product->id)
                    ?? 0;

                $adjustment->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name ?? $product->sku,
                    'lot_id' => $lotId,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'line_value' => $qty * (float) $unitCost,
                ]);

                $total += $qty * (float) $unitCost;
            }

            if (! $adjustment->items()->exists()) {
                throw OperationsException::trans('no-items');
            }

            $adjustment->update(['total_value' => $total]);

            $this->approvals->record($adjustment, 'created', null, Adjustment::STATUS_DRAFT, $data['reason'] ?? null);

            return $adjustment->fresh('items');
        });
    }

    public function submit(Adjustment $adjustment): Adjustment
    {
        return $this->approvals->submit($adjustment, (float) $adjustment->total_value);
    }

    public function approve(Adjustment $adjustment, ?string $remarks): Adjustment
    {
        return $this->approvals->approve($adjustment, $remarks, Adjustment::STATUS_APPROVED);
    }

    public function reject(Adjustment $adjustment, string $remarks): Adjustment
    {
        return $this->approvals->reject($adjustment, $remarks);
    }

    public function cancel(Adjustment $adjustment, ?string $remarks): Adjustment
    {
        return DB::transaction(function () use ($adjustment, $remarks) {
            $adjustment = $this->approvals->lock($adjustment);

            $this->approvals->assertStatus($adjustment, [Adjustment::STATUS_DRAFT, Adjustment::STATUS_SUBMITTED]);

            $from = $adjustment->status;

            $adjustment->update(['status' => Adjustment::STATUS_CANCELLED]);

            $this->approvals->record($adjustment, 'cancelled', $from, Adjustment::STATUS_CANCELLED, $remarks);

            return $adjustment;
        });
    }

    /**
     * Apply an approved adjustment to stock exactly once.
     */
    public function post(Adjustment $adjustment): Adjustment
    {
        return DB::transaction(function () use ($adjustment) {
            $adjustment = $this->approvals->lock($adjustment);

            if ($adjustment->status === Adjustment::STATUS_POSTED) {
                return $adjustment;
            }

            if ($adjustment->status !== Adjustment::STATUS_APPROVED || ! $adjustment->isFullyApproved()) {
                throw OperationsException::trans('adjustment-not-approved');
            }

            foreach ($adjustment->items as $item) {
                $context = [
                    'reference' => $adjustment,
                    'reason' => trim(trans('operations::app.adjustments.reasons.'.$adjustment->reason_code).' '.$adjustment->reason),
                    'lot_id' => $item->lot_id,
                ];

                if ($adjustment->direction === Adjustment::DIRECTION_IN) {
                    $this->stockLedger->receive($item->product_id, $adjustment->inventory_source_id, $item->qty, $context + [
                        'type' => 'adjustment_in',
                        'unit_cost' => (float) $item->unit_cost,
                    ]);
                } else {
                    $type = match ($adjustment->reason_code) {
                        'transit_loss' => 'transit_loss',
                        'disposal' => 'disposal',
                        default => 'adjustment_out',
                    };

                    $this->stockLedger->writeOff($item->product_id, $adjustment->inventory_source_id, $item->qty, $adjustment->source_bucket, $context + [
                        'type' => $type,
                        'unit_cost' => (float) $item->unit_cost,
                    ]);
                }
            }

            $adjustment->update([
                'status' => Adjustment::STATUS_POSTED,
                'posted_by' => auth()->guard('admin')->id(),
                'posted_at' => now(),
            ]);

            $this->approvals->record($adjustment, 'posted', Adjustment::STATUS_APPROVED, Adjustment::STATUS_POSTED);

            if ($adjustment->origin instanceof Incident && $adjustment->origin->status === Incident::STATUS_CLASSIFIED) {
                $adjustment->origin->update(['status' => Incident::STATUS_CLOSED]);

                $this->approvals->record($adjustment->origin, 'closed', Incident::STATUS_CLASSIFIED, Incident::STATUS_CLOSED, $adjustment->reference);
            }

            return $adjustment;
        });
    }

    /**
     * Most recent known unit cost of the product at the location (from lots).
     */
    protected function latestCost(int $productId, int $sourceId): ?float
    {
        $cost = DB::table('fc_stock_lots')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->whereNotNull('unit_cost')
            ->latest('id')
            ->value('unit_cost');

        return $cost === null ? null : (float) $cost;
    }
}
