<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\SupplierReturn as SupplierReturnContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model implements SupplierReturnContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_PENDING = 'pending';

    const STATUS_DISPATCHED = 'dispatched';

    const STATUS_RESOLVED = 'resolved';

    const STATUS_CANCELLED = 'cancelled';

    protected $table = 'fc_supplier_returns';

    protected $fillable = [
        'reference',
        'supplier_id',
        'inventory_source_id',
        'status',
        'created_by',
        'dispatched_at',
        'resolved_at',
        'credit_amount',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItemProxy::modelClass(), 'supplier_return_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProxy::modelClass(), 'supplier_id')->withTrashed();
    }

    public function requesterId(): ?int
    {
        return $this->created_by;
    }
}
