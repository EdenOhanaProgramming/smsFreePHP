<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Laravel;

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Http\CurlHttpClient;
use EdenOhana\SmsFree\Http\HttpClient;
use EdenOhana\SmsFree\Sms4FreeClient;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the client, the configuration file and the notification channel.
 *
 * Laravel discovers this provider through the `extra.laravel` block in
 * composer.json, so an application only has to fill in its `.env`.
 */
final class Sms4FreeServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . '/../../config/sms4free.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'sms4free');

        $this->app->singleton(ClientOptions::class, static function (Container $app): ClientOptions {
            $config = self::config($app);

            return new ClientOptions(
                endpoint: self::string($config, 'endpoint', ClientOptions::DEFAULT_ENDPOINT),
                connectTimeout: self::float($config, 'connect_timeout', 5.0),
                timeout: self::float($config, 'timeout', 15.0),
                maxMessageLength: self::int($config, 'max_message_length', ClientOptions::DEFAULT_MAX_MESSAGE_LENGTH),
                truncateLongMessages: (bool) ($config['truncate_long_messages'] ?? true),
                allowInternational: (bool) ($config['allow_international'] ?? false),
                caBundlePath: self::nullableString($config, 'ca_bundle'),
            );
        });

        // Bound separately so an application can swap the transport for its
        // own, or for a fake in a feature test, without touching the client.
        $this->app->singleton(
            HttpClient::class,
            static fn (Container $app): HttpClient => new CurlHttpClient(self::resolve($app, ClientOptions::class)),
        );

        $this->app->singleton(Sms4FreeClient::class, static function (Container $app): Sms4FreeClient {
            $config = self::config($app);

            return new Sms4FreeClient(
                new Credentials(
                    self::string($config, 'username'),
                    self::string($config, 'password'),
                    self::string($config, 'api_key'),
                ),
                self::resolve($app, ClientOptions::class),
                self::resolve($app, HttpClient::class),
            );
        });

        $this->app->singleton(Sms4FreeChannel::class, static fn (Container $app): Sms4FreeChannel => new Sms4FreeChannel(
            self::resolve($app, Sms4FreeClient::class),
            self::nullableString(self::config($app), 'sender'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG_PATH => $this->app->configPath('sms4free.php')], 'sms4free-config');
        }

        // Deferred, so the notification manager is only touched once the
        // application actually resolves it.
        Notification::resolved(static function (ChannelManager $manager): void {
            $manager->extend(
                'sms4free',
                static fn (Container $app): Sms4FreeChannel => self::resolve($app, Sms4FreeChannel::class),
            );
        });
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [ClientOptions::class, HttpClient::class, Sms4FreeClient::class, Sms4FreeChannel::class];
    }

    /**
     * The container's `make()` is untyped, so every resolution goes through
     * here and comes back with the type the caller asked for.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function resolve(Container $app, string $class): object
    {
        /** @var T $instance */
        $instance = $app->make($class);

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(Container $app): array
    {
        /** @var array<string, mixed> $config */
        $config = self::resolve($app, Repository::class)->get('sms4free', []);

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function string(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? null;

        return \is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function float(array $config, string $key, float $default): float
    {
        $value = $config[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function int(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function nullableString(array $config, string $key): ?string
    {
        $value = self::string($config, $key);

        return $value === '' ? null : $value;
    }
}
