<?php

namespace FreshCart\Integrations\Console\Commands;

use FreshCart\Integrations\Clients\AkauntingClient;
use FreshCart\Integrations\Services\OutboxProcessor;
use Illuminate\Console\Command;

class PushOutbox extends Command
{
    protected $signature = 'fc:integrations:push {--limit= : Maximum messages to push in this run}';

    protected $description = 'Push queued FreshCart sales, procurement and payroll records to Akaunting.';

    public function handle(OutboxProcessor $processor, AkauntingClient $akaunting): int
    {
        if (! $akaunting->configured()) {
            $this->warn('Akaunting is not configured (AKAUNTING_URL / AKAUNTING_EMAIL / AKAUNTING_PASSWORD); messages stay queued.');

            return self::SUCCESS;
        }

        $result = $processor->run($this->option('limit') ? (int) $this->option('limit') : null);

        $this->info("Pushed {$result['done']}, failed {$result['failed']}.");

        return self::SUCCESS;
    }
}
