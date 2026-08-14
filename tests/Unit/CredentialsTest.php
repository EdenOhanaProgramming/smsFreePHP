<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Credentials::class)]
final class CredentialsTest extends TestCase
{
    /**
     * @param array{string, string, string} $arguments
     */
    #[DataProvider('incompleteCredentials')]
    public function testItRefusesIncompleteCredentials(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Credentials(...$arguments);
    }

    /**
     * @return iterable<string, array{array{string, string, string}}>
     */
    public static function incompleteCredentials(): iterable
    {
        yield 'no username' => [['', 'password', 'key']];
        yield 'no password' => [['user', '', 'key']];
        yield 'no api key' => [['user', 'password', '']];
        yield 'whitespace only' => [['user', '   ', 'key']];
    }

    public function testItReadsCredentialsFromTheEnvironment(): void
    {
        putenv('SMS4FREE_USERNAME=user');
        putenv('SMS4FREE_PASSWORD=secret');
        putenv('SMS4FREE_API_KEY=key');

        try {
            $credentials = Credentials::fromEnvironment();

            self::assertSame('user', $credentials->username());
            self::assertSame('secret', $credentials->password());
            self::assertSame('key', $credentials->apiKey());
        } finally {
            putenv('SMS4FREE_USERNAME');
            putenv('SMS4FREE_PASSWORD');
            putenv('SMS4FREE_API_KEY');
        }
    }

    public function testItNamesTheMissingEnvironmentVariable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ACME_USERNAME');

        Credentials::fromEnvironment('ACME_');
    }

    /**
     * A stack trace or a `var_dump()` in a log file must never expose the
     * account password.
     */
    public function testSecretsAreRedactedFromDebugOutput(): void
    {
        $credentials = new Credentials('user', 'super-secret', 'super-key');

        ob_start();
        var_dump($credentials);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString('super-secret', $dump);
        self::assertStringNotContainsString('super-key', $dump);
        self::assertStringContainsString('user', $dump);
    }
}
