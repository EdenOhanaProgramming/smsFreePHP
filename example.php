<?php

require_once('./SMSService.php');

$smsService = new SMSService();
$smsService->smsAuth("0501111111", "123456789", "AAAAAAA"); // Inserting Auth Details (Username, Password, API Key)
$code = $smsService->generateRandomOTP(); // Generating 6 digits OTP
$result = $smsService->sendSMS("0501111111", ["0501111111"], "הקוד שלך לאימות הוא: {$code}"); // Sender (Verified Number/Sender Name), Array of Recipients, Message
if($result == true) {
  // The SMS was sent successfuly
  echo "ה-SMS נשלח בהצלחה";
} else {
  // Errors
  echo $result;
}

?>
