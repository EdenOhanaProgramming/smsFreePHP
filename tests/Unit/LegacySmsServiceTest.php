<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The 1.x `SMSService` class is deprecated but still shipped, so that an
 * existing project can upgrade the package without editing any code. These
 * tests pin the parts of its old contract that can be checked without
 * touching the network.
 */
#[CoversNothing]
final class LegacySmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        require_once \dirname(__DIR__, 2) . '/SMSService.php';
    }

    public function testItStillReportsErrorsAsHebrewStrings(): void
    {
        $service = new \SMSService();
        $service->smsAuth('user', 'password', 'key');

        self::assertSame('שולח או הודעה ריקים.', $service->sendSMS('', ['0541234567'], 'hello'));
        self::assertSame('שולח או הודעה ריקים.', $service->sendSMS('sender', ['0541234567'], ''));
        self::assertSame('לא הוזנו נמענים להודעה.', $service->sendSMS('sender', [], 'hello'));
        self::assertSame(
            'המספרים הבאים אינם תקינים: 12345.',
            $service->sendSMS('sender', ['12345'], 'hello'),
        );
    }

    /**
     * 1.x would fatal with "typed property must not be accessed before
     * initialization" when `smsAuth()` had been forgotten. Now it explains
     * itself.
     */
    public function testItExplainsMissingCredentialsInsteadOfCrashing(): void
    {
        $result = (new \SMSService())->sendSMS('sender', ['0541234567'], 'hello');

        self::assertIsString($result);
        self::assertStringContainsString('smsAuth', $result);
    }

    public function testGenerateRandomOtpStillReturnsASixDigitInteger(): void
    {
        $service = new \SMSService();

        for ($i = 0; $i < 100; ++$i) {
            $code = $service->generateRandomOTP();

            self::assertIsInt($code);
            self::assertGreaterThanOrEqual(100000, $code);
            self::assertLessThanOrEqual(999999, $code);
        }
    }

    public function testGetInvalidPhoneNumbersReturnsOnlyTheBadOnes(): void
    {
        $service = new \SMSService();

        self::assertSame(
            ['03-1234567', 'nonsense'],
            $service->getInvalidPhoneNumbers(['054-123-4567', '03-1234567', '+972521111111', 'nonsense']),
        );
    }
}
