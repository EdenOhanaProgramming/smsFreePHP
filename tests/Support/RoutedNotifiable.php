<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Support;

/**
 * A notifiable that answers the routing question itself, the way a model
 * using Laravel's Notifiable trait does.
 *
 * The channel never type-hints a framework class, so a plain object like this
 * is enough to exercise it without installing or booting Laravel.
 */
final class RoutedNotifiable
{
    public function __construct(private readonly ?string $phone)
    {
    }

    public function routeNotificationFor(string $driver, ?object $notification = null): ?string
    {
        return $driver === 'sms4free' ? $this->phone : null;
    }
}
