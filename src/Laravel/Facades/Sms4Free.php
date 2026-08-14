<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Laravel\Facades;

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Message;
use EdenOhana\SmsFree\PhoneNumber;
use EdenOhana\SmsFree\SendResult;
use EdenOhana\SmsFree\Sms4FreeClient;
use Illuminate\Support\Facades\Facade;

/**
 * Facade over the configured {@see Sms4FreeClient}.
 *
 * ```php
 * Sms4Free::send('MyShop', ['054-123-4567'], 'ההזמנה שלך יצאה לדרך');
 * ```
 *
 * @method static SendResult send(string $senderName, iterable|string|PhoneNumber $recipients, string|Message $message)
 * @method static list<string> findInvalidRecipients(iterable $recipients)
 * @method static ClientOptions options()
 *
 * @see Sms4FreeClient
 */
final class Sms4Free extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Sms4FreeClient::class;
    }
}
