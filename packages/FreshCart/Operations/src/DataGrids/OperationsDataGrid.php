<?php

namespace FreshCart\Operations\DataGrids;

use FreshCart\Operations\Support\LocationScope;
use FreshCart\Operations\Support\StatusBadge;
use Illuminate\Database\Query\Builder;
use Webkul\DataGrid\DataGrid;

/**
 * Shared helpers: branch scoping and translated status badges.
 */
abstract class OperationsDataGrid extends DataGrid
{
    /**
     * Restrict a query to the admin's locations (no-op for all-location users).
     */
    protected function scopeLocations(Builder $query, string ...$columns): Builder
    {
        $ids = app(LocationScope::class)->ids();

        if ($ids === null) {
            return $query;
        }

        return $query->where(function ($query) use ($columns, $ids) {
            foreach ($columns as $column) {
                $query->orWhereIn($column, $ids);
            }
        });
    }

    protected function addReferenceColumn(string $label = 'operations::app.common.reference'): void
    {
        $this->addColumn([
            'index' => 'reference',
            'label' => trans($label),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);
    }

    protected function addStatusColumn(array $statuses, string $index = 'status'): void
    {
        $this->addColumn([
            'index' => $index,
            'label' => trans('operations::app.common.status'),
            'type' => 'string',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect($statuses)->map(fn ($status) => [
                'label' => trans('operations::app.statuses.'.$status),
                'value' => $status,
            ])->values()->all(),
            'sortable' => true,
            'closure' => fn ($row) => StatusBadge::html($row->{$index}),
        ]);
    }

    protected function addDateColumn(string $index, string $label): void
    {
        $this->addColumn([
            'index' => $index,
            'label' => trans($label),
            'type' => 'date',
            'filterable' => true,
            'filterable_type' => 'date_range',
            'sortable' => true,
        ]);
    }

    protected function addViewAction(string $permission, string $route): void
    {
        if (bouncer()->hasPermission($permission)) {
            $this->addAction([
                'icon' => 'icon-view',
                'title' => trans('operations::app.common.view'),
                'method' => 'GET',
                'url' => fn ($row) => route($route, $row->id),
            ]);
        }
    }
}
