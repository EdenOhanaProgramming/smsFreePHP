# smsFreePHP

[![CI](https://github.com/EdenOhanaProgramming/smsFreePHP/actions/workflows/ci.yml/badge.svg)](https://github.com/EdenOhanaProgramming/smsFreePHP/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4)](https://www.php.net/supported-versions)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

ספריית PHP מודרנית ומוקפדת לעבודה מול ה-API של [SMS4Free](https://www.sms4free.co.il/): שליחת הודעות
SMS, טיפול במספרי טלפון ישראליים ויצירת קודי אימות חד-פעמיים.

**🇬🇧 [README in English](README.md)** | [תיעוד ה-API](docs/api-reference.he.md) | [Laravel](docs/laravel.he.md) | [שדרוג מגרסה 1.x](UPGRADING.md)

---

## מה הספרייה פותרת

הקריאה ל-API של SMS4Free היא בסך הכול קריאת `curl` אחת. כל העבודה האמיתית היא מה שמסביב.

מספרי טלפון מגיעים מלוכלכים. `054-123-4567`, `+972 54 123 4567` ו-`00972541234567` הם אותו קו בדיוק,
ולכן כולם עוברים נרמול לצורה קנונית אחת, וכל מה שאינו מספר סלולרי ישראלי תקין נפסל לפני שנשלחת בקשה.

עברית שוברת טיפול נאיבי במחרוזות. חיתוך הודעה עם `substr()` חותך תו דו-בייטי באמצע ומייצר ג׳יבריש,
ו-`strlen()` סופר בייטים במקום תווים. כאן הכול בטוח למולטי-בייט.

צריך להבדיל בין סוגי כשלונות. "המספר לא תקין", "הספק אומר שנגמרה היתרה" ו"הרשת נפלה" מחייבים שלוש
תגובות שונות באפליקציה, ולכן לכל אחד מהם יש טיפוס Exception משלו.

ופרטי ההתחברות הם סוד: הם לא מגיעים להודעות שגיאה, והם מוסתרים גם מפלט של `var_dump()`.

## דרישות מערכת

| | |
|---|---|
| PHP | 8.1 ומעלה |
| הרחבות | `curl`,‏ `json`,‏ `mbstring` |
| חשבון | חשבון ב-[SMS4Free](https://www.sms4free.co.il/): שם משתמש, סיסמה ומפתח API |

## התקנה

```bash
composer require edenohana/sms-free-php
```

כל עוד החבילה לא רשומה ב-Packagist, אפשר להפנות את Composer ישירות לריפו:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/EdenOhanaProgramming/smsFreePHP" }
    ],
    "require": {
        "edenohana/sms-free-php": "^2.0"
    }
}
```

לא עובדים עם Composer? מעתיקים את התיקייה לפרויקט וטוענים את ה-autoloader המצורף:

```php
require_once __DIR__ . '/smsFreePHP/src/autoload.php';
```

## התחלה מהירה

```php
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Sms4FreeClient;

$client = new Sms4FreeClient(new Credentials('username', 'password', 'api-key'));

$result = $client->send(
    senderName: 'MyShop',            // מספר שולח מאומת, או שם שולח מאושר
    recipients: ['054-123-4567'],    // מספר בודד או רשימה
    message:    'ההזמנה שלך יצאה לדרך',
);

echo $result->acceptedCount(); // 1
```

מומלץ להחזיק את הסודות מחוץ לקוד ולקרוא אותם ממשתני סביבה:

```php
$client = new Sms4FreeClient(Credentials::fromEnvironment());
// קורא את SMS4FREE_USERNAME,‏ SMS4FREE_PASSWORD ו-SMS4FREE_API_KEY
```

## טיפול בשגיאות

הפונקציה `send()` מחזירה אובייקט [`SendResult`](src/SendResult.php) או זורקת חריגה. כל החריגות של
הספרייה מממשות את `SmsFreeException`, כך ש-`catch` אחד מספיק כשלא מעניין ההבדל ביניהן:

```php
use EdenOhana\SmsFree\Exception\ApiException;
use EdenOhana\SmsFree\Exception\InvalidPhoneNumberException;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Exception\TransportException;

try {
    $client->send('MyShop', $recipients, $text);
} catch (InvalidPhoneNumberException $e) {
    // קלט שגוי של המשתמש. שום דבר לא נשלח ושום דבר לא חויב.
    $form->addError('phone', implode(', ', $e->invalidNumbers()));
} catch (ApiException $e) {
    // הספק סירב: פרטי התחברות שגויים, אין יתרה, שולח לא מאומת.
    $logger->error('SMS4Free refused', ['status' => $e->status(), 'reason' => $e->providerMessage()]);
} catch (TransportException $e) {
    // תקלת רשת. אי אפשר לדעת אם ההודעה יצאה, ראו את ההערה על ניסיונות חוזרים בהמשך.
    $logger->warning('SMS4Free unreachable', ['error' => $e->getMessage()]);
} catch (SmsFreeException $e) {
    // כל שאר התקלות של הספרייה.
}
```

| חריגה | משמעות | האם נשרף קרדיט? |
|---|---|---|
| `InvalidArgumentException` | שולח ריק, רשימת נמענים ריקה, הודעה ריקה או ארוכה מהמותר | לא, הבקשה לא נשלחת בכלל |
| `InvalidPhoneNumberException` | אחד הנמענים או יותר לא ניתן לפענוח | לא |
| `TransportException` | timeout, תקלת DNS או TLS, סטטוס שאינו 2xx, גוף תשובה לא קריא | לא ידוע |
| `ApiException` | הספק החזיר סטטוס שאינו חיובי | תלוי בספק |

## בדיקת מספרים לפני שליחה

בדיקת מספרים לא עולה כלום, ולכן כדאי לאמת את הטופס קודם ורק אז לשרוף קרדיט:

```php
$invalid = $client->findInvalidRecipients($rowsFromCsv);

if ($invalid !== []) {
    throw new RuntimeException('מספרים לא תקינים: ' . implode(', ', $invalid));
}
```

או לעבוד ישירות מול אובייקט הערך:

```php
use EdenOhana\SmsFree\PhoneNumber;

$number = PhoneNumber::parse('054-123-4567');

$number->national(); // '0541234567', מה שנשלח לספק
$number->e164();     // '+972541234567', מה שכדאי לשמור בבסיס הנתונים
$number->raw();      // '054-123-4567', מה שהמשתמש הקליד
```

## אורך הודעה, עברית וקרדיטים

הודעה בעברית נשלחת בקידוד UCS-2, ולכן נכנסים בה **70 תווים לכל חלק** במקום 160 בהודעה לטינית. זו
ההפתעה הנפוצה ביותר בחשבון החודשי מול הספק הזה, ולכן הספרייה חושפת את זה בגלוי:

```php
use EdenOhana\SmsFree\Message;

$message = Message::of('הקוד שלך לאימות הוא 123456');

$message->encoding();  // SmsEncoding::Ucs2
$message->length();    // 26 תווים
$message->parts();     // 1, כמה הודעות בפועל יחויבו בחשבון
```

השירות מקבל עד 134 תווים בבקשה אחת. כברירת מחדל הודעה ארוכה יותר נחתכת על גבול תו שלם, והתוצאה
מדווחת על כך:

```php
$result = $client->send('MyShop', $recipients, $veryLongText);

if ($result->wasTruncated()) {
    $logger->notice('ההודעה קוצרה לפני השליחה.');
}
```

אם אסור לאבד את סוף ההודעה (למשל כשיש שם קישור), אפשר להפוך את החיתוך לשגיאה:

```php
use EdenOhana\SmsFree\ClientOptions;

$client = new Sms4FreeClient(
    Credentials::fromEnvironment(),
    (new ClientOptions())->withMessageTruncation(false),
);
```

## קודי אימות חד-פעמיים (OTP)

```php
use EdenOhana\SmsFree\Otp\OtpGenerator;

$code = (new OtpGenerator(length: 6))->generate(); // '042317', מחרוזת, כך שאפס מוביל נשמר

$client->send('MyShop', [$phone], "הקוד שלך לאימות הוא: {$code}");

// אחר כך, כשהמשתמש מקליד אותו בחזרה:
OtpGenerator::matches($storedCode, $typedCode); // השוואה בזמן קבוע
```

הקודים נוצרים באמצעות `random_int()`, המחולל המאובטח קריפטוגרפית של PHP. כדאי לשמור את הקוד מוצפן
(hash) עם תפוגה והגבלת ניסיונות. [`examples/send-otp.php`](examples/send-otp.php) מדגים את התהליך המלא.

## הגדרות

```php
use EdenOhana\SmsFree\ClientOptions;

$options = (new ClientOptions())
    ->withTimeouts(connectTimeout: 3.0, timeout: 10.0)
    ->withMessageTruncation(false)
    ->withInternationalRecipients(true)  // אישור למספרים שאינם ישראליים
    ->withMaxMessageLength(70)
    ->withUserAgent('my-app/2.1')
    ->withCaBundlePath('/etc/ssl/certs/cacert.pem'); // לשרתים בלי מאגר תעודות

$client = new Sms4FreeClient(Credentials::fromEnvironment(), $options);
```

ברירות המחדל: 5 שניות ל-timeout של החיבור, 15 שניות לבקשה כולה, חיתוך הודעות פעיל, נמענים ישראליים
בלבד, ואימות TLS תמיד דלוק.

### הערה על ניסיונות חוזרים

הספרייה לא מבצעת retry אוטומטי. timeout לא מלמד אם הבקשה הגיעה לספק, כי ייתכן שרק התשובה אבדה בדרך
חזרה, ולכן ניסיון חוזר אוטומטי עלול לשלוח את אותה הודעה פעמיים ולחייב פעמיים. מי שרוצה retry כדאי
שיוסיף אותו במקום שבו יש מספיק הקשר כדי לעשות את זה נכון, למשל תור עם מפתח ייחודי לכל הודעה.

## שימוש בשכבת HTTP אחרת

שכבת התקשורת יושבת מאחורי ממשק [`HttpClient`](src/Http/HttpClient.php). אפשר לממש אותו כדי לנתב את
הבקשות דרך Guzzle,‏ Symfony HttpClient, לקוח PSR-18, או מימוש מזויף בטסטים שלכם:

```php
final class GuzzleTransport implements HttpClient
{
    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        // ...
    }
}

$client = new Sms4FreeClient($credentials, new ClientOptions(), new GuzzleTransport());
```

## Laravel

החבילה כוללת service provider,‏ facade ו-notification channel, שנטענים אוטומטית ב-Laravel 9 עד 12.
ממלאים את `.env` ואפשר לשלוח:

```php
// Notification
public function via(object $notifiable): array
{
    return ['sms4free'];
}

public function toSms4Free(object $notifiable): string
{
    return "הקוד שלך לאימות הוא: {$this->code}";
}
```

[docs/laravel.he.md](docs/laravel.he.md) מכסה את קובץ ההגדרות, איפה הערוץ מחפש מספר טלפון, התראות
בתור, ובדיקות.

## שדרוג מגרסה 1.x

המחלקה הישנה `SMSService` עדיין נשלחת עם הספרייה ועדיין מתנהגת בדיוק כמו קודם, כך שעדכון החבילה לא
שובר כלום עד שתחליטו לעבור. היא מסומנת כ-deprecated ותוסר בגרסה 3.0. [UPGRADING.md](UPGRADING.md)
היא קריאה קצרה.

## פיתוח

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan ברמה 9
composer cs        # תקני קוד (composer cs:fix כדי לתקן)
composer check     # שלושתם יחד
```

## תרומה לפרויקט

דיווחי באגים ו-Pull Requests יתקבלו בברכה, ראו [CONTRIBUTING.md](CONTRIBUTING.md). מצאתם בעיית
אבטחה? עקבו אחרי [SECURITY.md](SECURITY.md) במקום לפתוח issue פומבי.

## רישיון

[MIT](LICENSE) © עדן אוחנה

הפרויקט אינו מסונף ל-SMS4Free.
