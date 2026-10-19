<?php
/**
 * Test Forgot Password Send Code
 * Quick test to see if the send code functionality works
 */

require_once 'config.php';
require_once 'forgot_password.php';

$test_email = 'daetpublicmarket@gmail.com';

echo "<h2>Testing Send Code Functionality</h2>";

// Check if user exists
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = ? AND status = 'active'");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<p>✓ User found: " . htmlspecialchars($user['email']) . "</p>";
    
    // Generate test code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    echo "<p>Test code generated: $code</p>";
    
    // Test email sending
    echo "<h3>Testing Email Sending...</h3>";
    $email_result = sendPasswordResetCode($test_email, $user['full_name'], $code);
    
    if ($email_result['success']) {
        echo "<p style='color: green;'>✓ Email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Email failed: " . htmlspecialchars($email_result['error'] ?? 'Unknown error') . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ User not found or not active</p>";
}

$stmt->close();
$conn->close();
?>






