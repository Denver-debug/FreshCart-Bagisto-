<?php

namespace FreshCart\Integrations\Clients;

use FreshCart\Integrations\Exceptions\IntegrationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Akaunting 3 REST API (basic auth, company chosen with
 * the X-Company header).
 */
class AkauntingClient
{
    public function configured(): bool
    {
        return filled(config('freshcart-integrations.akaunting.url'))
            && filled(config('freshcart-integrations.akaunting.email'))
            && filled(config('freshcart-integrations.akaunting.password'));
    }

    public function ping(): bool
    {
        return $this->request()->get('ping')->successful();
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $data): array
    {
        return $this->send('POST', $path, $data);
    }

    public function put(string $path, array $data): array
    {
        return $this->send('PUT', $path, $data);
    }

    public function delete(string $path): void
    {
        $response = $this->request()->delete($path);

        if ($response->failed() && $response->status() !== 404) {
            throw IntegrationException::fromResponse('Akaunting', 'DELETE', $path, $response);
        }
    }

    /**
     * Id of the first contact of this type with this email, if any.
     */
    public function findContactId(string $type, ?string $email): ?int
    {
        if (blank($email)) {
            return null;
        }

        $contacts = $this->get('contacts', ['search' => "type:{$type} email:{$email}", 'limit' => 1]);

        return $contacts['data'][0]['id'] ?? null;
    }

    protected function send(string $method, string $path, array $data): array
    {
        $response = $method === 'GET'
            ? $this->request()->get($path, $data)
            : $this->request()->send($method, $path, ['json' => $data]);

        if ($response->failed()) {
            throw IntegrationException::fromResponse('Akaunting', $method, $path, $response);
        }

        return $response->json() ?? [];
    }

    protected function request(): PendingRequest
    {
        $config = config('freshcart-integrations.akaunting');

        return Http::baseUrl($config['url'].'/api/')
            ->withBasicAuth((string) $config['email'], (string) $config['password'])
            ->withHeaders(['X-Company' => $config['company_id']])
            ->acceptJson()
            ->timeout($config['timeout']);
    }
}
