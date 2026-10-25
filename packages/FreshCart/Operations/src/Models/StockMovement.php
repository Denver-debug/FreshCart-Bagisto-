<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\StockMovement as StockMovementContract;
use FreshCart\Operations\Models\Concerns\AppendOnly;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webkul\Product\Models\ProductProxy;
use Webkul\User\Models\AdminProxy;

/**
 * Append-only stock ledger entry.
 */
class StockMovement extends Model implements StockMovementContract
{
    use AppendOnly, BelongsToLocation;

    const UPDATED_AT = null;

    protected $table = 'fc_stock_movements';

    protected $fillable = [
        'product_id',
        'sku',
        'inventory_source_id',
        'lot_id',
        'movement_type',
        'bucket_from',
        'bucket_to',
        'qty',
        'sellable_delta',
        'sellable_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reference_no',
        'reason',
        'admin_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass());
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
