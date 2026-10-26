<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\StockRequestItem as StockRequestItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class StockRequestItem extends Model implements StockRequestItemContract
{
    protected $table = 'fc_stock_request_items';

    protected $fillable = [
        'stock_request_id',
        'product_id',
        'sku',
        'name',
        'qty_requested',
        'qty_suggested',
        'qty_allocated',
        'qty_dispatched',
        'qty_received',
        'override_reason',
    ];

    protected $casts = [
        'qty_requested' => 'integer',
        'qty_suggested' => 'integer',
        'qty_allocated' => 'integer',
        'qty_dispatched' => 'integer',
        'qty_received' => 'integer',
    ];

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequestProxy::modelClass(), 'stock_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    /**
     * Requested units not yet allocated (the backorder).
     */
    public function outstandingQty(): int
    {
        return max(0, $this->qty_requested - $this->qty_allocated);
    }
}
