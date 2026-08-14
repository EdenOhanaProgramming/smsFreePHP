<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Support;

use EdenOhana\SmsFree\Laravel\Sms4FreeMessage;
use EdenOhana\SmsFree\Message;

final class StubNotification
{
    public function __construct(private readonly string|Message|Sms4FreeMessage $payload)
    {
    }

    public function toSms4Free(mixed $notifiable): string|Message|Sms4FreeMessage
    {
        return $this->payload;
    }
}
