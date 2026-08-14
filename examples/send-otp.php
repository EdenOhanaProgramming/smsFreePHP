<?php

/**
 * A one-time passcode flow, end to end.
 *
 * The important part is what happens around the send: the code is generated
 * with a secure random source, stored hashed with an expiry, and compared in
 * constant time. Sending the SMS is the easy half.
 *
 *   SMS4FREE_USERNAME=… SMS4FREE_PASSWORD=… SMS4FREE_API_KEY=… \
 *     php examples/send-otp.php 054-123-4567
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Otp\OtpGenerator;
use EdenOhana\SmsFree\PhoneNumber;
use EdenOhana\SmsFree\Sms4FreeClient;

$recipient = PhoneNumber::parse($argv[1] ?? '054-123-4567');

$client = new Sms4FreeClient(Credentials::fromEnvironment());
$code = (new OtpGenerator(length: 6))->generate();

try {
    $client->send('MyShop', [$recipient], "הקוד שלך לאימות הוא: {$code}");
} catch (SmsFreeException $e) {
    fwrite(\STDERR, 'Could not deliver the code: ' . $e->getMessage() . \PHP_EOL);

    exit(1);
}

// Store the code the way you would store a password: hashed, with an expiry
// and an attempt counter. Never keep it in plain text, and never send it back
// to the browser.
$challenge = [
    'phone' => $recipient->e164(),
    'hash' => password_hash($code, \PASSWORD_DEFAULT),
    'expires_at' => time() + 300,
    'attempts_left' => 3,
];

echo 'Code sent. Type it to verify: ';
$typed = trim((string) fgets(\STDIN));

$valid = $challenge['attempts_left'] > 0
    && time() < $challenge['expires_at']
    && password_verify($typed, $challenge['hash']);

echo $valid ? 'Verified ✅' : 'Wrong or expired code ❌', \PHP_EOL;

// If you keep the code in plain text instead of a hash, compare it with
// OtpGenerator::matches(), which is constant time:
//   OtpGenerator::matches($storedCode, $typed);
