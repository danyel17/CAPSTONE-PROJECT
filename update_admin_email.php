<?php
/**
 * Update Admin Email
 * This script updates the admin email from admin@gmail.com to daetpublicmarket@gmail.com
 */

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Admin Email</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .btn { padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
    </style>
</head>
<body>
    <h1>Update Admin Email</h1>
    
    <?php
    if (isset($_POST['update'])) {
        try {
            $conn = getDBConnection();
            
            // Check if old admin exists
            $check_old = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = 'admin@gmail.com' AND user_type = 'admin'");
            $check_old->execute();
            $old_result = $check_old->get_result();
            
            if ($old_result->num_rows > 0) {
                $old_admin = $old_result->fetch_assoc();
                echo '<div class="info">Found admin account with email: ' . htmlspecialchars($old_admin['email']) . '</div>';
                
                // Check if new email already exists
                $check_new = $conn->prepare("SELECT user_id, email FROM users WHERE email = 'daetpublicmarket@gmail.com'");
                $check_new->execute();
                $new_result = $check_new->get_result();
                
                if ($new_result->num_rows > 0) {
                    $existing = $new_result->fetch_assoc();
                    if ($existing['user_id'] == $old_admin['user_id']) {
                        echo '<div class="success">✓ Admin email is already set to daetpublicmarket@gmail.com</div>';
                    } else {
                        echo '<div class="error">✗ Email daetpublicmarket@gmail.com already exists for a different user (ID: ' . $existing['user_id'] . ')</div>';
                        echo '<div class="info">You may need to delete or change the other account first.</div>';
                    }
                } else {
                    // Update the email
                    $update_stmt = $conn->prepare("UPDATE users SET email = 'daetpublicmarket@gmail.com' WHERE email = 'admin@gmail.com' AND user_type = 'admin'");
                    
                    if ($update_stmt->execute()) {
                        $affected = $update_stmt->affected_rows;
                        
                        if ($affected > 0) {
                            echo '<div class="success">✓ Admin email updated successfully!</div>';
                            echo '<div class="info">Admin can now login with: <strong>daetpublicmarket@gmail.com</strong></div>';
                            echo '<div class="info">Password remains the same.</div>';
                            
                            // Verify
                            $verify_stmt = $conn->prepare("SELECT user_id, email, full_name, user_type, status FROM users WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin'");
                            $verify_stmt->execute();
                            $verify_result = $verify_stmt->get_result();
                            $verify_admin = $verify_result->fetch_assoc();
                            
                            if ($verify_admin) {
                                echo '<h2>Updated Admin Account:</h2>';
                                echo '<table>';
                                echo '<tr><th>Field</th><th>Value</th></tr>';
                                echo '<tr><td>User ID</td><td>' . htmlspecialchars($verify_admin['user_id']) . '</td></tr>';
                                echo '<tr><td>Email</td><td>' . htmlspecialchars($verify_admin['email']) . '</td></tr>';
                                echo '<tr><td>Full Name</td><td>' . htmlspecialchars($verify_admin['full_name']) . '</td></tr>';
                                echo '<tr><td>User Type</td><td>' . htmlspecialchars($verify_admin['user_type']) . '</td></tr>';
                                echo '<tr><td>Status</td><td>' . htmlspecialchars($verify_admin['status']) . '</td></tr>';
                                echo '</table>';
                            }
                        } else {
                            echo '<div class="error">✗ No rows were updated. Admin account might not exist or email is already updated.</div>';
                        }
                    } else {
                        echo '<div class="error">✗ Update failed: ' . htmlspecialchars($update_stmt->error) . '</div>';
                    }
                }
            } else {
                // Check if new email already exists as admin
                $check_new = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin'");
                $check_new->execute();
                $new_result = $check_new->get_result();
                
                if ($new_result->num_rows > 0) {
                    $admin = $new_result->fetch_assoc();
                    echo '<div class="success">✓ Admin account already uses daetpublicmarket@gmail.com</div>';
                    echo '<table>';
                    echo '<tr><th>Field</th><th>Value</th></tr>';
                    echo '<tr><td>User ID</td><td>' . htmlspecialchars($admin['user_id']) . '</td></tr>';
                    echo '<tr><td>Email</td><td>' . htmlspecialchars($admin['email']) . '</td></tr>';
                    echo '<tr><td>Full Name</td><td>' . htmlspecialchars($admin['full_name']) . '</td></tr>';
                    echo '</table>';
                } else {
                    echo '<div class="error">✗ No admin account found with email admin@gmail.com</div>';
                    echo '<div class="info">You may need to create the admin account first using seed_default_admin.sql</div>';
                }
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        // Show current status
        try {
            $conn = getDBConnection();
            
            echo '<div class="section"><h2>Current Admin Accounts</h2>';
            
            // Check old email
            $old_stmt = $conn->prepare("SELECT user_id, email, full_name, status FROM users WHERE email = 'admin@gmail.com' AND user_type = 'admin'");
            $old_stmt->execute();
            $old_result = $old_stmt->get_result();
            
            if ($old_result->num_rows > 0) {
                $old = $old_result->fetch_assoc();
                echo '<div class="info">Found admin with old email: ' . htmlspecialchars($old['email']) . '</div>';
            } else {
                echo '<div class="info">No admin found with email: admin@gmail.com</div>';
            }
            
            // Check new email
            $new_stmt = $conn->prepare("SELECT user_id, email, full_name, status FROM users WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin'");
            $new_stmt->execute();
            $new_result = $new_stmt->get_result();
            
            if ($new_result->num_rows > 0) {
                $new = $new_result->fetch_assoc();
                echo '<div class="success">Found admin with new email: ' . htmlspecialchars($new['email']) . '</div>';
            } else {
                echo '<div class="info">No admin found with email: daetpublicmarket@gmail.com</div>';
            }
            
            echo '</div>';
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        echo '<div class="section">';
        echo '<h2>Update Admin Email</h2>';
        echo '<p>This will update the admin email from <strong>admin@gmail.com</strong> to <strong>daetpublicmarket@gmail.com</strong></p>';
        echo '<form method="POST">';
        echo '<button type="submit" name="update" class="btn">Update Admin Email</button>';
        echo '</form>';
        echo '</div>';
    }
    ?>
    
    <div class="section">
        <h2>Quick Actions</h2>
        <a href="adminlogin.php" class="btn">Admin Login</a>
        <a href="forgot_password.php" class="btn">Forgot Password</a>
        <a href="test_admin_forgot_password.php" class="btn">Test Admin Forgot Password</a>
    </div>
</body>
</html>






