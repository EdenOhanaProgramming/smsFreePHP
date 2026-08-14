<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\Otp\OtpGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OtpGenerator::class)]
final class OtpGeneratorTest extends TestCase
{
    public function testItGeneratesACodeOfTheRequestedLength(): void
    {
        foreach ([4, 6, 8, 12] as $length) {
            $code = (new OtpGenerator($length))->generate();

            self::assertSame($length, \strlen($code));
            self::assertMatchesRegularExpression('/^\d+$/', $code);
        }
    }

    /**
     * Returning a string is the whole point: `042317` cast to an integer is a
     * five digit code the user can never type correctly.
     */
    public function testLeadingZerosSurvive(): void
    {
        $generator = new OtpGenerator();
        $sawLeadingZero = false;

        for ($i = 0; $i < 500; ++$i) {
            $code = $generator->generate();
            self::assertSame(6, \strlen($code));

            $sawLeadingZero = $sawLeadingZero || str_starts_with($code, '0');
        }

        self::assertTrue($sawLeadingZero, 'In 500 draws a code starting with zero is all but certain.');
    }

    public function testCodesAreNotRepeated(): void
    {
        $generator = new OtpGenerator();
        $codes = [];

        for ($i = 0; $i < 200; ++$i) {
            $codes[] = $generator->generate();
        }

        // A million possible codes; 200 draws should be very nearly unique.
        self::assertGreaterThan(190, \count(array_unique($codes)));
    }

    public function testItRefusesAnUnusableLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OtpGenerator(3);
    }

    public function testItRefusesAnAbsurdLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OtpGenerator(64);
    }

    public function testComparisonIsExact(): void
    {
        self::assertTrue(OtpGenerator::matches('123456', '123456'));
        self::assertFalse(OtpGenerator::matches('123456', '123457'));
        self::assertFalse(OtpGenerator::matches('123456', '12345'));
        self::assertFalse(OtpGenerator::matches('123456', ''));
    }
}
