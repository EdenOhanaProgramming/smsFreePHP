# smsFreePHP

[![CI](https://github.com/EdenOhanaProgramming/smsFreePHP/actions/workflows/ci.yml/badge.svg)](https://github.com/EdenOhanaProgramming/smsFreePHP/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4)](https://www.php.net/supported-versions)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A modern, typed PHP client for the [SMS4Free](https://www.sms4free.co.il/) HTTP API — sending SMS,
handling Israeli phone numbers and generating one-time passcodes.

**🇮🇱 [README בעברית](README.he.md)** · [API reference](docs/api-reference.md) · [Upgrading from 1.x](UPGRADING.md)

---

## Why this library

Talking to the SMS4Free API is one `curl` call, so the interesting part is everything around it:

- **Phone numbers arrive messy.** `054-123-4567`, `+972 54 123 4567` and `00972541234567` are the
  same line. They are all parsed into one canonical form, and anything that is not a real Israeli
  mobile number is rejected *before* a request is made.
- **Hebrew breaks naive string handling.** Cutting a message with `substr()` splits a two-byte
  character in half; counting with `strlen()` reports bytes, not characters. Everything here is
  multibyte-safe.
- **Failures need to be distinguishable.** "The number is invalid", "the provider says you are out
  of balance" and "the network is down" call for three different reactions in your application.
  Each one is a different exception type.
- **Credentials are secrets.** They are never echoed into an exception message, and they are
  redacted from `var_dump()` output.

## Requirements

| | |
|---|---|
| PHP | 8.1 or newer |
| Extensions | `curl`, `json`, `mbstring` |
| Account | An [SMS4Free](https://www.sms4free.co.il/) account: username, password and API key |

## Installation

```bash
composer require edenohana/sms-free-php
```

Not using Composer? Copy the folder into your project and require the bundled autoloader:

```php
require_once __DIR__ . '/smsFreePHP/src/autoload.php';
```

## Quick start

```php
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Sms4FreeClient;

$client = new Sms4FreeClient(new Credentials('username', 'password', 'api-key'));

$result = $client->send(
    senderName: 'MyShop',            // a verified sender number, or an approved sender ID
    recipients: ['054-123-4567'],    // one number or a list
    message:    'ההזמנה שלך יצאה לדרך',
);

echo $result->acceptedCount(); // 1
```

Keep secrets out of the source tree by reading them from the environment:

```php
$client = new Sms4FreeClient(Credentials::fromEnvironment());
// reads SMS4FREE_USERNAME, SMS4FREE_PASSWORD and SMS4FREE_API_KEY
```

## Handling failures

`send()` either returns a [`SendResult`](src/SendResult.php) or throws. Every exception the library
raises implements `SmsFreeException`, so a single `catch` is enough when you do not care about the
difference:

```php
use EdenOhana\SmsFree\Exception\ApiException;
use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Exception\TransportException;

try {
    $client->send('MyShop', $recipients, $text);
} catch (InvalidPhoneNumberException $e) {
    // Bad user input. Nothing was sent, nothing was charged.
    $form->addError('phone', implode(', ', $e->invalidNumbers()));
} catch (ApiException $e) {
    // The provider refused: wrong credentials, no balance, unverified sender…
    $logger->error('SMS4Free refused', ['status' => $e->status(), 'reason' => $e->providerMessage()]);
} catch (TransportException $e) {
    // Network trouble. The message may or may not have gone out — see the note on retries below.
    $logger->warning('SMS4Free unreachable', ['error' => $e->getMessage()]);
} catch (SmsFreeException $e) {
    // Anything else from this library.
}
```

| Exception | Meaning | Was a credit spent? |
|---|---|---|
| `InvalidArgumentException` | Empty sender, empty recipient list, empty body, message over the limit | No — the request is never made |
| `InvalidPhoneNumberException` | One or more recipients could not be parsed | No |
| `TransportException` | Timeout, DNS or TLS failure, non-2xx status, unreadable body | Unknown |
| `ApiException` | The provider answered with a non-positive status | Depends on the provider |

## Validating before you send

Checking numbers costs nothing, so validate the form first and only then spend a credit:

```php
$invalid = $client->findInvalidRecipients($rowsFromCsv);

if ($invalid !== []) {
    throw new RuntimeException('Unusable numbers: ' . implode(', ', $invalid));
}
```

Or work with the value object directly:

```php
use EdenOhana\SmsFree\PhoneNumber;

$number = PhoneNumber::parse('054-123-4567');

$number->national(); // '0541234567'   — what the provider is given
$number->e164();     // '+972541234567' — what you want in your database
$number->raw();      // '054-123-4567'  — what the user typed
```

## Message length, Hebrew and credits

A Hebrew message is carried as UCS-2, which fits **70 characters per SMS part** instead of the 160 a
Latin message gets. That is the single most common billing surprise with this provider, so the
library makes it visible:

```php
use EdenOhana\SmsFree\Message;

$message = Message::of('הקוד שלך לאימות הוא 123456');

$message->encoding();  // SmsEncoding::Ucs2
$message->length();    // 26 characters
$message->parts();     // 1 — how many messages the account is billed for
```

SMS4Free accepts up to 134 characters per request. By default a longer body is shortened on a
character boundary and the result tells you it happened:

```php
$result = $client->send('MyShop', $recipients, $veryLongText);

if ($result->wasTruncated()) {
    $logger->notice('The message was shortened before sending.');
}
```

If losing the tail of a message is unacceptable — a link at the end, for instance — turn truncation
into a hard failure:

```php
use EdenOhana\SmsFree\ClientOptions;

$client = new Sms4FreeClient(
    Credentials::fromEnvironment(),
    (new ClientOptions())->withMessageTruncation(false),
);
```

## One-time passcodes

```php
use EdenOhana\SmsFree\Otp\OtpGenerator;

$code = (new OtpGenerator(length: 6))->generate(); // '042317' — a string, so leading zeros survive

$client->send('MyShop', [$phone], "הקוד שלך לאימות הוא: {$code}");

// Later, when the user types it back:
OtpGenerator::matches($storedCode, $typedCode); // constant-time comparison
```

Codes come from `random_int()`, PHP's cryptographically secure generator. Store the code hashed with
an expiry and an attempt limit — [`examples/send-otp.php`](examples/send-otp.php) shows the whole
flow.

## Configuration

```php
use EdenOhana\SmsFree\ClientOptions;

$options = (new ClientOptions())
    ->withTimeouts(connectTimeout: 3.0, timeout: 10.0)
    ->withMessageTruncation(false)
    ->withInternationalRecipients(true)  // accept non-Israeli numbers
    ->withMaxMessageLength(70)
    ->withUserAgent('my-app/2.1')
    ->withCaBundlePath('/etc/ssl/certs/cacert.pem'); // for hosts with no CA store

$client = new Sms4FreeClient(Credentials::fromEnvironment(), $options);
```

Defaults: a 5 second connect timeout and a 15 second overall timeout, truncation on, Israeli
recipients only, TLS verification always on.

### A note on retries

The library does not retry automatically. A timeout does not tell you whether the provider received
the request — the answer may simply have been lost on the way back — so an automatic retry can
quietly send the same message twice and bill you twice. If you want retries, add them where you have
the context to make them safe (a queue with an idempotency key, for example).

## Using a different HTTP stack

The transport sits behind [`HttpClient`](src/Http/HttpClient.php). Implement it to route requests
through Guzzle, Symfony HttpClient, a PSR-18 client, or a fake in your own tests:

```php
final class GuzzleTransport implements HttpClient
{
    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        // …
    }
}

$client = new Sms4FreeClient($credentials, new ClientOptions(), new GuzzleTransport());
```

## Upgrading from 1.x

The old `SMSService` class still ships and still behaves exactly as it did, so upgrading the package
changes nothing until you are ready. It is deprecated and will be removed in 3.0 —
[UPGRADING.md](UPGRADING.md) is a short read.

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, level 9
composer cs        # coding standards (composer cs:fix to apply)
composer check     # all three
```

## Contributing

Bug reports and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Found a security
issue? Please follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## License

[MIT](LICENSE) © Eden Ohana

This project is not affiliated with SMS4Free.
