<?php

namespace FreshCart\Operations\DataGrids\Quality;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\Incident;
use Illuminate\Support\Facades\DB;

class IncidentDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_incidents as inc')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'inc.inventory_source_id')
            ->leftJoin('admins', 'admins.id', '=', 'inc.reported_by')
            ->select(
                'inc.id',
                'inc.reference',
                'inc.sku',
                'inc.name',
                'inc.qty',
                'inc.incident_type',
                'inc.classification',
                'inc.status',
                'inc.expiry_date',
                'inc.created_at',
                'inventory_sources.name as location_name',
                'admins.name as reporter_name',
            );

        $this->addFilter('reference', 'inc.reference');
        $this->addFilter('sku', 'inc.sku');
        $this->addFilter('incident_type', 'inc.incident_type');
        $this->addFilter('classification', 'inc.classification');
        $this->addFilter('status', 'inc.status');
        $this->addFilter('created_at', 'inc.created_at');
        $this->addFilter('location_name', 'inventory_sources.name');

        return $this->scopeLocations($query, 'inc.inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'sku',
            'label' => trans('operations::app.common.sku'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('operations::app.common.product'),
            'type' => 'string',
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'location_name',
            'label' => trans('operations::app.common.location'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'qty',
            'label' => trans('operations::app.common.qty'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'incident_type',
            'label' => trans('operations::app.incidents.type'),
            'type' => 'string',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(Incident::TYPES)->map(fn ($type) => ['label' => trans('operations::app.incidents.types.'.$type), 'value' => $type])->all(),
            'sortable' => true,
            'closure' => fn ($row) => e(trans('operations::app.incidents.types.'.$row->incident_type)),
        ]);

        $this->addColumn([
            'index' => 'classification',
            'label' => trans('operations::app.incidents.classification'),
            'type' => 'string',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(Incident::CLASSIFICATIONS)->map(fn ($value) => ['label' => trans('operations::app.incidents.classifications.'.$value), 'value' => $value])->all(),
            'closure' => fn ($row) => $row->classification ? e(trans('operations::app.incidents.classifications.'.$row->classification)) : '—',
        ]);

        $this->addStatusColumn([Incident::STATUS_QUARANTINED, Incident::STATUS_INSPECTED, Incident::STATUS_CLASSIFIED, Incident::STATUS_CLOSED]);

        $this->addColumn([
            'index' => 'reporter_name',
            'label' => trans('operations::app.incidents.reported-by'),
            'type' => 'string',
        ]);

        $this->addDateColumn('created_at', 'operations::app.common.created-at');
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.incidents', 'admin.operations.incidents.view');
    }
}
