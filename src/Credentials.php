<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;

/**
 * The SMS4Free account details used to authenticate every request.
 *
 * The object is immutable and redacts the password and API key from
 * `var_dump()` output so credentials do not end up in a debug dump or a
 * stack trace that gets written to a log file.
 */
final class Credentials
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $apiKey,
    ) {
        foreach (['username' => $username, 'password' => $password, 'apiKey' => $apiKey] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(\sprintf('Credential "%s" must not be empty.', $name));
            }
        }
    }

    /**
     * Builds credentials from environment variables, which is the recommended
     * way to keep secrets out of the source tree.
     *
     * @param string $prefix variable name prefix, e.g. `SMS4FREE_` reads
     *                       `SMS4FREE_USERNAME`, `SMS4FREE_PASSWORD` and `SMS4FREE_API_KEY`
     *
     * @throws InvalidArgumentException if any of the three variables is missing or empty
     */
    public static function fromEnvironment(string $prefix = 'SMS4FREE_'): self
    {
        return new self(
            self::readEnv($prefix . 'USERNAME'),
            self::readEnv($prefix . 'PASSWORD'),
            self::readEnv($prefix . 'API_KEY'),
        );
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return array{username: string, password: string, apiKey: string}
     */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
            'password' => '***redacted***',
            'apiKey' => '***redacted***',
        ];
    }

    private static function readEnv(string $name): string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            throw new InvalidArgumentException(\sprintf('Environment variable "%s" is not set.', $name));
        }

        return $value;
    }
}
