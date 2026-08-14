<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;

/**
 * Tunable behaviour of the client.
 *
 * The defaults are meant to be sensible for a web request: fail fast rather
 * than leaving a page hanging on a provider that has stopped responding.
 * Instances are immutable, and the `with*()` methods return a modified copy.
 */
final class ClientOptions
{
    public const DEFAULT_ENDPOINT = 'https://api.sms4free.co.il/ApiSMS/v2/SendSMS';

    /**
     * The longest body SMS4Free accepts in a single request. Kept
     * configurable because it is a provider-side limit, not a protocol one.
     */
    public const DEFAULT_MAX_MESSAGE_LENGTH = 134;

    /**
     * @param string      $endpoint             absolute HTTPS URL of the SendSMS endpoint
     * @param float       $connectTimeout       seconds to wait for the connection to be established
     * @param float       $timeout              seconds to wait for the whole request to finish
     * @param int         $maxMessageLength     characters accepted before `$truncateLongMessages` decides what happens
     * @param bool        $truncateLongMessages true cuts an over-long body, false rejects it with an exception
     * @param bool        $allowInternational   true accepts non-Israeli recipient numbers
     * @param string|null $caBundlePath         path to a CA bundle, for hosts whose PHP has none configured
     * @param string|null $userAgent            overrides the User-Agent header sent with the request
     * @param InvalidRecipientPolicy $invalidRecipients whether an unparseable recipient rejects the request or is skipped
     */
    public function __construct(
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
        private readonly float $connectTimeout = 5.0,
        private readonly float $timeout = 15.0,
        private readonly int $maxMessageLength = self::DEFAULT_MAX_MESSAGE_LENGTH,
        private readonly bool $truncateLongMessages = true,
        private readonly bool $allowInternational = false,
        private readonly ?string $caBundlePath = null,
        private readonly ?string $userAgent = null,
        private readonly InvalidRecipientPolicy $invalidRecipients = InvalidRecipientPolicy::SkipInvalid,
    ) {
        if (!filter_var($endpoint, \FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://')) {
            throw new InvalidArgumentException('The endpoint must be an absolute HTTPS URL.');
        }

        if ($connectTimeout <= 0 || $timeout <= 0) {
            throw new InvalidArgumentException('Timeouts must be greater than zero.');
        }

        if ($maxMessageLength < 1) {
            throw new InvalidArgumentException('The maximum message length must be at least one character.');
        }

        if ($caBundlePath !== null && !is_readable($caBundlePath)) {
            throw new InvalidArgumentException(\sprintf('The CA bundle "%s" cannot be read.', $caBundlePath));
        }
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function maxMessageLength(): int
    {
        return $this->maxMessageLength;
    }

    public function truncatesLongMessages(): bool
    {
        return $this->truncateLongMessages;
    }

    public function allowsInternational(): bool
    {
        return $this->allowInternational;
    }

    public function caBundlePath(): ?string
    {
        return $this->caBundlePath;
    }

    public function invalidRecipientPolicy(): InvalidRecipientPolicy
    {
        return $this->invalidRecipients;
    }

    public function userAgent(): string
    {
        return $this->userAgent ?? 'smsFreePHP/' . Sms4FreeClient::VERSION . ' (+https://github.com/EdenOhanaProgramming/smsFreePHP)';
    }

    public function withEndpoint(string $endpoint): self
    {
        return $this->with(endpoint: $endpoint);
    }

    public function withTimeouts(float $connectTimeout, float $timeout): self
    {
        return $this->with(connectTimeout: $connectTimeout, timeout: $timeout);
    }

    public function withMaxMessageLength(int $maxMessageLength): self
    {
        return $this->with(maxMessageLength: $maxMessageLength);
    }

    /**
     * When disabled, a body longer than the limit raises an exception instead
     * of being silently shortened. That is the safer choice when the tail of the
     * message carries meaning, such as a link.
     */
    public function withMessageTruncation(bool $truncate): self
    {
        return $this->with(truncateLongMessages: $truncate);
    }

    public function withInternationalRecipients(bool $allow): self
    {
        return $this->with(allowInternational: $allow);
    }

    /**
     * Decides what an unparseable recipient does to the request: reject the
     * whole thing, or send to the rest and report what was left out.
     */
    public function withInvalidRecipientPolicy(InvalidRecipientPolicy $policy): self
    {
        return $this->with(invalidRecipients: $policy);
    }

    /**
     * Passing null restores PHP's own CA configuration.
     */
    public function withCaBundlePath(?string $path): self
    {
        return new self(
            $this->endpoint,
            $this->connectTimeout,
            $this->timeout,
            $this->maxMessageLength,
            $this->truncateLongMessages,
            $this->allowInternational,
            $path,
            $this->userAgent,
            $this->invalidRecipients,
        );
    }

    /**
     * Passing null restores the library's default User-Agent.
     */
    public function withUserAgent(?string $userAgent): self
    {
        return new self(
            $this->endpoint,
            $this->connectTimeout,
            $this->timeout,
            $this->maxMessageLength,
            $this->truncateLongMessages,
            $this->allowInternational,
            $this->caBundlePath,
            $userAgent,
            $this->invalidRecipients,
        );
    }

    private function with(
        ?string $endpoint = null,
        ?float $connectTimeout = null,
        ?float $timeout = null,
        ?int $maxMessageLength = null,
        ?bool $truncateLongMessages = null,
        ?bool $allowInternational = null,
        ?string $caBundlePath = null,
        ?string $userAgent = null,
        ?InvalidRecipientPolicy $invalidRecipients = null,
    ): self {
        return new self(
            $endpoint ?? $this->endpoint,
            $connectTimeout ?? $this->connectTimeout,
            $timeout ?? $this->timeout,
            $maxMessageLength ?? $this->maxMessageLength,
            $truncateLongMessages ?? $this->truncateLongMessages,
            $allowInternational ?? $this->allowInternational,
            $caBundlePath ?? $this->caBundlePath,
            $userAgent ?? $this->userAgent,
            $invalidRecipients ?? $this->invalidRecipients,
        );
    }
}
