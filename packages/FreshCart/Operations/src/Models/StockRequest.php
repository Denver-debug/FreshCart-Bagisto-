<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\StockRequest as StockRequestContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Inventory\Models\InventorySourceProxy;
use Webkul\User\Models\AdminProxy;

/**
 * Branch replenishment request. `inventory_source_id` is the requesting branch.
 */
class StockRequest extends Model implements StockRequestContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_PARTIALLY_ALLOCATED = 'partially_allocated';

    const STATUS_ALLOCATED = 'allocated';

    const STATUS_FULFILLED = 'fulfilled';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const ALLOCATABLE_STATUSES = [self::STATUS_SUBMITTED, self::STATUS_PARTIALLY_ALLOCATED];

    protected $table = 'fc_stock_requests';

    protected $fillable = [
        'reference',
        'inventory_source_id',
        'source_inventory_source_id',
        'requested_by',
        'priority',
        'needed_by',
        'notes',
        'status',
        'submitted_at',
        'allocated_by',
        'allocated_at',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'submitted_at' => 'datetime',
        'allocated_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItemProxy::modelClass(), 'stock_request_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(TransferProxy::modelClass(), 'stock_request_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventorySourceProxy::modelClass(), 'source_inventory_source_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }
}
