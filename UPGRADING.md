# Upgrading from 1.x to 2.0

[English](#english) | [עברית](#hebrew)

<a name="english"></a>

## English

### The short version

**Nothing breaks.** The `SMSService` class still ships, still has the same methods, and still returns
`true` or a Hebrew error string. Update the package and your existing code keeps working, with
one deprecation notice per process.

When you are ready, the new API is a small rewrite:

```php
// Before, in 1.x
require_once 'SMSService.php';

$sms = new SMSService();
$sms->smsAuth($user, $pass, $key);
$result = $sms->sendSMS($sender, [$phone], $text);

if ($result === true) {
    // sent
} else {
    echo $result; // an error string
}
```

```php
// After, in 2.x
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Sms4FreeClient;

$client = new Sms4FreeClient(new Credentials($user, $pass, $key));

try {
    $result = $client->send($sender, [$phone], $text);
    // sent, and $result knows how many, in how many parts, and whether it was truncated
} catch (SmsFreeException $e) {
    echo $e->getMessage();
}
```

### Method by method

| 1.x | 2.x |
|---|---|
| `new SMSService()` + `smsAuth($u, $p, $k)` | `Sms4FreeClient::create($u, $p, $k)`, or `new Sms4FreeClient(new Credentials(...))` |
| `sendSMS($sender, $to, $text)` | `send($sender, $to, $text)`, returns `SendResult` and throws on failure |
| `generateRandomOTP()` | `(new OtpGenerator())->generate()`, returns a **string** so leading zeros survive |
| `getInvalidPhoneNumbers($list)` | `$client->findInvalidRecipients($list)`, or `PhoneNumber::tryParse()` |

### Behaviour that changed

- **Errors are exceptions, not return values.** Catch `SmsFreeException` for everything, or the four
  specific types when you want to react differently to bad input, a refusal and a network failure.
- **A multi-recipient send now succeeds.** 1.x compared the provider's status against `1`, so sending
  to two people, which returns `2`, was reported as an error even though both messages went out.
- **`+972...` numbers are accepted.** 1.x stripped every non-digit *before* matching its own pattern,
  which meant the `+972` branch of that pattern could never match and every international-format
  number was rejected.
- **Truncation is multibyte-safe and visible.** 1.x cut the body with `substr()`, which slices a
  Hebrew character in half. Truncation now happens on a character boundary, and
  `SendResult::wasTruncated()` tells you it happened. You can also turn it into a hard failure with
  `ClientOptions::withMessageTruncation(false)`.
- **Invalid numbers are reported as typed.** 1.x echoed back the digit-stripped version, so the user
  saw a number they had never entered.
- **Timeouts are bounded.** 1.x waited indefinitely for a connection and up to 400 seconds for a
  response. The defaults are now 5 and 15 seconds.
- **Duplicate recipients are collapsed**, so the same number in a list twice is billed once.
- **The library no longer calls `set_time_limit(0)`.** Disabling a script's time limit is the
  application's decision, not a library's.
- **Everything is namespaced.** No more risk of colliding with your own `SMSService` class.

### Also worth checking in your own code

The 1.x README suggested `if ($result == true)`. With a loose comparison, a non-empty error string is
also truthy, so that check reported success on every failure. If you have it, make it `=== true`, or
move to the exception-based API where the problem cannot happen.

---

<a name="hebrew"></a>

## עברית

### הגרסה הקצרה

**שום דבר לא נשבר.** המחלקה `SMSService` עדיין נשלחת עם החבילה, עדיין עם אותן מתודות, ועדיין מחזירה
`true` או מחרוזת שגיאה בעברית. אפשר לעדכן את החבילה והקוד הקיים ימשיך לעבוד, עם התראת
deprecation אחת לכל תהליך.

וכשמתאים לכם, המעבר ל-API החדש הוא שכתוב קטן:

```php
// לפני, בגרסה 1.x
require_once 'SMSService.php';

$sms = new SMSService();
$sms->smsAuth($user, $pass, $key);
$result = $sms->sendSMS($sender, [$phone], $text);

if ($result === true) {
    // נשלח
} else {
    echo $result; // מחרוזת שגיאה
}
```

```php
// אחרי, בגרסה 2.x
use EdenOhana\SmsFree\Credentials;
use EdenOhana\SmsFree\Exception\SmsFreeException;
use EdenOhana\SmsFree\Sms4FreeClient;

$client = new Sms4FreeClient(new Credentials($user, $pass, $key));

try {
    $result = $client->send($sender, [$phone], $text);
    // נשלח, ו-$result יודע כמה הודעות, בכמה חלקים, והאם הטקסט קוצר
} catch (SmsFreeException $e) {
    echo $e->getMessage();
}
```

### מתודה מול מתודה

| 1.x | 2.x |
|---|---|
| `new SMSService()` ‏+‏ `smsAuth($u, $p, $k)` | `Sms4FreeClient::create($u, $p, $k)`, או `new Sms4FreeClient(new Credentials(...))` |
| `sendSMS($sender, $to, $text)` | `send($sender, $to, $text)`, מחזירה `SendResult` וזורקת בכישלון |
| `generateRandomOTP()` | `(new OtpGenerator())->generate()`, מחזירה **מחרוזת** כך שאפס מוביל נשמר |
| `getInvalidPhoneNumbers($list)` | `$client->findInvalidRecipients($list)`, או `PhoneNumber::tryParse()` |

### מה השתנה בהתנהגות

- **שגיאות הן חריגות, לא ערכי החזרה.** אפשר לתפוס `SmsFreeException` אחת לכל דבר, או את ארבעת
  הטיפוסים הספציפיים כשרוצים להגיב אחרת לקלט שגוי, לסירוב של הספק ולתקלת רשת.
- **שליחה לכמה נמענים סוף סוף מצליחה.** גרסה 1.x השוותה את הסטטוס של הספק ל-`1`, ולכן שליחה לשני
  אנשים, שמחזירה `2`, דווחה ככישלון למרות ששתי ההודעות יצאו.
- **מספרים בפורמט `+972...` מתקבלים.** גרסה 1.x הסירה כל תו שאינו ספרה *לפני* ההשוואה לתבנית שלה עצמה,
  כך שהענף `+972` בתבנית לא יכול היה להתאים אף פעם וכל מספר בפורמט בינלאומי נפסל.
- **החיתוך בטוח למולטי-בייט וגלוי.** גרסה 1.x חתכה עם `substr()`, שחותך תו עברי באמצע. עכשיו החיתוך
  על גבול תו שלם, ו-`SendResult::wasTruncated()` מדווח שזה קרה. אפשר גם להפוך את זה לשגיאה עם
  `ClientOptions::withMessageTruncation(false)`.
- **מספרים לא תקינים מוחזרים כפי שהוקלדו.** גרסה 1.x החזירה את הגרסה המנוקה מספרות בלבד, כך שהמשתמש
  ראה מספר שהוא מעולם לא הקליד.
- **ה-timeouts חסומים.** גרסה 1.x חיכתה לחיבור ללא הגבלה, ועד 400 שניות לתשובה. ברירות המחדל היום הן
  5 ו-15 שניות.
- **נמענים כפולים מאוחדים**, כך שאותו מספר שמופיע פעמיים ברשימה מחויב פעם אחת.
- **הספרייה כבר לא קוראת ל-`set_time_limit(0)`.** ביטול מגבלת הזמן של הסקריפט הוא החלטה של האפליקציה,
  לא של ספרייה.
- **הכול בתוך namespace.** אין יותר סיכון להתנגשות עם מחלקת `SMSService` משלכם.

### שווה לבדוק גם בקוד שלכם

ה-README של גרסה 1.x הציע `if ($result == true)`. בהשוואה רופפת גם מחרוזת שגיאה לא ריקה היא truthy,
כלומר הבדיקה הזו דיווחה על הצלחה בכל כישלון. אם יש לכם אותה בקוד, שנו ל-`=== true`, או עברו ל-API
מבוסס החריגות שבו הבעיה הזו לא יכולה לקרות.
