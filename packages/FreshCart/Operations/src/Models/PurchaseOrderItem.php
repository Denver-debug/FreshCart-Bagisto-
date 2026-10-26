<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PurchaseOrderItem as PurchaseOrderItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

class PurchaseOrderItem extends Model implements PurchaseOrderItemContract
{
    protected $table = 'fc_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'purchase_request_item_id',
        'product_id',
        'sku',
        'name',
        'qty_ordered',
        'qty_received',
        'qty_rejected',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'qty_ordered' => 'integer',
        'qty_received' => 'integer',
        'qty_rejected' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderProxy::modelClass(), 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    /**
     * Units still expected from the supplier. Rejected units do not count as
     * delivered, so they remain outstanding until a replacement arrives.
     */
    public function outstandingQty(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }
}
