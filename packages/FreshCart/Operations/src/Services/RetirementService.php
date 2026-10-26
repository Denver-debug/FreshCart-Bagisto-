<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\DisposalItem;
use FreshCart\Operations\Models\DisposalRequest;
use FreshCart\Operations\Models\RetirementCandidate;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockLot;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Module F — product retirement and disposal (P10, P11).
 *
 * Candidate detection only builds a review list from transparent criteria:
 *   expired / near_expiry : batches on hand whose expiry falls within the warning window
 *   held_stock            : units sitting in damaged or expired holds
 *   slow_moving           : sellable stock whose shipped units over the look-back
 *                           window are at or below the configured maximum
 * Disposal itself always runs Nominate → Inspect → Finance valuation → Approval
 * → Segregate (quarantine) → Complete with evidence, and only then touches stock.
 */
class RetirementService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected StockLedgerService $stockLedger,
        protected QualityService $quality,
        protected NotificationService $notifications,
        protected AttachmentService $attachments,
        protected ProductCatalog $productCatalog,
        protected Settings $settings,
    ) {}

    /**
     * Refresh the review list. Returns the number of open candidates.
     */
    public function detectCandidates(): int
    {
        $now = now();
        $seen = [];

        $warningDate = $now->copy()->addDays($this->settings->expiryWarningDays())->toDateString();

        $lots = StockLot::where('qty_on_hand', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $warningDate)
            ->get();

        foreach ($lots as $lot) {
            $criterion = $lot->expiry_date->lt($now->copy()->startOfDay()) ? 'expired' : 'near_expiry';

            $seen[] = $this->upsertCandidate($criterion, $lot->product_id, $lot->inventory_source_id, $lot->id, $lot->qty_on_hand, $lot->unit_cost, [
                'batch_no' => $lot->batch_no,
                'expiry_date' => $lot->expiry_date->toDateString(),
                'days_to_expiry' => (int) $now->copy()->startOfDay()->diffInDays($lot->expiry_date, false),
            ]);
        }

        $holds = DB::table('fc_stock_holds')
            ->whereIn('bucket', [StockHold::BUCKET_DAMAGED, StockHold::BUCKET_EXPIRED])
            ->where('qty', '>', 0)
            ->selectRaw('product_id, inventory_source_id, bucket, SUM(qty) as qty, MIN(created_at) as since')
            ->groupBy('product_id', 'inventory_source_id', 'bucket')
            ->get();

        foreach ($holds as $hold) {
            $seen[] = $this->upsertCandidate('held_stock', $hold->product_id, $hold->inventory_source_id, null, (int) $hold->qty, null, [
                'bucket' => $hold->bucket,
                'held_since' => $hold->since,
            ], $hold->bucket);
        }

        $days = $this->settings->slowMovingDays();
        $maxUnits = $this->settings->slowMovingMaxUnits();
        $since = $now->copy()->subDays($days);

        $sold = DB::table('shipment_items')
            ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->where('shipments.created_at', '>=', $since)
            ->selectRaw('shipment_items.product_id, shipments.inventory_source_id, SUM(shipment_items.qty) as qty')
            ->groupBy('shipment_items.product_id', 'shipments.inventory_source_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.':'.$row->inventory_source_id);

        $stocked = DB::table('product_inventories')
            ->join('products', 'products.id', '=', 'product_inventories.product_id')
            ->whereIn('products.type', ProductCatalog::STOCKABLE_TYPES)
            ->where('product_inventories.vendor_id', 0)
            ->where('product_inventories.qty', '>', 0)
            ->where('products.created_at', '<', $since)
            ->select('product_inventories.product_id', 'product_inventories.inventory_source_id', 'product_inventories.qty')
            ->get();

        foreach ($stocked as $row) {
            $units = (int) ($sold[$row->product_id.':'.$row->inventory_source_id]->qty ?? 0);

            if ($units > $maxUnits) {
                continue;
            }

            $seen[] = $this->upsertCandidate('slow_moving', $row->product_id, $row->inventory_source_id, null, (int) $row->qty, null, [
                'units_sold' => $units,
                'window_days' => $days,
            ]);
        }

        // Open candidates whose condition no longer holds drop off the list.
        RetirementCandidate::where('status', RetirementCandidate::STATUS_OPEN)
            ->whereNotIn('id', array_filter($seen))
            ->update(['status' => RetirementCandidate::STATUS_RESOLVED, 'updated_at' => now()]);

        return RetirementCandidate::where('status', RetirementCandidate::STATUS_OPEN)->count();
    }

    public function dismiss(RetirementCandidate $candidate, ?string $remarks): RetirementCandidate
    {
        $candidate->update([
            'status' => RetirementCandidate::STATUS_DISMISSED,
            'reviewed_by' => auth()->guard('admin')->id(),
            'reviewed_at' => now(),
            'details' => array_merge($candidate->details ?? [], ['dismissal_remarks' => $remarks]),
        ]);

        return $candidate;
    }

    /**
     * Turn reviewed candidates (one location) into a disposal request draft.
     */
    public function nominate(array $candidateIds, string $reason): DisposalRequest
    {
        return DB::transaction(function () use ($candidateIds, $reason) {
            $candidates = RetirementCandidate::whereIn('id', $candidateIds)
                ->where('status', RetirementCandidate::STATUS_OPEN)
                ->lockForUpdate()
                ->get();

            if ($candidates->isEmpty()) {
                throw OperationsException::trans('no-items');
            }

            if ($candidates->pluck('inventory_source_id')->unique()->count() > 1) {
                throw OperationsException::trans('single-location');
            }

            $items = $candidates->map(fn ($candidate) => [
                'product_id' => $candidate->product_id,
                'lot_id' => $candidate->lot_id,
                'qty' => $candidate->qty,
                'source_bucket' => $candidate->details['bucket'] ?? DisposalItem::SOURCE_SELLABLE,
                'reason' => match ($candidate->criterion) {
                    'held_stock' => ($candidate->details['bucket'] ?? '') === StockHold::BUCKET_EXPIRED ? 'expired' : 'damaged',
                    default => $candidate->criterion,
                },
                'disposition' => DisposalRequest::DISPOSITION_DISPOSE,
            ])->all();

            $request = $this->create([
                'inventory_source_id' => $candidates->first()->inventory_source_id,
                'reason' => $reason,
            ], $items);

            RetirementCandidate::whereIn('id', $candidates->pluck('id'))->update([
                'status' => RetirementCandidate::STATUS_NOMINATED,
                'reviewed_by' => auth()->guard('admin')->id(),
                'reviewed_at' => now(),
                'disposal_request_id' => $request->id,
            ]);

            $this->approvals->record($request, 'nominated', null, DisposalRequest::STATUS_DRAFT, $candidates->count().' candidate(s)');

            return $request;
        });
    }

    /**
     * @param  array  $items  [{product_id, qty, lot_id?, source_bucket, reason, disposition}]
     */
    public function create(array $data, array $items): DisposalRequest
    {
        return DB::transaction(function () use ($data, $items) {
            $request = DisposalRequest::create([
                'reference' => $this->references->next('DSP'),
                'inventory_source_id' => $data['inventory_source_id'],
                'status' => DisposalRequest::STATUS_DRAFT,
                'reason' => $data['reason'] ?? null,
                'requested_by' => auth()->guard('admin')->id(),
                'supplier_id' => $data['supplier_id'] ?? null,
            ]);

            $total = 0;

            foreach ($items as $item) {
                $qty = (int) ($item['qty'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $product = $this->productCatalog->stockable((int) $item['product_id']);
                $lot = ! empty($item['lot_id']) ? StockLot::find($item['lot_id']) : null;
                $unitCost = (float) ($lot?->unit_cost ?? $this->productCatalog->cost($product->id) ?? 0);

                $request->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name ?? $product->sku,
                    'lot_id' => $lot?->id,
                    'batch_no' => $lot?->batch_no,
                    'expiry_date' => $lot?->expiry_date,
                    'qty' => $qty,
                    'source_bucket' => $item['source_bucket'] ?? DisposalItem::SOURCE_SELLABLE,
                    'reason' => $item['reason'],
                    'disposition' => $item['disposition'] ?? DisposalRequest::DISPOSITION_DISPOSE,
                    'unit_cost' => $unitCost,
                    'line_value' => $qty * $unitCost,
                ]);

                $total += $qty * $unitCost;
            }

            if (! $request->items()->exists()) {
                throw OperationsException::trans('no-items');
            }

            $request->update(['total_value' => $total]);

            $this->approvals->record($request, 'created', null, DisposalRequest::STATUS_DRAFT, $data['reason'] ?? null);

            return $request->fresh('items');
        });
    }

    public function submit(DisposalRequest $request): DisposalRequest
    {
        $request = $this->transition($request, [DisposalRequest::STATUS_DRAFT], DisposalRequest::STATUS_SUBMITTED, 'submitted', ['submitted_at' => now()]);

        $this->notifyStep($request, 'operations.disposals.inspect', trans('operations::app.disposals.step-inspection'));

        return $request;
    }

    public function inspect(DisposalRequest $request, string $remarks): DisposalRequest
    {
        $request = $this->transition($request, [DisposalRequest::STATUS_SUBMITTED], DisposalRequest::STATUS_INSPECTED, 'inspected', [
            'inspected_by' => auth()->guard('admin')->id(),
            'inspected_at' => now(),
            'inspection_remarks' => $remarks,
        ], $remarks);

        $this->notifyStep($request, 'operations.disposals.value', trans('operations::app.disposals.step-valuation'));

        return $request;
    }

    /**
     * Finance confirms unit costs; the request then enters the approval queue with
     * the number of approval levels set by its total value.
     *
     * @param  array<int, float>  $unitCosts  disposal item id => unit cost
     */
    public function value(DisposalRequest $request, array $unitCosts, ?string $remarks): DisposalRequest
    {
        DB::transaction(function () use ($request, $unitCosts, $remarks) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, [DisposalRequest::STATUS_INSPECTED]);

            $total = 0;

            foreach ($request->items as $item) {
                $cost = isset($unitCosts[$item->id]) ? (float) $unitCosts[$item->id] : (float) $item->unit_cost;

                $item->update(['unit_cost' => $cost, 'line_value' => $cost * $item->qty]);

                $total += $cost * $item->qty;
            }

            $request->update([
                'total_value' => $total,
                'valued_by' => auth()->guard('admin')->id(),
                'valued_at' => now(),
                'valuation_remarks' => $remarks,
            ]);

            $this->approvals->record($request, 'valued', DisposalRequest::STATUS_INSPECTED, DisposalRequest::STATUS_INSPECTED, $remarks);
        });

        $request = $request->fresh();

        return $this->approvals->submit($request, (float) $request->total_value, [DisposalRequest::STATUS_INSPECTED], DisposalRequest::STATUS_VALUED);
    }

    public function approve(DisposalRequest $request, ?string $remarks): DisposalRequest
    {
        $request = $this->approvals->approve($request, $remarks, DisposalRequest::STATUS_APPROVED, DisposalRequest::STATUS_VALUED);

        if ($request->status === DisposalRequest::STATUS_APPROVED) {
            $this->notifyStep($request, 'operations.disposals.execute', trans('operations::app.disposals.step-segregation'));
        }

        return $request;
    }

    public function reject(DisposalRequest $request, string $remarks): DisposalRequest
    {
        $request = $this->approvals->reject($request, $remarks, DisposalRequest::STATUS_REJECTED, DisposalRequest::STATUS_VALUED);

        RetirementCandidate::where('disposal_request_id', $request->id)->update(['status' => RetirementCandidate::STATUS_OPEN, 'disposal_request_id' => null]);

        return $request;
    }

    /**
     * Physically segregate approved items: sellable units move to quarantine.
     */
    public function segregate(DisposalRequest $request): DisposalRequest
    {
        return DB::transaction(function () use ($request) {
            $request = $this->approvals->lock($request);

            $this->assertApproved($request);

            $this->approvals->assertStatus($request, [DisposalRequest::STATUS_APPROVED]);

            foreach ($request->items as $item) {
                if ($item->source_bucket !== DisposalItem::SOURCE_SELLABLE) {
                    continue;
                }

                $this->stockLedger->hold($item->product_id, $request->inventory_source_id, $item->qty, StockHold::BUCKET_QUARANTINE, [
                    'reference' => $request,
                    'lot_id' => $item->lot_id && StockLot::whereKey($item->lot_id)->value('qty_on_hand') >= $item->qty ? $item->lot_id : null,
                    'reason' => trans('operations::app.disposals.segregated-reason'),
                ]);

                $item->update(['source_bucket' => StockHold::BUCKET_QUARANTINE]);
            }

            $request->update(['status' => DisposalRequest::STATUS_QUARANTINED, 'segregated_at' => now()]);

            $this->approvals->record($request, 'segregated', DisposalRequest::STATUS_APPROVED, DisposalRequest::STATUS_QUARANTINED);

            return $request;
        });
    }

    /**
     * Record the disposal with evidence; dispose lines are written off, return lines
     * are handed to a supplier return.
     */
    public function complete(DisposalRequest $request, array $data, array $evidence): DisposalRequest
    {
        if (empty($evidence)) {
            throw OperationsException::trans('evidence-required');
        }

        return DB::transaction(function () use ($request, $data, $evidence) {
            $request = $this->approvals->lock($request);

            $this->assertApproved($request);

            $this->approvals->assertStatus($request, [DisposalRequest::STATUS_QUARANTINED]);

            $returnLines = [];

            foreach ($request->items as $item) {
                if ($item->disposition === DisposalRequest::DISPOSITION_RETURN) {
                    $this->stockLedger->moveHold($item->product_id, $request->inventory_source_id, $item->qty, $item->source_bucket, StockHold::BUCKET_SUPPLIER_RETURN, [
                        'reference' => $request,
                    ]);

                    $returnLines[] = [
                        'product_id' => $item->product_id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'lot_id' => $item->lot_id,
                        'qty' => $item->qty,
                        'origin' => $item,
                    ];

                    continue;
                }

                $this->stockLedger->writeOff($item->product_id, $request->inventory_source_id, $item->qty, $item->source_bucket, [
                    'reference' => $request,
                    'type' => 'disposal',
                    'unit_cost' => (float) $item->unit_cost,
                    'reason' => trim(($data['disposal_method'] ?? '').' '.trans('operations::app.disposals.reasons.'.$item->reason)),
                ]);
            }

            $supplierReturn = null;

            if ($returnLines) {
                $supplierId = $data['supplier_id'] ?? $request->supplier_id;

                if (! $supplierId) {
                    throw OperationsException::trans('classification-requires-supplier');
                }

                $supplierReturn = $this->quality->createSupplierReturn((int) $supplierId, $request->inventory_source_id, $returnLines, trans('operations::app.supplier-returns.from-disposal', ['reference' => $request->reference]));
            }

            foreach ($evidence as $file) {
                if ($file instanceof UploadedFile) {
                    $this->attachments->store($request, $file, 'evidence');
                }
            }

            $dispositions = $request->items->pluck('disposition')->unique();

            $status = match (true) {
                $dispositions->count() > 1 => DisposalRequest::STATUS_COMPLETED,
                $dispositions->first() === DisposalRequest::DISPOSITION_RETURN => DisposalRequest::STATUS_RETURNED_TO_SUPPLIER,
                default => DisposalRequest::STATUS_DISPOSED,
            };

            $request->update([
                'status' => $status,
                'completed_by' => auth()->guard('admin')->id(),
                'completed_at' => now(),
                'disposal_method' => $data['disposal_method'] ?? null,
                'completion_remarks' => $data['completion_remarks'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? $request->supplier_id,
                'supplier_return_id' => $supplierReturn?->id,
            ]);

            RetirementCandidate::where('disposal_request_id', $request->id)->update(['status' => RetirementCandidate::STATUS_RESOLVED]);

            $this->approvals->record($request, 'completed', DisposalRequest::STATUS_QUARANTINED, $status, $data['completion_remarks'] ?? null);

            return $request;
        });
    }

    public function cancel(DisposalRequest $request, ?string $remarks): DisposalRequest
    {
        $request = $this->transition($request, [DisposalRequest::STATUS_DRAFT, DisposalRequest::STATUS_SUBMITTED, DisposalRequest::STATUS_INSPECTED], DisposalRequest::STATUS_CANCELLED, 'cancelled', [], $remarks);

        RetirementCandidate::where('disposal_request_id', $request->id)->update(['status' => RetirementCandidate::STATUS_OPEN, 'disposal_request_id' => null]);

        return $request;
    }

    protected function assertApproved(DisposalRequest $request): void
    {
        if (! $request->approved_at || ! $request->isFullyApproved()) {
            throw OperationsException::trans('disposal-not-approved');
        }
    }

    protected function upsertCandidate(string $criterion, int $productId, int $sourceId, ?int $lotId, int $qty, $unitCost, array $details, ?string $bucket = null): ?int
    {
        $fingerprint = implode(':', [$criterion, $productId, $sourceId, $lotId ?? 0, $bucket ?? '']);

        $value = $qty * (float) ($unitCost ?? $this->productCatalog->cost($productId) ?? 0);

        $candidate = RetirementCandidate::where('fingerprint', $fingerprint)->first();

        if ($candidate && in_array($candidate->status, [RetirementCandidate::STATUS_DISMISSED, RetirementCandidate::STATUS_NOMINATED], true)) {
            return null;
        }

        $candidate = RetirementCandidate::updateOrCreate(['fingerprint' => $fingerprint], [
            'product_id' => $productId,
            'inventory_source_id' => $sourceId,
            'lot_id' => $lotId,
            'criterion' => $criterion,
            'qty' => $qty,
            'estimated_value' => $value,
            'details' => $details,
            'status' => RetirementCandidate::STATUS_OPEN,
            'detected_at' => $candidate?->detected_at ?? now(),
        ]);

        return $candidate->id;
    }

    protected function transition(DisposalRequest $request, array $from, string $to, string $action, array $extra = [], ?string $remarks = null): DisposalRequest
    {
        return DB::transaction(function () use ($request, $from, $to, $action, $extra, $remarks) {
            $request = $this->approvals->lock($request);

            $this->approvals->assertStatus($request, $from);

            $previous = $request->status;

            $request->forceFill(['status' => $to] + $extra)->save();

            $this->approvals->record($request, $action, $previous, $to, $remarks);

            return $request;
        });
    }

    protected function notifyStep(DisposalRequest $request, string $permission, string $step): void
    {
        $this->notifications->notifyPermission(
            $permission,
            'disposal_step',
            trans('operations::app.notifications.disposal-pending', ['reference' => $request->reference, 'step' => $step]),
            null,
            route('admin.operations.disposals.view', $request->id),
            $request->inventory_source_id,
        );
    }
}
