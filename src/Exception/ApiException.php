<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Exception;

/**
 * Thrown when the provider answered successfully but rejected the request.
 *
 * SMS4Free reports the outcome inside the response body rather than through
 * HTTP status codes: a positive `status` is the number of messages accepted,
 * while zero or a negative value is an error. The raw code and the provider's
 * own message are preserved verbatim so callers can branch on them without
 * this library having to guess at an ever-changing code table.
 */
final class ApiException extends \RuntimeException implements SmsFreeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $providerMessage,
    ) {
        parent::__construct(\sprintf(
            'SMS4Free rejected the request (status %d)%s',
            $status,
            $providerMessage !== '' ? ': ' . $providerMessage : '.',
        ));
    }

    /**
     * The raw `status` value returned by the provider (zero or negative).
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * The provider's own error message, which may be empty or localised.
     */
    public function providerMessage(): string
    {
        return $this->providerMessage;
    }
}
