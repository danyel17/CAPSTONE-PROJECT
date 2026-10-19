<?php
/**
 * Receive SMS Webhook Handler
 * This script receives incoming SMS messages from Twilio
 * 
 * To use this:
 * 1. Upload this file to your web server
 * 2. Get the public URL (e.g., https://yourdomain.com/receive_sms.php)
 * 3. Configure your Twilio number to use this URL
 */

// Log incoming SMS to a file (optional)
function logSMS($data) {
    $logFile = __DIR__ . '/sms_logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] From: {$data['From']} | To: {$data['To']} | Message: {$data['Body']}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Get incoming SMS data from Twilio
$from = $_POST['From'] ?? '';
$to = $_POST['To'] ?? '';
$body = $_POST['Body'] ?? '';
$messageSid = $_POST['MessageSid'] ?? '';

// Log the SMS
if (!empty($from)) {
    logSMS([
        'From' => $from,
        'To' => $to,
        'Body' => $body,
        'MessageSid' => $messageSid
    ]);
}

// Respond to Twilio with TwiML (required)
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<Response>
    <Message>Thank you! Your message has been received.</Message>
</Response>


