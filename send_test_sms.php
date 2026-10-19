<?php
/**
 * Quick Test SMS Sender
 * Sends a test SMS to a specific number
 */

require_once 'config.php';
require_once 'sms_config.php';

// Include SMS sending functions
require_once 'send_payment_reminder_sms.php';

/**
 * Format phone number to E.164 format
 */
function formatPhoneNumber($phoneNumber) {
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // If it doesn't start with +, add country code (Philippines = +63)
    if (substr($phoneNumber, 0, 1) !== '+') {
        // If it starts with 0, replace with +63
        if (substr($cleaned, 0, 1) === '0') {
            $cleaned = '63' . substr($cleaned, 1);
        } elseif (substr($cleaned, 0, 2) !== '63') {
            $cleaned = '63' . $cleaned;
        }
        return '+' . $cleaned;
    }
    
    return $phoneNumber;
}

// Phone number to send to
$testNumber = '09511874805';

// Test message
$testMessage = "Hello! This is a test message from Daet Public Market Office.\n\n";
$testMessage .= "Your SMS system is working correctly!\n\n";
$testMessage .= "Thank you!";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Send Test SMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #27ae60; padding: 15px; background: #d4edda; border-left: 4px solid #27ae60; margin: 20px 0; border-radius: 4px; }
        .error { color: #e74c3c; padding: 15px; background: #f8d7da; border-left: 4px solid #e74c3c; margin: 20px 0; border-radius: 4px; }
        .info { color: #3498db; padding: 15px; background: #d1ecf1; border-left: 4px solid #3498db; margin: 20px 0; border-radius: 4px; }
        .message-box { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0; white-space: pre-wrap; font-family: monospace; }
        .send-btn { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none; 
            padding: 15px 40px; 
            font-size: 18px; 
            font-weight: bold; 
            border-radius: 8px; 
            cursor: pointer; 
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .send-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Send Test SMS</h1>
        
        <div class="info">
            <strong>To:</strong> <?php echo htmlspecialchars($testNumber); ?> (<?php echo htmlspecialchars(formatPhoneNumber($testNumber)); ?>)<br>
            <strong>From:</strong> <?php echo htmlspecialchars(TWILIO_PHONE_NUMBER); ?><br>
            <strong>Provider:</strong> <?php echo strtoupper(SMS_PROVIDER); ?>
        </div>
        
        <div class="message-box">
            <strong>Message:</strong><br>
            <?php echo htmlspecialchars($testMessage); ?>
        </div>

        <?php
        // Check if form was submitted
        if (isset($_POST['send_sms'])) {
            echo "<div class='info'><strong>⏳ Sending SMS...</strong></div>";
            
            // Format phone number
            $formattedNumber = formatPhoneNumber($testNumber);
            
            // Send SMS
            $result = sendSms($formattedNumber, $testMessage);
            
            if ($result['success']) {
                echo "<div class='success'>";
                echo "<strong>✅ SMS Sent Successfully!</strong><br>";
                echo "Message ID: " . htmlspecialchars($result['message_sid'] ?? $result['message_id'] ?? 'N/A') . "<br>";
                echo "Status: " . htmlspecialchars($result['status'] ?? 'sent') . "<br>";
                echo "To: " . htmlspecialchars($formattedNumber) . "<br>";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>❌ Failed to Send SMS</strong><br>";
                echo "Error: " . htmlspecialchars($result['error'] ?? 'Unknown error') . "<br>";
                echo "</div>";
                
                // Show troubleshooting tips
                if (strpos($result['error'], 'verified') !== false || strpos($result['error'], 'trial') !== false) {
                    echo "<div class='info'>";
                    echo "<strong>💡 Trial Account Limitation:</strong><br>";
                    echo "You need to verify this number first:<br>";
                    echo "1. Go to <a href='https://console.twilio.com/us1/develop/phone-numbers/manage/verified' target='_blank'>Verified Caller IDs</a><br>";
                    echo "2. Click 'Add a new Caller ID'<br>";
                    echo "3. Enter: " . htmlspecialchars($formattedNumber) . "<br>";
                    echo "4. Select 'SMS' verification method<br>";
                    echo "5. Verify the code you receive<br>";
                    echo "</div>";
                }
            }
        } else {
            // Show send button
            echo "<form method='POST'>";
            echo "<input type='hidden' name='send_sms' value='1'>";
            echo "<button type='submit' class='send-btn'>📱 Send Test SMS</button>";
            echo "</form>";
        }
        ?>
        
        <div class="info" style="margin-top: 30px;">
            <strong>Note:</strong> If you're on a Twilio trial account, make sure this number is verified first.<br>
            <a href="https://console.twilio.com/us1/develop/phone-numbers/manage/verified" target="_blank">→ Verify Number Here</a>
        </div>
    </div>
</body>
</html>

