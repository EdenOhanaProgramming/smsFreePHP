<?php
set_time_limit(0);

class SMSService
{
    private string $username;
    private string $password;
    private string $api_token;

    public function smsAuth(string $username, string $password, string $api_token)
    {
        $this->username = $username;
        $this->password = $password;
        $this->api_token = $api_token;
    }

    public function sendSMS(string $senderName, array $recipientsList, string $message)
    {
        $errorMsg = "";

        if(!strlen($senderName) || !strlen($message)) $errorMsg = "שולח או הודעה ריקים.";
        else {
            if (!count($recipientsList)) $errorMsg = "לא הוזנו נמענים להודעה.";
            else {
                $invalidNumbers = $this->getInvalidPhoneNumbers($recipientsList);
                if(count($invalidNumbers)) $errorMsg = "המספרים הבאים אינם תקינים: ".implode(", ", $invalidNumbers).".";
            }
        }

        if(empty($errorMsg)) {
            $postData = array(
                'key' => $this->api_token,
                'user' => $this->username,
                'pass' => $this->password,
                'sender' => $senderName,
                'recipient' => implode(",", $recipientsList),
                'msg' => strlen($message) > 134 ? substr($message, 0, 134) : $message
            );
    
            $ch = curl_init("https://api.sms4free.co.il/ApiSMS/v2/SendSMS");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 400);
    
            try {
                $data = json_decode(curl_exec($ch), false);
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);
    
                if ($curl_errno) {
                    $errorMsg = "שגיאת CURL בעת שליחת ה-SMS: {$curl_error}";
                } else if ($data->status !== 1) {
                    $errorMsg = "שגיאת שירות בעת שליחת ה-SMS: {$data->message}";
                } else {
                    return true;
                }
            } catch (\Throwable $th) {
                $errorMsg = "שגיאת שרת פנימית בעת שליחת ה-SMS: {$th->getMessage()}";
            }
        }

        return $errorMsg;
    }

    public function generateRandomOTP()
    {
        return random_int(100000, 999999);
    }

    public function getInvalidPhoneNumbers(array $phoneNumbers) {
        $invalidNumbers = [];
        foreach ($phoneNumbers as $phoneNumber) {
            $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
            if (!preg_match('/^(05\d{8}|(\+972)?5\d{8})$/', $phoneNumber)) $invalidNumbers[] = $phoneNumber;
        }
        return $invalidNumbers;
    }
}
?>