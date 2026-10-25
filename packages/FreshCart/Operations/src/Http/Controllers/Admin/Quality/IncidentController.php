<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Quality;

use FreshCart\Operations\DataGrids\Quality\IncidentDataGrid;
use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\Attachment;
use FreshCart\Operations\Models\Incident;
use FreshCart\Operations\Models\Supplier;
use FreshCart\Operations\Services\AttachmentService;
use FreshCart\Operations\Services\QualityService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(protected QualityService $quality) {}

    public function index()
    {
        if (request()->ajax()) {
            return datagrid(IncidentDataGrid::class)->process();
        }

        return view('operations::admin.incidents.index');
    }

    public function create(): View
    {
        return view('operations::admin.incidents.create', [
            'locationOptions' => $this->locations()->options(),
            'suppliers' => Supplier::where('status', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store()
    {
        $validated = request()->validate([
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'incident_type' => ['required', Rule::in(Incident::TYPES)],
            'items' => 'required|array|size:1',
            'items.0.product_id' => 'required|integer|exists:products,id',
            'items.0.qty' => 'required|integer|min:1|max:1000000',
            'batch_no' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'description' => 'required|string|max:2000',
            'supplier_id' => ['nullable', 'integer', Rule::exists('fc_suppliers', 'id')->whereNull('deleted_at')],
            'photos' => 'nullable|array|max:5',
            'photos.*' => AttachmentService::RULES,
        ]);

        $this->locations()->authorize((int) $validated['inventory_source_id']);

        $data = $validated + [
            'product_id' => $validated['items'][0]['product_id'],
            'qty' => $validated['items'][0]['qty'],
        ];

        return $this->attempt(
            fn () => $this->quality->report($data, request()->file('photos', [])),
            trans('operations::app.incidents.reported'),
            fn ($incident) => redirect()->route('admin.operations.incidents.view', $incident->id),
        );
    }

    public function view(int $id): View
    {
        $incident = $this->find($id)->load(['location', 'reporter', 'inspector', 'classifier', 'supplier', 'adjustment', 'supplierReturn', 'attachments']);

        return view('operations::admin.incidents.view', [
            'incident' => $incident,
            'suppliers' => Supplier::where('status', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function photo(int $id, int $attachmentId)
    {
        $incident = $this->find($id);

        $attachment = Attachment::where('attachable_type', $incident->getMorphClass())
            ->where('attachable_id', $incident->id)
            ->findOrFail($attachmentId);

        return app(AttachmentService::class)->download($attachment);
    }

    public function inspect(int $id)
    {
        $incident = $this->find($id);

        $validated = request()->validate([
            'checklist' => 'nullable|array',
            'checklist.*' => 'boolean',
            'inspection_remarks' => 'required|string|max:2000',
        ]);

        return $this->attempt(
            fn () => $this->quality->inspect($incident, $validated['checklist'] ?? [], $validated['inspection_remarks']),
            trans('operations::app.incidents.inspected'),
        );
    }

    public function classify(int $id)
    {
        $incident = $this->find($id);

        $validated = request()->validate([
            'classification' => ['required', Rule::in(Incident::CLASSIFICATIONS)],
            'supplier_id' => ['nullable', 'required_if:classification,return_to_supplier', 'integer', Rule::exists('fc_suppliers', 'id')->whereNull('deleted_at')],
            'remarks' => 'nullable|string|max:2000',
        ]);

        return $this->attempt(
            fn () => $this->quality->classify($incident, $validated['classification'], isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null, $validated['remarks'] ?? null),
            trans('operations::app.incidents.classified'),
        );
    }

    protected function find(int $id): Incident
    {
        $incident = Incident::findOrFail($id);

        $this->locations()->authorize($incident->inventory_source_id);

        return $incident;
    }
}
