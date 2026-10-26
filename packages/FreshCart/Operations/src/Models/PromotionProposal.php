<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\PromotionProposal as PromotionProposalContract;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CatalogRule\Models\CatalogRuleProxy;
use Webkul\User\Models\AdminProxy;

/**
 * Marketing proposal; on approval it materialises as a native Bagisto catalog rule.
 */
class PromotionProposal extends Model implements PromotionProposalContract
{
    use HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_SCHEDULED = 'scheduled';

    const STATUS_ACTIVE = 'active';

    const STATUS_EXPIRED = 'expired';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const DISCOUNT_PERCENT = 'by_percent';

    const DISCOUNT_FIXED = 'by_fixed';

    protected $table = 'fc_promotion_proposals';

    protected $fillable = [
        'reference',
        'name',
        'description',
        'department',
        'requested_by',
        'discount_type',
        'discount_amount',
        'starts_on',
        'ends_on',
        'product_ids',
        'category_ids',
        'status',
        'approval_level_required',
        'approvals_received',
        'catalog_rule_id',
        'submitted_at',
        'approved_at',
        'activated_at',
        'expired_at',
        'expiry_warned_at',
    ];

    protected $casts = [
        'product_ids' => 'array',
        'category_ids' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'expiry_warned_at' => 'datetime',
    ];

    public function catalogRule(): BelongsTo
    {
        return $this->belongsTo(CatalogRuleProxy::modelClass(), 'catalog_rule_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }
}
