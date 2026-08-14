<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Exception;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by this library.
 *
 * Catching this single interface is enough to isolate the library from the
 * rest of the application:
 *
 * ```php
 * try {
 *     $client->send($sender, $recipients, $text);
 * } catch (SmsFreeException $e) {
 *     // Any failure originating from smsFreePHP.
 * }
 * ```
 */
interface SmsFreeException extends Throwable
{
}
