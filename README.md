# smsFreePHP

ספריית PHP פשוטה לשליחת הודעות SMS דרך שירות SMS4Free באמצעות HTTP API.

## תיאור הפרוייקט

ספריית PHP שמאפשרת שליחת הודעות SMS עם פרטי אימות מותאמים אישית דרך שירות SMS4Free.

## דרישות מערכת

- PHP 7.0 ומעלה
- חיבור אינטרנט לשימוש ב-API של SMS4Free

## התקנה

1. הורד את הספרייה `smsFreePHP` לתיקיית הפרוייקט שלך.
2. הכנס את הספרייה לפרוייקט שלך על ידי כלאות של הקובץ `SMSService.php`.

## שימוש

### דוגמאות

#### שליחת הודעת SMS

```php
<?php

require_once("SMSService.php");

// יצירת אובייקט SMSService
$smsService = new SMSService();
// התחברות עם הפרטים
$smsService->smsAuth("username", "password", "api_key");

// שליחת הודעת SMS
$result = $smsService->sendSMS("sender", ["phone1","phone2"], "message");

// בדיקת תוצאה
if ($result === true) {
    echo "ההודעה נשלחה בהצלחה!";
} else {
    echo "שגיאה בשליחת ההודעה: $result";
}

?>
