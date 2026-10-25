<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\StockLot as StockLotContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webkul\Product\Models\ProductProxy;

/**
 * A received batch. `qty_on_hand` is the sellable portion still at the location;
 * held units of the batch are tracked on StockHold rows that reference the lot.
 */
class StockLot extends Model implements StockLotContract
{
    use BelongsToLocation;

    protected $table = 'fc_stock_lots';

    protected $fillable = [
        'product_id',
        'inventory_source_id',
        'batch_no',
        'expiry_date',
        'unit_cost',
        'qty_on_hand',
        'origin_type',
        'origin_id',
        'received_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_at' => 'datetime',
        'qty_on_hand' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }
}
