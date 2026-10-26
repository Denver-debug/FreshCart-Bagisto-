<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Transfer as TransferContract;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Inventory\Models\InventorySourceProxy;
use Webkul\User\Models\AdminProxy;

class Transfer extends Model implements TransferContract
{
    use HasApprovalTrail;

    const TYPE_REPLENISHMENT = 'replenishment';

    const TYPE_INTER_BRANCH = 'inter_branch';

    const TYPE_TEMPORARY = 'temporary';

    const TYPE_EMERGENCY = 'emergency';

    const TYPES = [self::TYPE_REPLENISHMENT, self::TYPE_INTER_BRANCH, self::TYPE_TEMPORARY, self::TYPE_EMERGENCY];

    const STATUS_DRAFT = 'draft';

    const STATUS_PENDING_APPROVAL = 'pending_approval';

    const STATUS_APPROVED = 'approved';

    const STATUS_PICKING = 'picking';

    const STATUS_IN_TRANSIT = 'in_transit';

    const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    const STATUS_RECEIVED = 'received';

    const STATUS_CLOSED_WITH_DISCREPANCY = 'closed_with_discrepancy';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const RECEIVABLE_STATUSES = [self::STATUS_IN_TRANSIT, self::STATUS_PARTIALLY_RECEIVED];

    protected $table = 'fc_transfers';

    protected $fillable = [
        'reference',
        'type',
        'stock_request_id',
        'return_of_transfer_id',
        'source_inventory_source_id',
        'destination_inventory_source_id',
        'status',
        'priority',
        'reason',
        'requested_by',
        'approval_required',
        'approval_level_required',
        'approvals_received',
        'submitted_at',
        'approved_at',
        'picked_by',
        'picking_started_at',
        'packed_at',
        'scheduled_dispatch_at',
        'expected_arrival_at',
        'assigned_staff_id',
        'driver_name',
        'vehicle',
        'dispatched_by',
        'dispatched_at',
        'dispatch_idempotency_key',
        'received_at',
        'expected_return_date',
        'has_discrepancy',
        'notes',
    ];

    protected $casts = [
        'approval_required' => 'boolean',
        'has_discrepancy' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'picking_started_at' => 'datetime',
        'packed_at' => 'datetime',
        'scheduled_dispatch_at' => 'datetime',
        'expected_arrival_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
        'expected_return_date' => 'date',
        'overdue_notified_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransferItemProxy::modelClass(), 'transfer_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(TransferReceiptProxy::modelClass(), 'transfer_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventorySourceProxy::modelClass(), 'source_inventory_source_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventorySourceProxy::modelClass(), 'destination_inventory_source_id');
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequestProxy::modelClass(), 'stock_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'assigned_staff_id');
    }

    /**
     * Transfers touch two locations; a user sees it if either end is theirs.
     */
    public function scopeForLocations(Builder $query, ?array $locationIds): Builder
    {
        if ($locationIds === null) {
            return $query;
        }

        return $query->where(function ($query) use ($locationIds) {
            $query->whereIn('source_inventory_source_id', $locationIds)
                ->orWhereIn('destination_inventory_source_id', $locationIds);
        });
    }

    public function isTemporary(): bool
    {
        return $this->type === self::TYPE_TEMPORARY;
    }
}
