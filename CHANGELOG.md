# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0]

A rewrite of the internals with a compatibility layer on top: 1.x code keeps working unchanged.
See [UPGRADING.md](UPGRADING.md).

### Added

- `Sms4FreeClient`, a namespaced, typed client that returns a `SendResult` and throws typed
  exceptions instead of returning `true` or an error string.
- `PhoneNumber`, which parses and normalises Israeli mobile numbers written in any common format
  (`054-123-4567`, `+972 54 123 4567`, `00972...`), exposes `national()` and `e164()`, and keeps the
  raw input for error messages. Optional lenient mode for international numbers.
- `Message` and `SmsEncoding`, for multibyte-safe body handling, GSM-7/UCS-2 detection and SMS part
  counting, so the cost of a Hebrew message is visible before it is sent.
- `Credentials`, validated, immutable, and redacted from `var_dump()`; `fromEnvironment()` reads
  `SMS4FREE_USERNAME`, `SMS4FREE_PASSWORD` and `SMS4FREE_API_KEY`.
- `ClientOptions`, covering endpoint, timeouts, message-length policy, international recipients, CA bundle
  and User-Agent, all immutable with `with*()` copies.
- `SendResult`, carrying the accepted count, normalised recipients, whether the body was truncated, and an
  estimate of the credits consumed.
- `Otp\OtpGenerator`, with configurable length, `random_int()` as the source, string return so leading
  zeros survive, and a constant-time `matches()`.
- `Http\HttpClient`. The transport is now an interface, so the provider can be faked in tests or
  replaced with an existing HTTP stack. `Http\CurlHttpClient` is the default.
- A typed exception hierarchy behind one `SmsFreeException` interface.
- A PHPUnit suite, PHPStan at level 9, a PHP-CS-Fixer ruleset, and CI across PHP 8.1 to 8.4.
- Documentation in English and Hebrew, a bilingual API reference, and runnable examples.
- `src/autoload.php`, a standalone PSR-4 autoloader for projects that do not use Composer.
- Laravel support: an auto-discovered service provider, a publishable config file, a `Sms4Free`
  facade and an `sms4free` notification channel, for Laravel 9 through 12. `illuminate/*` stays a
  development dependency, so nothing changes for projects that do not use the framework.

### Fixed

- **Every `+972...` number was rejected.** `getInvalidPhoneNumbers()` stripped non-digits before
  matching, so the `(\+972)?` branch of its own pattern could never match.
- **A successful multi-recipient send was reported as a failure.** The response status is the number
  of messages accepted, but it was compared against `1`.
- **A non-JSON response caused a property access on `null`.** When the provider answered with an
  HTML error page, `$data->status` and `$data->message` produced warnings and an error message with
  no detail. Unparseable and non-2xx responses now raise a `TransportException` that includes a
  bounded snippet of what came back.
- **Truncation corrupted Hebrew.** `substr($message, 0, 134)` cuts UTF-8 mid-character; truncation
  now happens on a character boundary and is reported through `SendResult::wasTruncated()`.
- **Invalid numbers were echoed back in a form the user never typed**: the digit-stripped version
  rather than the original input.
- **Requests could hang for minutes.** `CURLOPT_CONNECTTIMEOUT` was `0` (wait forever) and
  `CURLOPT_TIMEOUT` was 400 seconds. The defaults are now 5 and 15 seconds.
- **Calling `sendSMS()` without `smsAuth()` was a fatal error** ("typed property must not be accessed
  before initialization"). Credentials are now required at construction, and the legacy class returns
  a clear message.
- **The cURL handle leaked on the exception path**; it is now closed in a `finally` block.
- **The library disabled the script time limit for the whole request** via a top-level
  `set_time_limit(0)`. That call is gone.
- **The class was global and unnamespaced**, so it collided with any application class of the same
  name.
- **The documented success check was wrong.** The 1.x README used `if ($result == true)`, and a
  non-empty error string is truthy under a loose comparison, so failures were reported as successes.

### Deprecated

- `SMSService`, still shipped and still behaving exactly as it did in 1.x, now implemented on top of
  the new client. It triggers one `E_USER_DEPRECATED` notice per process and will be removed in 3.0.

### Changed

- Minimum PHP version is now 8.1.

## [1.0.0]

- Initial release: `SMSService` with `smsAuth()`, `sendSMS()`, `generateRandomOTP()` and
  `getInvalidPhoneNumbers()`.

[Unreleased]: https://github.com/EdenOhanaProgramming/smsFreePHP/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/EdenOhanaProgramming/smsFreePHP/releases/tag/v2.0.0
[1.0.0]: https://github.com/EdenOhanaProgramming/smsFreePHP/releases/tag/v1.0.0
