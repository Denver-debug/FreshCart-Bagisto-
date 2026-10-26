<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PurchaseRequest as PurchaseRequestContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\AdminProxy;

class PurchaseRequest extends Model implements PurchaseRequestContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CONVERTED = 'converted';

    const STATUS_CANCELLED = 'cancelled';

    const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $table = 'fc_purchase_requests';

    protected $fillable = [
        'reference',
        'inventory_source_id',
        'requested_by',
        'priority',
        'needed_by',
        'justification',
        'status',
        'approval_level_required',
        'approvals_received',
        'estimated_total',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'converted_at',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItemProxy::modelClass(), 'purchase_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
