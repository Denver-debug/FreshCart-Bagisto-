<?php

namespace FreshCart\Integrations\Services;

/**
 * Single entry point for NovaCore HR data, whether it arrives by webhook or by
 * the scheduled pull, so both paths behave the same.
 */
class FrappeInbound
{
    public function __construct(
        protected EmployeeSync $employees,
        protected Outbox $outbox,
    ) {}

    /**
     * NovaCore webhook event name => result.
     */
    public function event(string $event, array $data): string
    {
        return match ($event) {
            'employee.created', 'employee.updated', 'employee.separated' => $this->employee($data),
            'payroll.released' => $this->payrollReleased($data),
            'ping' => 'pong',
            default => 'skipped:unknown event '.$event,
        };
    }

    public function employee(array $employee): string
    {
        if (! config('freshcart-integrations.flows.employees')) {
            return 'skipped:employee sync disabled';
        }

        return $this->employees->sync($employee);
    }

    /**
     * Aggregated payroll summary (NovaCore get_payroll_summary shape).
     */
    public function payrollReleased(array $summary): string
    {
        if (! config('freshcart-integrations.flows.payroll') || empty($summary['payroll_entry'])) {
            return 'skipped';
        }

        if (array_key_exists('released', $summary) && ! $summary['released']) {
            return 'skipped:payroll not released';
        }

        $company = config('freshcart-integrations.frappe.company');

        if ($company && ($summary['company'] ?? null) !== $company) {
            return 'skipped:other company';
        }

        $message = $this->outbox->record('akaunting.payroll', 'payroll_entry', $summary['payroll_entry'], $summary);

        return $message->wasRecentlyCreated ? 'queued' : 'skipped:already queued';
    }
}
