<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Exception;

/**
 * Thrown when the caller supplies input the library refuses to send,
 * such as an empty message body or an empty recipient list.
 */
class InvalidArgumentException extends \InvalidArgumentException implements SmsFreeException
{
}
