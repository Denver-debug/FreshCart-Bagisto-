<?php

use FreshCart\Integrations\Http\Controllers\FrappeWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Machine-to-machine endpoints: no session, no CSRF, authenticated by the
 * HMAC signature instead.
 */
Route::post('integrations/novacore-hr', FrappeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('freshcart.integrations.novacore_hr.webhook');
