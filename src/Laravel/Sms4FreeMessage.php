<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Laravel;

use EdenOhana\SmsFree\Message;

/**
 * The value a notification returns from `toSms4Free()`.
 *
 * A notification can simply return a string, in which case the sender comes
 * from `config('sms4free.sender')`. This class is for when a notification
 * needs to override the sender, for instance a marketing message that should
 * not look like it came from the support number.
 */
final class Sms4FreeMessage
{
    private function __construct(
        private readonly Message $content,
        private readonly ?string $sender,
    ) {
    }

    public static function create(string|Message $content, ?string $sender = null): self
    {
        return new self($content instanceof Message ? $content : Message::of($content), $sender);
    }

    /**
     * Overrides the sender configured for the application.
     */
    public function from(string $sender): self
    {
        return new self($this->content, $sender);
    }

    public function content(): Message
    {
        return $this->content;
    }

    public function sender(): ?string
    {
        return $this->sender;
    }
}
