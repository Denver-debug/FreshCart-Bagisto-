<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockLot;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Support\ProductCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Product\Repositories\ProductRepository;

/**
 * The only code path in FreshCart Operations that changes physical stock.
 *
 * Sellable on-hand stays in Bagisto's `product_inventories` so checkout, order
 * reservations and the inventory index keep working unchanged. Stock that is
 * physically present but must not be sold (quarantine, damaged, expired,
 * awaiting supplier return, in transit) is moved out of `product_inventories`
 * into `fc_stock_holds`. Every change writes an append-only `fc_stock_movements`
 * row naming the source document, actor, location, quantity and reason.
 *
 * Each public operation runs in a database transaction and locks the
 * `product_inventories` row of every location it touches, which serialises
 * concurrent operations on the same product and location.
 *
 * The `$context` array accepted by the operations supports:
 *   reference (Model), reference_no, reason, admin_id, type (movement type),
 *   lot_id (take from a specific lot), batch_no, expiry_date, unit_cost.
 */
class StockLedgerService
{
    /**
     * Product ids whose Bagisto indices must be refreshed once the transaction commits.
     */
    protected array $pendingReindex = [];

    public function __construct(protected ProductCatalog $productCatalog) {}

    /*
    |--------------------------------------------------------------------------
    | Read API
    |--------------------------------------------------------------------------
    */

    public function sellableQty(int $productId, int $sourceId): int
    {
        return (int) DB::table('product_inventories')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('vendor_id', 0)
            ->value('qty');
    }

    public function heldQty(int $productId, int $sourceId, ?string $bucket = null): int
    {
        return (int) DB::table('fc_stock_holds')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->when($bucket, fn ($query) => $query->where('bucket', $bucket))
            ->sum('qty');
    }

    /**
     * Units that can leave the location without breaking open order reservations.
     */
    public function transferableQty(int $productId, int $sourceId): int
    {
        $qty = $this->sellableQty($productId, $sourceId);

        foreach ($this->channelHeadrooms($productId, $sourceId) as $headroom) {
            $qty = min($qty, $headroom);
        }

        return max(0, $qty);
    }

    /*
    |--------------------------------------------------------------------------
    | Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Bring new physical stock into a location (supplier receipt, customer return,
     * found stock, supplier replacement). Lands in sellable stock unless a hold
     * bucket is given.
     */
    public function receive(int $productId, int $sourceId, int $qty, array $context = [], ?string $intoBucket = null): ?StockLot
    {
        $this->assertPositive($qty);

        return $this->transaction(function () use ($productId, $sourceId, $qty, $context, $intoBucket) {
            $this->lockInventories($productId, [$sourceId]);

            $lot = $this->hasLotData($context)
                ? $this->createLot($productId, $sourceId, $context)
                : null;

            if ($intoBucket) {
                $this->addToHold($productId, $sourceId, $intoBucket, $qty, $lot?->id);
            } else {
                $this->addToSellable($productId, $sourceId, $qty, $lot?->id);
            }

            $this->record($productId, $sourceId, $qty, $context + ['type' => 'receipt'], [
                'lot_id' => $lot?->id,
                'bucket_to' => $intoBucket,
                'sellable_delta' => $intoBucket ? 0 : $qty,
                'unit_cost' => $lot?->unit_cost ?? ($context['unit_cost'] ?? null),
            ]);

            return $lot;
        });
    }

    /**
     * Move sellable units into a hold bucket (quarantine, segregation, dispatch).
     *
     * @return array<int, array{lot_id: int|null, qty: int}>
     */
    public function hold(int $productId, int $sourceId, int $qty, string $bucket, array $context = []): array
    {
        $this->assertPositive($qty);
        $this->assertBucket($bucket);

        return $this->transaction(function () use ($productId, $sourceId, $qty, $bucket, $context) {
            $this->lockInventories($productId, [$sourceId]);

            $allocations = $this->takeFromSellable($productId, $sourceId, $qty, $context['lot_id'] ?? null);

            foreach ($allocations as $allocation) {
                $this->addToHold($productId, $sourceId, $bucket, $allocation['qty'], $allocation['lot_id']);

                $this->record($productId, $sourceId, $allocation['qty'], $context + ['type' => 'hold'], [
                    'lot_id' => $allocation['lot_id'],
                    'bucket_to' => $bucket,
                    'sellable_delta' => -$allocation['qty'],
                ]);
            }

            return $allocations;
        });
    }

    /**
     * Return held units to sellable stock.
     */
    public function release(int $productId, int $sourceId, int $qty, string $bucket, array $context = []): array
    {
        $this->assertPositive($qty);
        $this->assertBucket($bucket);

        return $this->transaction(function () use ($productId, $sourceId, $qty, $bucket, $context) {
            $this->lockInventories($productId, [$sourceId]);

            $allocations = $this->takeFromHold($productId, $sourceId, $bucket, $qty, $context['lot_id'] ?? null);

            foreach ($allocations as $allocation) {
                $this->addToSellable($productId, $sourceId, $allocation['qty'], $allocation['lot_id']);

                $this->record($productId, $sourceId, $allocation['qty'], $context + ['type' => 'release'], [
                    'lot_id' => $allocation['lot_id'],
                    'bucket_from' => $bucket,
                    'sellable_delta' => $allocation['qty'],
                ]);
            }

            return $allocations;
        });
    }

    /**
     * Reclassify held units, e.g. quarantine → damaged.
     */
    public function moveHold(int $productId, int $sourceId, int $qty, string $fromBucket, string $toBucket, array $context = []): array
    {
        $this->assertPositive($qty);
        $this->assertBucket($fromBucket);
        $this->assertBucket($toBucket);

        return $this->transaction(function () use ($productId, $sourceId, $qty, $fromBucket, $toBucket, $context) {
            $this->lockInventories($productId, [$sourceId]);

            $allocations = $this->takeFromHold($productId, $sourceId, $fromBucket, $qty, $context['lot_id'] ?? null);

            foreach ($allocations as $allocation) {
                $this->addToHold($productId, $sourceId, $toBucket, $allocation['qty'], $allocation['lot_id']);

                $this->record($productId, $sourceId, $allocation['qty'], $context + ['type' => 'reclassify'], [
                    'lot_id' => $allocation['lot_id'],
                    'bucket_from' => $fromBucket,
                    'bucket_to' => $toBucket,
                ]);
            }

            return $allocations;
        });
    }

    /**
     * Remove units from the location for good (approved write-off, disposal,
     * shipment to supplier). Takes from a hold bucket, or from sellable stock
     * when no bucket is given.
     */
    public function writeOff(int $productId, int $sourceId, int $qty, ?string $fromBucket, array $context = []): array
    {
        $this->assertPositive($qty);

        if ($fromBucket) {
            $this->assertBucket($fromBucket);
        }

        return $this->transaction(function () use ($productId, $sourceId, $qty, $fromBucket, $context) {
            $this->lockInventories($productId, [$sourceId]);

            $allocations = $fromBucket
                ? $this->takeFromHold($productId, $sourceId, $fromBucket, $qty, $context['lot_id'] ?? null)
                : $this->takeFromSellable($productId, $sourceId, $qty, $context['lot_id'] ?? null);

            foreach ($allocations as $allocation) {
                $this->record($productId, $sourceId, $allocation['qty'], $context + ['type' => 'write_off'], [
                    'lot_id' => $allocation['lot_id'],
                    'bucket_from' => $fromBucket,
                    'sellable_delta' => $fromBucket ? 0 : -$allocation['qty'],
                ]);
            }

            return $allocations;
        });
    }

    /**
     * Dispatch: sellable units at the source move to the source's in-transit bucket.
     */
    public function dispatchTransfer(int $productId, int $sourceId, int $qty, array $context = []): array
    {
        return $this->hold($productId, $sourceId, $qty, StockHold::BUCKET_IN_TRANSIT, $context + ['type' => 'transfer_dispatch']);
    }

    /**
     * Receipt at the destination: in-transit units leave the source and arrive at the
     * destination as sellable (good) or damaged-hold units. Lot batch, expiry and cost
     * travel with the goods.
     */
    public function receiveTransfer(int $productId, int $sourceId, int $destinationId, int $goodQty, int $damagedQty, array $context = []): void
    {
        $this->assertPositive($goodQty + $damagedQty);

        $this->transaction(function () use ($productId, $sourceId, $destinationId, $goodQty, $damagedQty, $context) {
            $this->lockInventories($productId, [$sourceId, $destinationId]);

            $allocations = $this->takeFromHold($productId, $sourceId, StockHold::BUCKET_IN_TRANSIT, $goodQty + $damagedQty);

            $remainingGood = $goodQty;

            foreach ($allocations as $allocation) {
                $this->record($productId, $sourceId, $allocation['qty'], $context + ['type' => 'transfer_out'], [
                    'lot_id' => $allocation['lot_id'],
                    'bucket_from' => StockHold::BUCKET_IN_TRANSIT,
                ]);

                $destinationLot = $allocation['lot_id']
                    ? $this->cloneLot($allocation['lot_id'], $destinationId, $context['reference'] ?? null)
                    : null;

                $good = min($remainingGood, $allocation['qty']);
                $damaged = $allocation['qty'] - $good;
                $remainingGood -= $good;

                if ($good > 0) {
                    $this->addToSellable($productId, $destinationId, $good, $destinationLot?->id);

                    $this->record($productId, $destinationId, $good, $context + ['type' => 'transfer_in'], [
                        'lot_id' => $destinationLot?->id,
                        'sellable_delta' => $good,
                    ]);
                }

                if ($damaged > 0) {
                    $this->addToHold($productId, $destinationId, StockHold::BUCKET_DAMAGED, $damaged, $destinationLot?->id);

                    $this->record($productId, $destinationId, $damaged, $context + ['type' => 'transfer_in_damaged'], [
                        'lot_id' => $destinationLot?->id,
                        'bucket_to' => StockHold::BUCKET_DAMAGED,
                    ]);
                }
            }
        });
    }

    /**
     * Called when Bagisto itself changes `product_inventories` (shipments, product
     * edits) so the ledger stays complete and lot balances never exceed on-hand.
     */
    public function recordExternalChange(int $productId, int $sourceId, int $delta, string $reason): void
    {
        if ($delta === 0) {
            return;
        }

        DB::transaction(function () use ($productId, $sourceId, $delta, $reason) {
            if ($delta < 0) {
                $this->trimLotsToOnHand($productId, $sourceId);
            }

            $this->record($productId, $sourceId, abs($delta), [
                'type' => 'external',
                'reason' => $reason,
                'admin_id' => auth()->guard('admin')->id(),
            ], [
                'sellable_delta' => $delta,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    protected function transaction(callable $callback): mixed
    {
        $result = DB::transaction($callback);

        $this->flushReindex();

        return $result;
    }

    /**
     * Lock (creating if needed) the inventory rows for a product at the given
     * locations, in a stable order to avoid deadlocks.
     */
    protected function lockInventories(int $productId, array $sourceIds): void
    {
        $this->productCatalog->stockable($productId);

        $sourceIds = array_values(array_unique($sourceIds));

        sort($sourceIds);

        foreach ($sourceIds as $sourceId) {
            DB::table('product_inventories')->insertOrIgnore([
                'product_id' => $productId,
                'inventory_source_id' => $sourceId,
                'vendor_id' => 0,
                'qty' => 0,
            ]);
        }

        DB::table('product_inventories')
            ->where('product_id', $productId)
            ->whereIn('inventory_source_id', $sourceIds)
            ->where('vendor_id', 0)
            ->orderBy('inventory_source_id')
            ->lockForUpdate()
            ->get();

        $this->pendingReindex[$productId] = true;
    }

    /**
     * Take units out of sellable stock, consuming lots first-expiry-first-out.
     *
     * @return array<int, array{lot_id: int|null, qty: int}>
     */
    protected function takeFromSellable(int $productId, int $sourceId, int $qty, ?int $lotId = null): array
    {
        $onHand = $this->sellableQty($productId, $sourceId);

        if ($qty > $onHand) {
            throw OperationsException::trans('insufficient-stock', [
                'sku' => $this->sku($productId),
                'available' => $onHand,
                'requested' => $qty,
            ]);
        }

        foreach ($this->channelHeadrooms($productId, $sourceId) as $headroom) {
            if ($qty > $headroom) {
                throw OperationsException::trans('stock-reserved', [
                    'sku' => $this->sku($productId),
                    'available' => max(0, $headroom),
                ]);
            }
        }

        $allocations = $this->consumeLots($productId, $sourceId, $qty, $onHand, $lotId);

        DB::table('product_inventories')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('vendor_id', 0)
            ->decrement('qty', $qty);

        return $allocations;
    }

    protected function addToSellable(int $productId, int $sourceId, int $qty, ?int $lotId): void
    {
        DB::table('product_inventories')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('vendor_id', 0)
            ->increment('qty', $qty);

        if ($lotId) {
            StockLot::whereKey($lotId)->increment('qty_on_hand', $qty);
        }
    }

    /**
     * Split a sellable withdrawal across lots. Units not covered by any lot
     * (stock that predates lot tracking) are returned with a null lot id.
     */
    protected function consumeLots(int $productId, int $sourceId, int $qty, int $onHand, ?int $lotId): array
    {
        $lots = StockLot::where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('qty_on_hand', '>', 0)
            ->when($lotId, fn ($query) => $query->whereKey($lotId))
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($lotId) {
            $lot = $lots->first();

            if (! $lot || $lot->qty_on_hand < $qty) {
                throw OperationsException::trans('insufficient-lot-stock', [
                    'sku' => $this->sku($productId),
                    'available' => $lot?->qty_on_hand ?? 0,
                ]);
            }

            $lot->decrement('qty_on_hand', $qty);

            return [['lot_id' => $lot->id, 'qty' => $qty]];
        }

        $unlotted = max(0, $onHand - $lots->sum('qty_on_hand'));
        $allocations = [];
        $remaining = $qty;

        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }

            // Lots with a known expiry go first; untracked stock is used before
            // lots that have no expiry date at all.
            if ($lot->expiry_date === null && $unlotted > 0) {
                $take = min($remaining, $unlotted);
                $allocations[] = ['lot_id' => null, 'qty' => $take];
                $remaining -= $take;
                $unlotted -= $take;

                if ($remaining === 0) {
                    break;
                }
            }

            $take = min($remaining, $lot->qty_on_hand);
            $lot->decrement('qty_on_hand', $take);
            $allocations[] = ['lot_id' => $lot->id, 'qty' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $allocations[] = ['lot_id' => null, 'qty' => $remaining];
        }

        return $this->mergeAllocations($allocations);
    }

    /**
     * Take units out of a hold bucket, oldest expiry first.
     */
    protected function takeFromHold(int $productId, int $sourceId, string $bucket, int $qty, ?int $lotId = null): array
    {
        $holds = StockHold::query()
            ->leftJoin('fc_stock_lots', 'fc_stock_lots.id', '=', 'fc_stock_holds.lot_id')
            ->where('fc_stock_holds.product_id', $productId)
            ->where('fc_stock_holds.inventory_source_id', $sourceId)
            ->where('fc_stock_holds.bucket', $bucket)
            ->where('fc_stock_holds.qty', '>', 0)
            ->when($lotId, fn ($query) => $query->where('fc_stock_holds.lot_id', $lotId))
            ->orderByRaw('fc_stock_lots.expiry_date IS NULL')
            ->orderBy('fc_stock_lots.expiry_date')
            ->orderBy('fc_stock_holds.id')
            ->select('fc_stock_holds.*')
            ->lockForUpdate()
            ->get();

        $available = (int) $holds->sum('qty');

        if ($qty > $available) {
            throw OperationsException::trans('insufficient-held-stock', [
                'sku' => $this->sku($productId),
                'bucket' => trans('operations::app.buckets.'.$bucket),
                'available' => $available,
                'requested' => $qty,
            ]);
        }

        $allocations = [];
        $remaining = $qty;

        foreach ($holds as $hold) {
            if ($remaining === 0) {
                break;
            }

            $take = min($remaining, $hold->qty);

            StockHold::whereKey($hold->id)->decrement('qty', $take);

            $allocations[] = ['lot_id' => $hold->lot_id, 'qty' => $take];
            $remaining -= $take;
        }

        StockHold::where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('qty', 0)
            ->delete();

        return $this->mergeAllocations($allocations);
    }

    protected function addToHold(int $productId, int $sourceId, string $bucket, int $qty, ?int $lotId): void
    {
        $hold = StockHold::where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('bucket', $bucket)
            ->when($lotId, fn ($query) => $query->where('lot_id', $lotId), fn ($query) => $query->whereNull('lot_id'))
            ->lockForUpdate()
            ->first();

        if ($hold) {
            $hold->increment('qty', $qty);

            return;
        }

        StockHold::create([
            'product_id' => $productId,
            'inventory_source_id' => $sourceId,
            'lot_id' => $lotId,
            'bucket' => $bucket,
            'qty' => $qty,
        ]);
    }

    protected function createLot(int $productId, int $sourceId, array $context): StockLot
    {
        $reference = $context['reference'] ?? null;

        return StockLot::create([
            'product_id' => $productId,
            'inventory_source_id' => $sourceId,
            'batch_no' => $context['batch_no'] ?? null,
            'expiry_date' => $context['expiry_date'] ?? null,
            'unit_cost' => $context['unit_cost'] ?? null,
            'qty_on_hand' => 0,
            'origin_type' => $reference instanceof Model ? $reference->getMorphClass() : null,
            'origin_id' => $reference instanceof Model ? $reference->getKey() : null,
            'received_at' => now(),
        ]);
    }

    /**
     * Find or create the destination copy of a lot (same batch, expiry and cost).
     */
    protected function cloneLot(int $lotId, int $destinationId, ?Model $reference): StockLot
    {
        $lot = StockLot::findOrFail($lotId);

        $existing = StockLot::where('product_id', $lot->product_id)
            ->where('inventory_source_id', $destinationId)
            ->where('batch_no', $lot->batch_no)
            ->where(fn ($query) => $lot->expiry_date
                ? $query->whereDate('expiry_date', $lot->expiry_date)
                : $query->whereNull('expiry_date'))
            ->where(fn ($query) => $lot->unit_cost === null
                ? $query->whereNull('unit_cost')
                : $query->where('unit_cost', $lot->unit_cost))
            ->lockForUpdate()
            ->first();

        return $existing ?? StockLot::create([
            'product_id' => $lot->product_id,
            'inventory_source_id' => $destinationId,
            'batch_no' => $lot->batch_no,
            'expiry_date' => $lot->expiry_date,
            'unit_cost' => $lot->unit_cost,
            'qty_on_hand' => 0,
            'origin_type' => $reference?->getMorphClass(),
            'origin_id' => $reference?->getKey(),
            'received_at' => now(),
        ]);
    }

    /**
     * After an external decrease, consume lots FEFO so their total never exceeds on-hand.
     */
    protected function trimLotsToOnHand(int $productId, int $sourceId): void
    {
        $onHand = $this->sellableQty($productId, $sourceId);

        $lots = StockLot::where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('qty_on_hand', '>', 0)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $excess = $lots->sum('qty_on_hand') - $onHand;

        foreach ($lots as $lot) {
            if ($excess <= 0) {
                break;
            }

            $take = min($excess, $lot->qty_on_hand);
            $lot->decrement('qty_on_hand', $take);
            $excess -= $take;
        }
    }

    /**
     * For every channel selling from this location: units on hand across the
     * channel's active sources minus units reserved by open orders.
     *
     * @return array<int, int> channel id => headroom
     */
    protected function channelHeadrooms(int $productId, int $sourceId): array
    {
        $isActive = DB::table('inventory_sources')->where('id', $sourceId)->value('status');

        if (! $isActive) {
            return [];
        }

        $channelIds = DB::table('channel_inventory_sources')
            ->where('inventory_source_id', $sourceId)
            ->pluck('channel_id');

        $headrooms = [];

        foreach ($channelIds as $channelId) {
            $onHand = (int) DB::table('product_inventories')
                ->join('channel_inventory_sources', 'channel_inventory_sources.inventory_source_id', '=', 'product_inventories.inventory_source_id')
                ->join('inventory_sources', 'inventory_sources.id', '=', 'product_inventories.inventory_source_id')
                ->where('channel_inventory_sources.channel_id', $channelId)
                ->where('inventory_sources.status', 1)
                ->where('product_inventories.product_id', $productId)
                ->sum('product_inventories.qty');

            $ordered = (int) DB::table('product_ordered_inventories')
                ->where('product_id', $productId)
                ->where('channel_id', $channelId)
                ->value('qty');

            $headrooms[$channelId] = $onHand - $ordered;
        }

        return $headrooms;
    }

    protected function record(int $productId, int $sourceId, int $qty, array $context, array $attributes): StockMovement
    {
        $reference = $context['reference'] ?? null;

        $lotId = $attributes['lot_id'] ?? null;

        $unitCost = $attributes['unit_cost']
            ?? ($lotId ? StockLot::whereKey($lotId)->value('unit_cost') : null)
            ?? $this->productCatalog->cost($productId);

        return StockMovement::create([
            'product_id' => $productId,
            'sku' => $this->sku($productId),
            'inventory_source_id' => $sourceId,
            'lot_id' => $lotId,
            'movement_type' => $context['type'],
            'bucket_from' => $attributes['bucket_from'] ?? null,
            'bucket_to' => $attributes['bucket_to'] ?? null,
            'qty' => $qty,
            'sellable_delta' => $attributes['sellable_delta'] ?? 0,
            'sellable_after' => $this->sellableQty($productId, $sourceId),
            'unit_cost' => $unitCost,
            'reference_type' => $reference instanceof Model ? $reference->getMorphClass() : null,
            'reference_id' => $reference instanceof Model ? $reference->getKey() : null,
            'reference_no' => $context['reference_no'] ?? ($reference instanceof Model ? $reference->reference : null),
            'reason' => $context['reason'] ?? null,
            'admin_id' => $context['admin_id'] ?? auth()->guard('admin')->id(),
        ]);
    }

    /**
     * Refresh Bagisto's inventory/price/search indices for touched products after commit.
     */
    protected function flushReindex(): void
    {
        // Runs immediately when no transaction is open, otherwise once the outermost commits.
        DB::afterCommit(fn () => $this->reindexPending());
    }

    protected function reindexPending(): void
    {
        $productIds = array_keys($this->pendingReindex);

        $this->pendingReindex = [];

        if (empty($productIds)) {
            return;
        }

        foreach (app(ProductRepository::class)->findWhereIn('id', $productIds) as $product) {
            Event::dispatch('catalog.product.update.after', $product);
        }
    }

    protected function mergeAllocations(array $allocations): array
    {
        $merged = [];

        foreach ($allocations as $allocation) {
            $key = $allocation['lot_id'] ?? 'none';

            $merged[$key] = [
                'lot_id' => $allocation['lot_id'],
                'qty' => ($merged[$key]['qty'] ?? 0) + $allocation['qty'],
            ];
        }

        return array_values($merged);
    }

    protected function hasLotData(array $context): bool
    {
        return ! empty($context['batch_no'])
            || ! empty($context['expiry_date'])
            || isset($context['unit_cost']);
    }

    protected function sku(int $productId): string
    {
        return (string) DB::table('products')->where('id', $productId)->value('sku');
    }

    protected function assertPositive(int $qty): void
    {
        if ($qty <= 0) {
            throw OperationsException::trans('quantity-must-be-positive');
        }
    }

    protected function assertBucket(string $bucket): void
    {
        if (! in_array($bucket, StockHold::BUCKETS, true)) {
            throw new \InvalidArgumentException("Unknown stock bucket [$bucket].");
        }
    }
}
