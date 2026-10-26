<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\GoodsReceipt as GoodsReceiptContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\AdminProxy;

class GoodsReceipt extends Model implements GoodsReceiptContract
{
    use BelongsToLocation, HasApprovalTrail;

    protected $table = 'fc_goods_receipts';

    protected $fillable = [
        'reference',
        'purchase_order_id',
        'inventory_source_id',
        'received_by',
        'delivery_note_no',
        'received_at',
        'has_discrepancy',
        'remarks',
        'idempotency_key',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'has_discrepancy' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItemProxy::modelClass(), 'goods_receipt_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderProxy::modelClass(), 'purchase_order_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'received_by');
    }
}
