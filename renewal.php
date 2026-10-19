<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once 'includes/vendor_resubmit_helpers.php';
require_once 'includes/stall_slot_map.php';

requireLogin();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['full_name'] ?? '';
$user_email = $_SESSION['email'] ?? '';

$success_message = '';
$error_message = '';

// Show any flash message left by a Post-Redirect-Get submission, then clear it so it
// doesn't reappear on refresh.
if (!empty($_SESSION['renewal_flash_success'])) {
    $success_message = $_SESSION['renewal_flash_success'];
    unset($_SESSION['renewal_flash_success']);
}
if (!empty($_SESSION['renewal_flash_error'])) {
    $error_message = $_SESSION['renewal_flash_error'];
    unset($_SESSION['renewal_flash_error']);
}

// Debug: Check if form was just submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    error_log("DEBUG: Form submission detected in renewal.php");
}

$editing = false;
$editing_vendor = null;
$editing_vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($editing_vendor_id > 0) {
    $conn = getDBConnection();
    if ($conn) {
        try {
            dpmEnsureStallColumns($conn);
            $stmt = $conn->prepare("SELECT vendor_id, vendor_name, section, block, status, stall_number FROM vendors WHERE vendor_id = ? AND user_id = ? AND application_type = 'renewal'");
            if ($stmt) {
                $stmt->bind_param("ii", $editing_vendor_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $editing_vendor = $result->fetch_assoc();
                $stmt->close();

                if ($editing_vendor && $editing_vendor['status'] !== 'approved') {
                    $editing = true;
                } else {
                    $editing_vendor = null;
                    $editing_vendor_id = 0;
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching editing vendor: " . $e->getMessage());
            $editing_vendor = null;
            $editing_vendor_id = 0;
        }
        $conn->close();
    }
}

// Extract first name only for navbar display
$first_name = explode(' ', trim($user_name))[0] ?? 'User';

$unread_notifications_count = getUnreadApprovalNotificationCount($user_id);

// Get user's application section and block for renewal (from any submitted application)
$existing_section = '';
$existing_block = '';
$existing_stall = 0;
$conn = getDBConnection();
if ($conn) {
    try {
        dpmEnsureStallColumns($conn);
        // Get the most recent application (approved first, then any other status)
        $stmt = $conn->prepare("SELECT section, block, stall_number FROM vendors WHERE user_id = ? AND application_type = 'new' AND section IS NOT NULL AND section != '' AND block IS NOT NULL AND block != '' ORDER BY status = 'approved' DESC, submitted_date DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $app_data = $result->fetch_assoc();
            $existing_section = $app_data['section'] ?? '';
            $existing_block = $app_data['block'] ?? '';
            $existing_stall = (int) ($app_data['stall_number'] ?? 0);
        }
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Error fetching application data: " . $e->getMessage());
        if ($conn) $conn->close();
    }
}

// Fetch ALL of the user's approved stalls (their held applications/renewals) for the
// "My All Stalls" picker. Each approved vendor row = one stall the user currently holds.
$my_stalls = [];
$conn = getDBConnection();
if ($conn) {
    try {
        dpmEnsureStallColumns($conn);
        $stmt = $conn->prepare("SELECT vendor_id, vendor_name, section, block, stall_number, application_type, submitted_date, reviewed_date FROM vendors WHERE user_id = ? AND status = 'approved' ORDER BY submitted_date DESC, vendor_id DESC");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            // Keep only the most recent approved record per physical stall so an already-renewed
            // stall doesn't show its old application AND its renewal side by side.
            $seen_stalls = [];
            while ($row = $res->fetch_assoc()) {
                $slot = (int) ($row['stall_number'] ?? 0);
                // A stall is uniquely identified by section + block + slot number. If it has no
                // assigned slot yet, fall back to its own vendor_id so distinct records aren't merged.
                $stall_key = $slot > 0
                    ? strtolower(trim((string) ($row['section'] ?? '')) . '|' . trim((string) ($row['block'] ?? '')) . '|' . $slot)
                    : 'vendor-' . $row['vendor_id'];
                if (isset($seen_stalls[$stall_key])) {
                    // Rows are ordered newest-first, so the first one we saw is the current record.
                    continue;
                }
                $seen_stalls[$stall_key] = true;

                // Reference date = when it was approved (reviewed_date) or, as a fallback, when it was submitted.
                $ref = !empty($row['reviewed_date']) ? $row['reviewed_date'] : ($row['submitted_date'] ?? '');
                $days = 0;
                if (!empty($ref)) {
                    $ts = strtotime($ref);
                    if ($ts !== false) {
                        $days = (int) floor((time() - $ts) / 86400);
                    }
                }
                $row['days_since'] = $days;
                // 20–29 days: approaching the one-month due date. 30+ days: already due.
                $row['almost_due'] = ($days >= 20 && $days < 30);
                $row['is_overdue'] = ($days >= 30);
                $my_stalls[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching user's approved stalls: " . $e->getMessage());
    }
    $conn->close();
}

// Function to get requirements from database
function getApplicationRequirements($application_type = 'new') {
    $conn = getDBConnection();
    if (!$conn) {
        // Return default requirements if database fails
        return getDefaultRequirements($application_type);
    }
    
    try {
        // Create table if it doesn't exist
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
        
        // If no requirements found, return defaults
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

// Get requirements for display
$application_requirements = getApplicationRequirements('renewal');

// Output requirements as JSON for JavaScript
$requirements_json = json_encode($application_requirements);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // Log that form was submitted
    error_log("Renewal form submitted - POST data received");
    
    $posted_vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    
    $is_multiple = isset($_POST['full_name']) && is_array($_POST['full_name']);
    
    error_log("Renewal submission - is_multiple: " . ($is_multiple ? 'yes' : 'no') . ", vendor_id: $posted_vendor_id");
    
    // Debug: Show what POST data was received
    error_log("POST data: full_name=" . (is_array($_POST['full_name'] ?? null) ? 'array(' . count($_POST['full_name']) . ')' : ($_POST['full_name'] ?? 'not set')) . 
              ", section=" . (is_array($_POST['section'] ?? null) ? 'array(' . count($_POST['section']) . ')' : ($_POST['section'] ?? 'not set')) .
              ", block=" . (is_array($_POST['block'] ?? null) ? 'array(' . count($_POST['block']) . ')' : ($_POST['block'] ?? 'not set')));
    
    if ($is_multiple) {
        $full_names = $_POST['full_name'] ?? [];
        $sections = $_POST['section'] ?? [];
        $blocks = $_POST['block'] ?? [];
        $num_applications = count($full_names);
        
        if ($num_applications === 0) {
            $error_message = "Please fill in at least one application.";
        } else {
            $all_valid = true;
            $submitted_count = 0;
            $application_ids = [];
            
            $conn = getDBConnection();
            if (!$conn) {
                $error_message = "Database connection failed. Please try again.";
            } else {
                try {
                    dpmEnsureVendorResubmitTrigger($conn);
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
                INDEX idx_stall_number (stall_number),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
                    $colCheck = $conn->query("SHOW COLUMNS FROM application_documents LIKE 'stall_number'");
                    if ($colCheck && $colCheck->num_rows === 0) {
                        $conn->query("ALTER TABLE application_documents ADD COLUMN stall_number INT NULL AFTER vendor_id");
                    }
                    
                    $colCheck = $conn->query("SHOW COLUMNS FROM vendors LIKE 'block'");
                    if ($colCheck && $colCheck->num_rows === 0) {
                        $conn->query("ALTER TABLE vendors ADD COLUMN block VARCHAR(100) NULL AFTER section");
                    }
                    
                    $upload_dir = 'uploads/applications/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $required_docs = count($application_requirements);
                    if ($required_docs < 1) {
                        $required_docs = 7;
                    }
                    $docs_blocked = false;
                    for ($pre = 0; $pre < $num_applications; $pre++) {
                        $pre_num = $pre + 1;
                        if (dpm_count_uploaded_files('documents_' . $pre_num) < $required_docs) {
                            $error_message = "Stall $pre_num was not submitted. Attach all $required_docs of $required_docs required documents.";
                            $docs_blocked = true;
                            $all_valid = false;
                            break;
                        }
                    }

                    if (!$docs_blocked) {
                    for ($i = 0; $i < $num_applications; $i++) {
                $full_name = trim($full_names[$i] ?? '');
                $section = trim($sections[$i] ?? '');
                $block = trim($blocks[$i] ?? '');
                $stall_num = $i + 1;
                $posted_slots = $_POST['stall_number'] ?? [];
                $slot_number_val = (int) ($posted_slots[$i] ?? 0);
                $resolved_slot = dpmResolveStall($section, $block);
                $stall_block_val = $resolved_slot['db'] ?? null;
                
                if (empty($full_name) || empty($section) || empty($block)) {
                    $error_message = "Please fill in all required fields for Stall $stall_num.";
                    $all_valid = false;
                    continue;
                }
                
                $documents_key = 'documents_' . $stall_num;
                
                if (!$all_valid) continue;
                
                dpmEnsureStallColumns($conn);
                $conn->autocommit(false);
                
                try {
                    $check_stmt = $conn->prepare("
                        SELECT vendor_id, status FROM vendors 
                        WHERE user_id = ? AND application_type = 'renewal' AND section = ? AND block = ?
                        ORDER BY submitted_date DESC LIMIT 1
                    ");
                    $check_stmt->bind_param("iss", $user_id, $section, $block);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $existing_vendor = $check_result->fetch_assoc();
                    $check_stmt->close();
                    
                    $application_id = 0;

                    // Only reuse an existing renewal record if it is still pending/incomplete.
                    // If the most recent renewal is already approved (or none exists), start a
                    // fresh pending renewal cycle instead of blocking the vendor.
                    $can_update_existing = ($existing_vendor && ($existing_vendor['status'] ?? '') !== 'approved');

                    if ($can_update_existing) {
                        $existing_id = $existing_vendor['vendor_id'];
                        $stmt = $conn->prepare("
                            UPDATE vendors
                            SET vendor_name = ?, section = ?, block = ?, stall_number = ?, stall_block = ?, status = 'pending', reviewed_date = NULL, reviewed_by = NULL,
                                admin_notes = NULL, review_status = 'pending', is_reviewed = 0, submitted_date = NOW()
                            WHERE vendor_id = ? AND user_id = ? AND application_type = 'renewal'
                            AND status <> 'approved'
                        ");
                        $stmt->bind_param("sssisii", $full_name, $section, $block, $slot_number_val, $stall_block_val, $existing_id, $user_id);
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            $application_id = $existing_id;
                            dpmLogVendorResubmit($conn, $existing_id, $user_id, $existing_vendor['status'] ?? 'incomplete', 'renewal.php');
                        }
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO vendors (user_id, vendor_name, section, block, stall_number, stall_block, application_type, status, submitted_date) VALUES (?, ?, ?, ?, ?, ?, 'renewal', 'pending', NOW())");
                        if (!$stmt) {
                            error_log("ERROR: Failed to prepare INSERT statement for multiple renewal - " . $conn->error);
                            throw new Exception("Failed to prepare INSERT statement: " . $conn->error);
                        }
                        $stmt->bind_param("isssis", $user_id, $full_name, $section, $block, $slot_number_val, $stall_block_val);
                        if ($stmt->execute()) {
                            $application_id = $stmt->insert_id;
                            error_log("SUCCESS: Multiple renewal created - vendor_id=$application_id, user_id=$user_id, stall_num=$stall_num, name='$full_name', section='$section', block='$block'");
                        } else {
                            error_log("ERROR: Failed to create multiple renewal - error=" . $stmt->error);
                            throw new Exception("Failed to create renewal: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                    
                    if ($application_id > 0) {
                        $file_count = count($_FILES[$documents_key]['name']);
                        for ($j = 0; $j < $file_count; $j++) {
                            if ($_FILES[$documents_key]['error'][$j] === UPLOAD_ERR_OK) {
                                $file_name = $_FILES[$documents_key]['name'][$j];
                                $file_tmp = $_FILES[$documents_key]['tmp_name'][$j];
                                $file_size = $_FILES[$documents_key]['size'][$j];
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                
                                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                                if (!in_array($file_ext, $allowed_extensions)) {
                                    throw new Exception("Invalid file type for Stall $stall_num: $file_name");
                                }
                                
                                if ($file_size > 5 * 1024 * 1024) {
                                    throw new Exception("File size too large for Stall $stall_num: $file_name");
                                }
                                
                                $new_filename = $application_id . '_' . time() . '_' . $i . '_' . $j . '.' . $file_ext;
                                $file_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($file_tmp, $file_path)) {
                                    $doc_stmt = $conn->prepare("INSERT INTO application_documents (application_id, vendor_id, stall_number, original_name, file_name, file_path, file_type, file_size, mime_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                                    $mime_type = $_FILES[$documents_key]['type'][$j] ?: 'application/octet-stream';
                                    $doc_stmt->bind_param("iiissssis", $application_id, $application_id, $slot_number_val, $file_name, $new_filename, $file_path, $file_ext, $file_size, $mime_type);
                                    $doc_stmt->execute();
                                    $doc_stmt->close();
                                }
                            }
                        }
                        
                        $conn->commit();
                        $conn->autocommit(true);
                        $application_ids[] = $application_id;
                        $submitted_count++;
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $error_message = "Error processing Stall $stall_num: " . $e->getMessage();
                }
            }
                    }

                    // Create notifications for all submitted renewals (for both user and admin visibility)
                    if ($submitted_count > 0) {
                        try {
                            // Ensure table exists
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
                            
                            // Create notification for each submitted renewal (visible in notification.php)
                            foreach ($application_ids as $app_id) {
                                // Get vendor name for this application
                                $vendor_stmt = $conn->prepare("SELECT vendor_name, section, block FROM vendors WHERE vendor_id = ?");
                                $vendor_stmt->bind_param("i", $app_id);
                                $vendor_stmt->execute();
                                $vendor_result = $vendor_stmt->get_result();
                                $vendor_data = $vendor_result->fetch_assoc();
                                $vendor_stmt->close();
                                
                                $vendor_name = $vendor_data['vendor_name'] ?? 'Your renewal';
                                $section = $vendor_data['section'] ?? '';
                                $block = $vendor_data['block'] ?? '';
                                
                                $notification_title = "Renewal Submitted";
                                $loc = trim($section) !== '' && trim($block) !== ''
                                    ? trim($section) . ', Block ' . trim($block)
                                    : (trim($section) !== '' ? trim($section) : (trim($block) !== '' ? 'Block ' . trim($block) : $vendor_name));
                                $notification_message = "Your renewal application for {$loc} has been submitted and is now pending review. Please wait for the approval.";
                                
                                $notif_stmt = $conn->prepare("
                                    INSERT INTO approval_notifications 
                                    (user_id, vendor_id, notification_type, title, message, priority, action_required, action_url) 
                                    VALUES (?, ?, 'pending_review', ?, ?, 'medium', 0, 'renewal.php')
                                ");
                                if ($notif_stmt) {
                                    $notif_stmt->bind_param("iiss", $user_id, $app_id, $notification_title, $notification_message);
                                    $notif_stmt->execute();
                                    $notif_stmt->close();
                                }
                            }
                            
                            error_log("SUCCESS: Created $submitted_count notification(s) for renewal submissions - visible in notification.php and adminpanel.php");
                        } catch (Exception $e) {
                            error_log("EXCEPTION: Failed to create renewal notifications - " . $e->getMessage());
                        }
                    }
                    
                    $conn->close();
                    
                    if ($submitted_count > 0) {
                        $ids_text = implode(', #', $application_ids);
                        $success_message = "Successfully submitted $submitted_count application(s)! Application ID(s): #$ids_text";
                        require_once __DIR__ . '/includes/submission_notify_email.php';
                        $who = dpm_registered_recipient((int) $user_id);
                        $mailTo = $who['email'] !== '' ? $who['email'] : trim((string) $user_email);
                        $mailName = $who['name'] !== '' ? $who['name'] : (string) $user_name;
                        if ($mailTo !== '') {
                            dpm_notify_submission_email('renewal', $mailTo, $mailName, [
                                'action' => 'submitted',
                                'application_ids' => '#' . $ids_text,
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error processing multiple applications: " . $e->getMessage());
                    $error_message = "An error occurred while processing your applications. Please try again.";
                    if ($conn) {
                        $conn->close();
                    }
                }
            }
        }

        // Post-Redirect-Get: reload the page as a GET request so refreshing after a
        // renewal submission does not re-submit the form (which previously re-triggered
        // the "already approved" error).
        $_SESSION['renewal_flash_success'] = $success_message;
        $_SESSION['renewal_flash_error'] = $error_message;
        header('Location: renewal.php');
        exit;
    } else {
        // Handle single renewal submission
        // Check if form fields are arrays (from dynamically generated form) or single values
        if (isset($_POST['full_name']) && is_array($_POST['full_name'])) {
            // Form was dynamically generated with arrays, but only one renewal
            $full_name = trim($_POST['full_name'][0] ?? '');
            $section = trim($_POST['section'][0] ?? '');
            $block = trim($_POST['block'][0] ?? '');
            $slot_number_val = (int) (is_array($_POST['stall_number'] ?? null) ? ($_POST['stall_number'][0] ?? 0) : ($_POST['stall_number'] ?? 0));
        } else {
            // Form has single values (edit form or non-dynamic form)
            $full_name = trim($_POST['full_name'] ?? '');
            $section = trim($_POST['section'] ?? '');
            $block = trim($_POST['block'] ?? '');
            $slot_number_val = (int) ($_POST['stall_number'] ?? 0);
        }
        $resolved_slot = dpmResolveStall($section, $block);
        $stall_block_val = $resolved_slot['db'] ?? null;
        
        $posted_vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
        
        error_log("Single renewal submission - full_name: '$full_name', section: '$section', block: '$block', vendor_id: $posted_vendor_id");
        
        // Validate required fields
        if (empty($full_name)) {
            $error_message = "Please fill in the Full Name field.";
            error_log("Renewal validation failed: Full name is empty");
        } elseif (empty($section)) {
            $error_message = "Please select a Section. If you don't have an existing application, please submit a new application first.";
            error_log("Renewal validation failed: Section is empty");
        } elseif (empty($block)) {
            $error_message = "Please select a Block. If you don't have an existing application, please submit a new application first.";
            error_log("Renewal validation failed: Block is empty");
        } elseif (dpm_count_uploaded_files('documents') < (count($application_requirements) > 0 ? count($application_requirements) : 7)) {
            $need_docs = count($application_requirements) > 0 ? count($application_requirements) : 7;
            $error_message = "The renewal was not submitted. Attach all $need_docs of $need_docs required documents.";
            error_log("Renewal validation failed: documents incomplete, need $need_docs");
        } else {
            error_log("Renewal validation passed - proceeding with submission. full_name='$full_name', section='$section', block='$block', vendor_id=$posted_vendor_id");
            $conn = getDBConnection();
            if (!$conn) {
                $error_message = "Database connection failed. Please try again.";
            } else {
                try {
                    $conn->query("CREATE TABLE IF NOT EXISTS application_documents (
                        document_id INT AUTO_INCREMENT PRIMARY KEY,
                        application_id INT NULL,
                        vendor_id INT NOT NULL,
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
                    
                    $colCheck = $conn->query("SHOW COLUMNS FROM vendors LIKE 'block'");
                    if ($colCheck && $colCheck->num_rows === 0) {
                        $conn->query("ALTER TABLE vendors ADD COLUMN block VARCHAR(100) NULL AFTER section");
                    }
                    
                    $upload_dir = 'uploads/applications/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $application_id = 0;
                    $was_updated = false;

                    $conn->autocommit(false);
                    
                    try {
            // STRONG BACKEND: Always check for existing application first to prevent duplicates
            // Priority: 1) Explicit vendor_id from POST (if provided), 2) Any incomplete application (highest priority), 3) Any pending application, 4) Most recent application, 5) Create new ONLY if truly no existing
            
            $existing_vendor_id = 0;
            
            // Priority 1: If explicit vendor_id provided, use it (user clicked Edit button)
            if ($posted_vendor_id > 0) {
                // Check if the explicit vendor_id exists and belongs to user
                $check_stmt = $conn->prepare("
                    SELECT vendor_id, status FROM vendors 
                    WHERE vendor_id = ? AND user_id = ? AND application_type = 'renewal'
                ");
                $check_stmt->bind_param("ii", $posted_vendor_id, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $existing_vendor_id = $posted_vendor_id;
                    $row = $check_result->fetch_assoc();
                    error_log("Found explicit vendor_id=$posted_vendor_id with status=" . $row['status']);
                } else {
                    error_log("Explicit vendor_id=$posted_vendor_id not found or doesn't belong to user_id=$user_id");
                }
                $check_stmt->close();
            }
            
            // Priority 2: Find ANY incomplete application (highest priority for editing)
            // This ensures incomplete applications are always updated, not duplicated
            if ($existing_vendor_id == 0) {
                $check_stmt = $conn->prepare("
                    SELECT vendor_id FROM vendors 
                    WHERE user_id = ? AND application_type = 'renewal' 
                    AND status = 'incomplete'
                    ORDER BY submitted_date DESC
                    LIMIT 1
                ");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $existing_vendor_id = $row['vendor_id'];
                    error_log("Found incomplete application: vendor_id=$existing_vendor_id");
                }
                $check_stmt->close();
            }
            
            // Priority 3: Find any pending application
            if ($existing_vendor_id == 0) {
                $check_stmt = $conn->prepare("
                    SELECT vendor_id FROM vendors 
                    WHERE user_id = ? AND application_type = 'renewal' 
                    AND status = 'pending'
                    ORDER BY submitted_date DESC
                    LIMIT 1
                ");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $existing_vendor_id = $row['vendor_id'];
                    error_log("Found pending application: vendor_id=$existing_vendor_id");
                }
                $check_stmt->close();
            }
            
            // Priority 4: Find any application (including approved) - last resort before creating new
            if ($existing_vendor_id == 0) {
                $check_stmt = $conn->prepare("
                    SELECT vendor_id FROM vendors 
                    WHERE user_id = ? AND application_type = 'renewal'
                    ORDER BY submitted_date DESC
                    LIMIT 1
                ");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $existing_vendor_id = $row['vendor_id'];
                    error_log("Found most recent application: vendor_id=$existing_vendor_id");
                }
                $check_stmt->close();
            }
            
            // Log final decision
            if ($existing_vendor_id > 0) {
                error_log("Will UPDATE existing application: vendor_id=$existing_vendor_id");
            } else {
                error_log("No existing application found - will CREATE new one");
            }
            
            // Update existing application or create new one
            if ($existing_vendor_id > 0) {
                // Block only approved — incomplete renewals must stay editable for resubmission
                $status_check_stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ? AND user_id = ? AND application_type = 'renewal'");
                $status_check_stmt->bind_param("ii", $existing_vendor_id, $user_id);
                $status_check_stmt->execute();
                $status_result = $status_check_stmt->get_result();
                $status_row = $status_result->fetch_assoc();
                $status_check_stmt->close();
                
                if ($status_row && ($status_row['status'] ?? '') === 'approved') {
                    error_log("BLOCKED: Attempt to update vendor_id=$existing_vendor_id with status=approved - admin approval preserved");
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    $_SESSION['error_message'] = "Cannot update application: This application has already been approved by an administrator.";
                    header("Location: renewal.php?error=already_reviewed");
                    exit;
                }
                
                dpmEnsureVendorResubmitTrigger($conn);

                // UPDATE existing renewal — pending or incomplete → reset to pending for fresh admin review
                $stmt = $conn->prepare("
                    UPDATE vendors
                    SET vendor_name = ?, section = ?, block = ?, stall_number = ?, stall_block = ?,
                        status = 'pending',
                        review_status = 'pending',
                        is_reviewed = 0,
                        reviewed_date = NULL,
                        reviewed_by = NULL,
                        admin_notes = NULL,
                        submitted_date = NOW()
                    WHERE vendor_id = ? AND user_id = ? AND application_type = 'renewal' 
                    AND status <> 'approved'
                ");
                $stmt->bind_param("sssisii", $full_name, $section, $block, $slot_number_val, $stall_block_val, $existing_vendor_id, $user_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $application_id = $existing_vendor_id;
                        $was_updated = true;
                        dpmLogVendorResubmit($conn, $existing_vendor_id, $user_id, $status_row['status'] ?? 'incomplete', 'renewal.php');
                        error_log("SUCCESS: Renewal updated - vendor_id=$application_id, user_id=$user_id, status reset to pending");
                    } else {
                        error_log("WARNING: Update executed but no rows affected - vendor_id=$existing_vendor_id may not exist or already matches");
                        // Still set application_id to allow file uploads
                        $application_id = $existing_vendor_id;
                        $was_updated = true;
                    }
                } else {
                    error_log("ERROR: Failed to execute update - vendor_id=$existing_vendor_id, error=" . $stmt->error);
                    throw new Exception("Failed to update renewal: " . $stmt->error);
                }
                $stmt->close();
            } else {
                // CREATE new renewal ONLY if truly no existing one found
                // This should rarely happen if user has ever submitted a renewal
                $stmt = $conn->prepare("INSERT INTO vendors (user_id, vendor_name, section, block, stall_number, stall_block, application_type, status, submitted_date) VALUES (?, ?, ?, ?, ?, ?, 'renewal', 'pending', NOW())");
                if (!$stmt) {
                    error_log("ERROR: Failed to prepare INSERT statement - " . $conn->error);
                    throw new Exception("Failed to prepare INSERT statement: " . $conn->error);
                }
                $stmt->bind_param("isssis", $user_id, $full_name, $section, $block, $slot_number_val, $stall_block_val);
                if ($stmt->execute()) {
                    $application_id = $stmt->insert_id;
                    $was_updated = false;
                    error_log("NEW: Renewal created successfully - vendor_id=$application_id, user_id=$user_id, full_name='$full_name', section='$section', block='$block'");
                } else {
                    error_log("ERROR: Failed to create renewal - error=" . $stmt->error . ", user_id=$user_id, full_name='$full_name'");
                    throw new Exception("Failed to create renewal: " . $stmt->error);
                }
                $stmt->close();
            }
            
            $conn->commit();
            $conn->autocommit(true);
            
        } catch (Exception $e) {
                        $conn->rollback();
                        $conn->autocommit(true);
                        error_log("Database error in renewal submission: " . $e->getMessage());
                        $error_message = "Database error: " . $e->getMessage();
                        $application_id = 0;
                    }
                    
                    // Process file uploads if application was created/updated successfully
                    $upload_success = true;
                    if ($application_id > 0) {
                        if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                            $file_count = count($_FILES['documents']['name']);
                            
                            error_log("Processing $file_count file(s) for renewal ID: $application_id");
                            
                            for ($i = 0; $i < $file_count; $i++) {
                                if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                                    $file_name = $_FILES['documents']['name'][$i];
                                    $file_tmp = $_FILES['documents']['tmp_name'][$i];
                                    $file_size = $_FILES['documents']['size'][$i];
                                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    
                                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                                    if (!in_array($file_ext, $allowed_extensions)) {
                                        $error_message = "Invalid file type. Allowed: JPG, PNG, PDF, DOC, DOCX";
                                        $upload_success = false;
                                        break;
                                    }
                                    
                                    if ($file_size > 5 * 1024 * 1024) {
                                        $error_message = "File size must be less than 5MB";
                                        $upload_success = false;
                                        break;
                                    }
                                    
                                    $new_filename = $application_id . '_' . time() . '_' . $i . '.' . $file_ext;
                                    $file_path = $upload_dir . $new_filename;
                                    
                                    if (move_uploaded_file($file_tmp, $file_path)) {
                                        $colCheck = $conn->query("SHOW COLUMNS FROM application_documents LIKE 'stall_number'");
                                        if ($colCheck && $colCheck->num_rows === 0) {
                                            $conn->query("ALTER TABLE application_documents ADD COLUMN stall_number INT NULL AFTER vendor_id");
                                        }
                                        
                                        $doc_stmt = $conn->prepare("INSERT INTO application_documents (application_id, vendor_id, stall_number, original_name, file_name, file_path, file_type, file_size, mime_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                                        $mime_type = $_FILES['documents']['type'][$i] ?: 'application/octet-stream';
                                        $doc_stmt->bind_param("iiissssis", $application_id, $application_id, $slot_number_val, $file_name, $new_filename, $file_path, $file_ext, $file_size, $mime_type);
                                        $doc_stmt->execute();
                                        $doc_stmt->close();
                                    } else {
                                        $error_message = "Failed to upload file: " . $file_name;
                                        $upload_success = false;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Create notification for renewal submission (visible in notification.php and adminpanel.php)
                        try {
                            // Ensure table exists
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
                            
                            $loc = trim($section) !== '' && trim($block) !== ''
                                ? trim($section) . ', Block ' . trim($block)
                                : (trim($section) !== '' ? trim($section) : (trim($block) !== '' ? 'Block ' . trim($block) : $full_name));
                            if ($was_updated) {
                                $notification_title = "Renewal Updated";
                                $notification_message = "Your renewal application for {$loc} has been updated and is now pending review. Please wait for the approval.";
                            } else {
                                $notification_title = "Renewal Submitted";
                                $notification_message = "Your renewal application for {$loc} has been submitted and is now pending review. Please wait for the approval.";
                            }
                            
                            $notif_stmt = $conn->prepare("
                                INSERT INTO approval_notifications 
                                (user_id, vendor_id, notification_type, title, message, priority, action_required, action_url) 
                                VALUES (?, ?, 'pending_review', ?, ?, 'medium', 0, 'renewal.php')
                            ");
                            if ($notif_stmt) {
                                $notif_stmt->bind_param("iiss", $user_id, $application_id, $notification_title, $notification_message);
                                if ($notif_stmt->execute()) {
                                    $notification_id = $conn->insert_id;
                                    error_log("SUCCESS: Notification created for renewal " . ($was_updated ? "update" : "submission") . " - notification_id=$notification_id, vendor_id=$application_id, user_id=$user_id (visible in notification.php and adminpanel.php)");
                                } else {
                                    error_log("ERROR: Failed to execute notification insert - " . $notif_stmt->error);
                                }
                                $notif_stmt->close();
                            } else {
                                error_log("ERROR: Failed to prepare notification statement - " . $conn->error);
                            }
                        } catch (Exception $e) {
                            error_log("EXCEPTION: Failed to create renewal notification - " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                        }
                        
                        // Set success message - renewal was successfully created/updated
                        if ($was_updated) {
                            $success_message = "Renewal updated successfully! Application ID: #" . $application_id;
                            if (!$upload_success && isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                                $success_message .= " Note: Some files may not have been uploaded. Please check your documents.";
                            }
                        } else {
                            $success_message = "Renewal submitted successfully! Application ID: #" . $application_id;
                            if (!$upload_success && isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                                $success_message .= " Note: Some files may not have been uploaded. Please check your documents.";
                            }
                        }
                        
                        error_log("SUCCESS: Renewal submitted - application_id=$application_id, success_message set");
                        
                        // Verify renewal was saved to database
                        $verify_stmt = $conn->prepare("SELECT vendor_id, vendor_name, section, block, status, application_type FROM vendors WHERE vendor_id = ?");
                        $verify_stmt->bind_param("i", $application_id);
                        $verify_stmt->execute();
                        $verify_result = $verify_stmt->get_result();
                        if ($verify_row = $verify_result->fetch_assoc()) {
                            error_log("VERIFIED: Renewal exists in database - vendor_id=" . $verify_row['vendor_id'] . ", status=" . $verify_row['status'] . ", application_type=" . $verify_row['application_type'] . ", section=" . $verify_row['section'] . ", block=" . $verify_row['block']);
                        } else {
                            error_log("ERROR: Renewal NOT found in database after insert! application_id=$application_id");
                            $error_message = "Renewal was not saved properly. Please try again.";
                            $success_message = ''; // Clear success message if verification failed
                        }
                        $verify_stmt->close();
                        
                        require_once __DIR__ . '/includes/submission_notify_email.php';
                        $who = dpm_registered_recipient((int) $user_id);
                        $mailTo = $who['email'] !== '' ? $who['email'] : trim((string) $user_email);
                        $mailName = $who['name'] !== '' ? $who['name'] : (string) $user_name;
                        if (!empty($success_message) && $mailTo !== '') {
                            dpm_notify_submission_email('renewal', $mailTo, $mailName, [
                                'action' => $was_updated ? 'updated' : 'submitted',
                                'application_ids' => '#' . $application_id,
                            ]);
                        }
                        
                        // Log activity
                        try {
                            $activity_type = $was_updated ? 'renewal_updated' : 'renewal_submitted';
                            $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address) VALUES (?, ?, ?, ?)");
                            $details = $was_updated ? "Updated renewal" : "You submitted new renewal";
                            $details .= ": $full_name - $section";
                            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                            $log_stmt->bind_param("isss", $user_id, $activity_type, $details, $ip_address);
                            $log_stmt->execute();
                            $log_stmt->close();
                        } catch (Exception $e) {
                            error_log("Failed to log activity: " . $e->getMessage());
                        }
                    } else {
                        $error_message = "Failed to submit renewal. Application ID is 0. Please check the logs for details.";
                        error_log("ERROR: Renewal submission failed - application_id is 0");
                    }
                    
                    // Close connection after processing
                    if ($conn) {
                        $conn->close();
                    }
                } catch (Exception $e) {
                    error_log("Error processing single renewal: " . $e->getMessage());
                    $error_message = "An error occurred while processing your renewal. Please try again.";
                    if ($conn) {
                        $conn->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $editing ? 'Edit Renewal - Daet Public Market' : 'Renewal - Daet Public Market'; ?></title>
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

    .main-container {
      width: 100%;
      margin: 0;
      padding: 1.5rem;
      padding-top: calc(80px + 1.5rem);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: transparent;
      box-sizing: border-box;
    }

    .navbar-overlay {
      display: none;
    }

    .navbar {
      background: rgba(102, 126, 234, 0.95);
      backdrop-filter: blur(10px);
      padding: 1rem 2rem;
      margin-bottom: 0;
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

    .navbar-left { display: flex; align-items: center; gap: 1.5rem; }
    .navbar-logo { display: flex; flex-direction: row; align-items: center; gap: 0.65rem; }
    .navbar-logo .brand__stack { display: flex; flex-direction: column; gap: 0.1rem; }
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
      width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; overflow: hidden;
      border: 2px solid rgba(255, 255, 255, 0.85); box-sizing: border-box;
    }
    .navbar-logo .brand__mark img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .nav-pills-wrap {
      display: flex; align-items: center; justify-content: center; position: fixed; top: 0; left: 50%;
      transform: translateX(-50%); height: 80px; width: auto; z-index: 1001; pointer-events: none;
    }
    .nav-pills-wrap .nav-pills-container { pointer-events: auto; }
    .navbar-center { display: none; }
    .navbar-right-desktop { display: flex; align-items: center; gap: 1rem; margin-left: auto; }
    .navbar-right-desktop .calendar-icon { margin-right: 1.5rem; }
    .navbar-right-desktop .language-selector { margin-right: 1.5rem; margin-left: -1rem; }
    .navbar-right-desktop .logout-btn { margin-left: 1.5rem; }

    .nav-pills-container {
      background: rgba(102, 126, 234, 0.3); backdrop-filter: blur(10px); border-radius: 50px; padding: 0.3rem;
      display: flex; gap: 0.25rem; align-items: center; border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .nav-pill, .nav-icon-pill {
      padding: 0.6rem 1.5rem; border-radius: 50px; color: rgba(255, 255, 255, 0.9); text-decoration: none; font-weight: 500;
      font-size: 0.95rem; transition: transform 0.25s ease, color 0.25s ease, background 0.25s ease; background: transparent; border: none;
      display: flex; align-items: center; gap: 0.5rem; transform: scale(1); transform-origin: center center; will-change: transform;
    }
    .nav-icon-pill { padding: 0.6rem 1rem; position: relative; }
    .nav-pill.active, .nav-icon-pill.active {
      background: rgba(255, 255, 255, 0.2); color: #ffffff; backdrop-filter: blur(10px);
    }
    .nav-pill:hover, .nav-icon-pill:hover {
      color: #ffffff; background: rgba(255, 255, 255, 0.1); transform: scale(1.1);
    }
    .nav-pill.active:hover, .nav-icon-pill.active:hover {
      background: rgba(255, 255, 255, 0.2); transform: scale(1.1);
    }

    .navbar-menu-controls { display: none; }
    .navbar-right { display: none; }

    .logout-btn {
      padding: 0.65rem 1.5rem; background: #ff6b35; color: #ffffff; border: none; border-radius: 8px; font-weight: 600;
      font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
      box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
    }
    .logout-btn:hover { background: #ff8555; transform: translateY(-2px) scale(1.03); }

    .calendar-icon {
      display: flex; align-items: center; justify-content: center; width: 40px; height: 40px;
      text-decoration: none; transition: all 0.3s ease; position: relative; padding: 0; cursor: pointer; border: none;
    }
    .calendar-icon-img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .calendar-icon:hover { transform: translateY(-2px); }

    .language-selector {
      display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 30%;
      position: relative; cursor: pointer; overflow: hidden;
    }
    .language-selector select { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; z-index: 10; }
    .language-selector i { font-size: 1.1rem; z-index: 1; }
    .ph-flag-icon, .uk-flag-icon {
      position: absolute; width: 100%; height: 100%; object-fit: cover; border-radius: 30%; top: 0; left: 0; z-index: 0; display: none;
    }
    .language-selector.ph-selected .ph-flag-icon { display: block; }
    .language-selector.ph-selected .uk-flag-icon { display: none; }
    .language-selector.en-selected .uk-flag-icon { display: block; }
    .language-selector.en-selected .ph-flag-icon { display: none; }
    .language-selector.ph-selected i, .language-selector.en-selected i { display: none; }

    .nav-icon-badge {
      position: absolute; right: 0; top: 2px; transform: translate(35%, -35%);
      min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background-color: #ef4444; color: #fff;
      font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
    }

    .navbar-toggler {
      display: none; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 10px; padding: 0.5rem 0.75rem;
      background: rgba(255, 255, 255, 0.1); cursor: pointer;
    }
    .navbar-toggler-icon { width: 24px; height: 18px; position: relative; display: flex; flex-direction: column; justify-content: space-between; }
    .navbar-toggler-icon::before, .navbar-toggler-icon::after, .navbar-toggler-icon span {
      content: ''; display: block; height: 3px; width: 100%; background: white; border-radius: 3px; position: absolute;
    }
    .navbar-toggler-icon::before { top: 0; }
    .navbar-toggler-icon span { top: 50%; transform: translateY(-50%); }
    .navbar-toggler-icon::after { bottom: 0; }

    @media (max-width: 991px) {
      .navbar { padding: 0.75rem 1rem; }
      .navbar-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100vh;
        background: rgba(0, 0, 0, 0.5); z-index: 998; backdrop-filter: blur(2px);
      }
      .navbar-overlay.show { display: block; }
      .navbar-toggler { display: block; order: 2; margin-left: auto; z-index: 1000; }
      .navbar-content { flex-wrap: wrap; gap: 0.75rem; }
      .navbar-left { order: 1; flex: 1; }
      .nav-pills-wrap {
        top: auto; bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); height: auto;
      }
      .navbar-center {
        display: none; position: fixed; top: 0; right: 0; width: min(300px, 86vw); height: 100vh;
        background: rgba(102, 126, 234, 0.98); backdrop-filter: blur(20px); flex-direction: column;
        padding: 1.25rem; padding-top: 88px; padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
        z-index: 999; transform: translateX(100%); transition: transform 0.3s ease; overflow-y: auto;
        align-items: stretch; gap: 0; margin: 0; border-left: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
      }
      .navbar-center.show { display: flex; transform: translateX(0); }
      .navbar-menu-controls { display: flex; width: 100%; justify-content: flex-start; align-items: center; gap: 1rem; padding: 1rem 0 0.35rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2); border-bottom: none; }
      .navbar-right-desktop { display: none; }
      .navbar-right { width: 100%; display: flex; justify-content: flex-start; margin-top: 0; padding: 0.65rem 0 1.25rem; border-top: none; }
      .logout-btn { width: 100%; justify-content: center; }
      .main-container { padding: 1rem; padding-top: calc(80px + 1rem); padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px)); }
    }

    main {
      width: 100%;
      max-width: 880px;
      margin: 0 auto;
      flex: 1;
      color: #1e293b;
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
      margin-bottom: 1.75rem;
      flex-wrap: wrap;
    }
    .accounts-header-text {
      flex: 1;
      min-width: 0;
    }
    .accounts-header-text h1,
    .accounts-title {
      color: #ffffff;
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(2.35rem, 5vw, 3.1rem);
      font-weight: 400;
      letter-spacing: -0.02em;
      margin-bottom: 0;
      display: block;
      line-height: 1.05;
      text-shadow: none;
    }
    .accounts-title .app-title-accent {
      color: #ff6b35;
      font-style: italic;
      font-family: 'DM Serif Display', Georgia, serif;
    }
    .accounts-subtitle {
      margin: 0.85rem 0 0;
      color: rgba(255, 255, 255, 0.78);
      font-size: 0.98rem;
      line-height: 1.6;
      max-width: 36rem;
    }

    .app-upload-docs {
      margin-top: 0.35rem;
    }
    .app-card-head {
      display: flex;
      align-items: flex-start;
      gap: 0.9rem;
      margin-bottom: 1.05rem;
    }
    .app-upload-docs-head {
      margin-bottom: 1.05rem;
    }
    .app-card-icon {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: rgba(255, 107, 53, 0.18);
      color: #ff6b35;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      font-size: 1rem;
    }
    .app-upload-docs-title,
    .app-upload-docs-head h3 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 700;
      line-height: 1.25;
      color: rgba(255, 255, 255, 0.95);
      border: none;
      padding: 0;
    }
    .app-upload-docs-head .required {
      color: #ff6b35;
      font-weight: 700;
    }
    .app-card-sub {
      margin: 0.28rem 0 0;
      font-size: 0.88rem;
      line-height: 1.45;
      color: rgba(255, 255, 255, 0.65);
    }
    .app-upload-docs .requirements-callout {
      margin-bottom: 0;
    }
    .req-row-copy {
      flex: 1;
      min-width: 0;
      display: flex;
      align-items: flex-start;
      gap: 0.7rem;
    }
    .req-row-copy::before {
      content: '';
      width: 7px;
      height: 7px;
      margin-top: 0.45rem;
      border-radius: 50%;
      background: #64748b;
      flex-shrink: 0;
    }
    .req-row-title {
      display: block;
      color: rgba(255, 255, 255, 0.95);
      font-weight: 700;
      font-size: 0.95rem;
      line-height: 1.35;
    }
    .req-row-desc {
      display: block;
      color: rgba(255, 255, 255, 0.62);
      font-size: 0.8rem;
      line-height: 1.4;
      margin-top: 0.18rem;
    }
    body:not(.darkmode).page-renewal .form-box .app-upload-docs-title,
    body:not(.darkmode).page-renewal .form-box .app-upload-docs-head h3 {
      color: #1c1c1e;
    }
    body:not(.darkmode).page-renewal .form-box .app-card-sub {
      color: #3a3a3c;
    }
    body:not(.darkmode).page-renewal .form-box .req-row-title {
      color: #1c1c1e;
    }
    body:not(.darkmode).page-renewal .form-box .req-row-desc {
      color: #3a3a3c;
    }

    .alert {
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .form-box {
      position: relative;
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      padding: 2.25rem;
      border-radius: 24px;
      border: 1px solid rgba(255, 255, 255, 0.35);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      animation: formBoxIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
      overflow: hidden;
      transition: box-shadow 0.3s ease;
    }
    .form-box:hover {
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }
    .form-box::before {
      display: none;
    }
    .form-box > * {
      position: relative;
      z-index: 1;
    }
    @keyframes formBoxIn {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }

    label {
      display: block;
      margin-bottom: 0.55rem;
      color: #667eea;
      font-weight: 600;
      font-size: 0.92rem;
      letter-spacing: 0.01em;
    }

    .required {
      color: #ef4444;
    }

    input[type="text"],
    select {
      width: 100%;
      padding: 0.85rem 1rem;
      margin-bottom: 1.4rem;
      border: 2px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      font-size: 0.98rem;
      color: #1e293b;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
      background: linear-gradient(180deg, #ffffff, #f8f9ff);
      font-family: inherit;
    }

    input[type="text"]::placeholder { color: #94a3b8; }

    input[type="text"]:hover,
    select:hover {
      border-color: rgba(255, 255, 255, 0.45);
    }

    input[type="text"]:focus,
    select:focus {
      outline: none;
      border-color: #667eea;
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), 0 4px 12px rgba(102, 126, 234, 0.1);
    }

    select {
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      padding-right: 2.6rem;
    }

    .file-upload-wrapper {
      position: relative;
      margin-bottom: 1.5rem;
    }

    .file-upload-label {
      display: block;
      padding: 2rem;
      border: 2px dashed rgba(255, 255, 255, 0.35);
      border-radius: 12px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      color: rgba(255, 255, 255, 0.92);
    }

    .file-upload-label:hover {
      background: rgba(255, 255, 255, 0.25);
      border-color: rgba(255, 255, 255, 0.5);
    }

    .file-upload-label i {
      font-size: 3rem;
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 1rem;
    }

    input[type="file"] {
      display: none;
    }

    .file-list {
      margin-top: 1rem;
      padding: 1.1rem;
      background: rgba(32, 42, 88, 0.52);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 14px;
    }

    .file-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.8rem 1rem;
      margin-bottom: 0.6rem;
      background: rgba(26, 36, 78, 0.68);
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      font-size: 0.9rem;
      color: #f1f5f9;
      animation: fileItemIn 0.25s ease both;
    }
    .file-item:last-child { margin-bottom: 0; }
    @keyframes fileItemIn {
      from { opacity: 0; transform: translateX(-6px); }
      to { opacity: 1; transform: translateX(0); }
    }

    .file-item i {
      color: rgba(255, 255, 255, 0.95);
      margin-right: 0.5rem;
    }

    .form-btn {
      width: 100%;
      padding: 1rem;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .form-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .form-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .stall-row {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }
    .label-stall,
    .renewal-stall-heading {
      font-size: 1.05rem;
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 1rem;
    }
    .stall-field,
    select.stall-field,
    input.stall-field[type="number"] {
      flex: 1;
      min-width: 200px;
      margin-bottom: 0;
      padding: 0.85rem 1rem;
      border: 2px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      font-size: 0.98rem;
      background: linear-gradient(180deg, #ffffff, #f8f9ff);
      color: #000000;
    }
    select.stall-field,
    #num_stalls {
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
      padding-right: 2.6rem;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23000000' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"),
        linear-gradient(180deg, #ffffff, #f8f9ff);
      background-repeat: no-repeat, no-repeat;
      background-position: right 1rem center, center;
    }
    select.stall-field option,
    #num_stalls option {
      color: #000000;
    }
    .stall-hint,
    .renewal-stall-hint {
      margin-top: 0.85rem;
      margin-bottom: 0;
      color: rgba(255, 255, 255, 0.85);
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .stall-hint i,
    .renewal-stall-hint i { color: rgba(255, 255, 255, 0.95); }

    .requirements-callout,
    .renewal-requirements-box {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-left: 4px solid rgba(255, 255, 255, 0.45);
      padding: 1.1rem 1.25rem;
      margin-bottom: 1.4rem;
      border-radius: 12px;
    }
    .requirements-callout-title,
    .renewal-requirements-box .renewal-req-title {
      margin: 0 0 0.85rem 0;
      color: #000000;
      font-size: 0.95rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.45rem;
    }
    .requirements-callout-title i,
    .renewal-requirements-box .renewal-req-title i { color: #000000; }
    .requirements-callout-title strong,
    .renewal-requirements-box .renewal-req-title strong { color: #000000; }
    .requirements-callout ul,
    .renewal-requirements-box ul {
      margin: 0;
      padding-left: 0;
      list-style: none;
      color: #000000;
      font-size: 0.92rem;
    }
    .req-row,
    .renewal-req-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.7rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.35);
      gap: 0.75rem;
      position: relative;
      list-style: none;
      margin-left: 0;
    }
    .req-row:last-child,
    .renewal-req-row:last-child { border-bottom: none; padding-bottom: 0; }
    .req-row.is-incomplete,
    .renewal-req-row.is-incomplete,
    .requirements-callout li.is-incomplete,
    .req-row:last-child.is-incomplete,
    .renewal-req-row:last-child.is-incomplete,
    body:not(.darkmode) .requirements-callout li.is-incomplete,
    body.darkmode .requirements-callout li.is-incomplete {
      background: #fee2e2 !important;
      box-shadow: inset 0 0 0 2px #ef4444 !important;
      border-radius: 12px !important;
      padding: 0.85rem 0.7rem !important;
      margin: 0.35rem 0 !important;
    }
    body.darkmode .req-row.is-incomplete,
    body.darkmode .requirements-callout li.is-incomplete,
    body.darkmode .req-row:last-child.is-incomplete {
      background: rgba(239, 68, 68, 0.42) !important;
      box-shadow: inset 0 0 0 2px #fca5a5 !important;
    }
    .req-row:first-child,
    .renewal-req-row:first-child { padding-top: 0; }
    .req-row > span,
    .renewal-req-name {
      flex: 1;
      min-width: 0;
      line-height: 1.45;
      padding-right: 0.5rem;
      color: #000000;
    }
    .req-row-actions,
    .renewal-req-actions {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      flex-shrink: 0;
    }
    .req-check,
    .renewal-req-check {
      display: none;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      min-width: 24px;
      min-height: 24px;
      background: #dcfce7;
      color: #16a34a;
      border-radius: 50%;
      font-size: 0.7rem;
      font-weight: bold;
      flex-shrink: 0;
      line-height: 1;
      padding: 0;
      box-sizing: border-box;
    }
    .req-check i,
    .renewal-req-check i {
      display: block;
      line-height: 1;
      font-size: 0.65rem;
    }
    .btn-add-file,
    .renewal-req-add-btn {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      color: #000000;
      border: 1px solid rgba(255, 255, 255, 0.35);
      padding: 0.45rem 0.9rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.2s ease;
      white-space: nowrap;
      z-index: 1;
      position: relative;
    }
    .btn-add-file:hover,
    .renewal-req-add-btn:hover {
      background: rgba(255, 255, 255, 0.35);
      color: #000000;
      border-color: rgba(255, 255, 255, 0.5);
    }
    .stall-section-heading,
    .renewal-stall-heading {
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 1.6rem;
      padding-bottom: 0.9rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.25);
      font-size: 1.3rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .stall-section-heading i,
    .stall-section-heading .me-2,
    .renewal-stall-heading i { color: rgba(255, 255, 255, 0.95); }
    .file-list-heading,
    .renewal-file-list-heading {
      margin-bottom: 1rem;
      color: rgba(255, 255, 255, 0.92);
      font-weight: 700;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 0.45rem;
    }
    .file-list-heading i,
    .renewal-file-list-heading i { color: rgba(255, 255, 255, 0.95); }
    .renewal-select-disabled:disabled {
      background-color: #f5f5f5;
      color: #64748b;
      cursor: not-allowed;
    }
    .renewal-prev-uploads {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-left: 4px solid rgba(34, 197, 94, 0.65);
      padding: 0.75rem 1rem;
      margin-top: 1rem;
      margin-bottom: 1rem;
      border-radius: 12px;
    }
    .renewal-prev-uploads p {
      margin: 0 0 0.5rem 0;
      color: rgba(255, 255, 255, 0.95);
      font-size: 0.95rem;
      font-weight: 700;
    }
    .renewal-prev-uploads ul {
      margin: 0;
      padding-left: 1.5rem;
      color: rgba(255, 255, 255, 0.92);
      font-size: 0.9rem;
      line-height: 1.8;
    }
    .renewal-prev-uploads a {
      color: #16a34a;
      text-decoration: none;
      font-size: 0.85rem;
    }
    .btn-remove-stall-file {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.2);
      padding: 0.4rem 0.8rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.83rem;
      font-weight: 600;
      margin-left: 1rem;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.2s ease;
    }
    .btn-remove-stall-file:hover {
      background: #ef4444;
      color: #ffffff;
      border-color: #ef4444;
    }
    .btn-remove-stall-file-x {
      font-size: 1.1rem;
      font-weight: bold;
      line-height: 1;
    }

    .submit-all-footer-btn {
      position: relative;
      margin-top: 2rem;
      width: 100%;
      background: rgba(255, 255, 255, 0.25) !important;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      color: #ffffff !important;
      border: 2px solid rgba(255, 255, 255, 0.35) !important;
      font-weight: 700;
      font-size: 1.1rem;
      padding: 1.15rem 2rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      cursor: pointer;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      overflow: hidden;
    }
    .submit-all-footer-btn::before {
      display: none;
    }
    .submit-all-footer-btn i,
    .submit-all-footer-btn span {
      position: relative;
      z-index: 1;
    }
    .submit-all-footer-btn:hover {
      background: rgba(255, 255, 255, 0.35) !important;
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.5) !important;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }

    .form-box-stall-picker {
      margin-bottom: 1.75rem;
    }

    .my-stalls-empty {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 18px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.15);
      border: 1px dashed rgba(255, 255, 255, 0.5);
      color: #fff;
      font-weight: 600;
    }
    .my-stalls-empty i { font-size: 1.2rem; }

    .my-stalls-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 14px;
      margin-top: 6px;
    }

    .my-stall-card {
      position: relative;
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding: 16px 16px 16px 46px;
      border-radius: 14px;
      background: #ffffff;
      border: 2px solid #e5e7f5;
      cursor: pointer;
      transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .my-stall-card:hover {
      border-color: #667eea;
      box-shadow: 0 8px 22px rgba(102, 126, 234, 0.18);
      transform: translateY(-2px);
    }
    .my-stall-card.is-due {
      border-color: #f6a723;
      background: #fffaf0;
    }
    .my-stall-check {
      position: absolute;
      left: -9999px;
      opacity: 0;
    }
    .my-stall-check-ui {
      position: absolute;
      left: 14px;
      top: 16px;
      width: 22px;
      height: 22px;
      border-radius: 6px;
      border: 2px solid #c7cbe6;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      background: #fff;
      transition: background .15s ease, border-color .15s ease;
    }
    .my-stall-check-ui i { font-size: 0.7rem; opacity: 0; }
    .my-stall-check:checked ~ .my-stall-check-ui {
      background: #667eea;
      border-color: #667eea;
    }
    .my-stall-check:checked ~ .my-stall-check-ui i { opacity: 1; }
    .my-stall-check:checked ~ .my-stall-body .my-stall-title { color: #4f52c9; }
    .my-stall-check:focus-visible ~ .my-stall-check-ui {
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.35);
    }

    .my-stall-body { flex: 1; min-width: 0; }
    .my-stall-title {
      font-weight: 700;
      color: #2b2f45;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
    }
    .my-stall-title i { color: #667eea; }
    .my-stall-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 14px;
      font-size: 0.82rem;
      color: #6b7089;
    }
    .my-stall-meta i { color: #9aa0bf; margin-right: 3px; }
    .my-stall-type {
      background: #eef0fb;
      color: #4f52c9;
      padding: 1px 9px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.72rem;
    }
    .my-stall-due-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 10px;
      padding: 6px 11px;
      border-radius: 999px;
      background: #fff2d6;
      color: #a5670a;
      font-weight: 700;
      font-size: 0.78rem;
      border: 1px solid #f6cd7a;
    }
    .my-stall-due-badge i { color: #e08c00; }

    body.darkmode {
      background-color: #667eea;
    }
    body.darkmode main {
      color: #e2e8f0;
    }
    body.darkmode .accounts-title {
      color: #f8fafc;
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(2.35rem, 5vw, 3.1rem);
      font-weight: 400;
      line-height: 1.05;
    }
    body.darkmode .accounts-title .app-title-accent {
      color: #ff6b35;
      font-style: italic;
    }
    body.darkmode .accounts-subtitle {
      color: #cbd5e1;
    }
    body.darkmode .form-box {
      background-color: rgba(45, 55, 72, 0.95);
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
    }
    body.darkmode .form-box::before {
      display: none;
    }
    body.darkmode label {
      color: #c7d2fe;
    }
    body.darkmode .required {
      color: #fca5a5;
    }
    body.darkmode input[type="text"],
    body.darkmode input[type="number"],
    body.darkmode select {
      background: rgba(31, 41, 55, 0.95);
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      border-color: rgba(255, 255, 255, 0.15);
      color: #e2e8f0;
      box-shadow: none;
    }
    body.darkmode input[type="text"]::placeholder { color: #64748b; }
    body.darkmode input[type="text"]:hover,
    body.darkmode input[type="number"]:hover,
    body.darkmode select:hover {
      border-color: rgba(255, 255, 255, 0.28);
      background: rgba(31, 41, 55, 0.95);
    }
    body.darkmode input[type="text"]:focus,
    body.darkmode input[type="number"]:focus,
    body.darkmode select:focus {
      background: rgba(17, 24, 39, 0.98);
      border-color: #818cf8;
      box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.22);
    }
    body.darkmode select {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background: rgba(31, 41, 55, 0.95) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 1rem center;
      padding-right: 2.6rem;
    }
    body.darkmode select:focus {
      background: rgba(17, 24, 39, 0.98) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 1rem center;
    }
    body.darkmode select:disabled {
      background: rgba(31, 41, 55, 0.85) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 1rem center;
      color: #e2e8f0;
      opacity: 1;
    }
    body.darkmode .renewal-select-disabled:disabled {
      background: rgba(31, 41, 55, 0.95) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 1rem center !important;
      color: #e2e8f0 !important;
      -webkit-text-fill-color: #e2e8f0;
      border-color: rgba(255, 255, 255, 0.15);
      cursor: not-allowed;
      opacity: 1;
    }
    body.darkmode select option {
      background-color: #1f2937;
      color: #e2e8f0;
    }
    body.darkmode .label-stall,
    body.darkmode .renewal-stall-heading {
      color: #e2e8f0;
    }
    body.darkmode .stall-field,
    body.darkmode select.stall-field,
    body.darkmode input.stall-field[type="number"],
    body.darkmode #num_stalls,
    body.darkmode #custom_stalls {
      background: rgba(31, 41, 55, 0.95);
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      border-color: rgba(255, 255, 255, 0.15);
      color: #e2e8f0;
      box-shadow: none;
    }
    body.darkmode select.stall-field,
    body.darkmode #num_stalls {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background: rgba(31, 41, 55, 0.95) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 1rem center;
      padding-right: 2.6rem;
    }
    body.darkmode select.stall-field:focus,
    body.darkmode #num_stalls:focus {
      background: rgba(17, 24, 39, 0.98) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 1rem center;
    }
    body.darkmode input.stall-field[type="number"],
    body.darkmode #custom_stalls {
      -moz-appearance: textfield;
      appearance: textfield;
    }
    body.darkmode input.stall-field[type="number"]::-webkit-inner-spin-button,
    body.darkmode input.stall-field[type="number"]::-webkit-outer-spin-button,
    body.darkmode #custom_stalls::-webkit-inner-spin-button,
    body.darkmode #custom_stalls::-webkit-outer-spin-button {
      -webkit-appearance: none;
      appearance: none;
      margin: 0;
      display: none;
    }
    body.darkmode .stall-hint,
    body.darkmode .renewal-stall-hint {
      color: #cbd5e1;
    }
    body.darkmode .stall-hint i,
    body.darkmode .renewal-stall-hint i {
      color: #a5b4fc;
    }
    body.darkmode .requirements-callout,
    body.darkmode .renewal-requirements-box {
      background: rgba(31, 41, 55, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-left: 4px solid #818cf8;
    }
    body.darkmode .requirements-callout-title,
    body.darkmode .renewal-requirements-box .renewal-req-title,
    body.darkmode .requirements-callout ul,
    body.darkmode .renewal-requirements-box ul {
      color: #e2e8f0;
    }
    body.darkmode .req-row > span,
    body.darkmode .renewal-req-name,
    body.darkmode .req-row-title {
      color: #ffffff !important;
    }
    body.darkmode .req-row-desc,
    body.darkmode .app-card-sub {
      color: #94a3b8 !important;
    }
    body.darkmode .app-upload-docs-title,
    body.darkmode .app-upload-docs-head h3 {
      color: #f8fafc !important;
    }
    body.darkmode .app-card-icon {
      background: rgba(255, 107, 53, 0.22);
      color: #ff8c5a;
    }
    body.darkmode .requirements-callout-title i,
    body.darkmode .renewal-requirements-box .renewal-req-title i {
      color: #a5b4fc;
    }
    body.darkmode .requirements-callout strong,
    body.darkmode .renewal-requirements-box .renewal-req-title strong {
      color: #f1f5f9;
    }
    body.darkmode .req-row,
    body.darkmode .renewal-req-row {
      border-bottom-color: rgba(255, 255, 255, 0.1);
    }
    body.darkmode .stall-section-heading,
    body.darkmode .renewal-stall-heading {
      color: #e2e8f0;
      border-bottom-color: rgba(255, 255, 255, 0.12);
    }
    body.darkmode .stall-section-heading i,
    body.darkmode .renewal-stall-heading i {
      color: #a5b4fc;
    }
    body.darkmode .file-list-heading,
    body.darkmode .renewal-file-list-heading {
      color: #cbd5e1;
    }
    body.darkmode .file-list-heading i,
    body.darkmode .renewal-file-list-heading i {
      color: #a5b4fc;
    }
    body.darkmode .btn-add-file,
    body.darkmode .renewal-req-add-btn {
      background: rgba(129, 140, 248, 0.18);
      color: #c7d2fe;
      border: 1px solid rgba(129, 140, 248, 0.35);
    }
    body.darkmode .btn-add-file:hover,
    body.darkmode .renewal-req-add-btn:hover {
      background: #818cf8;
      color: #ffffff;
      border-color: #818cf8;
    }
    body.darkmode .req-check,
    body.darkmode .renewal-req-check {
      background: rgba(34, 197, 94, 0.22);
      color: #4ade80;
    }
    body.darkmode .file-list {
      background: rgba(31, 41, 55, 0.88) !important;
      color: #e2e8f0;
      border-color: rgba(255, 255, 255, 0.1);
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
    }
    body.darkmode .file-item {
      background: rgba(17, 24, 39, 0.96) !important;
      border-color: rgba(255, 255, 255, 0.12);
      color: #f1f5f9 !important;
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
    }
    body.darkmode .file-item i {
      color: #a5b4fc;
    }
    body.darkmode .btn-remove-stall-file {
      background: rgba(248, 113, 113, 0.15);
      color: #fca5a5;
      border: 1px solid rgba(248, 113, 113, 0.3);
    }
    body.darkmode .btn-remove-stall-file:hover {
      background: #ef4444;
      color: #ffffff;
      border-color: #ef4444;
    }
    body.darkmode .renewal-prev-uploads {
      background: rgba(22, 101, 52, 0.28);
      border-left-color: #4ade80;
    }
    body.darkmode .renewal-prev-uploads p,
    body.darkmode .renewal-prev-uploads ul,
    body.darkmode .renewal-prev-uploads li,
    body.darkmode .renewal-prev-uploads span {
      color: #bbf7d0;
    }
    body.darkmode .renewal-prev-uploads a {
      color: #86efac;
    }
    body.darkmode .submit-all-footer-btn {
      background: rgba(31, 41, 55, 0.95) !important;
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      color: #f1f5f9 !important;
      text-shadow: none;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
    }
    body.darkmode .submit-all-footer-btn::before {
      display: none;
    }
    body.darkmode .submit-all-footer-btn:hover {
      background: rgba(55, 65, 81, 0.98) !important;
      border-color: rgba(255, 255, 255, 0.18) !important;
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
    }
    body.darkmode .alert-success {
      background: rgba(22, 101, 52, 0.45);
      color: #bbf7d0;
      border-color: rgba(74, 222, 128, 0.35);
    }
    body.darkmode .alert-danger {
      background: rgba(127, 29, 29, 0.45);
      color: #fecaca;
      border-color: rgba(248, 113, 113, 0.35);
    }
    body.darkmode .alert .btn-close {
      filter: invert(1);
    }
    body.darkmode .file-upload-label {
      background: rgba(31, 41, 55, 0.65);
      border-color: #818cf8;
    }

    @media (max-width: 991px) {
      main {
        padding: 0 0.75rem;
      }
      .accounts-header {
        flex-direction: column;
        align-items: stretch;
      }
      .accounts-header-right {
        width: 100%;
        justify-content: flex-end;
      }
      .form-box {
        padding: 1.5rem;
      }
    }

    @media (max-width: 768px) {
      main {
        padding: 0 0.5rem;
        margin: 1rem auto;
      }
      .accounts-title {
        font-size: 1.6rem;
      }
      .accounts-subtitle {
        font-size: 0.95rem;
      }
      .form-box {
        padding: 1.25rem;
      }
      .stall-field,
      select.stall-field,
      input.stall-field[type="number"] {
        min-width: 100%;
      }
    }

    @media (max-width: 576px) {
      .accounts-title {
        font-size: 1.4rem;
      }
      .accounts-subtitle {
        font-size: 0.85rem;
      }
      .form-box {
        padding: 1rem;
      }
      label {
        font-size: 0.9rem;
      }
      input[type="text"],
      select {
        padding: 0.7rem 0.85rem;
        font-size: 0.9rem;
        margin-bottom: 1rem;
      }
      .req-row,
      .renewal-req-row {
        flex-wrap: wrap;
      }
      .req-row-actions,
      .renewal-req-actions {
        width: 100%;
        justify-content: flex-end;
      }
      .file-list {
        padding: 0.75rem;
      }
      .file-item {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        flex-wrap: wrap;
      }
      .form-btn {
        padding: 0.75rem;
        font-size: 0.9rem;
      }
      .alert {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
      }
    }

  </style>
  <link rel="stylesheet" href="assets/css/vendor-light-mode-text.css?v=renewal-header-2">
  <link rel="stylesheet" href="assets/css/vendor-mobile-sidebar-logout.css">
  <link rel="stylesheet" href="assets/css/vendor-navbar-glass.css?v=nav-icon-phone-1">
  <link rel="stylesheet" href="assets/css/vendor-nav-theme-lock.css?v=1">
  <link rel="stylesheet" href="assets/css/vendor-portal-shell.css?v=1">
  <link rel="stylesheet" href="assets/css/menu-landing-nav.css?v=menu-nav-lock-4">
  <link rel="stylesheet" href="assets/css/vendor-portal-dark-nav-pills.css?v=phone-glass-4">
  <link rel="stylesheet" href="assets/css/vendor-page-glass.css?v=renewal-header-2">
  <style>
    /* Final overrides — match application.php header + white dark-mode reminders */
    body.page-renewal .main-container main .accounts-title,
    body.page-renewal .main-container main .accounts-title #renewalPageTitle {
      font-family: 'DM Serif Display', Georgia, serif !important;
      font-weight: 400 !important;
      font-size: clamp(2.35rem, 5vw, 3.1rem) !important;
      line-height: 1.05 !important;
      letter-spacing: -0.02em !important;
      text-shadow: none !important;
      display: block !important;
    }
    body:not(.darkmode).page-renewal .main-container main .accounts-title,
    body:not(.darkmode).page-renewal .main-container main .accounts-title #renewalPageTitle {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.darkmode.page-renewal .main-container main .accounts-title,
    body.darkmode.page-renewal .main-container main .accounts-title #renewalPageTitle {
      color: #f8fafc !important;
      -webkit-text-fill-color: #f8fafc !important;
    }
    body.page-renewal .main-container main .accounts-title .app-title-accent,
    body:not(.darkmode).page-renewal .main-container main .accounts-title .app-title-accent,
    body.darkmode.page-renewal .main-container main .accounts-title .app-title-accent {
      color: #ff6b35 !important;
      -webkit-text-fill-color: #ff6b35 !important;
      font-style: italic !important;
      font-family: 'DM Serif Display', Georgia, serif !important;
      font-weight: 400 !important;
      font-size: inherit !important;
      display: inline !important;
    }
    body:not(.darkmode).page-renewal .main-container main .accounts-subtitle {
      color: rgba(255, 255, 255, 0.78) !important;
      -webkit-text-fill-color: rgba(255, 255, 255, 0.78) !important;
    }

    body.darkmode.page-renewal .main-container main .form-box,
    body.darkmode.page-renewal .main-container main .form-box .label-stall,
    body.darkmode.page-renewal .main-container main .form-box .label-stall span,
    body.darkmode.page-renewal .main-container main .form-box .label-stall i,
    body.darkmode.page-renewal .main-container main .form-box .renewal-stall-hint,
    body.darkmode.page-renewal .main-container main .form-box .renewal-stall-hint span,
    body.darkmode.page-renewal .main-container main .form-box .renewal-stall-hint i,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-title,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-meta,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-meta span,
    body.darkmode.page-renewal .main-container main .form-box label,
    body.darkmode.page-renewal .main-container main .form-box .renewal-req-name,
    body.darkmode.page-renewal .main-container main .form-box .renewal-req-title,
    body.darkmode.page-renewal .main-container main .form-box .file-item,
    body.darkmode.page-renewal .main-container main .form-box .my-stalls-empty,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-due-badge,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-due-badge *,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-due-badge--overdue,
    body.darkmode.page-renewal .main-container main .form-box .my-stall-due-badge--overdue * {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.darkmode.page-renewal .main-container main .form-box .my-stall-title i {
      color: #ff6b35 !important;
      -webkit-text-fill-color: #ff6b35 !important;
    }
    body.darkmode.page-renewal .main-container main .form-box .my-stall-type {
      background: rgba(255, 255, 255, 0.14) !important;
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.darkmode.page-renewal .main-container main .form-box .my-stall-due-badge {
      background: rgba(255, 255, 255, 0.12) !important;
      border-color: rgba(255, 255, 255, 0.28) !important;
    }
    body.darkmode.page-renewal .main-container main .form-box .my-stall-due-badge--overdue {
      background: rgba(239, 68, 68, 0.38) !important;
      border-color: rgba(248, 113, 113, 0.65) !important;
    }
  </style>
</head>
<body class="page-renewal" data-application-mode="<?php echo $editing ? 'edit' : 'new'; ?>">
  <script>try{if(localStorage.getItem('darkmode')==='active')document.body.classList.add('darkmode');}catch(e){}</script>
  <div class="main-container">
    <?php $vendor_nav_active = 'renewal'; include __DIR__ . '/includes/vendor_portal_navbar.php'; ?>

  <main>
    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="accounts-header accounts-header-application">
      <div class="accounts-header-text">
        <h1 class="accounts-title">
          <?php if ($editing): ?>
            <span id="renewalPageTitle"><span>Edit renewal</span> <span class="app-title-accent">application</span></span>
          <?php else: ?>
            <span id="renewalPageTitle"><span>Renewal</span> <span class="app-title-accent">application</span></span>
          <?php endif; ?>
        </h1>
        <p class="accounts-subtitle" id="renewalPageSubtitle"><?php echo $editing ? 'Update your existing renewal details below. The allotment office reviews every renewal and replies within two working days.' : 'Click Application you want to renew and fill out the form below to renew your market clearance.'; ?></p>
      </div>
      <div class="accounts-header-right">
        <button type="button" class="profile-darkmode-btn" id="theme-switch" data-translate-title="profile.darkModeTitle" data-translate-aria="profile.darkModeAria" title="Dark mode" aria-label="Toggle dark mode">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-moon"><path d="M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Z"/></svg>
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-sun"><path d="M480-280q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Z"/></svg>
        </button>
      </div>
    </div>

    <?php if (!$editing): ?>
    <div class="form-box form-box-stall-picker" id="myStallsBox">
      <label class="label-stall">
        <i class="fas fa-store me-2"></i><span data-translate="stall.myAll">My All Stalls</span> <span class="required">*</span>
      </label>
      <p class="stall-hint renewal-stall-hint">
        <i class="fas fa-info-circle"></i> <span data-translate="stall.myAllHint">Select the stall(s) you want to renew.</span>
      </p>

      <?php if (empty($my_stalls)): ?>
        <div class="my-stalls-empty">
          <i class="fas fa-store-slash"></i>
          <span data-translate="stall.noApproved">You have no approved stalls to renew yet.</span>
        </div>
      <?php else: ?>
        <div class="my-stalls-list">
          <?php foreach ($my_stalls as $idx => $st):
            $st_name    = htmlspecialchars($st['vendor_name'] ?? '', ENT_QUOTES);
            $st_section = htmlspecialchars($st['section'] ?? '', ENT_QUOTES);
            $st_block   = htmlspecialchars($st['block'] ?? '', ENT_QUOTES);
            $st_slot    = (int) ($st['stall_number'] ?? 0);
            $st_type    = (($st['application_type'] ?? '') === 'renewal') ? 'Renewal' : 'New';
            $ref_date   = !empty($st['reviewed_date']) ? $st['reviewed_date'] : ($st['submitted_date'] ?? '');
            $ref_fmt    = $ref_date ? date('M d, Y', strtotime($ref_date)) : '—';
          ?>
          <label class="my-stall-card<?php echo !empty($st['is_overdue']) ? ' is-overdue' : (!empty($st['almost_due']) ? ' is-due' : ''); ?>" for="myStall_<?php echo $idx; ?>">
            <input type="checkbox" class="my-stall-check" id="myStall_<?php echo $idx; ?>"
                   data-section="<?php echo $st_section; ?>"
                   data-block="<?php echo $st_block; ?>"
                   data-stall="<?php echo $st_slot; ?>"
                   data-name="<?php echo $st_name; ?>"
                   onchange="renderSelectedStalls()">
            <span class="my-stall-check-ui"><i class="fas fa-check"></i></span>
            <div class="my-stall-body">
              <div class="my-stall-title">
                <i class="fas fa-store"></i>
                <?php echo $st_section !== '' ? $st_section : 'Stall'; ?><?php echo $st_slot > 0 ? ' &mdash; Stall ' . $st_slot : ''; ?>
              </div>
              <div class="my-stall-meta">
                <span><i class="fas fa-layer-group"></i> <?php echo $st_block !== '' ? $st_block : '—'; ?></span>
                <span><i class="fas fa-calendar-check"></i> <?php echo $ref_fmt; ?></span>
                <span class="my-stall-type"><?php echo $st_type; ?></span>
              </div>
              <?php if (!empty($st['is_overdue'])): ?>
              <div class="my-stall-due-badge my-stall-due-badge--overdue">
                <i class="fas fa-circle-exclamation"></i>
                <span data-translate="stall.alreadyDue">Your application is already due, renew it now.</span>
              </div>
              <?php elseif (!empty($st['almost_due'])): ?>
              <div class="my-stall-due-badge">
                <i class="fas fa-triangle-exclamation"></i>
                <span data-translate="stall.almostDue">Almost due date, don't forget to renew!</span>
              </div>
              <?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div id="formsContainer">
    <?php if ($editing): ?>
      <form class="form-box" method="POST" action="renewal.php<?php echo $editing_vendor_id ? '?vendor_id=' . $editing_vendor_id : ''; ?>" enctype="multipart/form-data">
        <input type="hidden" name="vendor_id" value="<?php echo (int)$editing_vendor_id; ?>">
        <label for="full_name"><span data-translate="form.fullName">Full Name</span> <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($editing_vendor['vendor_name']); ?>" required>

        <label for="section"><span data-translate="form.section">Section</span> <span class="required">*</span></label>
        <?php 
        // Use existing section from approved application or editing vendor
        $current_section = !empty($editing_vendor['section']) ? $editing_vendor['section'] : $existing_section;
        ?>
        <select id="section" name="section" class="renewal-select-disabled" required disabled>
          <option value="" data-translate="form.selectSection">Select a section</option>
          <option value="Fresh Fish Section" data-translate="opt.freshFish" <?php echo $current_section === 'Fresh Fish Section' ? 'selected' : ''; ?>>Fresh Fish Section</option>
          <option value="Meat and Chicken Section" data-translate="opt.meat" <?php echo $current_section === 'Meat and Chicken Section' ? 'selected' : ''; ?>>Meat and Chicken Section</option>
          <option value="Dried and Salted Fish Section" data-translate="opt.driedFish" <?php echo $current_section === 'Dried and Salted Fish Section' ? 'selected' : ''; ?>>Dried and Salted Fish Section</option>
          <option value="Fruits and Vegetables Section" data-translate="opt.produce" <?php echo $current_section === 'Fruits and Vegetables Section' ? 'selected' : ''; ?>>Fruits and Vegetables Section</option>
          <option value="Dry Goods Section" data-translate="opt.dryGoods" <?php echo $current_section === 'Dry Goods Section' ? 'selected' : ''; ?>>Dry Goods Section</option>
          <option value="Groceries Sari-Sari Section" data-translate="opt.sariSari" <?php echo $current_section === 'Groceries Sari-Sari Section' ? 'selected' : ''; ?>>Groceries Sari-Sari Section</option>
          <option value="Eateries and Cooked Food Section" data-translate="opt.eateries" <?php echo $current_section === 'Eateries and Cooked Food Section' ? 'selected' : ''; ?>>Eateries and Cooked Food Section</option>
          <option value="Service Section" data-translate="opt.service" <?php echo $current_section === 'Service Section' ? 'selected' : ''; ?>>Service Section</option>
          <option value="Special Area Section" data-translate="opt.special" <?php echo $current_section === 'Special Area Section' ? 'selected' : ''; ?>>Special Area Section</option>
        </select>
        <input type="hidden" name="section" value="<?php echo htmlspecialchars($current_section); ?>">

        <label for="block"><span data-translate="form.block">Block</span> <span class="required">*</span></label>
        <?php 
        // Use existing block from approved application or editing vendor
        $current_block = !empty($editing_vendor['block']) ? $editing_vendor['block'] : $existing_block;
        ?>
        <select id="block" name="block" class="renewal-select-disabled" required disabled>
          <option value="" data-translate="form.selectBlock">Select a Block</option>
          <option value="Daet Elevated Plaza" data-translate="blk.plaza" <?php echo $current_block === 'Daet Elevated Plaza' ? 'selected' : ''; ?>>Daet Elevated Plaza</option>
          <option value="Market Tienda Bldg" data-translate="blk.tienda" <?php echo $current_block === 'Market Tienda Bldg' ? 'selected' : ''; ?>>Market Tienda Bldg</option>
          <option value="Block A" data-translate="blk.a" <?php echo $current_block === 'Block A' ? 'selected' : ''; ?>>Block A</option>
          <option value="Block B" data-translate="blk.b" <?php echo $current_block === 'Block B' ? 'selected' : ''; ?>>Block B</option>
          <option value="Block C" data-translate="blk.c" <?php echo $current_block === 'Block C' ? 'selected' : ''; ?>>Block C</option>
          <option value="BOT Block A-1" data-translate="blk.botA1" <?php echo $current_block === 'BOT Block A-1' ? 'selected' : ''; ?>>BOT Block A-1</option>
          <option value="BOT Block E" data-translate="blk.botE" <?php echo $current_block === 'BOT Block E' ? 'selected' : ''; ?>>BOT Block E</option>
          <option value="BOT Behind Block B" data-translate="blk.behindB" <?php echo $current_block === 'BOT Behind Block B' ? 'selected' : ''; ?>>BOT Behind Block B</option>
          <option value="BOT Behind Block C" data-translate="blk.behindC" <?php echo $current_block === 'BOT Behind Block C' ? 'selected' : ''; ?>>BOT Behind Block C</option>
        </select>
        <input type="hidden" name="block" value="<?php echo htmlspecialchars($current_block); ?>">

        <label for="stall_number"><span data-translate="form.stallNumber">Stall Slot Number</span> <span class="required">*</span></label>
        <?php $current_stall = (int) (!empty($editing_vendor['stall_number']) ? $editing_vendor['stall_number'] : $existing_stall); ?>
        <select id="stall_number" class="renewal-select-disabled" disabled>
          <option value="<?php echo $current_stall; ?>" selected>Stall <?php echo $current_stall > 0 ? $current_stall : '—'; ?></option>
        </select>
        <input type="hidden" name="stall_number" value="<?php echo $current_stall; ?>">

        <?php $doc_req_count_edit = count($application_requirements); ?>
        <div class="app-upload-docs">
        <div class="app-card-head app-upload-docs-head">
          <span class="app-card-icon" aria-hidden="true"><i class="fas fa-upload"></i></span>
          <div>
            <h3 class="app-upload-docs-title"><span data-translate="form.uploadDocs">Upload documents</span> <span class="required">*</span></h3>
            <p class="app-card-sub app-docs-status" id="docsStatus_edit" data-total="<?php echo (int) $doc_req_count_edit; ?>">0 of <?php echo (int) $doc_req_count_edit; ?> requirements attached.</p>
          </div>
        </div>
        <div class="requirements-callout">
          <ul>
            <?php 
            $req_types_edit = ['id_card', 'dti', 'barangay', 'moa', 'lease', 'inspection', 'receipt'];
            $req_index_edit = 0;
            foreach ($application_requirements as $req): 
              $req_type_edit = $req_types_edit[$req_index_edit] ?? 'other_' . $req_index_edit;
              $req_index_edit++;
              $req_name_only = htmlspecialchars($req['name']);
              $req_desc_only = htmlspecialchars($req['description'] ?? '');
            ?>
            <li class="req-row">
              <div class="req-row-copy">
                <div>
                  <span class="req-row-title"><?php echo $req_name_only; ?><?php echo !empty($req['required']) || preg_match('/clearance|receipt/i', $req['name']) ? ' <span class="required">*</span>' : ''; ?></span>
                  <?php if ($req_desc_only !== ''): ?><span class="req-row-desc"><?php echo $req_desc_only; ?></span><?php endif; ?>
                </div>
              </div>
              <div class="req-row-actions">
                <span id="check_<?php echo $req_type_edit; ?>_edit" class="req-check"><i class="fas fa-check" aria-hidden="true"></i></span>
                <button type="button" class="btn-add-file" onclick="document.getElementById('file_<?php echo $req_type_edit; ?>_edit').click(); return false;">
                  <i class="fas fa-plus"></i> <span data-translate="form.addFile">Add file</span>
                </button>
              </div>
              <input type="file" id="file_<?php echo $req_type_edit; ?>_edit" name="documents[]" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display: none; position: absolute; left: -9999px;" onchange="handleFileUpload(this, 'edit', '<?php echo $req_type_edit; ?>')">
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div id="fileList_edit" class="file-list" style="display: none; margin-top: 1rem;"></div>
        </div>
        
        <?php if (!empty($existing_documents)): ?>
        <div class="renewal-prev-uploads">
          <p><i class="fas fa-file-check"></i> <strong data-translate="renewal.prevUploads">Previously Uploaded Documents:</strong></p>
          <ul>
            <?php foreach ($existing_documents as $doc): 
              $file_size_kb = round($doc['file_size'] / 1024, 2);
              $file_name = htmlspecialchars($doc['original_name']);
            ?>
            <li style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span><i class="fas fa-file"></i> <?php echo $file_name; ?> (<?php echo $file_size_kb; ?> KB)</span>
              <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" style="color: #4caf50; text-decoration: none; font-size: 0.85rem;">
                <i class="fas fa-download"></i> <span data-translate="renewal.viewFile">View</span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        
        <button type="submit" name="submit_application" class="form-btn submit-all-btn submit-all-footer-btn">
          <i class="fas fa-sync-alt"></i> <span data-translate="form.updateRenewal">Update Renewal</span>
        </button>
      </form>
    <?php else: ?>
      <form method="POST" action="renewal.php" enctype="multipart/form-data" id="applicationForm">
      </form>
    <?php endif; ?>
    </div>
  </main>
  </div><!-- /.main-container -->

  <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 15px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
          <h5 class="modal-title" id="calendarModalLabel" style="color: #6366f1;" data-translate="modal.calendar">
            Calendar
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" data-translate-aria="modal.close" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
          <div id="calendarContainer" style="color: #475569;">
            <p style="text-align: center; color: rgba(71, 85, 105, 0.7);" data-translate="modal.calendar.coming">Calendar functionality coming soon</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
<?php readfile(__DIR__ . '/_renewal_i18n_extract.txt'); ?>
  </script>
  <script>
    let selectedFiles = [];
    const fileInput = document.getElementById('documents');
    
    function displaySelectedFiles(input) {
      const fileList = document.getElementById('fileList');
      const newFiles = Array.from(input.files);
      
      newFiles.forEach(newFile => {
        const isDuplicate = selectedFiles.some(existingFile => 
          existingFile.name === newFile.name && existingFile.size === newFile.size
        );
        if (!isDuplicate) {
          selectedFiles.push(newFile);
        }
      });
      
      const dataTransfer = new DataTransfer();
      selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
      });
      fileInput.files = dataTransfer.files;
      
      if (selectedFiles.length > 0) {
        fileList.style.display = 'block';
        fileList.innerHTML = '<h6 style="margin-bottom: 1rem; color: #667eea;"><i class="fas fa-paperclip"></i> Selected Files (' + selectedFiles.length + '):</h6>';
        
        let totalSize = 0;
        selectedFiles.forEach((file, index) => {
          const fileSize = (file.size / 1024).toFixed(2);
          totalSize += file.size;
          const fileItem = document.createElement('div');
          fileItem.className = 'file-item';
          fileItem.innerHTML = `
            <span><i class="fas fa-file"></i> ${file.name} (${fileSize} KB)</span>
            <button type="button" onclick="removeFile(${index})" style="background: #FFA07A; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
              <i class="fas fa-times"></i> Remove
            </button>
          `;
          fileList.appendChild(fileItem);
        });
        
        const totalSizeKB = (totalSize / 1024).toFixed(2);
        const totalSizeMB = (totalSize / (1024 * 1024)).toFixed(2);
        const sizeText = totalSizeMB > 1 ? totalSizeMB + ' MB' : totalSizeKB + ' KB';
        fileList.innerHTML += '<p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;"><strong>Total:</strong> ' + selectedFiles.length + ' files, ' + sizeText + '</p>';
      } else {
        fileList.style.display = 'none';
      }
    }
    
    function removeFile(index) {
      selectedFiles.splice(index, 1);
      
      const dataTransfer = new DataTransfer();
      selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
      });
      fileInput.files = dataTransfer.files;
      
      displaySelectedFiles(fileInput);
    }

    const numStallsSelect = document.getElementById('num_stalls');
    const customStallsInput = document.getElementById('custom_stalls');
    
    function handleStallSelection() {
      if (numStallsSelect && numStallsSelect.value === 'custom') {
        customStallsInput.style.display = 'block';
        customStallsInput.focus();
        customStallsInput.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') {
            customStallsInput.style.display = 'none';
            numStallsSelect.value = '1';
            updateStallForms();
          }
        });
      } else {
        customStallsInput.style.display = 'none';
        updateStallForms();
      }
    }
    
    if (numStallsSelect) {
      numStallsSelect.addEventListener('change', handleStallSelection);
    }
    
    if (customStallsInput) {
      customStallsInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          customStallsInput.style.display = 'none';
          numStallsSelect.value = '1';
          updateStallForms();
        }
      });
    }

    // Get existing section and block from PHP
    const existingSection = '<?php echo htmlspecialchars($existing_section, ENT_QUOTES); ?>';
    const existingBlock = '<?php echo htmlspecialchars($existing_block, ENT_QUOTES); ?>';
    const existingStall = <?php echo (int) $existing_stall; ?>;
    
    // Escape a value for use inside an HTML attribute.
    function attrEsc(v) {
      return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function selIf(a, b) { return String(a) === String(b) ? 'selected' : ''; }

    // Build a single renewal stall form, prefilled + locked to a specific stall the user holds.
    function buildStallFormHTML(i, section, block, stall, fullName) {
      const s = section || '';
      const b = block || '';
      const st = parseInt(stall) || 0;
      const nm = fullName || '<?php echo htmlspecialchars($user_name, ENT_QUOTES); ?>';
      return `
        <h3 class="renewal-stall-heading">
          <i class="fas fa-store me-2"></i><span data-translate-stall="form.stallHeading" data-stall-n="${i}">Stall ${i} Renewal</span>
        </h3>
        <label for="full_name_${i}"><span data-translate="form.fullName">Full Name</span> <span class="required">*</span></label>
        <input type="text" id="full_name_${i}" name="full_name[]" value="${attrEsc(nm)}" required>

        <label for="section_${i}"><span data-translate="form.section">Section</span> <span class="required">*</span></label>
        <select id="section_${i}" name="section[]" class="renewal-select-disabled" required disabled>
          <option value="" data-translate="form.selectSection">Select a section</option>
          <option value="Fresh Fish Section" data-translate="opt.freshFish" ${selIf(s,'Fresh Fish Section')}>Fresh Fish Section</option>
          <option value="Meat and Chicken Section" data-translate="opt.meat" ${selIf(s,'Meat and Chicken Section')}>Meat and Chicken Section</option>
          <option value="Dried and Salted Fish Section" data-translate="opt.driedFish" ${selIf(s,'Dried and Salted Fish Section')}>Dried and Salted Fish Section</option>
          <option value="Fruits and Vegetables Section" data-translate="opt.produce" ${selIf(s,'Fruits and Vegetables Section')}>Fruits and Vegetables Section</option>
          <option value="Dry Goods Section" data-translate="opt.dryGoods" ${selIf(s,'Dry Goods Section')}>Dry Goods Section</option>
          <option value="Groceries Sari-Sari Section" data-translate="opt.sariSari" ${selIf(s,'Groceries Sari-Sari Section')}>Groceries Sari-Sari Section</option>
          <option value="Eateries and Cooked Food Section" data-translate="opt.eateries" ${selIf(s,'Eateries and Cooked Food Section')}>Eateries and Cooked Food Section</option>
          <option value="Service Section" data-translate="opt.service" ${selIf(s,'Service Section')}>Service Section</option>
          <option value="Special Area Section" data-translate="opt.special" ${selIf(s,'Special Area Section')}>Special Area Section</option>
        </select>
        <input type="hidden" name="section[]" value="${attrEsc(s)}">

        <label for="block_${i}"><span data-translate="form.block">Block</span> <span class="required">*</span></label>
        <select id="block_${i}" name="block[]" class="renewal-select-disabled" required disabled>
          <option value="" data-translate="form.selectBlock">Select a Block</option>
          <option value="Daet Elevated Plaza" data-translate="blk.plaza" ${selIf(b,'Daet Elevated Plaza')}>Daet Elevated Plaza</option>
          <option value="Market Tienda Bldg" data-translate="blk.tienda" ${selIf(b,'Market Tienda Bldg')}>Market Tienda Bldg</option>
          <option value="Block A" data-translate="blk.a" ${selIf(b,'Block A')}>Block A</option>
          <option value="Block B" data-translate="blk.b" ${selIf(b,'Block B')}>Block B</option>
          <option value="Block C" data-translate="blk.c" ${selIf(b,'Block C')}>Block C</option>
          <option value="BOT Block A-1" data-translate="blk.botA1" ${selIf(b,'BOT Block A-1')}>BOT Block A-1</option>
          <option value="BOT Block E" data-translate="blk.botE" ${selIf(b,'BOT Block E')}>BOT Block E</option>
          <option value="BOT Behind Block B" data-translate="blk.behindB" ${selIf(b,'BOT Behind Block B')}>BOT Behind Block B</option>
          <option value="BOT Behind Block C" data-translate="blk.behindC" ${selIf(b,'BOT Behind Block C')}>BOT Behind Block C</option>
        </select>
        <input type="hidden" name="block[]" value="${attrEsc(b)}">

        <label for="stall_number_${i}"><span data-translate="form.stallNumber">Stall Slot Number</span> <span class="required">*</span></label>
        <select id="stall_number_${i}" class="renewal-select-disabled" disabled>
          <option value="${st}" selected>Stall ${st > 0 ? st : '—'}</option>
        </select>
        <input type="hidden" name="stall_number[]" value="${st}">

        ${buildUploadDocsHTML(i)}
        <div id="fileList_${i}" class="file-list" style="display: none;"></div>
      `;
    }

    // Make sure the single "Submit All Renewals" button exists at the end of the form.
    function ensureRenewalSubmitButton() {
      const existingForm = document.getElementById('applicationForm');
      if (!existingForm) return;
      const oldButtons = existingForm.querySelectorAll('button[name="submit_application"]');
      oldButtons.forEach(btn => btn.remove());
      const submitButton = document.createElement('button');
      submitButton.type = 'submit';
      submitButton.name = 'submit_application';
      submitButton.className = 'form-btn submit-all-btn submit-all-footer-btn';
      submitButton.innerHTML = '<i class="fas fa-sync-alt"></i> <span data-translate="form.submitAllRenewals">Submit All Renewals</span>';
      existingForm.appendChild(submitButton);
      if (typeof translatePage === 'function') {
        translatePage(localStorage.getItem('preferredLanguage') || 'en');
      }
    }

    // Build a renewal form for each stall the user checked in the "My All Stalls" list.
    function renderSelectedStalls() {
      const existingForm = document.getElementById('applicationForm');
      if (!existingForm) return;

      const checks = Array.from(document.querySelectorAll('.my-stall-check:checked'));
      existingForm.innerHTML = '';
      if (checks.length === 0) {
        return; // Nothing selected -> no form, no submit button.
      }

      checks.forEach((chk, idx) => {
        const i = idx + 1;
        const formBox = document.createElement('div');
        formBox.className = 'form-box';
        if (i > 1) formBox.style.marginTop = '2rem';
        formBox.innerHTML = buildStallFormHTML(
          i,
          chk.getAttribute('data-section') || '',
          chk.getAttribute('data-block') || '',
          chk.getAttribute('data-stall') || 0,
          chk.getAttribute('data-name') || ''
        );
        existingForm.appendChild(formBox);
      });

      if (typeof translatePage === 'function') {
        translatePage(localStorage.getItem('preferredLanguage') || 'en');
      }
      ensureRenewalSubmitButton();
      if (typeof updateDocsAttachedCounters === 'function') {
        updateDocsAttachedCounters();
      }
    }

    function updateStallForms() {
      const formsContainer = document.getElementById('formsContainer');
      if (!formsContainer) return;
      
      let numStalls = 1;
      
      if (numStallsSelect && numStallsSelect.value === 'custom') {
        numStalls = parseInt(customStallsInput.value) || 1;
        if (numStalls < 1) numStalls = 1;
        if (numStalls > 20) {
          numStalls = 20;
          customStallsInput.value = 20;
          (function(){ var G=window.APPLICATION_I18N, l=localStorage.getItem('preferredLanguage')||'en', p=G&&(G[l]||G.en); alert((p&&p['alert.maxStalls'])||'Maximum 20 stalls allowed per application.'); })();
        }
      } else if (numStallsSelect) {
        numStalls = parseInt(numStallsSelect.value) || 1;
      }
      
      // Clear existing forms
      const existingForm = document.getElementById('applicationForm');
      if (existingForm) {
        existingForm.innerHTML = '';
      }
      
      // Generate forms for each stall
      for (let i = 1; i <= numStalls; i++) {
        const formBox = document.createElement('div');
        formBox.className = 'form-box';
        if (i > 1) {
          formBox.style.marginTop = '2rem';
        }
        
        formBox.innerHTML = `
          <h3 class="renewal-stall-heading">
            <i class="fas fa-store me-2"></i><span data-translate-stall="form.stallHeading" data-stall-n="${i}">Stall ${i} Application</span>
          </h3>
          <label for="full_name_${i}"><span data-translate="form.fullName">Full Name</span> <span class="required">*</span></label>
          <input type="text" id="full_name_${i}" name="full_name[]" value="${document.getElementById('full_name') ? document.getElementById('full_name').value : '<?php echo htmlspecialchars($user_name); ?>'}" required>

          <label for="section_${i}"><span data-translate="form.section">Section</span> <span class="required">*</span></label>
          <select id="section_${i}" name="section[]" class="renewal-select-disabled" required disabled>
            <option value="" data-translate="form.selectSection">Select a section</option>
            <option value="Fresh Fish Section" data-translate="opt.freshFish" ${existingSection === 'Fresh Fish Section' ? 'selected' : ''}>Fresh Fish Section</option>
            <option value="Meat and Chicken Section" data-translate="opt.meat" ${existingSection === 'Meat and Chicken Section' ? 'selected' : ''}>Meat and Chicken Section</option>
            <option value="Dried and Salted Fish Section" data-translate="opt.driedFish" ${existingSection === 'Dried and Salted Fish Section' ? 'selected' : ''}>Dried and Salted Fish Section</option>
            <option value="Fruits and Vegetables Section" data-translate="opt.produce" ${existingSection === 'Fruits and Vegetables Section' ? 'selected' : ''}>Fruits and Vegetables Section</option>
            <option value="Dry Goods Section" data-translate="opt.dryGoods" ${existingSection === 'Dry Goods Section' ? 'selected' : ''}>Dry Goods Section</option>
            <option value="Groceries Sari-Sari Section" data-translate="opt.sariSari" ${existingSection === 'Groceries Sari-Sari Section' ? 'selected' : ''}>Groceries Sari-Sari Section</option>
            <option value="Eateries and Cooked Food Section" data-translate="opt.eateries" ${existingSection === 'Eateries and Cooked Food Section' ? 'selected' : ''}>Eateries and Cooked Food Section</option>
            <option value="Service Section" data-translate="opt.service" ${existingSection === 'Service Section' ? 'selected' : ''}>Service Section</option>
            <option value="Special Area Section" data-translate="opt.special" ${existingSection === 'Special Area Section' ? 'selected' : ''}>Special Area Section</option>
          </select>
          <input type="hidden" name="section[]" value="${existingSection}">

          <label for="block_${i}"><span data-translate="form.block">Block</span> <span class="required">*</span></label>
          <select id="block_${i}" name="block[]" class="renewal-select-disabled" required disabled>
            <option value="" data-translate="form.selectBlock">Select a Block</option>
            <option value="Daet Elevated Plaza" data-translate="blk.plaza" ${existingBlock === 'Daet Elevated Plaza' ? 'selected' : ''}>Daet Elevated Plaza</option>
            <option value="Market Tienda Bldg" data-translate="blk.tienda" ${existingBlock === 'Market Tienda Bldg' ? 'selected' : ''}>Market Tienda Bldg</option>
            <option value="Block A" data-translate="blk.a" ${existingBlock === 'Block A' ? 'selected' : ''}>Block A</option>
            <option value="Block B" data-translate="blk.b" ${existingBlock === 'Block B' ? 'selected' : ''}>Block B</option>
            <option value="Block C" data-translate="blk.c" ${existingBlock === 'Block C' ? 'selected' : ''}>Block C</option>
            <option value="BOT Block A-1" data-translate="blk.botA1" ${existingBlock === 'BOT Block A-1' ? 'selected' : ''}>BOT Block A-1</option>
            <option value="BOT Block E" data-translate="blk.botE" ${existingBlock === 'BOT Block E' ? 'selected' : ''}>BOT Block E</option>
            <option value="BOT Behind Block B" data-translate="blk.behindB" ${existingBlock === 'BOT Behind Block B' ? 'selected' : ''}>BOT Behind Block B</option>
            <option value="BOT Behind Block C" data-translate="blk.behindC" ${existingBlock === 'BOT Behind Block C' ? 'selected' : ''}>BOT Behind Block C</option>
          </select>
          <input type="hidden" name="block[]" value="${existingBlock}">

          <label for="stall_number_${i}"><span data-translate="form.stallNumber">Stall Slot Number</span> <span class="required">*</span></label>
          <select id="stall_number_${i}" class="renewal-select-disabled" disabled>
            <option value="${existingStall}" selected>Stall ${existingStall > 0 ? existingStall : '—'}</option>
          </select>
          <input type="hidden" name="stall_number[]" value="${existingStall}">

          ${buildUploadDocsHTML(i)}
          <div id="fileList_${i}" class="file-list" style="display: none;"></div>
        `;
        
        if (existingForm) {
          existingForm.appendChild(formBox);
        }
      }
      if (typeof translatePage === 'function') {
        translatePage(localStorage.getItem('preferredLanguage') || 'en');
      }
      
      // Always add single "Submit All Applications" button at the end
      setTimeout(function() {
        const existingForm = document.getElementById('applicationForm');
        if (existingForm) {
          const existingSubmitBtn = existingForm.querySelector('button[name="submit_application"]');
          if (existingSubmitBtn && (existingSubmitBtn.textContent.includes('Submit All Applications') || existingSubmitBtn.textContent.includes('Submit All Renewals'))) {
            return;
          }
          const oldButtons = existingForm.querySelectorAll('.form-box button[name="submit_application"]');
          oldButtons.forEach(btn => btn.remove());
          
          const submitButton = document.createElement('button');
          submitButton.type = 'submit';
          submitButton.name = 'submit_application';
          submitButton.className = 'form-btn submit-all-btn submit-all-footer-btn';
          submitButton.innerHTML = '<i class="fas fa-sync-alt"></i> <span data-translate="form.submitAllRenewals">Submit All Renewals</span>';
          existingForm.appendChild(submitButton);
          if (typeof translatePage === 'function') {
            translatePage(localStorage.getItem('preferredLanguage') || 'en');
          }
        }
      }, 100);
    }
    
    function handleFileUpload(input, stallNumber, requirementType) {
      if (input.files && input.files.length > 0) {
        const file = input.files[0];
        const fileListId = stallNumber === 'edit' ? 'fileList_edit' : 'fileList_' + stallNumber;
        const fileList = document.getElementById(fileListId);
        
        if (!fileList) {
          // Create file list if it doesn't exist
          const parentLi = input.closest('li');
          if (parentLi) {
            const requirementsDiv = parentLi.closest('.app-upload-docs');
            if (requirementsDiv) {
              const formBox = requirementsDiv.closest('.form-box');
              if (formBox) {
                // Check if file list already exists elsewhere in the form
                let existingFileList = formBox.querySelector('#fileList_' + stallNumber);
                if (!existingFileList && stallNumber !== 'edit') {
                  existingFileList = document.createElement('div');
                  existingFileList.id = fileListId;
                  existingFileList.className = 'file-list';
                  existingFileList.style.cssText = 'display: block; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;';
                  // Insert after the requirements div
                  requirementsDiv.parentNode.insertBefore(existingFileList, requirementsDiv.nextSibling);
                } else if (stallNumber === 'edit') {
                  // For edit mode, create after requirements div
                  existingFileList = document.createElement('div');
                  existingFileList.id = fileListId;
                  existingFileList.className = 'file-list';
                  existingFileList.style.cssText = 'display: block; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;';
                  requirementsDiv.parentNode.insertBefore(existingFileList, requirementsDiv.nextSibling);
                }
                if (existingFileList) {
                  fileList = existingFileList;
                } else {
                  return;
                }
              } else {
                return;
              }
            } else {
              return;
            }
          } else {
            return;
          }
        }
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
          alert('File size must be less than 5MB');
          input.value = '';
          return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const fileType = file.type;
        const fileExt = file.name.split('.').pop().toLowerCase();
        const allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        
        if (!allowedTypes.includes(fileType) && !allowedExts.includes(fileExt)) {
          alert('Invalid file type. Allowed: JPG, PNG, PDF, DOC, DOCX');
          input.value = '';
          return;
        }
        
        // Show file list if hidden
        if (fileList.style.display === 'none') {
          fileList.style.display = 'block';
          fileList.innerHTML = '<h6 class="renewal-file-list-heading"><i class="fas fa-paperclip"></i> Uploaded Files:</h6>';
        }
        
        // Remove existing file for this requirement if any
        const existingFileId = 'file_' + stallNumber + '_' + requirementType;
        const existingFile = document.getElementById(existingFileId);
        if (existingFile) {
          existingFile.remove();
        }
        
        // Find and show checkmark - use requirementType directly
        let checkmarkId;
        if (stallNumber === 'edit') {
          checkmarkId = 'check_' + requirementType + '_edit';
        } else {
          checkmarkId = 'check_' + requirementType + '_' + stallNumber;
        }
        
        const checkmark = document.getElementById(checkmarkId);
        if (checkmark) {
          checkmark.style.display = 'inline-flex';
        } else {
          const parentDiv = input.closest('li');
          if (parentDiv) {
            const checkmarkSpan = parentDiv.querySelector('span[id^="check_"]');
            if (checkmarkSpan) {
              checkmarkSpan.style.display = 'inline-flex';
            }
          }
        }
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.id = existingFileId;
        const fileSize = (file.size / 1024).toFixed(2);
        fileItem.innerHTML = `
          <span><i class="fas fa-file"></i> ${file.name} (${fileSize} KB)</span>
          <button type="button" class="btn-remove-stall-file" onclick="removeFileItem('${existingFileId}', '${input.id}', '${checkmarkId}')">
            <span class="btn-remove-stall-file-x">×</span> <span>Remove</span>
          </button>
        `;
        fileList.appendChild(fileItem);
      }
      if (typeof updateDocsAttachedCounters === 'function') {
        updateDocsAttachedCounters();
      }
    }
    
    // Requirements from database
    const applicationRequirements = <?php echo $requirements_json; ?>;
    
    function getRequirementName(type) {
      const names = {
        'id_card': 'ID Card',
        'dti': 'DTI Registration',
        'barangay': 'Barangay Clearance',
        'moa': 'Memorandum of Agreement',
        'lease': 'Lease Contract',
        'inspection': 'Stall Inspection',
        'receipt': 'Official Receipt'
      };
      return names[type] || 'Document';
    }
    
    window.RENEWAL_I18N = {
      ph: {
        'stall.myAll': 'Lahat ng Aking Stall',
        'stall.myAllHint': 'Piliin ang stall na gusto mong i-renew.',
        'stall.noApproved': 'Wala ka pang aprubadong stall na maaaring i-renew.',
        'stall.alreadyDue': 'Lampas na ang iyong aplikasyon, i-renew na ito ngayon.',
        'stall.almostDue': 'Malapit na ang due date, huwag kalimutang mag-renew!',
        'form.fullName': 'Buong Pangalan',
        'form.section': 'Seksyon',
        'form.selectSection': 'Pumili ng seksyon',
        'opt.freshFish': 'Seksyon ng Sariwang Isda',
        'opt.meat': 'Seksyon ng Karne at Manok',
        'opt.driedFish': 'Seksyon ng Pinatuyo at Inasnan na Isda',
        'opt.produce': 'Seksyon ng Prutas at Gulay',
        'opt.dryGoods': 'Seksyon ng Dry Goods',
        'opt.sariSari': 'Seksyon ng Groceries / Sari-Sari',
        'opt.eateries': 'Seksyon ng Kainan at Lutong Pagkain',
        'opt.service': 'Seksyon ng Serbisyo',
        'opt.special': 'Seksyon ng Special Area',
        'form.block': 'Bloke',
        'form.selectBlock': 'Pumili ng Bloke',
        'blk.plaza': 'Daet Elevated Plaza',
        'blk.tienda': 'Gusali ng Market Tienda',
        'blk.a': 'Bloke A',
        'blk.b': 'Bloke B',
        'blk.c': 'Bloke C',
        'blk.botA1': 'BOT Bloke A-1',
        'blk.botE': 'BOT Bloke E',
        'blk.behindB': 'BOT sa Likod ng Bloke B',
        'blk.behindC': 'BOT sa Likod ng Bloke C',
        'form.stallNumber': 'Numero ng Stall',
        'form.uploadDocs': 'Mag-upload ng mga dokumento',
        'form.addFile': 'Magdagdag ng file',
        'renewal.prevUploads': 'Mga dating na-upload na dokumento:',
        'renewal.viewFile': 'Tingnan',
        'form.updateRenewal': 'I-update ang Renewal',
        'form.submitAllRenewals': 'Isumite ang Lahat ng Renewal',
        'modal.calendar': 'Kalendaryo',
        'modal.calendar.coming': 'Malapit na ang kalendaryo',
        'profile.darkModeTitle': 'Dark mode',
        'profile.darkModeAria': 'I-toggle ang dark mode',
        'form.docsAttached': '{{filled}} sa {{total}} na requirements ang naka-attach.'
      }
    };

    function renewalT(key) {
      var lang = localStorage.getItem('preferredLanguage') || 'en';
      var pack = (window.RENEWAL_I18N && window.RENEWAL_I18N[lang]) || null;
      if (pack && pack[key]) return pack[key];
      var G = window.APPLICATION_I18N;
      var app = G && (G[lang] || G.en);
      return (app && app[key]) || '';
    }

    function changeLanguage(lang) {
      localStorage.setItem('preferredLanguage', lang);
      if (typeof updateLanguageIcon === 'function') updateLanguageIcon(lang);
      translatePage(lang);
      var calModal = document.getElementById('calendarModal');
      if (calModal && calModal.classList.contains('show') && typeof renderCalendar === 'function') {
        renderCalendar();
      }
      window.dispatchEvent(new CustomEvent('menu-lang-change', { detail: { lang: lang } }));
    }

    function translatePage(lang) {
      var ren = (window.RENEWAL_I18N && window.RENEWAL_I18N[lang]) || {};
      var app = (window.APPLICATION_I18N && window.APPLICATION_I18N[lang]) || {};
      var pack = Object.assign({}, app, ren);
      document.querySelectorAll('[data-translate]').forEach(function (el) {
        var key = el.getAttribute('data-translate');
        if (!key || key.indexOf('nav.') === 0) return;
        if (pack[key]) el.textContent = pack[key];
      });
      document.querySelectorAll('[data-translate-title]').forEach(function (el) {
        var key = el.getAttribute('data-translate-title');
        if (!key || key.indexOf('nav.') === 0) return;
        if (pack[key]) el.title = pack[key];
      });
      document.querySelectorAll('[data-translate-aria]').forEach(function (el) {
        var key = el.getAttribute('data-translate-aria');
        if (!key || key.indexOf('nav.') === 0) return;
        if (pack[key]) el.setAttribute('aria-label', pack[key]);
      });
      if (document.documentElement) document.documentElement.lang = lang === 'ph' ? 'tl' : 'en';
    }

    function formatDocsAttachedText(filled, total) {
      var tpl = renewalT('form.docsAttached');
      if (!tpl || tpl === 'form.docsAttached') {
        tpl = '{{filled}} of {{total}} requirements attached.';
      }
      return tpl.replace(/\{\{filled\}\}/g, String(filled)).replace(/\{\{total\}\}/g, String(total));
    }

    function updateDocsAttachedCounters() {
      document.querySelectorAll('.app-docs-status').forEach(function (el) {
        var total = parseInt(el.getAttribute('data-total'), 10);
        if (isNaN(total)) total = 0;
        var scope = el.closest('.app-upload-docs');
        var filled = 0;
        if (scope) {
          scope.querySelectorAll('.req-check').forEach(function (c) {
            if (window.getComputedStyle(c).display !== 'none') filled += 1;
          });
        }
        el.textContent = formatDocsAttachedText(filled, total);
      });
    }

    function buildUploadDocsHTML(stallNumber) {
      var total = applicationRequirements ? applicationRequirements.length : 0;
      var statusId = stallNumber === 'edit' ? 'docsStatus_edit' : 'docsStatus_' + stallNumber;
      var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
      return '<div class="app-upload-docs">' +
        '<div class="app-card-head app-upload-docs-head">' +
          '<span class="app-card-icon" aria-hidden="true"><i class="fas fa-upload"></i></span>' +
          '<div>' +
            '<h3 class="app-upload-docs-title"><span data-translate="form.uploadDocs">' + escapeHtml(en['form.uploadDocs'] || 'Upload documents') + '</span> <span class="required">*</span></h3>' +
            '<p class="app-card-sub app-docs-status" id="' + statusId + '" data-total="' + total + '">' + formatDocsAttachedText(0, total) + '</p>' +
          '</div>' +
        '</div>' +
        '<div class="requirements-callout"><ul>' + generateRequirementsHTML(stallNumber) + '</ul></div>' +
      '</div>';
    }

    function generateRequirementsHTML(stallNumber) {
      if (!applicationRequirements || applicationRequirements.length === 0) {
        return '';
      }
      
      const reqTypes = ['id_card', 'dti', 'barangay', 'moa', 'lease', 'inspection', 'receipt'];
      const suffix = stallNumber === 'edit' ? 'edit' : stallNumber;
      const en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
      let html = '';
      
      applicationRequirements.forEach((req, idx) => {
        const reqType = reqTypes[idx] || 'other_' + idx;
        const checkId = 'check_' + reqType + '_' + suffix;
        const fileId = 'file_' + reqType + '_' + suffix;
        const fileName = stallNumber === 'edit' ? 'documents[]' : 'documents_' + stallNumber + '[]';
        var reqTitle = String(req.name || 'Document');
        var reqDesc = String(req.description || '');
        var needsStar = /clearance|receipt|id card|dti/i.test(reqTitle);
        html += '<li class="req-row">';
        html += '<div class="req-row-copy"><div>';
        html += '<span class="req-row-title">' + escapeHtml(reqTitle) + (needsStar ? ' <span class="required">*</span>' : '') + '</span>';
        if (reqDesc) html += '<span class="req-row-desc">' + escapeHtml(reqDesc) + '</span>';
        html += '</div></div>';
        html += '<div class="req-row-actions">';
        html += '<span id="' + checkId + '" class="req-check"><i class="fas fa-check" aria-hidden="true"></i></span>';
        html += '<button type="button" class="btn-add-file" onclick="document.getElementById(\'' + fileId + '\').click(); return false;">';
        html += '<i class="fas fa-plus"></i> <span data-translate="form.addFile">' + escapeHtml(en['form.addFile'] || 'Add file') + '</span>';
        html += '</button>';
        html += '</div>';
        html += '<input type="file" id="' + fileId + '" name="' + fileName + '" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display: none; position: absolute; left: -9999px;" onchange="handleFileUpload(this, \'' + stallNumber + '\', \'' + reqType + '\')">';
        html += '</li>';
      });
      
      return html;
    }
    
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    
    function removeFileItem(fileItemId, inputId, checkmarkId) {
      const fileItem = document.getElementById(fileItemId);
      if (fileItem) {
        fileItem.remove();
      }
      
      const input = document.getElementById(inputId);
      if (input) {
        input.value = '';
      }
      
      // Hide checkmark
      if (checkmarkId) {
        const checkmark = document.getElementById(checkmarkId);
        if (checkmark) {
          checkmark.style.display = 'none';
        }
      }
      
      // Hide file list if empty
      const fileList = fileItem ? fileItem.parentElement : null;
      if (fileList && fileList.querySelectorAll('.file-item').length === 0) {
        fileList.style.display = 'none';
      }
      if (typeof updateDocsAttachedCounters === 'function') {
        updateDocsAttachedCounters();
      }
    }

    // On the "My All Stalls" view, render renewal forms based on the user's current selection
    // (initially none selected, so the form starts empty until they pick a stall to renew).
    function clearRenewalIncompleteMarks(root) {
      root.querySelectorAll('.is-incomplete').forEach(function (el) {
        el.classList.remove('is-incomplete');
      });
    }

    function scrollToRenewalMissing(el) {
      if (!el) return;
      var target = (el.closest && el.closest('.req-row')) || el;
      try {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (err) {
        target.scrollIntoView(true);
      }
      setTimeout(function () {
        try { (el.focus ? el : target).focus({ preventScroll: true }); } catch (e2) {}
      }, 280);
    }

    function validateRenewalForm(root) {
      clearRenewalIncompleteMarks(root);
      var firstMissing = null;
      var docsMissing = false;

      root.querySelectorAll('input, select, textarea').forEach(function (el) {
        if (el.type === 'file' || el.type === 'hidden' || el.disabled) return;
        if (el.type === 'checkbox') {
          if (el.required && !el.checked) {
            el.classList.add('is-incomplete');
            if (!firstMissing) firstMissing = el;
          }
          return;
        }
        if (!el.required) return;
        if (String(el.value || '').trim() === '') {
          el.classList.add('is-incomplete');
          if (!firstMissing) firstMissing = el;
        }
      });

      var docRows = root.querySelectorAll('.requirements-callout li');
      if (!docRows.length) docRows = root.querySelectorAll('.req-row');
      docRows.forEach(function (row) {
        var input = row.querySelector('input[type="file"]');
        if (!input) return;
        var attached = input.files && input.files.length > 0;
        if (!attached) {
          docsMissing = true;
          row.classList.add('req-row');
          row.classList.add('is-incomplete');
          var addBtn = row.querySelector('.btn-add-file');
          if (!firstMissing) firstMissing = addBtn || row;
        }
      });

      if (firstMissing) {
        scrollToRenewalMissing(firstMissing);
        var msg = docsMissing && firstMissing.closest && firstMissing.closest('.req-row')
          ? 'Documents are not complete. Attach every requirement (all of them, for example 7 of 7), then submit again.'
          : 'Please complete the missing part before submitting.';
        alert(msg);
        return false;
      }
      return true;
    }

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM') return;
      if (!form.querySelector('[name="submit_application"]')) return;
      if (!validateRenewalForm(form)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);

    document.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.closest) return;
      var row = t.closest('.req-row');
      if (row && t.type === 'file' && t.files && t.files.length > 0) {
        row.classList.remove('is-incomplete');
      }
    });

    if (!<?php echo $editing ? 'true' : 'false'; ?>) {
      renderSelectedStalls();
    }
    if (typeof updateDocsAttachedCounters === 'function') {
      updateDocsAttachedCounters();
    }

    (function () {
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
        if (navbarOverlay) navbarOverlay.addEventListener('click', closeSidebar);
        document.addEventListener('click', function (event) {
          if (window.innerWidth <= 991) {
            var isClickInside = navbarToggler.contains(event.target) || navbarCenter.contains(event.target);
            if (!isClickInside && navbarCenter.classList.contains('show')) closeSidebar();
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
        document.querySelectorAll('#calendarIconLink, #calendarIconLinkDesktop').forEach(function (calLink) {
          calLink.addEventListener('click', function () {
            if (window.innerWidth <= 991) closeSidebar();
          });
        });
        window.addEventListener('resize', function () {
          if (window.innerWidth > 991) {
            navbarCenter.classList.remove('show');
            navbarToggler.setAttribute('aria-expanded', 'false');
            if (navbarOverlay) navbarOverlay.classList.remove('show');
            document.body.style.overflow = '';
          }
        });
      }
    })();

  </script>
  <?php
  require_once __DIR__ . '/includes/rating_helpers.php';
  $rating_source = 'renewal';
  $rating_already_done = dpmUserHasRated($user_id);
  include __DIR__ . '/includes/rating_modal.php';
  ?>
  <?php if (!empty($success_message) && !$rating_already_done): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var savedLang = localStorage.getItem('preferredLanguage') || 'en';
      var langSelectDesktop = document.getElementById('languageSelectDesktop');
      if (langSelectDesktop) {
        langSelectDesktop.value = savedLang;
        langSelectDesktop.addEventListener('change', function () {
          localStorage.setItem('preferredLanguage', this.value);
          if (typeof updateLanguageIcon === 'function') updateLanguageIcon(this.value);
          translatePage(this.value);
        });
      }
      translatePage(savedLang);
      if (typeof window.showRatingSuccessPopup === 'function') {
        setTimeout(function () { window.showRatingSuccessPopup('Renewal submitted successfully!'); }, 500);
      }
    });
  </script>
  <?php endif; ?>
  <?php
  $cs_contact_href = 'index.php#contact';
  $cs_announcement_href = 'index.php#announcement';
  $cs_show_menu_tools = true;
  include __DIR__ . '/includes/mobile_enhancements.php';
  include __DIR__ . '/includes/customer_support_widget.php';
  ?>
</body>
</html>

