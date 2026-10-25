<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Distribution;

use FreshCart\Operations\DataGrids\Distribution\TransferDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Services\AttachmentService;
use FreshCart\Operations\Services\TransferService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Webkul\Core\Traits\PDFHandler;
use Webkul\User\Models\Admin;

class TransferController extends Controller
{
    use PDFHandler;

    public function __construct(protected TransferService $transfers) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(TransferDataGrid::class)->process();
        }

        return view('operations::admin.transfers.index');
    }

    public function create(): View
    {
        return view('operations::admin.transfers.create', [
            'sourceOptions' => $this->locations()->allOptions(),
            'destinationOptions' => $this->locations()->options(),
        ]);
    }

    /**
     * Branch managers request stock from another location into their own.
     */
    public function store()
    {
        $validated = request()->validate([
            'type' => ['required', Rule::in([Transfer::TYPE_INTER_BRANCH, Transfer::TYPE_TEMPORARY, Transfer::TYPE_EMERGENCY])],
            'source_inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'destination_inventory_source_id' => ['required', 'integer', 'different:source_inventory_source_id', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'priority' => 'required|in:low,normal,high,urgent',
            'reason' => 'required|string|max:2000',
            'expected_return_date' => 'nullable|required_if:type,temporary|date|after:today',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:1000000',
        ]);

        $this->locations()->authorizeAny([(int) $validated['source_inventory_source_id'], (int) $validated['destination_inventory_source_id']]);

        return $this->attempt(
            fn () => $this->transfers->create($validated, $validated['items']),
            trans('operations::app.transfers.saved'),
            fn ($transfer) => redirect()->route('admin.operations.transfers.view', $transfer->id),
        );
    }

    public function view(int $id): View
    {
        $transfer = $this->find($id)->load(['items', 'sourceLocation', 'destinationLocation', 'requester', 'assignedStaff', 'receipts.items', 'receipts.receiver', 'receipts.attachments', 'stockRequest']);

        $available = $transfer->items->mapWithKeys(fn ($item) => [
            $item->id => $this->transfers->uncommittedQty($item->product_id, $transfer->source_inventory_source_id, $transfer->id),
        ]);

        $staff = Admin::where('status', 1)->orderBy('name')->pluck('name', 'id');

        $canAtSource = $this->locations()->allows($transfer->source_inventory_source_id);
        $canAtDestination = $this->locations()->allows($transfer->destination_inventory_source_id);

        return view('operations::admin.transfers.view', compact('transfer', 'available', 'staff', 'canAtSource', 'canAtDestination'));
    }

    public function print(int $id)
    {
        $transfer = $this->find($id)->load(['items', 'sourceLocation', 'destinationLocation', 'assignedStaff']);

        $qr = base64_encode(QrCode::format('svg')->size(110)->generate(route('admin.operations.transfers.view', $transfer->id)));

        return $this->downloadPDF(view('operations::admin.transfers.pdf', compact('transfer', 'qr'))->render(), $transfer->reference);
    }

    public function submit(int $id)
    {
        $transfer = $this->find($id);

        abort_unless($transfer->requested_by === $this->adminId(), 403);

        return $this->attempt(fn () => $this->transfers->submit($transfer), trans('operations::app.transfers.submitted'));
    }

    public function approve(int $id)
    {
        $transfer = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->transfers->approve($transfer, $remarks), trans('operations::app.common.approved-message'));
    }

    public function reject(int $id)
    {
        $transfer = $this->find($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(fn () => $this->transfers->reject($transfer, $remarks), trans('operations::app.common.rejected-message'));
    }

    public function cancel(int $id)
    {
        $transfer = $this->find($id);

        abort_unless($transfer->requested_by === $this->adminId() || bouncer()->hasPermission('operations.transfers.approve'), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->transfers->cancel($transfer, $remarks), trans('operations::app.common.cancelled-message'));
    }

    public function pick(int $id)
    {
        $transfer = $this->findAtSource($id);

        $validated = request()->validate([
            'picked' => 'required|array',
            'picked.*' => 'integer|min:0',
        ]);

        return $this->attempt(fn () => $this->transfers->pick($transfer, $validated['picked']), trans('operations::app.transfers.picked'));
    }

    public function schedule(int $id)
    {
        $transfer = $this->find($id);

        $validated = request()->validate([
            'scheduled_dispatch_at' => 'nullable|date',
            'expected_arrival_at' => 'nullable|date|after_or_equal:scheduled_dispatch_at',
            'assigned_staff_id' => 'nullable|integer|exists:admins,id',
            'driver_name' => 'nullable|string|max:100',
            'vehicle' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->attempt(fn () => $this->transfers->schedule($transfer, $validated), trans('operations::app.transfers.scheduled'));
    }

    public function dispatchTransfer(int $id)
    {
        $transfer = $this->findAtSource($id);

        $key = request()->validate(['idempotency_key' => 'required|uuid'])['idempotency_key'];

        return $this->attempt(fn () => $this->transfers->dispatch($transfer, $key), trans('operations::app.transfers.dispatched'));
    }

    public function receive(int $id)
    {
        $transfer = Transfer::findOrFail($id);

        $this->locations()->authorize($transfer->destination_inventory_source_id);

        $validated = request()->validate([
            'idempotency_key' => 'required|uuid',
            'receiver_name' => 'required|string|max:100',
            'remarks' => 'nullable|string|max:2000',
            'lines' => 'required|array',
            'lines.*.qty_received' => 'nullable|integer|min:0',
            'lines.*.qty_damaged' => 'nullable|integer|min:0',
            'lines.*.remarks' => 'nullable|string|max:500',
            'proof' => 'nullable|'.AttachmentService::RULES,
        ]);

        return $this->attempt(
            fn () => $this->transfers->receive($transfer, $validated, $validated['lines'], request()->file('proof')),
            trans('operations::app.transfers.receipt-recorded'),
        );
    }

    public function closeWithDiscrepancy(int $id)
    {
        $transfer = $this->find($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(
            fn () => $this->transfers->closeWithDiscrepancy($transfer, $remarks),
            trans('operations::app.transfers.closed-discrepancy'),
            fn ($adjustment) => redirect()->route('admin.operations.adjustments.view', $adjustment->id),
        );
    }

    public function createReturn(int $id)
    {
        $transfer = $this->find($id);

        return $this->attempt(
            fn () => $this->transfers->createReturn($transfer),
            trans('operations::app.transfers.return-created'),
            fn ($return) => redirect()->route('admin.operations.transfers.view', $return->id),
        );
    }

    protected function find(int $id): Transfer
    {
        $transfer = Transfer::findOrFail($id);

        $this->locations()->authorizeAny([$transfer->source_inventory_source_id, $transfer->destination_inventory_source_id]);

        return $transfer;
    }

    protected function findAtSource(int $id): Transfer
    {
        $transfer = Transfer::findOrFail($id);

        $this->locations()->authorize($transfer->source_inventory_source_id);

        return $transfer;
    }
}
