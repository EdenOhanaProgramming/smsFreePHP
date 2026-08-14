<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Support;

use EdenOhana\SmsFree\Exception\TransportException;
use EdenOhana\SmsFree\Http\HttpClient;
use EdenOhana\SmsFree\Http\HttpResponse;

/**
 * An in-memory transport that records what was sent and replays canned
 * answers, so the client can be tested without touching the network.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<array{url: string, body: string, headers: array<string, string>}> */
    private array $requests = [];

    /** @var list<HttpResponse|TransportException> */
    private array $queue = [];

    public function __construct(HttpResponse|TransportException ...$responses)
    {
        $this->queue = array_values($responses);
    }

    public static function respondingWith(int $status, string $message = ''): self
    {
        return new self(new HttpResponse(200, json_encode(
            ['status' => $status, 'message' => $message],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
        )));
    }

    public static function respondingWithRawBody(string $body, int $httpStatus = 200): self
    {
        return new self(new HttpResponse($httpStatus, $body));
    }

    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new \LogicException('The fake HTTP client received more requests than it has answers for.');
        }

        if ($next instanceof TransportException) {
            throw $next;
        }

        return $next;
    }

    public function requestCount(): int
    {
        return \count($this->requests);
    }

    /**
     * @return array{url: string, body: string, headers: array<string, string>}
     */
    public function lastRequest(): array
    {
        $last = end($this->requests);

        if ($last === false) {
            throw new \LogicException('No request was recorded.');
        }

        return $last;
    }

    /**
     * A single field of the most recent request's payload, as a string.
     */
    public function lastPayloadField(string $key): string
    {
        $value = $this->lastPayload()[$key] ?? null;

        if (!\is_scalar($value)) {
            throw new \LogicException(\sprintf('The payload has no scalar field "%s".', $key));
        }

        return (string) $value;
    }

    /**
     * The decoded JSON payload of the most recent request.
     *
     * @return array<string, mixed>
     */
    public function lastPayload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($this->lastRequest()['body'], true, 512, \JSON_THROW_ON_ERROR);

        return $payload;
    }
}
