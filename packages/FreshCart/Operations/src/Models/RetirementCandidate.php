<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\RetirementCandidate as RetirementCandidateContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;

/**
 * System-detected item for human review. Never disposes stock on its own.
 */
class RetirementCandidate extends Model implements RetirementCandidateContract
{
    use BelongsToLocation;

    const STATUS_OPEN = 'open';

    const STATUS_NOMINATED = 'nominated';

    const STATUS_DISMISSED = 'dismissed';

    const STATUS_RESOLVED = 'resolved';

    const CRITERIA = ['expired', 'near_expiry', 'slow_moving', 'held_stock'];

    protected $table = 'fc_retirement_candidates';

    protected $fillable = [
        'fingerprint',
        'product_id',
        'inventory_source_id',
        'lot_id',
        'criterion',
        'qty',
        'estimated_value',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'disposal_request_id',
        'detected_at',
    ];

    protected $casts = [
        'details' => 'array',
        'detected_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLotProxy::modelClass(), 'lot_id');
    }
}
