<?php
require_once 'config.php';

/**
 * Authenticate user with email and password
 */
function authenticateUser($email, $password, $remember_me = false) {
    $conn = getDBConnection();
    
    // Get user from database
    $stmt = $conn->prepare("SELECT user_id, email, password_hash, full_name, user_type, status, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if user exists
    if ($result->num_rows === 0) {
        logLoginAttempt($email, false, 'user_not_found');
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Check account status
    if ($user['status'] !== 'active') {
        logLoginAttempt($email, false, 'account_inactive');
        $conn->close();
        return ['success' => false, 'message' => 'Your account is not active. Please contact administrator.'];
    }
    
    // Verify password - THIS IS THE CRITICAL CHECK
    if (!password_verify($password, $user['password_hash'])) {
        logLoginAttempt($email, false, 'invalid_password');
        $conn->close();
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Check email verification - Admin users bypass this requirement
    if (!$user['email_verified'] && $user['user_type'] !== 'admin') {
        logLoginAttempt($email, false, 'email_not_verified');
        $conn->close();
        return ['success' => false, 'message' => 'Please verify your email address before logging in.'];
    }
    
    // Ensure admin users have email_verified set to 1 (auto-fix)
    if ($user['user_type'] === 'admin' && !$user['email_verified']) {
        $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
        $update_stmt->bind_param("i", $user['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        $user['email_verified'] = 1;
    }
    
    // Log successful login
    logLoginAttempt($email, true, null);
    
    // Update last login time and reset failed attempts
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $stmt->close();
    
    // Create session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['last_activity'] = time();
    
    // Create remember me token if requested
    if ($remember_me) {
        createRememberMeToken($user['user_id']);
    }
    
    $conn->close();
    return ['success' => true, 'message' => 'Login successful', 'user_type' => $user['user_type']];
}

/**
 * Create remember me token
 */
function createRememberMeToken($user_id) {
    $conn = getDBConnection();
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO login_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $token, $ip_address, $user_agent, $expires_at);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    // Set cookie for 30 days
    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
}

/**
 * Check if account is locked due to too many failed attempts
 */
function isAccountLocked($email) {
    $conn = getDBConnection();
    $lockout_time = date('Y-m-d H:i:s', time() - LOCKOUT_TIME);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > ?");
    $stmt->bind_param("ss", $email, $lockout_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $row['attempt_count'] >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Log login attempt
 */
function logLoginAttempt($email, $success, $failure_reason = null) {
    try {
        $conn = getDBConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success, failure_reason) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $email, $ip_address, $success, $failure_reason);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silently fail if there's an error
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

/**
 * Register new user
 */
function emailAlreadyRegistered($email) {
    $conn = getDBConnection();
    if (!$conn) {
        return false;
    }
    $email = strtolower(trim((string)$email));
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE LOWER(email) = ? LIMIT 1");
    if (!$stmt) {
        $conn->close();
        return false;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    $conn->close();
    return $exists;
}

function registerUser($email, $password, $full_name, $phone_number = null) {
    $conn = getDBConnection();
    $email = strtolower(trim((string)$email));

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'This email is already registered. Please use a different email or login.', 'code' => 'email_exists'];
    }
    $stmt->close();
    
    // Validate password length
    if (strlen($password) < 8) {
        $conn->close();
        return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
    }
    
    // Validate password complexity
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $conn->close();
        return ['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number'];
    }
    
    // Hash password with bcrypt - cost of 12 for security
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert new user with email_verified=1 and status='active' so they can login immediately
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, full_name, phone_number, user_type, status, email_verified) VALUES (?, ?, ?, ?, 'vendor', 'active', 1)");
    $stmt->bind_param("ssss", $email, $password_hash, $full_name, $phone_number);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        return [
            'success' => true, 
            'message' => 'Registration successful! You can now login with your credentials.', 
            'user_id' => $user_id
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Registration failed: ' . $error];
    }
}

/**
 * Ensure approval_notifications table exists
 */
function ensureApprovalNotificationsTable($conn) {
    if (!$conn) {
        return;
    }

    $createSql = "
        CREATE TABLE IF NOT EXISTS approval_notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            vendor_id INT DEFAULT NULL,
            notification_type ENUM('approval','rejection','incomplete','pending_review','profile_required','deadline_reminder','resubmission') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
            action_required BOOLEAN DEFAULT FALSE,
            action_url VARCHAR(500) DEFAULT NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_vendor_id (vendor_id),
            INDEX idx_notification_type (notification_type),
            INDEX idx_is_read (is_read),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $conn->query($createSql);
    
    // Update existing table to include 'incomplete' in ENUM if it doesn't exist
    try {
        $checkResult = $conn->query("SHOW COLUMNS FROM approval_notifications LIKE 'notification_type'");
        if ($checkResult && $checkResult->num_rows > 0) {
            $row = $checkResult->fetch_assoc();
            if (strpos($row['Type'], 'incomplete') === false) {
                $conn->query("ALTER TABLE approval_notifications MODIFY COLUMN notification_type ENUM('approval','rejection','incomplete','pending_review','profile_required','deadline_reminder','resubmission') NOT NULL");
            }
        }
    } catch (Exception $e) {
        // Table might not exist yet, which is fine
        error_log("ENUM update check: " . $e->getMessage());
    }

    try {
        $receiptCol = $conn->query("SHOW COLUMNS FROM approval_notifications LIKE 'receipt_id'");
        if ($receiptCol && $receiptCol->num_rows === 0) {
            $conn->query("ALTER TABLE approval_notifications ADD COLUMN receipt_id INT NULL DEFAULT NULL AFTER vendor_id");
            $conn->query("ALTER TABLE approval_notifications ADD INDEX idx_receipt_id (receipt_id)");
        }
    } catch (Exception $e) {
        error_log("receipt_id column check: " . $e->getMessage());
    }
}

/**
 * Record an approval/rejection notification for a user
 */
function recordApprovalNotification($conn, $user_id, $vendor_id, $type, $title, $message, $priority = 'medium', $action_required = false, $action_url = null, $receipt_id = null) {
    if (!$conn || !$user_id) {
        error_log("recordApprovalNotification: Invalid parameters - conn: " . ($conn ? 'set' : 'null') . ", user_id: $user_id");
        return false;
    }

    try {
        ensureApprovalNotificationsTable($conn);

        $action_required_flag = $action_required ? 1 : 0;
        $action_url = $action_url ?? 'notification.php';
        $receipt_id_bind = ($receipt_id !== null && (int)$receipt_id > 0) ? (int)$receipt_id : 0;

        $stmt = $conn->prepare("
            INSERT INTO approval_notifications
            (user_id, vendor_id, receipt_id, notification_type, title, message, priority, action_required, action_url)
            VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("recordApprovalNotification: Failed to prepare statement - " . $conn->error);
            return false;
        }

        $stmt->bind_param(
            "iiissssis",
            $user_id,
            $vendor_id,
            $receipt_id_bind,
            $type,
            $title,
            $message,
            $priority,
            $action_required_flag,
            $action_url
        );

        $result = $stmt->execute();
        if (!$result) {
            error_log("recordApprovalNotification: Execute failed - " . $stmt->error);
        }
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        error_log("recordApprovalNotification: Exception - " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread approval/rejection notification count for nav badge
 */
function getUnreadApprovalNotificationCount($user_id) {
    if (!$user_id) {
        return 0;
    }

    $conn = getDBConnection();
    if (!$conn) {
        return 0;
    }

    ensureApprovalNotificationsTable($conn);

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM approval_notifications
        WHERE user_id = ?
          AND is_read = 0
          AND notification_type IN ('approval','rejection','incomplete')
    ");

    if (!$stmt) {
        $conn->close();
        return 0;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    return (int)($row['unread_count'] ?? 0);
}

/**
 * Mark approval/rejection notifications as read for a user
 */
function markApprovalNotificationsAsRead($user_id) {
    if (!$user_id) {
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        return;
    }

    ensureApprovalNotificationsTable($conn);

    $stmt = $conn->prepare("
        UPDATE approval_notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE user_id = ?
          AND is_read = 0
          AND notification_type IN ('approval','rejection','incomplete')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    // Check if user_id exists in session
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Logout user and clean up session
 */
function logoutUser() {
    // Remove remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        try {
            $conn = getDBConnection();
            $token = $_COOKIE['remember_token'];
            
            $stmt = $conn->prepare("DELETE FROM login_sessions WHERE session_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log("Failed to delete session token: " . $e->getMessage());
        }
        
        // Delete cookie
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    // Clear session
    session_unset();
    session_destroy();
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: userlogin.php");
        exit();
    }
}

/**
 * Require specific user type
 */
function requireUserType($required_type) {
    requireLogin();
    
    if ($_SESSION['user_type'] !== $required_type) {
        header("Location: unauthorized.php");
        exit();
    }
}

/**
 * Require admin login - redirect to admin login page if not admin
 */
function requireAdminLogin() {
    if (!isLoggedIn()) {
        header("Location: adminlogin.php");
        exit();
    }
    
    if ($_SESSION['user_type'] !== 'admin') {
        header("Location: unauthorized.php");
        exit();
    }
}
?>