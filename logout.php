<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'] ?? 'Unknown';
    $user_type = $_SESSION['user_type'] ?? 'unknown';
    
    // Log to database (optional)
    try {
        $conn = getDBConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, ip_address, details) VALUES (?, 'logout', ?, ?)");
        if ($stmt === false) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        $details = "User logged out: $email (Type: $user_type)";
        $stmt->bind_param("iss", $user_id, $ip_address, $details);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silent fail - logging is not critical
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

// Capture redirect target before session is destroyed
$redirect_url = 'index.php';
if (!empty($_GET['redirect'])) {
    $allowed = ['index.php', 'userlogin.php'];
    $requested = basename($_GET['redirect']);
    if (in_array($requested, $allowed, true)) {
        $redirect_url = $requested;
    }
}

// Perform logout
logoutUser();

// Redirect to login/home page with success message
header('Location: ' . $redirect_url . '?message=logout_success');
exit();
?>