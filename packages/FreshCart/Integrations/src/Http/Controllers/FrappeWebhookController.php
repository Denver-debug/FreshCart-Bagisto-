<?php

namespace FreshCart\Integrations\Http\Controllers;

use FreshCart\Integrations\Services\FrappeInbound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Receives NovaCore HR "ERP Event Subscription" webhooks:
 * body {"id", "event", "occurred_at", "data"} signed as
 * hex(HMAC-SHA256(secret, raw body)) in X-NovaCore-Signature.
 *
 * Handlers are idempotent, so NovaCore's retries and duplicate deliveries
 * are harmless.
 */
class FrappeWebhookController extends Controller
{
    public function __invoke(Request $request, FrappeInbound $inbound): JsonResponse
    {
        $secret = (string) config('freshcart-integrations.frappe.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret is not configured.'], 503);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('X-NovaCore-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = (string) ($request->header('X-NovaCore-Event') ?: $request->json('event'));
        $data = $request->json('data');

        $result = $inbound->event($event, is_array($data) ? $data : []);

        return response()->json(['result' => $result]);
    }
}
