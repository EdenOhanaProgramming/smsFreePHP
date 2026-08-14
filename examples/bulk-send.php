<?php

/**
 * Sending to a list that came from somewhere messy — a CSV export, a form,
 * a database column filled in by hand over several years.
 *
 * The pattern: validate first, report the bad rows, then send to the rest.
 * Validation costs nothing; a rejected request costs a round trip, and in
 * some plans a credit.
 *
 *   SMS4FREE_USERNAME=… SMS4FREE_PASSWORD=… SMS4FREE_API_KEY=… \
 *     php examples/bulk-send.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Message;
use EdenOhana\SmsFree\Sms4FreeClient;

$rows = [
    '054-123-4567',
    '+972 52 111 1111',
    '0501234567',
    '03-1234567',      // a landline: not deliverable
    '',                // an empty cell
    '054-123-4567',    // a duplicate, collapsed automatically
];

$client = new Sms4FreeClient(
    Credentials::fromEnvironment(),
    // Refuse to shorten the body rather than send half a link.
    (new ClientOptions())->withMessageTruncation(false),
);

$invalid = $client->findInvalidRecipients($rows);

if ($invalid !== []) {
    fwrite(\STDERR, 'Skipping ' . \count($invalid) . ' unusable row(s): ' . implode(', ', $invalid) . \PHP_EOL);
}

$deliverable = array_values(array_diff($rows, $invalid));

if ($deliverable === []) {
    fwrite(\STDERR, 'Nothing left to send.' . \PHP_EOL);

    exit(1);
}

$message = Message::of('החנות סגורה מחר, יום שני. נתראה ביום שלישי!');

printf(
    "About to send a %s message of %d part(s) to %d recipient(s).%s",
    $message->encoding()->value,
    $message->parts(),
    \count($deliverable),
    \PHP_EOL,
);

try {
    $result = $client->send('MyShop', $deliverable, $message);

    printf(
        "Accepted: %d. Estimated credits: %d.%s",
        $result->acceptedCount(),
        $result->estimatedCredits(),
        \PHP_EOL,
    );
} catch (SmsFreeException $e) {
    fwrite(\STDERR, 'Send failed: ' . $e->getMessage() . \PHP_EOL);

    exit(1);
}
