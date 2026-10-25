<?php

namespace FreshCart\Operations\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Branch-level data scoping for admins.
 *
 * Users holding `operations.scope.all_locations` (or a full-access role) see every
 * location. Everyone else is limited to the inventory sources assigned to them
 * under Operations → Setup → Locations.
 */
class LocationScope
{
    public function adminId(): ?int
    {
        return auth()->guard('admin')->id();
    }

    public function seesAllLocations(): bool
    {
        return bouncer()->hasPermission('operations.scope.all_locations');
    }

    /**
     * Inventory source ids the current admin may access, or null for "all".
     */
    public function ids(): ?array
    {
        if ($this->seesAllLocations()) {
            return null;
        }

        return $this->assignedIds($this->adminId());
    }

    public function assignedIds(?int $adminId): array
    {
        if (! $adminId) {
            return [];
        }

        return DB::table('fc_admin_locations')
            ->where('admin_id', $adminId)
            ->pluck('inventory_source_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function allows(?int $inventorySourceId): bool
    {
        if ($inventorySourceId === null) {
            return false;
        }

        $ids = $this->ids();

        return $ids === null || in_array($inventorySourceId, $ids, true);
    }

    /**
     * Abort with 403 unless the admin may act on the location.
     */
    public function authorize(?int $inventorySourceId): void
    {
        abort_unless($this->allows($inventorySourceId), 403, trans('operations::app.errors.location-forbidden'));
    }

    /**
     * Abort with 403 unless at least one of the locations is accessible.
     */
    public function authorizeAny(array $inventorySourceIds): void
    {
        foreach ($inventorySourceIds as $id) {
            if ($this->allows($id)) {
                return;
            }
        }

        abort(403, trans('operations::app.errors.location-forbidden'));
    }

    /**
     * Accessible, active inventory sources as id => name.
     */
    public function options(): Collection
    {
        $query = DB::table('inventory_sources')
            ->where('status', 1)
            ->orderBy('priority')
            ->orderBy('name');

        if (($ids = $this->ids()) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('name', 'id');
    }

    /**
     * Every active inventory source as id => name (for destination pickers).
     */
    public function allOptions(): Collection
    {
        return DB::table('inventory_sources')
            ->where('status', 1)
            ->orderBy('priority')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * The central warehouse source id, falling back to the first warehouse.
     */
    public function centralWarehouseId(): ?int
    {
        $id = DB::table('fc_locations')
            ->orderByDesc('is_central')
            ->where('type', 'warehouse')
            ->value('inventory_source_id');

        return $id ? (int) $id : null;
    }

    public function warehouseIds(): array
    {
        return DB::table('fc_locations')
            ->where('type', 'warehouse')
            ->pluck('inventory_source_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
