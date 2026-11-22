<?php

namespace FreshCart\Integrations\Listeners;

use FreshCart\Integrations\Services\Outbox;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Models\SupplierReturn;

/**
 * Bagisto events and FreshCart model observers that queue Akaunting work.
 * They run inside the caller's transaction, so the outbox row is committed (or
 * rolled back) together with the invoice, receipt or return it describes.
 */
class RecordsOutbox
{
    public function __construct(protected Outbox $outbox) {}

    public function invoiceSaved($invoice): void
    {
        if (config('freshcart-integrations.flows.sales')) {
            $this->outbox->record('akaunting.sales_invoice', 'invoice', $invoice->id);
        }
    }

    public function refundSaved($refund): void
    {
        if (config('freshcart-integrations.flows.sales')) {
            $this->outbox->record('akaunting.sales_refund', 'refund', $refund->id);
        }
    }

    public function supplierSaved(Supplier $supplier): void
    {
        if (config('freshcart-integrations.flows.procurement')) {
            $this->outbox->record('akaunting.vendor', 'supplier', $supplier->id, refresh: true);
        }
    }

    public function goodsReceiptCreated(GoodsReceipt $receipt): void
    {
        if (config('freshcart-integrations.flows.procurement')) {
            $this->outbox->record('akaunting.purchase_bill', 'goods_receipt', $receipt->id);
        }
    }

    public function supplierReturnUpdated(SupplierReturn $return): void
    {
        if (
            config('freshcart-integrations.flows.procurement')
            && $return->wasChanged('status')
            && $return->status === SupplierReturn::STATUS_RESOLVED
            && (float) $return->credit_amount > 0
        ) {
            $this->outbox->record('akaunting.supplier_credit', 'supplier_return', $return->id);
        }
    }
}
