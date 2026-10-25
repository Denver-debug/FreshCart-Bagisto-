<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PriceChangeBatch as PriceChangeBatchContract;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\AdminProxy;

class PriceChangeBatch extends Model implements PriceChangeBatchContract
{
    use HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_APPROVED = 'approved';

    const STATUS_APPLIED = 'applied';

    const STATUS_PARTIALLY_APPLIED = 'partially_applied';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    protected $table = 'fc_price_change_batches';

    protected $fillable = [
        'reference',
        'name',
        'requested_by',
        'status',
        'effective_at',
        'approval_level_required',
        'approvals_received',
        'submitted_at',
        'approved_at',
        'applied_at',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PriceChangeItemProxy::modelClass(), 'price_change_batch_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }
}
