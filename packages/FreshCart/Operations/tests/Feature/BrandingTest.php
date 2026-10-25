<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

it('shows FreshCart branding on the admin login page and the storefront', function () {
    // Writes the two SVGs to the public disk (idempotent); DB changes roll back with the test.
    Artisan::call('fc:apply-branding');

    Storage::disk('public')->assertExists('freshcart/logo.svg');

    get(route('admin.session.create'))
        ->assertOk()
        ->assertSee('FreshCart Supermarket Corp.')
        ->assertSee('freshcart/logo.svg')
        ->assertDontSee('an open-source project by');

    get(route('shop.home.index'))
        ->assertOk()
        ->assertSee('FreshCart Supermarket Corp.')
        ->assertSee('freshcart/logo.svg')
        ->assertDontSee('Webkul Software');
});
