<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\GoodsReceiptItem as GoodsReceiptItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model implements GoodsReceiptItemContract
{
    const INSPECTION_PASSED = 'passed';

    const INSPECTION_PARTIAL = 'partial';

    const INSPECTION_FAILED = 'failed';

    protected $table = 'fc_goods_receipt_items';

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'sku',
        'qty_received',
        'qty_accepted',
        'qty_rejected',
        'batch_no',
        'expiry_date',
        'storage_location',
        'inspection_result',
        'discrepancy_note',
        'over_receipt_reason',
        'over_receipt_approved_by',
        'lot_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptProxy::modelClass(), 'goods_receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItemProxy::modelClass(), 'purchase_order_item_id');
    }
}
