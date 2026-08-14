<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\InvalidRecipientPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientOptions::class)]
final class ClientOptionsTest extends TestCase
{
    public function testTheDefaultsAreSafeForAWebRequest(): void
    {
        $options = new ClientOptions();

        self::assertSame(ClientOptions::DEFAULT_ENDPOINT, $options->endpoint());
        self::assertSame(134, $options->maxMessageLength());
        self::assertTrue($options->truncatesLongMessages());
        self::assertFalse($options->allowsInternational());
        self::assertSame(InvalidRecipientPolicy::SkipInvalid, $options->invalidRecipientPolicy());

        // 1.x used CURLOPT_CONNECTTIMEOUT = 0 (wait forever) and a 400 second
        // timeout, which is long enough to hang a page until the visitor gives
        // up. Both are now bounded.
        self::assertGreaterThan(0.0, $options->connectTimeout());
        self::assertLessThanOrEqual(30.0, $options->timeout());
    }

    public function testItRefusesAPlainHttpEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientOptions(endpoint: 'http://api.sms4free.co.il/ApiSMS/v2/SendSMS');
    }

    public function testItRefusesAnEndpointThatIsNotAUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientOptions(endpoint: 'not a url');
    }

    public function testItRefusesANonPositiveTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientOptions(timeout: 0.0);
    }

    public function testItRefusesACaBundleItCannotRead(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientOptions(caBundlePath: '/nowhere/ca-bundle.crt');
    }

    public function testWithersReturnAModifiedCopy(): void
    {
        $original = new ClientOptions();
        $modified = $original
            ->withTimeouts(1.5, 2.5)
            ->withMaxMessageLength(70)
            ->withMessageTruncation(false)
            ->withInternationalRecipients(true)
            ->withUserAgent('my-app/1.0')
            ->withInvalidRecipientPolicy(InvalidRecipientPolicy::RejectRequest);

        self::assertSame(1.5, $modified->connectTimeout());
        self::assertSame(2.5, $modified->timeout());
        self::assertSame(70, $modified->maxMessageLength());
        self::assertFalse($modified->truncatesLongMessages());
        self::assertTrue($modified->allowsInternational());
        self::assertSame('my-app/1.0', $modified->userAgent());
        self::assertSame(InvalidRecipientPolicy::RejectRequest, $modified->invalidRecipientPolicy());

        // The original is untouched.
        self::assertSame(134, $original->maxMessageLength());
        self::assertTrue($original->truncatesLongMessages());
        self::assertStringContainsString('smsFreePHP/', $original->userAgent());
        self::assertSame(InvalidRecipientPolicy::SkipInvalid, $original->invalidRecipientPolicy());
    }

    public function testACustomUserAgentCanBeCleared(): void
    {
        $options = (new ClientOptions())->withUserAgent('my-app/1.0')->withUserAgent(null);

        self::assertStringContainsString('smsFreePHP/', $options->userAgent());
    }
}
