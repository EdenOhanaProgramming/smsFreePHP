<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;

/**
 * The body of an SMS.
 *
 * Wrapping the text in a value object buys three things the original string
 * did not give us: the text is validated once, truncation is multibyte-safe
 * (cutting a Hebrew string with `substr()` splits a character in half and
 * produces mojibake), and the caller can ask up front how many SMS parts —
 * and therefore how many credits — the message is going to cost.
 */
final class Message implements \Stringable
{
    private function __construct(
        private readonly string $text,
        private readonly bool $truncated,
    ) {
    }

    /**
     * @throws InvalidArgumentException if the text is empty or only whitespace
     */
    public static function of(string $text): self
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('The message body must not be empty.');
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('The message body must be valid UTF-8.');
        }

        return new self($text, false);
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * The number of characters as a human would count them.
     */
    public function length(): int
    {
        return mb_strlen($this->text, 'UTF-8');
    }

    public function encoding(): SmsEncoding
    {
        return SmsEncoding::detect($this->text);
    }

    /**
     * How many SMS parts the message is split into on the network — in other
     * words, how many messages the account is billed for.
     */
    public function parts(): int
    {
        $encoding = $this->encoding();
        $units = $encoding === SmsEncoding::Gsm7
            ? Gsm7Alphabet::septets($this->text)
            : $this->utf16CodeUnits();

        if ($units <= $encoding->singlePartLength()) {
            return 1;
        }

        return (int) ceil($units / $encoding->multiPartLength());
    }

    /**
     * Whether this instance is the result of truncating a longer message.
     */
    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    /**
     * Returns a message no longer than `$maxLength` characters, cutting on a
     * character boundary so multibyte text is never corrupted.
     *
     * The same instance is returned when no truncation is needed, so the
     * `isTruncated()` flag stays meaningful.
     *
     * @throws InvalidArgumentException if the limit is not positive
     */
    public function truncateTo(int $maxLength): self
    {
        if ($maxLength < 1) {
            throw new InvalidArgumentException('The maximum message length must be at least one character.');
        }

        if ($this->length() <= $maxLength) {
            return $this;
        }

        return new self(mb_substr($this->text, 0, $maxLength, 'UTF-8'), true);
    }

    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * UCS-2 is billed per 16-bit unit, so characters outside the Basic
     * Multilingual Plane (most emoji) count as two.
     */
    private function utf16CodeUnits(): int
    {
        $codePoints = mb_str_split($this->text, 1, 'UTF-8');
        $units = 0;

        foreach ($codePoints as $codePoint) {
            $ordinal = mb_ord($codePoint, 'UTF-8');
            $units += $ordinal !== false && $ordinal > 0xFFFF ? 2 : 1;
        }

        return $units;
    }
}
