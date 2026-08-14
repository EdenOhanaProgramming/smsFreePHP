<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Exception;

/**
 * Thrown when the request never produced a usable answer from the provider:
 * DNS failure, TLS failure, timeout, unexpected HTTP status or a body that is
 * not valid JSON.
 *
 * A transport failure says nothing about whether the message was delivered —
 * the request may have reached the provider and the response may have been
 * lost on the way back. Treat retries accordingly.
 */
final class TransportException extends \RuntimeException implements SmsFreeException
{
}
