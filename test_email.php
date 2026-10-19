<?php
/**
 * Test Email Configuration
 * 
 * This script helps you test if your Gmail configuration is working.
 * Run this file in your browser to test email sending.
 * 
 * IMPORTANT: Configure email_config.php first!
 */

require_once 'config.php';

// Check if email config exists
$email_config_file = __DIR__ . '/email_config.php';
if (!file_exists($email_config_file)) {
    die("❌ email_config.php not found. Please create it first.");
}

$email_config = require $email_config_file;

// Check if configured
if ($email_config['smtp_username'] === 'your-email@gmail.com' || 
    $email_config['smtp_password'] === 'your-app-password') {
    die("❌ Please configure your Gmail credentials in email_config.php first!");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email Configuration</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .test-form { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #5568d3; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>📧 Test Email Configuration</h1>
    
    <div class="info">
        <strong>Current Configuration:</strong><br>
        SMTP Host: <?php echo htmlspecialchars($email_config['smtp_host']); ?><br>
        SMTP Port: <?php echo htmlspecialchars($email_config['smtp_port']); ?><br>
        From Email: <?php echo htmlspecialchars($email_config['from_email']); ?><br>
        SMTP Username: <?php echo htmlspecialchars($email_config['smtp_username']); ?>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $test_email = $_POST['test_email'] ?? '';
        
        if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            echo '<div class="error">❌ Please enter a valid email address</div>';
        } else {
            // Test sending email
            $result = testEmailSending($test_email, $email_config);
            
            if ($result['success']) {
                echo '<div class="success">✅ SUCCESS! Email sent to ' . htmlspecialchars($test_email) . '<br>';
                echo 'Check your inbox (and spam folder) for the test email.</div>';
            } else {
                echo '<div class="error">❌ FAILED: ' . htmlspecialchars($result['message']) . '</div>';
            }
        }
    }
    ?>

    <div class="test-form">
        <h2>Send Test Email</h2>
        <form method="POST">
            <label>Enter your email address to receive a test email:</label>
            <input type="email" name="test_email" placeholder="your-email@gmail.com" required>
            <button type="submit">Send Test Email</button>
        </form>
    </div>

    <div class="info">
        <strong>📝 Instructions:</strong>
        <ol>
            <li>Enter your email address above</li>
            <li>Click "Send Test Email"</li>
            <li>Check your inbox (and spam folder)</li>
            <li>If you receive the email, your configuration is working! ✅</li>
        </ol>
    </div>
</body>
</html>

<?php
function testEmailSending($to_email, $config) {
    $subject = 'Test Email - Daet Public Market';
    $message = "
    <html>
    <body>
        <h2>Test Email Successful! ✅</h2>
        <p>If you received this email, your Gmail SMTP configuration is working correctly.</p>
        <p>Your password reset emails will now be sent to users' Gmail inboxes.</p>
        <hr>
        <p><small>This is a test email from Market Clearance System</small></p>
    </body>
    </html>
    ";
    
    // Try PHPMailer first
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to_email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            if ($mail->send()) {
                return ['success' => true, 'message' => 'Email sent successfully via PHPMailer'];
            } else {
                return ['success' => false, 'message' => 'PHPMailer failed: ' . $mail->ErrorInfo];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'PHPMailer Exception: ' . $e->getMessage()];
        }
    } else {
        // Fallback to mail()
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $config['from_name'] . " <" . $config['from_email'] . ">" . "\r\n";
        
        if (mail($to_email, $subject, $message, $headers)) {
            return ['success' => true, 'message' => 'Email sent via PHP mail() (may go to spam)'];
        } else {
            return ['success' => false, 'message' => 'PHP mail() function failed'];
        }
    }
}
?>



