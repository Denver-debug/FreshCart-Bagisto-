<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\GoodsReceiptItem;
use FreshCart\Operations\Models\Incident;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockLot;
use FreshCart\Operations\Models\SupplierReturn;
use FreshCart\Operations\Support\ProductCatalog;
use FreshCart\Operations\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Module D — damage, expiry and return reports; supplier returns (P6, P11).
 *
 * Reporting physically isolates the units at once (sellable → quarantine) so they
 * cannot be sold while awaiting inspection. Nothing is deleted: damaged/expired
 * units only leave stock through an approved, posted adjustment.
 */
class QualityService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected ReferenceService $references,
        protected StockLedgerService $stockLedger,
        protected AdjustmentService $adjustments,
        protected NotificationService $notifications,
        protected AttachmentService $attachments,
        protected ProductCatalog $productCatalog,
        protected Settings $settings,
    ) {}

    public function report(array $data, array $photos = []): Incident
    {
        $product = $this->productCatalog->stockable((int) $data['product_id']);

        $incident = DB::transaction(function () use ($data, $photos, $product) {
            $fromStock = $data['incident_type'] !== 'customer_return';

            $lot = ! empty($data['batch_no'])
                ? StockLot::where('product_id', $product->id)
                    ->where('inventory_source_id', $data['inventory_source_id'])
                    ->where('batch_no', $data['batch_no'])
                    ->orderByDesc('qty_on_hand')
                    ->first()
                : null;

            $incident = Incident::create([
                'reference' => $this->references->next('INC'),
                'inventory_source_id' => $data['inventory_source_id'],
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name ?? $product->sku,
                'lot_id' => $lot?->id,
                'batch_no' => $data['batch_no'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? $lot?->expiry_date,
                'qty' => (int) $data['qty'],
                'incident_type' => $data['incident_type'],
                'from_stock' => $fromStock,
                'description' => $data['description'] ?? null,
                'status' => Incident::STATUS_QUARANTINED,
                'reported_by' => auth()->guard('admin')->id(),
                'supplier_id' => $data['supplier_id'] ?? null,
            ]);

            $context = [
                'reference' => $incident,
                'reason' => trans('operations::app.incidents.types.'.$incident->incident_type),
                'type' => 'quarantine',
            ];

            if ($fromStock) {
                $lotHasStock = $lot && $lot->qty_on_hand >= $incident->qty;

                $this->stockLedger->hold($product->id, $incident->inventory_source_id, $incident->qty, StockHold::BUCKET_QUARANTINE, $context + [
                    'lot_id' => $lotHasStock ? $lot->id : null,
                ]);
            } else {
                $this->stockLedger->receive($product->id, $incident->inventory_source_id, $incident->qty, $context + [
                    'type' => 'customer_return',
                    'batch_no' => $incident->batch_no,
                    'expiry_date' => $incident->expiry_date?->toDateString(),
                ], StockHold::BUCKET_QUARANTINE);
            }

            foreach ($photos as $photo) {
                $this->attachments->store($incident, $photo, 'photo');
            }

            $this->approvals->record($incident, 'quarantined', null, Incident::STATUS_QUARANTINED, $incident->description);

            return $incident;
        });

        $this->notifications->notifyPermission(
            'operations.incidents.inspect',
            'incident_reported',
            trans('operations::app.notifications.incident-reported', [
                'type' => trans('operations::app.incidents.types.'.$incident->incident_type),
                'qty' => $incident->qty,
                'sku' => $incident->sku,
                'location' => $incident->location?->name,
                'reference' => $incident->reference,
            ]),
            null,
            route('admin.operations.incidents.view', $incident->id),
            $incident->inventory_source_id,
            [$incident->reported_by],
        );

        return $incident;
    }

    public function inspect(Incident $incident, array $checklist, ?string $remarks): Incident
    {
        return DB::transaction(function () use ($incident, $checklist, $remarks) {
            $incident = $this->approvals->lock($incident);

            $this->approvals->assertStatus($incident, [Incident::STATUS_QUARANTINED, Incident::STATUS_INSPECTED]);

            $answers = collect(Incident::CHECKLIST)->mapWithKeys(fn ($key) => [$key => (bool) ($checklist[$key] ?? false)])->all();

            $from = $incident->status;

            $incident->update([
                'checklist' => $answers,
                'inspection_remarks' => $remarks,
                'inspected_by' => auth()->guard('admin')->id(),
                'inspected_at' => now(),
                'status' => Incident::STATUS_INSPECTED,
            ]);

            $this->approvals->record($incident, 'inspected', $from, Incident::STATUS_INSPECTED, $remarks);

            return $incident;
        });
    }

    /**
     * Supervisor disposition. Saleable units go straight back on sale; the rest
     * are reclassified and routed to a supplier return or an adjustment that
     * needs approval before any stock is written off.
     */
    public function classify(Incident $incident, string $classification, ?int $supplierId, ?string $remarks): Incident
    {
        return DB::transaction(function () use ($incident, $classification, $supplierId, $remarks) {
            $incident = $this->approvals->lock($incident);

            $this->approvals->assertStatus($incident, [Incident::STATUS_INSPECTED]);

            if ($incident->reported_by === auth()->guard('admin')->id() && ! $this->settings->allowSelfApproval()) {
                throw OperationsException::trans('self-approval');
            }

            $context = ['reference' => $incident, 'reason' => $remarks, 'lot_id' => null];

            $updates = [
                'classification' => $classification,
                'classified_by' => auth()->guard('admin')->id(),
                'classified_at' => now(),
                'status' => Incident::STATUS_CLASSIFIED,
            ];

            switch ($classification) {
                case Incident::CLASS_SALEABLE:
                    $this->stockLedger->release($incident->product_id, $incident->inventory_source_id, $incident->qty, StockHold::BUCKET_QUARANTINE, $context);

                    $updates['status'] = Incident::STATUS_CLOSED;

                    break;

                case Incident::CLASS_RETURN_TO_SUPPLIER:
                    if (! $supplierId) {
                        throw OperationsException::trans('classification-requires-supplier');
                    }

                    $this->stockLedger->moveHold($incident->product_id, $incident->inventory_source_id, $incident->qty, StockHold::BUCKET_QUARANTINE, StockHold::BUCKET_SUPPLIER_RETURN, $context);

                    $return = $this->createSupplierReturn($supplierId, $incident->inventory_source_id, [[
                        'product_id' => $incident->product_id,
                        'sku' => $incident->sku,
                        'name' => $incident->name,
                        'lot_id' => $incident->lot_id,
                        'qty' => $incident->qty,
                        'origin' => $incident,
                    ]], trans('operations::app.supplier-returns.from-incident', ['reference' => $incident->reference]));

                    $updates['supplier_id'] = $supplierId;
                    $updates['supplier_return_id'] = $return->id;

                    break;

                default:
                    $bucket = $classification === Incident::CLASS_EXPIRED ? StockHold::BUCKET_EXPIRED : StockHold::BUCKET_DAMAGED;

                    $this->stockLedger->moveHold($incident->product_id, $incident->inventory_source_id, $incident->qty, StockHold::BUCKET_QUARANTINE, $bucket, $context);

                    $adjustment = $this->adjustments->create([
                        'inventory_source_id' => $incident->inventory_source_id,
                        'direction' => Adjustment::DIRECTION_OUT,
                        'source_bucket' => $bucket,
                        'reason_code' => $classification,
                        'reason' => $incident->reference.($remarks ? ' — '.$remarks : ''),
                        'origin' => $incident,
                    ], [[
                        'product_id' => $incident->product_id,
                        'qty' => $incident->qty,
                        'lot_id' => $incident->lot_id,
                    ]]);

                    $this->adjustments->submit($adjustment);

                    $updates['adjustment_id'] = $adjustment->id;
            }

            $incident->update($updates);

            $this->approvals->record($incident, 'classified', Incident::STATUS_INSPECTED, $incident->status, trim(trans('operations::app.incidents.classifications.'.$classification).' '.$remarks));

            return $incident;
        });
    }

    /**
     * @param  array  $items  [{product_id, sku, name, lot_id, qty, origin (Model)}]
     */
    public function createSupplierReturn(int $supplierId, int $sourceId, array $items, ?string $notes): SupplierReturn
    {
        $return = SupplierReturn::create([
            'reference' => $this->references->next('SRT'),
            'supplier_id' => $supplierId,
            'inventory_source_id' => $sourceId,
            'status' => SupplierReturn::STATUS_PENDING,
            'created_by' => auth()->guard('admin')->id(),
            'notes' => $notes,
        ]);

        foreach ($items as $item) {
            $unitCost = ($item['lot_id'] ?? null)
                ? StockLot::whereKey($item['lot_id'])->value('unit_cost')
                : null;

            $return->items()->create([
                'product_id' => $item['product_id'],
                'sku' => $item['sku'],
                'name' => $item['name'],
                'lot_id' => $item['lot_id'] ?? null,
                'qty' => $item['qty'],
                'unit_cost' => $unitCost ?? $this->productCatalog->cost($item['product_id']) ?? 0,
                'origin_type' => $item['origin']->getMorphClass(),
                'origin_id' => $item['origin']->getKey(),
            ]);
        }

        $this->approvals->record($return, 'created', null, SupplierReturn::STATUS_PENDING, $notes);

        return $return;
    }

    /**
     * Goods physically leave for the supplier. Units held for return are written off;
     * units rejected at receipt never entered stock and need no stock movement.
     */
    public function dispatchSupplierReturn(SupplierReturn $return): SupplierReturn
    {
        return DB::transaction(function () use ($return) {
            $return = $this->approvals->lock($return);

            $this->approvals->assertStatus($return, [SupplierReturn::STATUS_PENDING]);

            foreach ($return->items as $item) {
                if ($this->isInStock($item)) {
                    $this->stockLedger->writeOff($item->product_id, $return->inventory_source_id, $item->qty, StockHold::BUCKET_SUPPLIER_RETURN, [
                        'reference' => $return,
                        'type' => 'supplier_return',
                        'lot_id' => $item->lot_id,
                        'unit_cost' => (float) $item->unit_cost,
                    ]);
                }
            }

            $return->update(['status' => SupplierReturn::STATUS_DISPATCHED, 'dispatched_at' => now()]);

            $this->approvals->record($return, 'dispatched', SupplierReturn::STATUS_PENDING, SupplierReturn::STATUS_DISPATCHED);

            return $return;
        });
    }

    /**
     * Record replacement units and/or a credit note from the supplier.
     *
     * @param  array<int, int>  $replaced  supplier return item id => qty
     */
    public function resolveSupplierReturn(SupplierReturn $return, array $replaced, ?float $credit, ?string $notes): SupplierReturn
    {
        return DB::transaction(function () use ($return, $replaced, $credit, $notes) {
            $return = $this->approvals->lock($return);

            $this->approvals->assertStatus($return, [SupplierReturn::STATUS_DISPATCHED]);

            foreach ($return->items as $item) {
                $qty = (int) ($replaced[$item->id] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $item->qty) {
                    throw OperationsException::trans('replacement-exceeds', ['sku' => $item->sku]);
                }

                // Replacements for goods rejected at receipt arrive through the PO, whose
                // outstanding quantity still includes them; only stock returns are replaced here.
                if (! $this->isInStock($item)) {
                    continue;
                }

                $this->stockLedger->receive($item->product_id, $return->inventory_source_id, $qty, [
                    'reference' => $return,
                    'type' => 'supplier_replacement',
                    'unit_cost' => (float) $item->unit_cost,
                ]);

                $item->update(['qty_replaced' => $qty]);
            }

            $return->update([
                'status' => SupplierReturn::STATUS_RESOLVED,
                'resolved_at' => now(),
                'credit_amount' => $credit,
                'notes' => trim($return->notes."\n".$notes),
            ]);

            $this->approvals->record($return, 'resolved', SupplierReturn::STATUS_DISPATCHED, SupplierReturn::STATUS_RESOLVED, $notes);

            return $return;
        });
    }

    /**
     * Cancel before shipping: held units go back to quarantine for a new decision.
     */
    public function cancelSupplierReturn(SupplierReturn $return, ?string $remarks): SupplierReturn
    {
        return DB::transaction(function () use ($return, $remarks) {
            $return = $this->approvals->lock($return);

            $this->approvals->assertStatus($return, [SupplierReturn::STATUS_PENDING]);

            foreach ($return->items as $item) {
                if ($this->isInStock($item)) {
                    $this->stockLedger->moveHold($item->product_id, $return->inventory_source_id, $item->qty, StockHold::BUCKET_SUPPLIER_RETURN, StockHold::BUCKET_QUARANTINE, [
                        'reference' => $return,
                        'reason' => $remarks,
                        'lot_id' => $item->lot_id,
                    ]);
                }
            }

            $return->update(['status' => SupplierReturn::STATUS_CANCELLED]);

            $this->approvals->record($return, 'cancelled', SupplierReturn::STATUS_PENDING, SupplierReturn::STATUS_CANCELLED, $remarks);

            return $return;
        });
    }

    /**
     * Whether the return line's units are physically held at the location
     * (true for incident/disposal returns, false for receipt rejections).
     */
    public function isInStock($item): bool
    {
        return $item->origin_type !== (new GoodsReceiptItem)->getMorphClass();
    }
}
