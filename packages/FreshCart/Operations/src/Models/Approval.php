<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Approval as ApprovalContract;
use FreshCart\Operations\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webkul\User\Models\AdminProxy;

/**
 * Append-only workflow decision record.
 */
class Approval extends Model implements ApprovalContract
{
    use AppendOnly;

    const UPDATED_AT = null;

    protected $table = 'fc_approvals';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'level',
        'action',
        'from_status',
        'to_status',
        'remarks',
        'admin_id',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass());
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
