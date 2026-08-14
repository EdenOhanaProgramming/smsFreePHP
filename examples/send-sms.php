<?php

/**
 * Sending a single message.
 *
 * Run it with the account details in the environment, so no secret ever ends
 * up in the source tree:
 *
 *   SMS4FREE_USERNAME=... SMS4FREE_PASSWORD=... SMS4FREE_API_KEY=... \
 *     php examples/send-sms.php 054-123-4567
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\ApiException;
use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Exception\TransportException;
use EdenOhana\SmsFree\Sms4FreeClient;

$recipient = $argv[1] ?? '054-123-4567';

$client = new Sms4FreeClient(Credentials::fromEnvironment());

try {
    $result = $client->send(
        senderName: 'MyShop',
        recipients: [$recipient],
        message: 'ההזמנה שלך יצאה לדרך 📦',
    );

    printf(
        'Sent to %s: %d message(s) accepted, %d part(s) each.%s',
        implode(', ', $result->recipientNumbers()),
        $result->acceptedCount(),
        $result->message()->parts(),
        \PHP_EOL,
    );

    if ($result->wasTruncated()) {
        fwrite(\STDERR, 'Heads up: the body was longer than the provider allows and was shortened.' . \PHP_EOL);
    }
} catch (InvalidPhoneNumberException $e) {
    // Bad input from the user. Worth showing them, and no credit was spent.
    fwrite(\STDERR, 'Invalid number(s): ' . implode(', ', $e->invalidNumbers()) . \PHP_EOL);
} catch (ApiException $e) {
    // The provider said no: wrong credentials, no balance, unverified sender.
    fwrite(\STDERR, sprintf('Provider refused (status %d): %s%s', $e->status(), $e->providerMessage(), \PHP_EOL));
} catch (TransportException $e) {
    // The network, not the message. Safe to surface as "try again later".
    fwrite(\STDERR, 'Could not reach SMS4Free: ' . $e->getMessage() . \PHP_EOL);
} catch (SmsFreeException $e) {
    fwrite(\STDERR, 'Send failed: ' . $e->getMessage() . \PHP_EOL);
}
