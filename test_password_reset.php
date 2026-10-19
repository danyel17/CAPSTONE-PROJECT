<?php
/**
 * Test Password Reset - Debug Tool
 * This script helps verify if password reset is working
 */

require_once 'config.php';

$email = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_SANITIZE_EMAIL) : 'danielmantesprado@gmail.com';
$test_password = isset($_GET['test']) ? true : false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Password Reset</title>
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
        .info {
            background: #d1ecf1;
            color: #0c5460;
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
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #667eea;
            color: white;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Password Reset</h1>
        
        <?php
        try {
            $conn = getDBConnection();
            
            if (!empty($email)) {
                echo '<div class="info"><h2>Checking User: ' . htmlspecialchars($email) . '</h2>';
                
                // Get current user info
                $user_stmt = $conn->prepare("SELECT user_id, email, password_hash, full_name, status FROM users WHERE email = ?");
                $user_stmt->bind_param("s", $email);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                
                if ($user_result->num_rows > 0) {
                    $user = $user_result->fetch_assoc();
                    
                    echo '<div class="success">✓ User found in database</div>';
                    echo '<table>';
                    echo '<tr><th>Field</th><th>Value</th></tr>';
                    echo '<tr><td>User ID</td><td>' . htmlspecialchars($user['user_id']) . '</td></tr>';
                    echo '<tr><td>Email</td><td>' . htmlspecialchars($user['email']) . '</td></tr>';
                    echo '<tr><td>Full Name</td><td>' . htmlspecialchars($user['full_name']) . '</td></tr>';
                    echo '<tr><td>Status</td><td>' . htmlspecialchars($user['status']) . '</td></tr>';
                    echo '<tr><td>Password Hash</td><td><pre>' . htmlspecialchars(substr($user['password_hash'], 0, 50)) . '...</pre></td></tr>';
                    echo '</table>';
                    
                    // Test password update
                    if ($test_password) {
                        echo '<div class="info"><h3>Testing Password Update</h3>';
                        
                        $test_hash = password_hash('TestPassword123!', PASSWORD_DEFAULT);
                        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                        $update_stmt->bind_param("ss", $test_hash, $email);
                        
                        if ($update_stmt->execute()) {
                            $affected = $update_stmt->affected_rows;
                            echo '<div class="success">✓ UPDATE query executed successfully</div>';
                            echo '<p><strong>Affected Rows:</strong> ' . $affected . '</p>';
                            
                            if ($affected > 0) {
                                echo '<div class="success">✓ Password was updated in database</div>';
                                
                                // Verify the update
                                $verify_stmt = $conn->prepare("SELECT password_hash FROM users WHERE email = ?");
                                $verify_stmt->bind_param("s", $email);
                                $verify_stmt->execute();
                                $verify_result = $verify_stmt->get_result();
                                $verify_user = $verify_result->fetch_assoc();
                                
                                if ($verify_user['password_hash'] === $test_hash) {
                                    echo '<div class="success">✓ Verified: Password hash matches in database</div>';
                                } else {
                                    echo '<div class="error">✗ Warning: Password hash does not match after update</div>';
                                }
                                
                                $verify_stmt->close();
                            } else {
                                echo '<div class="error">✗ No rows were affected - password was NOT updated</div>';
                            }
                        } else {
                            echo '<div class="error">✗ UPDATE query failed: ' . htmlspecialchars($update_stmt->error) . '</div>';
                        }
                        
                        $update_stmt->close();
                        echo '</div>';
                    } else {
                        echo '<p><a href="?email=' . urlencode($email) . '&test=1" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Test Password Update</a></p>';
                    }
                    
                } else {
                    echo '<div class="error">✗ User not found in database</div>';
                }
                
                $user_stmt->close();
                echo '</div>';
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <div style="margin-top: 30px;">
            <h2>Quick Actions</h2>
            <a href="reset_password.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Reset Password Page</a>
            <a href="forgot_password.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Forgot Password</a>
        </div>
    </div>
</body>
</html>






