<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\SupplierReturnItem as SupplierReturnItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupplierReturnItem extends Model implements SupplierReturnItemContract
{
    protected $table = 'fc_supplier_return_items';

    protected $fillable = [
        'supplier_return_id',
        'product_id',
        'sku',
        'name',
        'lot_id',
        'qty',
        'qty_replaced',
        'unit_cost',
        'origin_type',
        'origin_id',
    ];

    protected $casts = [
        'qty' => 'integer',
        'qty_replaced' => 'integer',
    ];

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturnProxy::modelClass(), 'supplier_return_id');
    }

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }
}
