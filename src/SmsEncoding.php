<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

/**
 * The alphabet a message is carried in, which determines how many characters
 * fit into a single SMS part.
 *
 * This matters a great deal for Hebrew: a Hebrew message is always encoded as
 * UCS-2, so it fits 70 characters per part instead of the 160 a Latin message
 * gets. A "short" message can therefore cost more than one credit.
 */
enum SmsEncoding: string
{
    /** The 7-bit GSM 03.38 alphabet: Latin letters, digits and common punctuation. */
    case Gsm7 = 'GSM-7';

    /** UTF-16 code units, used as soon as a single character falls outside GSM-7 (Hebrew, Arabic, emoji …). */
    case Ucs2 = 'UCS-2';

    /**
     * Characters that fit in a message consisting of a single part.
     */
    public function singlePartLength(): int
    {
        return match ($this) {
            self::Gsm7 => 160,
            self::Ucs2 => 70,
        };
    }

    /**
     * Characters per part once a message is split, the difference being the
     * concatenation header each part has to carry.
     */
    public function multiPartLength(): int
    {
        return match ($this) {
            self::Gsm7 => 153,
            self::Ucs2 => 67,
        };
    }

    /**
     * Detects the alphabet required to carry the given text.
     */
    public static function detect(string $text): self
    {
        return Gsm7Alphabet::supports($text) ? self::Gsm7 : self::Ucs2;
    }
}
