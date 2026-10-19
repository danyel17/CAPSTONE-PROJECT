<?php
/**
 * Fix Admin Account Script
 * This script ensures the admin account (daetpublicmarket@gmail.com) is properly configured
 * Run this once to fix any issues with the admin account
 */

require_once 'config.php';

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed. Please check your database configuration.");
}

echo "<h2>Fixing Admin Account</h2>";

// Check if admin user exists
$check_stmt = $conn->prepare("SELECT user_id, email, user_type, status, email_verified FROM users WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin'");
$check_stmt->execute();
$result = $check_stmt->get_result();
$admin = $result->fetch_assoc();
$check_stmt->close();

if (!$admin) {
    echo "<p style='color: red;'>❌ Admin user not found!</p>";
    echo "<p>Creating admin user...</p>";
    
    // Create admin user with default password "password"
    $password_hash = password_hash('password', PASSWORD_DEFAULT);
    $insert_stmt = $conn->prepare("INSERT INTO users (email, password_hash, full_name, user_type, status, email_verified) VALUES (?, ?, 'Admin', 'admin', 'active', 1)");
    $insert_stmt->bind_param("ss", $email, $password_hash);
    $email = 'daetpublicmarket@gmail.com';
    
    if ($insert_stmt->execute()) {
        echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
        echo "<p><strong>Email:</strong> daetpublicmarket@gmail.com</p>";
        echo "<p><strong>Password:</strong> password</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create admin user: " . $insert_stmt->error . "</p>";
    }
    $insert_stmt->close();
} else {
    echo "<p style='color: green;'>✅ Admin user found!</p>";
    echo "<p><strong>User ID:</strong> " . $admin['user_id'] . "</p>";
    echo "<p><strong>Email:</strong> " . $admin['email'] . "</p>";
    echo "<p><strong>Status:</strong> " . $admin['status'] . "</p>";
    echo "<p><strong>Email Verified:</strong> " . ($admin['email_verified'] ? 'Yes' : 'No') . "</p>";
    
    // Fix any issues
    $fixes = [];
    
    // Ensure status is active
    if ($admin['status'] !== 'active') {
        $update_stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $update_stmt->bind_param("i", $admin['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        $fixes[] = "Status set to 'active'";
    }
    
    // Ensure email_verified is 1
    if (!$admin['email_verified']) {
        $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
        $update_stmt->bind_param("i", $admin['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        $fixes[] = "Email verified set to 1";
    }
    
    if (!empty($fixes)) {
        echo "<p style='color: blue;'>🔧 Applied fixes:</p><ul>";
        foreach ($fixes as $fix) {
            echo "<li>" . htmlspecialchars($fix) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✅ No fixes needed - account is properly configured!</p>";
    }
    
    // Option to reset password
    echo "<hr>";
    echo "<h3>Reset Admin Password</h3>";
    echo "<form method='POST' style='margin-top: 20px;'>";
    echo "<p>Enter new password (must be at least 8 characters with uppercase, lowercase, and number):</p>";
    echo "<input type='password' name='new_password' placeholder='New Password' required style='padding: 10px; width: 300px; margin-bottom: 10px;'><br>";
    echo "<input type='password' name='confirm_password' placeholder='Confirm Password' required style='padding: 10px; width: 300px; margin-bottom: 10px;'><br>";
    echo "<button type='submit' name='reset_password' style='padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;'>Reset Password</button>";
    echo "</form>";
    
    if (isset($_POST['reset_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            echo "<p style='color: red;'>❌ Passwords don't match!</p>";
        } elseif (strlen($new_password) < 8) {
            echo "<p style='color: red;'>❌ Password must be at least 8 characters long!</p>";
        } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            echo "<p style='color: red;'>❌ Password must contain uppercase, lowercase, and number!</p>";
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_stmt->bind_param("si", $password_hash, $admin['user_id']);
            
            if ($update_stmt->execute()) {
                echo "<p style='color: green;'>✅ Password reset successfully!</p>";
                echo "<p>You can now login with:</p>";
                echo "<p><strong>Email:</strong> daetpublicmarket@gmail.com</p>";
                echo "<p><strong>Password:</strong> (the password you just set)</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to reset password: " . $update_stmt->error . "</p>";
            }
            $update_stmt->close();
        }
    }
}

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 50px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h2, h3 {
        color: #333;
    }
    p {
        margin: 10px 0;
    }
</style>



