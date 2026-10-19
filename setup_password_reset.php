<?php
/**
 * Setup Password Reset - Run this file once to add required database columns
 * 
 * This script will automatically add the reset_token and reset_token_expires
 * columns to your users table.
 * 
 * Just open this file in your browser: http://localhost:8000/setup_password_reset.php
 */

require_once 'config.php';

$success = false;
$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['run'])) {
    try {
        $conn = getDBConnection();
        
        // Try to add reset_token column (ignore error if already exists)
        $sql1 = "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL";
        if ($conn->query($sql1)) {
            $messages[] = "✅ Column 'reset_token' added successfully";
        } else {
            // Check if error is because column already exists
            if (strpos($conn->error, 'Duplicate column') !== false || strpos($conn->error, 'already exists') !== false) {
                $messages[] = "ℹ️ Column 'reset_token' already exists (that's okay!)";
            } else {
                $errors[] = "❌ Failed to add reset_token: " . $conn->error;
            }
        }
        
        // Try to add reset_token_expires column (ignore error if already exists)
        $sql2 = "ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL";
        if ($conn->query($sql2)) {
            $messages[] = "✅ Column 'reset_token_expires' added successfully";
        } else {
            // Check if error is because column already exists
            if (strpos($conn->error, 'Duplicate column') !== false || strpos($conn->error, 'already exists') !== false) {
                $messages[] = "ℹ️ Column 'reset_token_expires' already exists (that's okay!)";
            } else {
                $errors[] = "❌ Failed to add reset_token_expires: " . $conn->error;
            }
        }
        
        // Try to create index (ignore error if already exists)
        $sql3 = "CREATE INDEX idx_reset_token ON users(reset_token)";
        if ($conn->query($sql3)) {
            $messages[] = "✅ Index 'idx_reset_token' created successfully";
        } else {
            // Check if error is because index already exists
            if (strpos($conn->error, 'Duplicate key') !== false || strpos($conn->error, 'already exists') !== false) {
                $messages[] = "ℹ️ Index 'idx_reset_token' already exists (that's okay!)";
            } else {
                // Index error is not critical, just warn
                $messages[] = "⚠️ Index creation skipped: " . $conn->error;
            }
        }
        
        // If we got here, it's a success (even if columns already existed)
        $success = true;
        if (empty($errors)) {
            $messages[] = "🎉 Password reset setup completed successfully!";
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        $errors[] = "❌ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Password Reset - Database Migration</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #FFA07A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #666;
        }
        .info-box li {
            margin: 5px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Password Reset Setup</h1>
        <p class="subtitle">This will add the required database columns for password reset functionality</p>
        
        <?php if ($success): ?>
            <div class="message success">
                <strong>✅ Setup Complete!</strong><br>
                The password reset functionality is now ready to use.
            </div>
            <div class="info-box">
                <h3>Next Steps:</h3>
                <ul>
                    <li>Go to the login page and click "Forgot password?"</li>
                    <li>The reset link will be displayed on screen (no email needed)</li>
                    <li>Copy the link and use it to reset your password</li>
                </ul>
            </div>
            <a href="userlogin.php" class="btn">Go to Login Page</a>
        <?php elseif (!empty($messages) || !empty($errors)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message info"><?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
            
            <?php if (empty($errors)): ?>
                <a href="?run=1" class="btn">Run Setup Again</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="info-box">
                <h3>What this script will do:</h3>
                <ul>
                    <li>Add <code>reset_token</code> column to users table</li>
                    <li>Add <code>reset_token_expires</code> column to users table</li>
                    <li>Create index on <code>reset_token</code> for faster lookups</li>
                </ul>
            </div>
            
            <div class="message info">
                <strong>⚠️ Important:</strong> This script is safe to run multiple times. 
                It will check if columns already exist before adding them.
            </div>
            
            <form method="POST">
                <button type="submit" class="btn">Run Database Setup</button>
            </form>
            
            <p style="margin-top: 20px; color: #666; font-size: 13px;">
                Or click: <a href="?run=1" style="color: #667eea;">Run Setup</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
