<?php

namespace FreshCart\Operations\DataGrids\Procurement;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use Illuminate\Support\Facades\DB;

class GoodsReceiptDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_goods_receipts as grn')
            ->join('fc_purchase_orders as po', 'po.id', '=', 'grn.purchase_order_id')
            ->join('fc_suppliers', 'fc_suppliers.id', '=', 'po.supplier_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'grn.inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'grn.received_by')
            ->select(
                'grn.id',
                'grn.reference',
                'grn.received_at',
                'grn.has_discrepancy',
                'grn.delivery_note_no',
                'po.reference as po_reference',
                'fc_suppliers.name as supplier_name',
                'inventory_sources.name as location_name',
                'admins.name as receiver_name',
            );

        $this->addFilter('reference', 'grn.reference');
        $this->addFilter('received_at', 'grn.received_at');
        $this->addFilter('has_discrepancy', 'grn.has_discrepancy');
        $this->addFilter('po_reference', 'po.reference');
        $this->addFilter('supplier_name', 'fc_suppliers.name');

        return $this->scopeLocations($query, 'grn.inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'po_reference',
            'label' => trans('operations::app.purchase-orders.reference'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'supplier_name',
            'label' => trans('operations::app.common.supplier'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'location_name',
            'label' => trans('operations::app.common.location'),
            'type' => 'string',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'receiver_name',
            'label' => trans('operations::app.receiving.received-by'),
            'type' => 'string',
        ]);

        $this->addColumn([
            'index' => 'received_at',
            'label' => trans('operations::app.receiving.received-at'),
            'type' => 'datetime',
            'filterable' => true,
            'filterable_type' => 'datetime_range',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'has_discrepancy',
            'label' => trans('operations::app.receiving.discrepancy'),
            'type' => 'boolean',
            'filterable' => true,
            'filterable_options' => [
                ['label' => trans('operations::app.common.yes'), 'value' => 1],
                ['label' => trans('operations::app.common.no'), 'value' => 0],
            ],
            'closure' => fn ($row) => $row->has_discrepancy
                ? '<span class="label-canceled">'.e(trans('operations::app.common.yes')).'</span>'
                : '<span class="label-active">'.e(trans('operations::app.common.no')).'</span>',
        ]);
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.receiving', 'admin.operations.receiving.view');
    }
}
