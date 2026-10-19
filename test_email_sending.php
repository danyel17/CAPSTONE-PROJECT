<?php
/**
 * Test Email Sending - Debug Tool
 * This script helps test and debug email sending functionality
 */

require_once 'config.php';
require_once 'email_config.php';

// Load PHPMailer
if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

$email_config = require 'email_config.php';
$test_email = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_SANITIZE_EMAIL) : $email_config['smtp_username'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Sending</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #5568d3;
        }
        form {
            margin: 20px 0;
        }
        input[type="email"] {
            padding: 10px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        input[type="submit"] {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Sending Test Tool</h1>
        
        <?php
        // Check configuration
        echo '<div class="section">';
        echo '<h2>1. Configuration Check</h2>';
        
        $config_ok = true;
        $issues = [];
        
        if (empty($email_config['smtp_username'])) {
            $config_ok = false;
            $issues[] = "smtp_username is empty";
        }
        if (empty($email_config['smtp_password'])) {
            $config_ok = false;
            $issues[] = "smtp_password is empty";
        }
        if ($email_config['smtp_password'] === 'your-app-password') {
            $config_ok = false;
            $issues[] = "smtp_password is still set to placeholder 'your-app-password'";
        }
        if (strpos($email_config['smtp_username'], '@gmail.com') === false) {
            $config_ok = false;
            $issues[] = "smtp_username does not appear to be a Gmail address";
        }
        
        if ($config_ok) {
            echo '<div class="success">✓ Configuration looks good!</div>';
            echo '<pre>';
            echo "SMTP Host: " . htmlspecialchars($email_config['smtp_host']) . "\n";
            echo "SMTP Port: " . htmlspecialchars($email_config['smtp_port']) . "\n";
            echo "SMTP Username: " . htmlspecialchars($email_config['smtp_username']) . "\n";
            echo "SMTP Password: " . (empty($email_config['smtp_password']) ? 'NOT SET' : '***SET***') . "\n";
            echo "From Email: " . htmlspecialchars($email_config['from_email']) . "\n";
            echo "From Name: " . htmlspecialchars($email_config['from_name']) . "\n";
            echo '</pre>';
        } else {
            echo '<div class="error">✗ Configuration Issues Found:</div>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li>' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ul>';
            echo '<div class="warning">Please fix these issues in email_config.php</div>';
        }
        echo '</div>';
        
        // Check PHPMailer
        echo '<div class="section">';
        echo '<h2>2. PHPMailer Check</h2>';
        
        $phpmailer_path = __DIR__ . '/PHPMailer/src/PHPMailer.php';
        if (file_exists($phpmailer_path)) {
            echo '<div class="success">✓ PHPMailer files found</div>';
            
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                echo '<div class="success">✓ PHPMailer class is loaded</div>';
            } else {
                echo '<div class="warning">⚠ PHPMailer files exist but class is not loaded</div>';
            }
        } else {
            echo '<div class="error">✗ PHPMailer files not found at: ' . htmlspecialchars($phpmailer_path) . '</div>';
        }
        echo '</div>';
        
        // Test email sending
        if (isset($_GET['send']) && $config_ok) {
            echo '<div class="section">';
            echo '<h2>3. Sending Test Email</h2>';
            
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = $email_config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $email_config['smtp_username'];
                $mail->Password = $email_config['smtp_password'];
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int)$email_config['smtp_port'];
                $mail->CharSet = 'UTF-8';
                $mail->Timeout = 30;
                
                // SMTP Options
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Enable debug output
                $mail->SMTPDebug = 2;
                ob_start();
                
                // Recipients
                $mail->setFrom($email_config['from_email'], $email_config['from_name']);
                $mail->addAddress($test_email);
                $mail->addReplyTo($email_config['from_email'], $email_config['from_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Test Email - Daet Public Market';
                $mail->Body = '<h2>Test Email Successful!</h2><p>If you received this email, your Gmail SMTP configuration is working correctly.</p>';
                $mail->AltBody = 'Test Email Successful! If you received this email, your Gmail SMTP configuration is working correctly.';
                
                // Send email
                if ($mail->send()) {
                    $debug_output = ob_get_clean();
                    echo '<div class="success">✓ Email sent successfully to: ' . htmlspecialchars($test_email) . '</div>';
                    echo '<div class="info">Please check your inbox (and spam folder) for the test email.</div>';
                    if (!empty($debug_output)) {
                        echo '<h3>Debug Output:</h3>';
                        echo '<pre>' . htmlspecialchars($debug_output) . '</pre>';
                    }
                } else {
                    $debug_output = ob_get_clean();
                    echo '<div class="error">✗ Failed to send email</div>';
                    echo '<div class="error">Error: ' . htmlspecialchars($mail->ErrorInfo) . '</div>';
                    if (!empty($debug_output)) {
                        echo '<h3>Debug Output:</h3>';
                        echo '<pre>' . htmlspecialchars($debug_output) . '</pre>';
                    }
                }
            } catch (\Exception $e) {
                $debug_output = ob_get_clean();
                echo '<div class="error">✗ Exception occurred</div>';
                echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                if (!empty($debug_output)) {
                    echo '<h3>Debug Output:</h3>';
                    echo '<pre>' . htmlspecialchars($debug_output) . '</pre>';
                }
            }
            echo '</div>';
        }
        
        // Test form
        if ($config_ok) {
            echo '<div class="section">';
            echo '<h2>4. Send Test Email</h2>';
            echo '<form method="GET">';
            echo '<label>Test Email Address: </label>';
            echo '<input type="email" name="email" value="' . htmlspecialchars($test_email) . '" required>';
            echo '<input type="submit" name="send" value="Send Test Email">';
            echo '</form>';
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h2>5. Quick Links</h2>
            <a href="forgot_password.php" class="btn">Forgot Password Page</a>
            <a href="email_config.php" class="btn">View Email Config</a>
            <a href="setup_email_logs.sql" class="btn">View Email Logs SQL</a>
        </div>
    </div>
</body>
</html>






