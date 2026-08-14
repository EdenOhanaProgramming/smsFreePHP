<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneNumber::class)]
final class PhoneNumberTest extends TestCase
{
    /**
     * Every one of these is the same line, written the way a real user would.
     */
    #[DataProvider('israeliMobileFormats')]
    public function testItNormalisesIsraeliMobileNumbers(string $input): void
    {
        $number = PhoneNumber::parse($input);

        self::assertSame('0541234567', $number->national());
        self::assertSame('+972541234567', $number->e164());
        self::assertTrue($number->isIsraeli());
        self::assertSame($input, $number->raw());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function israeliMobileFormats(): iterable
    {
        yield 'plain' => ['0541234567'];
        yield 'hyphenated' => ['054-123-4567'];
        yield 'spaced' => ['054 123 4567'];
        yield 'parenthesised' => ['(054) 123-4567'];
        yield 'dotted' => ['054.123.4567'];
        yield 'international plus' => ['+972541234567'];
        yield 'international spaced' => ['+972 54 123 4567'];
        yield 'international double zero' => ['00972541234567'];
        yield 'international with trunk prefix' => ['+9720541234567'];
        yield 'bare country code' => ['972541234567'];
        yield 'no trunk prefix' => ['541234567'];
        yield 'surrounding whitespace' => ["\t0541234567 "];
    }

    /**
     * The 1.x validator stripped every non-digit before matching, which meant
     * the `+972` branch of its own pattern could never match. These cases lock
     * that regression down.
     */
    #[DataProvider('invalidNumbers')]
    public function testItRejectsNumbersThatAreNotIsraeliMobiles(string $input): void
    {
        self::assertNull(PhoneNumber::tryParse($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
        yield 'too short' => ['054123456'];
        yield 'too long' => ['05412345678'];
        yield 'landline' => ['031234567'];
        yield 'letters' => ['054-ABC-4567'];
        yield 'foreign number' => ['+14155552671'];
        yield 'wrong country code' => ['+9711234567'];
        yield 'not a number at all' => ['not a phone'];
        yield 'sql-ish injection attempt' => ["0541234567' OR 1=1"];
    }

    public function testParseThrowsWithTheOriginalInputInTheMessage(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('054-123-456');

        PhoneNumber::parse('054-123-456');
    }

    public function testItAcceptsForeignNumbersOnlyWhenAskedTo(): void
    {
        self::assertNull(PhoneNumber::tryParse('+14155552671'));

        $number = PhoneNumber::parse('+1 415 555 2671', allowInternational: true);

        self::assertSame('14155552671', $number->national());
        self::assertSame('+14155552671', $number->e164());
        self::assertFalse($number->isIsraeli());
    }

    public function testAForeignNumberWithoutACountryCodeHasNoE164Form(): void
    {
        $number = PhoneNumber::parse('4155552671', allowInternational: true);

        self::assertNull($number->e164(), 'A country code must never be invented.');
    }

    public function testParseListReportsEveryInvalidEntryAtOnce(): void
    {
        try {
            PhoneNumber::parseList(['0541234567', 'nope', '0521111111', '03-1234567']);

            self::fail('Expected an InvalidPhoneNumberException.');
        } catch (InvalidPhoneNumberException $e) {
            self::assertSame(['nope', '03-1234567'], $e->invalidNumbers());
        }
    }

    public function testParseListPassesThroughAlreadyParsedNumbers(): void
    {
        $existing = PhoneNumber::parse('0541234567');

        $parsed = PhoneNumber::parseList([$existing, '052-111-1111']);

        self::assertCount(2, $parsed);
        self::assertTrue($parsed[0]->equals($existing));
        self::assertSame('0521111111', $parsed[1]->national());
    }

    public function testDifferentlyWrittenNumbersAreEqual(): void
    {
        self::assertTrue(
            PhoneNumber::parse('054-123-4567')->equals(PhoneNumber::parse('+972541234567')),
        );
    }

    public function testItCastsToItsCanonicalForm(): void
    {
        self::assertSame('0541234567', (string) PhoneNumber::parse('054 123 4567'));
    }
}
