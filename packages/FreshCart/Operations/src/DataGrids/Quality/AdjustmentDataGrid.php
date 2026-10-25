<?php

namespace FreshCart\Operations\DataGrids\Quality;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\Adjustment;
use Illuminate\Support\Facades\DB;

class AdjustmentDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_adjustments as adj')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'adj.inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'adj.requested_by')
            ->select(
                'adj.id',
                'adj.reference',
                'adj.direction',
                'adj.reason_code',
                'adj.source_bucket',
                'adj.total_value',
                'adj.status',
                'adj.created_at',
                'adj.posted_at',
                'inventory_sources.name as location_name',
                'admins.name as requester_name',
            );

        $this->addFilter('reference', 'adj.reference');
        $this->addFilter('reason_code', 'adj.reason_code');
        $this->addFilter('status', 'adj.status');
        $this->addFilter('created_at', 'adj.created_at');
        $this->addFilter('location_name', 'inventory_sources.name');

        return $this->scopeLocations($query, 'adj.inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'location_name',
            'label' => trans('operations::app.common.location'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'reason_code',
            'label' => trans('operations::app.common.reason'),
            'type' => 'string',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(Adjustment::REASONS)->map(fn ($reason) => ['label' => trans('operations::app.adjustments.reasons.'.$reason), 'value' => $reason])->all(),
            'closure' => fn ($row) => e(trans('operations::app.adjustments.reasons.'.$row->reason_code)),
        ]);

        $this->addColumn([
            'index' => 'direction',
            'label' => trans('operations::app.adjustments.direction'),
            'type' => 'string',
            'closure' => fn ($row) => e(trans('operations::app.adjustments.directions.'.$row->direction))
                .($row->source_bucket ? ' · '.e(trans('operations::app.buckets.'.$row->source_bucket)) : ''),
        ]);

        $this->addColumn([
            'index' => 'total_value',
            'label' => trans('operations::app.common.value'),
            'type' => 'decimal',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatBasePrice((float) $row->total_value),
        ]);

        $this->addStatusColumn([
            Adjustment::STATUS_DRAFT,
            Adjustment::STATUS_SUBMITTED,
            Adjustment::STATUS_APPROVED,
            Adjustment::STATUS_POSTED,
            Adjustment::STATUS_REJECTED,
            Adjustment::STATUS_CANCELLED,
        ]);

        $this->addColumn([
            'index' => 'requester_name',
            'label' => trans('operations::app.common.requested-by'),
            'type' => 'string',
        ]);

        $this->addDateColumn('created_at', 'operations::app.common.created-at');
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.adjustments', 'admin.operations.adjustments.view');
    }
}
