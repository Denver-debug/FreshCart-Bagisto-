<?php

namespace FreshCart\Integrations\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class IntegrationException extends RuntimeException
{
    public static function fromResponse(string $system, string $method, string $path, Response $response): self
    {
        $body = $response->json();

        $detail = is_array($body)
            ? json_encode($body['errors'] ?? $body['message'] ?? $body['exception'] ?? $body, JSON_UNESCAPED_SLASHES)
            : mb_substr((string) $response->body(), 0, 500);

        return new self("{$system} {$method} {$path} failed with HTTP {$response->status()}: {$detail}");
    }
}
