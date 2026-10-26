<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Incident as IncidentContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use FreshCart\Operations\Models\Concerns\HasApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\ProductProxy;
use Webkul\User\Models\AdminProxy;

/**
 * Damaged, expired or returned product report.
 */
class Incident extends Model implements IncidentContract
{
    use BelongsToLocation, HasApprovalTrail;

    const STATUS_QUARANTINED = 'quarantined';

    const STATUS_INSPECTED = 'inspected';

    const STATUS_CLASSIFIED = 'classified';

    const STATUS_CLOSED = 'closed';

    const TYPES = ['customer_return', 'damaged', 'expired', 'near_expiry', 'recall', 'other'];

    const CLASS_SALEABLE = 'saleable';

    const CLASS_RETURN_TO_SUPPLIER = 'return_to_supplier';

    const CLASS_DAMAGED = 'damaged';

    const CLASS_EXPIRED = 'expired';

    const CLASSIFICATIONS = [self::CLASS_SALEABLE, self::CLASS_RETURN_TO_SUPPLIER, self::CLASS_DAMAGED, self::CLASS_EXPIRED];

    const CHECKLIST = ['packaging_intact', 'seal_intact', 'within_expiry', 'no_contamination', 'label_legible', 'temperature_ok'];

    protected $table = 'fc_incidents';

    protected $fillable = [
        'reference',
        'inventory_source_id',
        'product_id',
        'sku',
        'name',
        'lot_id',
        'batch_no',
        'expiry_date',
        'qty',
        'incident_type',
        'from_stock',
        'description',
        'status',
        'reported_by',
        'checklist',
        'inspection_remarks',
        'inspected_by',
        'inspected_at',
        'classification',
        'classified_by',
        'classified_at',
        'supplier_id',
        'adjustment_id',
        'supplier_return_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'from_stock' => 'boolean',
        'checklist' => 'array',
        'inspected_at' => 'datetime',
        'classified_at' => 'datetime',
        'qty' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'reported_by');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'inspected_by');
    }

    public function classifier(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'classified_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProxy::modelClass(), 'supplier_id')->withTrashed();
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(AdjustmentProxy::modelClass(), 'adjustment_id');
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturnProxy::modelClass(), 'supplier_return_id');
    }

    public function requesterId(): ?int
    {
        return $this->reported_by;
    }
}
