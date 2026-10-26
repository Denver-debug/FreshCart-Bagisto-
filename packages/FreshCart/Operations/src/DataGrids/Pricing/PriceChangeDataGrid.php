<?php

namespace FreshCart\Operations\DataGrids\Pricing;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\PriceChangeBatch;
use Illuminate\Support\Facades\DB;

class PriceChangeDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_price_change_batches as pcb')
            ->leftJoin('admins', 'admins.id', '=', 'pcb.requested_by')
            ->leftJoinSub(
                DB::table('fc_price_change_items')->selectRaw('price_change_batch_id, COUNT(*) as lines_count')->groupBy('price_change_batch_id'),
                'lines',
                'lines.price_change_batch_id',
                '=',
                'pcb.id'
            )
            ->select('pcb.id', 'pcb.reference', 'pcb.name', 'pcb.status', 'pcb.effective_at', 'pcb.applied_at', 'lines.lines_count', 'admins.name as requester_name');

        $this->addFilter('reference', 'pcb.reference');
        $this->addFilter('name', 'pcb.name');
        $this->addFilter('status', 'pcb.status');
        $this->addFilter('effective_at', 'pcb.effective_at');

        return $query;
    }

    public function prepareColumns()
    {
        $this->addReferenceColumn();

        $this->addColumn([
            'index' => 'name',
            'label' => trans('operations::app.promotions.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'lines_count',
            'label' => trans('operations::app.price-changes.lines'),
            'type' => 'integer',
        ]);

        $this->addColumn([
            'index' => 'effective_at',
            'label' => trans('operations::app.price-changes.effective-at'),
            'type' => 'datetime',
            'sortable' => true,
        ]);

        $this->addStatusColumn([
            PriceChangeBatch::STATUS_DRAFT,
            PriceChangeBatch::STATUS_SUBMITTED,
            PriceChangeBatch::STATUS_APPROVED,
            PriceChangeBatch::STATUS_APPLIED,
            PriceChangeBatch::STATUS_PARTIALLY_APPLIED,
            PriceChangeBatch::STATUS_REJECTED,
            PriceChangeBatch::STATUS_CANCELLED,
        ]);

        $this->addColumn([
            'index' => 'requester_name',
            'label' => trans('operations::app.common.requested-by'),
            'type' => 'string',
        ]);
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.price_changes', 'admin.operations.price_changes.view');
    }
}
