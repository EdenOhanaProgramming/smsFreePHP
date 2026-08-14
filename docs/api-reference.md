# API reference

[English](api-reference.md) | [עברית](api-reference.he.md) | [Laravel](laravel.md) | [Back to the README](../README.md)

Every class lives in the `EdenOhana\SmsFree` namespace unless stated otherwise.

---

## `Sms4FreeClient`

The entry point. Immutable, and safe to register as a long-lived service.

```php
new Sms4FreeClient(
    Credentials $credentials,
    ClientOptions $options = new ClientOptions(),
    ?HttpClient $httpClient = null,
)
```

| Method | Returns | Description |
|---|---|---|
| `create(string $username, string $password, string $apiKey)` | `self` | Shortcut for the common case, using the default options. |
| `send(string $senderName, iterable\|string\|PhoneNumber $recipients, string\|Message $message)` | `SendResult` | Sends one message to one or more recipients. Throws on any failure. |
| `findInvalidRecipients(iterable $recipients)` | `list<string>` | The raw values that cannot be parsed. Makes no network call. |
| `options()` | `ClientOptions` | The options this client was built with. |

`send()` in detail:

1. The sender is trimmed and must not be empty.
2. Recipients are parsed and normalised; **all** bad values are reported at once.
3. Duplicate recipients are collapsed, so nobody is billed for twice in one call.
4. The body is validated and, if it exceeds the configured limit, either truncated or rejected.
5. The request is posted as JSON with Hebrew left unescaped.
6. A positive `status` in the response becomes a `SendResult`; anything else becomes an `ApiException`.

Throws: `InvalidArgumentException`, `InvalidPhoneNumberException`, `TransportException`, `ApiException`.

---

## `Credentials`

Immutable holder for the account details. The password and API key are redacted from `var_dump()`.

```php
new Credentials(string $username, string $password, string $apiKey)
Credentials::fromEnvironment(string $prefix = 'SMS4FREE_'): self
```

`fromEnvironment()` reads `{PREFIX}USERNAME`, `{PREFIX}PASSWORD` and `{PREFIX}API_KEY`, and throws
`InvalidArgumentException` naming the first variable that is missing.

Accessors: `username()`, `password()`, `apiKey()`.

---

## `ClientOptions`

Immutable configuration. Every `with*()` method returns a modified copy.

| Option | Default | Notes |
|---|---|---|
| `endpoint` | `https://api.sms4free.co.il/ApiSMS/v2/SendSMS` | Must be an absolute **HTTPS** URL. |
| `connectTimeout` | `5.0` seconds | Must be greater than zero. |
| `timeout` | `15.0` seconds | Whole-request budget. |
| `maxMessageLength` | `134` characters | The provider's limit. |
| `truncateLongMessages` | `true` | `false` turns an over-long body into an exception. |
| `allowInternational` | `false` | `true` accepts non-Israeli recipients. |
| `invalidRecipients` | `InvalidRecipientPolicy::SkipInvalid` | Whether an unparseable recipient rejects the request or is skipped. |
| `caBundlePath` | `null` | For hosts whose PHP has no usable CA store. Must be readable. |
| `userAgent` | `smsFreePHP/<version>` | Sent with every request. |

Withers: `withEndpoint()`, `withTimeouts()`, `withMaxMessageLength()`, `withMessageTruncation()`,
`withInternationalRecipients()`, `withInvalidRecipientPolicy()`, `withCaBundlePath()`, `withUserAgent()`.

TLS verification is not configurable. Requests carry account credentials, and there is no acceptable
reason to send those over an unauthenticated connection; a broken CA store is fixed with
`withCaBundlePath()`.

---

## `PhoneNumber`

A parsed, normalised recipient.

```php
PhoneNumber::parse(string $raw, bool $allowInternational = false): self          // throws
PhoneNumber::tryParse(string $raw, bool $allowInternational = false): ?self      // returns null
PhoneNumber::parseList(iterable $numbers, bool $allowInternational = false): array
PhoneNumber::partition(iterable $numbers, bool $allowInternational = false): array
```

`parseList()` reports every invalid entry in one `InvalidPhoneNumberException`, rather than stopping
at the first, and passes already-parsed instances through untouched.

`partition()` is the same walk without the verdict: it returns `[list<PhoneNumber>, list<string>]`,
the numbers that parsed and the raw values that did not, both in their original order.

| Method | Example |
|---|---|
| `raw()` | `'054-123-4567'`, exactly what was supplied |
| `national()` | `'0541234567'`, the form sent to the provider |
| `e164()` | `'+972541234567'`, or `null` when no country code could be determined |
| `isIsraeli()` | `true` |
| `equals(PhoneNumber $other)` | compares canonical forms |
| `__toString()` | same as `national()` |

Accepted Israeli formats, all of which are the same line:

```
0541234567      054-123-4567      054 123 4567      (054) 123-4567
054.123.4567    +972541234567     +972 54 123 4567  00972541234567
+9720541234567  972541234567      541234567
```

Rejected in the default mode: landlines, numbers of the wrong length, anything containing letters,
and foreign numbers.

---

## `Message`

The body of an SMS.

```php
Message::of(string $text): self   // throws on an empty body or invalid UTF-8
```

| Method | Returns | Description |
|---|---|---|
| `text()` | `string` | The body. |
| `length()` | `int` | Characters as a human counts them, not bytes. |
| `encoding()` | `SmsEncoding` | `Gsm7` or `Ucs2`. |
| `parts()` | `int` | How many SMS parts the network splits this into, i.e. how many credits. |
| `truncateTo(int $maxLength)` | `self` | Cuts on a character boundary. Returns `$this` when nothing needs cutting. |
| `isTruncated()` | `bool` | Whether this instance came from a `truncateTo()` that actually cut. |

---

## `InvalidRecipientPolicy`

A backed enum deciding what an unparseable recipient does to a request.

| Case | Behaviour |
|---|---|
| `RejectRequest` | Nothing is sent, nothing is charged, and `InvalidPhoneNumberException` lists every bad entry. |
| `SkipInvalid` (default) | The valid recipients are sent to; the rest come back from `SendResult::skippedRecipients()`. |

Under `SkipInvalid`, a request where *every* recipient is invalid still throws. Sending to nobody is
never what the caller meant, and a silent success there would hide the problem instead of reporting
it.

---

## `SmsEncoding`

A backed enum describing the alphabet a message travels in.

| Case | Single part | Multi part |
|---|---|---|
| `SmsEncoding::Gsm7` | 160 characters | 153 per part |
| `SmsEncoding::Ucs2` | 70 characters | 67 per part |

`SmsEncoding::detect(string $text)` picks the alphabet a given text needs. Hebrew, Arabic and emoji
all force `Ucs2`. Characters from the GSM extension table (`^ { } [ ] ~ | \ €`) cost two septets each.

---

## `SendResult`

What `send()` returns.

| Method | Returns | Description |
|---|---|---|
| `acceptedCount()` | `int` | Messages the provider accepted. Can be lower than the recipient count. |
| `recipients()` | `list<PhoneNumber>` | The normalised, de-duplicated recipients. |
| `recipientNumbers()` | `list<string>` | The same, as canonical strings. |
| `message()` | `Message` | The body as it was actually sent. |
| `wasTruncated()` | `bool` | Whether the body had to be shortened. |
| `skippedRecipients()` | `list<string>` | Raw values left out under `SkipInvalid`; empty otherwise. |
| `hasSkippedRecipients()` | `bool` | Whether anything was left out. |
| `providerMessage()` | `string` | Any text the provider returned alongside the success. |
| `estimatedCredits()` | `int` | `parts × recipients`. The provider's billing is authoritative. |

---

## `Otp\OtpGenerator`

```php
new OtpGenerator(int $length = 6)   // 4 to 32 digits
```

| Method | Returns | Description |
|---|---|---|
| `generate()` | `string` | A fresh code from `random_int()`. A string, so leading zeros survive. |
| `length()` | `int` | The configured length. |
| `OtpGenerator::matches(string $expected, string $provided)` | `bool` | Constant-time comparison via `hash_equals()`. |

---

## `Http\HttpClient`

The seam between the library and the network.

```php
interface HttpClient
{
    public function post(string $url, string $body, array $headers = []): HttpResponse;
}
```

`Http\CurlHttpClient` is the default implementation, built on ext-curl. `Http\HttpResponse` exposes
`statusCode()`, `body()` and `isSuccessful()`.

Implement `HttpClient` to route requests through your own HTTP stack, or to fake the provider in
tests without any network access.

---

## Exceptions

All of them implement `Exception\SmsFreeException`, which extends `Throwable`.

| Class | Extends | Extra methods |
|---|---|---|
| `Exception\InvalidArgumentException` | `\InvalidArgumentException` | none |
| `Exception\InvalidPhoneNumberException` | the above | `invalidNumbers(): list<string>` |
| `Exception\TransportException` | `\RuntimeException` | none |
| `Exception\ApiException` | `\RuntimeException` | `status(): int`, `providerMessage(): string` |

`ApiException` deliberately does not translate the provider's status codes into named constants: the
provider is free to change that table, and passing the raw code and message through means the
library never claims to know something it does not.

---

## Legacy: `SMSService`

The 1.x class, kept working on top of the new code and deprecated since 2.0. It uses the old
contract, `true` on success and a Hebrew error string on failure, and triggers an `E_USER_DEPRECATED`
notice once per process. See [UPGRADING.md](../UPGRADING.md).
