<?php

/**
 * Backwards-compatibility layer for smsFreePHP 1.x.
 *
 * Version 2 moved to a namespaced, exception-based API
 * ({@see \EdenOhana\SmsFree\Sms4FreeClient}). This file keeps the old
 * `SMSService` class working, with the same method names and the same return
 * values, so an existing project can upgrade the library without changing a
 * line of code and migrate at its own pace.
 *
 * @deprecated since 2.0, use \EdenOhana\SmsFree\Sms4FreeClient instead.
 *             See UPGRADING.md for the (short) migration guide.
 */

declare(strict_types=1);

use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\ApiException;
use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Otp\OtpGenerator;
use EdenOhana\SmsFree\PhoneNumber;
use EdenOhana\SmsFree\Sms4FreeClient;

// Works whether the project uses Composer or simply requires this file
// directly: only when no autoloader can find the client do we register ours.
if (!class_exists(Sms4FreeClient::class)) {
    $composerAutoloader = __DIR__ . '/vendor/autoload.php';

    require_once is_file($composerAutoloader) ? $composerAutoloader : __DIR__ . '/src/autoload.php';
}

/**
 * @deprecated since 2.0, use \EdenOhana\SmsFree\Sms4FreeClient instead.
 */
class SMSService
{
    private ?Credentials $credentials = null;

    private static bool $deprecationNoticeEmitted = false;

    public function __construct()
    {
        if (!self::$deprecationNoticeEmitted) {
            self::$deprecationNoticeEmitted = true;

            @trigger_error(
                'SMSService is deprecated since smsFreePHP 2.0 and will be removed in 3.0. '
                . 'Use EdenOhana\SmsFree\Sms4FreeClient instead, see UPGRADING.md.',
                \E_USER_DEPRECATED,
            );
        }
    }

    /**
     * Stores the account details used by {@see self::sendSMS()}.
     */
    public function smsAuth(string $username, string $password, string $api_token): void
    {
        $this->credentials = new Credentials($username, $password, $api_token);
    }

    /**
     * Sends a message and returns `true` on success or a Hebrew error string
     * on failure, exactly as version 1 did.
     *
     * @param array<int, string> $recipientsList
     *
     * @return true|string
     */
    public function sendSMS(string $senderName, array $recipientsList, string $message)
    {
        if ($this->credentials === null) {
            return 'לא הוזנו פרטי התחברות. יש לקרוא ל-smsAuth לפני שליחת הודעה.';
        }

        if ($senderName === '' || $message === '') {
            return 'שולח או הודעה ריקים.';
        }

        if ($recipientsList === []) {
            return 'לא הוזנו נמענים להודעה.';
        }

        try {
            (new Sms4FreeClient($this->credentials))->send($senderName, $recipientsList, $message);

            return true;
        } catch (InvalidPhoneNumberException $e) {
            return 'המספרים הבאים אינם תקינים: ' . implode(', ', $e->invalidNumbers()) . '.';
        } catch (ApiException $e) {
            return 'שגיאת שירות בעת שליחת ה-SMS: ' . $e->providerMessage();
        } catch (SmsFreeException $e) {
            return 'שגיאה בעת שליחת ה-SMS: ' . $e->getMessage();
        } catch (\Throwable $e) {
            return 'שגיאת שרת פנימית בעת שליחת ה-SMS: ' . $e->getMessage();
        }
    }

    /**
     * @return int a six digit code
     *
     * @see OtpGenerator for a version that returns a string and therefore
     *      keeps leading zeros
     */
    public function generateRandomOTP(): int
    {
        // Deliberately not delegating to OtpGenerator: version 1 promised an
        // integer, and casting "042317" to int would hand back a five digit
        // code. The range below is the one 1.x used.
        return random_int(100000, 999999);
    }

    /**
     * @param array<int, string> $phoneNumbers
     *
     * @return array<int, string> the entries that are not valid Israeli mobile numbers
     */
    public function getInvalidPhoneNumbers(array $phoneNumbers): array
    {
        $invalid = [];

        foreach ($phoneNumbers as $phoneNumber) {
            if (PhoneNumber::tryParse($phoneNumber) === null) {
                $invalid[] = $phoneNumber;
            }
        }

        return $invalid;
    }
}
