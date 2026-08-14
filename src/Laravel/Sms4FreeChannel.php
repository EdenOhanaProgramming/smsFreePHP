<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Laravel;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\Message;
use EdenOhana\SmsFree\PhoneNumber;
use EdenOhana\SmsFree\SendResult;
use EdenOhana\SmsFree\Sms4FreeClient;

/**
 * Laravel notification channel.
 *
 * ```php
 * public function via(object $notifiable): array
 * {
 *     return ['sms4free'];
 * }
 *
 * public function toSms4Free(object $notifiable): string
 * {
 *     return "הקוד שלך לאימות הוא: {$this->code}";
 * }
 * ```
 *
 * The recipient is looked for in three places, in order: a
 * `routeNotificationForSms4free()` method on the notifiable, an Eloquent
 * `phone_number` attribute, or a public `phone_number` property. A notifiable
 * with none of them is skipped rather than throwing, the way Laravel's own
 * channels behave: one user without a phone number should not fail a whole
 * notification run.
 *
 * Both parameters are typed loosely on purpose. Laravel resolves channels
 * dynamically and never checks the signature, and keeping the framework's
 * classes out of it means this channel can be unit tested without booting an
 * application.
 */
final class Sms4FreeChannel
{
    public function __construct(
        private readonly Sms4FreeClient $client,
        private readonly ?string $defaultSender = null,
    ) {
    }

    /**
     * @return SendResult|null null when the notifiable has no phone number
     */
    public function send(mixed $notifiable, object $notification): ?SendResult
    {
        if (!method_exists($notification, 'toSms4Free')) {
            throw new InvalidArgumentException(\sprintf(
                'To use the sms4free channel, %s must define a toSms4Free() method.',
                $notification::class,
            ));
        }

        $recipient = self::recipientFor($notifiable, $notification);

        if ($recipient === null) {
            return null;
        }

        $payload = self::call($notification, 'toSms4Free', $notifiable);

        $message = match (true) {
            $payload instanceof Sms4FreeMessage => $payload,
            $payload instanceof Message, \is_string($payload) => Sms4FreeMessage::create($payload),
            default => throw new InvalidArgumentException(\sprintf(
                '%s::toSms4Free() must return a string, a %s or a %s.',
                $notification::class,
                Message::class,
                Sms4FreeMessage::class,
            )),
        };

        $sender = $message->sender() ?? $this->defaultSender;

        if ($sender === null || trim($sender) === '') {
            throw new InvalidArgumentException(
                'No sender configured. Set SMS4FREE_SENDER in the environment, or name one on the '
                . 'message with Sms4FreeMessage::create($text)->from($sender).',
            );
        }

        return $this->client->send($sender, $recipient, $message->content());
    }

    private static function recipientFor(mixed $notifiable, object $notification): string|PhoneNumber|null
    {
        $route = self::call($notifiable, 'routeNotificationFor', 'sms4free', $notification)
            ?? self::call($notifiable, 'getAttribute', 'phone_number')
            ?? (\is_object($notifiable) ? get_object_vars($notifiable)['phone_number'] ?? null : null);

        if ($route instanceof PhoneNumber) {
            return $route;
        }

        return \is_string($route) && trim($route) !== '' ? $route : null;
    }

    /**
     * Calls a method if the target actually has one, so the channel can work
     * with an Eloquent model, a plain object, or anything in between without
     * demanding a particular base class.
     */
    private static function call(mixed $target, string $method, mixed ...$arguments): mixed
    {
        if (!\is_object($target) || !method_exists($target, $method)) {
            return null;
        }

        /** @var callable $callable */
        $callable = [$target, $method];

        return $callable(...$arguments);
    }
}
