<?php

namespace FreshCart\Operations\DataGrids\Procurement;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_purchase_orders as po')
            ->join('fc_suppliers', 'fc_suppliers.id', '=', 'po.supplier_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'po.inventory_source_id')
            ->select(
                'po.id',
                'po.reference',
                'po.status',
                'po.order_date',
                'po.expected_delivery_date',
                'po.subtotal',
                'fc_suppliers.name as supplier_name',
                'inventory_sources.name as location_name',
            );

        $this->addFilter('reference', 'po.reference');
        $this->addFilter('status', 'po.status');
        $this->addFilter('order_date', 'po.order_date');
        $this->addFilter('expected_delivery_date', 'po.expected_delivery_date');
        $this->addFilter('supplier_name', 'fc_suppliers.name');
        $this->addFilter('location_name', 'inventory_sources.name');

        return $this->scopeLocations($query, 'po.inventory_source_id');
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
            'label' => trans('operations::app.purchase-orders.deliver-to'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addDateColumn('order_date', 'operations::app.purchase-orders.order-date');
        $this->addDateColumn('expected_delivery_date', 'operations::app.purchase-orders.expected-delivery');

        $this->addColumn([
            'index' => 'subtotal',
            'label' => trans('operations::app.common.total'),
            'type' => 'decimal',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatBasePrice((float) $row->subtotal),
        ]);

        $this->addStatusColumn([
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_ISSUED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_CLOSED,
            PurchaseOrder::STATUS_CANCELLED,
        ]);
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.purchase_orders', 'admin.operations.purchase_orders.view');
    }
}
