<?php
/**
 * Test Admin Forgot Password
 * Verify that daetpublicmarket@gmail.com can use forgot password
 */

require_once 'config.php';

$email = 'daetpublicmarket@gmail.com';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Admin Forgot Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <h1>Test Admin Forgot Password</h1>
    
    <?php
    try {
        $conn = getDBConnection();
        
        // Check if admin account exists
        echo '<div class="section"><h2>1. Check Admin Account</h2>';
        $stmt = $conn->prepare("SELECT user_id, email, full_name, user_type, status, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            echo '<div class="success">✓ Admin account found</div>';
            echo '<table>';
            echo '<tr><th>Field</th><th>Value</th></tr>';
            echo '<tr><td>User ID</td><td>' . htmlspecialchars($admin['user_id']) . '</td></tr>';
            echo '<tr><td>Email</td><td>' . htmlspecialchars($admin['email']) . '</td></tr>';
            echo '<tr><td>Full Name</td><td>' . htmlspecialchars($admin['full_name']) . '</td></tr>';
            echo '<tr><td>User Type</td><td>' . htmlspecialchars($admin['user_type']) . '</td></tr>';
            echo '<tr><td>Status</td><td>' . htmlspecialchars($admin['status']) . '</td></tr>';
            echo '<tr><td>Email Verified</td><td>' . ($admin['email_verified'] ? 'Yes' : 'No') . '</td></tr>';
            echo '</table>';
            
            // Check if account can use forgot password
            if ($admin['status'] === 'active') {
                echo '<div class="success">✓ Account status is "active" - can use forgot password</div>';
            } else {
                echo '<div class="error">✗ Account status is "' . htmlspecialchars($admin['status']) . '" - cannot use forgot password</div>';
            }
        } else {
            echo '<div class="error">✗ Admin account not found!</div>';
        }
        $stmt->close();
        echo '</div>';
        
        // Check email configuration
        echo '<div class="section"><h2>2. Email Configuration</h2>';
        $email_config = require 'email_config.php';
        echo '<div class="info">';
        echo '<p><strong>SMTP Username:</strong> ' . htmlspecialchars($email_config['smtp_username']) . '</p>';
        echo '<p><strong>From Email:</strong> ' . htmlspecialchars($email_config['from_email']) . '</p>';
        echo '<p><strong>SMTP Password:</strong> ' . (empty($email_config['smtp_password']) ? 'NOT SET' : '***SET***') . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Test forgot password query
        echo '<div class="section"><h2>3. Test Forgot Password Query</h2>';
        $test_stmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = ? AND status = 'active'");
        $test_stmt->bind_param("s", $email);
        $test_stmt->execute();
        $test_result = $test_stmt->get_result();
        
        if ($test_result->num_rows > 0) {
            $test_user = $test_result->fetch_assoc();
            echo '<div class="success">✓ Query would find admin account for forgot password</div>';
            echo '<p><strong>User ID:</strong> ' . htmlspecialchars($test_user['user_id']) . '</p>';
            echo '<p><strong>Email:</strong> ' . htmlspecialchars($test_user['email']) . '</p>';
            echo '<p><strong>Full Name:</strong> ' . htmlspecialchars($test_user['full_name']) . '</p>';
        } else {
            echo '<div class="error">✗ Query would NOT find admin account (status might not be active)</div>';
        }
        $test_stmt->close();
        echo '</div>';
        
        $conn->close();
        
    } catch (Exception $e) {
        echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    
    <div class="section">
        <h2>4. Quick Actions</h2>
        <p><a href="forgot_password.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Test Forgot Password</a></p>
        <p><a href="test_email_sending.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Test Email Sending</a></p>
    </div>
</body>
</html>

