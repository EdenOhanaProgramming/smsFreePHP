<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree;

use EdenOhana\SmsFree\Exception\ApiException;
use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\Exception\TransportException;
use EdenOhana\SmsFree\Http\CurlHttpClient;
use EdenOhana\SmsFree\Http\HttpClient;
use EdenOhana\SmsFree\Http\HttpResponse;

/**
 * Client for the SMS4Free HTTP API.
 *
 * ```php
 * $client = new Sms4FreeClient(Credentials::fromEnvironment());
 *
 * try {
 *     $result = $client->send('MyShop', ['054-123-4567'], 'Your order has shipped');
 *     echo "Accepted: {$result->acceptedCount()}";
 * } catch (SmsFreeException $e) {
 *     // One failure type to catch, whatever went wrong.
 * }
 * ```
 *
 * The client is immutable and safe to keep as a long-lived service in a
 * container.
 */
final class Sms4FreeClient
{
    public const VERSION = '2.0.0';

    private readonly HttpClient $httpClient;

    /**
     * @param HttpClient|null $httpClient a custom transport; the cURL-based default is used when omitted
     */
    public function __construct(
        private readonly Credentials $credentials,
        private readonly ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient($options);
    }

    /**
     * Convenience constructor for the common case of three credential strings.
     */
    public static function create(string $username, string $password, string $apiKey): self
    {
        return new self(new Credentials($username, $password, $apiKey));
    }

    /**
     * Sends one message to one or more recipients.
     *
     * Duplicate recipients are collapsed, so the same person is never billed
     * for twice in a single call.
     *
     * @param string                        $senderName a verified sender number, or an approved alphanumeric sender ID
     * @param iterable<string|PhoneNumber>|string|PhoneNumber $recipients one recipient or a list of them
     * @param string|Message                $message    the body to deliver
     *
     * @throws InvalidArgumentException    if the sender, recipient list or body is unusable
     * @throws InvalidPhoneNumberException if any recipient number cannot be parsed
     * @throws TransportException          if the provider could not be reached or answered unintelligibly
     * @throws ApiException                if the provider rejected the request
     */
    public function send(string $senderName, iterable|string|PhoneNumber $recipients, string|Message $message): SendResult
    {
        $senderName = trim($senderName);

        if ($senderName === '') {
            throw new InvalidArgumentException('The sender name must not be empty.');
        }

        $parsedRecipients = $this->parseRecipients($recipients);
        $body = $this->prepareBody($message);

        $response = $this->httpClient->post(
            $this->options->endpoint(),
            $this->encodePayload($senderName, $parsedRecipients, $body),
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        );

        return $this->interpret($response, $parsedRecipients, $body);
    }

    /**
     * Checks whether every recipient can be delivered to, without sending
     * anything. Useful for validating a form before a credit is spent.
     *
     * @param iterable<string|PhoneNumber> $recipients
     *
     * @return list<string> the raw values that failed to parse; empty when all are valid
     */
    public function findInvalidRecipients(iterable $recipients): array
    {
        try {
            PhoneNumber::parseList($recipients, $this->options->allowsInternational());
        } catch (InvalidPhoneNumberException $e) {
            return $e->invalidNumbers();
        }

        return [];
    }

    public function options(): ClientOptions
    {
        return $this->options;
    }

    /**
     * @param iterable<string|PhoneNumber>|string|PhoneNumber $recipients
     *
     * @return list<PhoneNumber>
     */
    private function parseRecipients(iterable|string|PhoneNumber $recipients): array
    {
        if (\is_string($recipients) || $recipients instanceof PhoneNumber) {
            $recipients = [$recipients];
        }

        $parsed = PhoneNumber::parseList($recipients, $this->options->allowsInternational());

        if ($parsed === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        return self::deduplicate($parsed);
    }

    private function prepareBody(string|Message $message): Message
    {
        $message = $message instanceof Message ? $message : Message::of($message);
        $limit = $this->options->maxMessageLength();

        if ($message->length() <= $limit) {
            return $message;
        }

        if (!$this->options->truncatesLongMessages()) {
            throw new InvalidArgumentException(\sprintf(
                'The message is %d characters long, which exceeds the %d character limit. '
                . 'Shorten it, or enable truncation in the client options.',
                $message->length(),
                $limit,
            ));
        }

        return $message->truncateTo($limit);
    }

    /**
     * @param list<PhoneNumber> $recipients
     */
    private function encodePayload(string $senderName, array $recipients, Message $message): string
    {
        $payload = [
            'key' => $this->credentials->apiKey(),
            'user' => $this->credentials->username(),
            'pass' => $this->credentials->password(),
            'sender' => $senderName,
            'recipient' => implode(',', array_map(
                static fn (PhoneNumber $number): string => $number->national(),
                $recipients,
            )),
            'msg' => $message->text(),
        ];

        try {
            // Hebrew must travel as-is: escaping it to \uXXXX would inflate the
            // payload and is not what the provider expects.
            return json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('The request could not be encoded as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Turns the provider's answer into a result, or into the most specific
     * exception we can justify.
     *
     * @param list<PhoneNumber> $recipients
     */
    private function interpret(HttpResponse $response, array $recipients, Message $message): SendResult
    {
        if (!$response->isSuccessful()) {
            throw new TransportException(\sprintf(
                'SMS4Free answered with HTTP %d: %s',
                $response->statusCode(),
                self::snippet($response->body()),
            ));
        }

        [$status, $providerMessage] = self::parseBody($response->body());

        // A positive status is the number of messages accepted; anything else
        // is a failure, and the provider's own code is the useful part.
        if ($status <= 0) {
            throw new ApiException($status, $providerMessage);
        }

        return new SendResult($status, $recipients, $message, $providerMessage);
    }

    /**
     * The endpoint normally answers with `{"status": n, "message": "…"}`, but
     * has been known to reply with a bare number. Accept both.
     *
     * @return array{0: int, 1: string}
     */
    private static function parseBody(string $body): array
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            throw new TransportException('SMS4Free returned an empty response body.');
        }

        if (preg_match('/^-?\d+$/', $trimmed) === 1) {
            return [(int) $trimmed, ''];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransportException(
                'SMS4Free returned a response that is not valid JSON: ' . self::snippet($trimmed),
                0,
                $e,
            );
        }

        $status = \is_array($decoded) ? $decoded['status'] ?? null : null;

        if (!\is_scalar($status) || !is_numeric($status)) {
            throw new TransportException(
                'SMS4Free returned an unexpected response shape: ' . self::snippet($trimmed),
            );
        }

        /** @var array<mixed> $decoded */
        $providerMessage = $decoded['message'] ?? '';

        return [(int) $status, \is_scalar($providerMessage) ? (string) $providerMessage : ''];
    }

    /**
     * @param list<PhoneNumber> $recipients
     *
     * @return list<PhoneNumber>
     */
    private static function deduplicate(array $recipients): array
    {
        $seen = [];
        $unique = [];

        foreach ($recipients as $recipient) {
            if (isset($seen[$recipient->national()])) {
                continue;
            }

            $seen[$recipient->national()] = true;
            $unique[] = $recipient;
        }

        return $unique;
    }

    /**
     * Keeps a foreign response short enough to be safe in an exception message
     * and a log line.
     */
    private static function snippet(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);

        if ($body === '') {
            return '(empty body)';
        }

        return mb_strlen($body) > 200 ? mb_substr($body, 0, 200) . '…' : $body;
    }
}
