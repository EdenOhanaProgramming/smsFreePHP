<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;

/**
 * A parsed, normalised recipient number.
 *
 * Users type phone numbers in every shape imaginable: `054-123-4567`,
 * `+972 54 123 4567`, `00972541234567`. This value object accepts all of them
 * and exposes a single canonical form, so the rest of the library never has to
 * deal with formatting noise.
 *
 * Two parsing modes are supported:
 *
 * - **Israeli mobile (default)** accepts only numbers that resolve to a valid
 *   Israeli mobile line (`05X` followed by seven digits).
 * - **International** accepts any 7 to 15 digit number, for accounts that are
 *   permitted to send abroad.
 */
final class PhoneNumber implements \Stringable
{
    private const ISRAEL_COUNTRY_CODE = '972';

    /** An Israeli national significant number for a mobile line: a leading 5 plus eight digits. */
    private const ISRAELI_MOBILE_NSN = '/^5\d{8}$/';

    /** Anything a sane numbering plan can produce; E.164 allows at most 15 digits. */
    private const LOOSE_INTERNATIONAL = '/^\d{7,15}$/';

    private function __construct(
        private readonly string $raw,
        private readonly string $national,
        private readonly ?string $e164,
        private readonly bool $israeli,
    ) {
    }

    /**
     * Parses a number, throwing when it cannot be understood.
     *
     * @param string $raw                the number exactly as the user typed it
     * @param bool   $allowInternational when true, non-Israeli numbers are accepted as well
     *
     * @throws InvalidPhoneNumberException
     */
    public static function parse(string $raw, bool $allowInternational = false): self
    {
        return self::tryParse($raw, $allowInternational)
            ?? throw new InvalidPhoneNumberException([$raw]);
    }

    /**
     * Parses a number, returning null instead of throwing.
     *
     * @param string $raw                the number exactly as the user typed it
     * @param bool   $allowInternational when true, non-Israeli numbers are accepted as well
     */
    public static function tryParse(string $raw, bool $allowInternational = false): ?self
    {
        $cleaned = self::stripFormatting($raw);

        if ($cleaned === null) {
            return null;
        }

        [$digits, $explicitInternational] = self::stripInternationalPrefix($cleaned);

        if ($digits === '') {
            return null;
        }

        $israeliNsn = self::israeliMobileNsn($digits, $explicitInternational);

        if ($israeliNsn !== null) {
            return new self(
                raw: $raw,
                national: '0' . $israeliNsn,
                e164: '+' . self::ISRAEL_COUNTRY_CODE . $israeliNsn,
                israeli: true,
            );
        }

        if (!$allowInternational || preg_match(self::LOOSE_INTERNATIONAL, $digits) !== 1) {
            return null;
        }

        return new self(
            raw: $raw,
            national: $digits,
            // Only a number that was written with a country code can be
            // expressed as E.164; guessing one would be worse than admitting
            // we do not know it.
            e164: $explicitInternational ? '+' . $digits : null,
            israeli: false,
        );
    }

    /**
     * Parses a whole recipient list at once and reports *every* bad entry
     * rather than failing on the first one.
     *
     * @param iterable<string|self> $numbers
     *
     * @return list<self>
     *
     * @throws InvalidPhoneNumberException listing all values that failed to parse
     */
    public static function parseList(iterable $numbers, bool $allowInternational = false): array
    {
        $parsed = [];
        $invalid = [];

        foreach ($numbers as $number) {
            if ($number instanceof self) {
                $parsed[] = $number;

                continue;
            }

            $candidate = self::tryParse($number, $allowInternational);

            if ($candidate === null) {
                $invalid[] = $number;

                continue;
            }

            $parsed[] = $candidate;
        }

        if ($invalid !== []) {
            throw new InvalidPhoneNumberException($invalid);
        }

        return $parsed;
    }

    /**
     * The number exactly as it was supplied, for error messages that have to
     * point the user back at what they typed.
     */
    public function raw(): string
    {
        return $this->raw;
    }

    /**
     * The canonical dialling form sent to the provider: `0541234567` for an
     * Israeli line, the full international number otherwise.
     */
    public function national(): string
    {
        return $this->national;
    }

    /**
     * The E.164 representation, e.g. `+972541234567`, or null when the input
     * carried no country code and none could be inferred.
     */
    public function e164(): ?string
    {
        return $this->e164;
    }

    public function isIsraeli(): bool
    {
        return $this->israeli;
    }

    public function equals(self $other): bool
    {
        return $this->national === $other->national;
    }

    public function __toString(): string
    {
        return $this->national;
    }

    /**
     * Removes the punctuation people put in phone numbers and returns the
     * digits, keeping a leading `+` so the country code can still be detected.
     * Returns null when anything other than punctuation and digits is present.
     */
    private static function stripFormatting(string $raw): ?string
    {
        // Spaces (including the non-breaking and bidirectional marks that
        // creep in when a number is copied out of a browser), dashes, dots,
        // slashes and parentheses are all noise.
        $cleaned = preg_replace('/[\s\-.\/()\x{00A0}\x{200E}\x{200F}]+/u', '', $raw);

        if (!\is_string($cleaned) || $cleaned === '') {
            return null;
        }

        return preg_match('/^\+?\d+$/', $cleaned) === 1 ? $cleaned : null;
    }

    /**
     * Splits off an international dialling prefix.
     *
     * @return array{0: string, 1: bool} the remaining digits, and whether the
     *                                   number was explicitly international
     */
    private static function stripInternationalPrefix(string $cleaned): array
    {
        if (str_starts_with($cleaned, '+')) {
            return [substr($cleaned, 1), true];
        }

        if (str_starts_with($cleaned, '00')) {
            return [substr($cleaned, 2), true];
        }

        return [$cleaned, false];
    }

    /**
     * Returns the Israeli national significant number (`5XXXXXXXX`) if the
     * digits describe an Israeli mobile line, or null if they do not.
     */
    private static function israeliMobileNsn(string $digits, bool $explicitInternational): ?string
    {
        // `972...` is unambiguous as a country code: no Israeli national number
        // begins with those digits.
        if (str_starts_with($digits, self::ISRAEL_COUNTRY_CODE)) {
            $nsn = substr($digits, \strlen(self::ISRAEL_COUNTRY_CODE));

            // `+972 054...` is a common copy/paste mistake, so tolerate the
            // trunk prefix that should have been dropped.
            $nsn = str_starts_with($nsn, '0') ? substr($nsn, 1) : $nsn;

            return preg_match(self::ISRAELI_MOBILE_NSN, $nsn) === 1 ? $nsn : null;
        }

        // Without a country code, a leading zero is the national trunk prefix.
        // `0541234567` and `541234567` both mean the same line.
        if (!$explicitInternational) {
            $nsn = str_starts_with($digits, '0') ? substr($digits, 1) : $digits;

            return preg_match(self::ISRAELI_MOBILE_NSN, $nsn) === 1 ? $nsn : null;
        }

        return null;
    }
}
