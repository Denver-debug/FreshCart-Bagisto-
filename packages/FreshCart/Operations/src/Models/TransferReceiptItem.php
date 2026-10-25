<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\TransferReceiptItem as TransferReceiptItemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferReceiptItem extends Model implements TransferReceiptItemContract
{
    public $timestamps = false;

    protected $table = 'fc_transfer_receipt_items';

    protected $fillable = [
        'transfer_receipt_id',
        'transfer_item_id',
        'qty_received',
        'qty_damaged',
        'remarks',
    ];

    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(TransferItemProxy::modelClass(), 'transfer_item_id');
    }
}
