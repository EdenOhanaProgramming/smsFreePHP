<?php

declare(strict_types=1);

use EdenOhana\SmsFree\ClientOptions;

return [
    /*
     * Account details. Keep the actual values in .env, never in this file.
     */
    'username' => env('SMS4FREE_USERNAME'),
    'password' => env('SMS4FREE_PASSWORD'),
    'api_key' => env('SMS4FREE_API_KEY'),

    /*
     * The sender shown on the recipient's phone: a verified sender number, or
     * a sender ID approved by the provider. Used by the notification channel
     * whenever a notification does not name one itself.
     */
    'sender' => env('SMS4FREE_SENDER'),

    'endpoint' => env('SMS4FREE_ENDPOINT', ClientOptions::DEFAULT_ENDPOINT),

    /*
     * Seconds. The defaults fail fast rather than leaving a request hanging on
     * a provider that has stopped answering.
     */
    'connect_timeout' => (float) env('SMS4FREE_CONNECT_TIMEOUT', 5.0),
    'timeout' => (float) env('SMS4FREE_TIMEOUT', 15.0),

    /*
     * The provider accepts 134 characters per request. Set truncate to false
     * to raise an exception instead of quietly shortening a longer body.
     */
    'max_message_length' => (int) env('SMS4FREE_MAX_MESSAGE_LENGTH', ClientOptions::DEFAULT_MAX_MESSAGE_LENGTH),
    'truncate_long_messages' => (bool) env('SMS4FREE_TRUNCATE', true),

    /*
     * Accept recipients outside Israel. Off by default, so a typo in a local
     * number is caught rather than sent abroad.
     */
    'allow_international' => (bool) env('SMS4FREE_ALLOW_INTERNATIONAL', false),

    /*
     * Path to a CA bundle, for hosts whose PHP has no usable certificate
     * store. Leave null to use PHP's own configuration.
     */
    'ca_bundle' => env('SMS4FREE_CA_BUNDLE'),
];
