<?php

namespace FreshCart\Operations\Console\Commands;

use FreshCart\Operations\Models\StockPolicy;
use FreshCart\Operations\Services\StockLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Category\Models\Category;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Replaces Bagisto's demo catalog (fashion, electronics, bookings…) with the FreshCart
 * supermarket assortment in Resources/data/grocery-catalog.php.
 *
 * Products and categories are created through Bagisto's repositories and events so
 * the flat table, price and inventory indices are built exactly as from the admin.
 * Opening stock is booked through the FreshCart ledger with batches and expiry dates.
 *
 * Destructive: with --replace every existing product and category (except the root)
 * is deleted. It refuses to run when orders exist unless --force is given.
 */
class SeedGroceryCatalog extends Command
{
    protected $signature = 'fc:seed-grocery-catalog
        {--replace : Delete the existing products and categories first}
        {--force : Run even if orders exist, without confirmation}';

    protected $description = 'Load the FreshCart supermarket catalog (categories, products, opening stock, reorder policies, homepage).';

    public function handle(ProductRepository $productRepository, CategoryRepository $categoryRepository, StockLedgerService $ledger): int
    {
        $catalog = require dirname(__DIR__, 2).'/Resources/data/grocery-catalog.php';

        if (! $this->option('force')) {
            if (DB::table('orders')->exists()) {
                $this->error('Orders exist. Replacing the catalog would detach them from their products. Use --force to continue anyway.');

                return self::FAILURE;
            }

            if ($this->option('replace') && ! $this->confirm('Delete ALL existing products and categories and load the FreshCart catalog?')) {
                return self::INVALID;
            }
        }

        if ($this->option('replace')) {
            $this->removeDemoCatalog();
        }

        $categoryIds = $this->createCategories($catalog['categories'], $categoryRepository);

        $sources = $this->stockLocations();

        $bar = $this->output->createProgressBar(count($catalog['products']));

        foreach ($catalog['products'] as [$sku, $name, $categoryKey, $price, $cost, $weight, $shelfLife, $openingQty, $featured]) {
            if (DB::table('products')->where('sku', $sku)->exists()) {
                $bar->advance();

                continue;
            }

            $product = $productRepository->create([
                'type' => 'simple',
                'attribute_family_id' => 1,
                'sku' => $sku,
            ]);

            Event::dispatch('catalog.product.create.after', $product);

            $parentKey = $catalog['categories'][$categoryKey][1];
            $categoryName = $catalog['categories'][$categoryKey][0];

            $product = $productRepository->update([
                'channel' => core()->getDefaultChannelCode(),
                'locale' => 'en',
                'sku' => $sku,
                'product_number' => $sku,
                'name' => $name,
                'url_key' => Str::slug($name).'-'.Str::lower(Str::afterLast($sku, 'FC-')),
                'short_description' => '<p>'.e($name).' from FreshCart Supermarket.</p>',
                'description' => '<p>'.e($name).' — '.e($categoryName).'. Available for delivery and in-store pick-up at FreshCart branches in Calapan, Pinamalayan, San Jose, Batangas City and Puerto Princesa.</p>',
                'meta_title' => $name.' | FreshCart',
                'meta_keywords' => implode(', ', [$categoryName, 'grocery', 'supermarket', 'FreshCart']),
                'meta_description' => 'Buy '.$name.' at FreshCart Supermarket.',
                'price' => $price,
                'cost' => $cost,
                'weight' => $weight,
                'status' => 1,
                'visible_individually' => 1,
                'new' => $featured ? 1 : 0,
                'featured' => $featured ? 1 : 0,
                'guest_checkout' => 1,
                'manage_stock' => 1,
                'channels' => DB::table('channels')->pluck('id')->all(),
                'categories' => array_values(array_filter([$categoryIds[$categoryKey] ?? null, $parentKey ? ($categoryIds[$parentKey] ?? null) : null])),
            ], $product->id);

            Event::dispatch('catalog.product.update.after', $product);

            foreach ($sources as $sourceId => $share) {
                $qty = max(1, (int) round($openingQty * $share));

                $ledger->receive($product->id, $sourceId, $qty, [
                    'type' => 'receipt',
                    'reason' => 'Opening stock (catalog setup)',
                    'reference_no' => 'OPENING',
                    'batch_no' => 'OPEN-'.now()->format('Ymd'),
                    'expiry_date' => $shelfLife ? now()->addDays($shelfLife)->toDateString() : null,
                    'unit_cost' => $cost,
                ]);

                StockPolicy::updateOrCreate(
                    ['product_id' => $product->id, 'inventory_source_id' => $sourceId],
                    ['reorder_level' => max(1, (int) ceil($qty * 0.25)), 'min_stock' => (int) ceil($qty * 0.1), 'max_stock' => $qty * 2],
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->updateHomepage($catalog['categories'], $categoryIds);

        $this->info(count($catalog['products']).' products in '.count($catalog['categories']).' categories are ready.');
        $this->line('Run "php artisan optimize:clear" and refresh the storefront.');

        return self::SUCCESS;
    }

    protected function removeDemoCatalog(): void
    {
        $this->line('Removing demo products and categories…');

        DB::transaction(function () {
            // Variants first, then parents; product tables cascade from products.
            DB::table('products')->whereNotNull('parent_id')->delete();
            DB::table('products')->delete();

            foreach (Category::where('parent_id', 1)->get() as $category) {
                $category->delete();
            }
        });

        foreach (['product', 'category'] as $directory) {
            if (Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->deleteDirectory($directory);
            }
        }
    }

    /**
     * @return array<string, int> catalog key => category id
     */
    protected function createCategories(array $categories, CategoryRepository $categoryRepository): array
    {
        $ids = [];
        $position = 0;
        $priceAttributeId = DB::table('attributes')->where('code', 'price')->value('id');

        foreach ($categories as $key => [$name, $parentKey, $description]) {
            $existing = DB::table('category_translations')->where('slug', $key)->value('category_id');

            if ($existing) {
                $ids[$key] = (int) $existing;

                continue;
            }

            $category = $categoryRepository->create([
                'locale' => 'all',
                'name' => $name,
                'slug' => $key,
                'description' => '<p>'.e($description).'</p>',
                'meta_title' => $name.' | FreshCart',
                'meta_description' => $description,
                'meta_keywords' => $name,
                'status' => 1,
                'position' => ++$position,
                'display_mode' => 'products_and_description',
                'parent_id' => $parentKey ? $ids[$parentKey] : 1,
                'attributes' => array_filter([$priceAttributeId]),
            ]);

            $ids[$key] = $category->id;
        }

        return $ids;
    }

    /**
     * Opening stock share per location: warehouses get the full quantity, branches
     * a third. Without FreshCart locations configured, every active source gets the full quantity.
     *
     * @return array<int, float>
     */
    protected function stockLocations(): array
    {
        $sources = DB::table('inventory_sources')
            ->leftJoin('fc_locations', 'fc_locations.inventory_source_id', '=', 'inventory_sources.id')
            ->where('inventory_sources.status', 1)
            ->get(['inventory_sources.id', 'fc_locations.type']);

        return $sources->mapWithKeys(fn ($source) => [
            $source->id => $source->type === 'branch' ? 1 / 3 : 1.0,
        ])->all();
    }

    /**
     * Replace the fashion homepage blocks with grocery carousels and offers.
     */
    protected function updateHomepage(array $categories, array $categoryIds): void
    {
        $themeIds = DB::table('theme_customizations')->pluck('id', 'name');

        // Fashion banners and lookbook blocks do not fit a supermarket.
        DB::table('theme_customizations')
            ->whereIn('type', ['image_carousel', 'static_content'])
            ->whereNotIn('name', ['Offer Information'])
            ->update(['status' => 0]);

        $carousels = [
            'Mens Collection' => ['Fresh Produce', 'fresh-produce', 'name-asc'],
            'Womens Collection' => ['Pantry Staples', 'pantry', 'price-asc'],
            'Kids Collection' => ['Beverages', 'beverages', 'name-asc'],
            'Book Tickets' => ['Household Essentials', 'household', 'name-asc'],
        ];

        foreach ($carousels as $oldName => [$title, $categoryKey, $sort]) {
            if (! $id = $themeIds[$oldName] ?? null) {
                continue;
            }

            DB::table('theme_customizations')->where('id', $id)->update(['name' => $title, 'status' => 1]);

            DB::table('theme_customization_translations')->where('theme_customization_id', $id)->update([
                'options' => json_encode([
                    'title' => $title,
                    'filters' => ['sort' => $sort, 'limit' => 10, 'category_id' => $categoryIds[$categoryKey]],
                ]),
            ]);
        }

        if ($id = $themeIds['Offer Information'] ?? null) {
            $options = json_decode(DB::table('theme_customization_translations')->where('theme_customization_id', $id)->value('options') ?? '{}', true) ?: [];

            $options['css'] = str_replace('#E8EDFE', '#DCFCE7', $options['css'] ?? '');
            $options['html'] = '<div class="home-offer"><h1>Fresh from Mindoro farms every morning · Free delivery on orders over ₱1,500</h1></div>';

            DB::table('theme_customization_translations')->where('theme_customization_id', $id)->update(['options' => json_encode($options)]);
        }

        if ($id = $themeIds['Services Content'] ?? null) {
            DB::table('theme_customization_translations')->where('theme_customization_id', $id)->update([
                'options' => json_encode(['services' => [
                    ['title' => 'Same-Day Delivery', 'description' => 'Order before 2 PM for delivery today within the city', 'service_icon' => 'icon-truck'],
                    ['title' => 'Freshness Guarantee', 'description' => 'Not fresh? We replace it, no questions asked', 'service_icon' => 'icon-product'],
                    ['title' => 'Pay Your Way', 'description' => 'Cash on delivery, cards and e-wallets accepted', 'service_icon' => 'icon-dollar-sign'],
                    ['title' => 'Five Branches', 'description' => 'Calapan · Pinamalayan · San Jose · Batangas City · Puerto Princesa', 'service_icon' => 'icon-support'],
                ]]),
            ]);
        }
    }
}
