<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Support;

/**
 * A notifiable that only exposes a `phone_number` property, the way a plain
 * DTO would.
 */
final class AttributeNotifiable
{
    public function __construct(public ?string $phone_number)
    {
    }
}
