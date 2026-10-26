<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\AdjustmentItem as AdjustmentItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class AdjustmentItem extends Model implements AdjustmentItemContract
{
    protected $table = 'fc_adjustment_items';

    protected $fillable = [
        'adjustment_id',
        'product_id',
        'sku',
        'name',
        'lot_id',
        'qty',
        'unit_cost',
        'line_value',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(AdjustmentProxy::modelClass(), 'adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }
}
