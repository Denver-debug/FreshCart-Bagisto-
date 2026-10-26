<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\TransferItem as TransferItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class TransferItem extends Model implements TransferItemContract
{
    protected $table = 'fc_transfer_items';

    protected $fillable = [
        'transfer_id',
        'stock_request_item_id',
        'product_id',
        'sku',
        'name',
        'qty_requested',
        'qty_picked',
        'qty_dispatched',
        'qty_received',
        'qty_damaged',
        'qty_written_off',
        'remarks',
    ];

    protected $casts = [
        'qty_requested' => 'integer',
        'qty_picked' => 'integer',
        'qty_dispatched' => 'integer',
        'qty_received' => 'integer',
        'qty_damaged' => 'integer',
        'qty_written_off' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(TransferProxy::modelClass(), 'transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function stockRequestItem(): BelongsTo
    {
        return $this->belongsTo(StockRequestItemProxy::modelClass(), 'stock_request_item_id');
    }

    /**
     * Units dispatched but not yet accounted for at the destination.
     */
    public function inTransitQty(): int
    {
        return max(0, $this->qty_dispatched - $this->qty_received - $this->qty_damaged - $this->qty_written_off);
    }
}
