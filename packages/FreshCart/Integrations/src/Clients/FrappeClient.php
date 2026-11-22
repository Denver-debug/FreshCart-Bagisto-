<?php

namespace FreshCart\Integrations\Clients;

use FreshCart\Integrations\Exceptions\IntegrationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NovaCore HR integration API v1 (novacore/API.md in the FrappeHR repository).
 *
 * The API user needs the "ERP Integration" role (employee directory) and
 * "ERP Finance Integration" (payroll summaries).
 */
class FrappeClient
{
    const BASE = 'method/novacore_integrations.api.v1.';

    public function configured(): bool
    {
        return filled(config('freshcart-integrations.frappe.url'))
            && filled(config('freshcart-integrations.frappe.api_key'))
            && filled(config('freshcart-integrations.frappe.api_secret'));
    }

    /**
     * Checks the credentials too: health itself needs no login.
     */
    public function ping(): bool
    {
        $this->call('get_employees', ['limit' => 1]);

        return true;
    }

    /**
     * Every employee modified after $since, oldest first.
     */
    public function employeesModifiedSince(?string $since): array
    {
        $rows = [];
        $offset = 0;

        do {
            $page = $this->call('get_employees', array_filter([
                'modified_since' => $since,
                'limit' => 500,
                'offset' => $offset,
            ], fn ($value) => $value !== null));

            $rows = array_merge($rows, $page['data'] ?? []);
            $offset = $page['next_offset'] ?? null;
        } while ($offset !== null);

        return $rows;
    }

    public function releasedPayrollRuns(int $limit = 50): array
    {
        return $this->call('list_payroll_runs', array_filter([
            'company' => config('freshcart-integrations.frappe.company'),
            'released_only' => 1,
            'limit' => $limit,
        ]))['data'] ?? [];
    }

    public function payrollSummary(string $payrollEntry): array
    {
        return $this->call('get_payroll_summary', ['payroll_entry' => $payrollEntry]);
    }

    protected function call(string $function, array $query = []): array
    {
        $path = self::BASE.$function;

        $response = $this->request()->get($path, $query);

        if ($response->failed()) {
            throw IntegrationException::fromResponse('NovaCore HR', 'GET', $function, $response);
        }

        return $response->json('message') ?? [];
    }

    protected function request(): PendingRequest
    {
        $config = config('freshcart-integrations.frappe');

        return Http::baseUrl($config['url'].'/api/')
            ->withHeaders(['Authorization' => "token {$config['api_key']}:{$config['api_secret']}"])
            ->acceptJson()
            ->timeout($config['timeout']);
    }
}
