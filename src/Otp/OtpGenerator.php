<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Otp;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;

/**
 * Generates one-time passcodes for SMS verification flows.
 *
 * Two details separate this from the usual `rand()` one-liner:
 *
 * 1. Codes are drawn from `random_int()`, the cryptographically secure
 *    generator. A predictable OTP is not a second factor.
 * 2. Codes are strings, not integers, so a code such as `042317` keeps its
 *    leading zero instead of turning into a five-digit number.
 */
final class OtpGenerator
{
    public const DEFAULT_LENGTH = 6;

    private const MAX_LENGTH = 32;

    public function __construct(
        private readonly int $length = self::DEFAULT_LENGTH,
    ) {
        if ($length < 4 || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf(
                'The OTP length must be between 4 and %d digits, %d given.',
                self::MAX_LENGTH,
                $length,
            ));
        }
    }

    /**
     * Returns a fresh passcode of the configured length.
     */
    public function generate(): string
    {
        $code = '';

        // Drawing digit by digit keeps the distribution uniform for any
        // length, without ever building an integer that could overflow.
        for ($i = 0; $i < $this->length; ++$i) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    public function length(): int
    {
        return $this->length;
    }

    /**
     * Compares a stored code with the one the user typed, in constant time.
     *
     * A plain `===` leaks how much of the code was correct through the time it
     * takes to fail, which is exactly the sort of side channel a brute-force
     * attack feeds on.
     */
    public static function matches(string $expected, string $provided): bool
    {
        return hash_equals($expected, $provided);
    }
}
