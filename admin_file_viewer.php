<?php
/**
 * Admin File Viewer - Secure file viewing for admin panel
 * This file handles secure file viewing with proper authentication and validation
 */

require_once 'config.php';
require_once 'auth_functions.php';

// Protect this page - require admin login
requireAdminLogin();

// Get the file path from the request
$file_path = $_GET['file'] ?? '';
$vendor_id = (int)($_GET['vendor_id'] ?? 0);

// Validate inputs
if (empty($file_path) || $vendor_id <= 0) {
    http_response_code(400);
    die('Invalid request parameters');
}

try {
    $conn = getDBConnection();
    
    // Verify the file belongs to the specified vendor and admin has access
    $stmt = $conn->prepare("
        SELECT 
            ad.file_path,
            ad.original_name,
            ad.file_type,
            ad.mime_type,
            ad.file_size,
            v.vendor_id,
            v.vendor_name,
            v.status
        FROM application_documents ad
        JOIN vendors v ON ad.vendor_id = v.vendor_id
        WHERE ad.vendor_id = ? 
        AND ad.file_path = ? 
        AND ad.is_active = TRUE
    ");
    
    $stmt->bind_param("is", $vendor_id, $file_path);
    $stmt->execute();
    $result = $stmt->get_result();
    $file_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$file_info) {
        http_response_code(404);
        die('File not found or access denied');
    }
    
    // Check if file exists on disk
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on disk');
    }
    
    // Security check - ensure file is within allowed directories
    $allowed_dirs = [
        'uploads/applications/',
        'uploads/renewals/',
        'uploads/admin/'
    ];
    
    $file_allowed = false;
    foreach ($allowed_dirs as $dir) {
        if (strpos($file_path, $dir) === 0) {
            $file_allowed = true;
            break;
        }
    }
    
    if (!$file_allowed) {
        http_response_code(403);
        die('Access denied - file outside allowed directories');
    }
    
    // Get file info
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Set appropriate headers based on file type
    $mime_type = $file_info['mime_type'];
    
    // Additional security - validate MIME type
    $allowed_mime_types = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ];
    
    if (!in_array($mime_type, $allowed_mime_types)) {
        http_response_code(403);
        die('File type not allowed');
    }
    
    // Set headers for file download/viewing
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: inline; filename="' . $file_info['original_name'] . '"');
    header('Cache-Control: private, max-age=3600');
    header('Pragma: cache');
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Log the file access
    try {
        $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address, user_agent) VALUES (?, 'file_viewed', ?, ?, ?)");
        $admin_id = $_SESSION['user_id'];
        $details = "Viewed file: {$file_info['original_name']} for vendor: {$file_info['vendor_name']} (ID: {$vendor_id})";
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $log_stmt->bind_param("isss", $admin_id, $details, $ip_address, $user_agent);
        $log_stmt->execute();
        $log_stmt->close();
    } catch (Exception $e) {
        // Log error but don't stop file serving
        error_log("Failed to log file access: " . $e->getMessage());
    }
    
    // Output the file
    readfile($file_path);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("File viewer error: " . $e->getMessage());
    die('Internal server error');
}
?>