<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once 'includes/vendor_resubmit_helpers.php';
require_once 'includes/receipt_resubmit_helpers.php';

// Protect this page - require login
requireLogin();

// Get user information from session
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';

// Extract first name only for navbar display
$first_name = explode(' ', trim($user_name))[0] ?? 'User';

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed. Please try again later.");
}

// Unread count for nav badge only
$unread_notifications_count = getUnreadApprovalNotificationCount($user_id);

// Handle AJAX requests for editing applications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_vendor_data') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
        
        if ($vendor_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid vendor ID']);
            exit;
        }
        
        $conn = getDBConnection();
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("SELECT vendor_id, vendor_name, section, block, application_type, status, submitted_date FROM vendors WHERE vendor_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $vendor_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vendor = $result->fetch_assoc();
            $stmt->close();
            
            if (!$vendor) {
                echo json_encode(['success' => false, 'message' => 'Application not found']);
                exit;
            }
            
            // Get existing documents (match vendor_id or legacy application_id)
            $doc_stmt = $conn->prepare("SELECT document_id, original_name, file_name, file_path, file_type, file_size, upload_date FROM application_documents WHERE is_active = 1 AND (vendor_id = ? OR application_id = ?) ORDER BY upload_date ASC");
            $doc_stmt->bind_param("ii", $vendor_id, $vendor_id);
            $doc_stmt->execute();
            $doc_result = $doc_stmt->get_result();
            $documents = [];
            while ($row = $doc_result->fetch_assoc()) {
                if (!empty($row['file_path'])) {
                    $row['file_path'] = str_replace('\\', '/', $row['file_path']);
                    $row['file_path'] = ltrim($row['file_path'], '/');
                }
                if (!empty($row['file_type'])) {
                    $row['file_type'] = strtolower($row['file_type']);
                }
                $documents[] = $row;
            }
            $doc_stmt->close();
            
            // Get requirements (always from DB so admin edits appear immediately)
            $requirements = getApplicationRequirements(normalizeApplicationType($vendor['application_type'] ?? 'new'));
            
            $conn->close();
            
            echo json_encode([
                'success' => true,
                'vendor' => $vendor,
                'documents' => $documents,
                'requirements' => $requirements
            ]);
        } catch (Exception $e) {
            if ($conn) $conn->close();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_receipt_data') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $receipt_id = isset($_POST['receipt_id']) ? (int)$_POST['receipt_id'] : 0;

        if ($receipt_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid receipt ID']);
            exit;
        }

        $conn = getDBConnection();
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $stmt = $conn->prepare("SELECT id, section, block, notes, receipt_file_path, status, created_at FROM receipts WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->bind_param('ii', $receipt_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $receipt = $result->fetch_assoc();
            $stmt->close();
            $conn->close();

            if (!$receipt) {
                echo json_encode(['success' => false, 'message' => 'Receipt not found']);
                exit;
            }

            if (!empty($receipt['receipt_file_path'])) {
                $receipt['receipt_file_path'] = ltrim(str_replace('\\', '/', $receipt['receipt_file_path']), '/');
            }

            $notes_raw = trim((string)($receipt['notes'] ?? ''));
            $receipt['admin_feedback'] = '';
            $receipt['user_notes'] = $notes_raw;
            if (stripos($notes_raw, 'Invalid Reason:') === 0) {
                $receipt['admin_feedback'] = $notes_raw;
                $receipt['user_notes'] = '';
            }

            echo json_encode(['success' => true, 'receipt' => $receipt]);
        } catch (Exception $e) {
            if ($conn) {
                $conn->close();
            }
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_receipt') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        $receipt_id = isset($_POST['receipt_id']) ? (int)$_POST['receipt_id'] : 0;
        $raw_notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';
        $notes = function_exists('mb_substr')
            ? mb_substr($raw_notes, 0, 2000, 'UTF-8')
            : substr($raw_notes, 0, 2000);

        if ($receipt_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid receipt ID']);
            exit;
        }

        $conn = getDBConnection();
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }

        $old_path_db = null;
        try {
            $stmt = $conn->prepare("SELECT id, status, section, block, receipt_file_path FROM receipts WHERE id = ? AND user_id = ? LIMIT 1");
            if (!$stmt) {
                throw new Exception('Database error');
            }
            $stmt->bind_param('ii', $receipt_id, $user_id);
            $stmt->execute();
            $receipt_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$receipt_row) {
                throw new Exception('Receipt not found');
            }

            $status = strtolower(trim((string)($receipt_row['status'] ?? '')));
            if ($status !== 'rejected') {
                throw new Exception('Only invalid receipts can be edited and resubmitted.');
            }

            $old_path_db = trim((string)($receipt_row['receipt_file_path'] ?? ''));
            $new_path_db = null;

            if (isset($_FILES['receipt_file']) && is_array($_FILES['receipt_file']) && ($_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = dpmReceiptStoreUploadedFile($_FILES['receipt_file'], (int)$user_id);
                if (!$stored['ok']) {
                    throw new Exception($stored['message'] ?? 'Failed to save uploaded file.');
                }
                $new_path_db = $stored['path_db'];
            }

            if ($new_path_db === null && $old_path_db === '') {
                throw new Exception('Please upload a receipt image.');
            }

            if ($new_path_db !== null) {
                $upd = $conn->prepare("UPDATE receipts SET status = 'pending', notes = ?, receipt_file_path = ?, reviewed_by = NULL, reviewed_at = NULL WHERE id = ? AND user_id = ?");
                if (!$upd) {
                    dpmReceiptDeleteFileIfExists($new_path_db);
                    throw new Exception('Database error: ' . $conn->error);
                }
                $upd->bind_param('ssii', $notes, $new_path_db, $receipt_id, $user_id);
            } else {
                $upd = $conn->prepare("UPDATE receipts SET status = 'pending', notes = ?, reviewed_by = NULL, reviewed_at = NULL WHERE id = ? AND user_id = ?");
                if (!$upd) {
                    throw new Exception('Database error: ' . $conn->error);
                }
                $upd->bind_param('sii', $notes, $receipt_id, $user_id);
            }

            if (!$upd->execute()) {
                if ($new_path_db !== null) {
                    dpmReceiptDeleteFileIfExists($new_path_db);
                }
                throw new Exception('Failed to update receipt: ' . $upd->error);
            }
            $upd->close();

            if ($new_path_db !== null && $old_path_db !== '' && $old_path_db !== $new_path_db) {
                dpmReceiptDeleteFileIfExists($old_path_db);
            }

            $section = trim((string)($receipt_row['section'] ?? ''));
            $block = trim((string)($receipt_row['block'] ?? ''));
            $loc = trim(implode(', ', array_filter([
                $section !== '' ? $section : '',
                $block !== '' ? 'Block ' . $block : '',
            ], static function ($part) {
                return $part !== '';
            })));
            if ($loc === '') {
                $loc = 'your stall';
            }

            $receipt_message = "Your receipt for {$loc} has been updated and is now pending review. Please wait for the approval.";
            recordApprovalNotification(
                $conn,
                (int)$user_id,
                null,
                'pending_review',
                'Receipt Resubmitted',
                $receipt_message,
                'medium',
                false,
                'notification.php',
                $receipt_id
            );

            $det = 'Receipt updated';
            if ($loc !== '' && $loc !== 'your stall') {
                $det .= ': ' . str_replace(', Block ', ' · ', $loc);
            }
            dpmLogReceiptActivity($conn, (int)$user_id, 'receipt_resubmit', $det);

            $conn->close();
            try {
                require_once __DIR__ . '/includes/submission_notify_email.php';
                $who = dpm_registered_recipient((int) $user_id);
                $mailTo = $who['email'] !== '' ? $who['email'] : trim((string) $user_email);
                $mailName = $who['name'] !== '' ? $who['name'] : (string) $user_name;
                if ($mailTo !== '') {
                    dpm_notify_submission_email('receipt', $mailTo, $mailName, [
                        'action' => 'updated',
                        'receipt_id' => $receipt_id,
                        'detail_line' => $det,
                    ]);
                }
            } catch (Throwable $mailEx) {
                error_log('receipt resubmit email: ' . $mailEx->getMessage());
            }
            echo json_encode(['success' => true, 'message' => 'Receipt updated successfully. It is pending review again.']);
        } catch (Exception $e) {
            if ($conn) {
                $conn->close();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update_application') {
        header('Content-Type: application/json');
        $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
        $full_name = trim($_POST['full_name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $block = trim($_POST['block'] ?? '');
        $application_type = $_POST['application_type'] ?? 'new';
        
        if ($vendor_id <= 0 || empty($full_name) || empty($section) || empty($block)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        $conn = getDBConnection();
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        $update_tx_active = false;
        try {
            // Start transaction for atomicity
            $conn->autocommit(false);
            $update_tx_active = true;
            
            // Ensure review columns exist (admin panel relies on them; keeps display in sync after vendor resubmit)
            $conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0");
            $conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending'");
            
            // Ensure application_documents table exists with correct structure
            $conn->query("CREATE TABLE IF NOT EXISTS application_documents (
                document_id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NULL,
                vendor_id INT NOT NULL,
                stall_number INT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(50) NOT NULL,
                file_size BIGINT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                upload_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_vendor_id (vendor_id),
                INDEX idx_application_id (application_id),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // Check if stall_number column exists, if not add it
            $colCheck = $conn->query("SHOW COLUMNS FROM application_documents LIKE 'stall_number'");
            if ($colCheck && $colCheck->num_rows === 0) {
                $conn->query("ALTER TABLE application_documents ADD COLUMN stall_number INT NULL AFTER vendor_id");
            }
            
            // Block only final approvals — incomplete applications must stay editable so vendors can fix and resubmit
            $status_check_stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ? AND user_id = ? AND application_type = ?");
            $status_check_stmt->bind_param("iis", $vendor_id, $user_id, $application_type);
            $status_check_stmt->execute();
            $status_result = $status_check_stmt->get_result();
            $status_row = $status_result->fetch_assoc();
            $status_check_stmt->close();
            
            if ($status_row && ($status_row['status'] ?? '') === 'approved') {
                error_log("BLOCKED: Attempt to update vendor_id=$vendor_id with status=approved - admin approval preserved");
                throw new Exception("Cannot update application: This application has already been approved by an administrator.");
            }

            $status_before = $status_row['status'] ?? 'incomplete';
            dpmEnsureVendorResubmitTrigger($conn);
            dpmDisableLegacyStatusAutoFix($conn);
            
            // Update vendor information - reset to pending for admin queue (clear review flags)
            $stmt = $conn->prepare("
                UPDATE vendors 
                SET vendor_name = ?, section = ?, block = ?, 
                    status = 'pending',
                    review_status = 'pending',
                    is_reviewed = 0,
                    reviewed_date = NULL,
                    reviewed_by = NULL,
                    admin_notes = NULL,
                    submitted_date = NOW() 
                WHERE vendor_id = ? AND user_id = ? AND application_type = ?
                AND status <> 'approved'
            ");
            $stmt->bind_param("sssiis", $full_name, $section, $block, $vendor_id, $user_id, $application_type);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update vendor: " . $stmt->error);
            }
            if ($stmt->affected_rows === 0) {
                throw new Exception("Unable to update application. Please refresh the page and try again.");
            }
            $stmt->close();

            dpmLogVendorResubmit($conn, $vendor_id, $user_id, $status_before, 'notification.php');
            
            $deleted_count = 0;
            $uploaded_count = 0;
            $uploaded_files = [];
            
            // Handle file deletions if any
            if (isset($_POST['delete_documents']) && is_array($_POST['delete_documents']) && count($_POST['delete_documents']) > 0) {
                foreach ($_POST['delete_documents'] as $doc_id) {
                    $doc_id = (int)$doc_id;
                    if ($doc_id > 0) {
                        // Get file path before deleting
                        $get_file_stmt = $conn->prepare("SELECT file_path FROM application_documents WHERE document_id = ? AND vendor_id = ? AND is_active = 1");
                        $get_file_stmt->bind_param("ii", $doc_id, $vendor_id);
                        $get_file_stmt->execute();
                        $file_result = $get_file_stmt->get_result();
                        if ($file_row = $file_result->fetch_assoc()) {
                            // Delete physical file
                            if (file_exists($file_row['file_path'])) {
                                @unlink($file_row['file_path']);
                            }
                        }
                        $get_file_stmt->close();
                        
                        // Soft delete from database
                        $del_stmt = $conn->prepare("UPDATE application_documents SET is_active = 0 WHERE document_id = ? AND vendor_id = ?");
                        $del_stmt->bind_param("ii", $doc_id, $vendor_id);
                        if ($del_stmt->execute()) {
                            $deleted_count++;
                        }
                        $del_stmt->close();
                    }
                }
            }
            
            // Handle file uploads - STRONG BACKEND APPROACH
            $upload_dir = 'uploads/applications/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            // Check if files are being uploaded
            if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
                $file_count = count($_FILES['documents']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    // Skip if no file or upload error
                    if (!isset($_FILES['documents']['name'][$i]) || $_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    
                    $file_name = $_FILES['documents']['name'][$i];
                    $file_tmp = $_FILES['documents']['tmp_name'][$i];
                    $file_size = $_FILES['documents']['size'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Validate file extension
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                    if (!in_array($file_ext, $allowed_extensions)) {
                        error_log("WARNING: Invalid file extension - vendor_id=$vendor_id, file=$file_name, ext=$file_ext");
                        continue;
                    }
                    
                    // Validate file size (5MB max)
                    if ($file_size > 5 * 1024 * 1024) {
                        error_log("WARNING: File too large - vendor_id=$vendor_id, file=$file_name, size=$file_size");
                        continue;
                    }
                    
                    // Validate file exists
                    if (!file_exists($file_tmp)) {
                        error_log("ERROR: Temporary file not found - vendor_id=$vendor_id, file=$file_name");
                        continue;
                    }
                    
                    // Generate unique filename
                    $new_filename = $vendor_id . '_' . time() . '_' . $i . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
                    $file_path = $upload_dir . $new_filename;
                    
                    // Move uploaded file
                    if (!move_uploaded_file($file_tmp, $file_path)) {
                        error_log("ERROR: Failed to move uploaded file - vendor_id=$vendor_id, file=$file_name, tmp=$file_tmp, dest=$file_path");
                        continue;
                    }
                    
                    // Verify file was moved successfully
                    if (!file_exists($file_path)) {
                        error_log("ERROR: File not found after move - vendor_id=$vendor_id, file=$file_name, path=$file_path");
                        continue;
                    }
                    
                    // Get mime type
                    $mime_type = $_FILES['documents']['type'][$i] ?: 'application/octet-stream';
                    $stall_num = 1; // Single application/renewal = stall 1
                    
                    // Insert into database
                    $doc_stmt = $conn->prepare("INSERT INTO application_documents (application_id, vendor_id, stall_number, original_name, file_name, file_path, file_type, file_size, mime_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    
                    if (!$doc_stmt) {
                        @unlink($file_path); // Clean up file
                        throw new Exception("Failed to prepare INSERT statement: " . $conn->error);
                    }
                    
                    $doc_stmt->bind_param("iiissssis", $vendor_id, $vendor_id, $stall_num, $file_name, $new_filename, $file_path, $file_ext, $file_size, $mime_type);
                    
                    if (!$doc_stmt->execute()) {
                        @unlink($file_path); // Clean up file
                        $doc_stmt->close();
                        throw new Exception("Failed to insert document: " . $doc_stmt->error);
                    }
                    
                    $document_id = $conn->insert_id;
                    $uploaded_count++;
                    $uploaded_files[] = [
                        'document_id' => $document_id,
                        'file_name' => $file_name,
                        'file_path' => $file_path
                    ];
                    
                    error_log("SUCCESS: File uploaded and inserted - vendor_id=$vendor_id, document_id=$document_id, file=$file_name");
                    
                    $doc_stmt->close();
                    
                    // Verify the insert was successful by querying it back
                    $verify_stmt = $conn->prepare("SELECT document_id FROM application_documents WHERE document_id = ? AND vendor_id = ? AND is_active = 1");
                    $verify_stmt->bind_param("ii", $document_id, $vendor_id);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    if ($verify_result->num_rows === 0) {
                        error_log("WARNING: Document insert verification failed - document_id=$document_id, vendor_id=$vendor_id");
                    }
                    $verify_stmt->close();
                }
            }
            
            // Commit transaction
            if (!$conn->commit()) {
                throw new Exception("Failed to commit transaction: " . $conn->error);
            }
            
            // Set autocommit back to true
            $conn->autocommit(true);
            
            // Log activity
            try {
                $activity_type = 'application_updated';
                if ($application_type === 'renewal') {
                    $activity_type = 'renewal_updated';
                }
                $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address) VALUES (?, ?, ?, ?)");
                $details = "Updated " . ($application_type === 'renewal' ? 'renewal' : 'application') . ": $full_name - $section";
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $log_stmt->bind_param("isss", $user_id, $activity_type, $details, $ip_address);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
            
            // Create notification for admin that application was updated
            try {
                $conn->query("CREATE TABLE IF NOT EXISTS approval_notifications (
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
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                
                $notification_title = ucfirst($application_type) . " Application Updated";
                $loc = notif_apply_location($section, $block, $full_name);
                if ($application_type === 'renewal') {
                    $notification_message = "Your renewal application for {$loc} has been updated and is now pending review. Please wait for the approval.";
                } else {
                    $notification_message = "Your application for {$loc} has been updated and is now pending review. Please wait for the approval.";
                }
                
                $notif_stmt = $conn->prepare("
                    INSERT INTO approval_notifications 
                    (user_id, vendor_id, notification_type, title, message, priority, action_required, action_url) 
                    VALUES (?, ?, 'pending_review', ?, ?, 'medium', 0, ?)
                ");
                $action_url = $application_type === 'renewal' ? 'renewal.php' : 'application.php';
                if ($notif_stmt) {
                    $notif_stmt->bind_param("iisss", $user_id, $vendor_id, $notification_title, $notification_message, $action_url);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            } catch (Exception $e) {
                error_log("Failed to create notification: " . $e->getMessage());
            }
            
            // Verify files were inserted by querying them back
            $verify_docs_stmt = $conn->prepare("SELECT document_id, original_name, file_path FROM application_documents WHERE vendor_id = ? AND is_active = 1 ORDER BY upload_date DESC LIMIT ?");
            $limit = 50;
            $verify_docs_stmt->bind_param("ii", $vendor_id, $limit);
            $verify_docs_stmt->execute();
            $verify_docs_result = $verify_docs_stmt->get_result();
            $all_documents = [];
            while ($doc_row = $verify_docs_result->fetch_assoc()) {
                $all_documents[] = $doc_row;
            }
            $verify_docs_stmt->close();
            
            $conn->close();
            
            // Build success message with file operation details
            $message = 'Application updated successfully!';
            if ($deleted_count > 0 || $uploaded_count > 0) {
                $file_ops = [];
                if ($deleted_count > 0) {
                    $file_ops[] = "$deleted_count file(s) removed";
                }
                if ($uploaded_count > 0) {
                    $file_ops[] = "$uploaded_count file(s) uploaded";
                }
                $message .= ' ' . implode(', ', $file_ops) . '.';
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'files_deleted' => $deleted_count,
                'files_uploaded' => $uploaded_count,
                'uploaded_files' => $uploaded_files,
                'total_documents' => count($all_documents),
                'vendor_id' => $vendor_id
            ]);
            
        } catch (Exception $e) {
            // Rollback only if a transaction was started (never read $conn->autocommit as a property — it emits PHP notices/HTML and breaks JSON)
            if ($conn) {
                if (!empty($update_tx_active)) {
                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackEx) {
                        error_log('Rollback notice: ' . $rollbackEx->getMessage());
                    }
                }
                $conn->autocommit(true);
            }
            
            error_log("ERROR in update_application: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            
            if ($conn) {
                $conn->close();
            }
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

function normalizeApplicationType($application_type) {
    $type = strtolower(trim((string)$application_type));
    return ($type === 'renewal') ? 'renewal' : 'new';
}

// Function to get requirements from database (same as application.php and renewal.php)
function getApplicationRequirements($application_type = 'new') {
    $application_type = normalizeApplicationType($application_type);
    $conn = getDBConnection();
    if (!$conn) {
        return getDefaultRequirements($application_type);
    }
    
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS application_requirements (
            requirement_id INT AUTO_INCREMENT PRIMARY KEY,
            application_type ENUM('new', 'renewal') NOT NULL,
            requirement_name VARCHAR(500) NOT NULL,
            requirement_description TEXT,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_application_type (application_type),
            INDEX idx_display_order (display_order),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        $stmt = $conn->prepare("SELECT requirement_name, requirement_description FROM application_requirements WHERE application_type = ? AND is_active = 1 ORDER BY display_order ASC, requirement_id ASC");
        $stmt->bind_param("s", $application_type);
        $stmt->execute();
        $result = $stmt->get_result();
        $requirements = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $requirements[] = [
                    'name' => $row['requirement_name'],
                    'description' => $row['requirement_description'] ?? ''
                ];
            }
        }
        
        $stmt->close();
        $conn->close();
        
        if (empty($requirements)) {
            return getDefaultRequirements($application_type);
        }
        
        return $requirements;
    } catch (Exception $e) {
        error_log("Error fetching requirements: " . $e->getMessage());
        $conn->close();
        return getDefaultRequirements($application_type);
    }
}

function getDefaultRequirements($application_type) {
    if ($application_type === 'renewal') {
        return [
            ['name' => 'Client Government Issued ID', 'description' => ''],
            ['name' => 'DTI Business Registration', 'description' => ''],
            ['name' => 'Market Clearance', 'description' => ''],
            ['name' => "Mayor's Permit", 'description' => ''],
            ['name' => 'Renewed Lease of Contract (If expired)', 'description' => '1 Original, 1 Photocopy'],
            ['name' => 'Official Receipt of Payment', 'description' => '']
        ];
    } else {
        return [
            ['name' => 'Client Government Issued Identification Card', 'description' => 'Presentation'],
            ['name' => 'DTI Business Registration', 'description' => '1 Original, 1 Photocopy'],
            ['name' => 'Barangay Clearance', 'description' => '1 Original, 1 Photocopy'],
            ['name' => 'Memorandum of Agreement (Owner)', 'description' => '1 Original, 1 Photocopy'],
            ['name' => 'Lease of Contract (NOT Owner)', 'description' => '1 Original, 1 Xerox']
        ];
    }
}

// Get user statistics from database (for small summary cards)
$stats = [
    'total_applications'    => 0,
    'approved_count'        => 0,
    'pending_count'         => 0,
    'incomplete_count'        => 0,
    'new_applications'      => 0,
    'renewal_applications'  => 0,
];

if ($conn) {
    $recent_applications = [];
    $recent_notifications = [];
    $activity_logs = [];
    $combined_feed = [];
    try {
        $stats_query = "
            SELECT 
                COUNT(*) as total_applications,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_count,
                SUM(CASE WHEN application_type = 'new' THEN 1 ELSE 0 END) as new_applications,
                SUM(CASE WHEN application_type = 'renewal' THEN 1 ELSE 0 END) as renewal_applications
            FROM vendors 
            WHERE user_id = ?
        ";
        $stats_stmt = $conn->prepare($stats_query);
        if ($stats_stmt) {
            $stats_stmt->bind_param("i", $user_id);
            $stats_stmt->execute();
            $stats_result = $stats_stmt->get_result();
            if ($stats_result) {
                $row = $stats_result->fetch_assoc();
                if ($row) {
                    $stats = $row;
                }
            }
            $stats_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching stats: " . $e->getMessage());
    }
    
    try {
        // Recent applications - group by vendor_id to avoid duplicates when editing
    $applications_query = "
        SELECT 
            v.vendor_id,
            v.vendor_name,
            v.section,
            v.application_type,
            v.status,
            v.submitted_date,
            v.reviewed_date,
            v.admin_notes,
            aa.rejection_reason,
            aa.admin_notes as action_notes,
            aa.action_type,
            aa.action_date
        FROM vendors v
        LEFT JOIN (
            SELECT 
                aa1.vendor_id,
                aa1.rejection_reason,
                aa1.admin_notes,
                aa1.action_type,
                aa1.action_date
            FROM approval_actions aa1
            INNER JOIN (
                SELECT vendor_id, MAX(action_date) as max_date
                FROM approval_actions
                GROUP BY vendor_id
            ) aa2 ON aa1.vendor_id = aa2.vendor_id 
                AND aa1.action_date = aa2.max_date
        ) aa ON v.vendor_id = aa.vendor_id
        WHERE v.user_id = ? 
        GROUP BY v.vendor_id
        ORDER BY v.submitted_date DESC, v.vendor_id DESC
        LIMIT 10
    ";
        $apps_stmt = $conn->prepare($applications_query);
        if ($apps_stmt) {
            $apps_stmt->bind_param("i", $user_id);
            $apps_stmt->execute();
            $applications_result = $apps_stmt->get_result();
            $recent_applications = [];
            while ($app = $applications_result->fetch_assoc()) {
                $recent_applications[] = $app;
            }
            $apps_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching applications: " . $e->getMessage());
        $recent_applications = [];
    }
    
    // Recent notifications from approval_notifications table
    // Ensure the table exists
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS approval_notifications (
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
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        error_log("Table creation warning: " . $e->getMessage());
    }
    
    dpmEnsureReceiptNotificationColumn($conn);

    $notifications_query = "
        SELECT 
            n.notification_id,
            n.vendor_id,
            n.receipt_id,
            n.notification_type,
            n.title,
            n.message,
            n.is_read,
            n.created_at,
            n.priority,
            n.action_required,
            n.action_url,
            v.vendor_name,
            COALESCE(r.section, v.section) AS section,
            COALESCE(r.block, v.block) AS block,
            v.stall_number,
            v.application_type,
            COALESCE(r.status, v.status) AS status
        FROM approval_notifications n
        LEFT JOIN vendors v ON n.vendor_id = v.vendor_id
        LEFT JOIN receipts r ON n.receipt_id = r.id
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 15
    ";
    $notifications_stmt = $conn->prepare($notifications_query);
    if ($notifications_stmt) {
        $notifications_stmt->bind_param("i", $user_id);
        $notifications_stmt->execute();
        $notifications_result = $notifications_stmt->get_result();
        $recent_notifications = [];
        while ($notification = $notifications_result->fetch_assoc()) {
            $recent_notifications[] = $notification;
        }
        $notifications_stmt->close();
    }

    // Recent user activity log (apply/renew)
    $activity_logs = [];
    try {
        $activity_stmt = $conn->prepare("
            SELECT activity_type, details, created_at
            FROM user_activity_log
            WHERE user_id = ?
              AND activity_type IN ('application_submitted','application_updated','renewal_submitted','renewal_updated')
            ORDER BY created_at DESC
            LIMIT 10
        ");
        if ($activity_stmt) {
            $activity_stmt->bind_param("i", $user_id);
            $activity_stmt->execute();
            $activity_result = $activity_stmt->get_result();
            while ($row = $activity_result->fetch_assoc()) {
                $activity_logs[] = $row;
            }
            $activity_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching activity logs: " . $e->getMessage());
    }

    $user_vendors = [];
    try {
        $vendor_map_stmt = $conn->prepare("SELECT vendor_id, vendor_name, section, block, stall_number, application_type, status FROM vendors WHERE user_id = ? ORDER BY submitted_date DESC");
        if ($vendor_map_stmt) {
            $vendor_map_stmt->bind_param("i", $user_id);
            $vendor_map_stmt->execute();
            $vendor_map_result = $vendor_map_stmt->get_result();
            while ($vrow = $vendor_map_result->fetch_assoc()) {
                $user_vendors[] = $vrow;
            }
            $vendor_map_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching vendor map: " . $e->getMessage());
    }

    $user_receipts = [];
    try {
        $receipt_stmt = $conn->prepare("SELECT id, section, block, notes, receipt_file_path, created_at, status FROM receipts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        if ($receipt_stmt) {
            $receipt_stmt->bind_param("i", $user_id);
            $receipt_stmt->execute();
            $receipt_result = $receipt_stmt->get_result();
            while ($rrow = $receipt_result->fetch_assoc()) {
                $user_receipts[] = $rrow;
            }
            $receipt_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching user receipts: " . $e->getMessage());
    }

    $pending_receipts = array_values(array_filter($user_receipts, static function ($row) {
        return strtolower(trim((string)($row['status'] ?? ''))) === 'pending';
    }));

    $vendor_submissions = [];
    try {
        $vsub_stmt = $conn->prepare("
            SELECT vendor_id, vendor_name, section, block, stall_number, application_type, status, submitted_date
            FROM vendors
            WHERE user_id = ? AND status IN ('pending', 'incomplete', 'resubmitted')
            ORDER BY submitted_date DESC
            LIMIT 15
        ");
        if ($vsub_stmt) {
            $vsub_stmt->bind_param("i", $user_id);
            $vsub_stmt->execute();
            $vsub_result = $vsub_stmt->get_result();
            while ($vrow = $vsub_result->fetch_assoc()) {
                $vendor_submissions[] = $vrow;
            }
            $vsub_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching vendor submissions: " . $e->getMessage());
    }
    
    // Combine notifications and activity logs into a single feed, sorted by date
    $combined_feed = [];
    foreach ($recent_notifications as $n) {
        $receipt_id = (int)($n['receipt_id'] ?? 0);
        if ($receipt_id <= 0) {
            $receipt_id = notif_link_receipt_id($n, $user_receipts);
        }
        $hay = strtolower(($n['title'] ?? '') . ' ' . ($n['message'] ?? ''));
        if ($receipt_id <= 0 && strpos($hay, 'receipt') !== false) {
            $want_rejected = (($n['notification_type'] ?? '') === 'rejection');
            foreach ($user_receipts as $ur) {
                $ur_status = strtolower(trim((string)($ur['status'] ?? '')));
                if ($want_rejected && $ur_status === 'rejected') {
                    $receipt_id = (int)($ur['id'] ?? 0);
                    if ($receipt_id > 0) {
                        if (empty($n['section'])) {
                            $n['section'] = $ur['section'] ?? '';
                        }
                        if (empty($n['block'])) {
                            $n['block'] = $ur['block'] ?? '';
                        }
                    }
                    break;
                }
                if (!$want_rejected && $ur_status === 'pending') {
                    $receipt_id = (int)($ur['id'] ?? 0);
                    break;
                }
            }
        }
        $combined_feed[] = [
            'type' => 'notification',
            'notification_id' => (int)($n['notification_id'] ?? 0),
            'created_at' => $n['created_at'] ?? null,
            'notification_type' => $n['notification_type'] ?? '',
            'title' => $n['title'] ?? '',
            'message' => $n['message'] ?? '',
            'is_read' => $n['is_read'] ?? 0,
            'vendor_id' => isset($n['vendor_id']) ? (int)$n['vendor_id'] : 0,
            'vendor_name' => $n['vendor_name'] ?? '',
            'section' => $n['section'] ?? '',
            'block' => $n['block'] ?? '',
            'stall_number' => (int)($n['stall_number'] ?? 0),
            'application_type' => $n['application_type'] ?? '',
            'status' => $n['status'] ?? '',
            'receipt_id' => $receipt_id,
        ];
    }
    foreach ($activity_logs as $a) {
        $vendor_id = 0;
        $section = '';
        $block = '';
        $stall_number = 0;
        $application_type = '';
        $status = '';
        $details = $a['details'] ?? '';
        $activity_type = $a['activity_type'] ?? '';

        if ($activity_type === 'receipt_upload') {
            if (preg_match('/Receipt sent:\s*(.+?)(?:\s*·\s*(.+))?$/i', $details, $rm)) {
                $section = trim($rm[1] ?? '');
                $block = trim($rm[2] ?? '');
            }
        } elseif (preg_match('/(?:submitted|updated)\s+(?:new\s+)?(?:renewal\s+)?application:\s*(.+?)\s*-\s*(.+)$/i', $details, $m)
            || preg_match('/:\s*(.+?)\s*-\s*(.+)$/i', $details, $m)) {
            $name = trim($m[1] ?? '');
            $section = trim($m[2] ?? '');
            foreach ($user_vendors as $v) {
                if (strcasecmp(trim($v['vendor_name'] ?? ''), $name) === 0) {
                    $vendor_id = (int)($v['vendor_id'] ?? 0);
                    if ($section === '') {
                        $section = $v['section'] ?? '';
                    }
                    $block = $v['block'] ?? '';
                    $stall_number = (int)($v['stall_number'] ?? 0);
                    $application_type = $v['application_type'] ?? '';
                    $status = $v['status'] ?? '';
                    break;
                }
            }
            if ($vendor_id === 0 && $section !== '') {
                foreach ($user_vendors as $v) {
                    if (strcasecmp(trim($v['section'] ?? ''), $section) === 0) {
                        $vendor_id = (int)($v['vendor_id'] ?? 0);
                        $block = $v['block'] ?? '';
                        $stall_number = (int)($v['stall_number'] ?? 0);
                        $application_type = $v['application_type'] ?? '';
                        $status = $v['status'] ?? '';
                        break;
                    }
                }
            }
            if ($vendor_id === 0 && strpos($activity_type, 'renewal') !== false) {
                foreach ($user_vendors as $v) {
                    if (($v['application_type'] ?? '') === 'renewal') {
                        $vendor_id = (int)($v['vendor_id'] ?? 0);
                        $section = $section ?: ($v['section'] ?? '');
                        $block = $v['block'] ?? '';
                        $stall_number = (int)($v['stall_number'] ?? 0);
                        $application_type = 'renewal';
                        $status = $v['status'] ?? '';
                        break;
                    }
                }
            } elseif ($vendor_id === 0 && strpos($activity_type, 'application') !== false) {
                foreach ($user_vendors as $v) {
                    if (($v['application_type'] ?? '') === 'new') {
                        $vendor_id = (int)($v['vendor_id'] ?? 0);
                        $section = $section ?: ($v['section'] ?? '');
                        $block = $v['block'] ?? '';
                        $stall_number = (int)($v['stall_number'] ?? 0);
                        $application_type = 'new';
                        $status = $v['status'] ?? '';
                        break;
                    }
                }
            }
        }

        $combined_feed[] = [
            'type' => 'activity',
            'created_at' => $a['created_at'] ?? null,
            'activity_type' => $activity_type,
            'details' => $details,
            'vendor_id' => $vendor_id,
            'section' => $section,
            'block' => $block,
            'stall_number' => $stall_number,
            'application_type' => $application_type,
            'status' => $status,
        ];
    }
    foreach ($pending_receipts as $r) {
        $combined_feed[] = [
            'type' => 'receipt_pending',
            'created_at' => $r['created_at'] ?? null,
            'receipt_id' => (int)($r['id'] ?? 0),
            'section' => $r['section'] ?? '',
            'block' => $r['block'] ?? '',
            'notes' => $r['notes'] ?? '',
            'receipt_file_path' => $r['receipt_file_path'] ?? '',
            'status' => $r['status'] ?? 'pending',
        ];
    }
    foreach ($vendor_submissions as $v) {
        $combined_feed[] = [
            'type' => 'vendor_pending',
            'created_at' => $v['submitted_date'] ?? null,
            'vendor_id' => (int)($v['vendor_id'] ?? 0),
            'vendor_name' => $v['vendor_name'] ?? '',
            'section' => $v['section'] ?? '',
            'block' => $v['block'] ?? '',
            'stall_number' => (int)($v['stall_number'] ?? 0),
            'application_type' => $v['application_type'] ?? 'new',
            'status' => $v['status'] ?? 'pending',
        ];
    }
    $combined_feed = notif_deduplicate_feed($combined_feed);
    usort($combined_feed, function($a, $b) {
        return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
    });
    $combined_feed = array_slice($combined_feed, 0, 25);
} else {
    $recent_applications = [];
    $recent_notifications = [];
    $activity_logs = [];
    $combined_feed = [];
}

$notif_count_all = count($combined_feed);
$notif_count_application = 0;
$notif_count_renewal = 0;
$notif_count_receipt = 0;
foreach ($combined_feed as $feed_item_count) {
    $cat_count = notif_feed_category($feed_item_count);
    if ($cat_count === 'application') {
        $notif_count_application++;
    } elseif ($cat_count === 'renewal') {
        $notif_count_renewal++;
    } elseif ($cat_count === 'receipt') {
        $notif_count_receipt++;
    }
}
function notif_feed_priority(array $item): int {
    if (($item['type'] ?? '') === 'receipt_pending') {
        return 1;
    }
    if (($item['type'] ?? '') === 'notification') {
        $nType = $item['notification_type'] ?? '';
        if ($nType === 'pending_review') {
            return 2;
        }
        if (in_array($nType, ['approval', 'rejection', 'incomplete', 'resubmission'], true)) {
            return 10;
        }
        return 5;
    }
    if (($item['type'] ?? '') === 'activity') {
        return 2;
    }
    if (($item['type'] ?? '') === 'vendor_pending') {
        return 1;
    }
    return 3;
}

function notif_link_receipt_id(array $notification, array $receipts): int {
    $hay = strtolower(($notification['title'] ?? '') . ' ' . ($notification['message'] ?? ''));
    if (strpos($hay, 'receipt') === false) {
        return 0;
    }

    $nType = $notification['notification_type'] ?? '';
    $wantStatus = null;
    if ($nType === 'approval') {
        $wantStatus = 'approved';
    } elseif ($nType === 'rejection') {
        $wantStatus = 'rejected';
    } elseif ($nType === 'pending_review') {
        $wantStatus = 'pending';
    }

    [$msgSection, $msgBlock] = notif_parse_section_block($notification['message'] ?? '');

    foreach ($receipts as $receipt) {
        $status = strtolower(trim((string)($receipt['status'] ?? '')));
        if ($wantStatus !== null && $status !== $wantStatus) {
            continue;
        }
        if ($msgSection !== '' && strcasecmp(trim((string)($receipt['section'] ?? '')), $msgSection) !== 0) {
            continue;
        }
        if ($msgBlock !== '' && strcasecmp(trim((string)($receipt['block'] ?? '')), $msgBlock) !== 0) {
            continue;
        }
        return (int)($receipt['id'] ?? 0);
    }

    if ($wantStatus !== null) {
        foreach ($receipts as $receipt) {
            if (strtolower(trim((string)($receipt['status'] ?? ''))) === $wantStatus) {
                return (int)($receipt['id'] ?? 0);
            }
        }
    }

    return 0;
}

function notif_deduplicate_feed(array $feed): array {
    $best = [];
    foreach ($feed as $item) {
        $key = null;
        if (!empty($item['receipt_id'])) {
            $key = 'r:' . (int)$item['receipt_id'];
        } elseif (!empty($item['vendor_id'])) {
            $key = 'v:' . (int)$item['vendor_id'];
        } elseif (!empty($item['notification_id'])) {
            $key = 'n:' . (int)$item['notification_id'];
        } else {
            $key = 'x:' . md5(($item['type'] ?? '') . '|' . ($item['created_at'] ?? '') . '|' . ($item['details'] ?? '') . '|' . ($item['message'] ?? ''));
        }
        $prio = notif_feed_priority($item);
        $ts = strtotime($item['created_at'] ?? '1970-01-01');
        if (!isset($best[$key])) {
            $best[$key] = $item;
            continue;
        }
        $bestPrio = notif_feed_priority($best[$key]);
        $bestTs = strtotime($best[$key]['created_at'] ?? '1970-01-01');
        if ($prio > $bestPrio || ($prio === $bestPrio && $ts > $bestTs)) {
            $best[$key] = $item;
        }
    }
    return array_values($best);
}

function notif_feed_category(array $item): string {
    if (in_array($item['type'] ?? '', ['receipt_pending'], true)) {
        return 'receipt';
    }
    if (($item['type'] ?? '') === 'vendor_pending') {
        return (($item['application_type'] ?? '') === 'renewal') ? 'renewal' : 'application';
    }
    $hay = strtolower(
        ($item['title'] ?? '') . ' ' .
        ($item['message'] ?? '') . ' ' .
        ($item['details'] ?? '') . ' ' .
        ($item['activity_type'] ?? '')
    );
    if (strpos($hay, 'receipt') !== false) {
        return 'receipt';
    }
    if (($item['application_type'] ?? '') === 'renewal') {
        return 'renewal';
    }
    if (!empty($item['activity_type'])) {
        return strpos($item['activity_type'], 'renewal') !== false ? 'renewal' : 'application';
    }
    if (strpos($hay, 'renewal') !== false) {
        return 'renewal';
    }
    return 'application';
}

function notif_parse_section_block(string $text): array {
    $section = '';
    $block = '';
    if (preg_match('/Section:\s*([^,\n]+)(?:,\s*Block:\s*([^\n]+))?/i', $text, $m)) {
        $section = trim($m[1] ?? '');
        $block = trim($m[2] ?? '');
    } elseif (preg_match('/Block:\s*([^\n]+)/i', $text, $m)) {
        $block = trim($m[1] ?? '');
    }
    return [$section, $block];
}

function notif_item_location(array $item): array {
    $section = trim($item['section'] ?? '');
    $block = trim($item['block'] ?? '');
    if ($section === '' || $block === '') {
        $text = ($item['message'] ?? '') . ' ' . ($item['details'] ?? '');
        [$parsedSection, $parsedBlock] = notif_parse_section_block($text);
        if ($section === '') {
            $section = $parsedSection;
        }
        if ($block === '') {
            $block = $parsedBlock;
        }
    }
    if (($item['type'] ?? '') === 'activity' && $section !== '' && $block === '') {
        if (preg_match('/:\s*(.+?)\s*-\s*(.+)$/i', $item['details'] ?? '', $m)) {
            $section = trim($m[2] ?? $section);
        }
    }
    return [$section, $block];
}

function notif_is_pending(array $item): bool {
    if (($item['type'] ?? '') === 'receipt_pending') {
        return true;
    }
    if (($item['type'] ?? '') === 'vendor_pending') {
        return in_array($item['status'] ?? '', ['pending', 'resubmitted'], true);
    }
    if (($item['type'] ?? '') === 'notification') {
        if (($item['notification_type'] ?? '') === 'pending_review') {
            return true;
        }
        $msg = strtolower($item['message'] ?? '');
        return strpos($msg, 'pending review') !== false;
    }
    if (($item['type'] ?? '') === 'activity') {
        $activityType = $item['activity_type'] ?? '';
        return in_array($activityType, [
            'application_submitted',
            'application_updated',
            'renewal_submitted',
            'renewal_updated',
            'receipt_upload',
        ], true);
    }
    return false;
}

function notif_is_updated_pending(array $item): bool {
    if (($item['type'] ?? '') === 'vendor_pending') {
        return false;
    }
    if (($item['type'] ?? '') === 'notification') {
        return stripos($item['message'] ?? '', 'updated') !== false;
    }
    if (($item['type'] ?? '') === 'activity') {
        return strpos($item['activity_type'] ?? '', 'updated') !== false;
    }
    return false;
}

function notif_pending_summary(array $item): string {
    $cat = notif_feed_category($item);
    [$section, $block] = notif_item_location($item);
    $loc = notif_apply_location($section, $block, '');
    $action = notif_is_updated_pending($item) ? 'updated' : 'submitted';

    if ($cat === 'renewal') {
        return "Your renewal application for {$loc} has been {$action} and is now pending review. Please wait for the approval.";
    }
    if ($cat === 'receipt') {
        if ($loc !== '') {
            return "Your receipt for {$loc} has been {$action} and is now pending review. Please wait for the approval.";
        }
        return "Your receipt has been {$action} and is now pending review. Please wait for the approval.";
    }
    return "Your application for {$loc} has been {$action} and is now pending review. Please wait for the approval.";
}

function notif_incomplete_followup(array $item): string {
    $cat = notif_feed_category($item);
    [$section, $block] = notif_item_location($item);
    $loc = notif_apply_location($section, $block, $item['vendor_name'] ?? '');
    if ($loc === '') {
        return '';
    }
    if ($cat === 'renewal') {
        return "Your renewal application for {$loc} has been marked as incomplete by the admin. Please edit it and make sure you submit complete and valid documents.";
    }
    if ($cat === 'receipt') {
        if (notif_is_receipt_invalid($item)) {
            return "Your receipt for {$loc} has been marked as invalid by the admin. Please edit it and resubmit a valid receipt.";
        }
        return "Your receipt for {$loc} has been marked as incomplete by the admin. Please edit it and make sure you submit complete and valid documents.";
    }
    return "Your application for {$loc} has been marked as incomplete by the admin. Please edit it and make sure you submit complete and valid documents.";
}

function notif_can_edit(array $item): bool {
    if (!notif_is_incomplete($item)) {
        return false;
    }
    if (notif_feed_category($item) === 'receipt' && (int)($item['receipt_id'] ?? 0) > 0) {
        $status = strtolower(trim((string)($item['status'] ?? '')));
        return $status === 'rejected';
    }
    return true;
}

function notif_can_view_requirements(array $item): bool {
    if ((int)($item['receipt_id'] ?? 0) > 0) {
        return true;
    }
    if ((int)($item['vendor_id'] ?? 0) > 0) {
        return true;
    }
    $cat = notif_feed_category($item);
    return in_array($cat, ['application', 'renewal', 'receipt'], true);
}

function notif_edit_action(array $item): array {
    $cat = notif_feed_category($item);
    if ($cat === 'receipt') {
        $receiptId = (int)($item['receipt_id'] ?? 0);
        if ($receiptId > 0) {
            return ['type' => 'modal', 'receipt_id' => $receiptId, 'label' => 'Edit'];
        }
        return ['type' => 'link', 'href' => 'notification.php', 'label' => 'Edit'];
    }
    $vendorId = (int)($item['vendor_id'] ?? 0);
    $appType = ($item['application_type'] ?? '') === 'renewal' ? 'renewal' : 'new';
    if ($cat === 'renewal') {
        $appType = 'renewal';
    }
    return ['type' => 'modal', 'vendor_id' => $vendorId, 'application_type' => $appType, 'label' => 'Edit'];
}

function notif_is_incomplete(array $item): bool {
    if (in_array($item['status'] ?? '', ['incomplete'], true)) {
        return true;
    }
    if ($item['type'] !== 'notification') {
        return false;
    }
    $nType = $item['notification_type'] ?? '';
    $msg = strtolower($item['message'] ?? '');
    if ($nType === 'incomplete' || $nType === 'rejection') {
        return true;
    }
    return strpos($msg, 'invalid') !== false && strpos($msg, 'receipt') !== false;
}

function notif_is_receipt_invalid(array $item): bool {
    if (notif_feed_category($item) !== 'receipt') {
        return false;
    }
    $title = strtolower(trim((string)($item['title'] ?? '')));
    if (strpos($title, 'invalid') !== false) {
        return true;
    }
    $msg = strtolower((string)($item['message'] ?? ''));
    if (strpos($msg, 'marked as invalid') !== false || strpos($msg, 'invalid reason:') !== false) {
        return true;
    }
    if (($item['notification_type'] ?? '') === 'rejection') {
        return true;
    }
    return strtolower(trim((string)($item['status'] ?? ''))) === 'rejected';
}

function notif_extract_reason(string $message): string {
    if (preg_match('/(?:Invalid\s+)?Reason:\s*(.+?)(?:\n\n|\nNotes:|\nYou can|$)/is', $message, $m)) {
        return trim($m[1]);
    }
    return '';
}

function notif_apply_location(?string $section, ?string $block, ?string $fallback = ''): string {
    $section = trim((string)$section);
    $block = trim((string)$block);
    if ($section !== '' && $block !== '') {
        return "{$section}, Block {$block}";
    }
    if ($section !== '') {
        return $section;
    }
    if ($block !== '') {
        return "Block {$block}";
    }
    return trim((string)$fallback);
}

function notif_display_summary(array $item): string {
    if (notif_is_pending($item)) {
        return notif_pending_summary($item);
    }
    if ($item['type'] !== 'notification') {
        return trim(notif_card_message($item));
    }
    $message = notif_card_message($item);
    $nType = $item['notification_type'] ?? '';
    if ($nType === 'approval' || stripos($message, 'has been approved') !== false) {
        $loc = notif_apply_location(
            $item['section'] ?? '',
            $item['block'] ?? '',
            $item['vendor_name'] ?? ''
        );
        if ($loc !== '') {
            $cat = notif_feed_category($item);
            if ($cat === 'renewal') {
                return "Your renewal application for {$loc} has been approved.";
            }
            if ($cat === 'receipt') {
                return "Your receipt for {$loc} has been approved.";
            }
            return "Your application for {$loc} has been approved.";
        }
    }
    $lines = preg_split('/\r?\n+/', $message);
    $first = trim($lines[0] ?? '');
    if ($first !== '') {
        return $first;
    }
    return trim($message);
}

function notif_title_dot(array $item): ?string {
    if (notif_is_incomplete($item)) {
        return 'incomplete';
    }
    if ($item['type'] === 'notification' && empty($item['is_read'])) {
        return 'unread';
    }
    if (notif_is_pending($item)) {
        return 'pending';
    }
    return null;
}

function notif_display_date(?string $datetime): string {
    if (empty($datetime)) {
        return 'N/A';
    }
    $ts = strtotime($datetime);
    return $ts !== false ? date('M d, Y', $ts) : 'N/A';
}

function notif_category_label(string $cat): string {
    switch ($cat) {
        case 'renewal':
            return 'RENEWAL';
        case 'receipt':
            return 'RECEIPTS';
        default:
            return 'APPLICATION';
    }
}

function notif_category_icon(string $cat): string {
    switch ($cat) {
        case 'renewal':
            return 'fa-sync-alt';
        case 'receipt':
            return 'fa-receipt';
        default:
            return 'fa-file-alt';
    }
}

function notif_tag_class(string $cat, array $item): string {
    if ($cat === 'receipt') {
        $msg = strtolower(($item['message'] ?? '') . ' ' . ($item['details'] ?? ''));
        if (strpos($msg, 'invalid') !== false) {
            return 'notif-tag--receipt-orange';
        }
        return 'notif-tag--receipt';
    }
    return 'notif-tag--' . $cat;
}

function notif_card_location_line(array $item): string {
    $section = trim((string)($item['section'] ?? ''));
    $block = trim((string)($item['block'] ?? ''));
    $stall = (int)($item['stall_number'] ?? 0);
    $parts = [];
    if ($stall > 0) {
        $parts[] = 'Stall ' . ($block !== '' ? $block . '-' . $stall : (string)$stall);
    } elseif ($block !== '') {
        $parts[] = 'Block ' . $block;
    }
    if ($section !== '') {
        $parts[] = $section;
    }
    return implode(' · ', $parts);
}

function notif_date_group_key(?string $datetime): string {
    if (empty($datetime)) {
        return 'earlier';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return 'earlier';
    }
    $day = date('Y-m-d', $ts);
    if ($day === date('Y-m-d')) {
        return 'today';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'yesterday';
    }
    return 'earlier';
}

function notif_date_group_label(string $key): string {
    switch ($key) {
        case 'today':
            return 'Today';
        case 'yesterday':
            return 'Yesterday';
        default:
            return 'Earlier';
    }
}

function notif_cta_label(array $item, array $edit_action): string {
    $cat = notif_feed_category($item);
    if ($cat === 'renewal') {
        return 'Renew now';
    }
    if ($cat === 'receipt') {
        return notif_is_receipt_invalid($item) ? 'Resubmit receipt' : 'Edit receipt';
    }
    return 'Edit application';
}

function notif_card_title(array $item): string {
    if (notif_is_pending($item)) {
        $cat = notif_feed_category($item);
        if ($cat === 'renewal') {
            return 'Renewal Pending';
        }
        if ($cat === 'receipt') {
            return 'Receipt Pending';
        }
        return 'Application Pending';
    }
    if ($item['type'] === 'notification') {
        $title = trim($item['title'] ?? 'Notification');
        if (notif_is_incomplete($item)) {
            $cat = notif_feed_category($item);
            if ($cat === 'renewal') {
                return 'Renewal Incomplete';
            }
            if ($cat === 'receipt') {
                if (notif_is_receipt_invalid($item)) {
                    return 'Receipt Invalid';
                }
                return 'Receipt Incomplete';
            }
            return 'Application Incomplete';
        }
        return $title;
    }
    return ucwords(str_replace('_', ' ', $item['activity_type'] ?? 'Activity'));
}

function notif_card_message(array $item): string {
    if ($item['type'] === 'notification') {
        $message = $item['message'] ?? '';
        $message = str_replace(['Additional Notes:', 'Additional notes:', 'additional notes:'], 'Notes:', $message);
        return $message;
    }
    $details = $item['details'] ?? '';
    return preg_replace('/:\s*Admin\s*-\s*[^:]*\s*Section\s*/i', '', $details);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - Daet Public Market</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
  <link rel="stylesheet" href="assets/css/profile-darkmode-btn.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background-color: #667eea;
      min-height: 100vh;
      padding: 0;
      margin: 0;
      font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
      position: relative;
      color: #ffffff;
    }

    /* Main Container - Full Width */
    .main-container {
      width: 100%;
      margin: 0;
      padding: 1.5rem;
      padding-top: calc(80px + 1.5rem);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: transparent;
      backdrop-filter: none;
      border-radius: 0;
      border: none;
      box-shadow: none;
      position: relative;
      z-index: 1;
      box-sizing: border-box;
    }

    /* Top Navigation Bar */
    .navbar {
      background: rgba(102, 126, 234, 0.95);
      backdrop-filter: blur(10px);
      padding: 1rem 2rem;
      margin-bottom: 0;
      border-radius: 0;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      border-bottom: 2px solid rgba(255, 255, 255, 0.2);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      width: 100%;
      z-index: 1000;
    }
    
    .navbar-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      max-width: 1400px;
      margin: 0 auto;
      position: relative;
    }
    
    /* Hamburger Menu Button */
    .navbar-toggler {
      display: none;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 10px;
      padding: 0.5rem 0.75rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }
    
    .navbar-toggler:hover {
      border-color: rgba(255, 255, 255, 0.5);
      background: rgba(255, 255, 255, 0.2);
    }
    
    .navbar-toggler-icon {
      background-image: none !important;
      width: 24px;
      height: 18px;
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    
    .navbar-toggler-icon::before {
      content: '';
      display: block;
      height: 3px;
      width: 100%;
      background: white;
      border-radius: 3px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: absolute;
      top: 0;
      z-index: 1;
    }
    
    .navbar-toggler-icon::after {
      content: '';
      display: block;
      height: 3px;
      width: 100%;
      background: white;
      border-radius: 3px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: absolute;
      bottom: 0;
      z-index: 1;
    }
    
    .navbar-toggler-icon span {
      display: block;
      height: 3px;
      width: 100%;
      background: white;
      border-radius: 3px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 1;
    }
    
    .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon::before {
      transform: rotate(45deg) translate(6px, 6px);
      top: 50%;
    }
    
    .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon span {
      opacity: 0;
      transform: translateY(-50%) scaleX(0);
    }
    
    .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon::after {
      transform: rotate(-45deg) translate(6px, -6px);
      bottom: 50%;
    }
    
    /* Left: Logo */
    .navbar-left {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }
    
    .navbar-logo {
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 0.65rem;
    }
    .navbar-logo .brand__stack {
      display: flex;
      flex-direction: column;
      gap: 0.1rem;
    }
    .navbar-logo .brand__text {
      font-family: 'DM Serif Display', Georgia, serif;
      font-weight: 400;
      font-size: clamp(0.88rem, 1.55vw, 1.08rem);
      line-height: 1.15;
      letter-spacing: 0;
      color: #fff;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    .navbar-logo .brand__place {
      display: block;
      font-family: 'Inter', system-ui, sans-serif;
      font-size: 0.62rem;
      letter-spacing: 0.14em;
      font-weight: 700;
      color: rgba(255, 255, 255, 0.78);
      margin-top: 0.15rem;
    }
    .navbar-logo .brand__mark {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      flex-shrink: 0;
      overflow: hidden;
      border: 2px solid rgba(255, 255, 255, 0.85);
      box-sizing: border-box;
    }
    .navbar-logo .brand__mark img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    
    .nav-pills-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      position: fixed;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      height: 80px;
      width: auto;
      z-index: 1001;
      pointer-events: none;
    }
    .nav-pills-wrap .nav-pills-container {
      pointer-events: auto;
    }
    .navbar-center {
      display: none;
    }
    
    /* Right: Calendar, Language, Logout (Desktop) */
    .navbar-right-desktop {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-left: auto;
    }
    
    .navbar-right-desktop .calendar-icon {
      margin-right: 1.5rem;
    }
    
    .navbar-right-desktop .language-selector {
      margin-right: 1.5rem;
      margin-left: -1rem;
    }
    
    .navbar-right-desktop .logout-btn {
      margin-left: 1.5rem;
    }
    
    .nav-pills-container {
      background: rgba(102, 126, 234, 0.3);
      backdrop-filter: blur(10px);
      border-radius: 50px;
      padding: 0.3rem;
      display: flex;
      gap: 0.25rem;
      align-items: center;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .nav-pill {
      padding: 0.6rem 1.5rem;
      border-radius: 50px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.95rem;
      transition: transform 0.25s ease, color 0.25s ease, background 0.25s ease;
      background: transparent;
      border: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transform: scale(1);
      transform-origin: center center;
      will-change: transform;
    }
    
    .nav-pill.active,
    .nav-icon-pill.active {
      background: rgba(255, 255, 255, 0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }
    
    .nav-pill:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.1);
      transform: scale(1.1);
    }

    .nav-pill.active:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: scale(1.1);
    }
    
    .nav-pill i {
      font-size: 1rem;
    }
    
    .nav-icon-pill {
      padding: 0.6rem 1rem;
      border-radius: 50px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: transform 0.25s ease, color 0.25s ease, background 0.25s ease;
      background: transparent;
      border: none;
      font-weight: 500;
      font-size: 0.95rem;
      position: relative;
      transform: scale(1);
      transform-origin: center center;
      will-change: transform;
    }
    
    .nav-icon-pill:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.1);
      transform: scale(1.1);
    }
    
    .nav-icon-pill.active {
      background: rgba(255, 255, 255, 0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }
    
    .nav-icon-pill i {
      font-size: 1rem;
    }
    
    .navbar-menu-controls {
      display: none;
    }
    
    .navbar-right {
      display: none;
    }
    
    .logout-btn {
      padding: 0.65rem 1.5rem;
      background: #ff6b35;
      backdrop-filter: none;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
    }
    
    .logout-btn:hover {
      background: #ff8555;
      transform: translateY(-2px) scale(1.03);
      box-shadow: 0 8px 20px rgba(255, 107, 53, 0.5), 0 0 15px rgba(255, 107, 53, 0.3);
    }
    
    .logout-btn i {
      font-size: 1rem;
    }

    .accounts-header-right {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      flex-wrap: wrap;
      gap: 0.75rem;
      flex-shrink: 0;
    }
    .accounts-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .accounts-header-text h1,
    .accounts-title {
      color: #ffffff;
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(2.35rem, 5vw, 3.1rem);
      font-weight: 400;
      line-height: 1.05;
      letter-spacing: -0.02em;
      margin-bottom: 0;
    }
    .accounts-header-notifications {
      align-items: flex-start;
      margin-bottom: 0.85rem;
      position: relative;
      z-index: 2;
    }
    .accounts-header-notifications .accounts-header-text {
      flex: 1;
      min-width: 0;
      text-align: left;
      padding-top: 0.15rem;
      position: relative;
      z-index: 2;
    }
    .accounts-header-notifications .accounts-title {
      position: relative;
      z-index: 2;
    }
    .accounts-title .app-title-accent {
      color: #ff6b35;
      font-style: italic;
      font-family: 'DM Serif Display', Georgia, serif;
    }
    .notif-page-sub {
      margin: 0.85rem 0 0;
      max-width: 38rem;
      color: rgba(255, 255, 255, 0.78);
      font-size: 0.98rem;
      line-height: 1.6;
      position: relative;
      z-index: 2;
    }
    /* Calendar Icon - Image (menu.php) */
    .calendar-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: transparent;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      padding: 0;
      cursor: pointer;
      border: none;
    }
    
    .calendar-icon-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }
    
    .calendar-icon:hover {
      transform: translateY(-2px);
    }
    
    .language-selector {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      width: 40px;
      height: 40px;
      border-radius: 30%;
      color: #ffffff;
      text-decoration: none;
      transition: all 0.3s ease;
      background: transparent;
      backdrop-filter: none;
      border: none;
      position: relative;
      cursor: pointer;
      overflow: hidden;
    }
    
    .language-selector:hover {
      transform: translateY(-2px);
    }
    
    .language-selector.ph-selected,
    .language-selector.en-selected {
      background: transparent;
      border: none;
    }
    
    .language-selector select {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
      z-index: 10;
    }
    
    .language-selector i {
      font-size: 1.1rem;
      z-index: 1;
    }
    
    .ph-flag-icon,
    .uk-flag-icon {
      position: absolute;
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 30%;
      top: 0;
      left: 0;
      z-index: 0;
      display: none;
    }
    
    .uk-flag-icon {
      border-radius: 30%;
    }
    
    .language-selector.ph-selected .ph-flag-icon {
      display: block;
    }
    
    .language-selector.ph-selected .uk-flag-icon {
      display: none;
    }
    
    .language-selector.en-selected .uk-flag-icon {
      display: block;
    }
    
    .language-selector.en-selected .ph-flag-icon {
      display: none;
    }
    
    .language-selector.ph-selected i,
    .language-selector.en-selected i {
      display: none;
    }
    
    .nav-pills-container,
    .nav-icon-pill {
      overflow: visible;
    }

    .nav-icon-badge {
      position: absolute;
      top: 0;
      right: 0.2rem;
      left: auto;
      z-index: 3;
      transform: translate(42%, -42%);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 999px;
      background-color: #ef4444;
      color: #fff;
      font-size: 11px;
      line-height: 1;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.85);
      font-weight: 700;
      pointer-events: none;
    }

    @media (max-width: 991px) {
      .nav-icon-pill .nav-icon-badge {
        top: 0.28rem;
        right: 0.32rem;
        transform: none;
      }
    }

    /* Responsive — menu.php */
    @media (max-width: 991px) {
      .navbar {
        padding: 0.75rem 1rem;
      }
      
      .navbar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        z-index: 998;
        backdrop-filter: blur(2px);
      }
      
      .navbar-overlay.show {
        display: block;
      }
      
      .navbar-toggler {
        display: block;
        order: 2;
        margin-left: auto;
        z-index: 1000;
      }
      
      .navbar-content {
        flex-wrap: wrap;
        gap: 0.75rem;
      }
      
      .navbar-left {
        order: 1;
        flex: 1;
      }
      
      .nav-pills-wrap {
        top: auto;
        bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
        height: auto;
      }
      .navbar-center {
        display: none;
        position: fixed;
        top: 0;
        right: 0;
        width: min(300px, 86vw);
        height: 100vh;
        background: rgba(102, 126, 234, 0.98);
        backdrop-filter: blur(20px);
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        margin: 0;
        padding: 1.25rem;
        padding-top: 88px;
        padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
        border-left: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
        z-index: 999;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        overflow-y: auto;
      }
      .navbar-center.show {
        display: flex;
        transform: translateX(0);
      }
      .navbar-menu-controls {
        display: flex;
        width: 100%;
        justify-content: flex-start;
        align-items: center;
        gap: 1rem;
        margin: 0;
        padding: 1rem 0 0.35rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        border-bottom: none;
      }
      .navbar-right-desktop {
        display: none;
      }
      .navbar-right {
        width: 100%;
        display: flex;
        justify-content: flex-start;
        margin-top: 0;
        margin-left: 0;
        padding: 0.65rem 0 1.25rem;
        border-top: none;
        position: relative;
      }
      .logout-btn {
        width: 100%;
        justify-content: center;
      }
      .main-container {
        padding: 1rem;
        padding-top: calc(80px + 1rem);
        padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
      }
      
      .navbar-logo .brand__text {
        font-size: clamp(0.82rem, 2vw, 1rem);
      }
      .navbar-logo .brand__mark {
        width: 40px;
        height: 40px;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 0.5rem 0.75rem;
      }
      
      .logout-btn {
        padding: 0.6rem 1.25rem;
        font-size: 0.95rem;
      }
      
      .calendar-icon,
      .language-selector {
        width: 36px;
        height: 36px;
      }
    }

    @media (max-width: 576px) {
      .navbar {
        padding: 0.5rem;
      }
      
      .navbar-content {
        gap: 0.5rem;
      }
      
      .navbar-left {
        gap: 0.5rem;
      }
      
      .navbar-logo .brand__text {
        font-size: 0.82rem;
      }
      .navbar-logo .brand__place {
        font-size: 0.55rem;
      }
      .navbar-logo .brand__mark {
        width: 36px;
        height: 36px;
      }
      
      .logout-btn {
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
      }
      
      .calendar-icon,
      .language-selector {
        width: 32px;
        height: 32px;
      }
      
      .main-container {
        padding: 0.75rem;
        padding-top: calc(70px + 0.75rem);
        padding-bottom: calc(5.25rem + env(safe-area-inset-bottom, 0px));
      }
    }

    main {
      max-width: 1200px;
      margin: 1rem auto 2rem;
      padding: 0 1rem;
      width: 100%;
      flex: 1;
    }

    .page-header {
      background-color: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1.5rem;
    }

    .page-header h1 {
      color: #667eea;
      margin: 0;
      font-size: 2rem;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .page-header p {
      margin: 0.25rem 0 0;
      color: #666;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
    }

    .stat-card {
      background: linear-gradient(135deg, #667eea, #764ba2);
      padding: 1rem 1.25rem;
      border-radius: 10px;
      color: white;
      text-align: left;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .stat-card h4 {
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      opacity: 0.9;
      margin-bottom: 0.25rem;
    }

    .stat-card .number {
      font-size: 1.6rem;
      font-weight: bold;
    }

    .notifications-layout {
      display: grid;
      grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
      gap: 1.5rem;
      margin-top: 2rem;
    }

    .card-block {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      padding: 1.75rem;
      border-radius: 24px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
    }

    .card-block h2 {
      font-size: 1.5rem;
      color: #667eea;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .applications-table {
      overflow-x: auto;
      margin-top: 0.5rem;
    }

    .applications-table table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
    }

    .applications-table th {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      padding: 0.75rem;
      text-align: left;
      font-weight: 600;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .applications-table td {
      padding: 0.75rem;
      border-bottom: 1px solid #e9ecef;
      vertical-align: middle;
      font-size: 0.95rem;
    }

    .applications-table tr:hover {
      background-color: #f8f9fa;
    }

    .applications-table tr:last-child td {
      border-bottom: none;
    }

    .applications-table tr.table-light td {
      background-color: #f8f9fa;
    }

    .badge-primary { background-color: #007bff; color: white; }
    .badge-secondary { background-color: #6c757d; color: white; }
    .badge-success { background-color: #28a745; color: white; }
    .badge-danger { background-color: #dc3545; color: white; }
    .badge-warning { background-color: #ffc107; color: #333; }

    .edit-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .edit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
      color: white;
      text-decoration: none;
    }

    .edit-btn i {
      font-size: 0.875rem;
    }

    .notif-feed-panel {
      margin-top: 0.35rem;
      padding: 0;
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
    }

    .notif-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.85rem 1.1rem;
      margin-bottom: 1.5rem;
    }

    .notif-filter-rail {
      display: inline-flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.25rem;
      padding: 0.3rem;
      border-radius: 50px;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.22);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    .notif-filter-chip {
      border: 1px solid transparent;
      border-radius: 50px;
      padding: 0.52rem 1.05rem;
      font-size: 0.9rem;
      font-weight: 500;
      font-family: inherit;
      cursor: pointer;
      background: transparent;
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      transition: background 0.2s, color 0.2s, transform 0.15s;
    }

    .notif-filter-chip:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.1);
    }

    .notif-filter-chip.is-active {
      background: rgba(255, 255, 255, 0.38);
      border-color: rgba(255, 255, 255, 0.35);
      color: #1c1c1e;
      -webkit-text-fill-color: #1c1c1e;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
    }

    .notif-filter-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 1.35rem;
      height: 1.35rem;
      padding: 0 0.35rem;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.14);
      color: inherit;
    }

    .notif-filter-chip.is-active .notif-filter-count {
      background: rgba(28, 28, 30, 0.14);
    }

    .notif-date-group {
      display: flex;
      flex-direction: column;
      gap: 1.35rem;
      margin: 0 0 1.5rem;
    }

    .notif-date-group-label {
      display: block;
      margin: 0;
      color: rgba(255, 255, 255, 0.62);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .notif-date-group.is-hidden {
      display: none;
    }

    .notifications-list {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
    }

    .notif-card {
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 18px;
      padding: 1.35rem 1.4rem 1.4rem;
      box-shadow: 0 12px 32px rgba(46, 35, 108, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.55);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .notif-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 40px rgba(46, 35, 108, 0.18);
    }

    .notif-card.is-unread {
      box-shadow: 0 12px 32px rgba(46, 35, 108, 0.16), inset 3px 0 0 #ff6b35;
    }

    .notif-card.is-hidden {
      display: none;
    }

    .notif-card-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
    }

    .notif-tag {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      flex-shrink: 0;
      font-size: 0.68rem;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      padding: 0.38rem 0.72rem;
      border-radius: 999px;
      line-height: 1;
      border: 1px solid transparent;
    }

    .notif-tag i {
      font-size: 0.72rem;
    }

    .notif-tag--application {
      background: rgba(255, 107, 53, 0.1);
      color: #ff6b35;
      border-color: rgba(255, 107, 53, 0.45);
    }

    .notif-tag--renewal {
      background: rgba(255, 107, 53, 0.1);
      color: #ff6b35;
      border-color: rgba(255, 107, 53, 0.45);
    }

    .notif-tag--receipt,
    .notif-tag--receipt-orange {
      background: rgba(255, 107, 53, 0.1);
      color: #ff6b35;
      border-color: rgba(255, 107, 53, 0.45);
    }

    .notif-card-meta {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      flex-shrink: 0;
      margin-left: auto;
    }

    .notif-time {
      font-size: 0.8rem;
      color: #64748b;
      white-space: nowrap;
      line-height: 1.4;
    }

    .notif-eye-btn {
      flex-shrink: 0;
      width: 32px;
      height: 32px;
      border: none;
      border-radius: 50%;
      background: transparent;
      color: #94a3b8;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s ease, background 0.2s ease, transform 0.2s ease;
    }

    .notif-eye-btn:hover {
      color: #ff6b35;
      background: rgba(255, 107, 53, 0.1);
      transform: scale(1.05);
    }

    .notif-card-body {
      display: block;
    }

    .notif-card-content {
      min-width: 0;
    }

    .notif-card-title {
      font-size: 1.08rem;
      font-weight: 700;
      color: #1c1c1e;
      margin: 0 0 0.35rem;
      line-height: 1.3;
      text-align: left;
    }

    .notif-card-loc {
      margin: 0 0 0.55rem;
      font-size: 0.88rem;
      font-weight: 600;
      color: #ff6b35;
      line-height: 1.35;
    }

    .notif-card-desc,
    .notif-card-reason {
      margin: 0;
      font-size: 0.9rem;
      line-height: 1.55;
      color: #64748b;
    }

    .notif-card-incomplete-details {
      margin-top: 0.45rem;
    }

    .notif-new-dot,
    .notif-incomplete-dot,
    .notif-pending-dot {
      display: inline-block;
      width: 7px;
      height: 7px;
      margin-left: 0.35rem;
      border-radius: 50%;
      vertical-align: middle;
    }

    .notif-new-dot { background: #ff6b35; }
    .notif-incomplete-dot { background: #ca8a04; }
    .notif-pending-dot { background: #667eea; }

    .notif-card-actions {
      display: flex;
      justify-content: flex-start;
      margin-top: 1rem;
      padding-top: 0;
      border-top: none;
    }

    .notif-edit-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border: none;
      border-radius: 999px;
      background: #ff6b35;
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
      font-size: 0.88rem;
      font-weight: 700;
      padding: 0.7rem 1.25rem;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
      text-decoration: none;
      box-shadow: 0 8px 22px rgba(255, 107, 53, 0.32);
    }

    .notif-edit-btn:hover {
      background: #ff8555;
      transform: translateY(-1px);
      color: #ffffff !important;
      box-shadow: 0 12px 28px rgba(255, 107, 53, 0.4);
    }

    .notif-card.is-collapsed {
      display: none;
    }

    .notif-roll-wrap {
      display: none;
      margin-top: 0.95rem;
    }

    .notif-roll-wrap.is-visible {
      display: block;
    }

    .notif-roll-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      padding: 0.65rem 1.35rem;
      border: 1px solid rgba(255, 255, 255, 0.22);
      background: rgba(255, 255, 255, 0.12);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
      font-weight: 500;
      font-size: 0.9rem;
      border-radius: 50px;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .notif-roll-btn:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.28);
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
    }

    .notif-roll-btn i {
      transition: transform 0.2s ease;
    }

    .notif-roll-btn.expanded i {
      transform: rotate(180deg);
    }

    .notif-empty {
      text-align: center;
      padding: 2.25rem 1.25rem;
      color: rgba(255, 255, 255, 0.88);
      font-size: 0.98rem;
      background: rgba(15, 23, 42, 0.22);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 18px;
    }

    @media (max-width: 768px) {
      .notif-toolbar {
        align-items: stretch;
      }
    }

    /* Legacy aliases kept for dark mode hooks */
    .notification-item { display: none; }

    /* Dark mode — only cards/panels go dark; keep indigo page background */
    body.darkmode {
      background-color: #667eea;
    }
    body.darkmode .card-block {
      background-color: rgba(55, 65, 81, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    }
    body.darkmode .card-block h2 {
      color: #e2e8f0;
    }
    /* Application Status table: Bootstrap .table forces white rows — override in dark mode */
    body.darkmode .applications-table table.table {
      --bs-table-bg: #0f0f0f;
      --bs-table-color: #e2e8f0;
      --bs-table-border-color: rgba(255, 255, 255, 0.12);
      --bs-table-hover-bg: #1a1a1a;
      --bs-table-hover-color: #f8fafc;
      background: #0f0f0f !important;
      color: #e2e8f0 !important;
    }
    body.darkmode .applications-table table {
      background: #0f0f0f !important;
    }
    body.darkmode .applications-table thead th {
      background: linear-gradient(135deg, #4c51bf, #553c9a) !important;
      color: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.12);
    }
    body.darkmode .applications-table tbody tr,
    body.darkmode .applications-table tbody td {
      background-color: #0f0f0f !important;
      color: #e2e8f0 !important;
      border-color: rgba(255, 255, 255, 0.1);
    }
    body.darkmode .applications-table tbody tr:hover td {
      background-color: #1a1a1a !important;
      color: #f8fafc !important;
    }
    body.darkmode .applications-table tr.table-light td {
      background-color: #141414 !important;
      color: #e2e8f0 !important;
    }
    body.darkmode .applications-table .text-muted {
      color: #cbd5e1 !important;
    }
    body.darkmode .notif-page-sub {
      color: rgba(255, 255, 255, 0.72);
    }
    body.darkmode .notif-filter-rail {
      background: rgba(10, 10, 14, 0.58);
      border-color: rgba(255, 255, 255, 0.12);
      box-shadow: 0 14px 42px rgba(0, 0, 0, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.07);
    }
    body.darkmode .notif-filter-chip {
      background: transparent;
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
    }
    body.darkmode .notif-filter-chip.is-active {
      background: rgba(255, 255, 255, 0.14);
      border-color: rgba(255, 255, 255, 0.16);
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }
    body.darkmode .notif-card {
      background: #0b0b0d !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4) !important;
    }
    body.darkmode .notif-card.is-unread {
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4), inset 3px 0 0 #ff6b35 !important;
    }
    body.darkmode .notif-card-title {
      color: #f8fafc !important;
      -webkit-text-fill-color: #f8fafc !important;
    }
    body.darkmode .notif-card-loc {
      color: #ff6b35 !important;
      -webkit-text-fill-color: #ff6b35 !important;
    }
    body.darkmode .notif-card-desc,
    body.darkmode .notif-card-reason,
    body.darkmode .notif-time {
      color: rgba(255, 255, 255, 0.62) !important;
      -webkit-text-fill-color: rgba(255, 255, 255, 0.62) !important;
    }
    body.darkmode .notif-incomplete-dot {
      background: #facc15;
    }
    body.darkmode .notif-pending-dot {
      background: #818cf8;
    }
    body.darkmode .notif-eye-btn {
      background: transparent;
      color: rgba(255, 255, 255, 0.45);
    }
    body.darkmode .notif-eye-btn:hover {
      background: rgba(255, 107, 53, 0.14);
      color: #ff8c5a;
    }
    body.darkmode .notif-roll-btn {
      background: rgba(10, 10, 14, 0.58);
      border-color: rgba(255, 255, 255, 0.12);
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
      box-shadow: 0 14px 42px rgba(0, 0, 0, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.07);
    }
    body.darkmode .notif-roll-btn:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.16);
      color: #ffffff;
      -webkit-text-fill-color: #ffffff;
    }
    body.darkmode .notification-item,
    body.darkmode .notification-item.unread,
    body.darkmode .notification-item.incomplete,
    body.darkmode .notification-item.incomplete.unread,
    body.darkmode .notification-item.approved,
    body.darkmode .notification-item.approved.unread {
      background: rgba(45, 55, 72, 0.95);
      border-left-color: #818cf8;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
    }
    body.darkmode .notification-title {
      color: #f7fafc;
    }
    body.darkmode .notification-message {
      color: #cbd5e0;
    }
    body.darkmode .notification-meta {
      color: #a0aec0;
    }
    body.darkmode .page-header {
      background-color: rgba(45, 55, 72, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
    body.darkmode .page-header h1 {
      color: #a5b4fc;
    }
    body.darkmode .page-header p {
      color: #cbd5e0;
    }
    body.darkmode .stat-card {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    }
    body.darkmode .incomplete-reason-box {
      background-color: rgba(120, 53, 15, 0.45) !important;
      border-left-color: #a5b4fc !important;
      color: #fef3c7;
    }

    /* Collapse: legacy — notifications show all in new layout */
    .applications-table-wrap:not(.is-expanded) tbody.app-status-group--collapsed {
      display: none;
    }
    .btn-section-expand {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      margin-top: 1rem;
      padding: 0.5rem 1rem;
      font-size: 0.9rem;
      font-weight: 600;
      color: #667eea;
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid rgba(102, 126, 234, 0.35);
      border-radius: 999px;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s;
    }
    .btn-section-expand:hover {
      background: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    body.darkmode .btn-section-expand {
      color: #c7d2fe;
      background: rgba(45, 55, 72, 0.95);
      border-color: rgba(129, 140, 248, 0.45);
    }
    body.darkmode .btn-section-expand:hover {
      background: rgba(55, 65, 81, 0.98);
    }

    @media (max-width: 991px) {
      .accounts-header {
        flex-direction: column;
        align-items: flex-start;
      }
      .accounts-header-notifications .accounts-header-text {
        text-align: left;
        width: 100%;
      }
      .accounts-header-right {
        width: 100%;
        justify-content: flex-end;
      }
      .notifications-layout {
        grid-template-columns: 1fr;
      }
      
      main {
        padding: 0 0.75rem;
      }
      
      .page-header {
        padding: 1.5rem 1rem;
      }
    }

    @media (max-width: 768px) {
      .page-header h1 {
        font-size: 1.75rem;
      }
      
      .card-block {
        padding: 1.5rem;
      }
      
      table {
        font-size: 0.9rem;
      }
      
      .notification-item {
        padding: 1rem;
      }
      
      .notification-content {
        padding-left: 0.75rem;
      }
    }

    @media (max-width: 576px) {
      .page-header {
        padding: 1rem;
      }
      
      .page-header h1 {
        font-size: 1.5rem;
      }
      
      .card-block {
        padding: 1rem;
      }
      
      table {
        font-size: 0.85rem;
        display: block;
        overflow-x: auto;
        white-space: nowrap;
      }
      
      table thead,
      table tbody,
      table tr {
        display: block;
      }
      
      table th,
      table td {
        display: inline-block;
        padding: 0.5rem;
        text-align: left;
      }
      
      .notification-item {
        padding: 0.75rem;
        flex-direction: column;
      }
      
      .notification-icon {
        margin-bottom: 0.5rem;
      }
      
      .notification-content {
        padding-left: 0;
      }
      
      .edit-btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
      }
    }
    
    @media (max-width: 480px) {
      .page-header h1 {
        font-size: 1.3rem;
      }
      
      .card-block {
        padding: 0.75rem;
      }
      
      .notification-title {
        font-size: 0.95rem;
      }
      
      .notification-message {
        font-size: 0.85rem;
      }
      
      .notification-meta {
        font-size: 0.75rem;
      }
    }

    /* Calendar modal uses menu.php inline styles + JS (#calendarContainer); no extra navbar-style rules here */

    /* Custom modals only — scoped so Bootstrap #calendarModal is not affected (fixes click-through) */
    /* Custom popups — NOT using Bootstrap .modal (avoids display/position conflicts) */
    .notif-popup-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1050;
      background: rgba(15, 23, 42, 0.55);
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .notif-popup-overlay.is-open {
      display: flex;
    }

    .notif-popup-content {
      background: #ffffff;
      border-radius: 14px;
      width: 100%;
      max-width: 760px;
      max-height: 90vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
      animation: notifPopupIn 0.25s ease;
    }

    @keyframes notifPopupIn {
      from { opacity: 0; transform: translateY(16px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .notif-popup-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.25rem;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #ffffff;
    }

    .notif-popup-header h2 {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .notif-popup-close {
      border: none;
      background: rgba(255, 255, 255, 0.2);
      color: #ffffff;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      font-size: 1.4rem;
      line-height: 1;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .notif-popup-close:hover {
      background: rgba(255, 255, 255, 0.32);
    }

    .notif-popup-body {
      padding: 1.25rem;
      overflow-y: auto;
      color: #374151;
    }

    body.darkmode .notif-popup-content {
      background: #000000;
    }

    body.darkmode .notif-popup-body {
      background: #000000;
      color: #ffffff;
    }

    .notif-files-panel,
    .notif-files-item {
      background: #ffffff;
      color: #111111;
      border-radius: 10px;
    }

    .notif-files-panel {
      margin-bottom: 1.1rem;
      padding: 0.85rem 1rem;
      line-height: 1.6;
    }

    .notif-files-item {
      margin-bottom: 0.85rem;
      padding: 0.75rem 0.85rem;
    }

    .notif-files-heading {
      font-size: 1rem;
      margin: 0 0 0.75rem;
      color: #111111;
    }

    .notif-files-reqs {
      margin: 0 0 1.25rem;
      padding-left: 1.2rem;
      color: #111111;
      line-height: 1.7;
    }

    .notif-files-meta,
    .notif-files-empty,
    .notif-files-status {
      color: #111111;
    }

    .notif-files-meta {
      font-size: 0.82rem;
      margin-top: 0.25rem;
    }

    body.darkmode .notif-files-heading,
    body.darkmode .notif-files-reqs,
    body.darkmode .notif-files-reqs li,
    body.darkmode .notif-popup-body,
    body.darkmode .notif-popup-body .notif-files-status {
      background: transparent;
      color: #ffffff;
    }

    body.darkmode .notif-files-panel,
    body.darkmode .notif-files-item {
      background: #ffffff;
      color: #111111;
    }

    body.darkmode .notif-files-panel strong,
    body.darkmode .notif-files-item strong,
    body.darkmode .notif-files-meta {
      color: #111111;
    }

    body.darkmode #filePreviewContent {
      background: #000000 !important;
      color: #ffffff !important;
    }

    body.darkmode #filePreviewContent p,
    body.darkmode #filePreviewContent strong {
      color: #ffffff !important;
    }

    .edit-notif-modal.modal {
      --edit-accent: #ff6b35;
      --edit-accent-hover: #ff8555;
      --edit-accent-soft: #fff0e8;
      --edit-accent-shadow: rgba(255, 107, 53, 0.28);
      display: none;
      position: fixed;
      z-index: 1000;
      inset: 0;
      background: rgba(45, 35, 20, 0.45);
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      animation: fadeIn 0.25s ease;
    }

    .edit-notif-modal.modal.show {
      display: flex;
    }

    .edit-notif-modal .edit-modal-panel {
      background: #faf7f2;
      border-radius: 16px;
      width: 100%;
      max-width: 620px;
      max-height: min(92vh, 900px);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 24px 60px rgba(62, 39, 20, 0.18);
      animation: slideIn 0.28s ease;
    }

    .edit-notif-modal .edit-modal-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.35rem 1.5rem 1.1rem;
      border-bottom: 1px solid #ebe4d8;
      flex-shrink: 0;
    }

    .edit-notif-modal form {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }

    .edit-notif-modal .edit-modal-header-main {
      display: flex;
      align-items: flex-start;
      gap: 0.85rem;
      min-width: 0;
    }

    .edit-notif-modal .edit-modal-icon {
      width: 42px;
      height: 42px;
      border-radius: 10px;
      background: var(--edit-accent);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .edit-notif-modal .edit-modal-header h2 {
      margin: 0 0 0.2rem;
      font-size: 1.35rem;
      font-weight: 700;
      color: #3d2918;
      line-height: 1.25;
    }

    .edit-notif-modal .edit-modal-subtitle {
      margin: 0;
      font-size: 0.88rem;
      color: #8b7355;
      line-height: 1.45;
    }

    .edit-notif-modal .edit-modal-close {
      background: none;
      border: none;
      color: #8b7355;
      font-size: 1.6rem;
      line-height: 1;
      cursor: pointer;
      padding: 0.15rem 0.35rem;
      border-radius: 8px;
      flex-shrink: 0;
    }

    .edit-notif-modal .edit-modal-close:hover {
      background: var(--edit-accent-soft);
      color: var(--edit-accent);
    }

    .edit-notif-modal .edit-modal-body {
      padding: 1.25rem 1.5rem 0.5rem;
      overflow-y: auto;
      overflow-x: hidden;
      flex: 1;
      min-height: 0;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: var(--edit-accent) #fff0e8;
    }

    .edit-notif-modal .edit-modal-body::-webkit-scrollbar {
      width: 8px;
    }

    .edit-notif-modal .edit-modal-body::-webkit-scrollbar-track {
      background: #fff0e8;
      border-radius: 999px;
    }

    .edit-notif-modal .edit-modal-body::-webkit-scrollbar-thumb {
      background: var(--edit-accent);
      border-radius: 999px;
    }

    .edit-notif-modal .edit-modal-body::-webkit-scrollbar-thumb:hover {
      background: var(--edit-accent-hover);
    }

    .edit-notif-modal .edit-modal-section {
      margin-bottom: 1.35rem;
    }

    .edit-notif-modal .edit-modal-section-label {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #9ca3af;
      margin-bottom: 0.75rem;
    }

    .edit-notif-modal .edit-modal-section-label i {
      color: var(--edit-accent);
      font-size: 0.85rem;
    }

    .edit-notif-modal .edit-field label {
      display: block;
      margin-bottom: 0.45rem;
      font-size: 0.88rem;
      font-weight: 600;
      color: #4b5563;
    }

    .edit-notif-modal .edit-field input,
    .edit-notif-modal .edit-field select,
    .edit-notif-modal .edit-field textarea {
      width: 100%;
      padding: 0.72rem 0.85rem;
      border: 1px solid #e5ddd0;
      border-radius: 10px;
      background: #fff;
      color: #1f2937;
      font-size: 0.92rem;
      font-family: inherit;
      box-sizing: border-box;
      transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .edit-notif-modal .edit-field input:focus,
    .edit-notif-modal .edit-field select:focus,
    .edit-notif-modal .edit-field textarea:focus {
      outline: none;
      border-color: var(--edit-accent);
      box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.15);
    }

    .edit-notif-modal .edit-field select:disabled {
      background: #f3ede4;
      color: #6b7280;
      cursor: not-allowed;
    }

    .edit-notif-modal .edit-field-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.85rem;
    }

    @media (max-width: 560px) {
      .edit-notif-modal .edit-field-grid {
        grid-template-columns: 1fr;
      }
    }

    .edit-notif-modal .edit-required {
      color: #dc2626;
    }

    .edit-notif-modal .edit-docs-list {
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
    }

    .edit-notif-modal .edit-doc-row {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      padding: 0.85rem 0.95rem;
      background: #fff;
      border: 1px solid #ebe4d8;
      border-radius: 12px;
    }

    .edit-notif-modal .edit-doc-status {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.82rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .edit-notif-modal .edit-doc-status--done {
      background: #dcfce7;
      color: #16a34a;
    }

    .edit-notif-modal .edit-doc-status--pending {
      background: #ffedd5;
      color: #ea580c;
    }

    .edit-notif-modal .edit-doc-info {
      flex: 1;
      min-width: 0;
    }

    .edit-notif-modal .edit-doc-name {
      font-size: 0.9rem;
      font-weight: 600;
      color: #1f2937;
      line-height: 1.35;
      margin-bottom: 0.15rem;
    }

    .edit-notif-modal .edit-doc-file {
      font-size: 0.78rem;
      color: #9ca3af;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      word-break: break-word;
    }

    .edit-notif-modal .edit-doc-file.is-new {
      color: var(--edit-accent);
    }

    .edit-notif-modal .edit-doc-actions {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      flex-shrink: 0;
    }

    .edit-notif-modal .edit-doc-btn {
      border: none;
      border-radius: 999px;
      padding: 0.45rem 0.85rem;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-family: inherit;
      white-space: nowrap;
    }

    .edit-notif-modal .edit-doc-btn--upload,
    .edit-notif-modal .edit-doc-btn--replace {
      background: var(--edit-accent);
      color: #fff;
      box-shadow: 0 2px 8px var(--edit-accent-shadow);
    }

    .edit-notif-modal .edit-doc-btn--upload:hover,
    .edit-notif-modal .edit-doc-btn--replace:hover {
      background: var(--edit-accent-hover);
    }

    .edit-notif-modal .edit-doc-btn--remove {
      background: transparent;
      color: #9ca3af;
      padding: 0.35rem 0.45rem;
      font-size: 1rem;
    }

    .edit-notif-modal .edit-doc-btn--remove:hover {
      color: #dc2626;
    }

    .edit-notif-modal .edit-doc-btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }

    .edit-notif-modal .edit-help-box {
      display: flex;
      align-items: flex-start;
      gap: 0.85rem;
      margin-top: 0.5rem;
      padding: 1rem 1.05rem;
      background: #fff;
      border: 1px solid #ebe4d8;
      border-radius: 12px;
    }

    .edit-notif-modal .edit-help-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: var(--edit-accent-soft);
      color: var(--edit-accent);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .edit-notif-modal .edit-help-content h4 {
      margin: 0 0 0.35rem;
      font-size: 0.95rem;
      font-weight: 700;
      color: #1f2937;
    }

    .edit-notif-modal .edit-help-content p {
      margin: 0;
      font-size: 0.82rem;
      color: #6b7280;
      line-height: 1.5;
    }

    .edit-notif-modal .edit-help-formats {
      margin-top: 0.45rem !important;
      font-size: 0.78rem !important;
      color: #9ca3af !important;
    }

    .edit-notif-modal .edit-modal-footer {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.75rem;
      padding: 1rem 1.5rem 1.35rem;
      border-top: 1px solid #ebe4d8;
      background: #faf7f2;
      flex-shrink: 0;
    }

    .edit-notif-modal .edit-btn-cancel {
      background: none;
      border: none;
      color: #6b7280;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      padding: 0.55rem 0.85rem;
      font-family: inherit;
    }

    .edit-notif-modal .edit-btn-cancel:hover {
      color: #3d2918;
    }

    .edit-notif-modal .edit-btn-save {
      background: var(--edit-accent);
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 0.72rem 1.35rem;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      font-family: inherit;
      box-shadow: 0 4px 14px var(--edit-accent-shadow);
    }

    .edit-notif-modal .edit-btn-save:hover {
      background: var(--edit-accent-hover);
    }

    .edit-notif-modal .edit-btn-save:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }

    body.darkmode .edit-notif-modal .edit-modal-body {
      scrollbar-color: var(--edit-accent) #2c2c2c;
    }

    body.darkmode .edit-notif-modal .edit-modal-body::-webkit-scrollbar-track {
      background: #2c2c2c;
    }

    body.darkmode .edit-notif-modal .edit-modal-body::-webkit-scrollbar-thumb {
      background: var(--edit-accent);
    }

    body.darkmode .edit-notif-modal .edit-modal-body::-webkit-scrollbar-thumb:hover {
      background: var(--edit-accent-hover);
    }

    body.darkmode .edit-notif-modal .edit-help-box {
      background: #2c2c2c;
      border-color: #3a3a3a;
    }

    body.darkmode .edit-notif-modal .edit-help-icon {
      background: rgba(255, 107, 53, 0.15);
      color: var(--edit-accent);
    }

    body.darkmode .edit-notif-modal .edit-help-content h4 {
      color: #f3f4f6;
    }

    body.darkmode .edit-notif-modal .edit-help-content p {
      color: #9ca3af;
    }

    body.darkmode .edit-notif-modal .edit-modal-panel,
    body.darkmode .edit-notif-modal .edit-modal-footer {
      background: #1e1e1e;
    }

    body.darkmode .edit-notif-modal .edit-modal-header,
    body.darkmode .edit-notif-modal .edit-modal-footer {
      border-color: #333;
    }

    body.darkmode .edit-notif-modal .edit-modal-header h2 {
      color: #f3f4f6;
    }

    body.darkmode .edit-notif-modal .edit-modal-subtitle,
    body.darkmode .edit-notif-modal .edit-modal-close {
      color: #9ca3af;
    }

    body.darkmode .edit-notif-modal .edit-field label {
      color: #d1d5db;
    }

    .edit-notif-modal .edit-field textarea {
      min-height: 96px;
      resize: vertical;
    }

    .edit-notif-modal .edit-field-readonly,
    .edit-notif-modal .edit-field input:disabled {
      background: #f3ede4 !important;
      color: #6b7280;
      cursor: not-allowed;
    }

    .edit-notif-modal .edit-help-box--alert {
      margin-bottom: 1rem;
      background: #fef2f2;
      border-color: #fecaca;
    }

    .edit-notif-modal .edit-help-icon--alert {
      background: #fee2e2;
      color: #b91c1c;
    }

    .edit-notif-modal .edit-help-title--alert {
      color: #991b1b;
    }

    .edit-notif-modal .edit-help-text--alert {
      white-space: pre-wrap;
      color: #7f1d1d;
    }

    .edit-notif-modal .edit-receipt-preview {
      margin-bottom: 0.85rem;
      padding: 0.85rem;
      background: #fff;
      border: 1px solid #ebe4d8;
      border-radius: 12px;
      text-align: center;
    }

    .edit-notif-modal .edit-receipt-preview img {
      max-width: 100%;
      max-height: 220px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .edit-notif-modal .edit-receipt-file-wrap {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.65rem;
      padding: 0.75rem 0.85rem;
      background: #fff;
      border: 1px dashed #e5ddd0;
      border-radius: 10px;
    }

    .edit-notif-modal .edit-receipt-file-input {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .edit-notif-modal .edit-receipt-file-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.55rem 0.95rem;
      border-radius: 999px;
      background: var(--edit-accent);
      color: #fff;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 2px 8px var(--edit-accent-shadow);
    }

    .edit-notif-modal .edit-receipt-file-btn:hover {
      background: var(--edit-accent-hover);
    }

    .edit-notif-modal .edit-receipt-file-name {
      font-size: 0.82rem;
      color: #6b7280;
      word-break: break-word;
    }

    .edit-notif-modal .edit-receipt-preview-empty {
      margin: 0;
      color: #6b7280;
      font-size: 0.88rem;
    }

    #editReceiptModal.modal {
      z-index: 1050;
    }

    body.darkmode .edit-notif-modal .edit-field textarea,
    body.darkmode .edit-notif-modal .edit-field input,
    body.darkmode .edit-notif-modal .edit-field select {
      background: #2c2c2c;
      border-color: #444;
      color: #f3f4f6;
    }

    body.darkmode .edit-notif-modal .edit-field select:disabled,
    body.darkmode .edit-notif-modal .edit-field-readonly,
    body.darkmode .edit-notif-modal .edit-field input:disabled {
      background: #252525 !important;
      color: #9ca3af;
    }

    body.darkmode .edit-notif-modal .edit-help-box--alert {
      background: rgba(127, 29, 29, 0.25);
      border-color: rgba(248, 113, 113, 0.35);
    }

    body.darkmode .edit-notif-modal .edit-help-icon--alert {
      background: rgba(185, 28, 28, 0.35);
      color: #fca5a5;
    }

    body.darkmode .edit-notif-modal .edit-help-title--alert {
      color: #fecaca;
    }

    body.darkmode .edit-notif-modal .edit-help-text--alert {
      color: #fca5a5;
    }

    body.darkmode .edit-notif-modal .edit-receipt-preview,
    body.darkmode .edit-notif-modal .edit-receipt-file-wrap {
      background: #2c2c2c;
      border-color: #3a3a3a;
    }

    body.darkmode .edit-notif-modal .edit-receipt-file-name {
      color: #9ca3af;
    }

    body.darkmode .edit-notif-modal .edit-doc-row {
      background: #2c2c2c;
      border-color: #3a3a3a;
    }

    body.darkmode .edit-notif-modal .edit-doc-name {
      color: #f3f4f6;
    }

    body.darkmode .edit-notif-modal .edit-doc-file {
      color: #9ca3af;
    }

    #filePreviewModal.notif-popup-overlay {
      z-index: 1100;
      background: rgba(15, 23, 42, 0.72);
    }

    #filePreviewModal .notif-popup-content {
      max-width: 920px;
    }

    .edit-notif-modal.modal.show,
    #receiptRequirementsModal.modal.show {
      display: flex;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes slideIn {
      from { transform: translateY(-24px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
  </style>
  <link rel="stylesheet" href="assets/css/vendor-light-mode-text.css?v=earlier-white-1">
  <link rel="stylesheet" href="assets/css/vendor-mobile-sidebar-logout.css">
  <link rel="stylesheet" href="assets/css/vendor-navbar-glass.css?v=nav-icon-phone-1">
  <link rel="stylesheet" href="assets/css/vendor-nav-theme-lock.css?v=1">
  <link rel="stylesheet" href="assets/css/vendor-portal-shell.css?v=1">
  <link rel="stylesheet" href="assets/css/menu-landing-nav.css?v=menu-nav-lock-4">
  <link rel="stylesheet" href="assets/css/vendor-page-glass.css?v=notif-elevate-2">
  <link rel="stylesheet" href="assets/css/vendor-portal-dark-nav-pills.css?v=phone-glass-5">
  <link rel="stylesheet" href="assets/css/notification-filter-pills.css?v=3">
</head>
<body class="page-notification">
  <script>try{if(localStorage.getItem('darkmode')==='active')document.body.classList.add('darkmode');}catch(e){}</script>
  <div class="main-container">
    <?php $vendor_nav_active = 'notification'; include __DIR__ . '/includes/vendor_portal_navbar.php'; ?>

  <main>
    <div class="accounts-header accounts-header-notifications">
      <div class="accounts-header-text">
        <h1 class="accounts-title">
          <span data-translate="notif.titleLead">Notif</span><span class="app-title-accent" data-translate="notif.titleAccent">ications.</span>
        </h1>
        <p class="notif-page-sub" data-translate="notif.subtitle">Everything the allotment office sends you in one place — application updates, renewal reminders and receipts. Open a notice to read the full message, then jump straight to the action it needs.</p>
      </div>
      <div class="accounts-header-right">
        <button type="button" class="profile-darkmode-btn" id="theme-switch" data-translate-title="profile.darkModeTitle" data-translate-aria="profile.darkModeAria" title="Dark mode" aria-label="Toggle dark mode">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-moon"><path d="M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Z"/></svg>
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-sun"><path d="M480-280q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Z"/></svg>
        </button>
      </div>
    </div>

    <div class="notif-feed-panel">
      <div class="notif-toolbar">
        <div class="notif-filter-rail" id="notifFilterRow">
          <button type="button" class="notif-filter-toggle" id="notifFilterToggle" aria-expanded="false" aria-controls="notifFilterMenu">
            <span class="notif-filter-toggle-text">
              <span class="notif-filter-toggle-name" data-translate="filter.all">All</span>
              <span class="notif-filter-count" id="notifFilterToggleCount"><?php echo (int)$notif_count_all; ?></span>
            </span>
            <i class="fas fa-chevron-down notif-filter-caret" aria-hidden="true"></i>
          </button>
          <div class="notif-filter-menu" id="notifFilterMenu" role="tablist" aria-label="Filter notifications">
          <button type="button" class="notif-filter-chip is-active" data-filter="all" role="tab" aria-selected="true">
            <span data-translate="filter.all">All</span>
            <span class="notif-filter-count"><?php echo (int)$notif_count_all; ?></span>
          </button>
          <button type="button" class="notif-filter-chip" data-filter="application" role="tab" aria-selected="false">
            <span data-translate="filter.application">Application</span>
            <span class="notif-filter-count"><?php echo (int)$notif_count_application; ?></span>
          </button>
          <button type="button" class="notif-filter-chip" data-filter="renewal" role="tab" aria-selected="false">
            <span data-translate="filter.renewal">Renewal</span>
            <span class="notif-filter-count"><?php echo (int)$notif_count_renewal; ?></span>
          </button>
          <button type="button" class="notif-filter-chip" data-filter="receipt" role="tab" aria-selected="false">
            <span data-translate="filter.receipts">Receipts</span>
            <span class="notif-filter-count"><?php echo (int)$notif_count_receipt; ?></span>
          </button>
          </div>
        </div>
      </div>

      <?php if (!empty($combined_feed)): ?>
      <div class="notifications-list" id="notificationsListWrap">
        <?php
          $last_date_group = null;
          foreach ($combined_feed as $feed_index => $item):
          $feed_cat = notif_feed_category($item);
          $tag_class = notif_tag_class($feed_cat, $item);
          $card_title = notif_card_title($item);
          $title_dot = notif_title_dot($item);
          $is_incomplete = notif_is_incomplete($item);
          $is_pending = notif_is_pending($item);
          $card_reason = $is_incomplete ? notif_extract_reason(notif_card_message($item)) : '';
          $card_incomplete_details = ($is_incomplete && !$is_pending) ? notif_incomplete_followup($item) : '';
          $card_summary = (!$is_incomplete || $is_pending) ? notif_display_summary($item) : '';
          $can_edit = notif_can_edit($item);
          $can_view = notif_can_view_requirements($item);
          $edit_action = notif_edit_action($item);
          $receipt_view_id = (int)($item['receipt_id'] ?? 0);
          $loc_line = notif_card_location_line($item);
          $date_group = notif_date_group_key($item['created_at'] ?? null);
          $is_unread_card = (($item['type'] ?? '') === 'notification' && empty($item['is_read']));
          $cta_label = notif_cta_label($item, $edit_action);
          $cta_icon = $feed_cat === 'renewal' ? 'fa-sync-alt' : ($feed_cat === 'receipt' ? 'fa-receipt' : 'fa-pen');
          if ($date_group !== $last_date_group):
            if ($last_date_group !== null) {
              echo '</div>';
            }
            $last_date_group = $date_group;
        ?>
        <div class="notif-date-group" data-date-group="<?php echo htmlspecialchars($date_group, ENT_QUOTES, 'UTF-8'); ?>">
          <span class="notif-date-group-label" style="color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;" data-translate="section.<?php echo htmlspecialchars($date_group, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(notif_date_group_label($date_group), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <article class="notif-card<?php echo $is_unread_card ? ' is-unread' : ''; ?>" data-category="<?php echo htmlspecialchars($feed_cat, ENT_QUOTES, 'UTF-8'); ?>" data-feed-index="<?php echo (int)$feed_index; ?>" data-date-group="<?php echo htmlspecialchars($date_group, ENT_QUOTES, 'UTF-8'); ?>" data-unread="<?php echo $is_unread_card ? '1' : '0'; ?>">
          <div class="notif-card-top">
            <span class="notif-tag <?php echo htmlspecialchars($tag_class, ENT_QUOTES, 'UTF-8'); ?>">
              <i class="fas <?php echo htmlspecialchars(notif_category_icon($feed_cat), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
              <?php echo htmlspecialchars(notif_category_label($feed_cat), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <div class="notif-card-meta">
              <span class="notif-time"><?php echo htmlspecialchars(notif_display_date($item['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
              <?php if ($can_view): ?>
              <button type="button"
                class="notif-eye-btn"
                title="View uploaded files"
                aria-label="View uploaded files"
                data-feed-category="<?php echo htmlspecialchars($feed_cat, ENT_QUOTES, 'UTF-8'); ?>"
                data-vendor-id="<?php echo (int)($item['vendor_id'] ?? 0); ?>"
                data-receipt-id="<?php echo (int)$receipt_view_id; ?>"
                onclick="handleNotifEyeClick(this)">
                <i class="fas fa-eye" aria-hidden="true"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
          <div class="notif-card-body">
            <div class="notif-card-content">
              <h3 class="notif-card-title">
                <?php echo htmlspecialchars($card_title, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($title_dot === 'incomplete'): ?>
                  <span class="notif-incomplete-dot" aria-label="Incomplete"></span>
                <?php elseif ($title_dot === 'unread'): ?>
                  <span class="notif-new-dot" aria-label="Unread"></span>
                <?php elseif ($title_dot === 'pending'): ?>
                  <span class="notif-pending-dot" aria-label="Pending"></span>
                <?php endif; ?>
              </h3>
              <?php if ($loc_line !== ''): ?>
              <p class="notif-card-loc"><?php echo htmlspecialchars($loc_line, ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
              <?php if ($is_incomplete && !$is_pending): ?>
              <?php if ($card_reason !== ''): ?>
              <p class="notif-card-reason"><?php echo htmlspecialchars($card_reason, ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
              <?php if ($card_incomplete_details !== ''): ?>
              <p class="notif-card-desc notif-card-incomplete-details"><?php echo htmlspecialchars($card_incomplete_details, ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
              <?php elseif (trim($card_summary) !== ''): ?>
              <p class="notif-card-desc"><?php echo htmlspecialchars($card_summary, ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($can_edit): ?>
          <div class="notif-card-actions">
            <?php if (($edit_action['type'] ?? '') === 'link'): ?>
            <a href="<?php echo htmlspecialchars($edit_action['href'] ?? 'profile.php', ENT_QUOTES, 'UTF-8'); ?>" class="notif-edit-btn">
              <i class="fas <?php echo htmlspecialchars($cta_icon, ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php elseif ((int)($edit_action['receipt_id'] ?? 0) > 0): ?>
            <button type="button" class="notif-edit-btn" onclick="openEditReceiptModal(<?php echo (int)$edit_action['receipt_id']; ?>)">
              <i class="fas <?php echo htmlspecialchars($cta_icon, ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <?php elseif ((int)($edit_action['vendor_id'] ?? 0) > 0): ?>
            <button type="button" class="notif-edit-btn" onclick="openEditModal(<?php echo (int)$edit_action['vendor_id']; ?>, '<?php echo htmlspecialchars($edit_action['application_type'] ?? 'new', ENT_QUOTES, 'UTF-8'); ?>')">
              <i class="fas <?php echo htmlspecialchars($cta_icon, ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php if ($last_date_group !== null): ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="notif-roll-wrap" id="notifRollWrap">
        <button type="button" class="notif-roll-btn" id="notifRollBtn" data-roll-expanded="0" aria-expanded="false">
          <i class="fas fa-chevron-down"></i>
          <span data-translate="notif.roll.viewAll">View all notifications</span>
        </button>
      </div>
      <?php else: ?>
        <p class="notif-empty mb-0" data-translate="empty.noActivity">No notifications or activity found.</p>
      <?php endif; ?>
    </div>
  </main>
  </div><!-- /.main-container -->

  <!-- Calendar Modal — same structure as menu.php (glass card, orange “today” dot) -->
  <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 15px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
          <h5 class="modal-title" id="calendarModalLabel" style="color: #6366f1;">
            <i class="fas fa-calendar"></i> <span data-translate="modal.calendar">Calendar</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
          <div id="calendarContainer" style="color: #475569;">
            <p style="text-align: center; color: rgba(71, 85, 105, 0.7);" data-translate="modal.calendar.coming">Calendar functionality coming soon</p>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- Edit Application Modal -->
<div id="editApplicationModal" class="modal edit-notif-modal" onclick="if(event.target===this)closeEditModal()">
  <div class="edit-modal-panel" onclick="event.stopPropagation()">
    <div class="edit-modal-header">
      <div class="edit-modal-header-main">
        <div class="edit-modal-icon"><i class="fas fa-edit"></i></div>
        <div>
          <h2 data-translate="modal.editApplication">Edit Application</h2>
          <p class="edit-modal-subtitle">Update your vendor stall application details</p>
        </div>
      </div>
      <button type="button" class="edit-modal-close" onclick="closeEditModal()" aria-label="Close">&times;</button>
    </div>
    <form id="editApplicationForm" enctype="multipart/form-data">
      <input type="hidden" id="edit_vendor_id" name="vendor_id">
      <input type="hidden" id="edit_application_type" name="application_type">
      <input type="hidden" name="action" value="update_application">

      <div class="edit-modal-body">
        <div class="edit-modal-section">
          <div class="edit-modal-section-label"><i class="fas fa-user"></i> Applicant Information</div>
          <div class="edit-field">
            <label for="edit_full_name">Full Name <span class="edit-required">*</span></label>
            <input type="text" id="edit_full_name" name="full_name" required placeholder="Enter your full name">
          </div>
        </div>

        <div class="edit-modal-section">
          <div class="edit-modal-section-label"><i class="fas fa-store"></i> Stall Assignment</div>
          <div class="edit-field-grid">
            <div class="edit-field">
              <label for="edit_section">Section <span class="edit-required">*</span></label>
              <select id="edit_section" name="section" required disabled>
                <option value="">Select a section</option>
                <option value="Fresh Fish Section">Fresh Fish Section</option>
                <option value="Meat and Chicken Section">Meat and Chicken Section</option>
                <option value="Dried and Salted Fish Section">Dried and Salted Fish Section</option>
                <option value="Fruits and Vegetables Section">Fruits and Vegetables Section</option>
                <option value="Dry Goods Section">Dry Goods Section</option>
                <option value="Groceries Sari-Sari Section">Groceries Sari-Sari Section</option>
                <option value="Eateries and Cooked Food Section">Eateries and Cooked Food Section</option>
                <option value="Service Section">Service Section</option>
              </select>
              <input type="hidden" id="edit_section_hidden" name="section">
            </div>
            <div class="edit-field">
              <label for="edit_block">Block <span class="edit-required">*</span></label>
              <select id="edit_block" name="block" required disabled>
                <option value="">Select a Block</option>
                <option value="Daet Elevated Plaza">Daet Elevated Plaza</option>
                <option value="Market Tienda Bldg">Market Tienda Bldg</option>
                <option value="Block A">Block A</option>
                <option value="Block B">Block B</option>
                <option value="Block C">Block C</option>
                <option value="BOT Block A-1">BOT Block A-1</option>
                <option value="BOT Block E">BOT Block E</option>
                <option value="BOT Behind Block B">BOT Behind Block B</option>
                <option value="BOT Behind Block C">BOT Behind Block C</option>
              </select>
              <input type="hidden" id="edit_block_hidden" name="block">
            </div>
          </div>
        </div>

        <div class="edit-modal-section">
          <div class="edit-modal-section-label"><i class="fas fa-file-alt"></i> Required Documents</div>
          <div id="edit_requirements_list" class="edit-docs-list"></div>
        </div>

        <div class="edit-help-box">
          <div class="edit-help-icon"><i class="fas fa-question"></i></div>
          <div class="edit-help-content">
            <h4>Need Help?</h4>
            <p>Contact the market administration office for assistance with your application.</p>
            <p class="edit-help-formats">Accepted formats: PDF, JPG, PNG. Max file size 5MB per document.</p>
          </div>
        </div>

        <!-- Legacy containers kept for JS compatibility -->
        <div id="edit_fileList" style="display:none;" aria-hidden="true"></div>
        <div id="edit_existing_documents" style="display:none;" aria-hidden="true">
          <ul id="edit_existing_docs_list"></ul>
        </div>
      </div>

      <div class="edit-modal-footer">
        <button type="button" class="edit-btn-cancel" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="edit-btn-save">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Receipt Modal -->
<div id="editReceiptModal" class="modal edit-notif-modal" onclick="if(event.target===this)closeEditReceiptModal()">
  <div class="edit-modal-panel" onclick="event.stopPropagation()">
    <div class="edit-modal-header">
      <div class="edit-modal-header-main">
        <div class="edit-modal-icon"><i class="fas fa-receipt"></i></div>
        <div>
          <h2 data-translate="modal.editReceipt">Edit Receipt</h2>
          <p class="edit-modal-subtitle">Update your receipt and resubmit for review</p>
        </div>
      </div>
      <button type="button" class="edit-modal-close" onclick="closeEditReceiptModal()" aria-label="Close">&times;</button>
    </div>
    <form id="editReceiptForm" enctype="multipart/form-data">
      <input type="hidden" id="edit_receipt_id" name="receipt_id">
      <input type="hidden" name="action" value="update_receipt">

      <div class="edit-modal-body">
        <div id="edit_receipt_admin_feedback" class="edit-help-box edit-help-box--alert" style="display:none;">
          <div class="edit-help-icon edit-help-icon--alert"><i class="fas fa-circle-exclamation"></i></div>
          <div class="edit-help-content">
            <h4 class="edit-help-title--alert">Admin feedback</h4>
            <p id="edit_receipt_admin_feedback_text" class="edit-help-text--alert"></p>
          </div>
        </div>

        <div class="edit-modal-section">
          <div class="edit-modal-section-label"><i class="fas fa-store"></i> Stall Location</div>
          <div class="edit-field-grid">
            <div class="edit-field">
              <label for="edit_receipt_section">Section</label>
              <input type="text" id="edit_receipt_section" class="edit-field-readonly" readonly disabled>
            </div>
            <div class="edit-field">
              <label for="edit_receipt_block">Block</label>
              <input type="text" id="edit_receipt_block" class="edit-field-readonly" readonly disabled>
            </div>
          </div>
        </div>

        <div class="edit-modal-section">
          <div class="edit-modal-section-label"><i class="fas fa-image"></i> Receipt Image</div>
          <div id="edit_receipt_current_preview" class="edit-receipt-preview" aria-live="polite"></div>
          <div class="edit-field">
            <label for="edit_receipt_file">Replace receipt image</label>
            <div class="edit-receipt-file-wrap">
              <input type="file" id="edit_receipt_file" name="receipt_file" class="edit-receipt-file-input" accept="image/jpeg,image/png,image/gif,.jpg,.jpeg,.png,.gif">
              <label for="edit_receipt_file" class="edit-receipt-file-btn"><i class="fas fa-cloud-arrow-up"></i> Choose new image</label>
              <span id="edit_receipt_file_name" class="edit-receipt-file-name">No new file chosen</span>
            </div>
            <p class="edit-help-formats">JPEG, PNG, or GIF. Maximum file size 5 MB.</p>
          </div>
        </div>

        <div class="edit-modal-section">
          <div class="edit-modal-section-label"><i class="fas fa-sticky-note"></i> Notes</div>
          <div class="edit-field">
            <label for="edit_receipt_notes">Notes (optional)</label>
            <textarea id="edit_receipt_notes" name="notes" rows="3" maxlength="2000" placeholder="Add a note for the admin (optional)"></textarea>
          </div>
        </div>
      </div>

      <div class="edit-modal-footer">
        <button type="button" class="edit-btn-cancel" onclick="closeEditReceiptModal()">Cancel</button>
        <button type="submit" class="edit-btn-save" id="editReceiptSaveBtn">
          <i class="fas fa-paper-plane"></i> Resubmit Receipt
        </button>
      </div>
    </form>
  </div>
</div>

<!-- File Preview Modal -->
<div id="filePreviewModal" class="notif-popup-overlay" aria-hidden="true">
  <div class="notif-popup-content" role="dialog" aria-modal="true" aria-labelledby="filePreviewTitle">
    <div class="notif-popup-header">
      <h2 id="filePreviewTitle"><i class="fas fa-eye"></i> <span data-translate="modal.filePreview">File Preview</span></h2>
      <button type="button" class="notif-popup-close" onclick="closeFilePreviewModal()" aria-label="Close">&times;</button>
    </div>
    <div class="notif-popup-body">
      <div id="filePreviewContent" style="text-align: center; min-height: 200px; display: flex; align-items: center; justify-content: center; background: #f5f5f5; border-radius: 8px;">
        <p class="notif-files-status" style="margin: 0;">Loading preview...</p>
      </div>
      <div style="margin-top: 1rem; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
        <a id="filePreviewDownload" href="#" target="_blank" class="notif-edit-btn" style="text-decoration: none;">
          <i class="fas fa-download"></i> Download File
        </a>
        <button type="button" class="notif-edit-btn" style="background: #6b7280;" onclick="closeFilePreviewModal()">
          <i class="fas fa-times"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<div id="viewRequirementsModal" class="notif-popup-overlay" aria-hidden="true">
  <div class="notif-popup-content" role="dialog" aria-modal="true" aria-labelledby="viewRequirementsTitle">
    <div class="notif-popup-header">
      <h2 id="viewRequirementsTitle"><i class="fas fa-folder-open"></i> <span data-translate="modal.uploadedFiles">Uploaded Files</span></h2>
      <button type="button" class="notif-popup-close" onclick="closeViewRequirementsModal()" aria-label="Close">&times;</button>
    </div>
    <div class="notif-popup-body" id="viewRequirementsContent">
      <p class="notif-files-status" style="margin: 0;">Loading...</p>
    </div>
  </div>
</div>

<div id="receiptRequirementsModal" class="modal">
  <div class="modal-content" style="max-width: 560px; margin: auto; position: relative;">
    <div class="modal-header">
      <h2><i class="fas fa-receipt"></i> <span data-translate="modal.receiptRequirements">Receipt Requirements</span></h2>
      <button class="close-modal" onclick="closeReceiptRequirementsModal()">&times;</button>
    </div>
    <div style="padding: 20px;">
      <ul style="margin: 0; padding-left: 1.2rem; color: #374151; line-height: 1.7;">
        <li>Upload a clear photo or scan of your official payment receipt.</li>
        <li>Make sure the amount, date, and reference number are readable.</li>
        <li>Accepted formats: JPG, PNG, or PDF (max 5MB).</li>
        <li>Include the correct section and block for your stall when submitting.</li>
      </ul>
    </div>
  </div>
</div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Language change (aligned with menu.php)
    function changeLanguage(lang) {
      localStorage.setItem('preferredLanguage', lang);
      updateLanguageIcon(lang);
      translatePage(lang);
      var calModal = document.getElementById('calendarModal');
      if (calModal && calModal.classList.contains('show')) {
        renderCalendar();
      }
    }

    function updateLanguageIcon(lang) {
      var languageSelector = document.getElementById('languageSelector');
      var languageSelectorDesktop = document.getElementById('languageSelectorDesktop');
      if (languageSelector) {
        languageSelector.classList.remove('ph-selected', 'en-selected');
        languageSelector.classList.add(lang === 'ph' ? 'ph-selected' : 'en-selected');
      }
      if (languageSelectorDesktop) {
        languageSelectorDesktop.classList.remove('ph-selected', 'en-selected');
        languageSelectorDesktop.classList.add(lang === 'ph' ? 'ph-selected' : 'en-selected');
      }
    }

    function translatePage(lang) {
      const translations = {
        en: {
          'nav.home': 'Home',
          'nav.application': 'Application',
          'nav.renewal': 'Renewal',
          'nav.profile': 'Profile',
          'nav.notifications': 'Notifications',
          'nav.logout': 'Logout',
          'hero.welcome': 'Welcome',
          'hero.subtitle': 'to your daily market',
          'hero.title': 'Market Clearance in Its Purest Form.',
          'hero.description': 'Efficient, reliable, seamless. Our market clearance system is designed to stand out, just like your business.',
          'hero.apply': 'Apply Now',
          'modal.calendar': 'Calendar',
          'modal.calendar.coming': 'Calendar functionality coming soon',
          'confirm.logout': 'Are you sure you want to logout?',
          'video.promotional': 'Promotional Permit',
          'video.price': 'Price',
          'video.announcement': 'Announcement',
          'features.easyApplications': 'Easy Applications',
          'features.easyApplicationsDesc': 'Submit your market clearance applications quickly and efficiently through our streamlined process.',
          'features.quickRenewals': 'Quick Renewals',
          'features.quickRenewalsDesc': 'Renew your existing clearances with just a few clicks and documents on it.',
          'features.trackProgress': 'Track Progress',
          'features.trackProgressDesc': 'Monitor the status of your applications in real-time from your profile.',
          'page.applicationStatus': 'Application Status',
          'table.th.id': 'Application ID',
          'table.th.type': 'Type',
          'table.th.section': 'Section',
          'table.th.submitted': 'Submitted',
          'table.th.status': 'Status',
          'table.th.reviewed': 'Reviewed',
          'table.th.action': 'Action',
          'empty.noApplications': 'No recent applications found.',
          'empty.noActivity': 'No notifications or activity found.',
          'label.notReviewed': 'Not reviewed',
          'label.completed': 'Completed',
          'label.incompleteReason': 'Incomplete Reason:',
          'action.edit': 'Edit',
          'badge.new': 'New',
          'profile.darkModeTitle': 'Dark mode',
          'profile.darkModeAria': 'Toggle dark mode',
          'expand.showAll': 'Show all',
          'expand.more': 'more',
          'expand.showLess': 'Show less',
          'notif.roll.viewAll': 'View all notifications',
          'notif.roll.viewApplications': 'View all applications',
          'notif.roll.viewRenewals': 'View all renewals',
          'notif.roll.viewReceipts': 'View all receipts',
          'notif.roll.showLess': 'Show less',
          'filter.all': 'All',
          'filter.application': 'Application',
          'filter.renewal': 'Renewal',
          'filter.receipts': 'Receipts',
          'section.today': 'Today',
          'section.yesterday': 'Yesterday',
          'section.earlier': 'Earlier',
          'notif.titleLead': 'Notif',
          'notif.titleAccent': 'ications.',
          'notif.subtitle': 'Everything the allotment office sends you in one place — application updates, renewal reminders and receipts. Open a notice to read the full message, then jump straight to the action it needs.',
          'empty.noFilterResults': 'No notifications in this category.',
          'modal.editApplication': 'Edit Application',
          'modal.editReceipt': 'Edit Receipt',
          'modal.filePreview': 'File Preview',
          'modal.uploadedFiles': 'Uploaded Files',
          'modal.receiptRequirements': 'Receipt Requirements'
        },
        ph: {
          'nav.home': 'Tahanan',
          'nav.application': 'Aplikasyon',
          'nav.renewal': 'Pag-renew',
          'nav.profile': 'Profile',
          'nav.notifications': 'Mga Abiso',
          'nav.logout': 'Mag-logout',
          'hero.welcome': 'Maligayang pagdating',
          'hero.subtitle': 'sa iyong pang-araw-araw na palengke',
          'hero.title': 'Market Clearance sa Pinakadalisay na Anyo.',
          'hero.description': 'Mahusay, maaasahan, walang kahirap-hirap. Ang aming sistema ng market clearance ay idinisenyo upang maging natatangi, tulad ng inyong negosyo.',
          'hero.apply': 'Mag-apply Ngayon',
          'modal.calendar': 'Kalendaryo',
          'modal.calendar.coming': 'Ang functionality ng kalendaryo ay darating sa lalong madaling panahon',
          'confirm.logout': 'Sigurado ka bang gusto mong mag-logout?',
          'video.promotional': 'Promotional Permit',
          'video.price': 'Presyo',
          'video.announcement': 'Anunsyo',
          'features.easyApplications': 'Madaling Aplikasyon',
          'features.easyApplicationsDesc': 'Ipasa ang inyong market clearance applications nang mabilis at mahusay sa pamamagitan ng aming streamlined na proseso.',
          'features.quickRenewals': 'Mabilis na Pag-renew',
          'features.quickRenewalsDesc': 'I-renew ang inyong mga clearance nang may ilang clicks lamang at mga dokumento.',
          'features.trackProgress': 'Subaybayan ang Pag-unlad',
          'features.trackProgressDesc': 'Subaybayan ang status ng inyong mga aplikasyon nang real-time mula sa inyong profile.',
          'page.applicationStatus': 'Katayuan ng Aplikasyon',
          'table.th.id': 'ID ng Aplikasyon',
          'table.th.type': 'Uri',
          'table.th.section': 'Seksyon',
          'table.th.submitted': 'Isinumite',
          'table.th.status': 'Katayuan',
          'table.th.reviewed': 'Nasuri na',
          'table.th.action': 'Aksyon',
          'empty.noApplications': 'Walang kamakailang aplikasyon.',
          'empty.noActivity': 'Walang abiso o aktibidad.',
          'label.notReviewed': 'Hindi pa nasusuri',
          'label.completed': 'Tapos na',
          'label.incompleteReason': 'Dahilan ng Hindi Kumpleto:',
          'action.edit': 'I-edit',
          'badge.new': 'Bago',
          'profile.darkModeTitle': 'Madilim na mode',
          'profile.darkModeAria': 'Lumipat sa madilim / maliwanag na mode',
          'expand.showAll': 'Ipakita lahat',
          'expand.more': 'pa',
          'expand.showLess': 'Itago ang iba',
          'notif.roll.viewAll': 'Tingnan lahat ng abiso',
          'notif.roll.viewApplications': 'Tingnan lahat ng aplikasyon',
          'notif.roll.viewRenewals': 'Tingnan lahat ng pag-renew',
          'notif.roll.viewReceipts': 'Tingnan lahat ng resibo',
          'notif.roll.showLess': 'Ipakita nang mas kaunti',
          'filter.all': 'Lahat',
          'filter.application': 'Aplikasyon',
          'filter.renewal': 'Pag-renew',
          'filter.receipts': 'Resibo',
          'section.today': 'Ngayon',
          'section.yesterday': 'Kahapon',
          'section.earlier': 'Mas Maaga',
          'notif.titleLead': 'Notif',
          'notif.titleAccent': 'ications.',
          'notif.subtitle': 'Lahat ng padala ng allotment office sa iyo — update sa aplikasyon, paalala sa pag-renew, at resibo. Buksan ang abiso para basahin ang buong mensahe, tapos gawin agad ang kailangan.',
          'empty.noFilterResults': 'Walang abiso sa kategoryang ito.',
          'modal.editApplication': 'I-edit ang Aplikasyon',
          'modal.editReceipt': 'I-edit ang Resibo',
          'modal.filePreview': 'Preview ng File',
          'modal.uploadedFiles': 'Mga Na-upload na File',
          'modal.receiptRequirements': 'Mga Kinakailangan sa Resibo'
        }
      };

      const texts = translations[lang] || translations.en;

      document.querySelectorAll('[data-translate]').forEach(function (element) {
        const key = element.getAttribute('data-translate');
        if (!key || key.indexOf('nav.') === 0) return;
        if (texts[key]) {
          element.textContent = texts[key];
        }
      });

      document.querySelectorAll('[data-translate-title]').forEach(function (element) {
        const key = element.getAttribute('data-translate-title');
        if (!key || key.indexOf('nav.') === 0) return;
        if (texts[key]) {
          element.title = texts[key];
        }
      });

      document.querySelectorAll('[data-translate-aria]').forEach(function (element) {
        const key = element.getAttribute('data-translate-aria');
        if (texts[key]) {
          element.setAttribute('aria-label', texts[key]);
        }
      });

      var heroSubtitle = document.querySelector('.hero-subtitle');
      if (heroSubtitle) {
        var userName = heroSubtitle.querySelector('.user-name');
        if (userName) {
          heroSubtitle.innerHTML = texts['hero.welcome'] + ', <span class="user-name">' + userName.textContent + '</span>';
        } else {
          heroSubtitle.textContent = texts['hero.subtitle'];
        }
      }

      var heroTitle = document.querySelector('.hero-title');
      if (heroTitle) {
        var highlights = heroTitle.querySelectorAll('.highlight');
        if (highlights.length >= 2) {
          if (lang === 'ph') {
            heroTitle.innerHTML = '<span class="highlight">Market Clearance</span> sa Pinakadalisay na<br><span class="highlight">Anyong</span> Ito.';
          } else {
            heroTitle.innerHTML = '<span class="highlight">Market Clearance</span> in Its<br><span class="highlight">Purest</span> Form.';
          }
        }
      }

      var heroDescription = document.querySelector('.hero-description');
      if (heroDescription) heroDescription.textContent = texts['hero.description'];

      var calendarIconLink = document.querySelector('#calendarIconLink');
      if (calendarIconLink) {
        calendarIconLink.title = texts['modal.calendar'];
      }
      var calendarIconLinkDesktop = document.querySelector('#calendarIconLinkDesktop');
      if (calendarIconLinkDesktop) {
        calendarIconLinkDesktop.title = texts['modal.calendar'];
      }

      var calendarComing = document.querySelector('[data-translate="modal.calendar.coming"]');
      if (calendarComing) {
        calendarComing.textContent = texts['modal.calendar.coming'];
      }

      var featureCards = document.querySelectorAll('.feature-card');
      featureCards.forEach(function (card, index) {
        var h3 = card.querySelector('h3');
        var p = card.querySelector('p');
        if (index === 0 && h3 && p) {
          h3.textContent = texts['features.easyApplications'];
          p.textContent = texts['features.easyApplicationsDesc'];
        } else if (index === 1 && h3 && p) {
          h3.textContent = texts['features.quickRenewals'];
          p.textContent = texts['features.quickRenewalsDesc'];
        } else if (index === 2 && h3 && p) {
          h3.textContent = texts['features.trackProgress'];
          p.textContent = texts['features.trackProgressDesc'];
        }
      });
      window.APPLICATION_I18N = translations;
      updateNotifRollButtonText(lang);
    }

    var notifRollTexts = {
      en: {
        'notif.roll.viewAll': 'View all notifications',
        'notif.roll.viewApplications': 'View all applications',
        'notif.roll.viewRenewals': 'View all renewals',
        'notif.roll.viewReceipts': 'View all receipts',
        'notif.roll.showLess': 'Show less'
      },
      ph: {
        'notif.roll.viewAll': 'Tingnan lahat ng abiso',
        'notif.roll.viewApplications': 'Tingnan lahat ng aplikasyon',
        'notif.roll.viewRenewals': 'Tingnan lahat ng pag-renew',
        'notif.roll.viewReceipts': 'Tingnan lahat ng resibo',
        'notif.roll.showLess': 'Ipakita nang mas kaunti'
      }
    };

    function confirmLogout() {
      var lang = localStorage.getItem('preferredLanguage') || 'en';
      var translations = {
        en: {
          'confirm.logout': 'Are you sure you want to logout?'
        },
        ph: {
          'confirm.logout': 'Sigurado ka bang gusto mong mag-logout?'
        }
      };
      var texts = translations[lang] || translations.en;
      return confirm(texts['confirm.logout']);
    }

    /* Calendar — same rendering as menu.php (#calendarContainer innerHTML, orange circle = today) */
    var monthNamesEn = ['January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'];
    var monthNamesPh = ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo',
      'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
    var dayNamesEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var dayNamesPh = ['Lin', 'Lun', 'Mar', 'Miy', 'Huw', 'Bi', 'Sab'];

    function renderCalendar() {
      var today = new Date();
      var currentYear = today.getFullYear();
      var currentMonth = today.getMonth();
      var currentDate = today.getDate();

      var firstDay = new Date(currentYear, currentMonth, 1).getDay();
      var daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

      var lang = localStorage.getItem('preferredLanguage') || 'en';
      var monthNames = lang === 'ph' ? monthNamesPh : monthNamesEn;
      var dayNames = lang === 'ph' ? dayNamesPh : dayNamesEn;

      var calendarHTML = '<div style="text-align: center; margin-bottom: 1.5rem;">' +
        '<h4 style="color: #667eea; font-weight: 600; margin: 0;">' +
        monthNames[currentMonth] + ' ' + currentYear +
        '</h4></div>' +
        '<table style="width: 100%; border-collapse: collapse;"><thead><tr>';

      var d;
      for (d = 0; d < dayNames.length; d++) {
        calendarHTML += '<th style="padding: 0.5rem; color: #667eea; font-weight: 600; font-size: 0.85rem; text-align: center;">' +
          dayNames[d] + '</th>';
      }
      calendarHTML += '</tr></thead><tbody>';

      var date = 1;
      var i;
      var j;
      for (i = 0; i < 6; i++) {
        calendarHTML += '<tr>';
        for (j = 0; j < 7; j++) {
          if (i === 0 && j < firstDay) {
            calendarHTML += '<td style="padding: 0.5rem;"></td>';
          } else if (date > daysInMonth) {
            calendarHTML += '<td style="padding: 0.5rem;"></td>';
          } else {
            var isToday = date === currentDate;
            var cellStyle = isToday
              ? 'background: #ff6b35; color: #ffffff; border-radius: 50%; font-weight: 700;'
              : 'color: #475569;';
            calendarHTML += '<td style="padding: 0.5rem; text-align: center;">' +
              '<div style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin: 0 auto; ' +
              cellStyle + '">' + date + '</div></td>';
            date++;
          }
        }
        calendarHTML += '</tr>';
        if (date > daysInMonth) break;
      }

      calendarHTML += '</tbody></table>';

      var calendarContainer = document.getElementById('calendarContainer');
      if (calendarContainer) {
        calendarContainer.innerHTML = calendarHTML;
      }
    }

    function setupSectionExpand(btnId, wrapId) {
      var btn = document.getElementById(btnId);
      var wrap = document.getElementById(wrapId);
      if (!btn || !wrap) return;
      btn.addEventListener('click', function () {
        var expanded = wrap.classList.toggle('is-expanded');
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var c = btn.querySelector('.when-collapsed');
        var e = btn.querySelector('.when-expanded');
        if (c) c.style.display = expanded ? 'none' : '';
        if (e) e.style.display = expanded ? '' : 'none';
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      var savedLang = localStorage.getItem('preferredLanguage') || 'en';
      var langSelect = document.getElementById('languageSelect');
      var langSelectDesktop = document.getElementById('languageSelectDesktop');
      if (langSelect) {
        langSelect.value = savedLang;
      }
      if (langSelectDesktop) {
        langSelectDesktop.value = savedLang;
      }
      if (langSelect && langSelectDesktop) {
        langSelect.addEventListener('change', function () {
          langSelectDesktop.value = this.value;
          changeLanguage(this.value);
        });
        langSelectDesktop.addEventListener('change', function () {
          langSelect.value = this.value;
          changeLanguage(this.value);
        });
      } else if (langSelect) {
        langSelect.addEventListener('change', function () {
          changeLanguage(this.value);
        });
      } else if (langSelectDesktop) {
        langSelectDesktop.addEventListener('change', function () {
          changeLanguage(this.value);
        });
      }
      updateLanguageIcon(savedLang);
      translatePage(savedLang);

      var navbarToggler = document.getElementById('navbarToggler');
      var navbarCenter = document.getElementById('navbarCenter');
      var navbarRight = document.querySelector('.navbar-right');
      var navbarOverlay = document.getElementById('navbarOverlay');

      function closeSidebar() {
        if (window.innerWidth <= 991) {
          if (navbarToggler) navbarToggler.setAttribute('aria-expanded', 'false');
          if (navbarCenter) navbarCenter.classList.remove('show');
          if (navbarOverlay) navbarOverlay.classList.remove('show');
          document.body.style.overflow = '';
        }
      }

      if (navbarToggler && navbarCenter) {
        navbarToggler.addEventListener('click', function (e) {
          e.stopPropagation();
          var isExpanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', !isExpanded);
          navbarCenter.classList.toggle('show');
          if (window.innerWidth <= 991) {
            if (navbarOverlay) navbarOverlay.classList.toggle('show');
            document.body.style.overflow = isExpanded ? '' : 'hidden';
          }
        });
        if (navbarOverlay) {
          navbarOverlay.addEventListener('click', function () {
            closeSidebar();
          });
        }
        document.addEventListener('click', function (event) {
          if (window.innerWidth <= 991) {
            var isClickInside = navbarToggler.contains(event.target) ||
              navbarCenter.contains(event.target);
            if (!isClickInside && navbarCenter.classList.contains('show')) {
              closeSidebar();
            }
          }
        });
        if (navbarRight) {
          var logoutBtn = navbarRight.querySelector('#logoutBtn');
          if (logoutBtn) {
            logoutBtn.addEventListener('click', function () {
              if (window.innerWidth <= 991) closeSidebar();
            });
          }
        }
        window.addEventListener('resize', function () {
          if (window.innerWidth > 991) {
            navbarCenter.classList.remove('show');
            navbarToggler.setAttribute('aria-expanded', 'false');
            if (navbarOverlay) navbarOverlay.classList.remove('show');
            document.body.style.overflow = '';
          }
        });

        document.querySelectorAll('#calendarIconLink, #calendarIconLinkDesktop').forEach(function (calLink) {
          calLink.addEventListener('click', function () {
            if (window.innerWidth <= 991) closeSidebar();
          });
        });
      }

      var calendarModal = document.getElementById('calendarModal');
      if (calendarModal) {
        calendarModal.addEventListener('show.bs.modal', function () {
          renderCalendar();
        });
        calendarModal.addEventListener('hidden.bs.modal', function () {
          document.body.style.overflow = '';
          document.body.classList.remove('modal-open');
          document.body.style.paddingRight = '';
        });
      }

      setupNotifFilters();
      setupNotifRollBtn();
    });

    var notifActiveFilter = 'all';
    var notifListExpanded = false;
    var NOTIF_DEFAULT_LIMIT = 5;

    function getNotifRollViewKey(filter) {
      switch (filter) {
        case 'application': return 'notif.roll.viewApplications';
        case 'renewal': return 'notif.roll.viewRenewals';
        case 'receipt': return 'notif.roll.viewReceipts';
        default: return 'notif.roll.viewAll';
      }
    }

    function updateNotifRollButtonText(lang) {
      var btn = document.getElementById('notifRollBtn');
      if (!btn) return;
      var expanded = btn.getAttribute('data-roll-expanded') === '1';
      var texts = notifRollTexts[lang] || notifRollTexts.en;
      var key = expanded ? 'notif.roll.showLess' : getNotifRollViewKey(notifActiveFilter);
      var label = texts[key] || notifRollTexts.en[key] || key;
      var iconClass = expanded ? 'fa-chevron-up' : 'fa-chevron-down';
      btn.innerHTML = '<i class="fas ' + iconClass + '"></i> <span data-translate="' + key + '">' + label + '</span>';
    }

    function applyNotifListState() {
      var list = document.getElementById('notificationsListWrap');
      var wrap = document.getElementById('notifRollWrap');
      var btn = document.getElementById('notifRollBtn');
      if (!list) return;

      var limit = notifListExpanded ? 9999 : NOTIF_DEFAULT_LIMIT;
      var cards = Array.prototype.slice.call(list.querySelectorAll('.notif-card'));
      var visibleCount = 0;
      var matchingCount = 0;

      cards.forEach(function (card) {
        var cat = card.getAttribute('data-category') || '';
        var matchesFilter = notifActiveFilter === 'all' || cat === notifActiveFilter;
        card.classList.toggle('is-hidden', !matchesFilter);
        if (matchesFilter) {
          matchingCount++;
        }
      });

      cards.forEach(function (card) {
        if (card.classList.contains('is-hidden')) {
          card.classList.remove('is-collapsed');
          return;
        }
        if (visibleCount >= limit) {
          card.classList.add('is-collapsed');
        } else {
          card.classList.remove('is-collapsed');
          visibleCount++;
        }
      });

      list.querySelectorAll('.notif-date-group').forEach(function (group) {
        var visibleInGroup = group.querySelectorAll('.notif-card:not(.is-hidden):not(.is-collapsed)').length;
        group.classList.toggle('is-hidden', visibleInGroup === 0);
      });

      if (wrap) {
        wrap.classList.toggle('is-visible', matchingCount > NOTIF_DEFAULT_LIMIT);
      }
      if (btn) {
        btn.classList.toggle('expanded', notifListExpanded);
        btn.setAttribute('data-roll-expanded', notifListExpanded ? '1' : '0');
        btn.setAttribute('aria-expanded', notifListExpanded ? 'true' : 'false');
      }
      updateNotifRollButtonText(localStorage.getItem('preferredLanguage') || 'en');
    }

    function setupNotifRollBtn() {
      var btn = document.getElementById('notifRollBtn');
      if (!btn) return;
      btn.addEventListener('click', function () {
        notifListExpanded = !notifListExpanded;
        applyNotifListState();
      });
      applyNotifListState();
    }

    function syncNotifFilterToggle() {
      var row = document.getElementById('notifFilterRow');
      if (!row) return;
      var active = row.querySelector('.notif-filter-chip.is-active');
      var nameEl = row.querySelector('.notif-filter-toggle-name');
      var countEl = document.getElementById('notifFilterToggleCount');
      if (!active || !nameEl) return;
      var label = active.querySelector('[data-translate]');
      var count = active.querySelector('.notif-filter-count');
      if (label) {
        nameEl.textContent = label.textContent;
        nameEl.setAttribute('data-translate', label.getAttribute('data-translate') || 'filter.all');
      }
      if (countEl && count) countEl.textContent = count.textContent;
    }

    function setNotifFilterOpen(open) {
      var row = document.getElementById('notifFilterRow');
      var toggle = document.getElementById('notifFilterToggle');
      if (!row || !toggle) return;
      row.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function setupNotifFilters() {
      var row = document.getElementById('notifFilterRow');
      var toggle = document.getElementById('notifFilterToggle');
      if (!row) return;

      if (toggle) {
        toggle.addEventListener('click', function (event) {
          event.stopPropagation();
          setNotifFilterOpen(!row.classList.contains('is-open'));
        });
      }

      document.addEventListener('click', function (event) {
        if (!row.contains(event.target)) setNotifFilterOpen(false);
      });

      row.querySelectorAll('.notif-filter-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          notifActiveFilter = chip.getAttribute('data-filter') || 'all';
          notifListExpanded = false;
          row.querySelectorAll('.notif-filter-chip').forEach(function (c) {
            c.classList.toggle('is-active', c === chip);
            c.setAttribute('aria-selected', c === chip ? 'true' : 'false');
          });
          syncNotifFilterToggle();
          setNotifFilterOpen(false);
          applyNotifListState();
        });
      });
    }

    // Dark mode (same localStorage as profile.php)
    (function () {
      var darkmode = localStorage.getItem('darkmode');
      var enableDarkmode = function () {
        document.body.classList.add('darkmode');
        localStorage.setItem('darkmode', 'active');
      };
      var disableDarkmode = function () {
        document.body.classList.remove('darkmode');
        try {
          localStorage.removeItem('darkmode');
        } catch (e) {}
      };
      if (darkmode === 'active') enableDarkmode();
      document.querySelectorAll('.profile-darkmode-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          darkmode = localStorage.getItem('darkmode');
          darkmode !== 'active' ? enableDarkmode() : disableDarkmode();
        });
      });
    })();

  // Parse JSON from notification.php even if PHP emitted warnings before the JSON body
  function parseNotificationPhpJson(response, text) {
    const trimmed = (text || '').trim();
    let data;
    try {
      data = JSON.parse(trimmed);
    } catch (parseErr) {
      const m = trimmed.match(/\{[\s\S]*\}/);
      if (m) {
        try {
          data = JSON.parse(m[0]);
        } catch (e2) {
          console.error('Non-JSON response:', trimmed.substring(0, 500));
          throw new Error('Server returned invalid data (not JSON).');
        }
      } else {
        console.error('Non-JSON response:', trimmed.substring(0, 500));
        throw new Error('Server returned invalid data (not JSON).');
      }
    }
    if (!response.ok) {
      const msg = (data && data.message) ? data.message : ('HTTP ' + response.status);
      throw new Error(msg);
    }
    return data;
  }

  function normalizeFileUrl(path) {
    if (!path) return '';
    var url = String(path).replace(/\\/g, '/');
    if (/^https?:\/\//i.test(url)) return url;
    return url.replace(/^\/+/, '');
  }

  function isImageFilePath(path) {
    var ext = String(path || '').split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].indexOf(ext) !== -1;
  }

  function isPdfFilePath(path) {
    return String(path || '').split('.').pop().toLowerCase() === 'pdf';
  }

  function formatNotifFileDate(value) {
    if (!value) return 'N/A';
    var d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function formatNotifFileSize(bytes) {
    var size = Number(bytes) || 0;
    if (size >= 1048576) return (size / 1048576).toFixed(2) + ' MB';
    return (size / 1024).toFixed(2) + ' KB';
  }

  function openNotifPopup(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeNotifPopup(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.notif-popup-overlay.is-open')) {
      document.body.style.overflow = '';
    }
  }

  function buildOpenFullViewButton(filePath, fileName) {
    var path = filePath || '';
    var name = fileName || 'file';
    return (
      '<button type="button" class="notif-preview-file-btn" data-file-path="' + escapeHtml(path) + '" data-file-name="' + escapeHtml(name) + '" style="background: #667eea; color: white; border: none; padding: 0.45rem 0.9rem; border-radius: 5px; cursor: pointer; font-size: 0.85rem;">' +
        '<i class="fas fa-eye"></i> Open full view' +
      '</button>'
    );
  }

  function handleNotifEyeClick(btn) {
    if (!btn) return;
    var category = btn.getAttribute('data-feed-category') || '';
    var vendorId = parseInt(btn.getAttribute('data-vendor-id'), 10) || 0;
    var receiptId = parseInt(btn.getAttribute('data-receipt-id'), 10) || 0;
    if (category === 'receipt') {
      openReceiptViewModal(receiptId);
    } else {
      openViewRequirementsModal(vendorId);
    }
  }

  // Edit Application Modal Functions
  function openViewRequirementsModal(vendorId) {
    if (!vendorId) {
      openNotifPopup('viewRequirementsModal');
      document.getElementById('viewRequirementsContent').innerHTML =
        '<p class="notif-files-status" style="margin: 0;">Unable to load files for this notification. Please open your application from the Application or Renewal page.</p>';
      return;
    }

    const content = document.getElementById('viewRequirementsContent');
    openNotifPopup('viewRequirementsModal');
    content.innerHTML = '<p class="notif-files-status" style="margin: 0;">Loading uploaded files...</p>';

    const formData = new FormData();
    formData.append('action', 'get_vendor_data');
    formData.append('vendor_id', vendorId);

    fetch('notification.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      cache: 'no-store'
    })
    .then(response => response.text().then(text => parseNotificationPhpJson(response, text)))
    .then(data => {
      if (!data.success) {
        content.innerHTML = '<p class="notif-files-status">' + escapeHtml(data.message || 'Failed to load details.') + '</p>';
        return;
      }

      document.getElementById('viewRequirementsTitle').innerHTML = '<i class="fas fa-folder-open"></i> Uploaded Files';

      const vendor = data.vendor || {};
      const requirements = data.requirements || [];
      const documents = data.documents || [];
      const appLabel = (vendor.application_type === 'renewal') ? 'Renewal' : 'Application';
      const loc = [vendor.section, vendor.block ? ('Block ' + vendor.block) : ''].filter(Boolean).join(', ');

      let html = '<div class="notif-files-panel">';
      html += '<div><strong>' + escapeHtml(appLabel) + ':</strong> ' + escapeHtml(vendor.vendor_name || 'N/A') + '</div>';
      if (loc) html += '<div><strong>Location:</strong> ' + escapeHtml(loc) + '</div>';
      html += '<div><strong>Status:</strong> ' + escapeHtml((vendor.status || 'pending').replace(/_/g, ' ')) + '</div>';
      if (vendor.submitted_date) html += '<div><strong>Submitted:</strong> ' + escapeHtml(formatNotifFileDate(vendor.submitted_date)) + '</div>';
      html += '</div>';

      html += '<h3 class="notif-files-heading">Required Documents</h3><ul class="notif-files-reqs">';
      if (requirements.length === 0) {
        html += '<li>No requirements listed.</li>';
      } else {
        requirements.forEach(function (req) {
          const label = (req.name || '') + (req.description ? ' (' + req.description + ')' : '');
          html += '<li>' + escapeHtml(label) + '</li>';
        });
      }
      html += '</ul>';

      html += '<h3 class="notif-files-heading">Uploaded Files</h3>';
      if (documents.length === 0) {
        html += '<p class="notif-files-status" style="margin: 0;">No files uploaded yet.</p>';
      } else {
        html += '<ul style="margin: 0; padding: 0; list-style: none;">';
        documents.forEach(function (doc) {
          var filePath = normalizeFileUrl(doc.file_path || '');
          var fileName = doc.original_name || doc.file_name || 'File';
          html += '<li class="notif-files-item">';
          html += '<div><i class="fas fa-file"></i> <strong>' + escapeHtml(fileName) + '</strong></div>';
          html += '<div class="notif-files-meta">';
          html += escapeHtml((doc.file_type || 'file').toUpperCase()) + ' · ' + escapeHtml(formatNotifFileSize(doc.file_size)) + ' · Uploaded ' + escapeHtml(formatNotifFileDate(doc.upload_date));
          html += '</div>';
          if (filePath && isImageFilePath(filePath)) {
            html += '<div style="margin-top: 0.75rem; text-align: center;">';
            html += '<img src="' + escapeHtml(filePath) + '" alt="' + escapeHtml(fileName) + '" style="max-width: 100%; max-height: 220px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">';
            html += '</div>';
          } else if (filePath && isPdfFilePath(filePath)) {
            html += '<div style="margin-top: 0.75rem;">';
            html += '<iframe src="' + escapeHtml(filePath) + '" title="' + escapeHtml(fileName) + '" style="width: 100%; height: 260px; border: none; border-radius: 8px; background: #fff;"></iframe>';
            html += '</div>';
          }
          html += '</li>';
        });
        html += '</ul>';
      }

      content.innerHTML = html;
    })
    .catch(function (error) {
      console.error('Error:', error);
      content.innerHTML = '<p class="notif-files-status">Network error: ' + escapeHtml(error.message) + '</p>';
    });
  }

  function closeViewRequirementsModal() {
    closeNotifPopup('viewRequirementsModal');
  }

  function openReceiptViewModal(receiptId) {
    const content = document.getElementById('viewRequirementsContent');
    if (!receiptId) {
      openNotifPopup('viewRequirementsModal');
      content.innerHTML = '<p class="notif-files-status" style="margin: 0;">Receipt file is not linked to this notification yet. Check Profile to view your latest receipt.</p>';
      return;
    }

    openNotifPopup('viewRequirementsModal');
    content.innerHTML = '<p class="notif-files-status" style="margin: 0;">Loading receipt...</p>';

    const formData = new FormData();
    formData.append('action', 'get_receipt_data');
    formData.append('receipt_id', receiptId);

    fetch('notification.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => response.text().then(text => parseNotificationPhpJson(response, text)))
    .then(data => {
      if (!data.success) {
        content.innerHTML = '<p class="notif-files-status">' + escapeHtml(data.message || 'Failed to load receipt.') + '</p>';
        return;
      }

      document.getElementById('viewRequirementsTitle').innerHTML = '<i class="fas fa-receipt"></i> Receipt Upload';

      const receipt = data.receipt || {};
      const loc = [receipt.section, receipt.block ? ('Block ' + receipt.block) : ''].filter(Boolean).join(', ');
      const filePath = normalizeFileUrl(receipt.receipt_file_path || '');
      const fileName = filePath ? filePath.split('/').pop() : 'Receipt image';
      const ext = filePath.split('.').pop().toLowerCase();
      const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

      let html = '<div class="notif-files-panel">';
      html += '<div><strong>Receipt #:</strong> ' + escapeHtml(String(receipt.id || '')) + '</div>';
      if (loc) html += '<div><strong>Location:</strong> ' + escapeHtml(loc) + '</div>';
      html += '<div><strong>Status:</strong> ' + escapeHtml((receipt.status || 'pending').replace(/_/g, ' ')) + '</div>';
      html += '<div><strong>Submitted:</strong> ' + escapeHtml(formatNotifFileDate(receipt.created_at)) + '</div>';
      if (receipt.notes) html += '<div><strong>Notes:</strong> ' + escapeHtml(receipt.notes) + '</div>';
      html += '</div>';

      html += '<h3 class="notif-files-heading">Uploaded Receipt</h3>';
      if (!filePath) {
        html += '<p class="notif-files-status" style="margin: 0;">No receipt file found.</p>';
      } else {
        html += '<div class="notif-files-item">';
        html += '<div style="margin-bottom: 0.75rem;"><i class="fas fa-file-image"></i> <strong>' + escapeHtml(fileName) + '</strong></div>';
        if (isImage) {
          html += '<div style="text-align: center; margin-bottom: 0.75rem;"><img src="' + escapeHtml(filePath) + '" alt="Receipt" style="max-width: 100%; max-height: 280px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);"></div>';
        }
        html += buildOpenFullViewButton(filePath, fileName);
        html += '</div>';
      }

      content.innerHTML = html;
    })
    .catch(function (error) {
      console.error('Error:', error);
      content.innerHTML = '<p class="notif-files-status">Network error: ' + escapeHtml(error.message) + '</p>';
    });
  }

  function closeReceiptRequirementsModal() {
    closeViewRequirementsModal();
  }

  function buildEditDocRowHtml(reqType, rowNumber, reqName, existingDoc) {
    const hasFile = !!existingDoc;
    const fileName = existingDoc ? (existingDoc.original_name || 'Uploaded file') : '';
    const docId = existingDoc ? existingDoc.document_id : '';
    const statusClass = hasFile ? 'edit-doc-status--done' : 'edit-doc-status--pending';
    const statusContent = hasFile ? '<i class="fas fa-check"></i>' : String(rowNumber);
    const fileHtml = hasFile
      ? '<i class="fas fa-paperclip"></i> ' + escapeHtml(fileName)
      : 'No file uploaded';
    let actionsHtml = '';
    if (hasFile) {
      actionsHtml =
        '<button type="button" class="edit-doc-btn edit-doc-btn--remove" onclick="removeExistingDocument(' + docId + ', this)" title="Remove file"><i class="fas fa-times"></i></button>' +
        '<button type="button" class="edit-doc-btn edit-doc-btn--replace" onclick="document.getElementById(\'file_' + reqType + '_edit\').click()"><i class="fas fa-rotate"></i> Replace</button>';
    } else {
      actionsHtml =
        '<button type="button" class="edit-doc-btn edit-doc-btn--upload" onclick="document.getElementById(\'file_' + reqType + '_edit\').click()"><i class="fas fa-upload"></i> Upload</button>';
    }
    return (
      '<div class="edit-doc-row' + (hasFile ? ' is-complete' : ' is-pending') + '" id="edit_req_row_' + reqType + '" data-req-type="' + reqType + '"' + (docId ? ' data-document-id="' + docId + '"' : '') + '>' +
        '<div class="edit-doc-status ' + statusClass + '" id="edit_status_' + reqType + '">' + statusContent + '</div>' +
        '<div class="edit-doc-info">' +
          '<div class="edit-doc-name">' + escapeHtml(reqName) + '</div>' +
          '<div class="edit-doc-file" id="edit_doc_file_' + reqType + '">' + fileHtml + '</div>' +
        '</div>' +
        '<div class="edit-doc-actions" id="edit_doc_actions_' + reqType + '">' + actionsHtml + '</div>' +
        '<input type="file" id="file_' + reqType + '_edit" name="documents[]" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display:none;" onchange="handleEditFileUpload(this, \'' + reqType + '\')">' +
        '<span id="check_' + reqType + '_edit" style="display:none;" aria-hidden="true"></span>' +
      '</div>'
    );
  }

  function setEditDocRowPending(reqType, rowNumber) {
    const row = document.getElementById('edit_req_row_' + reqType);
    if (!row) return;
    row.classList.remove('is-complete');
    row.classList.add('is-pending');
    row.removeAttribute('data-document-id');
    const status = document.getElementById('edit_status_' + reqType);
    if (status) {
      status.className = 'edit-doc-status edit-doc-status--pending';
      status.textContent = String(rowNumber);
    }
    const fileEl = document.getElementById('edit_doc_file_' + reqType);
    if (fileEl) {
      fileEl.className = 'edit-doc-file';
      fileEl.textContent = 'No file uploaded';
    }
    const actions = document.getElementById('edit_doc_actions_' + reqType);
    if (actions) {
      actions.innerHTML = '<button type="button" class="edit-doc-btn edit-doc-btn--upload" onclick="document.getElementById(\'file_' + reqType + '_edit\').click()"><i class="fas fa-upload"></i> Upload</button>';
    }
    const check = document.getElementById('check_' + reqType + '_edit');
    if (check) check.style.display = 'none';
  }

  function setEditDocRowUploaded(reqType, fileName, isNew) {
    const row = document.getElementById('edit_req_row_' + reqType);
    if (!row) return;
    row.classList.remove('is-pending');
    row.classList.add('is-complete');
    const status = document.getElementById('edit_status_' + reqType);
    if (status) {
      status.className = 'edit-doc-status edit-doc-status--done';
      status.innerHTML = '<i class="fas fa-check"></i>';
    }
    const fileEl = document.getElementById('edit_doc_file_' + reqType);
    if (fileEl) {
      fileEl.className = 'edit-doc-file' + (isNew ? ' is-new' : '');
      fileEl.innerHTML = '<i class="fas fa-paperclip"></i> ' + escapeHtml(fileName) + (isNew ? ' <span>(New)</span>' : '');
    }
    const actions = document.getElementById('edit_doc_actions_' + reqType);
    if (actions) {
      actions.innerHTML =
        '<button type="button" class="edit-doc-btn edit-doc-btn--remove" onclick="removeNewFileItem(\'file_edit_' + reqType + '\', \'file_' + reqType + '_edit\', \'check_' + reqType + '_edit\', \'' + reqType + '\')" title="Remove file"><i class="fas fa-times"></i></button>' +
        '<button type="button" class="edit-doc-btn edit-doc-btn--replace" onclick="document.getElementById(\'file_' + reqType + '_edit\').click()"><i class="fas fa-rotate"></i> Replace</button>';
    }
    const check = document.getElementById('check_' + reqType + '_edit');
    if (check) check.style.display = 'inline';
  }

  function openEditModal(vendorId, applicationType) {
    const modal = document.getElementById('editApplicationModal');
    modal.style.display = 'flex';
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Reset form
    document.getElementById('editApplicationForm').reset();
    document.getElementById('edit_requirements_list').innerHTML = '';
    document.getElementById('edit_existing_documents').style.display = 'none';
    document.getElementById('edit_existing_docs_list').innerHTML = '';
    document.getElementById('edit_fileList').style.display = 'none';
    document.getElementById('edit_fileList').innerHTML = '';
    filesToDelete = []; // Reset files to delete
    
    // Load vendor data
    const formData = new FormData();
    formData.append('action', 'get_vendor_data');
    formData.append('vendor_id', vendorId);
    
    fetch('notification.php', {
      method: 'POST',
      body: formData,
      cache: 'no-store'
    })
    .then(response => response.text().then(text => parseNotificationPhpJson(response, text)))
    .then(data => {
      if (data.success) {
        const vendor = data.vendor;
        const documents = data.documents || [];
        const requirements = data.requirements || [];
        
        // Fill form fields
        document.getElementById('edit_vendor_id').value = vendor.vendor_id;
        document.getElementById('edit_application_type').value = vendor.application_type;
        document.getElementById('edit_full_name').value = vendor.vendor_name || '';
        // Set section and block (disabled fields)
        const sectionSelect = document.getElementById('edit_section');
        const blockSelect = document.getElementById('edit_block');
        const sectionHidden = document.getElementById('edit_section_hidden');
        const blockHidden = document.getElementById('edit_block_hidden');
        
        if (sectionSelect && vendor.section) {
          sectionSelect.value = vendor.section;
          sectionHidden.value = vendor.section;
        }
        if (blockSelect && vendor.block) {
          blockSelect.value = vendor.block;
          blockHidden.value = vendor.block;
        }
        
        // Sync disabled selects with hidden inputs on change (if somehow changed)
        sectionSelect.addEventListener('change', function() {
          sectionHidden.value = this.value;
        });
        blockSelect.addEventListener('change', function() {
          blockHidden.value = this.value;
        });
        
        // Display requirements as document rows
        const reqTypes = ['id_card', 'dti', 'barangay', 'moa', 'lease', 'inspection', 'receipt'];
        let reqHTML = '';
        requirements.forEach((req, idx) => {
          const reqType = reqTypes[idx] || ('other_' + idx);
          const reqName = (req.name || 'Document') + (req.description ? ' (' + (req.description || '') + ')' : '');
          const existingDoc = documents[idx] || null;
          reqHTML += buildEditDocRowHtml(reqType, idx + 1, reqName, existingDoc);
        });
        document.getElementById('edit_requirements_list').innerHTML = reqHTML;
        
        // Keep legacy list in sync (hidden)
        const existingDocsList = document.getElementById('edit_existing_docs_list');
        if (existingDocsList) existingDocsList.innerHTML = '';
      } else {
        alert('Error: ' + (data.message || 'Failed to load application data'));
        closeEditModal();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Network error: ' + error.message);
      closeEditModal();
    });
  }
  
  function closeEditModal() {
    const modal = document.getElementById('editApplicationModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    if (!document.getElementById('editReceiptModal')?.classList.contains('show')) {
      document.body.style.overflow = '';
    }
  }

  function openEditReceiptModal(receiptId) {
    const modal = document.getElementById('editReceiptModal');
    if (!modal || !receiptId) return;
    modal.style.display = 'flex';
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    const form = document.getElementById('editReceiptForm');
    if (form) form.reset();
    document.getElementById('edit_receipt_id').value = String(receiptId);
    document.getElementById('edit_receipt_section').value = '';
    document.getElementById('edit_receipt_block').value = '';
    document.getElementById('edit_receipt_notes').value = '';
    document.getElementById('edit_receipt_current_preview').innerHTML = '<p class="edit-receipt-preview-empty">Loading...</p>';
    const fileNameEl = document.getElementById('edit_receipt_file_name');
    if (fileNameEl) fileNameEl.textContent = 'No new file chosen';
    const fileInput = document.getElementById('edit_receipt_file');
    if (fileInput) fileInput.value = '';

    const feedbackBox = document.getElementById('edit_receipt_admin_feedback');
    const feedbackText = document.getElementById('edit_receipt_admin_feedback_text');
    if (feedbackBox) feedbackBox.style.display = 'none';
    if (feedbackText) feedbackText.textContent = '';

    const formData = new FormData();
    formData.append('action', 'get_receipt_data');
    formData.append('receipt_id', receiptId);

    fetch('notification.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => response.text().then(text => parseNotificationPhpJson(response, text)))
    .then(data => {
      if (!data.success) {
        alert('Error: ' + (data.message || 'Failed to load receipt'));
        closeEditReceiptModal();
        return;
      }

      const receipt = data.receipt || {};
      document.getElementById('edit_receipt_section').value = receipt.section || '—';
      document.getElementById('edit_receipt_block').value = receipt.block || '—';
      document.getElementById('edit_receipt_notes').value = receipt.user_notes || '';

      if (receipt.admin_feedback && feedbackBox && feedbackText) {
        feedbackText.textContent = receipt.admin_feedback;
        feedbackBox.style.display = 'flex';
      }

      const preview = document.getElementById('edit_receipt_current_preview');
      const filePath = normalizeFileUrl(receipt.receipt_file_path || '');
      if (preview) {
        if (filePath) {
          preview.innerHTML = '<img src="' + escapeHtml(filePath) + '" alt="Current receipt">';
        } else {
          preview.innerHTML = '<p class="edit-receipt-preview-empty">No receipt image on file. Please upload one below.</p>';
        }
      }
    })
    .catch(function (error) {
      console.error('Error:', error);
      alert('Network error: ' + error.message);
      closeEditReceiptModal();
    });
  }

  function closeEditReceiptModal() {
    const modal = document.getElementById('editReceiptModal');
    if (!modal) return;
    modal.style.display = 'none';
    modal.classList.remove('show');
    if (!document.getElementById('editApplicationModal')?.classList.contains('show')) {
      document.body.style.overflow = '';
    }
  }

  (function () {
    const fileInput = document.getElementById('edit_receipt_file');
    const fileNameEl = document.getElementById('edit_receipt_file_name');
    if (!fileInput || !fileNameEl) return;
    fileInput.addEventListener('change', function () {
      if (fileInput.files && fileInput.files.length) {
        fileNameEl.textContent = fileInput.files[0].name;
      } else {
        fileNameEl.textContent = 'No new file chosen';
      }
    });
  })();
  
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.notif-preview-file-btn');
    if (!btn) return;
    e.preventDefault();
    previewFile(btn.getAttribute('data-file-path') || '', btn.getAttribute('data-file-name') || 'file');
  });

  // File Preview Functions
  function previewFile(filePath, fileName) {
    const previewContent = document.getElementById('filePreviewContent');
    const downloadLink = document.getElementById('filePreviewDownload');
    const safePath = normalizeFileUrl(filePath);
    const safeName = fileName || 'file';

    if (!safePath) {
      alert('Receipt file path is missing. Please try again or contact support.');
      return;
    }

    downloadLink.href = safePath;
    downloadLink.download = safeName;
    downloadLink.target = '_blank';
    downloadLink.rel = 'noopener noreferrer';

    openNotifPopup('filePreviewModal');

    const fileExtension = safePath.split('.').pop().toLowerCase();
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    const pdfExtensions = ['pdf'];

    previewContent.innerHTML = '<p class="notif-files-status">Loading preview...</p>';

    if (imageExtensions.includes(fileExtension)) {
      previewContent.innerHTML = '<img src="' + escapeHtml(safePath) + '" alt="' + escapeHtml(safeName) + '" style="max-width: 100%; max-height: 70vh; border-radius: 5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background: #ffffff;">';
    } else if (pdfExtensions.includes(fileExtension)) {
      previewContent.innerHTML = '<iframe src="' + escapeHtml(safePath) + '" style="width: 100%; height: 70vh; border: none; border-radius: 5px; background: #ffffff;"></iframe>';
    } else {
      previewContent.innerHTML =
        '<div class="notif-files-status" style="text-align: center;">' +
        '<i class="fas fa-file" style="font-size: 64px; margin-bottom: 20px;"></i>' +
        '<p style="font-size: 18px; margin-bottom: 10px;"><strong>' + escapeHtml(safeName) + '</strong></p>' +
        '<p>Preview not available for this file type.</p>' +
        '<p style="font-size: 14px; margin-top: 10px;">Click "Download File" to view the file.</p>' +
        '</div>';
    }
  }
  
  function closeFilePreviewModal() {
    closeNotifPopup('filePreviewModal');
    document.getElementById('filePreviewContent').innerHTML = '<p class="notif-files-status" style="margin: 0;">Loading preview...</p>';
  }

  ['viewRequirementsModal', 'filePreviewModal'].forEach(function (modalId) {
    var overlay = document.getElementById(modalId);
    if (!overlay) return;
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        if (modalId === 'viewRequirementsModal') closeViewRequirementsModal();
        else closeFilePreviewModal();
      }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (document.getElementById('filePreviewModal') && document.getElementById('filePreviewModal').classList.contains('is-open')) {
      closeFilePreviewModal();
    } else if (document.getElementById('viewRequirementsModal') && document.getElementById('viewRequirementsModal').classList.contains('is-open')) {
      closeViewRequirementsModal();
    }
  });
  
  // Track files to be deleted
  let filesToDelete = [];
  
  function handleEditFileUpload(input, requirementType) {
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      
      if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        input.value = '';
        return;
      }

      const row = document.getElementById('edit_req_row_' + requirementType);
      const docId = row ? row.getAttribute('data-document-id') : null;
      if (docId && !filesToDelete.includes(parseInt(docId, 10))) {
        filesToDelete.push(parseInt(docId, 10));
      }

      setEditDocRowUploaded(requirementType, file.name, true);
    }
  }
  
  function removeNewFileItem(fileItemId, inputId, checkmarkId, requirementType) {
    const input = document.getElementById(inputId);
    if (input) input.value = '';
    const row = document.getElementById('edit_req_row_' + requirementType);
    let rowNumber = 1;
    if (row && row.parentElement) {
      const rows = row.parentElement.querySelectorAll('.edit-doc-row');
      rows.forEach(function (r, i) {
        if (r.id === 'edit_req_row_' + requirementType) rowNumber = i + 1;
      });
    }
    setEditDocRowPending(requirementType, rowNumber);
  }
  
  function removeExistingDocument(documentId, buttonElement) {
    if (!confirm('Are you sure you want to remove this document?')) {
      return;
    }
    
    if (!filesToDelete.includes(documentId)) {
      filesToDelete.push(documentId);
    }
    
    const row = buttonElement.closest('.edit-doc-row');
    const reqType = row ? row.getAttribute('data-req-type') : null;
    if (reqType) {
      const input = document.getElementById('file_' + reqType + '_edit');
      if (input) input.value = '';
      let rowNumber = 1;
      if (row && row.parentElement) {
        const rows = row.parentElement.querySelectorAll('.edit-doc-row');
        rows.forEach(function (r, i) {
          if (r.getAttribute('data-req-type') === reqType) rowNumber = i + 1;
        });
      }
      setEditDocRowPending(reqType, rowNumber);
    }
  }
  
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Handle form submission
  document.getElementById('editApplicationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Create FormData manually to avoid duplicates
    const formData = new FormData();
    const form = this;
    
    // Add required form fields
    const vendorIdField = form.querySelector('#edit_vendor_id');
    const appTypeField = form.querySelector('#edit_application_type');
    const fullNameField = form.querySelector('#edit_full_name');
    const sectionHidden = form.querySelector('#edit_section_hidden');
    const blockHidden = form.querySelector('#edit_block_hidden');
    const actionField = form.querySelector('input[name="action"]');
    
    if (vendorIdField && vendorIdField.value) {
      formData.append('vendor_id', vendorIdField.value);
    }
    if (appTypeField && appTypeField.value) {
      formData.append('application_type', appTypeField.value);
    }
    if (fullNameField && fullNameField.value) {
      formData.append('full_name', fullNameField.value.trim());
    }
    if (sectionHidden && sectionHidden.value) {
      formData.append('section', sectionHidden.value);
    } else {
      // Fallback to disabled select value
      const sectionSelect = form.querySelector('#edit_section');
      if (sectionSelect && sectionSelect.value) {
        formData.append('section', sectionSelect.value);
      }
    }
    if (blockHidden && blockHidden.value) {
      formData.append('block', blockHidden.value);
    } else {
      // Fallback to disabled select value
      const blockSelect = form.querySelector('#edit_block');
      if (blockSelect && blockSelect.value) {
        formData.append('block', blockSelect.value);
      }
    }
    if (actionField && actionField.value) {
      formData.append('action', actionField.value);
    }
    
    // Collect files from file inputs - ONLY ONCE
    const fileInputs = form.querySelectorAll('input[type="file"][name="documents[]"]');
    let fileCount = 0;
    
    fileInputs.forEach(input => {
      if (input.files && input.files.length > 0) {
        for (let i = 0; i < input.files.length; i++) {
          const file = input.files[i];
          if (file && file.size > 0) {
            fileCount++;
            console.log('Adding file to FormData:', file.name, 'Size:', file.size, 'Type:', file.type);
            // Add each file only once
            formData.append('documents[]', file);
          }
        }
      }
    });
    
    console.log('Total files to upload:', fileCount);
    
    // Add files to delete array to FormData
    if (filesToDelete && filesToDelete.length > 0) {
      filesToDelete.forEach((docId) => {
        formData.append('delete_documents[]', docId);
      });
      console.log('Files marked for deletion:', filesToDelete.length);
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    fetch('notification.php', {
      method: 'POST',
      body: formData,
      // Don't set Content-Type header - browser will set it with boundary for multipart/form-data
    })
    .then(response => response.text().then(text => parseNotificationPhpJson(response, text)))
    .then(data => {
      console.log('Response received:', data);
      
      if (data.success) {
        // Reset files to delete array
        filesToDelete = [];
        
        // Show detailed success message
        let successMsg = data.message || 'Application updated successfully!';
        if (data.files_uploaded > 0) {
          successMsg += '\n\n' + data.files_uploaded + ' file(s) were successfully uploaded';
        }
        if (data.files_deleted > 0) {
          successMsg += '\n\n' + data.files_deleted + ' file(s) were removed.';
        }
        
        alert(successMsg);
        closeEditModal();
        location.reload(); // Reload page to show updated data
      } else {
        alert('Error: ' + (data.message || 'Failed to update application'));
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Network error: ' + error.message + '\n\nPlease check your internet connection and try again.');
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
  });

  const editReceiptForm = document.getElementById('editReceiptForm');
  if (editReceiptForm) {
    editReceiptForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const saveBtn = document.getElementById('editReceiptSaveBtn');
      const originalText = saveBtn ? saveBtn.innerHTML : '';
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      }

      const formData = new FormData(this);
      fetch('notification.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(response => response.text().then(text => parseNotificationPhpJson(response, text)))
      .then(data => {
        if (data.success) {
          alert(data.message || 'Receipt updated successfully.');
          closeEditReceiptModal();
          location.reload();
        } else {
          alert('Error: ' + (data.message || 'Failed to update receipt'));
          if (saveBtn) {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
          }
        }
      })
      .catch(function (error) {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
        if (saveBtn) {
          saveBtn.innerHTML = originalText;
          saveBtn.disabled = false;
        }
      });
    });
  }
  
  // Close modal when clicking outside
  window.onclick = function(event) {
    const modal = document.getElementById('editApplicationModal');
    if (event.target === modal) {
      closeEditModal();
    }
    const receiptModal = document.getElementById('editReceiptModal');
    if (event.target === receiptModal) {
      closeEditReceiptModal();
    }
  };

  window.handleNotifEyeClick = handleNotifEyeClick;
  window.openViewRequirementsModal = openViewRequirementsModal;
  window.openReceiptViewModal = openReceiptViewModal;
  window.openEditReceiptModal = openEditReceiptModal;
  window.closeEditReceiptModal = closeEditReceiptModal;
  window.closeViewRequirementsModal = closeViewRequirementsModal;
  window.previewFile = previewFile;
  window.closeFilePreviewModal = closeFilePreviewModal;
  </script>
  <?php
  $cs_contact_href = 'index.php#contact';
  $cs_announcement_href = 'index.php#announcement';
  $cs_show_menu_tools = true;
  include __DIR__ . '/includes/mobile_enhancements.php';
  include __DIR__ . '/includes/customer_support_widget.php';
  ?>
</body>
</html>
