<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit\Laravel;

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\Laravel\Sms4FreeChannel;
use EdenOhana\SmsFree\Laravel\Sms4FreeMessage;
use EdenOhana\SmsFree\Message;
use EdenOhana\SmsFree\PhoneNumber;
use EdenOhana\SmsFree\Sms4FreeClient;
use EdenOhana\SmsFree\Tests\Support\AttributeNotifiable;
use EdenOhana\SmsFree\Tests\Support\EloquentLikeNotifiable;
use EdenOhana\SmsFree\Tests\Support\FakeHttpClient;
use EdenOhana\SmsFree\Tests\Support\NotificationWithoutSmsMethod;
use EdenOhana\SmsFree\Tests\Support\RoutedNotifiable;
use EdenOhana\SmsFree\Tests\Support\StubNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sms4FreeChannel::class)]
#[CoversClass(Sms4FreeMessage::class)]
final class Sms4FreeChannelTest extends TestCase
{
    public function testItSendsToTheNumberTheNotifiableRoutes(): void
    {
        $http = FakeHttpClient::respondingWith(1);
        $channel = $this->channel($http, 'MyShop');

        $result = $channel->send(
            new RoutedNotifiable('054-123-4567'),
            new StubNotification('הקוד שלך הוא 123456'),
        );

        self::assertNotNull($result);
        self::assertSame(1, $result->acceptedCount());
        self::assertSame('MyShop', $http->lastPayloadField('sender'));
        self::assertSame('0541234567', $http->lastPayloadField('recipient'));
        self::assertSame('הקוד שלך הוא 123456', $http->lastPayloadField('msg'));
    }

    public function testItFallsBackToAnEloquentAttribute(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->channel($http, 'MyShop')->send(
            new EloquentLikeNotifiable(['phone_number' => '+972 52 111 1111']),
            new StubNotification('hello'),
        );

        self::assertSame('0521111111', $http->lastPayloadField('recipient'));
    }

    public function testItFallsBackToAPlainProperty(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->channel($http, 'MyShop')->send(
            new AttributeNotifiable('054-123-4567'),
            new StubNotification('hello'),
        );

        self::assertSame('0541234567', $http->lastPayloadField('recipient'));
    }

    /**
     * One user without a phone number should not blow up a notification run
     * that is going out to a thousand others.
     */
    public function testANotifiableWithoutANumberIsSkippedQuietly(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $result = $this->channel($http, 'MyShop')->send(
            new RoutedNotifiable(null),
            new StubNotification('hello'),
        );

        self::assertNull($result);
        self::assertSame(0, $http->requestCount());
    }

    public function testAMessageCanOverrideTheConfiguredSender(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->channel($http, 'MyShop')->send(
            new RoutedNotifiable('0541234567'),
            new StubNotification(Sms4FreeMessage::create('hello')->from('Marketing')),
        );

        self::assertSame('Marketing', $http->lastPayloadField('sender'));
    }

    public function testANotificationMayReturnAMessageObject(): void
    {
        $http = FakeHttpClient::respondingWith(1);

        $this->channel($http, 'MyShop')->send(
            new RoutedNotifiable('0541234567'),
            new StubNotification(Message::of('hello')),
        );

        self::assertSame('hello', $http->lastPayloadField('msg'));
    }

    public function testAnAlreadyParsedNumberIsAccepted(): void
    {
        $http = FakeHttpClient::respondingWith(1);
        $channel = $this->channel($http, 'MyShop');

        $notifiable = new class (PhoneNumber::parse('054-123-4567')) {
            public function __construct(private readonly PhoneNumber $phone)
            {
            }

            public function routeNotificationFor(string $driver, ?object $notification = null): PhoneNumber
            {
                return $this->phone;
            }
        };

        $channel->send($notifiable, new StubNotification('hello'));

        self::assertSame('0541234567', $http->lastPayloadField('recipient'));
    }

    public function testANotificationWithoutTheMethodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('toSms4Free()');

        $this->channel(FakeHttpClient::respondingWith(1), 'MyShop')
            ->send(new RoutedNotifiable('0541234567'), new NotificationWithoutSmsMethod());
    }

    public function testItExplainsAMissingSender(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS4FREE_SENDER');

        $this->channel(FakeHttpClient::respondingWith(1), null)
            ->send(new RoutedNotifiable('0541234567'), new StubNotification('hello'));
    }

    private function channel(FakeHttpClient $http, ?string $defaultSender): Sms4FreeChannel
    {
        return new Sms4FreeChannel(
            new Sms4FreeClient(new Credentials('user', 'secret', 'api-key'), new ClientOptions(), $http),
            $defaultSender,
        );
    }
}
