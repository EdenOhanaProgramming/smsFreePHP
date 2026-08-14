<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

/**
 * What actually happened when a message was handed to the provider.
 *
 * The original implementation returned `true` on success, which threw away
 * everything worth knowing: how many recipients were accepted, whether the
 * body had to be shortened, and how many message parts the account was billed
 * for. All of that lives here.
 */
final class SendResult
{
    /**
     * @param int               $acceptedCount     how many messages the provider accepted
     * @param list<PhoneNumber> $recipients        the normalised recipients the request was sent to
     * @param list<string>      $skippedRecipients raw values left out under InvalidRecipientPolicy::SkipInvalid
     */
    public function __construct(
        private readonly int $acceptedCount,
        private readonly array $recipients,
        private readonly Message $message,
        private readonly string $providerMessage = '',
        private readonly array $skippedRecipients = [],
    ) {
    }

    /**
     * The number of messages the provider reported as accepted. This can be
     * lower than the recipient count when some numbers were rejected.
     */
    public function acceptedCount(): int
    {
        return $this->acceptedCount;
    }

    /**
     * @return list<PhoneNumber>
     */
    public function recipients(): array
    {
        return $this->recipients;
    }

    /**
     * @return list<string> the recipients in canonical dialling form
     */
    public function recipientNumbers(): array
    {
        return array_map(static fn (PhoneNumber $number): string => $number->national(), $this->recipients);
    }

    /**
     * The body as it was actually sent, after any truncation.
     */
    public function message(): Message
    {
        return $this->message;
    }

    /**
     * Whether the body had to be shortened to fit the provider's limit.
     * Worth logging: a truncated message is a message that lost information.
     */
    public function wasTruncated(): bool
    {
        return $this->message->isTruncated();
    }

    /**
     * The recipients that could not be parsed and were left out of the
     * request, exactly as the caller supplied them. Always empty under the
     * default {@see InvalidRecipientPolicy::RejectRequest}, because that
     * policy never gets as far as sending.
     *
     * Worth logging: these are people who expected a message and did not get one.
     *
     * @return list<string>
     */
    public function skippedRecipients(): array
    {
        return $this->skippedRecipients;
    }

    public function hasSkippedRecipients(): bool
    {
        return $this->skippedRecipients !== [];
    }

    /**
     * Any informational text the provider returned alongside a success.
     */
    public function providerMessage(): string
    {
        return $this->providerMessage;
    }

    /**
     * A best-effort estimate of the credits this send consumed: message parts
     * multiplied by recipients. The provider's own billing is authoritative.
     */
    public function estimatedCredits(): int
    {
        return $this->message->parts() * \count($this->recipients);
    }
}
