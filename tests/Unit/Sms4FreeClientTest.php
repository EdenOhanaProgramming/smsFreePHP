<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\ApiException;
use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\Exception\TransportException;
use EdenOhana\SmsFree\Http\HttpResponse;
use EdenOhana\SmsFree\InvalidRecipientPolicy;
use EdenOhana\SmsFree\Message;
use EdenOhana\SmsFree\Sms4FreeClient;
use EdenOhana\SmsFree\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sms4FreeClient::class)]
final class Sms4FreeClientTest extends TestCase
{
    public function testItSendsThePayloadTheProviderExpects(): void
    {
        $http = FakeHttpClient::respondingWith(1);
        $client = $this->client($http);

        $client->send('MyShop', ['054-123-4567', '052 111 1111'], 'הקוד שלך הוא 123456');

        self::assertSame(ClientOptions::DEFAULT_ENDPOINT, $http->lastRequest()['url']);
        self::assertSame('application/json', $http->lastRequest()['headers']['Content-Type']);
        self::assertSame([
            'key' => 'api-key',
            'user' => 'user',
            'pass' => 'secret',
            'sender' => 'MyShop',
            'recipient' => '0541234567,0521111111',
            'msg' => 'הקוד שלך הוא 123456',
        ], $http->lastPayload());
    }

    public function testHebrewIsSentAsUtf8RatherThanEscapedEntities(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->client($http)->send('MyShop', ['0541234567'], 'שלום');

        self::assertStringContainsString('שלום', $http->lastRequest()['body']);
        self::assertStringNotContainsString(
            '\\u05e9',
            $http->lastRequest()['body'],
            'Escaping Hebrew to \\uXXXX would inflate the payload for no reason.',
        );
    }

    public function testAPositiveStatusIsTheNumberOfMessagesAccepted(): void
    {
        // 1.x compared the status against `1` and therefore reported a
        // successful send to two recipients as a failure.
        $result = $this->client(FakeHttpClient::respondingWith(2))
            ->send('MyShop', ['0541234567', '0521111111'], 'hello');

        self::assertSame(2, $result->acceptedCount());
        self::assertSame(['0541234567', '0521111111'], $result->recipientNumbers());
        self::assertFalse($result->wasTruncated());
        self::assertSame(2, $result->estimatedCredits());
    }

    public function testItAcceptsABareNumericResponseBody(): void
    {
        $result = $this->client(FakeHttpClient::respondingWithRawBody('1'))
            ->send('MyShop', ['0541234567'], 'hello');

        self::assertSame(1, $result->acceptedCount());
    }

    public function testASingleRecipientDoesNotHaveToBeWrappedInAnArray(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->client($http)->send('MyShop', '0541234567', 'hello');

        self::assertSame('0541234567', $http->lastPayloadField('recipient'));
    }

    public function testDuplicateRecipientsAreCollapsedSoTheyAreBilledOnce(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $result = $this->client($http)->send(
            'MyShop',
            ['054-123-4567', '+972541234567', '0541234567'],
            'hello',
        );

        self::assertSame('0541234567', $http->lastPayloadField('recipient'));
        self::assertCount(1, $result->recipients());
    }

    public function testItRejectsARequestBeforeSpendingACreditOnBadInput(): void
    {
        $http = FakeHttpClient::respondingWith(1);
        $client = $this->client($http);

        try {
            $client->send('MyShop', ['0541234567', 'nonsense'], 'hello');
            self::fail('Expected an InvalidPhoneNumberException.');
        } catch (InvalidPhoneNumberException $e) {
            self::assertSame(['nonsense'], $e->invalidNumbers());
        }

        self::assertSame(0, $http->requestCount(), 'Nothing should reach the provider.');
    }

    public function testItRejectsAnEmptySender(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client(FakeHttpClient::respondingWith(1))->send('  ', ['0541234567'], 'hello');
    }

    public function testItRejectsAnEmptyRecipientList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client(FakeHttpClient::respondingWith(1))->send('MyShop', [], 'hello');
    }

    public function testItRejectsAnEmptyBody(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client(FakeHttpClient::respondingWith(1))->send('MyShop', ['0541234567'], '');
    }

    public function testALongBodyIsTruncatedSafelyAndTheResultSaysSo(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $result = $this->client($http)->send('MyShop', ['0541234567'], str_repeat('א', 200));

        self::assertTrue($result->wasTruncated());
        self::assertSame(134, mb_strlen($http->lastPayloadField('msg')));
        self::assertTrue(mb_check_encoding($http->lastPayloadField('msg'), 'UTF-8'));
    }

    public function testTruncationCanBeTurnedIntoAHardFailure(): void
    {
        $client = new Sms4FreeClient(
            $this->credentials(),
            (new ClientOptions())->withMessageTruncation(false),
            $http = FakeHttpClient::respondingWith(1),
        );

        try {
            $client->send('MyShop', ['0541234567'], str_repeat('a', 200));
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('134 character limit', $e->getMessage());
        }

        self::assertSame(0, $http->requestCount());
    }

    public function testAnAlreadyBuiltMessageObjectIsAccepted(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->client($http)->send('MyShop', ['0541234567'], Message::of('hello'));

        self::assertSame('hello', $http->lastPayloadField('msg'));
    }

    public function testANonPositiveStatusBecomesAnApiExceptionThatKeepsTheProviderDetail(): void
    {
        try {
            $this->client(FakeHttpClient::respondingWith(-3, 'שם משתמש או סיסמה שגויים'))
                ->send('MyShop', ['0541234567'], 'hello');

            self::fail('Expected an ApiException.');
        } catch (ApiException $e) {
            self::assertSame(-3, $e->status());
            self::assertSame('שם משתמש או סיסמה שגויים', $e->providerMessage());
            self::assertStringContainsString('status -3', $e->getMessage());
        }
    }

    public function testAZeroStatusIsAFailureToo(): void
    {
        $this->expectException(ApiException::class);

        $this->client(FakeHttpClient::respondingWith(0))->send('MyShop', ['0541234567'], 'hello');
    }

    public function testAnHttpErrorBecomesATransportException(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('HTTP 502');

        $this->client(new FakeHttpClient(new HttpResponse(502, '<html>Bad Gateway</html>')))
            ->send('MyShop', ['0541234567'], 'hello');
    }

    /**
     * A provider that answers with an HTML error page used to make 1.x call a
     * property on `null`; it must produce a clear exception instead.
     */
    public function testAnUnparseableBodyBecomesATransportException(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->client(FakeHttpClient::respondingWithRawBody('<html>maintenance</html>'))
            ->send('MyShop', ['0541234567'], 'hello');
    }

    public function testJsonWithoutAStatusFieldBecomesATransportException(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('unexpected response shape');

        $this->client(FakeHttpClient::respondingWithRawBody('{"result":"ok"}'))
            ->send('MyShop', ['0541234567'], 'hello');
    }

    public function testAnEmptyBodyBecomesATransportException(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('empty response body');

        $this->client(FakeHttpClient::respondingWithRawBody('   '))
            ->send('MyShop', ['0541234567'], 'hello');
    }

    public function testAForeignResponseIsTruncatedBeforeItReachesAnExceptionMessage(): void
    {
        try {
            $this->client(FakeHttpClient::respondingWithRawBody(str_repeat('x', 5000)))
                ->send('MyShop', ['0541234567'], 'hello');

            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            self::assertLessThan(400, mb_strlen($e->getMessage()));
        }
    }

    public function testInvalidRecipientsCanBeCheckedWithoutSendingAnything(): void
    {
        $http = FakeHttpClient::respondingWith(1);
        $client = $this->client($http);

        self::assertSame([], $client->findInvalidRecipients(['0541234567', '+972 52 111 1111']));
        self::assertSame(['03-1234567'], $client->findInvalidRecipients(['0541234567', '03-1234567']));
        self::assertSame(0, $http->requestCount());
    }

    public function testInvalidRecipientsCanBeSkippedInsteadOfRejectingTheRequest(): void
    {
        $http = FakeHttpClient::respondingWith(2);

        $result = $this->skipping($http)->send(
            'MyShop',
            ['054-123-4567', '03-1234567', '052 111 1111', 'nonsense'],
            'hello',
        );

        self::assertSame('0541234567,0521111111', $http->lastPayloadField('recipient'));
        self::assertSame(['03-1234567', 'nonsense'], $result->skippedRecipients());
        self::assertTrue($result->hasSkippedRecipients());
        self::assertSame(2, $result->acceptedCount());
    }

    /**
     * Skipping is for salvaging a mostly-good list. A list with nothing usable
     * in it is a mistake, and sending to nobody must not look like success.
     */
    public function testSkippingStillFailsWhenNoRecipientSurvives(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        try {
            $this->skipping($http)->send('MyShop', ['03-1234567', 'nonsense'], 'hello');
            self::fail('Expected an InvalidPhoneNumberException.');
        } catch (InvalidPhoneNumberException $e) {
            self::assertSame(['03-1234567', 'nonsense'], $e->invalidNumbers());
            self::assertStringContainsString('Not one of the 2 recipients', $e->getMessage());
        }

        self::assertSame(0, $http->requestCount());
    }

    public function testTheDefaultPolicyStillRejectsTheWholeRequest(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->expectException(InvalidPhoneNumberException::class);

        try {
            $this->client($http)->send('MyShop', ['0541234567', 'nonsense'], 'hello');
        } finally {
            self::assertSame(0, $http->requestCount());
        }
    }

    public function testAResultWithoutSkippingReportsAnEmptySkippedList(): void
    {
        $result = $this->client(FakeHttpClient::respondingWith(1))
            ->send('MyShop', ['0541234567'], 'hello');

        self::assertSame([], $result->skippedRecipients());
        self::assertFalse($result->hasSkippedRecipients());
    }

    public function testSkippedValuesAreReportedExactlyAsTheyWereSupplied(): void
    {
        $result = $this->skipping(FakeHttpClient::respondingWith(1))
            ->send('MyShop', ['0541234567', '  03-123-4567  '], 'hello');

        self::assertSame(['  03-123-4567  '], $result->skippedRecipients());
    }

    public function testInternationalRecipientsAreOptIn(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $client = new Sms4FreeClient(
            $this->credentials(),
            (new ClientOptions())->withInternationalRecipients(true),
            $http,
        );

        $client->send('MyShop', ['+1 415 555 2671'], 'hello');

        self::assertSame('14155552671', $http->lastPayloadField('recipient'));
    }

    private function client(FakeHttpClient $http): Sms4FreeClient
    {
        return new Sms4FreeClient($this->credentials(), new ClientOptions(), $http);
    }

    private function skipping(FakeHttpClient $http): Sms4FreeClient
    {
        return new Sms4FreeClient(
            $this->credentials(),
            (new ClientOptions())->withInvalidRecipientPolicy(InvalidRecipientPolicy::SkipInvalid),
            $http,
        );
    }

    private function credentials(): Credentials
    {
        return new Credentials('user', 'secret', 'api-key');
    }
}
