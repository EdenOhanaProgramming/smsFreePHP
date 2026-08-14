<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Exception;

/**
 * Thrown when one or more recipient numbers cannot be parsed.
 *
 * The offending values are preserved exactly as the caller supplied them,
 * which makes the exception directly usable in a validation error message.
 */
final class InvalidPhoneNumberException extends InvalidArgumentException
{
    /**
     * @param list<string> $invalidNumbers the raw, unmodified input values that failed to parse
     */
    public function __construct(
        private readonly array $invalidNumbers,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::describe($invalidNumbers));
    }

    /**
     * @return list<string> the raw, unmodified input values that failed to parse
     */
    public function invalidNumbers(): array
    {
        return $this->invalidNumbers;
    }

    /**
     * @param list<string> $invalidNumbers
     */
    private static function describe(array $invalidNumbers): string
    {
        if ($invalidNumbers === []) {
            return 'The recipient list contains an invalid phone number.';
        }

        return \sprintf(
            'The following phone numbers are invalid: %s',
            implode(', ', $invalidNumbers),
        );
    }
}
