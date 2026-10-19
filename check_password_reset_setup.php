<?php
/**
 * Check if Password Reset is Set Up Correctly
 * Run this to verify your database has the required columns
 */

require_once 'config.php';

$conn = getDBConnection();
$issues = [];
$success = [];

// Check if reset_token column exists
$check1 = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($check1->num_rows > 0) {
    $success[] = "✅ Column 'reset_token' exists";
} else {
    $issues[] = "❌ Column 'reset_token' is MISSING";
}

// Check if reset_token_expires column exists
$check2 = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token_expires'");
if ($check2->num_rows > 0) {
    $success[] = "✅ Column 'reset_token_expires' exists";
} else {
    $issues[] = "❌ Column 'reset_token_expires' is MISSING";
}

// Check if index exists
$check3 = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_reset_token'");
if ($check3->num_rows > 0) {
    $success[] = "✅ Index 'idx_reset_token' exists";
} else {
    $issues[] = "⚠️ Index 'idx_reset_token' is missing (not critical)";
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Setup Check</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>🔍 Password Reset Setup Check</h1>
    
    <?php if (empty($issues)): ?>
        <div class="success">
            <h2>✅ All Good!</h2>
            <p>Your password reset is set up correctly.</p>
            <?php foreach ($success as $msg): ?>
                <p><?php echo $msg; ?></p>
            <?php endforeach; ?>
        </div>
        <a href="userlogin.php" class="btn">Go to Login</a>
    <?php else: ?>
        <div class="error">
            <h2>❌ Setup Required</h2>
            <p>You need to add the password reset columns to your database.</p>
            <?php foreach ($issues as $issue): ?>
                <p><?php echo $issue; ?></p>
            <?php endforeach; ?>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h3>🔧 How to Fix:</h3>
            <p><strong>Option 1 (Easiest):</strong></p>
            <p>Open: <a href="setup_password_reset.php">setup_password_reset.php</a></p>
            <p>Click "Run Database Setup"</p>
            
            <p style="margin-top: 15px;"><strong>Option 2 (Manual):</strong></p>
            <p>1. Open phpMyAdmin</p>
            <p>2. Select <code>daet_market_db</code> database</p>
            <p>3. Go to SQL tab</p>
            <p>4. Run the SQL from <code>add_password_reset_fields.sql</code></p>
        </div>
        
        <a href="setup_password_reset.php" class="btn">Run Setup Now</a>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div style="margin-top: 20px;">
            <?php foreach ($success as $msg): ?>
                <p style="color: #155724;"><?php echo $msg; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>



