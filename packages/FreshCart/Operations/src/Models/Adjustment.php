<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Adjustment as AdjustmentContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webkul\User\Models\AdminProxy;

/**
 * Controlled stock adjustment. Stock changes only when an approved adjustment is posted.
 */
class Adjustment extends Model implements AdjustmentContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_POSTED = 'posted';

    const STATUS_CANCELLED = 'cancelled';

    const DIRECTION_IN = 'in';

    const DIRECTION_OUT = 'out';

    const REASONS = [
        'damaged',
        'expired',
        'shrinkage',
        'transit_loss',
        'count_correction',
        'found_stock',
        'disposal',
        'other',
    ];

    protected $table = 'fc_adjustments';

    protected $fillable = [
        'reference',
        'inventory_source_id',
        'direction',
        'source_bucket',
        'reason_code',
        'reason',
        'status',
        'origin_type',
        'origin_id',
        'requested_by',
        'approval_level_required',
        'approvals_received',
        'total_value',
        'submitted_at',
        'approved_at',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AdjustmentItemProxy::modelClass(), 'adjustment_id');
    }

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'posted_by');
    }
}
