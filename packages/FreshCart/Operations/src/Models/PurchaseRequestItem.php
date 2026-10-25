<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PurchaseRequestItem as PurchaseRequestItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Product\Models\ProductProxy;

class PurchaseRequestItem extends Model implements PurchaseRequestItemContract
{
    protected $table = 'fc_purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'sku',
        'name',
        'qty',
        'estimated_unit_cost',
        'preferred_supplier_id',
        'notes',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestProxy::modelClass(), 'purchase_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProxy::modelClass(), 'preferred_supplier_id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemProxy::modelClass(), 'purchase_request_item_id');
    }
}
