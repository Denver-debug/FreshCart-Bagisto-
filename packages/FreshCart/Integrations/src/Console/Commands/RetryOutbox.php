<?php

namespace FreshCart\Integrations\Console\Commands;

use FreshCart\Integrations\Models\OutboxMessage;
use FreshCart\Integrations\Services\OutboxProcessor;
use Illuminate\Console\Command;

class RetryOutbox extends Command
{
    protected $signature = 'fc:integrations:retry {id?* : Outbox ids; all failed and stuck messages when omitted}';

    protected $description = 'Reset failed outbox messages (including ones that used up their attempts) so they are pushed again.';

    public function handle(): int
    {
        $count = OutboxMessage::where(fn ($query) => $query->where('status', OutboxMessage::STATUS_FAILED)
            ->orWhere(fn ($query) => $query->where('status', OutboxMessage::STATUS_PROCESSING)
                ->where('updated_at', '<', now()->subMinutes(OutboxProcessor::STALE_MINUTES))))
            ->when($this->argument('id'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->update(['status' => OutboxMessage::STATUS_PENDING, 'attempts' => 0, 'available_at' => now()]);

        $this->info("{$count} message(s) queued again. They are pushed on the next fc:integrations:push run.");

        return self::SUCCESS;
    }
}
