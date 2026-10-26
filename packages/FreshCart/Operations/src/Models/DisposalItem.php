<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\DisposalItem as DisposalItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class DisposalItem extends Model implements DisposalItemContract
{
    const SOURCE_SELLABLE = 'sellable';

    protected $table = 'fc_disposal_items';

    protected $fillable = [
        'disposal_request_id',
        'product_id',
        'sku',
        'name',
        'lot_id',
        'batch_no',
        'expiry_date',
        'qty',
        'source_bucket',
        'reason',
        'disposition',
        'unit_cost',
        'line_value',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'qty' => 'integer',
    ];

    public function disposalRequest(): BelongsTo
    {
        return $this->belongsTo(DisposalRequestProxy::modelClass(), 'disposal_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }
}
