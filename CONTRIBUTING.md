# Contributing

Thanks for taking the time. Bug reports, ideas and pull requests are all welcome.

## Getting set up

```bash
git clone https://github.com/EdenOhanaProgramming/smsFreePHP.git
cd smsFreePHP
composer install
composer check   # coding standards, static analysis and tests
```

You need PHP 8.1 or newer with the `curl`, `json` and `mbstring` extensions.

## Before opening a pull request

- `composer check` passes. That is the same gate CI runs.
- New behaviour comes with a test. The suite never touches the network — implement
  `Http\HttpClient` (see `tests/Support/FakeHttpClient.php`) instead of calling the provider.
- Public API changes are reflected in both `README.md` and `README.he.md`, and in both
  `docs/api-reference.md` and `docs/api-reference.he.md`.
- Anything user-visible gets a line in `CHANGELOG.md` under `Unreleased`.
- Commits are written in the imperative mood and explain *why*, not just *what*.

## Coding standards

PSR-12 plus the rules in `.php-cs-fixer.dist.php`. Run `composer cs:fix` and the formatting takes
care of itself.

A few conventions that the tooling cannot check:

- Value objects are immutable. Prefer a `with*()` copy over a setter.
- Comments explain reasoning, not mechanics. If a line needs a comment to say what it does, the line
  is usually the thing to change.
- Exceptions carry the detail a caller needs to react — an error message alone is rarely enough.
- Never put a credential in an exception message, a log line or a debug dump.

## Reporting a bug

Please include the PHP version, the library version, a minimal snippet that reproduces the problem,
and what you expected instead. If the provider is involved, the status code and message it returned
help a lot — with the credentials removed.

Security issues go to [SECURITY.md](SECURITY.md), not to a public issue.
