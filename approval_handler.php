<?php
/**
 * Enhanced Approval Handler
 * This file handles all approval and rejection actions with comprehensive backend processing
 */

require_once 'config.php';
require_once 'auth_functions.php';
require_once __DIR__ . '/includes/stall_slot_map.php';

// Protect this file - require admin login
requireAdminLogin();

// Set content type for JSON responses
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    switch ($action) {
        case 'approve_vendor':
            handleVendorApproval($conn, $admin_id, $admin_name, $ip_address, $user_agent);
            break;
            
        case 'reject_vendor':
            handleVendorRejection($conn, $admin_id, $admin_name, $ip_address, $user_agent);
            break;
            
        case 'upload_profile':
            handleProfileUpload($conn, $admin_id, $admin_name, $ip_address, $user_agent);
            break;
            
        case 'get_vendor_details':
            getVendorDetails($conn);
            break;
            
        case 'get_approval_history':
            getApprovalHistory($conn);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("Approval handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Handle vendor approval with comprehensive processing
 */
function handleVendorApproval($conn, $admin_id, $admin_name, $ip_address, $user_agent) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $compliance_score = (int)($_POST['compliance_score'] ?? 75);
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get vendor details
        $stmt = $conn->prepare("
            SELECT v.*, u.full_name, u.email, u.phone_number 
            FROM vendors v 
            JOIN users u ON v.user_id = u.user_id 
            WHERE v.vendor_id = ?
        ");
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
        
        $old_status = $vendor['status'];
        
        // Update vendor status
        $stmt = $conn->prepare("
            UPDATE vendors 
            SET status = 'approved', 
                reviewed_date = NOW(), 
                reviewed_by = ?, 
                admin_notes = ?,
                approval_priority = ?,
                compliance_score = ?,
                risk_level = CASE 
                    WHEN ? >= 90 THEN 'low'
                    WHEN ? >= 70 THEN 'medium'
                    ELSE 'high'
                END
            WHERE vendor_id = ?
        ");
        $stmt->bind_param("issiiii", $admin_id, $notes, $priority, $compliance_score, $compliance_score, $compliance_score, $vendor_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to approve vendor application');
        }
        $stmt->close();
        
        // Log approval action
        $stmt = $conn->prepare("
            INSERT INTO approval_actions 
            (vendor_id, admin_id, action_type, admin_notes, status_before, status_after, ip_address, user_agent) 
            VALUES (?, ?, 'approve', ?, ?, 'approved', ?, ?)
        ");
        $stmt->bind_param("iissss", $vendor_id, $admin_id, $notes, $old_status, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Create user profile automatically
        $stmt = $conn->prepare("
            INSERT INTO user_profiles (user_id, vendor_id, profile_type, profile_status) 
            VALUES (?, ?, 'vendor', 'active')
            ON DUPLICATE KEY UPDATE 
                vendor_id = VALUES(vendor_id),
                profile_status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("ii", $vendor['user_id'], $vendor_id);
        $stmt->execute();
        $stmt->close();
        
        // Update vendor profile status
        $stmt = $conn->prepare("
            UPDATE vendors 
            SET profile_uploaded = TRUE, 
                profile_upload_date = NOW() 
            WHERE vendor_id = ?
        ");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $stmt->close();
        
        // Create notification
        $apply_loc = trim(($vendor['section'] ?? '') . ((($vendor['section'] ?? '') !== '' && ($vendor['block'] ?? '') !== '') ? ', Block ' : ((($vendor['block'] ?? '') !== '') ? 'Block ' : '')) . ($vendor['block'] ?? ''));
        if ($apply_loc === '') {
            $apply_loc = $vendor['vendor_name'] ?? 'your stall';
        }
        $title = "Application Approved!";
        $message = "Your application for {$apply_loc} has been approved.";
        if (!empty($notes)) {
            $message .= "\n\nAdmin Notes: " . $notes;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO approval_notifications 
            (user_id, vendor_id, notification_type, title, message, priority, action_required) 
            VALUES (?, ?, 'approval', ?, ?, 'high', FALSE)
        ");
        $stmt->bind_param("iisss", $vendor['user_id'], $vendor_id, $title, $message);
        $stmt->execute();
        $stmt->close();
        
        // Log activity
        $stmt = $conn->prepare("
            INSERT INTO user_activity_log 
            (user_id, activity_type, details, ip_address, user_agent) 
            VALUES (?, 'vendor_approved', ?, ?, ?)
        ");
        $details = "Approved vendor application ID: {$vendor_id} - {$vendor['vendor_name']} (Score: {$compliance_score})";
        $stmt->bind_param("isss", $admin_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();

        dpmAssignVendorSlotOnApproval($conn, $vendor_id, (string) $admin_name);
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Vendor application approved successfully!',
            'vendor_id' => $vendor_id,
            'compliance_score' => $compliance_score,
            'profile_created' => true
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Handle vendor rejection with detailed processing
 */
function handleVendorRejection($conn, $admin_id, $admin_name, $ip_address, $user_agent) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    if (empty($rejection_reason)) {
        throw new Exception('Rejection reason is required');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get vendor details
        $stmt = $conn->prepare("
            SELECT v.*, u.full_name, u.email, u.phone_number 
            FROM vendors v 
            JOIN users u ON v.user_id = u.user_id 
            WHERE v.vendor_id = ?
        ");
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
        
        $old_status = $vendor['status'];
        
        // Update vendor status
        $stmt = $conn->prepare("
            UPDATE vendors 
            SET status = 'rejected', 
                reviewed_date = NOW(), 
                reviewed_by = ?, 
                admin_notes = ?,
                risk_level = 'high'
            WHERE vendor_id = ?
        ");
        $stmt->bind_param("isi", $admin_id, $notes, $vendor_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to reject vendor application');
        }
        $stmt->close();
        
        // Log rejection action
        $stmt = $conn->prepare("
            INSERT INTO approval_actions 
            (vendor_id, admin_id, action_type, admin_notes, rejection_reason, status_before, status_after, ip_address, user_agent) 
            VALUES (?, ?, 'reject', ?, ?, ?, 'rejected', ?, ?)
        ");
        $stmt->bind_param("iisssss", $vendor_id, $admin_id, $notes, $rejection_reason, $old_status, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Create notification
        $title = "Application Rejected";
        $message = "Your application for {$vendor['vendor_name']} has been rejected.\n\nReason: {$rejection_reason}";
        if (!empty($notes)) {
            $message .= "\n\nAdditional Notes: " . $notes;
        }
        $message .= "\n\nYou may reapply with the necessary corrections.";
        
        $stmt = $conn->prepare("
            INSERT INTO approval_notifications 
            (user_id, vendor_id, notification_type, title, message, priority, action_required) 
            VALUES (?, ?, 'rejection', ?, ?, 'high', TRUE)
        ");
        $stmt->bind_param("iisss", $vendor['user_id'], $vendor_id, $title, $message);
        $stmt->execute();
        $stmt->close();
        
        // Log activity
        $stmt = $conn->prepare("
            INSERT INTO user_activity_log 
            (user_id, activity_type, details, ip_address, user_agent) 
            VALUES (?, 'vendor_rejected', ?, ?, ?)
        ");
        $details = "Rejected vendor application ID: {$vendor_id} - {$vendor['vendor_name']} (Reason: {$rejection_reason})";
        $stmt->bind_param("isss", $admin_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Vendor application rejected.',
            'vendor_id' => $vendor_id,
            'rejection_reason' => $rejection_reason
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Handle profile upload for approved vendors
 */
function handleProfileUpload($conn, $admin_id, $admin_name, $ip_address, $user_agent) {
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $profile_type = $_POST['profile_type'] ?? '';
    
    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID');
    }
    
    if (empty($profile_type)) {
        throw new Exception('Profile type is required');
    }
    
    // Check if vendor is approved
    $stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$vendor || $vendor['status'] !== 'approved') {
        throw new Exception('Vendor must be approved to upload profile');
    }
    
    // Handle file upload
    $upload_dir = "uploads/profiles/{$profile_type}/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_files = [];
    
    if (isset($_FILES['profile_files'])) {
        $files = $_FILES['profile_files'];
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $files['name'][$i];
                $file_tmp = $files['tmp_name'][$i];
                $file_size = $files['size'][$i];
                $file_type = $files['type'][$i];
                
                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = $vendor_id . '_' . time() . '_' . $i . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $uploaded_files[] = [
                        'original_name' => $file_name,
                        'file_name' => $new_filename,
                        'file_path' => $file_path,
                        'file_size' => $file_size,
                        'file_type' => $file_type
                    ];
                }
            }
        }
    }
    
    if (empty($uploaded_files)) {
        throw new Exception('No files were uploaded');
    }
    
    // Update user profile with uploaded files
    $stmt = $conn->prepare("
        UPDATE user_profiles 
        SET {$profile_type} = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE vendor_id = ?
    ");
    $first_file = $uploaded_files[0]['file_path'];
    $stmt->bind_param("si", $first_file, $vendor_id);
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log 
        (user_id, activity_type, details, ip_address, user_agent) 
        VALUES (?, 'profile_uploaded', ?, ?, ?)
    ");
    $details = "Uploaded {$profile_type} for vendor ID: {$vendor_id}";
    $stmt->bind_param("isss", $admin_id, $details, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile uploaded successfully!',
        'uploaded_files' => $uploaded_files
    ]);
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
?>
