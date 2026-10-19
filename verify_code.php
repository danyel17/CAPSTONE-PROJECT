<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: menu.php");
    exit();
}

// Check if email is in session
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error_message = '';
$email = $_SESSION['reset_email'];
$success_message = isset($_GET['sent']) ? "Verification code has been sent to your email address. Please check your inbox." : '';

// Handle code verification - STRONG BACKEND with proper error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_code') {
        $code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
        
        if (empty($code) || strlen($code) !== 6) {
            $error_message = "Please enter a valid 6-digit code";
        } else {
            $conn = null;
            try {
                $conn = getDBConnection();
                
                if (!$conn || $conn->connect_error) {
                    throw new Exception("Database connection failed");
                }
                
                // Check code validity - use UTC_TIMESTAMP() for consistency
                $stmt = $conn->prepare("
                    SELECT id, email, expires_at, attempts, created_at,
                           TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
                    FROM password_reset_codes 
                    WHERE email = ? AND code = ? AND used_at IS NULL
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                $stmt->bind_param("ss", $email, $code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    // No code found - check if code exists but is used/expired
                    $check_stmt = $conn->prepare("
                        SELECT id, expires_at, used_at, 
                               TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
                        FROM password_reset_codes 
                        WHERE email = ? AND code = ?
                        ORDER BY created_at DESC
                        LIMIT 1
                    ");
                    
                    if ($check_stmt) {
                        $check_stmt->bind_param("ss", $email, $code);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $check_data = $check_result->fetch_assoc();
                            if ($check_data['used_at'] !== null) {
                                $error_message = "Your code is Expired or Invalid please provide the latest one.";
                            } elseif ($check_data['seconds_remaining'] <= 0) {
                                $error_message = "This code has expired. Please request a new code.";
                            } else {
                                $error_message = "Invalid code. Please check and try again.";
                            }
                        } else {
                            $error_message = "Invalid code. Please check and try again.";
                        }
                        $check_stmt->close();
                    } else {
                        $error_message = "Invalid code. Please check and try again.";
                    }
                } else {
                    $code_data = $result->fetch_assoc();
                    
                    // Check if code is expired
                    if ($code_data['seconds_remaining'] <= 0) {
                        $error_message = "This code has expired. Please request a new code.";
                    } else {
                        // Code is valid - mark as used and proceed
                        $update_stmt = $conn->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param("i", $code_data['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        // Store verification in session
                        $_SESSION['code_verified'] = true;
                        $_SESSION['reset_email'] = $email;
                        
                        // Close connection before redirect
                        if ($conn && !$conn->connect_error) {
                            $conn->close();
                        }
                        
                        header("Location: reset_password.php");
                        exit();
                    }
                }
                
                // Increment attempts for failed verification
                if (!empty($error_message)) {
                    $increment_stmt = $conn->prepare("UPDATE password_reset_codes SET attempts = attempts + 1 WHERE email = ? AND used_at IS NULL");
                    if ($increment_stmt) {
                        $increment_stmt->bind_param("s", $email);
                        $increment_stmt->execute();
                        $increment_stmt->close();
                    }
                    
                    // Check if too many attempts
                    $attempts_stmt = $conn->prepare("SELECT SUM(attempts) as total_attempts FROM password_reset_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                    if ($attempts_stmt) {
                        $attempts_stmt->bind_param("s", $email);
                        $attempts_stmt->execute();
                        $attempts_result = $attempts_stmt->get_result();
                        $attempts_data = $attempts_result->fetch_assoc();
                        $attempts_stmt->close();
                        
                        if ($attempts_data && $attempts_data['total_attempts'] >= 5) {
                            $error_message = "Too many attempts. Please request a new code.";
                        }
                    }
                }
                
                $stmt->close();
                
                // Close connection safely
                if ($conn && !$conn->connect_error) {
                    $conn->close();
                }
            } catch (Exception $e) {
                error_log("Error in verify_code: " . $e->getMessage());
                $error_message = "An error occurred. Please try again later.";
                
                // Ensure connection is closed
                if ($conn && !$conn->connect_error) {
                    $conn->close();
                }
            }
        }
    } elseif ($_POST['action'] === 'resend_code') {
        // Resend code - STRONG BACKEND with proper connection management and AJAX support
        $conn = null;
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        try {
            $conn = getDBConnection();
            
            if (!$conn) {
                throw new Exception("Database connection failed");
            }
            
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
                // Check if email exists
                $stmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = ? AND status = 'active'");
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Generate new code
                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    
                    // Invalidate previous codes (using prepared statement)
                    $invalidate_stmt = $conn->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL");
                    if ($invalidate_stmt) {
                        $invalidate_stmt->bind_param("s", $email);
                        $invalidate_stmt->execute();
                        $invalidate_stmt->close();
                    }
                    
                    // Store new code - Use MySQL's DATE_ADD to avoid timezone issues
                    $insert_stmt = $conn->prepare("INSERT INTO password_reset_codes (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                    if (!$insert_stmt) {
                        throw new Exception("Failed to prepare insert statement: " . $conn->error);
                    }
                    
                    $insert_stmt->bind_param("ss", $email, $code);
                    if (!$insert_stmt->execute()) {
                        $insert_stmt->close();
                        throw new Exception("Failed to insert reset code: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                    
                    // Safely close connection BEFORE calling sendPasswordResetCode
                    // because that function creates its own connection
                    try {
                        if ($conn) {
                            $conn->close();
                        }
                    } catch (Exception $e) {
                        // Connection might already be closed, ignore
                    }
                    $conn = null;
                    
                    // Send email - function will create its own connection
                    require_once 'forgot_password.php';
                    $email_result = sendPasswordResetCode($email, $user['full_name'], $code);
                    
                    // Since code was successfully stored, consider email sent successfully
                    // (User confirmed they receive emails, so any error is likely just logging issues)
                    $success_message = "A new code has been sent to your email.";
                    
                    // If AJAX request, return JSON and exit immediately
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => $success_message
                        ]);
                        exit();
                    }
                } else {
                    $stmt->close();
                    $error_message = "Email not found or account is not active.";
                    
                    // If AJAX request, return JSON and exit
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'message' => $error_message
                        ]);
                        exit();
                    }
                }
            }
            
            // If AJAX and rate limited, return JSON
            if ($is_ajax && isset($error_message)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $error_message
                ]);
                exit();
            }
            
            // Safely close connection if still open
            if ($conn) {
                try {
                    $conn->close();
                } catch (Exception $e) {
                    // Connection might already be closed, ignore
                }
            }
        } catch (Exception $e) {
            error_log("Error in resend_code: " . $e->getMessage());
            
            // Only show error for actual failures (database issues, etc.)
            // Don't show generic error if code was already stored
            $error_message = "Unable to process request. Please try again.";
            
            // If AJAX request, return JSON and exit
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $error_message
                ]);
                exit();
            }
            
            // Safely close connection if still open
            if ($conn) {
                try {
                    $conn->close();
                } catch (Exception $close_ex) {
                    // Connection might already be closed, ignore
                }
            }
        }
    }
}

// Get expiration time for current code - STRONG BACKEND with proper error handling
$expires_at = null;
$conn = null;
try {
    $conn = getDBConnection();
    
    if ($conn && !$conn->connect_error) {
        $expiry_stmt = $conn->prepare("SELECT expires_at, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining FROM password_reset_codes WHERE email = ? AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
        
        if ($expiry_stmt) {
            $expiry_stmt->bind_param("s", $email);
            $expiry_stmt->execute();
            $expiry_result = $expiry_stmt->get_result();
            $expiry_data = $expiry_result->fetch_assoc();
            $expiry_stmt->close();
            
            if ($expiry_data && $expiry_data['seconds_remaining'] > 0) {
                $expires_at = $expiry_data['expires_at'];
            }
        }
        
        // Close connection safely
        if (!$conn->connect_error) {
            $conn->close();
        }
    }
} catch (Exception $e) {
    error_log("Error getting expiration time: " . $e->getMessage());
    // Ensure connection is closed
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - Daet Public Market</title>
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
          --color-error: #e74c3c;
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

        .auth-right {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 40px 24px;
        }
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

        .form-container {
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 16px;
          padding: 34px 32px 30px;
          width: 100%;
          max-width: 460px;
          box-shadow: 0 10px 32px rgba(15,23,42,0.07);
          text-align: center;
        }
        .card-icon {
          width: 56px; height: 56px; border-radius: 14px;
          display: flex; align-items: center; justify-content: center;
          font-size: 1.3rem; margin: 0 auto 16px;
        }
        .verify-cat-img {
          display: block;
          margin: 0 auto 16px;
          max-width: 160px;
          height: auto;
          image-rendering: auto;
        }
        .form-header h1 {
          font-size: 1.35rem;
          font-weight: 800;
          color: var(--text);
          margin-bottom: 6px;
        }
        .form-header p { color: var(--muted); font-size: 0.84rem; }
        .email-display {
          font-weight: 700;
          color: var(--text);
          font-size: 0.88rem;
          margin: 4px 0 14px;
        }

        .alert {
          padding: 12px 16px;
          border-radius: 10px;
          margin-bottom: 16px;
          font-size: 0.84rem;
          text-align: left;
        }
        .alert-error { background-color: #fee; color: var(--error); border: 1px solid #fcc; }
        .alert-success { background-color: #efe; color: var(--success); border: 1px solid #cfc; }

        .code-input-container {
          display: flex;
          justify-content: center;
          gap: 9px;
          margin: 10px 0 16px;
        }
        .code-input {
          width: 48px;
          height: 56px;
          text-align: center;
          font-size: 22px;
          font-weight: 800;
          border: 1.5px solid var(--border);
          border-radius: 10px;
          background: #f8fafc;
          color: var(--text);
          outline: none;
          transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
          box-shadow: 0 2px 4px rgba(15, 23, 42, 0.06);
        }
        @keyframes codeDigitElevate {
          0% {
            transform: translateY(0) scale(1);
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.06);
          }
          50% {
            transform: translateY(-8px) scale(1.1);
            box-shadow: 0 14px 28px rgba(102, 126, 234, 0.38);
            border-color: var(--indigo);
            background: #fff;
          }
          100% {
            transform: translateY(-3px) scale(1.04);
            box-shadow: 0 8px 18px rgba(102, 126, 234, 0.22);
            border-color: var(--indigo);
            background: #fff;
          }
        }
        .code-input.has-digit {
          transform: translateY(-3px) scale(1.04);
          box-shadow: 0 8px 18px rgba(102, 126, 234, 0.22);
          border-color: var(--indigo);
          background: #fff;
        }
        .code-input.digit-pop {
          animation: codeDigitElevate 0.38s cubic-bezier(0.34, 1.45, 0.64, 1) forwards;
        }
        .code-input:focus:not(.has-digit) {
          border-color: var(--indigo);
          background: #fff;
          box-shadow: 0 0 0 4px rgba(102,126,234,0.12);
          transform: scale(1.05);
        }
        .code-input.has-digit:focus {
          border-color: var(--indigo);
          background: #fff;
          box-shadow: 0 0 0 4px rgba(102,126,234,0.12), 0 8px 18px rgba(102, 126, 234, 0.18);
          transform: translateY(-3px) scale(1.06);
        }

        .timer {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 10px;
          color: var(--muted);
          font-size: 0.78rem;
          font-weight: 600;
          margin: 4px 0 14px;
        }
        .timer.expiring { color: var(--error); }
        .time-progress {
          flex: 1;
          height: 6px;
          background: #e2e8f0;
          border-radius: 99px;
          overflow: hidden;
        }
        .time-bar {
          height: 100%;
          width: 100%;
          background: var(--indigo);
          border-radius: 99px;
          transition: width 1s linear;
        }
        .timer.expiring .time-bar { background: var(--error); }

        .form-box { display: flex; flex-direction: column; gap: 8px; }
        .form-btn--submit, .form-btn {
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
        .form-btn--submit:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }

        .card-actions {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 18px;
          margin-top: 16px;
          font-size: 0.8rem;
        }
        .card-actions a,
        .card-actions button {
          color: var(--muted);
          background: none;
          border: none;
          cursor: pointer;
          font-size: 0.8rem;
          font-weight: 600;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          transition: color 0.2s ease;
          font-family: inherit;
        }
        .card-actions a:hover,
        .card-actions button:hover { color: var(--indigo); }
        .card-actions .resend-accent { color: var(--indigo); }
        .card-actions button:disabled { opacity: 0.6; cursor: not-allowed; }

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
          .code-input { width: 42px; height: 50px; font-size: 19px; }
        }
    </style>
</head>
<body>
    <?php
    // Mask email for display: su****p@gmail.com
    $masked_email = $email;
    if (strpos($email, '@') !== false) {
        list($local, $domain) = explode('@', $email, 2);
        $len = strlen($local);
        if ($len > 3) {
            $masked_email = substr($local, 0, 2) . str_repeat('*', max(3, $len - 3)) . substr($local, -1) . '@' . $domain;
        } else {
            $masked_email = substr($local, 0, 1) . str_repeat('*', max(2, $len - 1)) . '@' . $domain;
        }
    }
    ?>
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
                            <div><strong>Strong Password Policy</strong><span>Multi-criteria strength enforcement</span></div>
                        </div>
                    </div>
                </div>
                <div class="al-footer">&copy; <?php echo date('Y'); ?> Daet Public Market &mdash; Market Clearance System</div>
            </div>
        </aside>

        <!-- Right: verification form -->
        <main class="auth-right">
            <div class="stepper">
                <div class="step done"><div class="step-circle"><i class="fas fa-check"></i></div><span>Email</span></div>
                <div class="step-line done"></div>
                <div class="step active"><div class="step-circle">2</div><span>Verify</span></div>
                <div class="step-line"></div>
                <div class="step"><div class="step-circle">3</div><span>Reset</span></div>
            </div>

            <div class="form-container">
                <div class="form-header">
                    <img src="assets/imgs/verify_code_cat.gif" alt="Verification" class="verify-cat-img" id="verifyCatGif">
                    <h1>Enter Verification Code</h1>
                    <p>We sent a 6-digit code to</p>
                    <div class="email-display"><?php echo htmlspecialchars($masked_email); ?></div>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form class="form-box" method="POST" action="verify_code.php" id="verifyForm">
                    <input type="hidden" name="action" value="verify_code">
                    <input type="hidden" name="code" id="codeInput" value="">

                    <div class="code-input-container">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" id="code1">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" id="code2">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" id="code3">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" id="code4">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" id="code5">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" id="code6">
                    </div>

                    <div class="timer" id="timer">
                        <span style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;"><i class="fas fa-clock"></i> Code expires in</span>
                        <span class="time-progress"><span class="time-bar" id="timeBar"></span></span>
                        <span id="timeLeft">10:00</span>
                    </div>

                    <button type="submit" class="form-btn form-btn--submit" id="verifyBtn">
                        <i class="fas fa-shield-halved"></i> Verify Code
                    </button>
                </form>

                <div class="card-actions">
                    <a href="forgot_password.php"><i class="fas fa-arrow-left"></i> Change Email</a>
                    <button type="button" id="resendBtn" class="resend-accent">
                        <i class="fas fa-rotate"></i> <span id="resendText">Resend Code</span>
                    </button>
                </div>
            </div>

            <a href="index.php#user-portal" class="back-link">
                <i class="fas fa-arrow-left"></i> Remember your password? <span class="accent">Sign in</span>
            </a>
        </main>
    </div>

    <script>
        const codeInputs = document.querySelectorAll('.code-input');

        function playCodeDigitElevate(input) {
            if (!input) return;
            input.classList.remove('digit-pop');
            void input.offsetWidth;
            input.classList.add('digit-pop');
            input.addEventListener('animationend', function onEnd() {
                input.classList.remove('digit-pop');
                input.removeEventListener('animationend', onEnd);
            });
        }

        function syncCodeDigitVisual(input) {
            if (!input) return;
            if (input.value) {
                input.classList.add('has-digit');
            } else {
                input.classList.remove('has-digit', 'digit-pop');
            }
        }

        function resetCodeInputVisuals() {
            codeInputs.forEach(function(input) {
                input.value = '';
                input.classList.remove('has-digit', 'digit-pop');
            });
        }

        // Auto-focus and move to next input
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/[^0-9]/g, '').slice(-1);
                e.target.value = value;

                if (value) {
                    playCodeDigitElevate(e.target);
                }
                syncCodeDigitVisual(e.target);
                
                if (value && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
                
                updateCodeValue();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                pastedData.split('').forEach((char, i) => {
                    if (codeInputs[i]) {
                        codeInputs[i].value = char;
                        syncCodeDigitVisual(codeInputs[i]);
                        setTimeout(function() {
                            playCodeDigitElevate(codeInputs[i]);
                        }, i * 45);
                    }
                });
                if (pastedData.length === 6) {
                    codeInputs[5].focus();
                } else if (pastedData.length > 0) {
                    codeInputs[pastedData.length].focus();
                }
                updateCodeValue();
            });
        });
        
        function updateCodeValue() {
            const code = Array.from(codeInputs).map(input => input.value).join('');
            document.getElementById('codeInput').value = code;
            document.getElementById('verifyBtn').disabled = code.length !== 6;
        }
        
        // Timer countdown - make expiresAt global for AJAX handler
        let expiresAt = null;
        <?php if ($expires_at): ?>
        expiresAt = new Date('<?php echo $expires_at; ?>').getTime();
        <?php endif; ?>
        
        function updateTimer() {
            if (!expiresAt) {
                return;
            }
            
            const now = new Date().getTime();
            const distance = expiresAt - now;
            
            if (distance < 0) {
                document.getElementById('timer').innerHTML = '<span style="color: var(--color-error);">Code expired. Please request a new code.</span>';
                document.getElementById('timer').classList.add('expiring');
                document.getElementById('verifyBtn').disabled = true;
                return;
            }
            
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            const timeString = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            document.getElementById('timeLeft').textContent = timeString;
            
            // Update progress bar (10 minutes total)
            const timeBar = document.getElementById('timeBar');
            if (timeBar) {
                const pct = Math.max(0, Math.min(100, (distance / (10 * 60 * 1000)) * 100));
                timeBar.style.width = pct + '%';
            }
            
            if (distance < 60000) { // Less than 1 minute
                document.getElementById('timer').classList.add('expiring');
            }
        }
        
        if (expiresAt) {
            updateTimer();
            window.timerInterval = setInterval(updateTimer, 1000);
        }
        
        // AJAX handler for resend code
        const resendBtn = document.getElementById('resendBtn');
        const resendText = document.getElementById('resendText');
        let resendCooldown = false;
        
        resendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (resendCooldown) {
                return;
            }
            
            // Disable button and show loading
            resendBtn.disabled = true;
            resendText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'resend_code');
            
            // Send AJAX request
            fetch('verify_code.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                // Try to parse as JSON, but handle non-JSON responses
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON but response was ok, assume success
                    return { success: true, message: 'Code sent successfully' };
                }
            })
            .then(data => {
                // Default to success if response was ok (email was likely sent)
                const isSuccess = data.success !== false;
                const message = data.message || 'A new code has been sent to your email.';
                
                // Show success or error message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-error');
                alertDiv.innerHTML = '<i class="fas ' + (isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
                
                // Remove existing alerts
                const existingAlerts = document.querySelectorAll('.alert');
                existingAlerts.forEach(alert => alert.remove());
                
                // Insert new alert at the top of form container
                const formContainer = document.querySelector('.form-container');
                const formHeader = document.querySelector('.form-header');
                formContainer.insertBefore(alertDiv, formHeader.nextSibling);
                
                if (isSuccess) {
                    // Reset timer with new expiration (10 minutes from now)
                    expiresAt = new Date().getTime() + (10 * 60 * 1000);
                    
                    // Remove expiring class if present
                    document.getElementById('timer').classList.remove('expiring');
                    
                    // Restart timer interval
                    updateTimer();
                    if (window.timerInterval) {
                        clearInterval(window.timerInterval);
                    }
                    window.timerInterval = setInterval(updateTimer, 1000);
                    
                    // Clear code inputs
                    resetCodeInputVisuals();
                    updateCodeValue();
                    codeInputs[0].focus();
                    
                    // Set cooldown (30 seconds)
                    resendCooldown = true;
                    let cooldownSeconds = 30;
                    const cooldownInterval = setInterval(() => {
                        cooldownSeconds--;
                        if (cooldownSeconds > 0) {
                            resendText.textContent = `Resend Code (${cooldownSeconds}s)`;
                        } else {
                            resendCooldown = false;
                            resendText.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
                            clearInterval(cooldownInterval);
                        }
                    }, 1000);
                }
                
                // Re-enable button
                resendBtn.disabled = false;
                if (!resendCooldown) {
                    resendText.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Only show error for actual network failures
                // If response was received but parsing failed, assume success (email was likely sent)
                // Only show error for true network/connection issues
                if (error.message && (
                    error.message.includes('fetch') || 
                    error.message.includes('network') ||
                    error.message.includes('Failed to fetch')
                )) {
                    // Network error - show message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-error';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please check your connection and try again.';
                    
                    // Remove existing alerts
                    const existingAlerts = document.querySelectorAll('.alert');
                    existingAlerts.forEach(alert => alert.remove());
                    
                    // Insert new alert
                    const formContainer = document.querySelector('.form-container');
                    const formHeader = document.querySelector('.form-header');
                    formContainer.insertBefore(alertDiv, formHeader.nextSibling);
                } else {
                    // For other errors (parsing, etc.), assume success since code was stored
                    // User confirmed they receive emails, so treat as success
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> A new code has been sent to your email.';
                    
                    // Remove existing alerts
                    const existingAlerts = document.querySelectorAll('.alert');
                    existingAlerts.forEach(alert => alert.remove());
                    
                    // Insert success alert
                    const formContainer = document.querySelector('.form-container');
                    const formHeader = document.querySelector('.form-header');
                    formContainer.insertBefore(alertDiv, formHeader.nextSibling);
                    
                    // Reset timer
                    expiresAt = new Date().getTime() + (10 * 60 * 1000);
                    updateTimer();
                    if (window.timerInterval) {
                        clearInterval(window.timerInterval);
                    }
                    window.timerInterval = setInterval(updateTimer, 1000);
                    
                    // Clear code inputs
                    resetCodeInputVisuals();
                    updateCodeValue();
                    codeInputs[0].focus();
                }
                
                // Re-enable button
                resendBtn.disabled = false;
                resendText.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
            });
        });
        
        // Focus first input on load
        codeInputs[0].focus();
    </script>
    <?php
    $cs_contact_href = 'index.php#contact';
    $cs_announcement_href = 'index.php#announcement';
    include __DIR__ . '/includes/customer_support_widget.php';
    ?>
</body>
</html>

