# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 2.x | ✅ |
| 1.x | ❌ — please upgrade, see [UPGRADING.md](UPGRADING.md) |

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately through
[GitHub Security Advisories](https://github.com/EdenOhanaProgramming/smsFreePHP/security/advisories/new),
or by email to edenohana72@gmail.com.

Include what you can: the affected version, a description of the issue, and the steps to reproduce
it. You can expect an acknowledgement within a few days, and an estimate of the fix timeline once the
report has been confirmed.

## Handling credentials with this library

- Keep the username, password and API key out of the source tree. `Credentials::fromEnvironment()`
  reads them from the environment for exactly this reason.
- Credentials are redacted from `var_dump()` output and never appear in an exception message, but a
  full stack trace can still capture constructor arguments. Do not log raw traces from a production
  request.
- TLS verification cannot be turned off. If a host has no usable certificate store, point
  `ClientOptions::withCaBundlePath()` at a CA bundle instead of weakening the connection that carries
  your credentials.

## One-time passcodes

`OtpGenerator` draws from `random_int()`, PHP's cryptographically secure source, and compares codes
with `hash_equals()` so a comparison cannot be timed. The parts it cannot do for you:

- Store the code hashed, the way you would a password, not in plain text.
- Give it a short expiry — a few minutes.
- Limit the number of attempts per code, and rate-limit how often a new code can be requested for the
  same number. Without that, a six-digit code is a million guesses away from anyone patient.
