<?php

namespace FreshCart\Operations\DataGrids\Procurement;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

class PurchaseRequestDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_purchase_requests as pr')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'pr.inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'pr.requested_by')
            ->select(
                'pr.id',
                'pr.reference',
                'pr.status',
                'pr.priority',
                'pr.needed_by',
                'pr.estimated_total',
                'pr.created_at',
                'inventory_sources.name as location_name',
                'admins.name as requester_name',
            );

        $this->addFilter('reference', 'pr.reference');
        $this->addFilter('status', 'pr.status');
        $this->addFilter('priority', 'pr.priority');
        $this->addFilter('needed_by', 'pr.needed_by');
        $this->addFilter('created_at', 'pr.created_at');
        $this->addFilter('location_name', 'inventory_sources.name');

        return $this->scopeLocations($query, 'pr.inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'location_name',
            'label' => trans('operations::app.common.location'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'requester_name',
            'label' => trans('operations::app.common.requested-by'),
            'type' => 'string',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'priority',
            'label' => trans('operations::app.common.priority'),
            'type' => 'string',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(PurchaseRequest::PRIORITIES)->map(fn ($priority) => [
                'label' => trans('operations::app.priorities.'.$priority),
                'value' => $priority,
            ])->all(),
            'sortable' => true,
            'closure' => fn ($row) => e(trans('operations::app.priorities.'.$row->priority)),
        ]);

        $this->addDateColumn('needed_by', 'operations::app.common.needed-by');

        $this->addColumn([
            'index' => 'estimated_total',
            'label' => trans('operations::app.purchase-requests.estimated-total'),
            'type' => 'decimal',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatBasePrice((float) $row->estimated_total),
        ]);

        $this->addStatusColumn([
            PurchaseRequest::STATUS_DRAFT,
            PurchaseRequest::STATUS_SUBMITTED,
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_REJECTED,
            PurchaseRequest::STATUS_CONVERTED,
            PurchaseRequest::STATUS_CANCELLED,
        ]);

        $this->addDateColumn('created_at', 'operations::app.common.created-at');
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.purchase_requests', 'admin.operations.purchase_requests.view');
    }
}
