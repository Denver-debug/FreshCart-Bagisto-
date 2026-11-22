<?php

namespace FreshCart\Integrations\Services;

use FreshCart\Integrations\Clients\AkauntingClient;
use FreshCart\Integrations\Exceptions\IntegrationException;
use FreshCart\Integrations\Models\IntegrationLink;
use FreshCart\Integrations\Models\OutboxMessage;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Models\SupplierReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Refund;

/**
 * Turns FreshCart / Frappe records into Akaunting documents and transactions.
 * Amounts use the store's base currency with rate 1, so Akaunting's company
 * currency must match Bagisto's base currency.
 *
 * Every handler is idempotent: the Akaunting id is stored in
 * fc_integration_links and a retried message never creates a duplicate.
 */
class AkauntingSync
{
    const SYSTEM = 'akaunting';

    const ACTIONS = [
        'akaunting.vendor' => 'syncVendor',
        'akaunting.sales_invoice' => 'syncSalesInvoice',
        'akaunting.sales_refund' => 'syncSalesRefund',
        'akaunting.purchase_bill' => 'syncPurchaseBill',
        'akaunting.supplier_credit' => 'syncSupplierCredit',
        'akaunting.payroll' => 'syncPayroll',
    ];

    public function __construct(protected AkauntingClient $client) {}

    public function handles(string $action): bool
    {
        return isset(self::ACTIONS[$action]);
    }

    public function handle(OutboxMessage $message): void
    {
        $this->{self::ACTIONS[$message->action]}($message);
    }

    /**
     * FreshCart supplier => Akaunting vendor (created or updated).
     */
    public function syncVendor(OutboxMessage $message): void
    {
        $supplier = Supplier::withTrashed()->findOrFail($message->subject_id);

        $this->vendorId($supplier, update: true);
    }

    /**
     * Bagisto invoice => Akaunting sales invoice, marked paid in full.
     */
    public function syncSalesInvoice(OutboxMessage $message): void
    {
        $invoice = Invoice::with(['order', 'items'])->findOrFail($message->subject_id);
        $order = $invoice->order;

        $customerId = $this->customerId(
            $order->customer_email,
            trim($order->customer_first_name.' '.$order->customer_last_name) ?: $order->customer_email,
        );

        $items = $invoice->items
            ->whereNull('parent_id')
            ->map(fn ($item) => [
                'name' => Str::limit($item->name ?: $item->sku, 250),
                'description' => $item->sku,
                'quantity' => (int) $item->qty,
                'price' => $this->money(($item->base_total - $item->base_discount_amount) / max(1, $item->qty)),
            ])
            ->values()
            ->all();

        if ((float) $invoice->base_shipping_amount > 0) {
            $items[] = ['name' => 'Shipping', 'quantity' => 1, 'price' => $this->money($invoice->base_shipping_amount)];
        }

        if ((float) $invoice->base_tax_amount > 0) {
            $items[] = ['name' => 'Tax', 'quantity' => 1, 'price' => $this->money($invoice->base_tax_amount)];
        }

        $issuedAt = $this->timestamp($invoice->created_at);

        $documentId = $this->document('sales_invoice', $invoice->id, [
            'type' => 'invoice',
            'document_number' => 'FC-INV-'.($invoice->increment_id ?: $invoice->id),
            'order_number' => (string) $order->increment_id,
            'status' => 'sent',
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt,
            'amount' => $this->money($invoice->base_grand_total),
            'currency_code' => $invoice->base_currency_code ?: $order->base_currency_code,
            'currency_rate' => 1,
            'contact_id' => $customerId,
            'contact_name' => trim($order->customer_first_name.' '.$order->customer_last_name) ?: $order->customer_email,
            'contact_email' => $order->customer_email,
            'category_id' => $this->category('sales'),
            'notes' => 'Bagisto order #'.$order->increment_id,
            'items' => $items,
        ]);

        $this->documentPayment('sales_invoice_payment', $invoice->id, $documentId, [
            'type' => 'income',
            'number' => 'FC-RCPT-'.($invoice->increment_id ?: $invoice->id),
            'account_id' => $this->account(),
            'paid_at' => $issuedAt,
            'amount' => $this->money($invoice->base_grand_total),
            'currency_code' => $invoice->base_currency_code ?: $order->base_currency_code,
            'currency_rate' => 1,
            'contact_id' => $customerId,
            'category_id' => $this->category('sales'),
            'payment_method' => $this->paymentMethod(),
            'reference' => 'Bagisto order #'.$order->increment_id,
        ]);
    }

    /**
     * Bagisto refund => Akaunting expense paid back to the customer.
     */
    public function syncSalesRefund(OutboxMessage $message): void
    {
        $refund = Refund::with('order')->findOrFail($message->subject_id);
        $order = $refund->order;

        $this->transaction('sales_refund', $refund->id, [
            'type' => 'expense',
            'number' => 'FC-RFND-'.($refund->increment_id ?: $refund->id),
            'account_id' => $this->account(),
            'paid_at' => $this->timestamp($refund->created_at),
            'amount' => $this->money($refund->base_grand_total),
            'currency_code' => $refund->base_currency_code ?: $order->base_currency_code,
            'currency_rate' => 1,
            'contact_id' => $this->customerId(
                $order->customer_email,
                trim($order->customer_first_name.' '.$order->customer_last_name) ?: $order->customer_email,
            ),
            'category_id' => $this->category('refunds'),
            'payment_method' => $this->paymentMethod(),
            'description' => 'Refund for Bagisto order #'.$order->increment_id,
            'reference' => 'Bagisto order #'.$order->increment_id,
        ]);
    }

    /**
     * Posted goods receipt => Akaunting bill for the accepted quantities at PO cost.
     */
    public function syncPurchaseBill(OutboxMessage $message): void
    {
        $receipt = GoodsReceipt::with(['items.purchaseOrderItem', 'purchaseOrder.supplier'])->findOrFail($message->subject_id);
        $order = $receipt->purchaseOrder;
        $supplier = $order->supplier;

        $items = $receipt->items
            ->filter(fn ($item) => $item->qty_accepted > 0)
            ->map(fn ($item) => [
                'name' => Str::limit($item->purchaseOrderItem?->name ?: $item->sku, 250),
                'description' => $item->sku,
                'quantity' => (int) $item->qty_accepted,
                'price' => $this->money($item->purchaseOrderItem?->unit_cost ?? 0),
            ])
            ->values()
            ->all();

        if (! $items) {
            return;
        }

        $amount = array_sum(array_map(fn ($item) => $item['quantity'] * $item['price'], $items));
        $issuedAt = Carbon::parse($receipt->received_at);

        $this->document('purchase_bill', $receipt->id, [
            'type' => 'bill',
            'document_number' => $receipt->reference,
            'order_number' => $order->reference,
            'status' => 'received',
            'issued_at' => $this->timestamp($issuedAt),
            'due_at' => $this->timestamp($issuedAt->copy()->addDays($this->termsDays($supplier->payment_terms))),
            'amount' => $this->money($amount),
            'currency_code' => $order->currency ?: core()->getBaseCurrencyCode(),
            'currency_rate' => 1,
            'contact_id' => $this->vendorId($supplier),
            'contact_name' => $supplier->name,
            'contact_email' => $supplier->email,
            'category_id' => $this->category('purchases'),
            'notes' => trim("PO {$order->reference}, delivery note {$receipt->delivery_note_no}", ', '),
            'items' => $items,
        ]);
    }

    /**
     * Resolved supplier return with a credit => Akaunting income from the vendor.
     */
    public function syncSupplierCredit(OutboxMessage $message): void
    {
        $return = SupplierReturn::with('supplier')->findOrFail($message->subject_id);

        if ((float) $return->credit_amount <= 0) {
            return;
        }

        $this->transaction('supplier_credit', $return->id, [
            'type' => 'income',
            'number' => 'FC-CR-'.$return->reference,
            'account_id' => $this->account(),
            'paid_at' => $this->timestamp($return->resolved_at ?? $return->updated_at),
            'amount' => $this->money($return->credit_amount),
            'currency_code' => core()->getBaseCurrencyCode(),
            'currency_rate' => 1,
            'contact_id' => $this->vendorId($return->supplier),
            'category_id' => $this->category('supplier_credits'),
            'payment_method' => $this->paymentMethod(),
            'description' => 'Supplier credit for return '.$return->reference,
            'reference' => $return->reference,
        ]);
    }

    /**
     * Released NovaCore payroll run (aggregated summary in the payload) =>
     *  - one expense transaction for the net pay disbursed to employees;
     *  - one bill per agency (SSS, PhilHealth, Pag-IBIG) for employee + employer
     *    + EC shares, and one bill to BIR for the withholding tax, all payable later.
     *
     * NovaCore never exposes per-employee pay, so neither does Akaunting.
     * Other deductions (loans, advances …) stay in the totals only.
     */
    public function syncPayroll(OutboxMessage $message): void
    {
        $summary = $message->payload ?? [];
        $entry = $message->subject_id;
        $currency = $summary['currency'] ?? config('freshcart-integrations.akaunting.currency');
        $postedAt = Carbon::parse($summary['posting_date'] ?? now());
        $period = trim(($summary['period']['start'] ?? '').' to '.($summary['period']['end'] ?? ''), ' to');
        $employees = (int) ($summary['totals']['employees'] ?? 0);

        if ((float) ($summary['totals']['net_pay'] ?? 0) > 0) {
            $this->transaction('payroll_net', $entry, [
                'type' => 'expense',
                'number' => 'PAY-'.$entry,
                'account_id' => $this->account(),
                'paid_at' => $this->timestamp($postedAt),
                'amount' => $this->money($summary['totals']['net_pay']),
                'currency_code' => $currency,
                'currency_rate' => 1,
                'category_id' => $this->category('payroll'),
                'payment_method' => $this->paymentMethod(),
                'description' => "Net pay {$period}, {$employees} employees (gross ".number_format((float) ($summary['totals']['gross_pay'] ?? 0), 2).')',
                'reference' => $entry,
            ]);
        }

        $remittances = [];

        foreach ($summary['statutory'] ?? [] as $row) {
            $remittances[$row['agency']] = array_filter([
                'Employee share' => (float) ($row['employee_share'] ?? 0),
                'Employer share' => (float) ($row['employer_share'] ?? 0),
                'EC contribution' => (float) ($row['ec_share'] ?? 0),
            ], fn ($amount) => $amount > 0);
        }

        $withholding = collect($summary['components'] ?? [])
            ->where('type', 'deduction')
            ->where('component', config('freshcart-integrations.frappe.withholding_component'))
            ->sum('amount');

        if ($withholding > 0) {
            $remittances['BIR'] = ['Withholding tax on compensation' => (float) $withholding];
        }

        $dueAt = $postedAt->copy()->addDays((int) config('freshcart-integrations.akaunting.statutory_due_days'));

        foreach (array_filter($remittances) as $agency => $lines) {
            $items = collect($lines)->map(fn ($amount, $name) => ['name' => "{$agency} {$name}", 'quantity' => 1, 'price' => $this->money($amount)])->values()->all();

            $this->document('payroll_remittance', "{$entry}:{$agency}", [
                'type' => 'bill',
                'document_number' => Str::limit("{$entry}-".Str::upper(Str::slug($agency)), 250, ''),
                'order_number' => $entry,
                'status' => 'received',
                'issued_at' => $this->timestamp($postedAt),
                'due_at' => $this->timestamp($dueAt),
                'amount' => $this->money(array_sum($lines)),
                'currency_code' => $currency,
                'currency_rate' => 1,
                'contact_id' => $this->agencyVendorId($agency, $currency),
                'contact_name' => $agency,
                'category_id' => $this->category('payroll_statutory'),
                'notes' => "Payroll {$entry}, {$period}",
                'items' => $items,
            ]);
        }
    }

    protected function agencyVendorId(string $agency, string $currency): int
    {
        if ($remoteId = IntegrationLink::remoteId(self::SYSTEM, 'agency', $agency)) {
            return (int) $remoteId;
        }

        $existing = $this->client->get('contacts', ['search' => "type:vendor name:\"{$agency}\"", 'limit' => 1])['data'][0]['id'] ?? null;

        $remoteId = $existing ?? $this->client->post('contacts', [
            'type' => 'vendor',
            'name' => $agency,
            'currency_code' => $currency,
            'enabled' => 1,
        ])['data']['id'];

        IntegrationLink::put(self::SYSTEM, 'agency', $agency, $remoteId);

        return (int) $remoteId;
    }

    protected function vendorId(Supplier $supplier, bool $update = false): int
    {
        $remoteId = IntegrationLink::remoteId(self::SYSTEM, 'vendor', $supplier->id);

        if ($remoteId && ! $update) {
            return (int) $remoteId;
        }

        $data = [
            'type' => 'vendor',
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'address' => $supplier->address,
            'reference' => $supplier->code,
            'currency_code' => core()->getBaseCurrencyCode(),
            'enabled' => (int) ($supplier->status && ! $supplier->trashed()),
        ];

        $remoteId ??= $this->client->findContactId('vendor', $supplier->email);

        $remoteId = $remoteId
            ? $this->client->put("contacts/{$remoteId}", $data)['data']['id'] ?? $remoteId
            : $this->client->post('contacts', $data)['data']['id'];

        IntegrationLink::put(self::SYSTEM, 'vendor', $supplier->id, $remoteId);

        return (int) $remoteId;
    }

    /**
     * Customers are keyed by e-mail so guest orders map to one contact too.
     */
    protected function customerId(?string $email, string $name): int
    {
        $key = Str::lower((string) $email) ?: 'guest';

        if ($remoteId = IntegrationLink::remoteId(self::SYSTEM, 'customer', $key)) {
            return (int) $remoteId;
        }

        $remoteId = $this->client->findContactId('customer', $email)
            ?? $this->client->post('contacts', [
                'type' => 'customer',
                'name' => $email ? $name : 'Walk-in / guest customer',
                'email' => $email,
                'currency_code' => core()->getBaseCurrencyCode(),
                'enabled' => 1,
            ])['data']['id'];

        IntegrationLink::put(self::SYSTEM, 'customer', $key, $remoteId);

        return (int) $remoteId;
    }

    protected function document(string $localType, string|int $localId, array $data): int
    {
        if ($remoteId = IntegrationLink::remoteId(self::SYSTEM, $localType, $localId)) {
            return (int) $remoteId;
        }

        $remoteId = $this->client->post('documents', $data)['data']['id'];

        IntegrationLink::put(self::SYSTEM, $localType, $localId, $remoteId);

        return (int) $remoteId;
    }

    protected function documentPayment(string $localType, string|int $localId, int $documentId, array $data): void
    {
        if (IntegrationLink::remoteId(self::SYSTEM, $localType, $localId)) {
            return;
        }

        $data['document_id'] = $documentId;

        $remoteId = $this->client->post("documents/{$documentId}/transactions", $data)['data']['id'];

        IntegrationLink::put(self::SYSTEM, $localType, $localId, $remoteId);
    }

    protected function transaction(string $localType, string|int $localId, array $data): void
    {
        if (IntegrationLink::remoteId(self::SYSTEM, $localType, $localId)) {
            return;
        }

        $remoteId = $this->client->post('transactions', array_filter($data, fn ($value) => $value !== null))['data']['id'];

        IntegrationLink::put(self::SYSTEM, $localType, $localId, $remoteId);
    }

    protected function category(string $key): int
    {
        $id = (int) config("freshcart-integrations.akaunting.categories.{$key}");

        if (! $id) {
            throw new IntegrationException('Set AKAUNTING_CATEGORY_'.Str::upper($key).' to an Akaunting category id.');
        }

        return $id;
    }

    protected function account(): int
    {
        return (int) config('freshcart-integrations.akaunting.account_id');
    }

    protected function paymentMethod(): string
    {
        return (string) config('freshcart-integrations.akaunting.payment_method');
    }

    protected function money(mixed $amount): float
    {
        return round((float) $amount, 2);
    }

    protected function timestamp(mixed $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    /**
     * "Net 30", "30 days", "COD" => 30, 30, 0.
     */
    protected function termsDays(?string $terms): int
    {
        return preg_match('/(\d+)/', (string) $terms, $match) ? (int) $match[1] : 0;
    }
}
