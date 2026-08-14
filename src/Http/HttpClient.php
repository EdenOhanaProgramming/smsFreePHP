<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Http;

use EdenOhana\SmsFree\Exception\TransportException;

/**
 * The seam between this library and the network.
 *
 * Keeping the transport behind an interface is what makes the client testable
 * without hitting the provider, and lets an application plug in its own HTTP
 * stack (Guzzle, Symfony HttpClient, a PSR-18 adapter) if it already has one.
 */
interface HttpClient
{
    /**
     * Performs a POST request and returns the response.
     *
     * @param string                $url     absolute URL to post to
     * @param string                $body    raw request body
     * @param array<string, string> $headers header name => value
     *
     * @throws TransportException if the request cannot be completed
     */
    public function post(string $url, string $body, array $headers = []): HttpResponse;
}
