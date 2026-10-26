<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Retirement;

use FreshCart\Operations\DataGrids\Retirement\DisposalDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\Attachment;
use FreshCart\Operations\Models\DisposalItem;
use FreshCart\Operations\Models\DisposalRequest;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Services\AttachmentService;
use FreshCart\Operations\Services\RetirementService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Core\Traits\PDFHandler;

class DisposalController extends Controller
{
    use PDFHandler;

    public function __construct(protected RetirementService $retirement) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(DisposalDataGrid::class)->process();
        }

        return view('operations::admin.disposals.index');
    }

    public function create(): View
    {
        return view('operations::admin.disposals.create', [
            'locationOptions' => $this->locations()->options(),
            'suppliers' => Supplier::where('status', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store()
    {
        $validated = request()->validate([
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'reason' => 'required|string|max:2000',
            'item_reason' => ['required', Rule::in(DisposalRequest::REASONS)],
            'source_bucket' => ['required', Rule::in(array_merge([DisposalItem::SOURCE_SELLABLE], StockHold::BUCKETS))],
            'disposition' => ['required', Rule::in([DisposalRequest::DISPOSITION_DISPOSE, DisposalRequest::DISPOSITION_RETURN])],
            'supplier_id' => ['nullable', 'required_if:disposition,return_to_supplier', 'integer', Rule::exists('fc_suppliers', 'id')->whereNull('deleted_at')],
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:1000000',
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        $items = collect($validated['items'])->map(fn ($item) => [
            'product_id' => (int) $item['product_id'],
            'qty' => (int) $item['qty'],
            'source_bucket' => $validated['source_bucket'],
            'reason' => $validated['item_reason'],
            'disposition' => $validated['disposition'],
        ])->all();

        return $this->attempt(
            fn () => $this->retirement->create($validated, $items),
            trans('operations::app.disposals.saved'),
            fn ($request) => redirect()->route('admin.operations.disposals.view', $request->id),
        );
    }

    public function view(int $id): View
    {
        $disposal = $this->find($id)->load(['items', 'location', 'requester', 'supplier', 'supplierReturn', 'attachments']);

        return view('operations::admin.disposals.view', [
            'disposal' => $disposal,
            'suppliers' => Supplier::where('status', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function print(int $id)
    {
        $disposal = $this->find($id)->load(['items', 'location', 'requester', 'approvals.admin', 'attachments']);

        return $this->downloadPDF(view('operations::admin.disposals.pdf', compact('disposal'))->render(), $disposal->reference);
    }

    public function evidence(int $id, int $attachmentId)
    {
        $disposal = $this->find($id);

        $attachment = Attachment::where('attachable_type', $disposal->getMorphClass())
            ->where('attachable_id', $disposal->id)
            ->findOrFail($attachmentId);

        return app(AttachmentService::class)->download($attachment);
    }

    public function submit(int $id)
    {
        $disposal = $this->find($id);

        abort_unless($disposal->requested_by === $this->adminId(), 403);

        return $this->attempt(fn () => $this->retirement->submit($disposal), trans('operations::app.disposals.submitted'));
    }

    public function inspect(int $id)
    {
        $disposal = $this->find($id);

        $remarks = request()->validate(['inspection_remarks' => 'required|string|max:2000'])['inspection_remarks'];

        return $this->attempt(fn () => $this->retirement->inspect($disposal, $remarks), trans('operations::app.disposals.inspected'));
    }

    public function value(int $id)
    {
        $disposal = $this->find($id);

        $validated = request()->validate([
            'unit_costs' => 'nullable|array',
            'unit_costs.*' => 'numeric|min:0|max:100000000',
            'valuation_remarks' => 'nullable|string|max:2000',
        ]);

        return $this->attempt(
            fn () => $this->retirement->value($disposal, $validated['unit_costs'] ?? [], $validated['valuation_remarks'] ?? null),
            trans('operations::app.disposals.valued'),
        );
    }

    public function approve(int $id)
    {
        $disposal = $this->find($id);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->retirement->approve($disposal, $remarks), trans('operations::app.common.approved-message'));
    }

    public function reject(int $id)
    {
        $disposal = $this->find($id);

        $remarks = request()->validate(['remarks' => 'required|string|max:2000'])['remarks'];

        return $this->attempt(fn () => $this->retirement->reject($disposal, $remarks), trans('operations::app.common.rejected-message'));
    }

    public function segregate(int $id)
    {
        $disposal = $this->find($id);

        return $this->attempt(fn () => $this->retirement->segregate($disposal), trans('operations::app.disposals.segregated'));
    }

    public function complete(int $id)
    {
        $disposal = $this->find($id);

        $validated = request()->validate([
            'disposal_method' => 'required|string|max:100',
            'completion_remarks' => 'nullable|string|max:2000',
            'supplier_id' => ['nullable', 'integer', Rule::exists('fc_suppliers', 'id')->whereNull('deleted_at')],
            'evidence' => 'required|array|min:1|max:5',
            'evidence.*' => AttachmentService::RULES,
        ]);

        return $this->attempt(
            fn () => $this->retirement->complete($disposal, $validated, request()->file('evidence', [])),
            trans('operations::app.disposals.completed'),
        );
    }

    public function cancel(int $id)
    {
        $disposal = $this->find($id);

        abort_unless($disposal->requested_by === $this->adminId() || bouncer()->hasPermission('operations.disposals.approve'), 403);

        $remarks = request()->validate(['remarks' => 'nullable|string|max:2000'])['remarks'] ?? null;

        return $this->attempt(fn () => $this->retirement->cancel($disposal, $remarks), trans('operations::app.common.cancelled-message'));
    }

    protected function find(int $id): DisposalRequest
    {
        $disposal = DisposalRequest::findOrFail($id);

        $this->locations()->authorize($disposal->inventory_source_id);

        return $disposal;
    }
}
