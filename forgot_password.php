<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Load PHPMailer classes (at top level)
if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

// Allow access even when logged in - user may want to reset password via email flow

$error_message = '';
$success_message = '';

// Create password_reset_codes table if it doesn't exist - STRONG BACKEND
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed. Please check your database configuration.");
}

// Create table with proper error handling
$create_table_sql = "CREATE TABLE IF NOT EXISTS password_reset_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_code (code),
    INDEX idx_expires (expires_at),
    INDEX idx_email_expires (email, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_table_sql)) {
    error_log("Failed to create password_reset_codes table: " . $conn->error);
    // Try to continue anyway - table might already exist
}

// Clean up expired codes (older than 1 hour) - safe query, no user input
$conn->query("DELETE FROM password_reset_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

// Handle email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_code') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    // Validate email
    if (empty($email)) {
        $error_message = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email exists (admin users can reset password too)
        // Use LOWER() for case-insensitive comparison and allow admin users even if status is not active
        $stmt = $conn->prepare("SELECT user_id, email, full_name, user_type, status FROM users WHERE LOWER(email) = LOWER(?) AND (status = 'active' OR user_type = 'admin')");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Additional check: maybe the user exists but status is wrong
            $check_stmt = $conn->prepare("SELECT user_id, email, user_type, status FROM users WHERE LOWER(email) = LOWER(?)");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_user = $check_result->fetch_assoc();
                // User exists but status might be wrong - fix it for admin users
                if ($check_user['user_type'] === 'admin') {
                    $fix_stmt = $conn->prepare("UPDATE users SET status = 'active', email_verified = 1 WHERE user_id = ?");
                    $fix_stmt->bind_param("i", $check_user['user_id']);
                    $fix_stmt->execute();
                    $fix_stmt->close();
                    
                    // Retry the query
                    $stmt->execute();
                    $result = $stmt->get_result();
                }
            }
            $check_stmt->close();
        
        if ($result->num_rows === 0) {
            $error_message = "Email not found. Please check your email address or sign up for a new account.";
            }
        }
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Rate limiting: Check if user has requested too many codes recently (max 3 per hour)
            $rate_limit_stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM password_reset_codes 
                WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND used_at IS NULL
            ");
            $rate_limit_stmt->bind_param("s", $email);
            $rate_limit_stmt->execute();
            $rate_result = $rate_limit_stmt->get_result();
            $rate_data = $rate_result->fetch_assoc();
            $rate_limit_stmt->close();
            
            if ($rate_data['count'] >= 3) {
                $error_message = "Too many code requests. Please wait before requesting another code.";
            } else {
                // Generate 6-digit code
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Invalidate previous unused codes for this email (using prepared statement)
                $invalidate_stmt = $conn->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL");
                $invalidate_stmt->bind_param("s", $email);
                $invalidate_stmt->execute();
                $invalidate_stmt->close();
                
                // Store code in database - Use MySQL's DATE_ADD to avoid timezone issues
                // This ensures expires_at is calculated using the same timezone as NOW()
                $insert_stmt = $conn->prepare("INSERT INTO password_reset_codes (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                if (!$insert_stmt) {
                    error_log("Failed to prepare INSERT statement: " . $conn->error);
                    $error_message = "Database error. Please try again later.";
                } else {
                    $insert_stmt->bind_param("ss", $email, $code);
                    if (!$insert_stmt->execute()) {
                        error_log("Failed to insert reset code: " . $insert_stmt->error);
                        $error_message = "Failed to generate reset code. Please try again.";
                    } else {
                        // Code stored successfully - proceed with email
                        $insert_stmt->close();
                        
                        // Send email with code - MUST send to real Gmail account
                        $email_result = sendPasswordResetCode($email, $user['full_name'], $code);
                        
                        if ($email_result['success']) {
                            // Email sent successfully - proceed to verification
                            $_SESSION['reset_email'] = $email;
                            $success_message = "Verification code has been sent to your email address. Please check your inbox.";
                            // Redirect after showing success message
                            header("Location: verify_code.php");
                            exit();
                        } else {
                            // Email failed - show error with helpful message
                            $error_message = $email_result['error'] ?? "Failed to send email. Please try again later or contact administrator.";
                            error_log("CRITICAL: Email sending failed for $email. Error: " . ($email_result['error'] ?? 'Unknown error'));
                            
                            // Delete the code from database since email failed
                            $delete_stmt = $conn->prepare("DELETE FROM password_reset_codes WHERE email = ? AND code = ?");
                            $delete_stmt->bind_param("ss", $email, $code);
                            $delete_stmt->execute();
                            $delete_stmt->close();
                        }
                    }
                }
            }
        }
        
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Daet Public Market</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
          --indigo: #667eea;
          --indigo-dark: #5568d3;
          --text: #1e293b;
          --muted: #64748b;
          --border: #e2e8f0;
          --error: #e74c3c;
          --success: #27ae60;
        }
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
          font-family: "Inter", "Segoe UI", sans-serif;
        }
        body { min-height: 100vh; background: #f5f7fb; color: var(--text); }

        .auth-page {
          display: grid;
          grid-template-columns: minmax(380px, 46%) 1fr;
          min-height: 100vh;
        }

        /* ── Left dark panel ── */
        .auth-left {
          position: relative;
          background: url('assets/imgs/palengke.jpg') center / cover no-repeat;
          display: flex;
        }
        .auth-left::before {
          content: "";
          position: absolute;
          inset: 0;
          background: linear-gradient(180deg, rgba(15,23,42,0.86), rgba(15,23,42,0.80));
        }
        .auth-left-content {
          position: relative;
          z-index: 1;
          display: flex;
          flex-direction: column;
          padding: 32px 38px;
          color: #fff;
          width: 100%;
        }
        .al-brand { display: flex; align-items: center; gap: 12px; }
        .al-brand-icon {
          width: 42px; height: 42px; border-radius: 10px;
          background: rgba(255,255,255,0.14);
          display: flex; align-items: center; justify-content: center;
          font-size: 1rem; flex-shrink: 0;
        }
        .al-brand strong { font-size: 0.95rem; font-weight: 800; display: block; letter-spacing: 0.05em; }
        .al-brand small { font-size: 0.68rem; opacity: 0.8; }
        .al-main { margin: auto 0; padding: 40px 0; }
        .al-shield {
          width: 46px; height: 46px; border-radius: 12px;
          background: rgba(91,92,226,0.5);
          display: flex; align-items: center; justify-content: center;
          font-size: 1.1rem; margin-bottom: 18px;
        }
        .al-main h1 { font-size: 2.5rem; font-weight: 800; line-height: 1.12; }
        .al-main h1 span { color: #7ea4f8; }
        .al-main > p { margin-top: 14px; font-size: 0.9rem; line-height: 1.65; opacity: 0.85; max-width: 400px; }
        .al-features { margin-top: 28px; display: flex; flex-direction: column; gap: 12px; max-width: 430px; }
        .al-feature {
          display: flex; align-items: center; gap: 13px;
          background: rgba(255,255,255,0.07);
          border: 1px solid rgba(255,255,255,0.14);
          border-radius: 12px;
          padding: 12px 16px;
        }
        .alf-icon {
          width: 36px; height: 36px; border-radius: 9px;
          background: rgba(91,92,226,0.45);
          display: flex; align-items: center; justify-content: center;
          font-size: 0.85rem; flex-shrink: 0;
        }
        .al-feature strong { display: block; font-size: 0.8rem; font-weight: 700; }
        .al-feature span { font-size: 0.7rem; opacity: 0.75; }
        .al-footer { font-size: 0.7rem; opacity: 0.6; }

        /* ── Right panel ── */
        .auth-right {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 40px 24px;
        }

        /* Stepper */
        .stepper { display: flex; align-items: flex-start; margin-bottom: 30px; }
        .step { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 64px; }
        .step-circle {
          width: 34px; height: 34px; border-radius: 50%;
          background: #e2e8f0; color: #94a3b8;
          font-weight: 700; font-size: 0.85rem;
          display: flex; align-items: center; justify-content: center;
        }
        .step span { font-size: 0.68rem; font-weight: 600; color: #94a3b8; }
        .step.active .step-circle { background: var(--indigo); color: #fff; box-shadow: 0 0 0 5px rgba(102,126,234,0.18); }
        .step.active span { color: var(--indigo); }
        .step.done .step-circle { background: var(--indigo); color: #fff; }
        .step.done span { color: var(--indigo); }
        .step-line { flex: 0 0 46px; height: 2px; background: #e2e8f0; margin-top: 17px; }
        .step-line.done { background: var(--indigo); }

        /* Card */
        .form-container {
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 16px;
          padding: 34px 32px 30px;
          width: 100%;
          max-width: 440px;
          box-shadow: 0 10px 32px rgba(15,23,42,0.07);
          text-align: center;
        }
        .card-icon {
          width: 56px; height: 56px; border-radius: 14px;
          display: flex; align-items: center; justify-content: center;
          font-size: 1.3rem; margin: 0 auto 16px;
        }
        .card-icon--blue { background: #eef1fd; color: var(--indigo); }
        .form-container h1 {
          font-size: 1.35rem;
          font-weight: 800;
          color: var(--text);
          margin-bottom: 6px;
        }
        .card-sub { color: var(--muted); font-size: 0.84rem; line-height: 1.55; margin-bottom: 20px; }

        .alert {
          padding: 12px 16px;
          border-radius: 10px;
          margin-bottom: 16px;
          font-size: 0.84rem;
          text-align: left;
        }
        .alert-error { background-color: #fee; color: var(--error); border: 1px solid #fcc; }
        .alert-success { background-color: #efe; color: var(--success); border: 1px solid #cfc; }

        .form-box { display: flex; flex-direction: column; gap: 16px; text-align: left; }
        .field-group label {
          display: block;
          font-size: 0.76rem;
          font-weight: 700;
          margin-bottom: 6px;
        }
        .field { position: relative; }
        .field > i {
          position: absolute;
          left: 14px; top: 50%;
          transform: translateY(-50%);
          color: var(--indigo);
          font-size: 0.85rem;
          pointer-events: none;
        }
        .input-field {
          width: 100%;
          height: 46px;
          padding: 0 14px 0 40px;
          border: 1.5px solid var(--border);
          border-radius: 10px;
          background: #fff;
          color: var(--text);
          font-size: 0.9rem;
          font-weight: 500;
          outline: none;
          transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .input-field::placeholder { color: #a3b0c2; font-weight: 400; }
        .input-field:focus {
          border-color: var(--indigo);
          box-shadow: 0 0 0 4px rgba(102,126,234,0.12);
        }

        .notice {
          display: flex;
          align-items: flex-start;
          gap: 9px;
          background: #fff7ed;
          border: 1px solid #fed7aa;
          color: #9a3412;
          border-radius: 10px;
          padding: 12px 14px;
          font-size: 0.76rem;
          line-height: 1.5;
          text-align: left;
        }
        .notice i { margin-top: 2px; flex-shrink: 0; }

        .form-btn--submit {
          width: 100%;
          height: 46px;
          border: none;
          outline: none;
          border-radius: 10px;
          cursor: pointer;
          background: var(--indigo);
          color: #fff;
          font-size: 0.92rem;
          font-weight: 700;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
          box-shadow: 0 6px 16px rgba(102,126,234,0.3);
        }
        .form-btn--submit:hover {
          background: var(--indigo-dark);
          transform: translateY(-1px);
          box-shadow: 0 9px 22px rgba(102,126,234,0.38);
        }
        .form-btn--submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .back-link {
          margin-top: 22px;
          font-size: 0.82rem;
          font-weight: 600;
          color: var(--muted);
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          gap: 7px;
          transition: color 0.2s ease;
        }
        .back-link:hover { color: var(--indigo); }
        .back-link .accent { color: var(--indigo); }

        @media (max-width: 900px) {
          .auth-page { grid-template-columns: 1fr; }
          .auth-left { min-height: 240px; }
          .al-main { padding: 22px 0; }
          .al-main h1 { font-size: 1.7rem; }
          .al-features { display: none; }
          .auth-right { padding: 30px 16px 44px; }
        }
    </style>
</head>
<body>
    <div class="auth-page">

        <!-- Left: Account Security panel -->
        <aside class="auth-left">
            <div class="auth-left-content">
                <div class="al-brand">
                    <div class="al-brand-icon"><i class="fas fa-store"></i></div>
                    <div><strong>MCS</strong><small>Market Clearance System</small></div>
                </div>
                <div class="al-main">
                    <div class="al-shield"><i class="fas fa-shield-halved"></i></div>
                    <h1>Account<br><span>Security</span></h1>
                    <p>Reset your password securely through our verified multi-step process. Your account security is our top priority.</p>
                    <div class="al-features">
                        <div class="al-feature">
                            <div class="alf-icon"><i class="fas fa-envelope"></i></div>
                            <div><strong>Email Verification</strong><span>Codes sent to your registered address only</span></div>
                        </div>
                        <div class="al-feature">
                            <div class="alf-icon"><i class="fas fa-clock"></i></div>
                            <div><strong>Time-Limited Codes</strong><span>10-minute expiry for enhanced security</span></div>
                        </div>
                        <div class="al-feature">
                            <div class="alf-icon"><i class="fas fa-lock"></i></div>
                            <div><strong>Strong Password Policy</strong><span>so your account is secured</span></div>
                        </div>
                    </div>
                </div>
                <div class="al-footer">&copy; <?php echo date('Y'); ?> Daet Public Market &mdash; Market Clearance System</div>
            </div>
        </aside>

        <!-- Right: form -->
        <main class="auth-right">
            <div class="stepper">
                <div class="step active"><div class="step-circle">1</div><span>Email</span></div>
                <div class="step-line"></div>
                <div class="step"><div class="step-circle">2</div><span>Verify</span></div>
                <div class="step-line"></div>
                <div class="step"><div class="step-circle">3</div><span>Reset</span></div>
            </div>

            <div class="form-container">
                <div class="card-icon card-icon--blue"><i class="fas fa-envelope-open-text"></i></div>
                <h1>Reset Your Password</h1>
                <p class="card-sub">No worries! Enter your registered email and we'll send you a secure verification code.</p>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form class="form-box" method="POST" action="forgot_password.php">
                    <input type="hidden" name="action" value="send_code">
                    <div class="field-group">
                        <label for="email">Email Address</label>
                        <div class="field">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="input-field" placeholder="you@gmail.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="notice">
                        <i class="fas fa-shield-halved"></i>
                        <span>For security, the code expires in <strong>10 minutes</strong>. Make sure to check your spam folder if you don't see the email.</span>
                    </div>

                    <button type="submit" class="form-btn--submit">
                        <i class="fas fa-paper-plane"></i> Send Verification Code
                    </button>
                </form>
            </div>

            <a href="index.php#user-portal" class="back-link">
                <i class="fas fa-arrow-left"></i> Remember your password? <span class="accent">Sign in</span>
            </a>
        </main>
    </div>
    <?php
    $cs_contact_href = 'index.php#contact';
    $cs_announcement_href = 'index.php#announcement';
    include __DIR__ . '/includes/customer_support_widget.php';
    ?>
</body>
</html>

<?php
/**
 * Send password reset code email - STRONG BACKEND with database logging and error handling
 * Returns array with 'success' and 'error' keys
 */
function sendPasswordResetCode($email, $name, $code) {
    $error_details = [];
    $log_id = null;
    
    // Initialize database connection for logging
    $conn = null;
    try {
        $conn = getDBConnection();
        
        // Create email_logs table if it doesn't exist
        $create_logs_table = "CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255) NULL,
            email_type VARCHAR(50) NOT NULL DEFAULT 'password_reset',
            subject VARCHAR(255) NOT NULL,
            status ENUM('pending', 'sent', 'failed', 'error') NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            error_details TEXT NULL,
            smtp_host VARCHAR(255) NULL,
            smtp_username VARCHAR(255) NULL,
            attempts INT DEFAULT 1,
            sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recipient (recipient_email),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($create_logs_table);
        
        // Log email attempt
        $subject = 'Password Reset Code - Daet Public Market';
        $log_stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, recipient_name, email_type, subject, status) VALUES (?, ?, 'password_reset', ?, 'pending')");
        if ($log_stmt) {
            $log_stmt->bind_param("sss", $email, $name, $subject);
            $log_stmt->execute();
            $log_id = $conn->insert_id;
            $log_stmt->close();
        }
    } catch (\Exception $e) {
        error_log("Warning: Could not create email log: " . $e->getMessage());
    }
    
    // Load email config
    $email_config_file = __DIR__ . '/email_config.php';
    if (file_exists($email_config_file)) {
        $email_config = require $email_config_file;
        $smtp_host = $email_config['smtp_host'] ?? 'smtp.gmail.com';
        $smtp_port = $email_config['smtp_port'] ?? 587;
        $smtp_username = $email_config['smtp_username'] ?? '';
        $smtp_password = $email_config['smtp_password'] ?? '';
        $from_email = $email_config['from_email'] ?? $smtp_username;
        $from_name = $email_config['from_name'] ?? 'Daet Public Market Office';
    } else {
        $error_msg = "Email config file not found: $email_config_file";
        error_log("CRITICAL: $error_msg");
        $error_details[] = $error_msg;
        $smtp_host = 'smtp.gmail.com';
        $smtp_port = 587;
        $smtp_username = '';
        $smtp_password = '';
        $from_email = '';
        $from_name = 'Daet Public Market Office';
    }
    
    // STRONG VALIDATION: Check if credentials are properly configured
    $config_errors = [];
    if (empty($smtp_username)) {
        $config_errors[] = "smtp_username is empty";
    }
    if (empty($smtp_password)) {
        $config_errors[] = "smtp_password is empty";
    }
    if ($smtp_password === 'your-app-password') {
        $config_errors[] = "smtp_password is still set to placeholder 'your-app-password'";
    }
    if (strpos($smtp_username, '@gmail.com') === false && !empty($smtp_username)) {
        $config_errors[] = "smtp_username does not appear to be a Gmail address";
    }
    if (empty($from_email)) {
        $config_errors[] = "from_email is empty";
    }
    
    if (!empty($config_errors)) {
        $error_msg = "Gmail credentials not properly configured in email_config.php. Issues: " . implode(', ', $config_errors);
        error_log("CRITICAL: $error_msg");
        $error_details[] = $error_msg;
        
        // Update log
        if ($conn && $log_id) {
            $update_stmt = $conn->prepare("UPDATE email_logs SET status = 'error', error_message = ?, error_details = ?, smtp_host = ?, smtp_username = ? WHERE id = ?");
            $error_details_json = json_encode($config_errors);
            $update_stmt->bind_param("ssssi", $error_msg, $error_details_json, $smtp_host, $smtp_username, $log_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        if ($conn) $conn->close();
        
        return ['success' => false, 'error' => $error_msg . '. Please check email_config.php and ensure you have set a valid Gmail App Password.'];
    }
    
    // Email subject and body
    $subject = 'Password Reset Code - Daet Public Market';
    $plain_text = "Hello $name,\n\nYour password reset code is: $code\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nDaet Public Market Office";
    
    // Get logo for email (embed as base64 for better email client compatibility)
    $logo_html = '';
    $logo_path = __DIR__ . '/assets/imgs/logo.jpeg';
    
    // Try to load logo, but don't let it break email sending
    if (file_exists($logo_path) && is_readable($logo_path)) {
        try {
            $file_size = filesize($logo_path);
            // Only process if file is reasonable size (less than 200KB original)
            if ($file_size > 0 && $file_size < 200000) {
                $logo_data = @file_get_contents($logo_path);
                if ($logo_data !== false && !empty($logo_data)) {
                    $logo_base64 = base64_encode($logo_data);
                    // Only include if base64 is reasonable size
                    if (strlen($logo_base64) < 300000) {
                        $logo_html = "<img src='data:image/jpeg;base64," . $logo_base64 . "' alt='Daet Public Market Logo' style='max-width: 150px; height: auto; margin-bottom: 15px; display: block;' />";
                    }
                }
            }
        } catch (Exception $e) {
            // Silently fail - email will send without logo
            error_log("Warning: Could not load logo for email: " . $e->getMessage());
        } catch (Error $e) {
            // Catch any fatal errors
            error_log("Warning: Error loading logo for email: " . $e->getMessage());
        }
    }
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #FFA07A 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header img { display: block; margin: 0 auto 15px auto; }
            .header h2 { margin: 0; font-size: 24px; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .code-box { background: #fff; border: 3px solid #667eea; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
            .code { font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 8px; font-family: 'Courier New', monospace; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                " . $logo_html . "
                <h2>Password Reset Code</h2>
            </div>
            <div class='content'>
                <p>Hello " . htmlspecialchars($name) . ",</p>
                <p>We received a request to reset your password for your Daet Public Market account.</p>
                <p>Your password reset code is:</p>
                <div class='code-box'>
                    <div class='code'>" . htmlspecialchars($code) . "</div>
                </div>
                <p><strong>This code will expire in 10 minutes.</strong></p>
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong> If you didn't request this, please ignore this email. Your account remains secure.
                </div>
                <p>Best regards,<br>Daet Public Market Office</p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Method 1: Try PHPMailer if available (RECOMMENDED)
    $phpmailer_path = __DIR__ . '/PHPMailer/src/PHPMailer.php';
    $phpmailer_loaded = false;
    
    // Check if PHPMailer files exist
    if (!file_exists($phpmailer_path)) {
        $error_details[] = "PHPMailer file not found at: $phpmailer_path";
        error_log("ERROR: PHPMailer file not found at: $phpmailer_path");
    } else {
        // Try to load PHPMailer classes
        $exception_path = __DIR__ . '/PHPMailer/src/Exception.php';
        $smtp_path = __DIR__ . '/PHPMailer/src/SMTP.php';
        
        if (!file_exists($exception_path)) {
            $error_details[] = "PHPMailer Exception.php not found";
        }
        if (!file_exists($smtp_path)) {
            $error_details[] = "PHPMailer SMTP.php not found";
        }
        
        if (file_exists($exception_path) && file_exists($phpmailer_path) && file_exists($smtp_path)) {
            try {
                require_once $exception_path;
                require_once $phpmailer_path;
                require_once $smtp_path;
                
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $phpmailer_loaded = true;
                } else {
                    $error_details[] = "PHPMailer class not found after loading files";
                }
            } catch (\Exception $e) {
                $error_details[] = "Error loading PHPMailer files: " . $e->getMessage();
                error_log("ERROR: Failed to load PHPMailer: " . $e->getMessage());
            }
        }
    }
    
    if ($phpmailer_loaded) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // Use TLS for Gmail
            $mail->Port = (int)$smtp_port; // Ensure port is integer
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 30; // 30 second timeout
            
            // SMTP Options for better compatibility with Gmail
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Enable verbose debug output for troubleshooting
            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) { 
                error_log("PHPMailer Debug: $str"); 
            };
            
            // Recipients
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($email, $name);
            $mail->addReplyTo($from_email, $from_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = $plain_text;
            
            // Send email
            $send_result = $mail->send();
            if ($send_result) {
                error_log("SUCCESS: Password reset code sent via PHPMailer to: $email");
                
                // Update log
                if ($conn && $log_id) {
                    $update_stmt = $conn->prepare("UPDATE email_logs SET status = 'sent', sent_at = NOW(), smtp_host = ?, smtp_username = ? WHERE id = ?");
                    $update_stmt->bind_param("ssi", $smtp_host, $smtp_username, $log_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                if ($conn) $conn->close();
                
                return ['success' => true, 'error' => null];
            } else {
                $error_info = $mail->ErrorInfo;
                $error_details[] = "PHPMailer send failed: $error_info";
                error_log("ERROR: PHPMailer failed for $email - $error_info");
                
                // Update log
                if ($conn && $log_id) {
                    $update_stmt = $conn->prepare("UPDATE email_logs SET status = 'failed', error_message = ?, error_details = ?, smtp_host = ?, smtp_username = ? WHERE id = ?");
                    $error_details_json = json_encode(['phpmailer_error' => $error_info]);
                    $update_stmt->bind_param("ssssi", $error_info, $error_details_json, $smtp_host, $smtp_username, $log_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }
        } catch (\Exception $e) {
            $error_msg = "PHPMailer Exception: " . $e->getMessage();
            $error_details[] = $error_msg;
            error_log("ERROR: PHPMailer Exception for $email - " . $e->getMessage());
            
            // Update log
            if ($conn && $log_id) {
                $update_stmt = $conn->prepare("UPDATE email_logs SET status = 'error', error_message = ?, error_details = ?, smtp_host = ?, smtp_username = ? WHERE id = ?");
                $error_details_json = json_encode(['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $update_stmt->bind_param("ssssi", $error_msg, $error_details_json, $smtp_host, $smtp_username, $log_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    } else {
        $error_msg = "PHPMailer not available. " . implode('; ', $error_details);
        error_log("ERROR: $error_msg");
    }
    
    // Method 2: Fallback to PHP mail() function (less reliable, may go to spam)
    // Note: This often doesn't work well with Gmail and may be blocked
    try {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $from_name . " <" . $from_email . ">" . "\r\n";
        $headers .= "Reply-To: " . $from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $result = @mail($email, $subject, $message, $headers);
        if ($result) {
            error_log("SUCCESS: Password reset code sent via mail() to: $email");
            return ['success' => true, 'error' => null];
        } else {
            $error_details[] = "PHP mail() function returned false (often blocked by Gmail)";
            error_log("ERROR: mail() function failed for $email - Gmail often blocks this method");
        }
    } catch (\Exception $e) {
        $error_details[] = "mail() Exception: " . $e->getMessage();
        error_log("ERROR: mail() Exception for $email - " . $e->getMessage());
    }
    
    // If all methods fail, return detailed error
    $final_error = implode('; ', $error_details);
    error_log("CRITICAL ERROR: Failed to send email to: $email");
    error_log("Error details: $final_error");
    error_log("Code generated but NOT sent: $code");
    
    // Update log with final failure
    if ($conn && $log_id) {
        $update_stmt = $conn->prepare("UPDATE email_logs SET status = 'failed', error_message = ?, error_details = ?, smtp_host = ?, smtp_username = ? WHERE id = ?");
        $error_details_json = json_encode($error_details);
        $update_stmt->bind_param("ssssi", $final_error, $error_details_json, $smtp_host, $smtp_username, $log_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    if ($conn) $conn->close();
    
    // Return user-friendly error message with more details
    $user_error = "Failed to send email. ";
    if (strpos($final_error, 'your-app-password') !== false) {
        $user_error .= "Please configure your Gmail App Password in email_config.php. ";
        $user_error .= "Get your App Password from: https://myaccount.google.com/apppasswords";
    } elseif (strpos($final_error, 'PHPMailer') !== false || strpos($final_error, 'SMTP') !== false) {
        // Show more detailed error for debugging
        $user_error .= "Email service error: ";
        // Extract the actual error message
        if (preg_match('/PHPMailer (?:send failed|Exception):\s*(.+?)(?:;|$)/i', $final_error, $matches)) {
            $user_error .= htmlspecialchars($matches[1]);
        } elseif (preg_match('/SMTP.*?error[:\s]+(.+?)(?:;|$)/i', $final_error, $matches)) {
            $user_error .= htmlspecialchars($matches[1]);
        } else {
            $user_error .= "Please check your email configuration. Error details logged.";
        }
        $user_error .= " Please check email_config.php settings or contact administrator.";
    } else {
        $user_error .= "Please check your email configuration or contact administrator.";
    }
    
    return ['success' => false, 'error' => $user_error];
}
?>
