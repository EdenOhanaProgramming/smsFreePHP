# Laravel integration

[English](laravel.md) | [עברית](laravel.he.md) | [Back to the README](../README.md)

The package ships a service provider, a facade and a notification channel. Laravel discovers the
provider automatically, so there is nothing to register by hand.

Supported: Laravel 11 and 12, the versions still receiving fixes. Applications that do not use
Laravel are unaffected, since `illuminate/*` is only a development dependency here.

## Setup

Add the account details to `.env`:

```dotenv
SMS4FREE_USERNAME=your-username
SMS4FREE_PASSWORD=your-password
SMS4FREE_API_KEY=your-api-key
SMS4FREE_SENDER=MyShop
```

That's enough to start sending. To change timeouts or the truncation policy, publish the config
file first:

```bash
php artisan vendor:publish --tag=sms4free-config
```

It lands in `config/sms4free.php` and covers the endpoint, both timeouts, the message length limit,
whether an over-long body is truncated or rejected, whether non-Israeli recipients are allowed, and
an optional CA bundle path.

For a bulk send, the setting worth knowing about is `invalid_recipients`. It defaults to `reject`,
where one unparseable number fails the whole request. Set `SMS4FREE_INVALID_RECIPIENTS=skip` and the
message goes to the recipients that are valid, with the rest available from
`SendResult::skippedRecipients()`.

## Sending directly

Inject the client wherever you need it:

```php
use EdenOhana\SmsFree\Sms4FreeClient;

final class OrderController
{
    public function __construct(private readonly Sms4FreeClient $sms)
    {
    }

    public function ship(Order $order)
    {
        $this->sms->send('MyShop', [$order->customer->phone_number], 'ההזמנה שלך יצאה לדרך');
    }
}
```

Or use the facade:

```php
use EdenOhana\SmsFree\Laravel\Facades\Sms4Free;

Sms4Free::send('MyShop', ['054-123-4567'], 'ההזמנה שלך יצאה לדרך');
```

## Notifications

Add `sms4free` to the notification's channels and give it a `toSms4Free()` method:

```php
use Illuminate\Notifications\Notification;

final class VerificationCode extends Notification
{
    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['sms4free'];
    }

    public function toSms4Free(object $notifiable): string
    {
        return "הקוד שלך לאימות הוא: {$this->code}";
    }
}
```

```php
$user->notify(new VerificationCode($code));
```

To override the sender for one notification, return a message object instead of a string:

```php
use EdenOhana\SmsFree\Laravel\Sms4FreeMessage;

public function toSms4Free(object $notifiable): Sms4FreeMessage
{
    return Sms4FreeMessage::create('מבצע סוף עונה, 30% הנחה')->from('Marketing');
}
```

### Where the phone number comes from

The channel looks in three places, in this order:

1. `routeNotificationForSms4free()` on the notifiable, which is Laravel's own convention:

   ```php
   public function routeNotificationForSms4free(): ?string
   {
       return $this->mobile;
   }
   ```

2. A `phone_number` attribute on an Eloquent model.
3. A public `phone_number` property on a plain object.

A notifiable with none of them is skipped and `send()` returns null. One user without a phone number
should not fail a notification run going out to a thousand others.

Any format the library accepts works here, so `054-123-4567` and `+972 54 123 4567` are both fine.

## Queued notifications

Nothing special is needed: implement `ShouldQueue` on the notification as usual. Two things worth
knowing when you do.

The library never retries on its own, because a timeout does not prove the message was not
delivered. On a queued notification, Laravel's retry is the one that decides, so set `$tries` with
that in mind: a retry after a timeout can send the same SMS twice.

An `InvalidPhoneNumberException` means the input was bad and retrying will not help. Add it to the
job's `$dontRetry` list, or catch it in `failed()`.

## Testing

Bind a fake transport and no request leaves the machine:

```php
use EdenOhana\SmsFree\Http\HttpClient;
use EdenOhana\SmsFree\Http\HttpResponse;

$this->app->bind(HttpClient::class, fn () => new class implements HttpClient {
    public array $sent = [];

    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        $this->sent[] = $body;

        return new HttpResponse(200, '{"status":1,"message":"ok"}');
    }
});
```

The transport is bound separately from the client for exactly this reason. For notifications,
Laravel's own `Notification::fake()` works as it does with any other channel.
