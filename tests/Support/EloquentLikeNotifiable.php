<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Support;

/**
 * Mimics an Eloquent model, whose attributes are reached through getAttribute().
 */
final class EloquentLikeNotifiable
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private readonly array $attributes)
    {
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
}
