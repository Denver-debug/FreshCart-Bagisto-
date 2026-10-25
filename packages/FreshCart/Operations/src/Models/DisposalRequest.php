<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\DisposalRequest as DisposalRequestContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\AdminProxy;

class DisposalRequest extends Model implements DisposalRequestContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_INSPECTED = 'inspected';

    const STATUS_VALUED = 'valued';

    const STATUS_APPROVED = 'approved';

    const STATUS_QUARANTINED = 'quarantined';

    const STATUS_DISPOSED = 'disposed';

    const STATUS_RETURNED_TO_SUPPLIER = 'returned_to_supplier';

    const STATUS_COMPLETED = 'completed';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const REASONS = ['expired', 'near_expiry', 'obsolete', 'recalled', 'slow_moving', 'damaged', 'unsellable'];

    const DISPOSITION_DISPOSE = 'dispose';

    const DISPOSITION_RETURN = 'return_to_supplier';

    protected $table = 'fc_disposal_requests';

    protected $fillable = [
        'reference',
        'inventory_source_id',
        'status',
        'reason',
        'requested_by',
        'submitted_at',
        'inspected_by',
        'inspected_at',
        'inspection_remarks',
        'valued_by',
        'valued_at',
        'valuation_remarks',
        'total_value',
        'approval_level_required',
        'approvals_received',
        'approved_at',
        'segregated_at',
        'completed_by',
        'completed_at',
        'disposal_method',
        'completion_remarks',
        'supplier_id',
        'supplier_return_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'inspected_at' => 'datetime',
        'valued_at' => 'datetime',
        'approved_at' => 'datetime',
        'segregated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DisposalItemProxy::modelClass(), 'disposal_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'requested_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProxy::modelClass(), 'supplier_id')->withTrashed();
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturnProxy::modelClass(), 'supplier_return_id');
    }
}
