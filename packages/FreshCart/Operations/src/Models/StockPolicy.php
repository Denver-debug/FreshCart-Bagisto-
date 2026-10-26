<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\StockPolicy as StockPolicyContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class StockPolicy extends Model implements StockPolicyContract
{
    use BelongsToLocation;

    protected $table = 'fc_stock_policies';

    protected $fillable = [
        'product_id',
        'inventory_source_id',
        'reorder_level',
        'min_stock',
        'max_stock',
    ];

    protected $casts = [
        'reorder_level' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'last_alerted_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }
}
