<?php
/**
 * Robust Approval System Backend
 * This file handles all approval and rejection actions with comprehensive processing
 */

require_once 'config.php';
require_once 'auth_functions.php';

// Set content type for JSON responses
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Protect this file - require admin login
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? '';
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$session_id = session_id();

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    switch ($action) {
        case 'approve_vendor':
            handleVendorApproval($conn, $admin_id, $admin_name, $ip_address, $user_agent, $session_id);
            break;
            
        case 'reject_vendor':
            handleVendorRejection($conn, $admin_id, $admin_name, $ip_address, $user_agent, $session_id);
            break;
            
        case 'get_vendor_details':
            getVendorDetails($conn);
            break;
            
        case 'get_approval_history':
            getApprovalHistory($conn);
            break;
            
        case 'get_pending_applications':
            getPendingApplications($conn);
            break;
            
        case 'update_vendor_status':
            updateVendorStatus($conn, $admin_id, $ip_address, $user_agent, $session_id);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("Approval backend error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Handle vendor approval with comprehensive processing
 */
function handleVendorApproval($conn, $admin_id, $admin_name, $ip_address, $user_agent, $session_id) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $compliance_score = (int)($_POST['compliance_score'] ?? 75);
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    // Validate compliance score
    if ($compliance_score < 0 || $compliance_score > 100) {
        throw new Exception('Compliance score must be between 0 and 100');
    }
    
    // Validate priority
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
        $priority = 'medium';
    }
    
    // Check if vendor exists and is pending
    $stmt = $conn->prepare("SELECT vendor_id, vendor_name, user_id, status FROM vendors WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$vendor) {
        throw new Exception('Vendor application not found');
    }
    
    if ($vendor['status'] !== 'pending') {
        throw new Exception('This application has already been reviewed');
    }
    
    // Use stored procedure for atomic approval
    $stmt = $conn->prepare("CALL ProcessApproval(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisissss", $vendor_id, $admin_id, $notes, $compliance_score, $priority, $ip_address, $user_agent, $session_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Get the updated vendor info
        $stmt = $conn->prepare("SELECT vendor_name, compliance_score, risk_level FROM vendors WHERE vendor_id = ?");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $updated_vendor = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Vendor application approved successfully!',
            'vendor_id' => $vendor_id,
            'vendor_name' => $updated_vendor['vendor_name'],
            'compliance_score' => $updated_vendor['compliance_score'],
            'risk_level' => $updated_vendor['risk_level'],
            'profile_created' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to approve vendor application: ' . $conn->error);
    }
}

/**
 * Handle vendor rejection with detailed processing
 */
function handleVendorRejection($conn, $admin_id, $admin_name, $ip_address, $user_agent, $session_id) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    if (empty($rejection_reason)) {
        throw new Exception('Rejection reason is required');
    }
    
    // Validate rejection reason
    $valid_reasons = [
        'Incomplete Documentation',
        'Invalid Business License',
        'Missing Required Documents',
        'Incorrect Information',
        'Policy Violation',
        'Other'
    ];
    
    if (!in_array($rejection_reason, $valid_reasons)) {
        $rejection_reason = 'Other';
    }
    
    // Check if vendor exists and is pending
    $stmt = $conn->prepare("SELECT vendor_id, vendor_name, user_id, status FROM vendors WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$vendor) {
        throw new Exception('Vendor application not found');
    }
    
    if ($vendor['status'] !== 'pending') {
        throw new Exception('This application has already been reviewed');
    }
    
    // Use stored procedure for atomic rejection
    $stmt = $conn->prepare("CALL ProcessRejection(?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $vendor_id, $admin_id, $notes, $rejection_reason, $ip_address, $user_agent, $session_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Vendor application rejected.',
            'vendor_id' => $vendor_id,
            'vendor_name' => $vendor['vendor_name'],
            'rejection_reason' => $rejection_reason,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to reject vendor application: ' . $conn->error);
    }
}

/**
 * Get detailed vendor information
 */
function getVendorDetails($conn) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    // Get vendor details with user info
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            u.full_name,
            u.email,
            u.phone_number,
            u.created_at as user_created_at,
            up.profile_image,
            up.business_license,
            up.tax_certificate,
            up.id_document
        FROM vendors v
        JOIN users u ON v.user_id = u.user_id
        LEFT JOIN user_profiles up ON v.vendor_id = up.vendor_id
        WHERE v.vendor_id = ?
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$vendor) {
        throw new Exception('Vendor not found');
    }
    
    // Get documents
    $stmt = $conn->prepare("
        SELECT 
            document_id,
            original_name as document_name,
            file_path as document_path,
            file_type as document_type,
            file_size,
            mime_type,
            upload_date as uploaded_date
        FROM application_documents
        WHERE vendor_id = ? AND is_active = TRUE
        ORDER BY upload_date DESC
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get approval history
    $stmt = $conn->prepare("
        SELECT 
            aa.*,
            u.full_name as admin_name
        FROM approval_actions aa
        JOIN users u ON aa.admin_id = u.user_id
        WHERE aa.vendor_id = ?
        ORDER BY aa.action_date DESC
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $approval_history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'vendor' => $vendor,
        'documents' => $documents,
        'approval_history' => $approval_history
    ]);
}

/**
 * Get approval history for a vendor
 */
function getApprovalHistory($conn) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            aa.*,
            u.full_name as admin_name,
            v.vendor_name
        FROM approval_actions aa
        JOIN users u ON aa.admin_id = u.user_id
        JOIN vendors v ON aa.vendor_id = v.vendor_id
        WHERE aa.vendor_id = ?
        ORDER BY aa.action_date DESC
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

/**
 * Get pending applications
 */
function getPendingApplications($conn) {
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            u.full_name,
            u.email,
            u.phone_number,
            DATEDIFF(NOW(), v.submitted_date) as days_pending
        FROM vendors v
        JOIN users u ON v.user_id = u.user_id
        WHERE v.status = 'pending'
        ORDER BY v.submitted_date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'applications' => $applications
    ]);
}

/**
 * Update vendor status (for admin actions)
 */
function updateVendorStatus($conn, $admin_id, $ip_address, $user_agent, $session_id) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    if (!in_array($new_status, ['pending', 'approved', 'rejected'])) {
        throw new Exception('Invalid status');
    }
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$vendor) {
        throw new Exception('Vendor not found');
    }
    
    $old_status = $vendor['status'];
    
    // Update status
    $stmt = $conn->prepare("UPDATE vendors SET status = ?, reviewed_date = NOW(), reviewed_by = ? WHERE vendor_id = ?");
    $stmt->bind_param("sii", $new_status, $admin_id, $vendor_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log the change
        $stmt = $conn->prepare("
            INSERT INTO approval_actions 
            (vendor_id, admin_id, action_type, status_before, status_after, ip_address, user_agent, session_id) 
            VALUES (?, ?, 'status_change', ?, ?, ?, ?, ?)
        ");
        $action_type = 'status_change';
        $stmt->bind_param("iisssss", $vendor_id, $admin_id, $action_type, $old_status, $new_status, $ip_address, $user_agent, $session_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vendor status updated successfully',
            'vendor_id' => $vendor_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
    } else {
        throw new Exception('Failed to update vendor status');
    }
}
?>
