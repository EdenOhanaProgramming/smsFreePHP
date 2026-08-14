<?php

/**
 * Sending to a list that came from somewhere messy: a CSV export, a form,
 * a database column filled in by hand over several years.
 *
 * The point of this example is the invalid-recipient policy. By default one
 * unusable row rejects the whole request, which is right for a verification
 * code and wrong for a list of five hundred customers. Switch the policy and
 * the send goes to everyone it can, then tells you who it left out.
 *
 *   SMS4FREE_USERNAME=... SMS4FREE_PASSWORD=... SMS4FREE_API_KEY=... \
 *     php examples/bulk-send.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use EdenOhana\SmsFree\ClientOptions;
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\InvalidRecipientPolicy;
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
    (new ClientOptions())
        // Deliver to the rows that are usable instead of failing on the ones
        // that are not.
        ->withInvalidRecipientPolicy(InvalidRecipientPolicy::SkipInvalid)
        // But refuse to shorten the body rather than send half a link.
        ->withMessageTruncation(false),
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
