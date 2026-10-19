<?php
/**
 * Diagnose Password Reset Issue
 * This will help identify why password reset isn't working
 */

require_once 'config.php';
session_start();

$email = 'danielmantesprado@gmail.com';
$test_password = 'TestPassword123!';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Diagnose Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Password Reset Diagnosis</h1>
    
    <?php
    try {
        $conn = getDBConnection();
        
        // 1. Check database schema
        echo '<div class="section"><h2>1. Database Schema Check</h2>';
        $result = $conn->query("DESCRIBE users");
        echo '<table><tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th></tr>';
        $has_password_hash = false;
        $has_password = false;
        while($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['Field']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
            echo '</tr>';
            if ($row['Field'] === 'password_hash') $has_password_hash = true;
            if ($row['Field'] === 'password') $has_password = true;
        }
        echo '</table>';
        
        if ($has_password_hash) {
            echo '<div class="success">✓ Column "password_hash" exists</div>';
        } else {
            echo '<div class="error">✗ Column "password_hash" does NOT exist</div>';
        }
        
        if ($has_password) {
            echo '<div class="info">ℹ Column "password" also exists</div>';
        }
        echo '</div>';
        
        // 2. Check user exists
        echo '<div class="section"><h2>2. User Check</h2>';
        $user_stmt = $conn->prepare("SELECT user_id, email, password_hash, full_name FROM users WHERE email = ?");
        $user_stmt->bind_param("s", $email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            echo '<div class="success">✓ User found: ' . htmlspecialchars($user['email']) . '</div>';
            echo '<p><strong>User ID:</strong> ' . htmlspecialchars($user['user_id']) . '</p>';
            echo '<p><strong>Full Name:</strong> ' . htmlspecialchars($user['full_name']) . '</p>';
            echo '<p><strong>Current Password Hash:</strong> <pre>' . htmlspecialchars(substr($user['password_hash'], 0, 60)) . '...</pre></p>';
        } else {
            echo '<div class="error">✗ User not found!</div>';
        }
        $user_stmt->close();
        echo '</div>';
        
        // 3. Test password update
        echo '<div class="section"><h2>3. Test Password Update</h2>';
        $test_hash = password_hash($test_password, PASSWORD_DEFAULT);
        
        // Try password_hash column
        if ($has_password_hash) {
            echo '<h3>Testing "password_hash" column:</h3>';
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $test_hash, $email);
            
            if ($update_stmt->execute()) {
                $affected = $update_stmt->affected_rows;
                echo '<div class="' . ($affected > 0 ? 'success' : 'error') . '">';
                echo ($affected > 0 ? '✓' : '✗') . ' UPDATE executed. Affected rows: ' . $affected;
                echo '</div>';
                
                if ($affected > 0) {
                    // Verify
                    $verify_stmt = $conn->prepare("SELECT password_hash FROM users WHERE email = ?");
                    $verify_stmt->bind_param("s", $email);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $verify_data = $verify_result->fetch_assoc();
                    
                    if (password_verify($test_password, $verify_data['password_hash'])) {
                        echo '<div class="success">✓ Password verification successful!</div>';
                    } else {
                        echo '<div class="error">✗ Password verification failed!</div>';
                    }
                    $verify_stmt->close();
                }
            } else {
                echo '<div class="error">✗ UPDATE failed: ' . htmlspecialchars($update_stmt->error) . '</div>';
            }
            $update_stmt->close();
        }
        
        // Try password column if it exists
        if ($has_password && !$has_password_hash) {
            echo '<h3>Testing "password" column:</h3>';
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $test_hash, $email);
            
            if ($update_stmt->execute()) {
                $affected = $update_stmt->affected_rows;
                echo '<div class="' . ($affected > 0 ? 'success' : 'error') . '">';
                echo ($affected > 0 ? '✓' : '✗') . ' UPDATE executed. Affected rows: ' . $affected;
                echo '</div>';
            } else {
                echo '<div class="error">✗ UPDATE failed: ' . htmlspecialchars($update_stmt->error) . '</div>';
            }
            $update_stmt->close();
        }
        echo '</div>';
        
        // 4. Check session
        echo '<div class="section"><h2>4. Session Check</h2>';
        if (isset($_SESSION['reset_email'])) {
            echo '<div class="info">Session reset_email: ' . htmlspecialchars($_SESSION['reset_email']) . '</div>';
        } else {
            echo '<div class="error">✗ Session reset_email is NOT set</div>';
        }
        
        if (isset($_SESSION['code_verified'])) {
            echo '<div class="info">Session code_verified: ' . ($_SESSION['code_verified'] ? 'true' : 'false') . '</div>';
        } else {
            echo '<div class="error">✗ Session code_verified is NOT set</div>';
        }
        echo '</div>';
        
        $conn->close();
        
    } catch (Exception $e) {
        echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    
    <div class="section">
        <h2>5. Quick Actions</h2>
        <p><a href="check_db_schema.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Check Database Schema</a></p>
        <p><a href="reset_password.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Reset Password Page</a></p>
        <p><a href="forgot_password.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Forgot Password</a></p>
    </div>
</body>
</html>






