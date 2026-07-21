<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A failed GitHub API call (SPEC §6.4): carries the HTTP status plus a
 * readable message built from GitHub's error body, surfaced to the user by
 * the export endpoint — API failures never fail silently.
 */
class GithubApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response, string $action): self
    {
        $detail = $response->json('message');

        if (! is_string($detail) || $detail === '') {
            $detail = trim($response->body()) !== '' ? trim($response->body()) : 'no error details returned';
        }

        return new self($response->status(), "GitHub could not {$action} (HTTP {$response->status()}): {$detail}");
    }
}
