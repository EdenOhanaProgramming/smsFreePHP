<?php

/**
 * Sending to a list that came from somewhere messy: a CSV export, a form,
 * a database column filled in by hand over several years.
 *
 * The default policy skips unparseable rows and sends to the rest, which is
 * what you want for a bulk list. The skipped rows come back in the result so
 * they can go into a log or a report.
 *
 *   SMS4FREE_USERNAME=... SMS4FREE_PASSWORD=... SMS4FREE_API_KEY=... \
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

$message = Message::of('החנות סגורה מחר, יום שני. נתראה ביום שלישי!');

printf(
    'About to send a %s message of %d part(s).%s',
    $message->encoding()->value,
    $message->parts(),
    PHP_EOL,
);

try {
    $result = $client->send('MyShop', $rows, $message);
} catch (SmsFreeException $e) {
    // Still throws when nothing in the list was usable, or when the provider
    // refuses the request outright.
    fwrite(STDERR, 'Send failed: ' . $e->getMessage() . PHP_EOL);

    exit(1);
}

printf(
    'Sent to %d recipient(s), %d accepted, roughly %d credit(s).%s',
    count($result->recipients()),
    $result->acceptedCount(),
    $result->estimatedCredits(),
    PHP_EOL,
);

// These are people who expected a message and did not get one, so this belongs
// in a log or a report rather than in the void.
if ($result->hasSkippedRecipients()) {
    fwrite(STDERR, sprintf(
        'Skipped %d unusable row(s): %s%s',
        count($result->skippedRecipients()),
        implode(', ', $result->skippedRecipients()),
        PHP_EOL,
    ));
}
