<?php

namespace FreshCart\Operations\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Inventory\Models\InventorySourceProxy;

trait BelongsToLocation
{
    /**
     * The inventory source (warehouse or branch) the record belongs to.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventorySourceProxy::modelClass(), 'inventory_source_id');
    }

    /**
     * Limit the query to the given inventory source ids; null means no restriction.
     */
    public function scopeForLocations(Builder $query, ?array $locationIds): Builder
    {
        if ($locationIds === null) {
            return $query;
        }

        return $query->whereIn($this->getTable().'.inventory_source_id', $locationIds);
    }
}
