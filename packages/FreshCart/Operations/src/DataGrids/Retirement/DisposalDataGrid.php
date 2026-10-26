<?php

namespace FreshCart\Operations\DataGrids\Retirement;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\DisposalRequest;
use Illuminate\Support\Facades\DB;

class DisposalDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_disposal_requests as dsp')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'dsp.inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'dsp.requested_by')
            ->select(
                'dsp.id',
                'dsp.reference',
                'dsp.status',
                'dsp.total_value',
                'dsp.created_at',
                'dsp.completed_at',
                'inventory_sources.name as location_name',
                'admins.name as requester_name',
            );

        $this->addFilter('reference', 'dsp.reference');
        $this->addFilter('status', 'dsp.status');
        $this->addFilter('created_at', 'dsp.created_at');
        $this->addFilter('location_name', 'inventory_sources.name');

        return $this->scopeLocations($query, 'dsp.inventory_source_id');
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
            'index' => 'total_value',
            'label' => trans('operations::app.common.value'),
            'type' => 'decimal',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatBasePrice((float) $row->total_value),
        ]);

        $this->addStatusColumn([
            DisposalRequest::STATUS_DRAFT,
            DisposalRequest::STATUS_SUBMITTED,
            DisposalRequest::STATUS_INSPECTED,
            DisposalRequest::STATUS_VALUED,
            DisposalRequest::STATUS_APPROVED,
            DisposalRequest::STATUS_QUARANTINED,
            DisposalRequest::STATUS_DISPOSED,
            DisposalRequest::STATUS_RETURNED_TO_SUPPLIER,
            DisposalRequest::STATUS_COMPLETED,
            DisposalRequest::STATUS_REJECTED,
            DisposalRequest::STATUS_CANCELLED,
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
        $this->addViewAction('operations.disposals', 'admin.operations.disposals.view');
    }
}
