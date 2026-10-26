<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\TransferReceipt as TransferReceiptContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\AdminProxy;

class TransferReceipt extends Model implements TransferReceiptContract
{
    protected $table = 'fc_transfer_receipts';

    protected $fillable = [
        'transfer_id',
        'received_by',
        'receiver_name',
        'received_at',
        'remarks',
        'idempotency_key',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransferReceiptItemProxy::modelClass(), 'transfer_receipt_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(TransferProxy::modelClass(), 'transfer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'received_by');
    }

    public function attachments()
    {
        return $this->morphMany(AttachmentProxy::modelClass(), 'attachable');
    }
}
