<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PriceHistory as PriceHistoryContract;
use FreshCart\Operations\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model implements PriceHistoryContract
{
    use AppendOnly;

    public $timestamps = false;

    protected $table = 'fc_price_history';

    protected $fillable = [
        'product_id',
        'sku',
        'old_price',
        'new_price',
        'source_type',
        'source_id',
        'admin_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];
}
