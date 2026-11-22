<?php

namespace FreshCart\Integrations\Console\Commands;

use FreshCart\Integrations\Models\OutboxMessage;
use Illuminate\Console\Command;

class RetryOutbox extends Command
{
    protected $signature = 'fc:integrations:retry {id?* : Outbox ids; all failed messages when omitted}';

    protected $description = 'Reset failed outbox messages (including ones that used up their attempts) so they are pushed again.';

    public function handle(): int
    {
        $count = OutboxMessage::where('status', OutboxMessage::STATUS_FAILED)
            ->when($this->argument('id'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->update(['status' => OutboxMessage::STATUS_PENDING, 'attempts' => 0, 'available_at' => now()]);

        $this->info("{$count} message(s) queued again. They are pushed on the next fc:integrations:push run.");

        return self::SUCCESS;
    }
}
