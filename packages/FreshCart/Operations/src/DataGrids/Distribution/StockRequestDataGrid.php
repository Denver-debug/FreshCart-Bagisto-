<?php

namespace FreshCart\Operations\DataGrids\Distribution;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\StockRequest;
use Illuminate\Support\Facades\DB;

class StockRequestDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_stock_requests as sr')
            ->join('inventory_sources as branch', 'branch.id', '=', 'sr.inventory_source_id')
            ->leftJoin('inventory_sources as warehouse', 'warehouse.id', '=', 'sr.source_inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'sr.requested_by')
            ->select(
                'sr.id',
                'sr.reference',
                'sr.status',
                'sr.priority',
                'sr.needed_by',
                'sr.created_at',
                'branch.name as branch_name',
                'warehouse.name as warehouse_name',
                'admins.name as requester_name',
            );

        $this->addFilter('reference', 'sr.reference');
        $this->addFilter('status', 'sr.status');
        $this->addFilter('needed_by', 'sr.needed_by');
        $this->addFilter('created_at', 'sr.created_at');
        $this->addFilter('branch_name', 'branch.name');

        return $this->scopeLocations($query, 'sr.inventory_source_id', 'sr.source_inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'branch_name',
            'label' => trans('operations::app.stock-requests.branch'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'warehouse_name',
            'label' => trans('operations::app.stock-requests.warehouse'),
            'type' => 'string',
        ]);

        $this->addColumn([
            'index' => 'requester_name',
            'label' => trans('operations::app.common.requested-by'),
            'type' => 'string',
        ]);

        $this->addColumn([
            'index' => 'priority',
            'label' => trans('operations::app.common.priority'),
            'type' => 'string',
            'sortable' => true,
            'closure' => fn ($row) => e(trans('operations::app.priorities.'.$row->priority)),
        ]);

        $this->addDateColumn('needed_by', 'operations::app.common.needed-by');

        $this->addStatusColumn([
            StockRequest::STATUS_DRAFT,
            StockRequest::STATUS_SUBMITTED,
            StockRequest::STATUS_PARTIALLY_ALLOCATED,
            StockRequest::STATUS_ALLOCATED,
            StockRequest::STATUS_FULFILLED,
            StockRequest::STATUS_REJECTED,
            StockRequest::STATUS_CANCELLED,
        ]);

        $this->addDateColumn('created_at', 'operations::app.common.created-at');
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.stock_requests', 'admin.operations.stock_requests.view');
    }
}
