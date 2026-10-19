<?php
/**
 * Test SMS Sender - Sends SMS immediately to all users for testing
 * Use this to verify your SMS provider is working correctly
 */

require_once 'config.php';
require_once 'sms_config.php';

// ============================================
// SMS SENDING FUNCTIONS
// ============================================

/**
 * Send SMS using Twilio
 */
function sendSmsTwilio($phoneNumber, $message) {
    try {
        // Check if credentials are configured
        if (TWILIO_ACCOUNT_SID === 'YOUR_TWILIO_ACCOUNT_SID' || empty(TWILIO_ACCOUNT_SID)) {
            return [
                'success' => false,
                'error' => 'Twilio Account SID not configured. Please update TWILIO_ACCOUNT_SID in sms_config.php'
            ];
        }
        
        if (TWILIO_AUTH_TOKEN === 'YOUR_TWILIO_AUTH_TOKEN' || empty(TWILIO_AUTH_TOKEN)) {
            return [
                'success' => false,
                'error' => 'Twilio Auth Token not configured. Please update TWILIO_AUTH_TOKEN in sms_config.php'
            ];
        }
        
        if (empty(TWILIO_PHONE_NUMBER) || TWILIO_PHONE_NUMBER === '+1234567890') {
            return [
                'success' => false,
                'error' => 'Twilio Phone Number not configured. Please update TWILIO_PHONE_NUMBER in sms_config.php with a valid Twilio number.'
            ];
        }
        
        require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';
        
        $client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        
        // Format phone number to E.164 format if needed
        $formattedPhone = formatPhoneNumber($phoneNumber);
        
        // Validate Twilio phone number format
        $twilioNumber = trim(TWILIO_PHONE_NUMBER);
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $twilioNumber)) {
            return [
                'success' => false,
                'error' => 'Invalid Twilio phone number format. Must be in E.164 format (e.g., +15551234567). Current: ' . $twilioNumber
            ];
        }
        
        $message = $client->messages->create(
            $formattedPhone,
            [
                'from' => $twilioNumber,
                'body' => $message
            ]
        );
        
        return [
            'success' => true,
            'message_sid' => $message->sid,
            'status' => $message->status
        ];
    } catch (\Twilio\Exceptions\RestException $e) {
        $errorMsg = $e->getMessage();
        $errorCode = $e->getCode();
        
        // Provide more helpful error messages
        if ($errorCode == 401 || strpos($errorMsg, 'Authentication Error') !== false) {
            $errorMsg .= ' - Please check your TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN in sms_config.php are correct.';
        } elseif ($errorCode == 400 && (strpos($errorMsg, 'not a Twilio phone number') !== false || strpos($errorMsg, 'country mismatch') !== false)) {
            $errorMsg = "The phone number '" . TWILIO_PHONE_NUMBER . "' is not a valid Twilio number in your account. ";
            $errorMsg .= "Please: 1) Go to https://console.twilio.com/us1/develop/phone-numbers/manage/incoming ";
            $errorMsg .= "2) Buy/verify a phone number 3) Update TWILIO_PHONE_NUMBER in sms_config.php";
        } elseif (strpos($errorMsg, 'invalid') !== false && strpos($errorMsg, 'phone') !== false) {
            $errorMsg .= ' - Please check your TWILIO_PHONE_NUMBER in sms_config.php is correct and verified in Twilio console.';
        }
        
        return [
            'success' => false,
            'error' => $errorMsg . ' (Error Code: ' . $errorCode . ')'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send SMS using Infobip
 */
function sendSmsInfobip($phoneNumber, $message) {
    try {
        require_once __DIR__ . '/infobip-api-php-client-master/Infobip/Configuration.php';
        require_once __DIR__ . '/infobip-api-php-client-master/Infobip/Api/SmsApi.php';
        require_once __DIR__ . '/infobip-api-php-client-master/Infobip/Model/SmsRequest.php';
        require_once __DIR__ . '/infobip-api-php-client-master/Infobip/Model/SmsMessage.php';
        require_once __DIR__ . '/infobip-api-php-client-master/Infobip/Model/SmsDestination.php';
        require_once __DIR__ . '/infobip-api-php-client-master/Infobip/Model/SmsTextContent.php';
        
        $configuration = new \Infobip\Configuration(INFOBIP_BASE_URL, INFOBIP_API_KEY);
        $sendSmsApi = new \Infobip\Api\SmsApi($configuration);
        
        // Format phone number (remove + and spaces)
        $formattedPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Create objects using positional parameters
        // SmsDestination($to, $messageId = null, $networkId = null)
        $destination = new \Infobip\Model\SmsDestination($formattedPhone);
        
        // SmsTextContent($text, $transliteration = null, $language = null)
        $textContent = new \Infobip\Model\SmsTextContent($message);
        
        // SmsMessage($destinations, $content, $sender = null, $options = null, $webhooks = null)
        $smsMessage = new \Infobip\Model\SmsMessage(
            [$destination],  // destinations array
            $textContent,     // content
            INFOBIP_SENDER_ID // sender
        );
        
        // SmsRequest($messages)
        $request = new \Infobip\Model\SmsRequest([$smsMessage]);
        $response = $sendSmsApi->sendSmsMessages($request);
        
        $messages = $response->getMessages();
        $messageId = !empty($messages) && isset($messages[0]) ? $messages[0]->getMessageId() : 'unknown';
        
        return [
            'success' => true,
            'message_id' => $messageId,
            'status' => 'sent'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

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

/**
 * Send SMS using configured provider
 */
function sendSms($phoneNumber, $message) {
    if (SMS_PROVIDER === 'twilio') {
        return sendSmsTwilio($phoneNumber, $message);
    } elseif (SMS_PROVIDER === 'infobip') {
        return sendSmsInfobip($phoneNumber, $message);
    } else {
        return [
            'success' => false,
            'error' => 'Invalid SMS provider configured'
        ];
    }
}

// ============================================
// MAIN LOGIC
// ============================================

$conn = getDBConnection();
$results = [
    'success' => [],
    'failed' => []
];

// Get users with phone numbers
if (!empty(TEST_PHONE_NUMBER)) {
    // Send to specific test number only
    $query = "
        SELECT 
            u.user_id,
            u.full_name,
            '" . $conn->real_escape_string(TEST_PHONE_NUMBER) . "' as phone_number,
            u.email
        FROM users u
        WHERE u.user_id = 1
        LIMIT 1
    ";
} else {
    // Get all users with phone numbers
    $query = "
        SELECT 
            u.user_id,
            u.full_name,
            u.phone_number,
            u.email
        FROM users u
        WHERE 
            u.phone_number IS NOT NULL 
            AND u.phone_number != ''
            AND u.status = 'active'
        ORDER BY u.user_id ASC
    ";
}

$result = $conn->query($query);

if (!$result) {
    die("Error executing query: " . $conn->error);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test SMS Sender</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #27ae60; padding: 10px; background: #d4edda; border-left: 4px solid #27ae60; margin: 10px 0; }
        .error { color: #e74c3c; padding: 10px; background: #f8d7da; border-left: 4px solid #e74c3c; margin: 10px 0; }
        .info { color: #3498db; padding: 10px; background: #d1ecf1; border-left: 4px solid #3498db; margin: 10px 0; }
        .warning { color: #f39c12; padding: 10px; background: #fff3cd; border-left: 4px solid #f39c12; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #667eea; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .message-preview { background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0; white-space: pre-wrap; font-family: monospace; }
        .send-button { 
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
            transition: all 0.3s ease;
        }
        .send-button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .send-button:active { 
            transform: translateY(0);
        }
        .send-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Test SMS Sender</h1>
        <div class='warning'>
            <strong>⚠️ TEST MODE:</strong> Click the button below to send SMS to all users with phone numbers.
        </div>
        <div class='info'>
            <strong>Provider:</strong> " . strtoupper(SMS_PROVIDER) . " | 
            <strong>Date:</strong> " . date('Y-m-d H:i:s') . "
        </div>";
        
// Check configuration
if (SMS_PROVIDER === 'twilio') {
    $configIssues = [];
    if (empty(TWILIO_ACCOUNT_SID) || TWILIO_ACCOUNT_SID === 'YOUR_TWILIO_ACCOUNT_SID') {
        $configIssues[] = 'TWILIO_ACCOUNT_SID';
    }
    if (empty(TWILIO_AUTH_TOKEN) || TWILIO_AUTH_TOKEN === 'YOUR_TWILIO_AUTH_TOKEN') {
        $configIssues[] = 'TWILIO_AUTH_TOKEN';
    }
    if (empty(TWILIO_PHONE_NUMBER) || TWILIO_PHONE_NUMBER === '+1234567890') {
        $configIssues[] = 'TWILIO_PHONE_NUMBER';
    }
    
    if (!empty($configIssues)) {
        echo "<div class='error'>
            <strong>⚠️ Configuration Required:</strong><br>
            Please configure the following in <strong>sms_config.php</strong>:<br>
            <ul style='margin: 10px 0; padding-left: 20px;'>";
        foreach ($configIssues as $issue) {
            echo "<li><strong>$issue</strong></li>";
        }
        echo "</ul>
            <strong>Steps to get a Twilio Phone Number (Covered by Trial Credit):</strong>
            <ol style='margin: 10px 0; padding-left: 20px;'>
                <li><strong>Sign up FREE:</strong> <a href='https://www.twilio.com/try-twilio' target='_blank'>https://www.twilio.com/try-twilio</a> (No credit card needed!)</li>
                <li>Go to <a href='https://console.twilio.com/us1/develop/phone-numbers/manage/incoming' target='_blank'>Twilio Phone Numbers</a></li>
                <li><strong>Click Buy a number</strong> in the left menu</li>
                <li>Select <strong>United States</strong> as country</li>
                <li>Click <strong>Search</strong> - numbers will show ~\$1.15/month</li>
                <li>Choose any number and click <strong>Buy</strong></li>
                <li><strong>Important:</strong> It will show a cost, but your \$15.50 trial credit covers it!</li>
                <li>Copy the number (e.g., +15551234567) and update TWILIO_PHONE_NUMBER in sms_config.php</li>
            </ol>
            <div style='background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0;'>
                <strong>💡 Trial Credit:</strong> You get \$15.50 FREE credit which covers the phone number (~\$1.15/month) for about 13 months! Plus enough credit for ~150-300 SMS messages to Philippine numbers.
            </div>
            <div style='background: #e7f3ff; padding: 10px; border-radius: 4px; margin: 10px 0;'>
                <strong>✅ Good News:</strong> US Twilio numbers CAN send SMS to Philippine numbers (+63)! International SMS works worldwide. Rates are ~\$0.05-0.10 per message to PH.
            </div>
            <div style='background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0;'>
                <strong>⚠️ Note:</strong> Numbers cost money, but your trial credit will be used automatically. You won't be charged until the credit runs out.
            </div>
            <strong>Current TWILIO_PHONE_NUMBER:</strong> " . htmlspecialchars(TWILIO_PHONE_NUMBER) . "<br>
            <strong>Note:</strong> This number must be purchased/verified in your Twilio account.
        </div>";
    } else {
        // Show current configuration
        echo "<div class='info'>
            <strong>✅ Configuration:</strong><br>
            Account SID: " . substr(TWILIO_ACCOUNT_SID, 0, 10) . "...<br>
            Phone Number: " . htmlspecialchars(TWILIO_PHONE_NUMBER) . "<br>
            <small>If you get errors, verify this number exists in your <a href='https://console.twilio.com/us1/develop/phone-numbers/manage/incoming' target='_blank'>Twilio Console</a></small>
        </div>";
        
        // Check if using trial account
        echo "<div class='warning'>
            <strong>💡 Free Trial Account:</strong><br>
            If you're using a Twilio trial account, you can only send SMS to <strong>verified phone numbers</strong>.<br>
            <strong>To verify numbers:</strong>
            <ol style='margin: 10px 0; padding-left: 20px;'>
                <li>Go to <a href='https://console.twilio.com/us1/develop/phone-numbers/manage/verified' target='_blank'>Verified Caller IDs</a></li>
                <li>Click <strong>'Add a new Caller ID'</strong></li>
                <li>Enter the phone number you want to send to</li>
                <li>Verify it via call or SMS</li>
            </ol>
            <strong>Note:</strong> Trial accounts get a free phone number and $15.50 credit. Upgrade to send to unverified numbers.
        </div>";
    }
} elseif (SMS_PROVIDER === 'infobip') {
    if (INFOBIP_BASE_URL === 'https://your-base-url.infobip.com' || INFOBIP_API_KEY === 'YOUR_INFOBIP_API_KEY') {
        echo "<div class='error'>
            <strong>⚠️ Configuration Required:</strong><br>
            Please configure your Infobip credentials in sms_config.php.
        </div>";
    }
}

// Test message
$testMessage = "Hello! This is a test message from Daet Public Market Office.\n\n";
$testMessage .= "Your payment reminder system is working correctly!\n\n";
$testMessage .= "Thank you for your attention.";

echo "<div class='message-preview'><strong>Test Message:</strong>\n" . htmlspecialchars($testMessage) . "</div>";

// Check if form was submitted
$sendSms = isset($_POST['send_sms']) && $_POST['send_sms'] === '1';

if (!$sendSms) {
    // Show button to send SMS
    echo "<form method='POST' action=''>
        <input type='hidden' name='send_sms' value='1'>
        <div class='button-container'>
            <button type='submit' class='send-button'>📱 Send Test SMS to All Users</button>
        </div>
    </form>";
    
    // Show user count
    $userCount = $result->num_rows;
    echo "<div class='info'><strong>📋 Users with phone numbers:</strong> $userCount</div>";
    
    echo "</div></body></html>";
    $conn->close();
    exit;
}

// If form was submitted, proceed with sending SMS
echo "<div class='info'><strong>⏳ Sending SMS messages...</strong></div>";

$totalUsers = 0;
$sentCount = 0;
$failedCount = 0;

// Reset result pointer
$result->data_seek(0);

while ($row = $result->fetch_assoc()) {
    $totalUsers++;
    
    $phoneNumber = !empty(TEST_PHONE_NUMBER) ? TEST_PHONE_NUMBER : $row['phone_number'];
    $fullName = $row['full_name'] ?? 'User';
    
    // Personalize message
    $message = str_replace('Hello!', 'Hello ' . $fullName . '!', $testMessage);
    
    // Send SMS
    $smsResult = sendSms($phoneNumber, $message);
    
    if ($smsResult['success']) {
        $sentCount++;
        $results['success'][] = [
            'name' => $fullName,
            'phone' => $phoneNumber,
            'message_id' => $smsResult['message_sid'] ?? $smsResult['message_id'] ?? 'N/A',
            'status' => $smsResult['status'] ?? 'sent'
        ];
    } else {
        $failedCount++;
        $results['failed'][] = [
            'name' => $fullName,
            'phone' => $phoneNumber,
            'error' => $smsResult['error'] ?? 'Unknown error'
        ];
    }
}

// Display results
echo "<h2>📊 Summary</h2>";
echo "<div class='success'><strong>✅ Successfully Sent:</strong> $sentCount</div>";
echo "<div class='error'><strong>❌ Failed:</strong> $failedCount</div>";
echo "<div class='info'><strong>📋 Total Users:</strong> $totalUsers</div>";

// Display successful sends
if (!empty($results['success'])) {
    echo "<h2>✅ Successfully Sent SMS</h2>";
    echo "<table>";
    echo "<tr><th>Name</th><th>Phone</th><th>Status</th><th>Message ID</th></tr>";
    foreach ($results['success'] as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['phone']) . "</td>";
        echo "<td><span class='status-badge status-success'>" . htmlspecialchars($item['status']) . "</span></td>";
        echo "<td>" . htmlspecialchars($item['message_id']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Display failed sends
if (!empty($results['failed'])) {
    echo "<h2>❌ Failed to Send SMS</h2>";
    echo "<table>";
    echo "<tr><th>Name</th><th>Phone</th><th>Error</th></tr>";
    foreach ($results['failed'] as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['phone']) . "</td>";
        echo "<td><span class='status-badge status-error'>" . htmlspecialchars($item['error']) . "</span></td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</div></body></html>";

$conn->close();
?>

