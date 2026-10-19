<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: menu.php");
    exit();
}

// Check if code is verified
if (!isset($_SESSION['code_verified']) || !$_SESSION['code_verified'] || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error_message = '';
$success_message = '';
$email = $_SESSION['reset_email'];

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords don't match";
    } elseif (!validatePasswordStrength($password)) {
        $error_message = "Weak password. Password must be at least 8 characters and contain uppercase, lowercase, and number.";
    } else {
        $conn = getDBConnection();
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // First, verify the user exists
        $check_stmt = $conn->prepare("SELECT user_id, email FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error_message = "User not found. Please contact administrator.";
            $check_stmt->close();
            $conn->close();
        } else {
            $user_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            // Debug: Log what we're about to do
            error_log("DEBUG: Attempting to reset password for email: $email, user_id: " . $user_data['user_id']);
            
            // Try both column names to be safe (password_hash and password)
            // First try password_hash (standard)
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $error_info = $stmt->error;
                $stmt->close();
                
                // If no rows affected with password_hash, try 'password' column
                if ($affected_rows === 0 && empty($error_info)) {
                    error_log("DEBUG: password_hash column update returned 0 rows, trying 'password' column");
                    $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt2->bind_param("ss", $hashed_password, $email);
                    
                    if ($stmt2->execute()) {
                        $affected_rows = $stmt2->affected_rows;
                        $error_info = $stmt2->error;
                        error_log("DEBUG: 'password' column update - affected_rows: $affected_rows, error: " . ($error_info ?: 'none'));
                    }
                    $stmt2->close();
                }
                
                if ($affected_rows > 0) {
                    // Invalidate all reset codes for this email
                    $invalidate_stmt = $conn->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL");
                    $invalidate_stmt->bind_param("s", $email);
                    $invalidate_stmt->execute();
                    $invalidate_stmt->close();
                    
                    // Verify the update worked
                    $verify_stmt = $conn->prepare("SELECT password_hash FROM users WHERE email = ?");
                    $verify_stmt->bind_param("s", $email);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $verify_data = $verify_result->fetch_assoc();
                    $verify_stmt->close();
                    
                    if ($verify_data && password_verify($password, $verify_data['password_hash'])) {
                        // Clear session
                        unset($_SESSION['code_verified']);
                        unset($_SESSION['reset_email']);
                        
                        // Set success flag for popup
                        $_SESSION['password_reset_success'] = true;
                        $success_message = "Password reset successfully! Redirecting to login...";
                        error_log("SUCCESS: Password reset verified for email: $email");
                    } else {
                        error_log("ERROR: Password update succeeded but verification failed for email: $email");
                        $error_message = "Password was updated but verification failed. Please try logging in or contact administrator.";
                    }
                } else {
                    error_log("WARNING: Password update executed but no rows affected for email: $email. Error: " . ($error_info ?: 'none'));
                    $error_message = "Password update failed. No changes were made. ";
                    if ($error_info) {
                        $error_message .= "Error: " . htmlspecialchars($error_info);
                    } else {
                        $error_message .= "Please check if the password column exists in the database.";
                    }
                }
            } else {
                error_log("ERROR: Password update query failed for email: $email - " . $stmt->error);
                $error_message = "Failed to reset password. Error: " . htmlspecialchars($stmt->error) . ". Please try again or contact administrator.";
                $stmt->close();
            }
            
            $conn->close();
        }
    }
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    // At least 8 characters
    if (strlen($password) < 8) {
        return false;
    }
    
    // Contains uppercase
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Contains lowercase
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Contains number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Daet Public Market</title>
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
          /* Kept for the validation JS which references these names */
          --color-error: #e74c3c;
          --color-success: #27ae60;
          --color-border: #e2e8f0;
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
          max-width: 440px;
          box-shadow: 0 10px 32px rgba(15,23,42,0.07);
          text-align: center;
        }
        .card-icon {
          width: 56px; height: 56px; border-radius: 14px;
          display: flex; align-items: center; justify-content: center;
          font-size: 1.3rem; margin: 0 auto 16px;
        }
        .card-icon--pink { background: #eef1fd; color: var(--indigo); }
        .form-header h1 {
          font-size: 1.35rem;
          font-weight: 800;
          color: var(--text);
          margin-bottom: 6px;
        }
        .form-header p { color: var(--muted); font-size: 0.84rem; line-height: 1.55; margin-bottom: 18px; }

        .alert {
          padding: 12px 16px;
          border-radius: 10px;
          margin-bottom: 16px;
          font-size: 0.84rem;
          text-align: left;
        }
        .alert-error { background-color: #fee; color: var(--error); border: 1px solid #fcc; }
        .alert-success { background-color: #efe; color: var(--success); border: 1px solid #cfc; }

        .form-box { display: flex; flex-direction: column; gap: 15px; text-align: left; }
        .field-group label {
          display: block;
          font-size: 0.76rem;
          font-weight: 700;
          margin-bottom: 6px;
        }
        .input-group { position: relative; }
        .input-group > .field-icon {
          position: absolute;
          left: 14px; top: 50%;
          transform: translateY(-50%);
          color: #94a3b8;
          font-size: 0.85rem;
          pointer-events: none;
        }
        .input-field {
          width: 100%;
          height: 46px;
          padding: 0 44px 0 40px;
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
        .password-toggle {
          position: absolute;
          right: 8px;
          top: 50%;
          transform: translateY(-50%);
          background: none;
          border: none;
          color: #94a3b8;
          cursor: pointer;
          font-size: 0.95rem;
          padding: 8px;
          border-radius: 6px;
          transition: color 0.2s ease;
        }
        .password-toggle:hover { color: var(--indigo); }

        .password-requirements {
          background: #f8fafc;
          border: 1px solid var(--border);
          padding: 14px 16px;
          border-radius: 10px;
          font-size: 0.78rem;
          text-align: left;
        }
        .password-requirements h4 {
          margin-bottom: 8px;
          color: var(--indigo-dark);
          font-size: 0.78rem;
          font-weight: 700;
          display: flex;
          align-items: center;
          gap: 6px;
        }
        .password-requirements ul { list-style: none; padding-left: 0; }
        .password-requirements li {
          padding: 4px 0;
          display: flex;
          align-items: center;
          gap: 8px;
        }
        .password-requirements li i { width: 14px; font-size: 0.6rem; }
        .password-requirements li.valid { color: var(--success); }
        .password-requirements li.invalid { color: #94a3b8; }

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

        /* Success Popup Modal */
        .success-popup {
          display: none;
          position: fixed;
          z-index: 2000;
          left: 0;
          top: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(0, 0, 0, 0.7);
          align-items: center;
          justify-content: center;
          animation: fadeIn 0.3s;
        }

        .success-popup.show {
          display: flex;
        }

        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }

        @keyframes slideUp {
          from {
            transform: translateY(50px);
            opacity: 0;
          }
          to {
            transform: translateY(0);
            opacity: 1;
          }
        }

        .success-popup-content {
          background: white;
          padding: 50px 60px;
          border-radius: 20px;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
          text-align: center;
          max-width: 500px;
          width: 90%;
          animation: slideUp 0.4s ease-out;
          display: flex;
          flex-direction: column;
          align-items: center;
        }

        .success-popup-image {
          height: 120px;
          width: auto;
          max-width: 300px;
          object-fit: contain;
          border-radius: 12px;
          margin-bottom: 20px;
        }

        .success-popup-content h2 {
          color: var(--color-success);
          font-size: 32px;
          margin-bottom: 20px;
          font-weight: 700;
          letter-spacing: 0.5px;
        }

        .success-popup-content p {
          color: #555;
          font-size: 18px;
          margin-bottom: 30px;
          font-weight: 500;
          line-height: 1.6;
        }

        .success-icon-wrapper {
          position: relative;
          z-index: 1;
          margin-bottom: 25px;
        }

        .success-check-icon {
          width: 90px;
          height: 90px;
          margin: 0 auto;
          background: linear-gradient(135deg, var(--color-success) 0%, #2ecc71 100%);
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 8px 24px rgba(39, 174, 96, 0.4);
          animation: scaleIn 0.5s ease-out;
        }

        .success-check-icon svg {
          width: 50px;
          height: 50px;
          stroke: white;
          stroke-width: 3;
          fill: none;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        @keyframes scaleIn {
          0% { transform: scale(0); }
          50% { transform: scale(1.1); }
          100% { transform: scale(1); }
        }

        .loading-icon {
          display: inline-block;
          width: 40px;
          height: 40px;
          border: 3px solid rgba(39, 174, 96, 0.2);
          border-top: 3px solid var(--color-success);
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-top: 15px;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
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
                            <div><strong>Strong Password Policy</strong><span>Multi-criteria strength enforcement</span></div>
                        </div>
                    </div>
                </div>
                <div class="al-footer">&copy; <?php echo date('Y'); ?> Daet Public Market &mdash; Market Clearance System</div>
            </div>
        </aside>

        <!-- Right: new password form -->
        <main class="auth-right">
            <div class="stepper">
                <div class="step done"><div class="step-circle"><i class="fas fa-check"></i></div><span>Email</span></div>
                <div class="step-line done"></div>
                <div class="step done"><div class="step-circle"><i class="fas fa-check"></i></div><span>Verify</span></div>
                <div class="step-line done"></div>
                <div class="step active"><div class="step-circle">3</div><span>Reset</span></div>
            </div>

            <div class="form-container">
                <div class="form-header">
                    <div class="card-icon card-icon--pink"><i class="fas fa-lock"></i></div>
                    <h1>Create New Password</h1>
                    <p>Choose a strong password you haven't used before to keep your account secure.</p>
                </div>

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

                <form class="form-box" method="POST" action="reset_password.php" id="resetForm">
                    <input type="hidden" name="action" value="reset_password">

                    <div class="field-group">
                        <label for="password">New Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" id="password" name="password" class="input-field" placeholder="Enter new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder="Re-enter password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-requirements">
                        <h4><i class="fas fa-shield-halved"></i> Password Requirements</h4>
                        <ul>
                            <li id="req-length" class="invalid">
                                <i class="fas fa-circle"></i> At least 8 characters
                            </li>
                            <li id="req-uppercase" class="invalid">
                                <i class="fas fa-circle"></i> One uppercase letter (A-Z)
                            </li>
                            <li id="req-lowercase" class="invalid">
                                <i class="fas fa-circle"></i> One lowercase letter (a-z)
                            </li>
                            <li id="req-number" class="invalid">
                                <i class="fas fa-circle"></i> One number (0-9)
                            </li>
                        </ul>
                    </div>

                    <button type="submit" class="form-btn form-btn--submit" id="submitBtn" disabled>
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>
            </div>

            <a href="index.php#user-portal" class="back-link">
                <i class="fas fa-arrow-left"></i> Remember your password? <span class="accent">Sign in</span>
            </a>
        </main>
    </div>

    <!-- Success Popup Modal -->
    <div id="successPopup" class="success-popup">
        <div class="success-popup-content">
            <img src="assets/imgs/passwordreset.jpg" alt="Password Reset" class="success-popup-image">
            <div class="success-icon-wrapper">
                <div class="success-check-icon">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="white" stroke-width="2" fill="none"/>
                        <polyline points="9 12 11 14 15 10" stroke="white" stroke-width="3" fill="none"/>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 text-center">Successfully Reset Password</h2>
            <p class="text-gray-700 text-center mt-2">Your password has been reset successfully.<br>Redirecting to login...</p>
            <div class="loading-icon"></div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');

        function validatePassword() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement('req-length', hasLength);
            updateRequirement('req-uppercase', hasUppercase);
            updateRequirement('req-lowercase', hasLowercase);
            updateRequirement('req-number', hasNumber);
            
            // Check if passwords match
            const passwordsMatch = password === confirmPassword && password.length > 0;
            
            // Enable submit button only if all requirements met and passwords match
            const allValid = hasLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch;
            submitBtn.disabled = !allValid;
            
            // Show match indicator
            if (confirmPassword.length > 0) {
                if (passwordsMatch) {
                    confirmPasswordField.style.borderColor = 'var(--color-success)';
                } else {
                    confirmPasswordField.style.borderColor = 'var(--color-error)';
                }
            } else {
                confirmPasswordField.style.borderColor = 'var(--color-border)';
            }
        }

        function updateRequirement(id, isValid) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                icon.classList.remove('fa-circle');
                icon.classList.add('fa-check-circle');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-circle');
            }
        }

        passwordField.addEventListener('input', validatePassword);
        confirmPasswordField.addEventListener('input', validatePassword);

        // Show success popup if password reset was successful
        <?php if (isset($_SESSION['password_reset_success']) && $_SESSION['password_reset_success']): ?>
            <?php 
                // Clear the session flag
                unset($_SESSION['password_reset_success']);
            ?>
            document.addEventListener('DOMContentLoaded', function() {
                const successPopup = document.getElementById('successPopup');
                if (successPopup) {
                    successPopup.classList.add('show');
                    // Redirect to login page after 3 seconds
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 3000);
                }
            });
        <?php endif; ?>
    </script>
    <?php
    $cs_contact_href = 'index.php#contact';
    $cs_announcement_href = 'index.php#announcement';
    include __DIR__ . '/includes/customer_support_widget.php';
    ?>
</body>
</html>
