<?php

namespace FreshCart\Integrations\Services;

use FreshCart\Integrations\Models\OutboxMessage;

/**
 * Queues work for the external systems. One row per (action, subject): queuing
 * the same subject twice is a no-op unless $refresh asks for it to be re-sent
 * (e.g. a supplier was edited after it was already pushed).
 */
class Outbox
{
    public function record(string $action, string $subjectType, string|int $subjectId, ?array $payload = null, bool $refresh = false): OutboxMessage
    {
        $keys = ['action' => $action, 'subject_type' => $subjectType, 'subject_id' => (string) $subjectId];

        $message = OutboxMessage::firstOrNew($keys);

        if ($message->exists && ! $refresh) {
            return $message;
        }

        $message->fill([
            'payload' => $payload,
            'status' => OutboxMessage::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'available_at' => now(),
            'processed_at' => null,
        ])->save();

        return $message;
    }
}
