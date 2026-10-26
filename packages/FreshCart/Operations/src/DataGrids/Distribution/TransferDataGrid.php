<?php

namespace FreshCart\Operations\DataGrids\Distribution;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\Transfer;
use Illuminate\Support\Facades\DB;

class TransferDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_transfers as tr')
            ->join('inventory_sources as source', 'source.id', '=', 'tr.source_inventory_source_id')
            ->join('inventory_sources as destination', 'destination.id', '=', 'tr.destination_inventory_source_id')
            ->select(
                'tr.id',
                'tr.reference',
                'tr.type',
                'tr.status',
                'tr.priority',
                'tr.has_discrepancy',
                'tr.scheduled_dispatch_at',
                'tr.expected_arrival_at',
                'tr.dispatched_at',
                'tr.created_at',
                'source.name as source_name',
                'destination.name as destination_name',
            );

        $this->addFilter('reference', 'tr.reference');
        $this->addFilter('type', 'tr.type');
        $this->addFilter('status', 'tr.status');
        $this->addFilter('source_name', 'source.name');
        $this->addFilter('destination_name', 'destination.name');
        $this->addFilter('created_at', 'tr.created_at');
        $this->addFilter('has_discrepancy', 'tr.has_discrepancy');

        return $this->scopeLocations($query, 'tr.source_inventory_source_id', 'tr.destination_inventory_source_id');
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'type',
            'label' => trans('operations::app.transfers.type'),
            'type' => 'string',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(Transfer::TYPES)->map(fn ($type) => [
                'label' => trans('operations::app.transfers.types.'.$type),
                'value' => $type,
            ])->all(),
            'sortable' => true,
            'closure' => fn ($row) => e(trans('operations::app.transfers.types.'.$row->type)),
        ]);

        $this->addColumn([
            'index' => 'source_name',
            'label' => trans('operations::app.common.source'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'destination_name',
            'label' => trans('operations::app.common.destination'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'expected_arrival_at',
            'label' => trans('operations::app.transfers.expected-arrival'),
            'type' => 'datetime',
            'sortable' => true,
        ]);

        $this->addStatusColumn([
            Transfer::STATUS_DRAFT,
            Transfer::STATUS_PENDING_APPROVAL,
            Transfer::STATUS_APPROVED,
            Transfer::STATUS_PICKING,
            Transfer::STATUS_IN_TRANSIT,
            Transfer::STATUS_PARTIALLY_RECEIVED,
            Transfer::STATUS_RECEIVED,
            Transfer::STATUS_CLOSED_WITH_DISCREPANCY,
            Transfer::STATUS_REJECTED,
            Transfer::STATUS_CANCELLED,
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
                : '',
        ]);

        $this->addDateColumn('created_at', 'operations::app.common.created-at');
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.transfers', 'admin.operations.transfers.view');
    }
}
