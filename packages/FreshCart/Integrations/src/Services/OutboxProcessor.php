<?php

namespace FreshCart\Integrations\Services;

use FreshCart\Integrations\Clients\AkauntingClient;
use FreshCart\Integrations\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes due outbox messages. A failure is recorded on the row and retried with
 * exponential backoff (1, 2, 4 … 60 minutes) until max_attempts is reached.
 */
class OutboxProcessor
{
    const STALE_MINUTES = 10;

    public function __construct(
        protected AkauntingClient $akaunting,
        protected AkauntingSync $akauntingSync,
    ) {}

    /**
     * @return array{done: int, failed: int}
     */
    public function run(?int $limit = null): array
    {
        $result = ['done' => 0, 'failed' => 0];

        if (! $this->akaunting->configured()) {
            return $result;
        }

        $messages = OutboxMessage::where(fn ($query) => self::claimable($query))
            ->where('attempts', '<', config('freshcart-integrations.outbox.max_attempts'))
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit ?? config('freshcart-integrations.outbox.batch'))
            ->get();

        foreach ($messages as $message) {
            $this->process($message) ? $result['done']++ : $result['failed']++;
        }

        return $result;
    }

    public function process(OutboxMessage $message): bool
    {
        /**
         * Claim the row so two overlapping runs never push it twice.
         */
        $claimed = OutboxMessage::whereKey($message->id)
            ->where(fn ($query) => self::claimable($query))
            ->update(['status' => OutboxMessage::STATUS_PROCESSING]);

        if (! $claimed) {
            return false;
        }

        /**
         * Sync the in-memory status with the claim, otherwise writing "failed"
         * back onto a row loaded as "failed" is not dirty and is never saved.
         */
        $message->refresh();

        try {
            if (! $this->akauntingSync->handles($message->action)) {
                throw new \LogicException("No handler for {$message->action}.");
            }

            $this->akauntingSync->handle($message);

            if (OutboxMessage::whereKey($message->id)->exists()) {
                $message->forceFill([
                    'status' => OutboxMessage::STATUS_DONE,
                    'attempts' => $message->attempts + 1,
                    'last_error' => null,
                    'processed_at' => now(),
                ])->save();
            }

            return true;
        } catch (Throwable $e) {
            $attempts = $message->attempts + 1;

            $message->forceFill([
                'status' => OutboxMessage::STATUS_FAILED,
                'attempts' => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
                'available_at' => now()->addMinutes(min(60, 2 ** ($attempts - 1))),
            ])->save();

            Log::warning("FreshCart integration {$message->action} #{$message->subject_id} failed", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Pending and failed rows, plus rows left in "processing" by a run that
     * died mid-push (deploy, fatal error), so they are not stuck forever.
     */
    public static function claimable($query)
    {
        return $query->whereIn('status', [OutboxMessage::STATUS_PENDING, OutboxMessage::STATUS_FAILED])
            ->orWhere(fn ($query) => $query->where('status', OutboxMessage::STATUS_PROCESSING)
                ->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES)));
    }
}
