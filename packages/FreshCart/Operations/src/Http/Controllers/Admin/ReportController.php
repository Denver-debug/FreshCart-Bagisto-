<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\Incident;
use FreshCart\Operations\Models\PromotionProposal;
use FreshCart\Operations\Models\Transfer;
use FreshCart\Operations\Services\ReportService;
use FreshCart\Operations\Support\Settings;
use FreshCart\Operations\Support\StatusBadge;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operational and management reports. Each report is a query from ReportService
 * rendered by one generic table view, paginated on screen and streamed in full
 * as CSV (`?export=csv`). Route-level ACL restricts who can open each report;
 * rows are restricted to the admin's locations.
 */
class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index()
    {
        $groups = [
            'operations.reports.inventory' => ['ledger', 'low_stock', 'expiry', 'transfers'],
            'operations.reports.procurement' => ['receiving_discrepancies', 'supplier_performance'],
            'operations.reports.quality' => ['held_stock', 'recurring_incidents'],
            'operations.reports.finance' => ['valuation', 'adjustments', 'disposals'],
            'operations.reports.sales' => ['branch_sales', 'promotion_performance'],
        ];

        return view('operations::admin.reports.index', [
            'groups' => collect($groups)->filter(fn ($reports, $permission) => bouncer()->hasPermission($permission)),
        ]);
    }

    public function ledger()
    {
        $filters = $this->filters(['sku' => 'nullable|string|max:100', 'type' => 'nullable|string|max:40', 'reference' => 'nullable|string|max:50', 'category_id' => 'nullable|integer']);

        return $this->render('ledger', $this->reports->ledger($this->locationIds($filters), $filters), [
            'created_at' => 'datetime', 'sku' => 'text', 'location' => 'text', 'movement_type' => 'movement',
            'bucket_from' => 'bucket', 'bucket_to' => 'bucket', 'qty' => 'int', 'sellable_delta' => 'int',
            'sellable_after' => 'int', 'unit_cost' => 'money', 'reference_no' => 'text', 'reason' => 'text', 'actor' => 'text',
        ], $filters, ['date', 'location', 'sku', 'type', 'reference', 'category']);
    }

    public function lowStock()
    {
        $filters = $this->filters(['category_id' => 'nullable|integer']);

        return $this->render('low_stock', $this->reports->lowStock($this->locationIds($filters), $filters['category_id'] ?? null), [
            'sku' => 'text', 'name' => 'text', 'location' => 'text', 'on_hand' => 'int', 'reorder_level' => 'int', 'max_stock' => 'int', 'out_of_stock' => 'bool',
        ], $filters, ['location', 'category']);
    }

    public function valuation()
    {
        $filters = $this->filters(['category_id' => 'nullable|integer']);

        return $this->render('valuation', $this->reports->valuation($this->locationIds($filters), $filters['category_id'] ?? null), [
            'location' => 'text', 'sellable_units' => 'int', 'held_units' => 'int', 'sellable_value' => 'money',
            'held_value' => 'money', 'unvalued_products' => 'int',
        ], $filters, ['location', 'category'], trans('operations::app.reports.notes.valuation'), false);
    }

    public function expiry()
    {
        $filters = $this->filters(['days' => 'nullable|integer|min:0|max:365']);

        $days = (int) ($filters['days'] ?? app(Settings::class)->expiryWarningDays());

        return $this->render('expiry', $this->reports->expiring($this->locationIds($filters), $days), [
            'sku' => 'text', 'location' => 'text', 'batch_no' => 'text', 'expiry_date' => 'date', 'qty_on_hand' => 'int', 'value' => 'money',
        ], $filters + ['days' => $days], ['location', 'days']);
    }

    public function transfers()
    {
        $filters = $this->filters(['status' => ['nullable', Rule::in([Transfer::STATUS_IN_TRANSIT, Transfer::STATUS_PARTIALLY_RECEIVED, Transfer::STATUS_RECEIVED, Transfer::STATUS_CLOSED_WITH_DISCREPANCY, Transfer::STATUS_APPROVED, Transfer::STATUS_PICKING])]]);

        return $this->render('transfers', $this->reports->transfers($this->locationIds($filters), $filters), [
            'reference' => 'text', 'type' => 'transfer_type', 'source' => 'text', 'destination' => 'text', 'status' => 'status',
            'dispatched_at' => 'datetime', 'expected_arrival_at' => 'datetime', 'received_at' => 'datetime',
            'transit_hours' => 'int', 'has_discrepancy' => 'bool', 'overdue' => 'bool',
        ], $filters, ['date', 'location', 'transfer_status']);
    }

    public function receivingDiscrepancies()
    {
        $filters = $this->filters(['supplier_id' => 'nullable|integer']);

        return $this->render('receiving_discrepancies', $this->reports->receivingDiscrepancies($this->locationIds($filters), $filters), [
            'received_at' => 'datetime', 'grn' => 'text', 'po' => 'text', 'supplier' => 'text', 'location' => 'text', 'sku' => 'text',
            'qty_ordered' => 'int', 'qty_received' => 'int', 'qty_rejected' => 'int', 'rejected_value' => 'money',
            'discrepancy_note' => 'text', 'over_receipt_reason' => 'text',
        ], $filters, ['date', 'location', 'supplier']);
    }

    public function supplierPerformance()
    {
        $filters = $this->filters(['supplier_id' => 'nullable|integer']);

        return $this->render('supplier_performance', $this->reports->supplierPerformance($filters), [
            'supplier' => 'text', 'orders' => 'int', 'ordered_units' => 'int', 'accepted_units' => 'int', 'rejected_units' => 'int',
            'fill_rate' => 'percent', 'measurable' => 'int', 'on_time_rate' => 'percent', 'avg_lead_days' => 'text',
        ], $filters, ['date', 'supplier'], trans('operations::app.reports.notes.supplier_performance'), false);
    }

    public function adjustments()
    {
        $filters = $this->filters(['reason' => ['nullable', Rule::in(Adjustment::REASONS)]]);

        return $this->render('adjustments', $this->reports->adjustments($this->locationIds($filters), $filters), [
            'posted_at' => 'datetime', 'reference' => 'text', 'location' => 'text', 'reason_code' => 'adjustment_reason', 'sku' => 'text',
            'qty' => 'int', 'unit_cost' => 'money', 'value' => 'money', 'reason' => 'text', 'requested_by' => 'text', 'approvers' => 'text',
        ], $filters, ['date', 'location', 'adjustment_reason']);
    }

    public function heldStock()
    {
        $filters = $this->filters([]);

        return $this->render('held_stock', $this->reports->heldStock($this->locationIds($filters)), [
            'location' => 'text', 'bucket' => 'bucket', 'sku' => 'text', 'batch_no' => 'text', 'expiry_date' => 'date', 'qty' => 'int', 'value' => 'money', 'since' => 'datetime',
        ], $filters, ['location'], trans('operations::app.reports.notes.held_stock'));
    }

    public function recurringIncidents()
    {
        $filters = $this->filters(['type' => ['nullable', Rule::in(Incident::TYPES)]]);

        return $this->render('recurring_incidents', $this->reports->recurringIncidents($this->locationIds($filters), $filters), [
            'sku' => 'text', 'name' => 'text', 'incidents' => 'int', 'units' => 'int', 'types' => 'text', 'locations' => 'text', 'suppliers' => 'text', 'last_reported' => 'datetime',
        ], $filters, ['date', 'location', 'incident_type'], null, false);
    }

    public function disposals()
    {
        $filters = $this->filters([]);

        return $this->render('disposals', $this->reports->disposals($this->locationIds($filters), $filters), [
            'completed_at' => 'datetime', 'reference' => 'text', 'location' => 'text', 'sku' => 'text', 'batch_no' => 'text', 'expiry_date' => 'date',
            'qty' => 'int', 'reason' => 'disposal_reason', 'disposition' => 'disposition', 'unit_cost' => 'money', 'value' => 'money', 'disposal_method' => 'text',
        ], $filters, ['date', 'location']);
    }

    public function branchSales()
    {
        $filters = $this->filters(['category_id' => 'nullable|integer']);

        return $this->render('branch_sales', $this->reports->branchSales($this->locationIds($filters), $filters), [
            'location' => 'text', 'orders' => 'int', 'units' => 'int', 'revenue' => 'money',
        ], $filters, ['date', 'location', 'category'], trans('operations::app.reports.notes.branch_sales'), false, $this->reports->salesSummary($filters));
    }

    public function promotionPerformance()
    {
        $filters = $this->filters([]);

        $query = DB::table('fc_promotion_proposals')
            ->whereIn('status', ['active', 'expired'])
            ->tap(fn ($query) => $this->reports->dateRange($query, 'starts_on', $filters))
            ->orderByDesc('starts_on');

        $rows = $query->get()->map(function ($row) {
            $performance = $this->reports->promotionPerformance(PromotionProposal::find($row->id));

            return (object) [
                'reference' => $row->reference,
                'name' => $row->name,
                'status' => $row->status,
                'window' => $row->starts_on.' → '.$row->ends_on,
                'units_during' => $performance['during']['units'],
                'revenue_during' => $performance['during']['revenue'],
                'units_before' => $performance['before']['units'],
                'uplift_units' => $performance['uplift_units'],
            ];
        });

        return $this->render('promotion_performance', $rows, [
            'reference' => 'text', 'name' => 'text', 'status' => 'status', 'window' => 'text', 'units_during' => 'int',
            'revenue_during' => 'money', 'units_before' => 'int', 'uplift_units' => 'percent',
        ], $filters, ['date'], trans('operations::app.reports.notes.promotion_performance'), false);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    protected function render(string $key, $source, array $columns, array $filters, array $filterFields, ?string $note = null, bool $paginate = true, ?array $summary = null)
    {
        if (request('export') === 'csv') {
            return $this->csv($key, $source, $columns);
        }

        $rows = $source instanceof Builder
            ? ($paginate ? $source->paginate(50)->withQueryString() : $source->get())
            : $source;

        return view('operations::admin.reports.table', [
            'key' => $key,
            'rows' => $rows,
            'columns' => $columns,
            'filters' => $filters,
            'filterFields' => $filterFields,
            'note' => $note,
            'summary' => $summary,
            'locationOptions' => $this->locations()->options(),
            'supplierOptions' => DB::table('fc_suppliers')->whereNull('deleted_at')->orderBy('name')->pluck('name', 'id'),
            'categoryOptions' => DB::table('category_translations')->where('locale', app()->getLocale())->orderBy('name')->pluck('name', 'category_id'),
        ]);
    }

    protected function csv(string $key, $source, array $columns): StreamedResponse
    {
        return response()->streamDownload(function () use ($source, $columns) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map(fn ($column) => trans('operations::app.reports.columns.'.$column), array_keys($columns)));

            $write = function ($row) use ($out, $columns) {
                fputcsv($out, array_map(
                    fn ($column, $type) => $this->plain($row->{$column} ?? null, $type),
                    array_keys($columns),
                    $columns,
                ));
            };

            if ($source instanceof Builder) {
                foreach ($source->cursor() as $row) {
                    $write($row);
                }
            } else {
                foreach ($source as $row) {
                    $write($row);
                }
            }

            fclose($out);
        }, $key.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Plain-text value for CSV (also used by the view before escaping).
     */
    public function plain($value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2, '.', ''),
            'int' => (string) (int) $value,
            'percent' => $value.'%',
            'bool' => $value ? trans('operations::app.common.yes') : trans('operations::app.common.no'),
            'bucket' => trans('operations::app.buckets.'.$value),
            'movement' => trans('operations::app.stock.movement-types.'.$value),
            'status' => StatusBadge::label($value),
            'transfer_type' => trans('operations::app.transfers.types.'.$value),
            'adjustment_reason' => trans('operations::app.adjustments.reasons.'.$value),
            'disposal_reason' => trans('operations::app.disposals.reasons.'.$value),
            'disposition' => trans('operations::app.disposals.dispositions.'.$value),
            default => (string) $value,
        };
    }

    protected function filters(array $rules): array
    {
        return request()->validate($rules + [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'location_id' => 'nullable|integer',
        ]);
    }

    /**
     * Location filter intersected with the admin's own scope.
     */
    protected function locationIds(array $filters): ?array
    {
        $scope = $this->locations()->ids();

        if (! empty($filters['location_id'])) {
            $this->locations()->authorize((int) $filters['location_id']);

            return [(int) $filters['location_id']];
        }

        return $scope;
    }
}
