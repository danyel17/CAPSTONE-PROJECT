<?php
/**
 * Payment Reminder SMS Sender
 * Sends SMS reminders to users about payment due dates
 * Supports both Twilio and Infobip SMS services
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
                'error' => 'Twilio Account SID not configured. Please update TWILIO_ACCOUNT_SID in the configuration section.'
            ];
        }
        
        if (TWILIO_AUTH_TOKEN === 'YOUR_TWILIO_AUTH_TOKEN' || empty(TWILIO_AUTH_TOKEN)) {
            return [
                'success' => false,
                'error' => 'Twilio Auth Token not configured. Please update TWILIO_AUTH_TOKEN in the configuration section.'
            ];
        }
        
        if (TWILIO_PHONE_NUMBER === '+1234567890' || empty(TWILIO_PHONE_NUMBER)) {
            return [
                'success' => false,
                'error' => 'Twilio Phone Number not configured. Please update TWILIO_PHONE_NUMBER in the configuration section.'
            ];
        }
        
        require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';
        
        $client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        
        // Format phone number to E.164 format if needed
        $formattedPhone = formatPhoneNumber($phoneNumber);
        
        $message = $client->messages->create(
            $formattedPhone,
            [
                'from' => TWILIO_PHONE_NUMBER,
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
        if (strpos($errorMsg, 'Authentication Error') !== false || strpos($errorCode, '401') !== false) {
            $errorMsg .= ' - Please check your TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN are correct.';
        } elseif (strpos($errorMsg, 'invalid') !== false && strpos($errorMsg, 'phone') !== false) {
            $errorMsg .= ' - Please check your TWILIO_PHONE_NUMBER is correct and verified in Twilio console.';
        } elseif (strpos($errorMsg, 'cannot be sent') !== false || strpos($errorMsg, 'combination') !== false || strpos($errorCode, '400') !== false) {
            // This is likely a trial account limitation - number needs to be verified
            $errorMsg = 'Trial Account Limitation: The recipient number must be verified in Twilio Console. ';
            $errorMsg .= 'Go to: https://console.twilio.com/us1/develop/phone-numbers/manage/verified and add ' . $formattedPhone . ' as a verified number.';
        }
        return [
            'success' => false,
            'error' => $errorMsg
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
        $destination = new \Infobip\Model\SmsDestination($formattedPhone);
        $textContent = new \Infobip\Model\SmsTextContent($message);
        $smsMessage = new \Infobip\Model\SmsMessage(
            [$destination],
            $textContent,
            INFOBIP_SENDER_ID
        );
        
        $request = new \Infobip\Model\SmsRequest([$smsMessage]);
        $response = $sendSmsApi->sendSmsMessages($request);
        
        return [
            'success' => true,
            'message_id' => $response->getMessages()[0]->getMessageId() ?? 'unknown',
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
    'failed' => [],
    'skipped' => []
];

// Check if expiration_date column exists, if not use review_deadline or submitted_date
// First, check what columns are available
$checkColumn = $conn->query("SHOW COLUMNS FROM vendors LIKE 'expiration_date'");
$hasExpirationDate = $checkColumn && $checkColumn->num_rows > 0;

// Get users with phone numbers and their payment due dates
// This query gets vendors with their user phone numbers
if ($hasExpirationDate) {
    $query = "
        SELECT 
            u.user_id,
            u.full_name,
            u.phone_number,
            u.email,
            v.vendor_id,
            v.expiration_date as due_date,
            v.vendor_name,
            v.section,
            v.block
        FROM users u
        INNER JOIN vendors v ON u.user_id = v.user_id
        WHERE 
            u.phone_number IS NOT NULL 
            AND u.phone_number != ''
            AND u.status = 'active'
            AND v.status = 'approved'
            AND v.expiration_date IS NOT NULL
        ORDER BY v.expiration_date ASC
    ";
} else {
    // Use review_deadline or submitted_date + 1 year as fallback
    $query = "
        SELECT 
            u.user_id,
            u.full_name,
            u.phone_number,
            u.email,
            v.vendor_id,
            COALESCE(v.review_deadline, DATE_ADD(v.submitted_date, INTERVAL 1 YEAR)) as due_date,
            v.vendor_name,
            v.section,
            v.block
        FROM users u
        INNER JOIN vendors v ON u.user_id = v.user_id
        WHERE 
            u.phone_number IS NOT NULL 
            AND u.phone_number != ''
            AND u.status = 'active'
            AND v.status = 'approved'
            AND (v.review_deadline IS NOT NULL OR v.submitted_date IS NOT NULL)
        ORDER BY due_date ASC
    ";
}

$result = $conn->query($query);

if (!$result) {
    die("Error executing query: " . $conn->error);
}

$today = new DateTime();
$reminderDate = clone $today;
$reminderDate->modify('+' . REMINDER_DAYS_BEFORE . ' days');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Payment Reminder SMS Sender</title>
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
        .status-skipped { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📱 Payment Reminder SMS Sender</h1>
        <div class='info'>
            <strong>Provider:</strong> " . strtoupper(SMS_PROVIDER) . " | 
            <strong>Reminder Days Before:</strong> " . REMINDER_DAYS_BEFORE . " days |
            <strong>Date:</strong> " . $today->format('Y-m-d') . "
        </div>
";

$totalUsers = 0;
$sentCount = 0;
$failedCount = 0;
$skippedCount = 0;

while ($row = $result->fetch_assoc()) {
    $totalUsers++;
    
    $phoneNumber = $row['phone_number'];
    $fullName = $row['full_name'];
    $dueDate = new DateTime($row['due_date']);
    $vendorName = $row['vendor_name'];
    $section = $row['section'] ?? 'N/A';
    $block = $row['block'] ?? 'N/A';
    
    // Calculate days until due date
    $daysUntilDue = $today->diff($dueDate)->days;
    
    // Check if we should send reminder (within reminder window or past due)
    $shouldSend = false;
    $reason = '';
    
    if ($dueDate < $today) {
        // Past due date
        $shouldSend = true;
        $reason = 'PAST DUE - Payment is overdue';
    } elseif ($dueDate <= $reminderDate) {
        // Within reminder window
        $shouldSend = true;
        $reason = 'REMINDER - Payment due in ' . $daysUntilDue . ' days';
    } else {
        // Too early to send reminder
        $shouldSend = false;
        $reason = 'SKIPPED - Payment due in ' . $daysUntilDue . ' days (too early)';
    }
    
    if ($shouldSend) {
        // Create SMS message
        $message = "Hello " . $fullName . "!\n\n";
        $message .= "This is a reminder from Daet Public Market Office.\n\n";
        
        if ($dueDate < $today) {
            $message .= "⚠️ Your payment is OVERDUE!\n";
            $message .= "Due Date: " . $dueDate->format('F d, Y') . "\n\n";
        } else {
            $message .= "📅 Payment Reminder\n";
            $message .= "Due Date: " . $dueDate->format('F d, Y') . "\n";
            $message .= "Days Remaining: " . $daysUntilDue . "\n\n";
        }
        
        $message .= "Vendor: " . $vendorName . "\n";
        $message .= "Section: " . $section . "\n";
        if ($block !== 'N/A') {
            $message .= "Block: " . $block . "\n";
        }
        $message .= "\nPlease pay your payment before the due date to avoid penalties.\n\n";
        $message .= "Thank you!";
        
        // Send SMS
        $smsResult = sendSms($phoneNumber, $message);
        
        if ($smsResult['success']) {
            $sentCount++;
            $results['success'][] = [
                'name' => $fullName,
                'phone' => $phoneNumber,
                'due_date' => $dueDate->format('Y-m-d'),
                'days' => $daysUntilDue,
                'message_id' => $smsResult['message_sid'] ?? $smsResult['message_id'] ?? 'N/A'
            ];
        } else {
            $failedCount++;
            $results['failed'][] = [
                'name' => $fullName,
                'phone' => $phoneNumber,
                'due_date' => $dueDate->format('Y-m-d'),
                'error' => $smsResult['error'] ?? 'Unknown error'
            ];
        }
    } else {
        $skippedCount++;
        $results['skipped'][] = [
            'name' => $fullName,
            'phone' => $phoneNumber,
            'due_date' => $dueDate->format('Y-m-d'),
            'days' => $daysUntilDue,
            'reason' => $reason
        ];
    }
}

// Display results
echo "<h2>📊 Summary</h2>";
echo "<div class='success'><strong>✅ Successfully Sent:</strong> $sentCount</div>";
echo "<div class='error'><strong>❌ Failed:</strong> $failedCount</div>";
echo "<div class='warning'><strong>⏭️ Skipped:</strong> $skippedCount</div>";
echo "<div class='info'><strong>📋 Total Users:</strong> $totalUsers</div>";

// Display successful sends
if (!empty($results['success'])) {
    echo "<h2>✅ Successfully Sent SMS</h2>";
    echo "<table>";
    echo "<tr><th>Name</th><th>Phone</th><th>Due Date</th><th>Days Until</th><th>Message ID</th></tr>";
    foreach ($results['success'] as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($item['due_date']) . "</td>";
        echo "<td>" . htmlspecialchars($item['days']) . "</td>";
        echo "<td><span class='status-badge status-success'>" . htmlspecialchars($item['message_id']) . "</span></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Display failed sends
if (!empty($results['failed'])) {
    echo "<h2>❌ Failed to Send SMS</h2>";
    echo "<table>";
    echo "<tr><th>Name</th><th>Phone</th><th>Due Date</th><th>Error</th></tr>";
    foreach ($results['failed'] as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($item['due_date']) . "</td>";
        echo "<td><span class='status-badge status-error'>" . htmlspecialchars($item['error']) . "</span></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Display skipped
if (!empty($results['skipped'])) {
    echo "<h2>⏭️ Skipped (Not Yet Due)</h2>";
    echo "<table>";
    echo "<tr><th>Name</th><th>Phone</th><th>Due Date</th><th>Days Until</th><th>Reason</th></tr>";
    foreach ($results['skipped'] as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($item['due_date']) . "</td>";
        echo "<td>" . htmlspecialchars($item['days']) . "</td>";
        echo "<td><span class='status-badge status-skipped'>" . htmlspecialchars($item['reason']) . "</span></td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</div></body></html>";

$conn->close();
?>

