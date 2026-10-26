<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PriceChangeItem as PriceChangeItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class PriceChangeItem extends Model implements PriceChangeItemContract
{
    const STATUS_PENDING = 'pending';

    const STATUS_APPLIED = 'applied';

    const STATUS_FAILED = 'failed';

    protected $table = 'fc_price_change_items';

    protected $fillable = [
        'price_change_batch_id',
        'product_id',
        'sku',
        'old_price',
        'new_price',
        'status',
        'error',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PriceChangeBatchProxy::modelClass(), 'price_change_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }
}
