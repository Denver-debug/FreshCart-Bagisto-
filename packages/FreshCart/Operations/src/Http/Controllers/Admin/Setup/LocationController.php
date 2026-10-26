<?php

namespace FreshCart\Operations\Http\Controllers\Admin\Setup;

use FreshCart\Operations\Http\Controllers\Admin\Controller;
use FreshCart\Operations\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\User\Models\Admin;

/**
 * Classifies Bagisto inventory sources as warehouse or branch, marks the central
 * warehouse, and assigns admins to the locations they work at.
 */
class LocationController extends Controller
{
    public function index(): View
    {
        $sources = DB::table('inventory_sources')
            ->leftJoin('fc_locations', 'fc_locations.inventory_source_id', '=', 'inventory_sources.id')
            ->orderBy('inventory_sources.priority')
            ->orderBy('inventory_sources.name')
            ->select('inventory_sources.id', 'inventory_sources.code', 'inventory_sources.name', 'inventory_sources.status', 'fc_locations.type', 'fc_locations.is_central')
            ->get();

        $admins = Admin::with('role')->orderBy('name')->get();

        $assignments = DB::table('fc_admin_locations')
            ->get()
            ->groupBy('admin_id')
            ->map(fn ($rows) => $rows->pluck('inventory_source_id')->map(fn ($id) => (int) $id)->all());

        return view('operations::admin.setup.locations.index', compact('sources', 'admins', 'assignments'));
    }

    public function update()
    {
        $validated = request()->validate([
            'locations' => 'required|array',
            'locations.*.type' => 'required|in:warehouse,branch',
            'central' => 'nullable|integer|exists:inventory_sources,id',
            'assignments' => 'nullable|array',
            'assignments.*' => 'array',
            'assignments.*.*' => 'integer|exists:inventory_sources,id',
        ]);

        $sourceIds = DB::table('inventory_sources')->pluck('id')->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($validated, $sourceIds) {
            foreach ($validated['locations'] as $sourceId => $location) {
                if (! $sourceIds->contains((int) $sourceId)) {
                    continue;
                }

                Location::updateOrCreate(
                    ['inventory_source_id' => (int) $sourceId],
                    [
                        'type' => $location['type'],
                        'is_central' => (int) ($validated['central'] ?? 0) === (int) $sourceId && $location['type'] === Location::TYPE_WAREHOUSE,
                    ],
                );
            }

            $adminIds = Admin::pluck('id');

            DB::table('fc_admin_locations')->whereIn('admin_id', $adminIds)->delete();

            foreach ($validated['assignments'] ?? [] as $adminId => $locationIds) {
                if (! $adminIds->contains((int) $adminId)) {
                    continue;
                }

                DB::table('fc_admin_locations')->insert(
                    collect($locationIds)->unique()->map(fn ($locationId) => [
                        'admin_id' => (int) $adminId,
                        'inventory_source_id' => (int) $locationId,
                    ])->all()
                );
            }
        });

        session()->flash('success', trans('operations::app.locations.saved'));

        return redirect()->route('admin.operations.locations.index');
    }
}
