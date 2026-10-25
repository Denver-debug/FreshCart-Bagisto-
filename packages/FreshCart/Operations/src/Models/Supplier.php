<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Supplier as SupplierContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model implements SupplierContract
{
    use SoftDeletes;

    protected $table = 'fc_suppliers';

    protected $fillable = [
        'code',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'payment_terms',
        'lead_time_days',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrderProxy::modelClass(), 'supplier_id');
    }
}
