<?php
/**
 * Change Admin Email - Simple Script
 * Changes admin@gmail.com to daetpublicmarket@gmail.com in the database
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Change Admin Email</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Change Admin Email</h1>";

try {
    $conn = getDBConnection();
    
    // Check current admin
    $check = $conn->query("SELECT user_id, email, full_name, user_type FROM users WHERE user_type = 'admin'");
    
    echo "<h2>Current Admin Accounts:</h2>";
    if ($check->num_rows > 0) {
        echo "<ul>";
        while ($row = $check->fetch_assoc()) {
            echo "<li>ID: {$row['user_id']}, Email: <strong>{$row['email']}</strong>, Name: {$row['full_name']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<div class='error'>No admin accounts found!</div>";
    }
    
    // Update admin email
    echo "<h2>Updating Admin Email...</h2>";
    
    $update = $conn->prepare("UPDATE users SET email = 'daetpublicmarket@gmail.com' WHERE email = 'admin@gmail.com' AND user_type = 'admin'");
    
    if ($update->execute()) {
        $affected = $update->affected_rows;
        
        if ($affected > 0) {
            echo "<div class='success'>✓ Successfully updated $affected admin account(s)!</div>";
            echo "<div class='info'>Admin email changed from admin@gmail.com to daetpublicmarket@gmail.com</div>";
            
            // Verify
            $verify = $conn->query("SELECT user_id, email, full_name, user_type, status FROM users WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin'");
            if ($verify->num_rows > 0) {
                $admin = $verify->fetch_assoc();
                echo "<div class='success'>";
                echo "<h3>Updated Admin Account:</h3>";
                echo "<p><strong>User ID:</strong> {$admin['user_id']}</p>";
                echo "<p><strong>Email:</strong> {$admin['email']}</p>";
                echo "<p><strong>Full Name:</strong> {$admin['full_name']}</p>";
                echo "<p><strong>Status:</strong> {$admin['status']}</p>";
                echo "</div>";
            }
        } else {
            // Check if already updated
            $check_new = $conn->query("SELECT user_id, email FROM users WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin'");
            if ($check_new->num_rows > 0) {
                echo "<div class='info'>Admin email is already set to daetpublicmarket@gmail.com</div>";
            } else {
                echo "<div class='error'>No admin account found with email admin@gmail.com to update.</div>";
            }
        }
    } else {
        echo "<div class='error'>Update failed: " . htmlspecialchars($conn->error) . "</div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top: 30px;'>
    <p><strong>Next Steps:</strong></p>
    <ul>
        <li>Admin can now login with: <strong>daetpublicmarket@gmail.com</strong></li>
        <li>Password remains the same</li>
        <li>Forgot password will send emails to daetpublicmarket@gmail.com</li>
    </ul>
    <p><a href='adminlogin.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a></p>
</div>
</body>
</html>";
?>






