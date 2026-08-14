<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Http;

/**
 * A minimal, immutable view of an HTTP response — everything this library
 * needs, and nothing more.
 */
final class HttpResponse
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
