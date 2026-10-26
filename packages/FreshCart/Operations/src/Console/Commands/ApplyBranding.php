<?php

namespace FreshCart\Operations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Applies FreshCart branding through Bagisto's own settings (no core files change):
 * admin logo and favicon (Configure → General → Design), each channel's storefront
 * logo, favicon, name and home-page SEO title. Safe to run repeatedly.
 */
class ApplyBranding extends Command
{
    protected $signature = 'fc:apply-branding
        {--name=FreshCart Supermarket : Store / channel display name}
        {--keep-channel-name : Do not rename channels}';

    protected $description = 'Apply FreshCart logos, favicon and store name using Bagisto configuration.';

    public function handle(): int
    {
        $disk = Storage::disk('public');

        foreach (['logo.svg', 'favicon.svg'] as $file) {
            $disk->put('freshcart/'.$file, file_get_contents(dirname(__DIR__, 2).'/Resources/branding/'.$file));
        }

        foreach ([
            'general.design.admin_logo.logo_image' => 'freshcart/logo.svg',
            'general.design.admin_logo.favicon' => 'freshcart/favicon.svg',
        ] as $code => $value) {
            DB::table('core_config')->updateOrInsert(
                ['code' => $code, 'channel_code' => null, 'locale_code' => null],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        DB::table('channels')->update([
            'logo' => 'freshcart/logo.svg',
            'favicon' => 'freshcart/favicon.svg',
        ]);

        if (! $this->option('keep-channel-name')) {
            $name = $this->option('name');

            foreach (DB::table('channel_translations')->get() as $translation) {
                $seo = json_decode($translation->home_seo ?? '{}', true) ?: [];

                $seo['meta_title'] = $name;
                $seo['meta_description'] = $seo['meta_description'] ?? $name;

                if (str_contains(strtolower($seo['meta_description']), 'demo store')) {
                    $seo['meta_description'] = $name.' online grocery';
                }

                if (str_contains(strtolower($seo['meta_keywords'] ?? ''), 'demo store')) {
                    $seo['meta_keywords'] = 'FreshCart, supermarket, grocery, Oriental Mindoro';
                }

                DB::table('channel_translations')->where('id', $translation->id)->update([
                    'name' => $name,
                    'home_seo' => json_encode($seo),
                ]);
            }
        }

        $this->info('FreshCart branding applied. Run "php artisan optimize:clear" and refresh the browser.');

        return self::SUCCESS;
    }
}
