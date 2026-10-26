<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\StockHold as StockHoldContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

/**
 * Physical stock that exists at a location but must not be sold.
 */
class StockHold extends Model implements StockHoldContract
{
    use BelongsToLocation;

    const BUCKET_QUARANTINE = 'quarantine';

    const BUCKET_DAMAGED = 'damaged';

    const BUCKET_EXPIRED = 'expired';

    const BUCKET_SUPPLIER_RETURN = 'supplier_return';

    const BUCKET_IN_TRANSIT = 'in_transit';

    const BUCKETS = [
        self::BUCKET_QUARANTINE,
        self::BUCKET_DAMAGED,
        self::BUCKET_EXPIRED,
        self::BUCKET_SUPPLIER_RETURN,
        self::BUCKET_IN_TRANSIT,
    ];

    protected $table = 'fc_stock_holds';

    protected $fillable = [
        'product_id',
        'inventory_source_id',
        'lot_id',
        'bucket',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLotProxy::modelClass(), 'lot_id');
    }
}
