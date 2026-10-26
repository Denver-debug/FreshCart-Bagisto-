<?php

namespace FreshCart\Operations\DataGrids\Quality;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\SupplierReturn;
use Illuminate\Support\Facades\DB;

class SupplierReturnDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_supplier_returns as srt')
            ->join('fc_suppliers', 'fc_suppliers.id', '=', 'srt.supplier_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'srt.inventory_source_id')
            ->leftJoinSub(
                DB::table('fc_supplier_return_items')
                    ->selectRaw('supplier_return_id, SUM(qty) as qty, SUM(qty_replaced) as qty_replaced, SUM(qty * unit_cost) as value')
                    ->groupBy('supplier_return_id'),
                'totals',
                'totals.supplier_return_id',
                '=',
                'srt.id'
            )
            ->select(
                'srt.id',
                'srt.reference',
                'srt.status',
                'srt.created_at',
                'srt.dispatched_at',
                'srt.credit_amount',
                'fc_suppliers.name as supplier_name',
                'inventory_sources.name as location_name',
                'totals.qty',
                'totals.qty_replaced',
                'totals.value',
            );

        $this->addFilter('reference', 'srt.reference');
        $this->addFilter('status', 'srt.status');
        $this->addFilter('supplier_name', 'fc_suppliers.name');
        $this->addFilter('created_at', 'srt.created_at');

        return $this->scopeLocations($query, 'srt.inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'supplier_name',
            'label' => trans('operations::app.common.supplier'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'location_name',
            'label' => trans('operations::app.common.location'),
            'type' => 'string',
        ]);

        $this->addColumn([
            'index' => 'qty',
            'label' => trans('operations::app.common.qty'),
            'type' => 'integer',
        ]);

        $this->addColumn([
            'index' => 'qty_replaced',
            'label' => trans('operations::app.supplier-returns.qty-replaced'),
            'type' => 'integer',
        ]);

        $this->addColumn([
            'index' => 'value',
            'label' => trans('operations::app.common.value'),
            'type' => 'decimal',
            'closure' => fn ($row) => core()->formatBasePrice((float) $row->value),
        ]);

        $this->addStatusColumn([SupplierReturn::STATUS_PENDING, SupplierReturn::STATUS_DISPATCHED, SupplierReturn::STATUS_RESOLVED, SupplierReturn::STATUS_CANCELLED]);

        $this->addDateColumn('created_at', 'operations::app.common.created-at');
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.supplier_returns', 'admin.operations.supplier_returns.view');
    }
}
