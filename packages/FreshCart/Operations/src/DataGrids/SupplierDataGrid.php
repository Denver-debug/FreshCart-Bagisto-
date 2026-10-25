<?php

namespace FreshCart\Operations\DataGrids;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class SupplierDataGrid extends DataGrid
{
    public function prepareQueryBuilder()
    {
        return DB::table('fc_suppliers')
            ->whereNull('deleted_at')
            ->select('id', 'code', 'name', 'contact_name', 'email', 'phone', 'lead_time_days', 'status');
    }

    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'code',
            'label' => trans('operations::app.suppliers.code'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('operations::app.suppliers.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'contact_name',
            'label' => trans('operations::app.suppliers.contact-name'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'email',
            'label' => trans('operations::app.suppliers.email'),
            'type' => 'string',
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'phone',
            'label' => trans('operations::app.suppliers.phone'),
            'type' => 'string',
        ]);

        $this->addColumn([
            'index' => 'lead_time_days',
            'label' => trans('operations::app.suppliers.lead-time'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('operations::app.common.status'),
            'type' => 'boolean',
            'filterable' => true,
            'filterable_options' => [
                ['label' => trans('operations::app.common.active'), 'value' => 1],
                ['label' => trans('operations::app.common.inactive'), 'value' => 0],
            ],
            'sortable' => true,
            'closure' => fn ($row) => $row->status
                ? '<span class="label-active">'.e(trans('operations::app.common.active')).'</span>'
                : '<span class="label-closed">'.e(trans('operations::app.common.inactive')).'</span>',
        ]);
    }

    public function prepareActions()
    {
        if (bouncer()->hasPermission('operations.setup.suppliers.edit')) {
            $this->addAction([
                'icon' => 'icon-edit',
                'title' => trans('operations::app.common.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.operations.suppliers.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('operations.setup.suppliers.delete')) {
            $this->addAction([
                'icon' => 'icon-delete',
                'title' => trans('operations::app.common.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.operations.suppliers.delete', $row->id),
            ]);
        }
    }
}
