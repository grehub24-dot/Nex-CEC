<?php
class SMSHelper {
    private function normalizePhone($to) {
        $phone = trim((string)$to);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/\s+/', '', $phone);
        $phone = str_replace(['-', '(', ')'], '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (!preg_match('/^\d+$/', $phone)) {
            return null;
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '233' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            $phone = '233' . $phone;
        }

        if (!preg_match('/^233\d{9}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    public function send($to, $message) {
        $normalizedPhone = $this->normalizePhone($to);
        if ($normalizedPhone === null) {
            return false;
        }

        $payloadMessage = trim((string)$message);
        if ($payloadMessage === '') {
            return false;
        }
        if (mb_strlen($payloadMessage) > 160) {
            $payloadMessage = mb_substr($payloadMessage, 0, 157) . '...';
        }

        $api_key = '$2y$10$6oYYcjc6Ge3/W.P.1Yqk6eHBs0ERVFR6IaBQ2qpYGBnMYp28B3uPe';
        $username = 'amanvid'; // using sender ID as username per standard Wigal API docs
        $sender_id = 'INFOTESS'; // Must be an approved sender ID, falling back to INFOTESS
        $endpoint = 'https://frogapi.wigal.com.gh/api/v3/sms/send';

        $postData = array(
            'senderid' => $sender_id,
            'destinations' => array(
                array(
                    'destination' => $normalizedPhone,
                    'message' => $payloadMessage,
                    'msgid' => uniqid('MSG'),
                    'smstype' => 'text'
                )
            )
        );

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'API-KEY: ' . $api_key,
            'USERNAME: ' . $username
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        
        // Disable SSL verification for local WAMP if needed, but best left on in prod
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // Optional: Still log it locally to track API responses
        $dir = __DIR__ . '/../sms_logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $logEntry = "[" . date('Y-m-d H:i:s') . "] To: $normalizedPhone | Message: $payloadMessage | API Response: $response | Curl Error: $error" . PHP_EOL;
        file_put_contents($dir . '/sms.log', $logEntry, FILE_APPEND);

        if ($error) {
            return false;
        }

        if (!$response) {
            return false;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['status'])) {
            $status = strtolower((string)$decoded['status']);
            return in_array($status, ['success', 'ok', 'accepted', 'acceptd', 'queued'], true);
        }

        return true;
    }
}
?>
