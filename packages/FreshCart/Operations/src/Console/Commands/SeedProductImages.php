<?php

namespace FreshCart\Operations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Product\Repositories\ProductImageRepository;

/**
 * Gives every FreshCart product a base image.
 *
 * Real photos are taken from a drop folder, named after the SKU (FC-VEG-001.jpg, .jpeg,
 * .png or .webp). Products without a photo get a generated, category-coloured placeholder
 * tile with the product name, so the storefront never shows Bagisto's empty-image icon.
 *
 * Images are converted to WebP and stored exactly like admin uploads (product/{id}/…).
 * Products that already have images are skipped unless --replace is given.
 */
class SeedProductImages extends Command
{
    protected $signature = 'fc:seed-product-images
        {--dir= : Folder with photos named by SKU (default: storage/app/freshcart-product-photos)}
        {--replace : Replace the images of products that already have some}
        {--photos-only : Only use real photos, never generate placeholders}';

    protected $description = 'Attach photos (by SKU) or generated placeholder images to FreshCart products.';

    /**
     * Placeholder colours per SKU group: [background, accent].
     */
    protected const PALETTE = [
        'VEG' => ['#ecfdf5', '#16a34a'], 'FRU' => ['#fff7ed', '#f97316'],
        'PRK' => ['#fdf2f8', '#db2777'], 'CHK' => ['#fefce8', '#ca8a04'],
        'BEF' => ['#fef2f2', '#b91c1c'], 'FSH' => ['#eff6ff', '#2563eb'],
        'MLK' => ['#f0f9ff', '#0284c7'], 'CHS' => ['#fffbeb', '#d97706'],
        'EGG' => ['#fefce8', '#a16207'], 'BKR' => ['#fff7ed', '#c2410c'],
        'RCE' => ['#f7fee7', '#65a30d'], 'NDL' => ['#fffbeb', '#ea580c'],
        'CAN' => ['#fef2f2', '#dc2626'], 'CND' => ['#faf5ff', '#7c3aed'],
        'COK' => ['#fefce8', '#ca8a04'], 'SNK' => ['#fff1f2', '#e11d48'],
        'COF' => ['#fafaf9', '#78350f'], 'DRK' => ['#fff7ed', '#ea580c'],
        'WTR' => ['#ecfeff', '#0891b2'], 'FRZ' => ['#eef2ff', '#4f46e5'],
        'LND' => ['#f0f9ff', '#0369a1'], 'CLN' => ['#f0fdfa', '#0d9488'],
        'PRC' => ['#fdf4ff', '#c026d3'], 'BBY' => ['#f0f9ff', '#0ea5e9'],
    ];

    protected const SIZE = 800;

    public function handle(ProductImageRepository $productImageRepository): int
    {
        $dir = $this->option('dir') ?: storage_path('app/freshcart-product-photos');

        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! $this->option('photos-only') && ! is_file($font)) {
            $this->error("Font not found for placeholders: {$font}");

            return self::FAILURE;
        }

        $products = DB::table('products')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')->where('product_flat.locale', 'en');
            })
            ->where('products.sku', 'like', 'FC-%')
            ->select('products.id', 'products.sku', 'product_flat.name')
            ->orderBy('products.id')
            ->get();

        $counts = ['photo' => 0, 'placeholder' => 0, 'skipped' => 0];

        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product) {
            $hasImages = DB::table('product_images')->where('product_id', $product->id)->exists();

            $photo = $this->findPhoto($dir, $product->sku);

            if (($hasImages && ! $this->option('replace')) || (! $photo && $this->option('photos-only'))) {
                $counts['skipped']++;
                $bar->advance();

                continue;
            }

            $webp = $photo
                ? (string) image_manager()->read($photo)->encodeByExtension('webp')
                : $this->placeholder($product->sku, $product->name ?: $product->sku, $font);

            if ($hasImages) {
                foreach (DB::table('product_images')->where('product_id', $product->id)->get() as $image) {
                    Storage::disk('public')->delete($image->path);

                    $productImageRepository->delete($image->id);
                }
            }

            $path = 'product/'.$product->id.'/'.Str::random(40).'.webp';

            Storage::disk('public')->put($path, $webp);

            $productImageRepository->create([
                'type' => 'images',
                'path' => $path,
                'product_id' => $product->id,
                'position' => 1,
            ]);

            $counts[$photo ? 'photo' : 'placeholder']++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Photos: {$counts['photo']}, placeholders: {$counts['placeholder']}, skipped: {$counts['skipped']}.");

        return self::SUCCESS;
    }

    protected function findPhoto(string $dir, string $sku): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP'] as $extension) {
            if (is_file($file = $dir.DIRECTORY_SEPARATOR.$sku.'.'.$extension)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Square tile: tinted background, accent circle with the item's initial,
     * the wrapped product name and a small FreshCart footer. Returns WebP bytes.
     */
    protected function placeholder(string $sku, string $name, string $font): string
    {
        [$background, $accent] = self::PALETTE[explode('-', $sku)[1] ?? ''] ?? ['#f0fdf4', '#16a34a'];

        $size = self::SIZE;
        $image = imagecreatetruecolor($size, $size);
        imageantialias($image, true);

        $bg = $this->color($image, $background);
        $fg = $this->color($image, $accent);
        $ink = $this->color($image, '#1f2937');
        $muted = $this->color($image, '#6b7280');
        $white = $this->color($image, '#ffffff');

        imagefilledrectangle($image, 0, 0, $size, $size, $bg);

        imagefilledellipse($image, $size / 2, 270, 300, 300, $fg);
        $this->centered($image, 130, 270 + 65, $white, $font, Str::upper(Str::substr($name, 0, 1)));

        $lines = explode("\n", wordwrap($name, 22, "\n", true));
        $y = 520;

        foreach (array_slice($lines, 0, 3) as $line) {
            $this->centered($image, 38, $y, $ink, $font, $line);
            $y += 56;
        }

        $this->centered($image, 22, $size - 50, $muted, $font, 'FreshCart Supermarket');

        ob_start();
        imagewebp($image, null, 90);
        imagedestroy($image);

        return ob_get_clean();
    }

    protected function centered($image, int $fontSize, int $baseline, int $color, string $font, string $text): void
    {
        $box = imagettfbbox($fontSize, 0, $font, $text);

        imagettftext($image, $fontSize, 0, (int) ((self::SIZE - ($box[2] - $box[0])) / 2), $baseline, $color, $font, $text);
    }

    protected function color($image, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocate($image, $r, $g, $b);
    }
}
