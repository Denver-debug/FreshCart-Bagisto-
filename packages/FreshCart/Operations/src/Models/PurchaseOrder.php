<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PurchaseOrder as PurchaseOrderContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\AdminProxy;

class PurchaseOrder extends Model implements PurchaseOrderContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_ISSUED = 'issued';

    const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    const STATUS_RECEIVED = 'received';

    const STATUS_CLOSED = 'closed';

    const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses in which goods may still be received.
     */
    const RECEIVABLE_STATUSES = [self::STATUS_ISSUED, self::STATUS_PARTIALLY_RECEIVED];

    protected $table = 'fc_purchase_orders';

    protected $fillable = [
        'reference',
        'supplier_id',
        'inventory_source_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'currency',
        'subtotal',
        'notes',
        'created_by',
        'issued_at',
        'closed_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'issued_at' => 'datetime',
        'closed_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemProxy::modelClass(), 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProxy::modelClass(), 'supplier_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'created_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceiptProxy::modelClass(), 'purchase_order_id');
    }

    public function requesterId(): ?int
    {
        return $this->created_by;
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, self::RECEIVABLE_STATUSES, true);
    }
}
