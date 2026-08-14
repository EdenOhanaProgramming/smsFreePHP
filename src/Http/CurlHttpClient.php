<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Http;

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Exception\TransportException;

/**
 * The default transport, built on ext-curl so the library has no runtime
 * dependencies beyond what a stock PHP installation already ships.
 *
 * Certificate verification is always on: the request carries account
 * credentials, and there is no acceptable reason to send those over a
 * connection nobody has authenticated. Hosts with an outdated or missing CA
 * store should point {@see ClientOptions::withCaBundlePath()} at a bundle
 * rather than turning verification off.
 */
final class CurlHttpClient implements HttpClient
{
    public function __construct(
        private readonly ClientOptions $options = new ClientOptions(),
    ) {
        if (!\extension_loaded('curl')) {
            throw new TransportException('The cURL extension is required to send messages.');
        }
    }

    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new TransportException('Unable to initialise a cURL handle.');
        }

        try {
            curl_setopt_array($handle, $this->curlOptions($body, $headers));

            $response = curl_exec($handle);
            $errorNumber = curl_errno($handle);

            if ($errorNumber !== 0 || !\is_string($response)) {
                throw new TransportException(\sprintf(
                    'The request to SMS4Free failed: %s (cURL error %d).',
                    curl_error($handle) !== '' ? curl_error($handle) : 'unknown error',
                    $errorNumber,
                ));
            }

            /** @var int $statusCode */
            $statusCode = curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);

            return new HttpResponse($statusCode, $response);
        } finally {
            curl_close($handle);
        }
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<int, mixed>
     */
    private function curlOptions(string $body, array $headers): array
    {
        $options = [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $body,
            \CURLOPT_HTTPHEADER => self::formatHeaders($headers),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_USERAGENT => $this->options->userAgent(),
            // Millisecond variants so sub-second timeouts are honoured.
            \CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->options->connectTimeout() * 1000),
            \CURLOPT_TIMEOUT_MS => (int) round($this->options->timeout() * 1000),
        ];

        if ($this->options->caBundlePath() !== null) {
            $options[\CURLOPT_CAINFO] = $this->options->caBundlePath();
        }

        return $options;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private static function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }
}
