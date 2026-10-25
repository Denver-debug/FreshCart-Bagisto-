<?php

namespace FreshCart\Operations\Tests\Concerns;

use FreshCart\Operations\Models\Location;
use Illuminate\Support\Facades\DB;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\Inventory\Models\InventorySource;
use Webkul\Product\Models\Product;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

trait OperationsTestBench
{
    /**
     * Create an active inventory source sold through the default channel and
     * register it as a FreshCart location.
     */
    public function makeLocation(string $type = Location::TYPE_BRANCH, bool $central = false): InventorySource
    {
        $source = InventorySource::factory()->create(['status' => 1]);

        DB::table('channel_inventory_sources')->insertOrIgnore([
            'channel_id' => core()->getDefaultChannel()->id,
            'inventory_source_id' => $source->id,
        ]);

        Location::create([
            'inventory_source_id' => $source->id,
            'type' => $type,
            'is_central' => $central,
        ]);

        return $source;
    }

    /**
     * Create a simple product with exact on-hand quantities per location.
     *
     * @param  array<int, int>  $stock  inventory source id => qty
     */
    public function makeProduct(array $stock = []): Product
    {
        $product = (new ProductFaker)->getSimpleProductFactory()->create();

        DB::table('product_inventories')->where('product_id', $product->id)->delete();

        foreach ($stock as $sourceId => $qty) {
            DB::table('product_inventories')->insert([
                'product_id' => $product->id,
                'inventory_source_id' => $sourceId,
                'vendor_id' => 0,
                'qty' => $qty,
            ]);
        }

        DB::table('product_ordered_inventories')->where('product_id', $product->id)->delete();

        return $product->fresh();
    }

    /**
     * Log in as an admin whose custom role grants exactly these permissions.
     */
    public function actingAsOperator(array $permissions, array $locationIds = [], ?string $name = null): Admin
    {
        $role = Role::factory()->create([
            'permission_type' => 'custom',
            'permissions' => array_values(array_unique(array_merge(['operations'], $permissions))),
        ]);

        $admin = Admin::factory()->create([
            'role_id' => $role->id,
            'status' => 1,
            'name' => $name ?? 'Operator '.$role->id,
        ]);

        foreach ($locationIds as $locationId) {
            DB::table('fc_admin_locations')->insert([
                'admin_id' => $admin->id,
                'inventory_source_id' => $locationId,
            ]);
        }

        $this->loginAsAdmin($admin);

        return $admin;
    }

    /**
     * Store a FreshCart setting (Configure → Operations) for the test.
     */
    public function setOperationsConfig(string $key, mixed $value): void
    {
        DB::table('core_config')->where('code', 'operations.'.$key)->delete();

        DB::table('core_config')->insert([
            'code' => 'operations.'.$key,
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function sellable(int $productId, int $sourceId): int
    {
        return (int) DB::table('product_inventories')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->value('qty');
    }

    public function held(int $productId, int $sourceId, string $bucket): int
    {
        return (int) DB::table('fc_stock_holds')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->where('bucket', $bucket)
            ->sum('qty');
    }

    public function channelIndexQty(int $productId): int
    {
        return (int) DB::table('product_inventory_indices')
            ->where('product_id', $productId)
            ->where('channel_id', core()->getDefaultChannel()->id)
            ->value('qty');
    }
}
