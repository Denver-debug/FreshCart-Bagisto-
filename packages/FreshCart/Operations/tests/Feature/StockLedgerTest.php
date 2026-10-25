<?php

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Services\StockLedgerService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->ledger = app(StockLedgerService::class);
    $this->warehouse = $this->makeLocation(Location::TYPE_WAREHOUSE, true);
    $this->branch = $this->makeLocation();
});

it('moves quarantined stock out of sellable inventory so it cannot be sold (acceptance 9)', function () {
    $product = $this->makeProduct([$this->branch->id => 10]);

    $this->ledger->hold($product->id, $this->branch->id, 4, StockHold::BUCKET_QUARANTINE, ['reason' => 'test']);

    expect($this->sellable($product->id, $this->branch->id))->toBe(6)
        ->and($this->held($product->id, $this->branch->id, StockHold::BUCKET_QUARANTINE))->toBe(4)
        ->and($this->channelIndexQty($product->id))->toBe(6)
        ->and($product->fresh()->getTypeInstance()->haveSufficientQuantity(7))->toBeFalse()
        ->and($product->fresh()->getTypeInstance()->haveSufficientQuantity(6))->toBeTrue();
});

it('refuses to take more stock than is on hand (no negative stock)', function () {
    $product = $this->makeProduct([$this->branch->id => 3]);

    expect(fn () => $this->ledger->hold($product->id, $this->branch->id, 4, StockHold::BUCKET_DAMAGED))
        ->toThrow(OperationsException::class);

    expect($this->sellable($product->id, $this->branch->id))->toBe(3)
        ->and(StockMovement::where('product_id', $product->id)->where('movement_type', 'hold')->count())->toBe(0);
});

it('protects units reserved by open orders', function () {
    $product = $this->makeProduct([$this->branch->id => 5]);

    DB::table('product_ordered_inventories')->insert([
        'product_id' => $product->id,
        'channel_id' => core()->getDefaultChannel()->id,
        'qty' => 4,
    ]);

    expect($this->ledger->transferableQty($product->id, $this->branch->id))->toBe(1);

    expect(fn () => $this->ledger->dispatchTransfer($product->id, $this->branch->id, 2))
        ->toThrow(OperationsException::class);

    $this->ledger->dispatchTransfer($product->id, $this->branch->id, 1);

    expect($this->sellable($product->id, $this->branch->id))->toBe(4);
});

it('records every change in an append-only ledger', function () {
    $product = $this->makeProduct([$this->branch->id => 8]);

    $this->ledger->hold($product->id, $this->branch->id, 2, StockHold::BUCKET_EXPIRED, ['reason' => 'expired on shelf']);
    $this->ledger->writeOff($product->id, $this->branch->id, 2, StockHold::BUCKET_EXPIRED, ['reason' => 'disposed']);

    $movements = StockMovement::where('product_id', $product->id)
        ->whereIn('movement_type', ['hold', 'write_off'])
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->sellable_delta)->toBe(-2)
        ->and($movements[0]->sellable_after)->toBe(6)
        ->and($movements[1]->bucket_from)->toBe(StockHold::BUCKET_EXPIRED);

    expect(fn () => $movements[0]->update(['qty' => 99]))->toThrow(LogicException::class);
    expect(fn () => $movements[0]->delete())->toThrow(LogicException::class);
});

it('consumes batches first-expiry-first-out and carries them through transfers', function () {
    $product = $this->makeProduct([$this->warehouse->id => 0]);

    $this->ledger->receive($product->id, $this->warehouse->id, 5, ['batch_no' => 'LATE', 'expiry_date' => now()->addDays(60)->toDateString(), 'unit_cost' => 10]);
    $this->ledger->receive($product->id, $this->warehouse->id, 5, ['batch_no' => 'SOON', 'expiry_date' => now()->addDays(5)->toDateString(), 'unit_cost' => 12]);

    $allocations = $this->ledger->dispatchTransfer($product->id, $this->warehouse->id, 6);

    $lots = DB::table('fc_stock_lots')->whereIn('id', array_column($allocations, 'lot_id'))->pluck('batch_no', 'id');

    expect($allocations)->toHaveCount(2)
        ->and($lots[$allocations[0]['lot_id']])->toBe('SOON')
        ->and($allocations[0]['qty'])->toBe(5);

    $this->ledger->receiveTransfer($product->id, $this->warehouse->id, $this->branch->id, 5, 1);

    expect($this->sellable($product->id, $this->branch->id))->toBe(5)
        ->and($this->held($product->id, $this->branch->id, StockHold::BUCKET_DAMAGED))->toBe(1)
        ->and($this->held($product->id, $this->warehouse->id, StockHold::BUCKET_IN_TRANSIT))->toBe(0)
        ->and(DB::table('fc_stock_lots')->where('inventory_source_id', $this->branch->id)->where('batch_no', 'SOON')->exists())->toBeTrue();
});

it('logs stock changes made by Bagisto itself', function () {
    $product = $this->makeProduct([$this->branch->id => 5]);

    $inventory = $product->inventories()->where('inventory_source_id', $this->branch->id)->first();

    $inventory->update(['qty' => 3]);

    $movement = StockMovement::where('product_id', $product->id)->where('movement_type', 'external')->latest('id')->first();

    expect($movement)->not->toBeNull()
        ->and($movement->sellable_delta)->toBe(-2)
        ->and($movement->sellable_after)->toBe(3);
});
