<?php
/**
 * Send SMS Now - Sends SMS immediately when accessed
 * Sends to: 09511874805
 */

require_once 'config.php';
require_once 'sms_config.php';
require_once 'send_payment_reminder_sms.php';

// Phone number to send to
$testNumber = '09511874805';

// Test message
$testMessage = "Hello! This is a test message from Daet Public Market Office.\n\n";
$testMessage .= "Your SMS system is working correctly!\n\n";
$testMessage .= "Thank you!";

// Format number
$formattedNumber = formatPhoneNumber($testNumber);

// Send SMS
$result = sendSms($formattedNumber, $testMessage);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Send SMS Now</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #27ae60; padding: 15px; background: #d4edda; border-left: 4px solid #27ae60; margin: 20px 0; border-radius: 4px; }
        .error { color: #e74c3c; padding: 15px; background: #f8d7da; border-left: 4px solid #e74c3c; margin: 20px 0; border-radius: 4px; }
        .info { color: #3498db; padding: 15px; background: #d1ecf1; border-left: 4px solid #3498db; margin: 20px 0; border-radius: 4px; }
        .message-box { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0; white-space: pre-wrap; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Sending SMS...</h1>
        
        <div class="info">
            <strong>To:</strong> <?php echo htmlspecialchars($testNumber); ?> (<?php echo htmlspecialchars($formattedNumber); ?>)<br>
            <strong>From:</strong> <?php echo htmlspecialchars(TWILIO_PHONE_NUMBER); ?><br>
            <strong>Provider:</strong> <?php echo strtoupper(SMS_PROVIDER); ?>
        </div>
        
        <div class="message-box">
            <strong>Message:</strong><br>
            <?php echo htmlspecialchars($testMessage); ?>
        </div>

        <?php
        if ($result['success']) {
            echo "<div class='success'>";
            echo "<strong>✅ SMS Sent Successfully!</strong><br><br>";
            echo "<strong>Message ID:</strong> " . htmlspecialchars($result['message_sid'] ?? $result['message_id'] ?? 'N/A') . "<br>";
            echo "<strong>Status:</strong> " . htmlspecialchars($result['status'] ?? 'sent') . "<br>";
            echo "<strong>To:</strong> " . htmlspecialchars($formattedNumber) . "<br>";
            echo "<strong>From:</strong> " . htmlspecialchars(TWILIO_PHONE_NUMBER) . "<br><br>";
            echo "Check your phone (" . htmlspecialchars($testNumber) . ") for the message!";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ Failed to Send SMS</strong><br><br>";
            echo "<strong>Error:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "<br><br>";
            echo "</div>";
            
            // Show troubleshooting tips
            if (strpos($result['error'], 'verified') !== false || strpos($result['error'], 'trial') !== false || 
                strpos($result['error'], 'unverified') !== false || strpos($result['error'], 'cannot be sent') !== false ||
                strpos($result['error'], 'combination') !== false) {
                echo "<div class='info'>";
                echo "<strong>💡 Trial Account Limitation:</strong><br>";
                echo "You need to verify this number first in Twilio Console:<br><br>";
                echo "1. Go to <a href='https://console.twilio.com/us1/develop/phone-numbers/manage/verified' target='_blank'>Verified Caller IDs</a><br>";
                echo "2. Click 'Add a new Caller ID'<br>";
                echo "3. Enter: <strong>" . htmlspecialchars($formattedNumber) . "</strong><br>";
                echo "4. Select 'SMS' verification method<br>";
                echo "5. Enter the verification code you receive<br>";
                echo "6. Then try sending again<br><br>";
                echo "<strong>Note:</strong> Twilio trial accounts can only send SMS to verified numbers. ";
                echo "After verifying, you can send SMS to that number.";
                echo "</div>";
            }
        }
        ?>
        
        <div class="info" style="margin-top: 30px;">
            <strong>Note:</strong> If you're on a Twilio trial account, you can only send SMS to verified numbers.<br>
            <a href="https://console.twilio.com/us1/develop/phone-numbers/manage/verified" target="_blank">→ Verify Number Here</a>
        </div>
    </div>
</body>
</html>

