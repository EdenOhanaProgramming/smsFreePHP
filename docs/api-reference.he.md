# תיעוד ה-API

[עברית](api-reference.he.md) · [English](api-reference.md) · [← חזרה ל-README](../README.he.md)

כל המחלקות נמצאות ב-namespace‏ `EdenOhana\SmsFree`, אלא אם צוין אחרת.

---

## `Sms4FreeClient`

נקודת הכניסה לספרייה. אובייקט immutable, שאפשר להחזיק כשירות ארוך-חיים ב-container.

```php
new Sms4FreeClient(
    Credentials $credentials,
    ClientOptions $options = new ClientOptions(),
    ?HttpClient $httpClient = null,
)
```

| מתודה | מחזירה | תיאור |
|---|---|---|
| `create(string $username, string $password, string $apiKey)` | `self` | קיצור דרך למקרה הנפוץ, עם הגדרות ברירת המחדל. |
| `send(string $senderName, iterable\|string\|PhoneNumber $recipients, string\|Message $message)` | `SendResult` | שולחת הודעה אחת לנמען אחד או יותר. זורקת חריגה בכל כישלון. |
| `findInvalidRecipients(iterable $recipients)` | `list<string>` | הערכים שלא ניתן לפענח. לא מבצעת שום קריאת רשת. |
| `options()` | `ClientOptions` | ההגדרות שאיתן נבנה הלקוח. |

מה `send()` עושה, שלב אחר שלב:

1. שם השולח עובר `trim` ואסור שיהיה ריק.
2. הנמענים מפוענחים ומנורמלים; **כל** הערכים השגויים מדווחים יחד.
3. נמענים כפולים מאוחדים, כדי שאף אחד לא יחויב פעמיים באותה קריאה.
4. גוף ההודעה נבדק, ואם הוא ארוך מהמותר הוא נחתך או נפסל — לפי ההגדרות.
5. הבקשה נשלחת כ-JSON, כשהעברית נשארת כמות שהיא ולא מקודדת ל-`\uXXXX`.
6. סטטוס חיובי בתשובה הופך ל-`SendResult`; כל דבר אחר הופך ל-`ApiException`.

חריגות אפשריות: ‏`InvalidArgumentException`,‏ `InvalidPhoneNumberException`,‏ `TransportException`,‏ `ApiException`.

---

## `Credentials`

מחזיק immutable של פרטי החשבון. הסיסמה ומפתח ה-API מוסתרים בפלט של `var_dump()`.

```php
new Credentials(string $username, string $password, string $apiKey)
Credentials::fromEnvironment(string $prefix = 'SMS4FREE_'): self
```

‏`fromEnvironment()` קוראת את `{PREFIX}USERNAME`,‏ `{PREFIX}PASSWORD` ו-`{PREFIX}API_KEY`, וזורקת
`InvalidArgumentException` עם שם המשתנה הראשון שחסר.

גישה לערכים: ‏`username()`,‏ `password()`,‏ `apiKey()`.

---

## `ClientOptions`

הגדרות immutable. כל מתודת `with*()` מחזירה עותק מעודכן.

| הגדרה | ברירת מחדל | הערות |
|---|---|---|
| `endpoint` | `https://api.sms4free.co.il/ApiSMS/v2/SendSMS` | חייבת להיות כתובת **HTTPS** מלאה. |
| `connectTimeout` | `5.0` שניות | חייב להיות גדול מאפס. |
| `timeout` | `15.0` שניות | תקציב הזמן לבקשה כולה. |
| `maxMessageLength` | `134` תווים | המגבלה של הספק. |
| `truncateLongMessages` | `true` | ‏`false` הופך הודעה ארוכה מדי לחריגה. |
| `allowInternational` | `false` | ‏`true` מאפשר נמענים שאינם ישראליים. |
| `caBundlePath` | `null` | לשרתים שבהם ל-PHP אין מאגר תעודות שמיש. חייב להיות קריא. |
| `userAgent` | `smsFreePHP/<version>` | נשלח בכל בקשה. |

מתודות: ‏`withEndpoint()`,‏ `withTimeouts()`,‏ `withMaxMessageLength()`,‏ `withMessageTruncation()`,‏
`withInternationalRecipients()`,‏ `withCaBundlePath()`,‏ `withUserAgent()`.

אימות TLS אינו ניתן לכיבוי. הבקשות נושאות את פרטי החשבון, ואין סיבה מוצדקת לשלוח אותם על גבי חיבור
שאף אחד לא אימת; מאגר תעודות שבור נפתר עם `withCaBundlePath()`.

---

## `PhoneNumber`

מספר נמען מפוענח ומנורמל.

```php
PhoneNumber::parse(string $raw, bool $allowInternational = false): self          // זורקת
PhoneNumber::tryParse(string $raw, bool $allowInternational = false): ?self      // מחזירה null
PhoneNumber::parseList(iterable $numbers, bool $allowInternational = false): array
```

‏`parseList()` מדווחת על כל הערכים השגויים ב-`InvalidPhoneNumberException` אחת, במקום לעצור בראשון,
ומעבירה הלאה בלי לגעת מופעים שכבר פוענחו.

| מתודה | דוגמה |
|---|---|
| `raw()` | `'054-123-4567'` — בדיוק מה שהתקבל |
| `national()` | `'0541234567'` — הצורה שנשלחת לספק |
| `e164()` | `'+972541234567'`, או `null` כשלא ניתן לקבוע קידומת מדינה |
| `isIsraeli()` | `true` |
| `equals(PhoneNumber $other)` | משווה את הצורות הקנוניות |
| `__toString()` | זהה ל-`national()` |

פורמטים ישראליים שמתקבלים — כולם אותו קו בדיוק:

```
0541234567      054-123-4567      054 123 4567      (054) 123-4567
054.123.4567    +972541234567     +972 54 123 4567  00972541234567
+9720541234567  972541234567      541234567
```

נפסלים במצב ברירת המחדל: קווים נייחים, מספרים באורך שגוי, כל מה שמכיל אותיות, ומספרים זרים.

---

## `Message`

גוף ההודעה.

```php
Message::of(string $text): self   // זורקת על הודעה ריקה או על UTF-8 שבור
```

| מתודה | מחזירה | תיאור |
|---|---|---|
| `text()` | `string` | תוכן ההודעה. |
| `length()` | `int` | תווים כפי שאדם סופר אותם — לא בייטים. |
| `encoding()` | `SmsEncoding` | ‏`Gsm7` או `Ucs2`. |
| `parts()` | `int` | לכמה חלקים הרשת מפצלת את ההודעה, כלומר כמה קרדיטים. |
| `truncateTo(int $maxLength)` | `self` | חותכת על גבול תו שלם. מחזירה את `$this` כשאין מה לחתוך. |
| `isTruncated()` | `bool` | האם המופע הזה נוצר מחיתוך שאכן קיצר משהו. |

---

## `SmsEncoding`

‏enum שמתאר את הא״ב שבו ההודעה נוסעת.

| ערך | חלק בודד | הודעה מפוצלת |
|---|---|---|
| `SmsEncoding::Gsm7` | 160 תווים | 153 לכל חלק |
| `SmsEncoding::Ucs2` | 70 תווים | 67 לכל חלק |

‏`SmsEncoding::detect(string $text)` בוחרת את הא״ב שהטקסט מחייב. עברית, ערבית ואימוג׳י כולם מכריחים
`Ucs2`. תווים מטבלת ההרחבה של GSM (`^ { } [ ] ~ | \ €`) עולים שני septets כל אחד.

---

## `SendResult`

מה ש-`send()` מחזירה.

| מתודה | מחזירה | תיאור |
|---|---|---|
| `acceptedCount()` | `int` | כמה הודעות הספק קיבל. יכול להיות נמוך ממספר הנמענים. |
| `recipients()` | `list<PhoneNumber>` | הנמענים אחרי נרמול ואיחוד כפילויות. |
| `recipientNumbers()` | `list<string>` | אותו דבר, כמחרוזות קנוניות. |
| `message()` | `Message` | הגוף כפי שנשלח בפועל. |
| `wasTruncated()` | `bool` | האם הגוף קוצר. |
| `providerMessage()` | `string` | טקסט שהספק החזיר יחד עם ההצלחה. |
| `estimatedCredits()` | `int` | ‏`חלקים × נמענים`. החיוב בפועל אצל הספק הוא הקובע. |

---

## `Otp\OtpGenerator`

```php
new OtpGenerator(int $length = 6)   // בין 4 ל-32 ספרות
```

| מתודה | מחזירה | תיאור |
|---|---|---|
| `generate()` | `string` | קוד חדש מ-`random_int()`. מחרוזת, כדי שאפסים מובילים לא ייעלמו. |
| `length()` | `int` | האורך שהוגדר. |
| `OtpGenerator::matches(string $expected, string $provided)` | `bool` | השוואה בזמן קבוע באמצעות `hash_equals()`. |

---

## `Http\HttpClient`

התפר בין הספרייה לרשת.

```php
interface HttpClient
{
    public function post(string $url, string $body, array $headers = []): HttpResponse;
}
```

‏`Http\CurlHttpClient` הוא המימוש שמגיע כברירת מחדל, מעל ext-curl. ‏`Http\HttpResponse` חושף את
`statusCode()`,‏ `body()` ו-`isSuccessful()`.

מימוש עצמאי של `HttpClient` מאפשר לנתב את הבקשות דרך שכבת ה-HTTP שכבר יש לכם, או לזייף את הספק
בטסטים בלי גישה לרשת בכלל.

---

## חריגות

כולן מממשות את `Exception\SmsFreeException`, שיורש מ-`Throwable`.

| מחלקה | יורשת מ- | מתודות נוספות |
|---|---|---|
| `Exception\InvalidArgumentException` | `\InvalidArgumentException` | — |
| `Exception\InvalidPhoneNumberException` | הקודמת | `invalidNumbers(): list<string>` |
| `Exception\TransportException` | `\RuntimeException` | — |
| `Exception\ApiException` | `\RuntimeException` | `status(): int`,‏ `providerMessage(): string` |

‏`ApiException` לא מתרגמת את קודי הסטטוס של הספק לקבועים בעלי שם — הספק חופשי לשנות את הטבלה הזו,
והעברת הקוד וההודעה כמות שהם מונעת מהספרייה להתיימר לדעת משהו שהיא לא באמת יודעת.

---

## תאימות לאחור: `SMSService`

המחלקה מגרסה 1.x, שממשיכה לעבוד מעל הקוד החדש ומסומנת deprecated מאז 2.0. היא שומרת על החוזה הישן —
`true` בהצלחה, מחרוזת שגיאה בעברית בכישלון — ומדליקה התראת `E_USER_DEPRECATED` פעם אחת בכל תהליך.
ראו [UPGRADING.md](../UPGRADING.md).
