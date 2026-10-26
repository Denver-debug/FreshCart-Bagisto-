<?php

namespace FreshCart\Operations\DataGrids\Pricing;

use FreshCart\Operations\DataGrids\OperationsDataGrid;
use FreshCart\Operations\Models\PromotionProposal;
use Illuminate\Support\Facades\DB;

class PromotionDataGrid extends OperationsDataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('fc_promotion_proposals as promo')
            ->leftJoin('admins', 'admins.id', '=', 'promo.requested_by')
            ->select(
                'promo.id',
                'promo.reference',
                'promo.name',
                'promo.department',
                'promo.discount_type',
                'promo.discount_amount',
                'promo.starts_on',
                'promo.ends_on',
                'promo.status',
                'admins.name as requester_name',
            );

        $this->addFilter('reference', 'promo.reference');
        $this->addFilter('name', 'promo.name');
        $this->addFilter('status', 'promo.status');
        $this->addFilter('starts_on', 'promo.starts_on');
        $this->addFilter('ends_on', 'promo.ends_on');

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
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'department',
            'label' => trans('operations::app.promotions.department'),
            'type' => 'string',
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'discount_amount',
            'label' => trans('operations::app.promotions.discount'),
            'type' => 'decimal',
            'sortable' => true,
            'closure' => fn ($row) => $row->discount_type === PromotionProposal::DISCOUNT_PERCENT
                ? e(rtrim(rtrim(number_format((float) $row->discount_amount, 2), '0'), '.').'%')
                : core()->formatBasePrice((float) $row->discount_amount),
        ]);

        $this->addDateColumn('starts_on', 'operations::app.promotions.starts-on');
        $this->addDateColumn('ends_on', 'operations::app.promotions.ends-on');

        $this->addStatusColumn([
            PromotionProposal::STATUS_DRAFT,
            PromotionProposal::STATUS_SUBMITTED,
            PromotionProposal::STATUS_SCHEDULED,
            PromotionProposal::STATUS_ACTIVE,
            PromotionProposal::STATUS_EXPIRED,
            PromotionProposal::STATUS_REJECTED,
            PromotionProposal::STATUS_CANCELLED,
        ]);

        $this->addColumn([
            'index' => 'requester_name',
            'label' => trans('operations::app.common.requested-by'),
            'type' => 'string',
        ]);
    }

    public function prepareActions()
    {
        $this->addViewAction('operations.promotions', 'admin.operations.promotions.view');
    }
}
