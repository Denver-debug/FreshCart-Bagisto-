<?php

namespace FreshCart\Integrations\Console\Commands;

use FreshCart\Integrations\Clients\FrappeClient;
use FreshCart\Integrations\Models\OutboxMessage;
use FreshCart\Integrations\Services\FrappeInbound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safety net (and initial import) for the NovaCore HR webhooks: fetches the
 * employees modified since the last run and any released payroll run that is
 * not in the outbox yet.
 */
class PullFrappe extends Command
{
    protected $signature = 'fc:integrations:pull-frappe
        {--only= : employees or payroll}
        {--full : Ignore the saved cursor and re-read every employee}';

    protected $description = 'Import NovaCore HR employees (as Bagisto admins) and released payroll runs (for Akaunting).';

    const CURSOR = 'frappe.employee';

    public function handle(FrappeClient $frappe, FrappeInbound $inbound): int
    {
        if (! $frappe->configured()) {
            $this->warn('NovaCore HR is not configured (FRAPPE_URL / FRAPPE_API_KEY / FRAPPE_API_SECRET).');

            return self::SUCCESS;
        }

        $only = $this->option('only');

        if ($only !== 'payroll' && config('freshcart-integrations.flows.employees')) {
            $this->pullEmployees($frappe, $inbound);
        }

        if ($only !== 'employees' && config('freshcart-integrations.flows.payroll')) {
            $this->pullPayroll($frappe, $inbound);
        }

        return self::SUCCESS;
    }

    protected function pullEmployees(FrappeClient $frappe, FrappeInbound $inbound): void
    {
        $since = $this->option('full') ? null : DB::table('fc_integration_cursors')->where('key', self::CURSOR)->value('value');
        $counts = [];

        foreach ($frappe->employeesModifiedSince($since) as $employee) {
            $result = $inbound->employee($employee);
            $counts[strtok($result, ':')] = ($counts[strtok($result, ':')] ?? 0) + 1;

            if (str_starts_with($result, 'skipped:')) {
                $this->line("  {$employee['employee_id']}: {$result}");
            }

            $since = $employee['modified'] ?? $since;
        }

        if ($since) {
            DB::table('fc_integration_cursors')->updateOrInsert(['key' => self::CURSOR], ['value' => $since, 'updated_at' => now(), 'created_at' => now()]);
        }

        $this->info('Employees: '.(collect($counts)->map(fn ($count, $result) => "{$result} {$count}")->implode(', ') ?: 'nothing new').'.');
    }

    protected function pullPayroll(FrappeClient $frappe, FrappeInbound $inbound): void
    {
        $queued = 0;

        foreach ($frappe->releasedPayrollRuns() as $run) {
            if (OutboxMessage::where(['action' => 'akaunting.payroll', 'subject_id' => $run['name']])->exists()) {
                continue;
            }

            if ($inbound->payrollReleased($frappe->payrollSummary($run['name'])) === 'queued') {
                $queued++;
            }
        }

        $this->info("Payroll runs: {$queued} queued.");
    }
}
