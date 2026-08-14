<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

/**
 * The GSM 03.38 alphabet, used to decide whether a message can travel as
 * 7-bit text or has to fall back to UCS-2.
 *
 * @internal
 */
final class Gsm7Alphabet
{
    /**
     * The basic character set. Every character here occupies one septet.
     */
    private const BASIC = '@£$¥èéùìòÇ' . "\n" . 'Øø' . "\r" . 'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?'
        . '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /**
     * The extension table. These characters are legal in a 7-bit message but
     * cost two septets each, because they are sent behind an escape.
     */
    private const EXTENDED = '^{}\\[~]|€' . "\f";

    /** @var array<string, true>|null */
    private static ?array $basicIndex = null;

    /** @var array<string, true>|null */
    private static ?array $extendedIndex = null;

    /**
     * Whether every character of the text can be represented in GSM 03.38.
     */
    public static function supports(string $text): bool
    {
        foreach (self::characters($text) as $character) {
            if (!isset(self::basicIndex()[$character]) && !isset(self::extendedIndex()[$character])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The number of septets the text occupies, counting escaped characters twice.
     */
    public static function septets(string $text): int
    {
        $septets = 0;

        foreach (self::characters($text) as $character) {
            $septets += isset(self::extendedIndex()[$character]) ? 2 : 1;
        }

        return $septets;
    }

    /**
     * @return list<string>
     */
    private static function characters(string $text): array
    {
        $characters = preg_split('//u', $text, -1, \PREG_SPLIT_NO_EMPTY);

        return $characters === false ? [] : $characters;
    }

    /**
     * @return array<string, true>
     */
    private static function basicIndex(): array
    {
        return self::$basicIndex ??= array_fill_keys(self::characters(self::BASIC), true);
    }

    /**
     * @return array<string, true>
     */
    private static function extendedIndex(): array
    {
        return self::$extendedIndex ??= array_fill_keys(self::characters(self::EXTENDED), true);
    }
}
