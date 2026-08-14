# אינטגרציה עם Laravel

[עברית](laravel.he.md) | [English](laravel.md) | [חזרה ל-README](../README.he.md)

החבילה כוללת service provider, ‏facade ו-notification channel. ‏Laravel מזהה את ה-provider אוטומטית,
כך שאין מה לרשום ידנית.

נתמכות גרסאות Laravel 11 ו-12, אלה שעדיין מקבלות תיקונים. פרויקטים שלא עובדים עם Laravel לא
מושפעים, כי `illuminate/*` מוגדר כאן כתלות פיתוח בלבד.

## התקנה

מוסיפים את פרטי החשבון ל-`.env`:

```dotenv
SMS4FREE_USERNAME=your-username
SMS4FREE_PASSWORD=your-password
SMS4FREE_API_KEY=your-api-key
SMS4FREE_SENDER=MyShop
```

זה כל מה שצריך כדי להתחיל לשלוח. כדי לשנות timeouts או את מדיניות החיתוך, מפרסמים קודם את קובץ
ההגדרות:

```bash
php artisan vendor:publish --tag=sms4free-config
```

הוא נוחת ב-`config/sms4free.php` ומכסה את ה-endpoint, שני ה-timeouts, מגבלת אורך ההודעה, האם הודעה
ארוכה מדי נחתכת או נפסלת, האם מותרים נמענים שאינם ישראליים, ונתיב אופציונלי ל-CA bundle.

## שליחה ישירה

מזריקים את הלקוח לאן שצריך:

```php
use EdenOhana\SmsFree\Sms4FreeClient;

final class OrderController
{
    public function __construct(private readonly Sms4FreeClient $sms)
    {
    }

    public function ship(Order $order)
    {
        $this->sms->send('MyShop', [$order->customer->phone_number], 'ההזמנה שלך יצאה לדרך');
    }
}
```

או משתמשים ב-facade:

```php
use EdenOhana\SmsFree\Laravel\Facades\Sms4Free;

Sms4Free::send('MyShop', ['054-123-4567'], 'ההזמנה שלך יצאה לדרך');
```

## Notifications

מוסיפים את `sms4free` לערוצים של ההתראה ומגדירים מתודת `toSms4Free()`:

```php
use Illuminate\Notifications\Notification;

final class VerificationCode extends Notification
{
    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['sms4free'];
    }

    public function toSms4Free(object $notifiable): string
    {
        return "הקוד שלך לאימות הוא: {$this->code}";
    }
}
```

```php
$user->notify(new VerificationCode($code));
```

כדי לשנות את השולח בהתראה מסוימת, מחזירים אובייקט הודעה במקום מחרוזת:

```php
use EdenOhana\SmsFree\Laravel\Sms4FreeMessage;

public function toSms4Free(object $notifiable): Sms4FreeMessage
{
    return Sms4FreeMessage::create('מבצע סוף עונה, 30% הנחה')->from('Marketing');
}
```

### מאיפה מגיע מספר הטלפון

הערוץ מחפש בשלושה מקומות, לפי הסדר הזה:

1. ‏`routeNotificationForSms4free()` על ה-notifiable, שזו הקונבנציה של Laravel עצמה:

   ```php
   public function routeNotificationForSms4free(): ?string
   {
       return $this->mobile;
   }
   ```

2. שדה `phone_number` במודל Eloquent.
3. property ציבורי בשם `phone_number` באובייקט רגיל.

‏notifiable שאין לו אף אחד מהם מדולג, ו-`send()` מחזירה null. משתמש אחד בלי מספר טלפון לא אמור להפיל
ריצה שיוצאת לאלף אחרים.

כל פורמט שהספרייה מקבלת עובד גם כאן, כך ש-`054-123-4567` ו-`+972 54 123 4567` שניהם תקינים.

## התראות בתור (Queue)

לא צריך שום דבר מיוחד: מממשים `ShouldQueue` על ההתראה כרגיל. שני דברים ששווה לדעת כשעושים את זה.

הספרייה לא מבצעת retry בעצמה, כי timeout לא מוכיח שההודעה לא נשלחה. בהתראה בתור, ה-retry של Laravel
הוא זה שמחליט, ולכן כדאי לקבוע `$tries` מתוך מודעות לכך שניסיון חוזר אחרי timeout עלול לשלוח את אותה
הודעה פעמיים.

חריגת `InvalidPhoneNumberException` אומרת שהקלט היה שגוי, ו-retry לא יעזור. כדאי להוסיף אותה לרשימת
`$dontRetry` של ה-job, או לתפוס אותה ב-`failed()`.

## בדיקות

מחליפים את שכבת התקשורת במימוש מזויף, ואף בקשה לא יוצאת מהמכונה:

```php
use EdenOhana\SmsFree\Http\HttpClient;
use EdenOhana\SmsFree\Http\HttpResponse;

$this->app->bind(HttpClient::class, fn () => new class implements HttpClient {
    public array $sent = [];

    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        $this->sent[] = $body;

        return new HttpResponse(200, '{"status":1,"message":"ok"}');
    }
});
```

שכבת התקשורת רשומה ב-container בנפרד מהלקוח בדיוק בשביל זה. עבור התראות, גם `Notification::fake()`
של Laravel עובד כרגיל, כמו בכל ערוץ אחר.
