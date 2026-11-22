<?php

namespace FreshCart\Integrations\Console\Commands;

use FreshCart\Integrations\Clients\AkauntingClient;
use FreshCart\Integrations\Clients\FrappeClient;
use FreshCart\Integrations\Models\OutboxMessage;
use Illuminate\Console\Command;
use Throwable;

class IntegrationStatus extends Command
{
    protected $signature = 'fc:integrations:status';

    protected $description = 'Check connectivity to Akaunting and Frappe HR and show the outbox backlog.';

    public function handle(AkauntingClient $akaunting, FrappeClient $frappe): int
    {
        $this->table(['System', 'URL', 'Configured', 'Reachable'], [
            ['Akaunting', config('freshcart-integrations.akaunting.url') ?: '-', $akaunting->configured() ? 'yes' : 'no', $this->check($akaunting)],
            ['Frappe HR', config('freshcart-integrations.frappe.url') ?: '-', $frappe->configured() ? 'yes' : 'no', $this->check($frappe)],
        ]);

        $counts = OutboxMessage::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $this->table(['Status', 'Messages'], $counts->map(fn ($total, $status) => [$status, $total])->values()->all());

        $failures = OutboxMessage::where('status', OutboxMessage::STATUS_FAILED)->latest('updated_at')->limit(10)->get();

        if ($failures->isNotEmpty()) {
            $this->table(
                ['Id', 'Action', 'Subject', 'Attempts', 'Error'],
                $failures->map(fn ($m) => [$m->id, $m->action, "{$m->subject_type} {$m->subject_id}", $m->attempts, mb_strimwidth((string) $m->last_error, 0, 120, '…')])->all(),
            );
        }

        return self::SUCCESS;
    }

    protected function check(AkauntingClient|FrappeClient $client): string
    {
        if (! $client->configured()) {
            return '-';
        }

        try {
            return $client->ping() ? 'yes' : 'no (authentication failed?)';
        } catch (Throwable $e) {
            return 'no: '.mb_strimwidth($e->getMessage(), 0, 80, '…');
        }
    }
}
