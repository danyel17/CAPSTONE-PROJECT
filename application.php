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
/** @var array|null { key: string, args: array } for client-side PH/EN flash translation */
$success_flash = null;
$error_flash = null;

// Post-Redirect-Get: show any flash left by a previous submission, then clear it so a
// browser refresh does not re-submit the form (which previously re-triggered the
// "already approved" error on refresh).
if (!empty($_SESSION['application_flash_success'])) {
    $success_message = $_SESSION['application_flash_success'];
    $success_flash = $_SESSION['application_flash_success_data'] ?? null;
}
if (!empty($_SESSION['application_flash_error'])) {
    $error_message = $_SESSION['application_flash_error'];
    $error_flash = $_SESSION['application_flash_error_data'] ?? null;
}
unset(
    $_SESSION['application_flash_success'],
    $_SESSION['application_flash_success_data'],
    $_SESSION['application_flash_error'],
    $_SESSION['application_flash_error_data']
);

$editing = false;
$editing_vendor = null;
$editing_vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($editing_vendor_id > 0) {
    $conn = getDBConnection();
    if ($conn) {
        try {
            dpmEnsureStallColumns($conn);
            $stmt = $conn->prepare("SELECT vendor_id, vendor_name, section, block, status, stall_number FROM vendors WHERE vendor_id = ? AND user_id = ? AND application_type = 'new'");
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

$mkt_app_sections = [
    'Fresh Fish Section',
    'Meat and Chicken Section',
    'Dried and Salted Fish Section',
    'Fruits and Vegetables Section',
    'Dry Goods Section',
    'Groceries Sari-Sari Section',
    'Eateries and Cooked Food Section',
    'Service Section',
    'Special Area Section',
];
$mkt_app_blocks = [
    'Daet Elevated Plaza',
    'Market Tienda Bldg',
    'Block A',
    'Block B',
    'Block C',
    'BOT Block A-1',
    'BOT Block E',
    'BOT Behind Block B',
    'BOT Behind Block C',
    'BOT BLOCK A',
    'BOT BLOCK B',
    'BOT BLOCK C',
    'BOT BLOCK D',
    'BOT BLOCK E',
    'BOT BEHIND BLOCK C',
];

$prefill_section = '';
$prefill_block = '';
$prefill_slot = 0;
if (!$editing) {
    $get_section = isset($_GET['section']) ? trim((string) $_GET['section']) : '';
    $get_block = isset($_GET['block']) ? trim((string) $_GET['block']) : '';
    if ($get_section !== '' && in_array($get_section, $mkt_app_sections, true)) {
        $prefill_section = $get_section;
    }
    if ($get_block !== '' && in_array($get_block, $mkt_app_blocks, true)) {
        $prefill_block = $get_block;
    }
    if (isset($_GET['slot'])) {
        $prefill_slot = max(0, (int) $_GET['slot']);
    }
}

// Build stall-slot data for the client-side stall-number dropdown.
$stall_slot_js = [
    'map'        => dpmStallCanonicalMap(),
    'plazaCount' => 80,
    'occupied'   => [],
];
try {
    $ssConn = getDBConnection();
    if ($ssConn) {
        dpmEnsureStallColumns($ssConn);
        $stall_slot_js['occupied'] = dpmOccupiedSlotsByBlock($ssConn);
        $ssConn->close();
    }
} catch (Throwable $e) {
    // Non-fatal: dropdown will still render all slots as available.
}

// Extract first name only for navbar display
$first_name = explode(' ', trim($user_name))[0] ?? 'User';

$unread_notifications_count = getUnreadApprovalNotificationCount($user_id);

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
$application_requirements = getApplicationRequirements('new');

// Output requirements as JSON for JavaScript
$requirements_json = json_encode($application_requirements);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $posted_vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    
    $is_multiple = isset($_POST['full_name']) && is_array($_POST['full_name']);
    
    if ($is_multiple) {
        $full_names = $_POST['full_name'] ?? [];
        $sections = $_POST['section'] ?? [];
        $blocks = $_POST['block'] ?? [];
        $num_applications = count($full_names);
        
        if ($num_applications === 0) {
            $error_message = "Please fill in at least one application.";
            $error_flash = ['key' => 'flash.minOneApp', 'args' => []];
        } else {
            $all_valid = true;
            $submitted_count = 0;
            $application_ids = [];
            
            $conn = getDBConnection();
            if (!$conn) {
                $error_message = "Database connection failed. Please try again.";
                $error_flash = ['key' => 'flash.dbConn', 'args' => []];
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
                    dpmEnsureStallColumns($conn);
                    $posted_slots = $_POST['stall_number'] ?? [];
                    
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
                            $error_flash = ['key' => 'flash.docsIncomplete', 'args' => ['stall' => 'Stall ' . $pre_num, 'total' => (string) $required_docs]];
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
                $slot_number_val = (int) ($posted_slots[$i] ?? 0);
                $resolved_slot = dpmResolveStall($section, $block);
                $stall_block_val = $resolved_slot['db'] ?? null;
                
                if (empty($full_name) || empty($section) || empty($block)) {
                    $error_message = "Please fill in all required fields for Stall $stall_num.";
                    $all_valid = false;
                    continue;
                }
                if ($slot_number_val <= 0) {
                    $error_message = "Please choose a stall slot number for Stall $stall_num.";
                    $error_flash = ['key' => 'flash.stallNumberRequired', 'args' => ['stall' => (string) $stall_num]];
                    $all_valid = false;
                    continue;
                }
                
                $documents_key = 'documents_' . $stall_num;
                
                if (!$all_valid) continue;
                
                $application_id = 0;
                $conn->autocommit(false);
                
                try {
                    // Match the SPECIFIC physical stall (section + block + slot number), not just
                    // the block. A vendor may legitimately hold / apply for several different slots
                    // in the same block, so only the exact same slot should be treated as existing.
                    $check_stmt = $conn->prepare("
                        SELECT vendor_id, status FROM vendors 
                        WHERE user_id = ? AND application_type = 'new' AND section = ? AND block = ? AND stall_number = ?
                        ORDER BY submitted_date DESC LIMIT 1
                    ");
                    $check_stmt->bind_param("issi", $user_id, $section, $block, $slot_number_val);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $existing_vendor = $check_result->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($existing_vendor && ($existing_vendor['status'] ?? '') === 'approved') {
                        throw new Exception("Stall $stall_num: You already hold this exact slot (it's approved). Pick a different slot number if you want another stall.");
                    }
                    
                    if ($existing_vendor) {
                        $existing_id = $existing_vendor['vendor_id'];
                        // Do not overwrite approved rows (incomplete may be updated)
                        $stmt = $conn->prepare("
                            UPDATE vendors
                            SET vendor_name = ?, section = ?, block = ?, stall_number = ?, stall_block = ?, status = 'pending', reviewed_date = NULL, reviewed_by = NULL,
                                admin_notes = NULL, review_status = 'pending', is_reviewed = 0, submitted_date = NOW()
                            WHERE vendor_id = ? AND user_id = ? AND application_type = 'new'
                            AND status <> 'approved'
                        ");
                        $stmt->bind_param("sssisii", $full_name, $section, $block, $slot_number_val, $stall_block_val, $existing_id, $user_id);
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            $application_id = $existing_id;
                            dpmLogVendorResubmit($conn, $existing_id, $user_id, $existing_vendor['status'] ?? 'incomplete', 'application.php');
                        }
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO vendors (user_id, vendor_name, section, block, stall_number, stall_block, application_type, status, submitted_date) VALUES (?, ?, ?, ?, ?, ?, 'new', 'pending', NOW())");
                        $stmt->bind_param("isssis", $user_id, $full_name, $section, $block, $slot_number_val, $stall_block_val);
                        if ($stmt->execute()) {
                            $application_id = $stmt->insert_id;
                            error_log("SUCCESS: New application inserted - vendor_id=$application_id, user_id=$user_id, name='$full_name'");
                        } else {
                            error_log("ERROR: Failed to insert application - " . $stmt->error);
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
                    $error_flash = ['key' => 'flash.stallProcess', 'args' => ['stall' => (string) $stall_num, 'detail' => $e->getMessage()]];
                }
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
                            dpm_notify_submission_email('application', $mailTo, $mailName, [
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
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $block = trim($_POST['block'] ?? '');
        $slot_number_val = (int) ($_POST['stall_number'] ?? 0);
        $resolved_slot = dpmResolveStall($section, $block);
        $stall_block_val = $resolved_slot['db'] ?? null;
        
        if (empty($full_name) || empty($section) || empty($block)) {
            $error_message = "Please fill in all required fields.";
            $error_flash = ['key' => 'flash.requiredFields', 'args' => []];
        } elseif ($slot_number_val <= 0) {
            $error_message = "Please choose a stall slot number.";
            $error_flash = ['key' => 'flash.stallNumberRequired', 'args' => []];
        } elseif (dpm_count_uploaded_files('documents') < (count($application_requirements) > 0 ? count($application_requirements) : 7)) {
            $need_docs = count($application_requirements) > 0 ? count($application_requirements) : 7;
            $error_message = "The application was not submitted. Attach all $need_docs of $need_docs required documents.";
            $error_flash = ['key' => 'flash.docsIncomplete', 'args' => ['stall' => 'Your application', 'total' => (string) $need_docs]];
        } else {
        $conn = getDBConnection();
        if (!$conn) {
            $error_message = "Database connection failed. Please try again.";
            $error_flash = ['key' => 'flash.dbConn', 'args' => []];
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
        dpmEnsureStallColumns($conn);
        
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
                    WHERE vendor_id = ? AND user_id = ? AND application_type = 'new'
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
                    WHERE user_id = ? AND application_type = 'new' 
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
                    WHERE user_id = ? AND application_type = 'new' 
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
                    WHERE user_id = ? AND application_type = 'new'
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
                // Block only approved — incomplete must remain editable for resubmission
                $status_check_stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ? AND user_id = ? AND application_type = 'new'");
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
                    header("Location: application.php?error=already_reviewed");
                    exit;
                }
                
                dpmEnsureVendorResubmitTrigger($conn);

                // UPDATE existing application — pending or incomplete → reset to pending for fresh admin review
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
                    WHERE vendor_id = ? AND user_id = ? AND application_type = 'new' 
                    AND status <> 'approved'
                ");
                $stmt->bind_param("sssisii", $full_name, $section, $block, $slot_number_val, $stall_block_val, $existing_vendor_id, $user_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $application_id = $existing_vendor_id;
                        $was_updated = true;
                        dpmLogVendorResubmit($conn, $existing_vendor_id, $user_id, $status_row['status'] ?? 'incomplete', 'application.php');
                        error_log("SUCCESS: Application updated - vendor_id=$application_id, user_id=$user_id, status reset to pending");
                    } else {
                        error_log("WARNING: Update executed but no rows affected - vendor_id=$existing_vendor_id may not exist or already matches");
                        // Still set application_id to allow file uploads
                        $application_id = $existing_vendor_id;
                        $was_updated = true;
                    }
                } else {
                    error_log("ERROR: Failed to execute update - vendor_id=$existing_vendor_id, error=" . $stmt->error);
                }
                $stmt->close();
            } else {
                // CREATE new application ONLY if truly no existing one found
                // This should rarely happen if user has ever submitted an application
                $stmt = $conn->prepare("INSERT INTO vendors (user_id, vendor_name, section, block, stall_number, stall_block, application_type, status, submitted_date) VALUES (?, ?, ?, ?, ?, ?, 'new', 'pending', NOW())");
                $stmt->bind_param("isssis", $user_id, $full_name, $section, $block, $slot_number_val, $stall_block_val);
                if ($stmt->execute()) {
                    $application_id = $stmt->insert_id;
                    $was_updated = false;
                    error_log("NEW: Application created - vendor_id=$application_id, user_id=$user_id, name='$full_name', section='$section', block='$block' (no existing application found)");
                } else {
                    error_log("ERROR: Failed to create application - error=" . $stmt->error);
                }
                $stmt->close();
            }
            
            $conn->commit();
            $conn->autocommit(true);
            
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(true);
            error_log("Database error in application submission: " . $e->getMessage());
            $error_message = "Database error: " . $e->getMessage();
            $error_flash = ['key' => 'flash.dbError', 'args' => ['detail' => $e->getMessage()]];
            $application_id = 0;
        }
        
        if ($application_id > 0) {
            $upload_success = true;
            if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                $file_count = count($_FILES['documents']['name']);
                
                error_log("Processing $file_count file(s) for application ID: $application_id");
                
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
                            $error_flash = ['key' => 'flash.uploadFail', 'args' => ['file' => $file_name]];
                            $upload_success = false;
                            break;
                        }
                    }
                }
            }
            
            // Create notification for submit and update (visible in notification.php)
            if ($application_id > 0) {
                try {
                    $loc = trim($section) !== '' && trim($block) !== ''
                        ? trim($section) . ', Block ' . trim($block)
                        : (trim($section) !== '' ? trim($section) : (trim($block) !== '' ? 'Block ' . trim($block) : $full_name));
                    if ($was_updated) {
                        $notification_title = "Application Updated";
                        $notification_message = "Your application for {$loc} has been updated and is now pending review. Please wait for the approval.";
                    } else {
                        $notification_title = "Application Submitted";
                        $notification_message = "Your application for {$loc} has been submitted and is now pending review. Please wait for the approval.";
                    }
                    recordApprovalNotification(
                        $conn,
                        $user_id,
                        $application_id,
                        'pending_review',
                        $notification_title,
                        $notification_message,
                        'medium',
                        false,
                        'application.php'
                    );
                } catch (Exception $e) {
                    error_log("EXCEPTION: Failed to create application notification - " . $e->getMessage());
                }
            }
            
            if ($upload_success) {
                if ($was_updated) {
                    $success_message = "Application updated successfully! Application ID: #" . $application_id;
                    $success_flash = ['key' => 'flash.updatedOk', 'args' => ['id' => '#' . $application_id]];
                } else {
                    $success_message = "Application submitted successfully! Application ID: #" . $application_id;
                    $success_flash = ['key' => 'flash.submittedOk', 'args' => ['id' => '#' . $application_id]];
                }
                
                try {
                    $activity_type = $was_updated ? 'application_updated' : 'application_submitted';
                    $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address) VALUES (?, ?, ?, ?)");
                    $details = $was_updated ? "Updated application" : "You submitted new application";
                    $details .= ": $full_name - $section";
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $log_stmt->bind_param("isss", $user_id, $activity_type, $details, $ip_address);
                    $log_stmt->execute();
                    $log_stmt->close();
                } catch (Exception $e) {
                    error_log("Failed to log activity: " . $e->getMessage());
                }
                require_once __DIR__ . '/includes/submission_notify_email.php';
                $who = dpm_registered_recipient((int) $user_id);
                $mailTo = $who['email'] !== '' ? $who['email'] : trim((string) $user_email);
                $mailName = $who['name'] !== '' ? $who['name'] : (string) $user_name;
                if ($mailTo !== '') {
                    dpm_notify_submission_email('application', $mailTo, $mailName, [
                        'action' => $was_updated ? 'updated' : 'submitted',
                        'application_ids' => '#' . $application_id,
                    ]);
                }
            }
            
            } else {
                $error_message = "Failed to submit application. Please try again.";
                $error_flash = ['key' => 'flash.submitFail', 'args' => []];
            }
            
            $conn->close();
            } catch (Exception $e) {
                error_log("Error processing single application: " . $e->getMessage());
                $error_message = "An error occurred while processing your application. Please try again.";
                $error_flash = ['key' => 'flash.genericError', 'args' => []];
                if ($conn) {
                    $conn->close();
                }
            }
        }
        }
    }

    // Post-Redirect-Get: stash the result in the session and reload as a GET request so
    // refreshing the page after submitting does NOT resubmit the form (which previously
    // re-triggered the "This application was already approved" error).
    $_SESSION['application_flash_success'] = $success_message;
    $_SESSION['application_flash_success_data'] = $success_flash;
    $_SESSION['application_flash_error'] = $error_message;
    $_SESSION['application_flash_error_data'] = $error_flash;
    header('Location: application.php');
    exit;
}

// After redirect: show error when update was blocked (approved applications cannot be overwritten)
if (!empty($_SESSION['error_message'])) {
    if (empty($error_message)) {
        $error_message = $_SESSION['error_message'];
        $error_flash = ['key' => 'flash.alreadyReviewed', 'args' => []];
    }
    unset($_SESSION['error_message']);
} elseif (empty($error_message) && isset($_GET['error']) && $_GET['error'] === 'already_reviewed') {
    $error_message = 'Cannot update application: This application has already been approved by an administrator.';
    $error_flash = ['key' => 'flash.alreadyReviewed', 'args' => []];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $editing ? 'Edit Application' : 'New Application - Daet Public Market'; ?></title>
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
    
    .nav-icon-badge {
      position: absolute;
      top: -5px;
      left: calc(1rem + 1rem + 0.5rem + 5.5rem);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background-color: #ef4444;
      color: #fff;
      font-size: 11px;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
      font-weight: 700;
    }

    /* Pin badge to bell icon (notification.php offset was for a different header layout) */
    .nav-icon-pill .nav-icon-badge {
      left: auto;
      right: 0;
      top: 2px;
      transform: translate(35%, -35%);
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
      max-width: 700px;
      margin: 1rem auto 2rem;
      padding: 0 1rem;
      width: 100%;
      flex: 1;
      color: #1e293b;
    }

    .alert {
      border-radius: 10px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Center the application content for a cleaner, focused layout */
    main {
      width: 100%;
      max-width: 880px;
      margin: 0 auto;
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
    /* ——— Application form UI (cards only; page bg / nav unchanged) ——— */
    /* Light mode defaults: white cards + light liquid glass fields */
    .app-form-ui {
      --app-card: #ffffff;
      --app-card-border: rgba(255, 255, 255, 0.55);
      --app-field: rgba(255, 255, 255, 0.42);
      --app-field-border: rgba(255, 255, 255, 0.65);
      --app-text: #1c1c1e;
      --app-muted: #3a3a3c;
      --app-orange: #ff6b35;
      --app-orange-soft: rgba(255, 107, 53, 0.16);
      --app-glass-blur: blur(18px);
      --app-glass-shadow: 0 10px 28px rgba(46, 35, 108, 0.1);
      max-width: 760px;
      margin: 0 auto;
      width: 100%;
    }
    body.darkmode .app-form-ui {
      --app-card: #0b0b0d;
      --app-card-border: rgba(255, 255, 255, 0.1);
      --app-field: rgba(40, 40, 44, 0.72);
      --app-field-border: rgba(255, 255, 255, 0.14);
      --app-text: #ffffff;
      --app-muted: rgba(255, 255, 255, 0.72);
      --app-orange-soft: rgba(255, 107, 53, 0.2);
      --app-glass-shadow: 0 14px 34px rgba(0, 0, 0, 0.35);
    }
    .app-form-ui .accounts-header-application {
      align-items: flex-start;
      margin-bottom: 1.35rem;
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
      color: var(--app-orange);
      font-style: italic;
    }
    .app-upload-docs {
      margin-top: 0.35rem;
    }
    .app-upload-docs-head {
      margin-bottom: 1.05rem;
    }
    .app-upload-docs-head h3 {
      margin: 0;
      color: var(--app-text);
      font-size: 1.2rem;
      font-weight: 700;
      line-height: 1.25;
      border: none;
      padding: 0;
    }
    .app-upload-docs-head .required {
      color: var(--app-orange);
      font-weight: 700;
    }
    body.darkmode .app-form-ui .app-upload-docs-head .required {
      color: var(--app-orange) !important;
    }
    .accounts-title .app-title-icon { display: none; }
    .accounts-subtitle {
      margin: 0.85rem 0 0;
      color: rgba(255, 255, 255, 0.78);
      font-size: 0.98rem;
      line-height: 1.6;
      max-width: 36rem;
    }
    .app-completion {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      margin: 1.35rem 0 1.75rem;
    }
    .app-completion__label,
    .app-completion__pct {
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: #ffffff;
      flex-shrink: 0;
    }
    .app-completion__track {
      flex: 1;
      height: 2px;
      background: rgba(255, 255, 255, 0.22);
      border-radius: 99px;
      overflow: hidden;
    }
    .app-completion__bar {
      height: 100%;
      width: 0%;
      background: var(--app-orange);
      border-radius: 99px;
      transition: width 0.35s ease;
    }

    .form-box {
      position: relative;
      background: var(--app-card);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      padding: 1.65rem 1.6rem 1.75rem;
      border-radius: 22px;
      border: 1px solid var(--app-card-border);
      box-shadow: 0 12px 32px rgba(46, 35, 108, 0.12);
      animation: formBoxIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
      overflow: hidden;
      transition: box-shadow 0.3s ease, background 0.25s ease, border-color 0.25s ease;
      margin-bottom: 1.15rem;
      color: var(--app-text);
    }
    .form-box:hover {
      box-shadow: 0 16px 40px rgba(46, 35, 108, 0.16);
    }
    body.darkmode .form-box {
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
    }
    body.darkmode .form-box:hover {
      box-shadow: 0 22px 48px rgba(0, 0, 0, 0.48);
    }
    .form-box::before { display: none; }
    .form-box > * { position: relative; z-index: 1; }
    @keyframes formBoxIn {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .app-card-head {
      display: flex;
      align-items: flex-start;
      gap: 0.9rem;
      margin-bottom: 1.45rem;
    }
    .app-card-icon {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: var(--app-orange-soft);
      color: var(--app-orange);
      display: grid;
      place-items: center;
      flex-shrink: 0;
      font-size: 1rem;
    }
    .app-card-head h3,
    .stall-section-heading {
      margin: 0;
      color: var(--app-text);
      font-size: 1.2rem;
      font-weight: 700;
      line-height: 1.25;
      border: none;
      padding: 0;
      display: block;
    }
    .stall-section-heading i,
    .stall-section-heading .me-2 { display: none; }
    .app-card-sub {
      margin: 0.28rem 0 0;
      color: var(--app-muted);
      font-size: 0.88rem;
      line-height: 1.45;
    }

    .app-form-ui label,
    .app-field-label {
      display: block;
      margin-bottom: 0.45rem;
      color: var(--app-muted);
      font-weight: 700;
      font-size: 0.68rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .required {
      color: #f87171;
    }

    .app-form-ui input[type="text"],
    .app-form-ui input[type="number"],
    .app-form-ui select,
    .app-form-ui .stall-field,
    .app-form-ui select.stall-field,
    .app-form-ui input.stall-field[type="number"] {
      width: 100%;
      padding: 0.9rem 1rem;
      margin-bottom: 1.15rem;
      border: 1px solid var(--app-field-border);
      border-radius: 14px;
      font-size: 0.95rem;
      color: var(--app-text) !important;
      -webkit-text-fill-color: var(--app-text);
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
      background: var(--app-field);
      backdrop-filter: var(--app-glass-blur);
      -webkit-backdrop-filter: var(--app-glass-blur);
      box-shadow: var(--app-glass-shadow);
      font-family: inherit;
    }

    .app-form-ui input[type="text"]::placeholder,
    .app-form-ui input[type="number"]::placeholder {
      color: #8e8e93;
      -webkit-text-fill-color: #8e8e93;
    }

    .app-form-ui input[type="text"]:hover,
    .app-form-ui input[type="number"]:hover,
    .app-form-ui select:hover {
      border-color: rgba(255, 107, 53, 0.35);
    }

    .app-form-ui input[type="text"]:focus,
    .app-form-ui input[type="number"]:focus,
    .app-form-ui select:focus {
      outline: none;
      border-color: rgba(255, 107, 53, 0.55);
      box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.15), var(--app-glass-shadow);
    }

    .app-form-ui select,
    .app-form-ui select.stall-field,
    .app-form-ui #num_stalls {
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%231c1c1e' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      background-color: transparent;
      padding-right: 2.6rem;
    }
    body.darkmode .app-form-ui select,
    body.darkmode .app-form-ui select.stall-field,
    body.darkmode .app-form-ui #num_stalls {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    }
    .app-form-ui select option {
      background: #ffffff;
      color: #1c1c1e;
    }
    body.darkmode .app-form-ui select option {
      background: #1c1c1e;
      color: #ffffff;
    }
    .app-form-ui .is-incomplete {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18) !important;
    }
    .app-form-ui .req-row.is-incomplete,
    .app-form-ui .requirements-callout li.is-incomplete,
    .app-form-ui .req-row:last-child.is-incomplete,
    body:not(.darkmode) .app-form-ui .req-row.is-incomplete,
    body:not(.darkmode) .app-form-ui .requirements-callout li.is-incomplete,
    body.darkmode .app-form-ui .req-row.is-incomplete,
    body.darkmode .app-form-ui .requirements-callout li.is-incomplete {
      background: #fee2e2 !important;
      box-shadow: inset 0 0 0 2px #ef4444 !important;
      border-radius: 12px !important;
      padding: 0.85rem 0.7rem !important;
      margin: 0.35rem 0 !important;
    }
    body.darkmode .app-form-ui .req-row.is-incomplete,
    body.darkmode .app-form-ui .requirements-callout li.is-incomplete,
    body.darkmode .app-form-ui .req-row:last-child.is-incomplete {
      background: rgba(239, 68, 68, 0.42) !important;
      box-shadow: inset 0 0 0 2px #fca5a5 !important;
    }

    .app-field-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.85rem 1rem;
    }
    .app-field-grid > div { min-width: 0; }
    .app-field-grid input,
    .app-field-grid select { margin-bottom: 1.15rem; }
    @media (max-width: 640px) {
      .app-field-grid { grid-template-columns: 1fr; }
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
      padding: 1rem;
      background: var(--app-field);
      border: 1px solid var(--app-field-border);
      border-radius: 14px;
      backdrop-filter: var(--app-glass-blur);
      -webkit-backdrop-filter: var(--app-glass-blur);
    }

    .file-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.75rem 0.9rem;
      margin-bottom: 0.55rem;
      background: rgba(128, 128, 128, 0.08);
      border-radius: 10px;
      border: 1px solid var(--app-field-border);
      font-size: 0.88rem;
      color: var(--app-text);
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

    .submit-all-btn:hover:not(.submit-all-footer-btn) {
      background: #7c8ef0 !important;
      color: white !important;
      transform: translateY(-3px) !important;
      box-shadow: 0 6px 20px rgba(124, 142, 240, 0.4) !important;
    }

    .form-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    /* Stall picker + requirements */
    .stall-row {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }
    .label-stall {
      font-size: 0.68rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--app-muted);
      margin-bottom: 0.45rem;
      font-weight: 700;
    }
    .label-stall i { display: none; }
    .app-form-ui .stall-field,
    .app-form-ui select.stall-field,
    .app-form-ui input.stall-field[type="number"] {
      flex: 1;
      min-width: 200px;
      margin-bottom: 0;
    }
    .stall-hint {
      margin-top: 0.75rem;
      margin-bottom: 0;
      color: var(--app-muted);
      font-size: 0.84rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .stall-hint i { color: var(--app-muted); }

    .requirements-callout {
      background: var(--app-field);
      backdrop-filter: var(--app-glass-blur);
      -webkit-backdrop-filter: var(--app-glass-blur);
      border: 1px solid var(--app-field-border);
      border-left: none;
      padding: 0.15rem 1.1rem;
      margin-bottom: 1.1rem;
      border-radius: 16px;
      box-shadow: var(--app-glass-shadow);
    }
    .requirements-callout-title {
      display: none;
    }
    .requirements-callout ul {
      margin: 0;
      padding-left: 0;
      list-style: none;
      color: var(--app-text);
      font-size: 0.92rem;
    }
    .req-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      gap: 0.85rem;
    }
    .req-row:last-child { border-bottom: none; }
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
      color: var(--app-text);
      font-weight: 700;
      font-size: 0.95rem;
      line-height: 1.35;
    }
    .req-row-desc {
      display: block;
      color: var(--app-muted);
      font-size: 0.8rem;
      line-height: 1.4;
      margin-top: 0.18rem;
    }
    .req-row > span {
      flex: 1;
      min-width: 0;
      line-height: 1.45;
      color: var(--app-text);
    }
    .req-row-actions {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      flex-shrink: 0;
    }
    .req-check {
      display: none;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      min-width: 24px;
      min-height: 24px;
      background: rgba(34, 197, 94, 0.2);
      color: #4ade80;
      border-radius: 50%;
      font-size: 0.7rem;
      font-weight: bold;
      flex-shrink: 0;
      line-height: 1;
      padding: 0;
      box-sizing: border-box;
    }
    .req-check i {
      display: block;
      line-height: 1;
      font-size: 0.65rem;
    }
    .btn-add-file {
      background: var(--app-field);
      backdrop-filter: var(--app-glass-blur);
      -webkit-backdrop-filter: var(--app-glass-blur);
      color: var(--app-text) !important;
      border: 1px solid var(--app-field-border);
      padding: 0.5rem 0.95rem;
      border-radius: 999px;
      cursor: pointer;
      font-size: 0.82rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      transition: all 0.2s ease;
      white-space: nowrap;
      box-shadow: var(--app-glass-shadow);
    }
    .btn-add-file:hover {
      background: rgba(255, 107, 53, 0.12);
      color: var(--app-text) !important;
      border-color: rgba(255, 107, 53, 0.4);
    }
    .file-list-heading {
      margin-bottom: 1rem;
      color: var(--app-text);
      font-weight: 700;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 0.45rem;
    }
    .file-list-heading i { color: var(--app-orange); }
    .file-total-line {
      margin-top: 0.75rem;
      margin-bottom: 0;
      color: var(--app-muted);
      font-size: 0.88rem;
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
    .btn-remove-selected {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.2);
      padding: 0.35rem 0.7rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.83rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      transition: all 0.2s ease;
    }
    .btn-remove-selected:hover {
      background: #ef4444;
      color: #ffffff;
      border-color: #ef4444;
    }

    .app-confirm-row {
      display: flex;
      align-items: flex-start;
      gap: 0.7rem;
      margin: 0.35rem 0 1.35rem;
      color: rgba(255, 255, 255, 0.92);
      font-size: 0.92rem;
      line-height: 1.5;
    }
    /* Confirm sits on indigo page background, not inside the white card */
    body:not(.darkmode) .app-confirm-row {
      color: rgba(255, 255, 255, 0.95);
    }
    body:not(.darkmode) .app-form-ui .app-confirm-row,
    body:not(.darkmode) .app-form-ui .app-confirm-row label {
      color: rgba(255, 255, 255, 0.95) !important;
      -webkit-text-fill-color: rgba(255, 255, 255, 0.95) !important;
    }
    .app-confirm-row input[type="checkbox"] {
      width: 18px;
      height: 18px;
      margin-top: 0.15rem;
      flex-shrink: 0;
      accent-color: #ff6b35;
      cursor: pointer;
    }
    .app-confirm-row label {
      margin: 0;
      text-transform: none;
      letter-spacing: 0;
      font-size: 0.92rem;
      font-weight: 500;
      color: inherit !important;
      cursor: pointer;
    }
    .app-rules-link {
      color: inherit;
      font-weight: 700;
      text-decoration: underline;
      text-underline-offset: 2px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      font: inherit;
    }
    .app-rules-link:hover { color: #ff6b35; }

    .app-rules-modal[hidden] { display: none !important; }
    .app-rules-modal {
      position: fixed;
      inset: 0;
      z-index: 12000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      background: rgba(15, 23, 42, 0.35);
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.22s ease, visibility 0.22s ease;
    }
    body.darkmode .app-rules-modal {
      background: rgba(10, 14, 28, 0.62);
    }
    .app-rules-modal.is-open {
      opacity: 1;
      visibility: visible;
    }
    .app-rules-modal__panel {
      width: min(520px, 100%);
      max-height: min(86vh, 640px);
      overflow-y: auto;
      background: #ffffff;
      border: 1px solid rgba(15, 23, 42, 0.1);
      border-radius: 20px;
      padding: 1.6rem 1.5rem 1.4rem;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
      color: #0f172a;
      position: relative;
      transform: scale(0.94) translateY(10px);
      transition: transform 0.22s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }
    body.darkmode .app-rules-modal__panel {
      background: #0b0b0d;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
      color: #ffffff;
    }
    .app-rules-modal.is-open .app-rules-modal__panel {
      transform: scale(1) translateY(0);
    }
    .app-rules-modal__close {
      position: absolute;
      top: 0.85rem;
      right: 0.85rem;
      width: 36px;
      height: 36px;
      border: none;
      border-radius: 50%;
      background: rgba(15, 23, 42, 0.06);
      color: #0f172a;
      font-size: 1.25rem;
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: background 0.2s ease, color 0.2s ease;
    }
    body.darkmode .app-rules-modal__close {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
    }
    .app-rules-modal__close:hover { background: rgba(255, 107, 53, 0.2); }
    body.darkmode .app-rules-modal__close:hover { background: rgba(255, 107, 53, 0.35); }
    .app-rules-modal__panel h3 {
      font-family: 'DM Serif Display', Georgia, serif;
      font-weight: 400;
      font-size: 1.55rem;
      color: #0f172a;
      margin: 0 2rem 0.75rem 0;
    }
    body.darkmode .app-rules-modal__panel h3 {
      color: #ffffff;
    }
    .app-rules-modal__panel p {
      color: #475569;
      font-size: 0.9rem;
      line-height: 1.55;
      margin: 0 0 0.85rem;
    }
    body.darkmode .app-rules-modal__panel p {
      color: rgba(255, 255, 255, 0.72);
    }
    .app-rules-modal__panel ul {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 0.55rem;
    }
    .app-rules-modal__panel li {
      display: flex;
      gap: 0.55rem;
      align-items: flex-start;
      font-size: 0.9rem;
      line-height: 1.45;
      color: #1e293b;
    }
    body.darkmode .app-rules-modal__panel li {
      color: #ffffff;
    }
    .app-rules-modal__panel li i {
      color: #ff6b35;
      margin-top: 0.2rem;
      font-size: 0.75rem;
    }

    /** Primary submit — orange pill like mock */
    .submit-all-footer-btn {
      position: relative;
      margin-top: 0.25rem;
      width: auto !important;
      max-width: 100%;
      min-width: min(300px, 100%);
      background: linear-gradient(135deg, #ff7a45 0%, #ff6b35 48%, #f35a22 100%) !important;
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      color: #ffffff !important;
      border: 1px solid rgba(255, 255, 255, 0.22) !important;
      font-family: 'Inter', system-ui, sans-serif;
      font-weight: 700;
      font-size: 1rem;
      padding: 1.05rem 1.85rem;
      box-shadow: 0 10px 28px rgba(255, 107, 53, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.25);
      transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
      cursor: pointer;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      overflow: hidden;
      letter-spacing: 0.02em;
    }
    .submit-all-footer-btn::before { display: none; }
    .submit-all-footer-btn i,
    .submit-all-footer-btn span {
      position: relative;
      z-index: 1;
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    .submit-all-footer-btn .submit-all-footer-btn__text {
      font-family: 'DM Serif Display', Georgia, serif;
      font-weight: 400;
      font-size: 1.12rem;
      letter-spacing: 0.01em;
      line-height: 1.1;
    }
    .submit-all-footer-btn i {
      font-size: 0.95rem;
      opacity: 0.95;
    }
    .submit-all-footer-btn:hover {
      background: #ff8555 !important;
      transform: translateY(-2px);
      border-color: transparent !important;
      box-shadow: 0 14px 32px rgba(255, 107, 53, 0.45);
    }
    .app-form-footer {
      margin-top: 0.5rem;
      margin-bottom: 1.5rem;
    }

    .form-box-stall-picker {
      margin-bottom: 1.75rem;
    }

    /* Dark mode — page background stays indigo; panels go dark (same idea as notification.php) */
    body.darkmode {
      background-color: #667eea;
    }
    body.darkmode main {
      color: #e2e8f0;
    }
    body.page-application.darkmode main .app-completion__label,
    body.page-application.darkmode main .app-completion__pct,
    body.page-application.darkmode main .app-confirm-row,
    body.page-application.darkmode main .app-confirm-row label,
    body.page-application.darkmode main .app-confirm-row span,
    body.page-application.darkmode main .app-confirm-row .app-rules-link {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.darkmode .accounts-title {
      color: #f8fafc;
    }
    body.darkmode .accounts-subtitle {
      color: #cbd5e1;
    }
    body.darkmode .app-form-ui .form-box {
      background-color: var(--app-card) !important;
      border-color: var(--app-card-border) !important;
      color: #ffffff !important;
    }
    body.darkmode .app-form-ui .form-box::before { display: none; }
    body.darkmode .app-form-ui label,
    body.darkmode .app-form-ui .label-stall,
    body.darkmode .app-form-ui .app-card-sub,
    body.darkmode .app-form-ui .stall-hint,
    body.darkmode .app-form-ui .req-row-desc {
      color: rgba(255, 255, 255, 0.72) !important;
    }
    body.darkmode .app-form-ui .required { color: #fca5a5; }
    body.darkmode .app-form-ui .stall-section-heading,
    body.darkmode .app-form-ui .app-card-head h3,
    body.darkmode .app-form-ui .req-row-title,
    body.darkmode .app-form-ui .req-row > span,
    body.darkmode .app-form-ui .btn-add-file,
    body.darkmode .app-form-ui input[type="text"],
    body.darkmode .app-form-ui input[type="number"],
    body.darkmode .app-form-ui select {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff;
    }
    body.darkmode .app-form-ui input[type="text"],
    body.darkmode .app-form-ui input[type="number"],
    body.darkmode .app-form-ui select,
    body.darkmode .app-form-ui .stall-field,
    body.darkmode .app-form-ui #num_stalls,
    body.darkmode .app-form-ui #custom_stalls,
    body.darkmode .app-form-ui .requirements-callout,
    body.darkmode .app-form-ui .btn-add-file {
      background: var(--app-field) !important;
      border-color: var(--app-field-border) !important;
      backdrop-filter: var(--app-glass-blur) !important;
      -webkit-backdrop-filter: var(--app-glass-blur) !important;
      box-shadow: var(--app-glass-shadow) !important;
    }
    body.darkmode .app-form-ui .requirements-callout {
      border-left: none !important;
    }
    body.darkmode .app-form-ui .req-row {
      border-bottom-color: rgba(255, 255, 255, 0.1);
    }
    body.darkmode .app-form-ui .req-check {
      background: rgba(34, 197, 94, 0.22);
      color: #4ade80;
    }
    body.darkmode .app-form-ui .file-list {
      background: rgba(40, 40, 44, 0.72);
      border-color: rgba(255, 255, 255, 0.12);
    }
    body.darkmode .app-form-ui .file-item {
      color: #ffffff;
    }
    body.darkmode .app-form-ui .app-confirm-row,
    body.darkmode .app-form-ui .app-confirm-row label {
      color: rgba(255, 255, 255, 0.92) !important;
    }
    body.darkmode .submit-all-footer-btn {
      background: #ff6b35 !important;
      color: #ffffff !important;
      border: none !important;
      box-shadow: 0 10px 28px rgba(255, 107, 53, 0.35) !important;
    }
    body.darkmode .submit-all-footer-btn i,
    body.darkmode .submit-all-footer-btn span {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.darkmode .submit-all-footer-btn:hover {
      background: #ff8555 !important;
      transform: translateY(-2px);
      box-shadow: 0 14px 32px rgba(255, 107, 53, 0.45) !important;
    }

    /* Light mode: white cards + charcoal text (overrides vendor-light-mode-text.css) */
    body:not(.darkmode) .app-form-ui .form-box {
      background: #ffffff !important;
      color: #1c1c1e !important;
      border-color: rgba(255, 255, 255, 0.65) !important;
    }
    body:not(.darkmode) .app-form-ui label,
    body:not(.darkmode) .app-form-ui .label-stall,
    body:not(.darkmode) .app-form-ui .app-card-sub,
    body:not(.darkmode) .app-form-ui .stall-hint,
    body:not(.darkmode) .app-form-ui .req-row-desc {
      color: #3a3a3c !important;
      -webkit-text-fill-color: #3a3a3c !important;
    }
    body:not(.darkmode) .app-form-ui .stall-section-heading,
    body:not(.darkmode) .app-form-ui .app-card-head h3,
    body:not(.darkmode) .app-form-ui .req-row-title,
    body:not(.darkmode) .app-form-ui .req-row > span,
    body:not(.darkmode) .app-form-ui .btn-add-file,
    body:not(.darkmode) .app-form-ui input[type="text"],
    body:not(.darkmode) .app-form-ui input[type="number"],
    body:not(.darkmode) .app-form-ui select {
      color: #1c1c1e !important;
      -webkit-text-fill-color: #1c1c1e !important;
    }
    body:not(.darkmode) .app-form-ui input[type="text"],
    body:not(.darkmode) .app-form-ui input[type="number"],
    body:not(.darkmode) .app-form-ui select,
    body:not(.darkmode) .app-form-ui .stall-field,
    body:not(.darkmode) .app-form-ui #num_stalls,
    body:not(.darkmode) .app-form-ui #custom_stalls,
    body:not(.darkmode) .app-form-ui .requirements-callout,
    body:not(.darkmode) .app-form-ui .btn-add-file {
      background: rgba(255, 255, 255, 0.42) !important;
      border-color: rgba(255, 255, 255, 0.65) !important;
      backdrop-filter: blur(18px) !important;
      -webkit-backdrop-filter: blur(18px) !important;
      box-shadow: 0 10px 28px rgba(46, 35, 108, 0.1) !important;
    }
    body:not(.darkmode) .app-form-ui .file-list {
      background: rgba(255, 255, 255, 0.55) !important;
      border-color: rgba(0, 0, 0, 0.08) !important;
    }
    body:not(.darkmode) .app-form-ui .file-item {
      background: rgba(255, 255, 255, 0.7) !important;
      color: #1c1c1e !important;
    }

    /* Calendar modal: keep same frosted light card as profile.php (no dark purple override) */
    body.darkmode .file-upload-label {
      background: rgba(31, 41, 55, 0.65);
      border-color: #818cf8;
      color: #e2e8f0;
    }
    body.darkmode .file-upload-label:hover {
      background: rgba(55, 65, 81, 0.85);
    }
    body.darkmode .file-upload-label i {
      color: #a5b4fc;
    }
    body.darkmode .file-list {
      background: rgba(31, 41, 55, 0.88) !important;
      border-color: rgba(255, 255, 255, 0.1);
      color: #e2e8f0;
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

      .file-upload-label {
        padding: 1.5rem;
      }

      .file-upload-label i {
        font-size: 2.5rem;
      }

      input[type="text"],
      select {
        padding: 0.7rem 0.85rem;
        font-size: 0.95rem;
      }

      .stall-field,
      select.stall-field,
      input.stall-field[type="number"] {
        min-width: 100%;
      }

      .form-btn {
        padding: 0.875rem;
        font-size: 0.95rem;
      }
    }

    @media (max-width: 576px) {
      main {
        padding: 0 0.5rem;
        margin: 0.75rem auto;
      }

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

      .req-row {
        flex-wrap: wrap;
      }
      .req-row-actions {
        width: 100%;
        justify-content: flex-end;
      }
      .btn-remove-stall-file {
        margin-left: 0;
      }

      .file-upload-label {
        padding: 1rem;
      }

      .file-upload-label i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
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

    @media (max-width: 480px) {
      .accounts-title {
        font-size: 1.2rem;
      }

      .form-box {
        padding: 0.75rem;
      }

      .file-upload-label {
        padding: 0.75rem;
      }
    }
  </style>
  <link rel="stylesheet" href="assets/css/vendor-light-mode-text.css?v=app-hero-copy-1">
  <style>
    body:not(.darkmode) .app-form-ui .accounts-title {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body:not(.darkmode) .app-form-ui .accounts-title .app-title-accent {
      color: #ff6b35 !important;
      -webkit-text-fill-color: #ff6b35 !important;
    }
    body:not(.darkmode) .app-form-ui .accounts-subtitle {
      color: rgba(255, 255, 255, 0.78) !important;
      -webkit-text-fill-color: rgba(255, 255, 255, 0.78) !important;
    }
    body.page-application.darkmode .app-form-ui .accounts-subtitle {
      color: #cbd5e1 !important;
      -webkit-text-fill-color: #cbd5e1 !important;
    }
    body.page-application .app-completion__label,
    body.page-application .app-completion__pct,
    body.page-application.darkmode .app-completion__label,
    body.page-application.darkmode .app-completion__pct {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.page-application .app-form-ui .app-confirm-row,
    body.page-application .app-form-ui .app-confirm-row label,
    body.page-application.darkmode .app-form-ui .app-confirm-row,
    body.page-application.darkmode .app-form-ui .app-confirm-row label {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
    body.darkmode .main-container main .submit-all-footer-btn,
    body.darkmode .main-container main .submit-all-footer-btn i,
    body.darkmode .main-container main .submit-all-footer-btn span {
      color: #ffffff !important;
      -webkit-text-fill-color: #ffffff !important;
    }
  </style>
  <link rel="stylesheet" href="assets/css/vendor-mobile-sidebar-logout.css">
  <link rel="stylesheet" href="assets/css/vendor-navbar-glass.css?v=nav-icon-phone-1">
  <link rel="stylesheet" href="assets/css/vendor-nav-theme-lock.css?v=1">
  <link rel="stylesheet" href="assets/css/vendor-portal-shell.css?v=1">
  <link rel="stylesheet" href="assets/css/menu-landing-nav.css?v=menu-nav-lock-4">
  <link rel="stylesheet" href="assets/css/application-page-theme.css?v=3">
  <link rel="stylesheet" href="assets/css/vendor-portal-dark-nav-pills.css?v=phone-glass-4">
</head>
<body class="page-application" data-application-mode="<?php echo $editing ? 'edit' : 'new'; ?>">
  <script>try{if(localStorage.getItem('darkmode')==='active')document.body.classList.add('darkmode');}catch(e){}</script>
  <div class="main-container">
    <?php $vendor_nav_active = 'application'; include __DIR__ . '/includes/vendor_portal_navbar.php'; ?>

  <main>
    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert"<?php if (!empty($success_flash)): ?> data-flash-key="<?php echo htmlspecialchars($success_flash['key'], ENT_QUOTES, 'UTF-8'); ?>" data-flash-args="<?php echo htmlspecialchars(json_encode($success_flash['args'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
        <i class="fas fa-check-circle me-2"></i><span class="application-flash-text"><?php echo htmlspecialchars($success_message); ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert"<?php if (!empty($error_flash)): ?> data-flash-key="<?php echo htmlspecialchars($error_flash['key'], ENT_QUOTES, 'UTF-8'); ?>" data-flash-args="<?php echo htmlspecialchars(json_encode($error_flash['args'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
        <i class="fas fa-exclamation-circle me-2"></i><span class="application-flash-text"><?php echo htmlspecialchars($error_message); ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="app-form-ui">
    <div class="accounts-header accounts-header-application">
      <div class="accounts-header-text">
        <h1 class="accounts-title">
          <i class="fas fa-file-alt app-title-icon me-2"></i>
          <?php if ($editing): ?>
            <span id="applicationPageTitle" data-translate="page.titleEdit">Edit Application</span>
          <?php else: ?>
            <span id="applicationPageTitle"><span data-translate="page.titleNewLead">New</span> <span class="app-title-accent" data-translate="page.titleNewAccent">application</span></span>
          <?php endif; ?>
        </h1>
        <p class="accounts-subtitle" id="applicationPageSubtitle"><?php echo $editing ? 'Update your existing application details below.' : 'Fill out the form below to apply for market stalls. The admin will reviews every application, thanks for you patient :)'; ?></p>
      </div>
      <div class="accounts-header-right">
        <button type="button" class="profile-darkmode-btn" id="theme-switch" data-translate-title="profile.darkModeTitle" data-translate-aria="profile.darkModeAria" title="Dark mode" aria-label="Toggle dark mode">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-moon"><path d="M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Z"/></svg>
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-sun"><path d="M480-280q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Z"/></svg>
        </button>
      </div>
    </div>

    <div class="app-completion" aria-label="Form completion">
      <span class="app-completion__label" data-translate="form.completion">Completion</span>
      <div class="app-completion__track"><div class="app-completion__bar" id="appCompletionBar"></div></div>
      <span class="app-completion__pct" id="appCompletionPct">0%</span>
    </div>

    <?php if (!$editing): ?>
    <div class="form-box form-box-stall-picker">
      <div class="app-card-head">
        <span class="app-card-icon" aria-hidden="true"><i class="fas fa-th-large"></i></span>
        <div>
          <h3 data-translate="stall.cardTitle">Select number of stalls</h3>
          <p class="app-card-sub" data-translate="stall.cardSub">Choose how many pitches to apply for.</p>
        </div>
      </div>
      <label for="num_stalls" class="label-stall">
        <span data-translate="stall.fieldLabel">Number of stalls</span>
      </label>
      <div class="stall-row">
        <select id="num_stalls" name="num_stalls" class="stall-field" onchange="handleStallSelection()">
          <option value="1" selected data-translate="stall.n1">1 Stall</option>
          <option value="2" data-translate="stall.n2">2 Stalls</option>
          <option value="3" data-translate="stall.n3">3 Stalls</option>
          <option value="4" data-translate="stall.n4">4 Stalls</option>
          <option value="5" data-translate="stall.n5">5 Stalls</option>
          <option value="custom" data-translate="stall.custom">or more (custom)</option>
        </select>
        <input type="number" id="custom_stalls" name="custom_stalls" class="stall-field" min="1" max="20" value="1"
               style="display: none;"
               oninput="updateStallForms()" onchange="updateStallForms()" data-translate-placeholder="stall.placeholder" placeholder="Type number of stalls">
      </div>
      <p class="stall-hint">
        <span data-translate="stall.hint">Choose number of stall or slot.</span>
      </p>
    </div>
    <?php endif; ?>

    <div id="formsContainer">
    <?php if ($editing): ?>
      <form method="POST" action="application.php<?php echo $editing_vendor_id ? '?vendor_id=' . $editing_vendor_id : ''; ?>" enctype="multipart/form-data">
        <div class="form-box">
        <input type="hidden" name="vendor_id" value="<?php echo (int)$editing_vendor_id; ?>">
        <div class="app-card-head">
          <span class="app-card-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
          <div>
            <h3 data-translate="form.editHeading">Edit application</h3>
            <p class="app-card-sub" data-translate="form.editSub">Update pitch details for this stall.</p>
          </div>
        </div>
        <label for="full_name"><span data-translate="form.fullName">Full Name</span> <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($editing_vendor['vendor_name']); ?>" required placeholder="e.g. Daria Fennel">

        <div class="app-field-grid">
          <div>
            <label for="section"><span data-translate="form.section">Section</span> <span class="required">*</span></label>
            <select id="section" name="section" required>
              <option value="" data-translate="form.selectSection">Select a section</option>
              <?php $current_section = $editing_vendor['section']; ?>
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
          </div>
          <div>
            <label for="block"><span data-translate="form.preferredBlock">Preferred Block</span> <span class="required">*</span></label>
            <?php $current_block = $editing_vendor['block']; ?>
            <select id="block" name="block" required onchange="appRefreshStallFieldEditing()">
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
              <option value="BOT BLOCK A" <?php echo $current_block === 'BOT BLOCK A' ? 'selected' : ''; ?>>BOT BLOCK A</option>
              <option value="BOT BLOCK B" <?php echo $current_block === 'BOT BLOCK B' ? 'selected' : ''; ?>>BOT BLOCK B</option>
              <option value="BOT BLOCK C" <?php echo $current_block === 'BOT BLOCK C' ? 'selected' : ''; ?>>BOT BLOCK C</option>
              <option value="BOT BLOCK D" <?php echo $current_block === 'BOT BLOCK D' ? 'selected' : ''; ?>>BOT BLOCK D</option>
              <option value="BOT BLOCK E" <?php echo $current_block === 'BOT BLOCK E' ? 'selected' : ''; ?>>BOT BLOCK E</option>
              <option value="BOT BEHIND BLOCK C" <?php echo $current_block === 'BOT BEHIND BLOCK C' ? 'selected' : ''; ?>>BOT BEHIND BLOCK C</option>
            </select>
          </div>
        </div>

        <label for="stall_number"><span data-translate="form.stallNumber">Stall Slot Number</span> <span class="required">*</span></label>
        <select id="stall_number" name="stall_number" required data-current-slot="<?php echo (int) ($editing_vendor['stall_number'] ?? 0); ?>">
          <option value="">Select a stall number</option>
        </select>

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
                <button type="button" class="btn-add-file" onclick="document.getElementById('file_<?php echo $req_type_edit; ?>_edit').click()">
                  <i class="fas fa-plus"></i> <span data-translate="form.addFile">Add file</span>
                </button>
              </div>
              <input type="file" id="file_<?php echo $req_type_edit; ?>_edit" name="documents[]" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display: none;" onchange="handleFileUpload(this, 'edit', '<?php echo $req_type_edit; ?>')">
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div id="fileList_edit" class="file-list" style="display: none; margin-top: 1rem;"></div>
        </div>
        </div>

        <div class="app-form-footer">
          <div class="app-confirm-row">
            <input type="checkbox" id="appConfirmRules" required>
            <label for="appConfirmRules">
              <span data-translate="form.confirmLead">I confirm the details above are correct and agree to the </span><button type="button" class="app-rules-link" id="openAppRules" data-translate="form.rulesWord">market rules</button><span>.</span>
            </label>
          </div>
          <button type="submit" name="submit_application" class="form-btn submit-all-btn submit-all-footer-btn">
            <span class="submit-all-footer-btn__text" data-translate="form.updateApplication">Update application</span> <i class="fas fa-arrow-right" aria-hidden="true"></i>
          </button>
        </div>
      </form>
    <?php else: ?>
      <form method="POST" action="application.php" enctype="multipart/form-data" id="applicationForm">
      </form>
    <?php endif; ?>
    </div>
    </div><!-- /.app-form-ui -->
  </main>

  <div class="app-rules-modal" id="appRulesModal" role="dialog" aria-modal="true" aria-labelledby="appRulesTitle" hidden>
    <div class="app-rules-modal__panel">
      <button type="button" class="app-rules-modal__close" id="closeAppRules" aria-label="Close">&times;</button>
      <h3 id="appRulesTitle" data-translate="rules.title">Market rules</h3>
      <p data-translate="rules.intro">By submitting an application you agree to follow Daet Public Market policies under the Revised Market Code of 2017 and related local ordinances.</p>
      <ul>
        <li><i class="fas fa-check"></i> <span data-translate="rules.r1">Provide complete and truthful information and required documents.</span></li>
        <li><i class="fas fa-check"></i> <span data-translate="rules.r2">Occupy only the stall or slot assigned by the Market Division.</span></li>
        <li><i class="fas fa-check"></i> <span data-translate="rules.r3">Keep your stall clean, safe, and free of prohibited items or hazards.</span></li>
        <li><i class="fas fa-check"></i> <span data-translate="rules.r4">Pay rental, fees, and clearances on time as required by the office.</span></li>
        <li><i class="fas fa-check"></i> <span data-translate="rules.r5">Follow market hours, inspection findings, and lawful directives from staff.</span></li>
        <li><i class="fas fa-check"></i> <span data-translate="rules.r6">Violations may lead to denial, suspension, or cancellation of trading privileges.</span></li>
      </ul>
    </div>
  </div>
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
            <!-- Calendar will be rendered here (same as profile.php) -->
            <p style="text-align: center; color: rgba(71, 85, 105, 0.7);" data-translate="modal.calendar.coming">Calendar functionality coming soon</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    function changeLanguage(lang) {
      localStorage.setItem('preferredLanguage', lang);
      updateLanguageIcon(lang);
      translatePage(lang);
      var calModal = document.getElementById('calendarModal');
      if (calModal && calModal.classList.contains('show')) {
        renderCalendar();
      }
      if (typeof window.applicationOnLangChange === 'function') {
        window.applicationOnLangChange(lang);
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

    window.APPLICATION_I18N = {
      en: {
        'nav.logoTitle': 'Daet Public Market',
        'nav.home': 'Home',
        'nav.application': 'Application',
        'nav.renewal': 'Renewal',
        'nav.profile': 'Profile',
        'nav.notifications': 'Notifications',
        'nav.logout': 'Logout',
        'page.titleNew': 'New application',
        'page.titleNewLead': 'New',
        'page.titleNewAccent': 'application',
        'page.titleEdit': 'Edit application',
        'page.subtitleNew': 'Fill out the form below to apply for market stalls. The admin will reviews every application, thanks for you patient :)',
        'page.subtitleEdit': 'Update your existing application details below.',
        'page.docTitleNew': 'New Application - Daet Public Market',
        'page.docTitleEdit': 'Edit Application - Daet Public Market',
        'confirm.logout': 'Are you sure you want to logout?',
        'stall.label': 'Select Number of Stalls',
        'stall.cardTitle': 'Select number of stalls',
        'stall.cardSub': 'Choose how many pitches to apply for.',
        'stall.fieldLabel': 'Number of stalls',
        'stall.hint': 'Choose number of stall or slot.',
        'stall.placeholder': 'Type number of stalls',
        'stall.n1': '1 stall',
        'stall.n2': '2 stalls',
        'stall.n3': '3 stalls',
        'stall.n4': '4 stalls',
        'stall.n5': '5 stalls',
        'stall.custom': 'or more (custom)',
        'form.fullName': 'Full Name',
        'form.section': 'Section',
        'form.block': 'Block',
        'form.preferredBlock': 'Preferred Block',
        'form.stallNumber': 'Stall Slot Number',
        'form.uploadDocs': 'Upload documents',
        'form.docsAttached': '{{filled}} of {{total}} requirements attached.',
        'form.requirements': 'Requirements:',
        'form.addFile': 'Add file',
        'form.remove': 'Remove',
        'form.selectSection': 'Select a section.',
        'form.selectBlock': 'Select a Block',
        'form.updateApplication': 'Update application',
        'form.submitAll': 'Submit all applications',
        'form.uploadedFiles': 'Uploaded Files:',
        'form.selectedFiles': 'Selected Files',
        'form.total': 'Total:',
        'form.stallHeading': 'Stall {{n}} application',
        'form.stallSub': 'Pitch details for this stall.',
        'form.completion': 'Completion',
        'form.confirmLead': 'I confirm the details above are correct and agree to the ',
        'form.rulesWord': 'market rules',
        'form.editHeading': 'Edit application',
        'form.editSub': 'Update pitch details for this stall.',
        'form.fullNamePh': 'e.g. Daria Fennel',
        'rules.title': 'Market rules',
        'rules.intro': 'By submitting an application you agree to follow Daet Public Market policies under the Revised Market Code of 2017 and related local ordinances.',
        'rules.r1': 'Provide complete and truthful information and required documents.',
        'rules.r2': 'Occupy only the stall or slot assigned by the Market Division.',
        'rules.r3': 'Keep your stall clean, safe, and free of prohibited items or hazards.',
        'rules.r4': 'Pay rental, fees, and clearances on time as required by the office.',
        'rules.r5': 'Follow market hours, inspection findings, and lawful directives from staff.',
        'rules.r6': 'Violations may lead to denial, suspension, or cancellation of trading privileges.',
        'alert.confirmRules': 'Please confirm that you agree to the market rules before submitting.',
        'alert.incompleteForm': 'Please complete all required fields and upload documents before submitting.',
        'alert.docsIncomplete': 'Documents are not complete. Attach every requirement (all of them, for example 7 of 7), then submit again.',
        'flash.docsIncomplete': '{stall} was not submitted. Attach all {total} of {total} required documents.',
        'opt.freshFish': 'Fresh Fish Section',
        'opt.meat': 'Meat and Chicken Section',
        'opt.driedFish': 'Dried and Salted Fish Section',
        'opt.produce': 'Fruits and Vegetables Section',
        'opt.dryGoods': 'Dry Goods Section',
        'opt.sariSari': 'Groceries Sari-Sari Section',
        'opt.eateries': 'Eateries and Cooked Food Section',
        'opt.service': 'Service Section',
        'opt.special': 'Special Area Section',
        'blk.plaza': 'Daet Elevated Plaza',
        'blk.tienda': 'Market Tienda Bldg',
        'blk.a': 'Block A',
        'blk.b': 'Block B',
        'blk.c': 'Block C',
        'blk.botA1': 'BOT Block A-1',
        'blk.botE': 'BOT Block E',
        'blk.behindB': 'BOT Behind Block B',
        'blk.behindC': 'BOT Behind Block C',
        'modal.calendar': 'Calendar',
        'modal.calendar.coming': 'Calendar functionality coming soon',
        'modal.close': 'Close',
        'profile.darkModeTitle': 'Dark mode',
        'profile.darkModeAria': 'Toggle dark mode',
        'alert.maxStalls': 'Maximum 20 stalls allowed per application.',
        'alert.fileSize': 'File size must be less than 5MB',
        'form.filesWord': 'files',
        'req.id_card': 'ID Card',
        'req.dti': 'DTI Registration',
        'req.barangay': 'Barangay Clearance',
        'req.moa': 'Memorandum of Agreement',
        'req.lease': 'Lease Contract',
        'req.inspection': 'Stall Inspection',
        'req.receipt': 'Official Receipt',
        'req.document': 'Document',
        'flash.minOneApp': 'Please fill in at least one application.',
        'flash.dbConn': 'Database connection failed. Please try again.',
        'flash.stallRequired': 'Please fill in all required fields for Stall {stall}.',
        'flash.stallProcess': 'Error processing Stall {stall}: {detail}',
        'flash.appsProcessError': 'An error occurred while processing your applications. Please try again.',
        'flash.requiredFields': 'Please fill in all required fields.',
        'flash.uploadDoc': 'Please upload at least one document. Documents are required to pass the application.',
        'flash.alreadyReviewed': 'Cannot update application: This application has already been approved by an administrator.',
        'flash.dbError': 'Database error: {detail}',
        'flash.fileType': 'Invalid file type. Allowed: JPG, PNG, PDF, DOC, DOCX',
        'flash.fileSize5': 'File size must be less than 5MB',
        'flash.uploadFail': 'Failed to upload file: {file}',
        'flash.submitFail': 'Failed to submit application. Please try again.',
        'flash.genericError': 'An error occurred while processing your application. Please try again.',
        'flash.multiSubmitOk': 'Successfully submitted {count} application(s)! Application ID(s): {ids}',
        'flash.updatedOk': 'Application updated successfully! Application ID: {id}',
        'flash.submittedOk': 'Application submitted successfully! Application ID: {id}'
      },
      ph: {
        'nav.logoTitle': 'Daet Public Market',
        'nav.home': 'Tahanan',
        'nav.application': 'Aplikasyon',
        'nav.renewal': 'Pag-renew',
        'nav.profile': 'Profile',
        'nav.notifications': 'Mga Abiso',
        'nav.logout': 'Mag-logout',
        'page.titleNew': 'Bagong aplikasyon',
        'page.titleNewLead': 'Bagong',
        'page.titleNewAccent': 'aplikasyon',
        'page.titleEdit': 'I-edit ang aplikasyon',
        'page.subtitleNew': 'Punan ang form sa ibaba para sa market clearance. Sinusuri ng allotment office ang bawat aplikasyon at sumasagot sa loob ng dalawang araw ng trabaho.',
        'page.subtitleEdit': 'I-update ang mga detalye ng iyong aplikasyon sa ibaba.',
        'page.docTitleNew': 'Bagong Aplikasyon - Daet Public Market',
        'page.docTitleEdit': 'I-edit ang Aplikasyon - Daet Public Market',
        'confirm.logout': 'Sigurado ka bang gusto mong mag-logout?',
        'stall.label': 'Pumili ng bilang ng stall',
        'stall.cardTitle': 'Pumili ng bilang ng stall',
        'stall.cardSub': 'Piliin kung ilang pitch ang ia-apply.',
        'stall.fieldLabel': 'Bilang ng stall',
        'stall.hint': 'Pumili ng bilang ng stall o slot.',
        'stall.placeholder': 'Ilagay ang bilang ng stall',
        'stall.n1': '1 stall',
        'stall.n2': '2 stall',
        'stall.n3': '3 stall',
        'stall.n4': '4 stall',
        'stall.n5': '5 stall',
        'stall.custom': 'o higit pa (custom)',
        'form.fullName': 'Buong pangalan',
        'form.section': 'Section',
        'form.block': 'Block',
        'form.preferredBlock': 'Preferred Block',
        'form.stallNumber': 'Numero ng Stall Slot',
        'form.uploadDocs': 'Mag-upload ng dokumento',
        'form.docsAttached': '{{filled}} sa {{total}} na kinakailangang dokumento ang naka-attach.',
        'form.requirements': 'Mga kinakailangan:',
        'form.addFile': 'Magdagdag ng file',
        'form.remove': 'Alisin',
        'form.selectSection': 'Pumili ng seksyon.',
        'form.selectBlock': 'Pumili ng block',
        'form.updateApplication': 'I-update ang aplikasyon',
        'form.submitAll': 'Isumite ang lahat ng aplikasyon',
        'form.uploadedFiles': 'Mga na-upload na file:',
        'form.selectedFiles': 'Mga napiling file',
        'form.total': 'Kabuuan:',
        'form.stallHeading': 'Aplikasyon sa Stall {{n}}',
        'form.stallSub': 'Detalye ng pitch para sa stall na ito.',
        'form.completion': 'Completion',
        'form.confirmLead': 'Kinukumpirma kong tama ang mga detalye at sumasang-ayon ako sa ',
        'form.rulesWord': 'mga patakaran ng pamilihan',
        'form.editHeading': 'I-edit ang aplikasyon',
        'form.editSub': 'I-update ang detalye ng pitch para sa stall na ito.',
        'form.fullNamePh': 'hal. Daria Fennel',
        'rules.title': 'Mga patakaran ng pamilihan',
        'rules.intro': 'Sa pagsusumite ng aplikasyon, sumasang-ayon kang sundin ang mga patakaran ng Daet Public Market ayon sa Revised Market Code of 2017.',
        'rules.r1': 'Magbigay ng kumpleto at totooong impormasyon at kinakailangang dokumento.',
        'rules.r2': 'Gamitin lamang ang stall o slot na itinalaga ng Market Division.',
        'rules.r3': 'Panatilihing malinis at ligtas ang stall.',
        'rules.r4': 'Bayaran ang renta at bayarin sa takdang oras.',
        'rules.r5': 'Sundin ang oras ng pamilihan at mga direksyon ng staff.',
        'rules.r6': 'Ang paglabag ay maaaring magresulta sa pagtanggi o pagsuspinde ng pribilehiyo.',
        'alert.confirmRules': 'Pakikumpirma muna ang pagsang-ayon sa mga patakaran ng pamilihan bago magsumite.',
        'alert.incompleteForm': 'Paki-kumpleto ang lahat ng required fields at mag-upload ng dokumento bago magsumite.',
        'alert.docsIncomplete': 'Hindi kumpleto ang mga dokumento. I-attach ang bawat requirement (halimbawa 7 sa 7), saka magsumite ulit.',
        'flash.docsIncomplete': 'Hindi naisumite ang {stall}. I-attach ang lahat ng {total} sa {total} na dokumento.',
        'opt.freshFish': 'Fresh Fish Section',
        'opt.meat': 'Meat and Chicken Section',
        'opt.driedFish': 'Dried and Salted Fish Section',
        'opt.produce': 'Fruits and Vegetables Section',
        'opt.dryGoods': 'Dry Goods Section',
        'opt.sariSari': 'Groceries Sari-Sari Section',
        'opt.eateries': 'Eateries and Cooked Food Section',
        'opt.service': 'Service Section',
        'opt.special': 'Special Area Section',
        'blk.plaza': 'Daet Elevated Plaza',
        'blk.tienda': 'Market Tienda Bldg',
        'blk.a': 'Block A',
        'blk.b': 'Block B',
        'blk.c': 'Block C',
        'blk.botA1': 'BOT Block A-1',
        'blk.botE': 'BOT Block E',
        'blk.behindB': 'BOT Behind Block B',
        'blk.behindC': 'BOT Behind Block C',
        'modal.calendar': 'Kalendaryo',
        'modal.calendar.coming': 'Darating ang kalendaryo sa lalong madaling panahon.',
        'modal.close': 'Isara',
        'profile.darkModeTitle': 'Madilim na mode',
        'profile.darkModeAria': 'Lumipat sa madilim / maliwanag na mode',
        'alert.maxStalls': 'Pinakamataas na 20 stall bawat aplikasyon.',
        'alert.fileSize': 'Ang laki ng file ay dapat mas mababa sa 5MB.',
        'form.filesWord': 'na file',
        'req.id_card': 'ID Card',
        'req.dti': 'Rehistro sa DTI',
        'req.barangay': 'Barangay Clearance',
        'req.moa': 'Memorandum of Agreement',
        'req.lease': 'Kontrata sa Paupahan',
        'req.inspection': 'Inspeksyon ng Stall',
        'req.receipt': 'Opisyal na Resibo',
        'req.document': 'Dokumento',
        'flash.minOneApp': 'Mangyaring punan ang kahit isang aplikasyon.',
        'flash.dbConn': 'Nabigo ang koneksyon sa database. Pakisubukan muli.',
        'flash.stallRequired': 'Mangyaring punan ang lahat ng kinakailangang field para sa Stall {stall}.',
        'flash.stallProcess': 'Error sa pagproseso ng Stall {stall}: {detail}',
        'flash.appsProcessError': 'May naganap na error habang pinoproseso ang iyong mga aplikasyon. Pakisubukan muli.',
        'flash.requiredFields': 'Mangyaring punan ang lahat ng kinakailangang field.',
        'flash.uploadDoc': 'Mangyaring mag-upload ng kahit isang dokumento. Kinakailangan ang mga dokumento para maipasa ang aplikasyon.',
        'flash.alreadyReviewed': 'Hindi ma-update ang aplikasyon: Naaprubahan na ito ng administrator.',
        'flash.dbError': 'Error sa database: {detail}',
        'flash.fileType': 'Hindi wastong uri ng file. Pinapayagan: JPG, PNG, PDF, DOC, DOCX',
        'flash.fileSize5': 'Ang laki ng file ay dapat mas mababa sa 5MB.',
        'flash.uploadFail': 'Nabigong i-upload ang file: {file}',
        'flash.submitFail': 'Nabigong isumite ang aplikasyon. Pakisubukan muli.',
        'flash.genericError': 'May naganap na error habang pinoproseso ang iyong aplikasyon. Pakisubukan muli.',
        'flash.multiSubmitOk': 'Matagumpay na naisumite ang {count} na aplikasyon! Application ID(s): {ids}',
        'flash.updatedOk': 'Matagumpay na na-update ang aplikasyon! Application ID: {id}',
        'flash.submittedOk': 'Matagumpay na naisumite ang aplikasyon! Application ID: {id}'
      }
    };

    function applyFlashI18n(lang) {
      var translations = window.APPLICATION_I18N;
      if (!translations) return;
      var texts = translations[lang] || translations.en;
      var fallback = translations.en || {};
      document.querySelectorAll('[data-flash-key]').forEach(function (el) {
        var key = el.getAttribute('data-flash-key');
        if (!key) return;
        var tpl = texts[key] || fallback[key];
        if (!tpl) return;
        var args = {};
        try {
          args = JSON.parse(el.getAttribute('data-flash-args') || '{}');
        } catch (e) {
          args = {};
        }
        var out = tpl.replace(/\{(\w+)\}/g, function (_, name) {
          return args[name] != null ? String(args[name]) : '';
        });
        var span = el.querySelector('.application-flash-text');
        if (span) span.textContent = out;
      });
    }

    function translatePage(lang) {
      var translations = window.APPLICATION_I18N;
      var texts = translations[lang] || translations.en;
      var fallback = translations.en || {};
      document.querySelectorAll('[data-translate]').forEach(function (element) {
        var key = element.getAttribute('data-translate');
        if (!key || key.indexOf('nav.') === 0) return;
        if (texts[key]) element.textContent = texts[key];
        else if (fallback[key]) element.textContent = fallback[key];
      });
      document.querySelectorAll('[data-translate-stall]').forEach(function (element) {
        var key = element.getAttribute('data-translate-stall');
        var n = element.getAttribute('data-stall-n') || '';
        var tpl = (texts[key] || fallback[key]);
        if (tpl) element.textContent = tpl.replace(/\{\{n\}\}/g, n);
      });
      document.querySelectorAll('[data-translate-title]').forEach(function (element) {
        var key = element.getAttribute('data-translate-title');
        if (!key || key.indexOf('nav.') === 0) return;
        if (texts[key]) element.title = texts[key];
      });
      document.querySelectorAll('[data-translate-aria]').forEach(function (element) {
        var key = element.getAttribute('data-translate-aria');
        if (texts[key]) element.setAttribute('aria-label', texts[key]);
      });
      document.querySelectorAll('[data-translate-placeholder]').forEach(function (el) {
        var key = el.getAttribute('data-translate-placeholder');
        if (texts[key]) el.setAttribute('placeholder', texts[key]);
      });
      var mode = document.body ? document.body.getAttribute('data-application-mode') : '';
      var pt = document.getElementById('applicationPageTitle');
      var ps = document.getElementById('applicationPageSubtitle');
      if (pt && texts['page.titleEdit']) {
        if (mode === 'edit') {
          pt.textContent = texts['page.titleEdit'];
        } else if (texts['page.titleNewLead'] && texts['page.titleNewAccent']) {
          pt.innerHTML = '<span data-translate="page.titleNewLead">' + texts['page.titleNewLead'] + '</span> <span class="app-title-accent" data-translate="page.titleNewAccent">' + texts['page.titleNewAccent'] + '</span>';
        } else if (texts['page.titleNew']) {
          pt.textContent = texts['page.titleNew'];
        }
      }
      if (ps && texts['page.subtitleNew'] && texts['page.subtitleEdit']) {
        ps.textContent = mode === 'edit' ? texts['page.subtitleEdit'] : texts['page.subtitleNew'];
      }
      var calendarComing = document.querySelector('[data-translate="modal.calendar.coming"]');
      if (calendarComing && texts['modal.calendar.coming']) calendarComing.textContent = texts['modal.calendar.coming'];
      document.querySelectorAll('#calendarIconLink, #calendarIconLinkDesktop').forEach(function (el) {
        if (texts['modal.calendar']) el.title = texts['modal.calendar'];
      });
      if (texts['page.docTitleNew'] && texts['page.docTitleEdit']) {
        document.title = mode === 'edit' ? texts['page.docTitleEdit'] : texts['page.docTitleNew'];
      }
      if (document.documentElement) {
        document.documentElement.lang = lang === 'ph' ? 'tl' : 'en';
      }
      applyFlashI18n(lang);
    }

    function confirmLogout() {
      var lang = localStorage.getItem('preferredLanguage') || 'en';
      var G = window.APPLICATION_I18N;
      var pack = G && (G[lang] || G.en);
      var msg = (pack && pack['confirm.logout']) || (G && G.en && G.en['confirm.logout']) || 'Are you sure you want to logout?';
      return confirm(msg);
    }

    var monthNamesEn = ['January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'];
    var monthNamesPh = ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo',
      'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
    var dayNamesEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var dayNamesPh = ['Li', 'Lu', 'Ma', 'Mi', 'Hu', 'Bi', 'Sa'];

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
      var i, j;
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
      if (calendarContainer) calendarContainer.innerHTML = calendarHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
      var savedLang = localStorage.getItem('preferredLanguage') || 'en';
      var langSelect = document.getElementById('languageSelect');
      var langSelectDesktop = document.getElementById('languageSelectDesktop');
      if (langSelect) langSelect.value = savedLang;
      if (langSelectDesktop) langSelectDesktop.value = savedLang;
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
        langSelect.addEventListener('change', function () { changeLanguage(this.value); });
      } else if (langSelectDesktop) {
        langSelectDesktop.addEventListener('change', function () { changeLanguage(this.value); });
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
          navbarOverlay.addEventListener('click', function () { closeSidebar(); });
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
        calendarModal.addEventListener('show.bs.modal', function () { renderCalendar(); });
        calendarModal.addEventListener('hidden.bs.modal', function () {
          document.body.style.overflow = '';
          document.body.classList.remove('modal-open');
          document.body.style.paddingRight = '';
        });
      }
    });

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
  </script>
  <script>
    var IS_EDITING_PAGE = <?php echo $editing ? 'true' : 'false'; ?>;
    var STALL_MAP_PREFILL = <?php echo json_encode([
        'section' => $prefill_section,
        'block' => $prefill_block,
        'slot' => $prefill_slot,
    ], JSON_UNESCAPED_UNICODE); ?>;
    var STALL_SLOT_DATA = <?php echo json_encode($stall_slot_js, JSON_UNESCAPED_UNICODE); ?>;

    // Resolve a section/block to its map block id + slot count (mirrors PHP dpmResolveStall).
    function appResolveStall(section, block) {
      section = (section || '').trim();
      block = (block || '').trim();
      if (block === 'Daet Elevated Plaza') {
        return { db: 'Daet Elevated Plaza', count: STALL_SLOT_DATA.plazaCount || 80 };
      }
      var map = STALL_SLOT_DATA.map || {};
      var key = section + '||' + block;
      if (map[key]) return map[key];
      for (var k in map) {
        if (map.hasOwnProperty(k) && k.split('||')[0] === section) return map[k];
      }
      return null;
    }

    // Build <option>s for the stall-number dropdown. Occupied slots are disabled.
    function appStallOptionsHtml(section, block, selectedSlot) {
      var resolved = appResolveStall(section, block);
      if (!resolved) {
        return '<option value="">Select section &amp; block first</option>';
      }
      var occupied = (STALL_SLOT_DATA.occupied && STALL_SLOT_DATA.occupied[resolved.db]) || [];
      var occSet = {};
      occupied.forEach(function (n) { occSet[parseInt(n, 10)] = true; });
      var sel = parseInt(selectedSlot, 10) || 0;
      var html = '<option value="">Select a stall number</option>';
      for (var n = 1; n <= resolved.count; n++) {
        var taken = occSet[n] && n !== sel;
        html += '<option value="' + n + '"' +
          (n === sel ? ' selected' : '') +
          (taken ? ' disabled' : '') + '>Stall ' + n + (taken ? ' (occupied)' : '') + '</option>';
      }
      if (sel > resolved.count) {
        html += '<option value="' + sel + '" selected>Stall ' + sel + '</option>';
      }
      return html;
    }

    // Rebuild a stall dropdown when its section/block changes.
    function appRefreshStallField(idx) {
      var sec = document.getElementById('section_' + idx);
      var blk = document.getElementById('block_' + idx);
      var stall = document.getElementById('stall_number_' + idx);
      if (!sec || !blk || !stall) return;
      var current = stall.value;
      stall.innerHTML = appStallOptionsHtml(sec.value, blk.value, current);
    }
    window.appRefreshStallField = appRefreshStallField;

    // Editing form (single, fixed-id fields).
    function appRefreshStallFieldEditing() {
      var sec = document.getElementById('section');
      var blk = document.getElementById('block');
      var stall = document.getElementById('stall_number');
      if (!sec || !blk || !stall) return;
      var current = parseInt(stall.value, 10) || parseInt(stall.getAttribute('data-current-slot'), 10) || 0;
      stall.innerHTML = appStallOptionsHtml(sec.value, blk.value, current);
    }
    window.appRefreshStallFieldEditing = appRefreshStallFieldEditing;
    document.addEventListener('DOMContentLoaded', function () {
      var secEl = document.getElementById('section');
      if (secEl) secEl.addEventListener('change', appRefreshStallFieldEditing);
      if (document.getElementById('stall_number')) appRefreshStallFieldEditing();
    });
    function appT(key) {
      var lang = localStorage.getItem('preferredLanguage') || 'en';
      var G = window.APPLICATION_I18N;
      if (!G) return key;
      var pack = G[lang] || G.en;
      return pack[key] || G.en[key] || key;
    }

    function escapeHtml(text) {
      var div = document.createElement('div');
      div.textContent = text == null ? '' : String(text);
      return div.innerHTML;
    }

    function appSectionOptionsHtml(selectedSection) {
      var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
      var sel = selectedSection || '';
      var rows = [
        ['', 'form.selectSection'],
        ['Fresh Fish Section', 'opt.freshFish'],
        ['Meat and Chicken Section', 'opt.meat'],
        ['Dried and Salted Fish Section', 'opt.driedFish'],
        ['Fruits and Vegetables Section', 'opt.produce'],
        ['Dry Goods Section', 'opt.dryGoods'],
        ['Groceries Sari-Sari Section', 'opt.sariSari'],
        ['Eateries and Cooked Food Section', 'opt.eateries'],
        ['Service Section', 'opt.service'],
        ['Special Area Section', 'opt.special']
      ];
      return rows.map(function (r) {
        var label = en[r[1]] != null ? String(en[r[1]]) : r[0];
        if (r[0] === '') return '<option value="" data-translate="' + r[1] + '">' + escapeHtml(label) + '</option>';
        var selected = (sel && r[0] === sel) ? ' selected' : '';
        return '<option value="' + escapeHtml(r[0]) + '" data-translate="' + r[1] + '"' + selected + '>' + escapeHtml(label) + '</option>';
      }).join('');
    }

    function appBlockOptionsHtml(selectedBlock) {
      var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
      var sel = selectedBlock || '';
      var rows = [
        ['', 'form.selectBlock'],
        ['Daet Elevated Plaza', 'blk.plaza'],
        ['Market Tienda Bldg', 'blk.tienda'],
        ['Block A', 'blk.a'],
        ['Block B', 'blk.b'],
        ['Block C', 'blk.c'],
        ['BOT Block A-1', 'blk.botA1'],
        ['BOT Block E', 'blk.botE'],
        ['BOT Behind Block B', 'blk.behindB'],
        ['BOT Behind Block C', 'blk.behindC'],
        ['BOT BLOCK A', 'blk.mapA'],
        ['BOT BLOCK B', 'blk.mapB'],
        ['BOT BLOCK C', 'blk.mapC'],
        ['BOT BLOCK D', 'blk.mapD'],
        ['BOT BLOCK E', 'blk.mapE'],
        ['BOT BEHIND BLOCK C', 'blk.mapBehindC']
      ];
      return rows.map(function (r) {
        var label = en[r[1]] != null ? String(en[r[1]]) : r[0];
        if (r[0] === '') return '<option value="" data-translate="' + r[1] + '">' + escapeHtml(label) + '</option>';
        var selected = (sel && r[0] === sel) ? ' selected' : '';
        return '<option value="' + escapeHtml(r[0]) + '" data-translate="' + r[1] + '"' + selected + '>' + escapeHtml(label) + '</option>';
      }).join('');
    }

    function applyStallMapPrefill() {
      if (IS_EDITING_PAGE || !window.STALL_MAP_PREFILL) return;
      var p = window.STALL_MAP_PREFILL;
      if (!p.section && !p.block) return;
      var secEl = document.getElementById('section_1');
      var blkEl = document.getElementById('block_1');
      if (secEl && p.section) secEl.value = p.section;
      if (blkEl && p.block) blkEl.value = p.block;
      // Rebuild stall #1 dropdown for the prefilled block and preselect the chosen slot.
      var stallEl = document.getElementById('stall_number_1');
      if (stallEl && secEl && blkEl) {
        stallEl.innerHTML = appStallOptionsHtml(secEl.value, blkEl.value, p.slot || 0);
      }
    }

    let selectedFiles = [];
    const fileInput = document.getElementById('documents');
    
    function displaySelectedFiles(input) {
      const fileList = document.getElementById('fileList');
      if (!fileList || !input) return;
      var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
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
        fileList.innerHTML = '<h6 class="file-list-heading"><i class="fas fa-paperclip"></i> <span data-translate="form.selectedFiles">' + escapeHtml(en['form.selectedFiles'] || 'Selected Files') + '</span> (<span class="file-selected-count">' + selectedFiles.length + '</span>):</h6>';
        
        let totalSize = 0;
        selectedFiles.forEach((file, index) => {
          const fileSize = (file.size / 1024).toFixed(2);
          totalSize += file.size;
          const fileItem = document.createElement('div');
          fileItem.className = 'file-item';
          fileItem.innerHTML =
            '<span><i class="fas fa-file"></i> ' + escapeHtml(file.name) + ' (' + fileSize + ' KB)</span>' +
            '<button type="button" class="btn-remove-selected" onclick="removeFile(' + index + ')">' +
            '<i class="fas fa-times"></i> <span data-translate="form.remove">' + escapeHtml(en['form.remove'] || 'Remove') + '</span></button>';
          fileList.appendChild(fileItem);
        });
        
        const totalSizeKB = (totalSize / 1024).toFixed(2);
        const totalSizeMB = (totalSize / (1024 * 1024)).toFixed(2);
        const sizeText = totalSizeMB > 1 ? totalSizeMB + ' MB' : totalSizeKB + ' KB';
        fileList.innerHTML += '<p class="file-total-line"><strong><span data-translate="form.total">' + escapeHtml(en['form.total'] || 'Total:') + '</span></strong> ' + selectedFiles.length + ' <span data-translate="form.filesWord">' + escapeHtml(en['form.filesWord'] || 'files') + '</span>, ' + sizeText + '</p>';
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
      if (fileInput) fileInput.files = dataTransfer.files;
      
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

    function updateStallForms() {
      const formsContainer = document.getElementById('formsContainer');
      if (!formsContainer) return;

      var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
      
      let numStalls = 1;
      
      if (numStallsSelect && numStallsSelect.value === 'custom') {
        numStalls = parseInt(customStallsInput.value) || 1;
        if (numStalls < 1) numStalls = 1;
        if (numStalls > 20) {
          numStalls = 20;
          customStallsInput.value = 20;
          alert(appT('alert.maxStalls'));
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
      var prefill = (!IS_EDITING_PAGE && window.STALL_MAP_PREFILL) ? window.STALL_MAP_PREFILL : null;
      for (let i = 1; i <= numStalls; i++) {
        const formBox = document.createElement('div');
        formBox.className = 'form-box';
        
        var fullNameDefault = document.getElementById('full_name') ? document.getElementById('full_name').value : '<?php echo htmlspecialchars($user_name); ?>';
        var stallHeadingEn = (en['form.stallHeading'] || 'Stall {{n}} application').replace(/\{\{n\}\}/g, String(i));
        var sectionPick = (i === 1 && prefill && prefill.section) ? prefill.section : '';
        var blockPick = (i === 1 && prefill && prefill.block) ? prefill.block : '';
        formBox.innerHTML =
          '<div class="app-card-head">' +
            '<span class="app-card-icon" aria-hidden="true"><i class="fas fa-store"></i></span>' +
            '<div>' +
              '<h3 class="stall-section-heading"><span data-translate-stall="form.stallHeading" data-stall-n="' + i + '">' + escapeHtml(stallHeadingEn) + '</span></h3>' +
              '<p class="app-card-sub" data-translate="form.stallSub">' + escapeHtml(en['form.stallSub'] || 'Pitch details for this stall.') + '</p>' +
            '</div>' +
          '</div>' +
          '<label for="full_name_' + i + '"><span data-translate="form.fullName">' + escapeHtml(en['form.fullName'] || 'Full Name') + '</span> <span class="required">*</span></label>' +
          '<input type="text" id="full_name_' + i + '" name="full_name[]" value="' + escapeHtml(fullNameDefault) + '" required placeholder="' + escapeHtml(en['form.fullNamePh'] || 'e.g. Daria Fennel') + '">' +
          '<div class="app-field-grid">' +
            '<div>' +
              '<label for="section_' + i + '"><span data-translate="form.section">' + escapeHtml(en['form.section'] || 'Section') + '</span> <span class="required">*</span></label>' +
              '<select id="section_' + i + '" name="section[]" required onchange="appRefreshStallField(' + i + '); updateAppCompletion();">' + appSectionOptionsHtml(sectionPick) + '</select>' +
            '</div>' +
            '<div>' +
              '<label for="block_' + i + '"><span data-translate="form.preferredBlock">' + escapeHtml(en['form.preferredBlock'] || 'Preferred Block') + '</span> <span class="required">*</span></label>' +
              '<select id="block_' + i + '" name="block[]" required onchange="appRefreshStallField(' + i + '); updateAppCompletion();">' + appBlockOptionsHtml(blockPick) + '</select>' +
            '</div>' +
          '</div>' +
          '<label for="stall_number_' + i + '"><span data-translate="form.stallNumber">' + escapeHtml(en['form.stallNumber'] || 'Stall Slot Number') + '</span> <span class="required">*</span></label>' +
          '<select id="stall_number_' + i + '" name="stall_number[]" required onchange="updateAppCompletion()">' + appStallOptionsHtml(sectionPick, blockPick, (i === 1 && prefill ? prefill.slot : 0)) + '</select>' +
          '<div class="app-upload-docs">' +
            '<div class="app-card-head app-upload-docs-head">' +
              '<span class="app-card-icon" aria-hidden="true"><i class="fas fa-upload"></i></span>' +
              '<div>' +
                '<h3 class="app-upload-docs-title"><span data-translate="form.uploadDocs">' + escapeHtml(en['form.uploadDocs'] || 'Upload documents') + '</span> <span class="required">*</span></h3>' +
                '<p class="app-card-sub app-docs-status" id="docsStatus_' + i + '" data-total="' + (applicationRequirements ? applicationRequirements.length : 0) + '">0 of ' + (applicationRequirements ? applicationRequirements.length : 0) + ' requirements attached.</p>' +
              '</div>' +
            '</div>' +
            '<div class="requirements-callout">' +
            '<ul>' + generateRequirementsHTML(i) + '</ul></div>' +
          '<div id="fileList_' + i + '" class="file-list" style="display: none; margin-top: 1rem;"></div></div>';
        
        if (existingForm) {
          existingForm.appendChild(formBox);
        }
      }

      applyStallMapPrefill();
      
      // Confirm + Submit footer
      setTimeout(function() {
        const existingForm = document.getElementById('applicationForm');
        if (existingForm) {
          const existingFooter = existingForm.querySelector('.app-form-footer');
          if (existingFooter) existingFooter.remove();
          const oldButtons = existingForm.querySelectorAll('button[name="submit_application"]');
          oldButtons.forEach(btn => btn.remove());
          
          const footer = document.createElement('div');
          footer.className = 'app-form-footer';
          footer.innerHTML =
            '<div class="app-confirm-row">' +
              '<input type="checkbox" id="appConfirmRules" required>' +
              '<label for="appConfirmRules">' +
                '<span data-translate="form.confirmLead">' + escapeHtml(en['form.confirmLead'] || 'I confirm the details above are correct and agree to the ') + '</span>' +
                '<button type="button" class="app-rules-link" id="openAppRules" data-translate="form.rulesWord">' + escapeHtml(en['form.rulesWord'] || 'market rules') + '</button><span>.</span>' +
              '</label>' +
            '</div>' +
            '<button type="submit" name="submit_application" data-submit-all="1" class="form-btn submit-all-btn submit-all-footer-btn">' +
              '<span class="submit-all-footer-btn__text" data-translate="form.submitAll">' + escapeHtml(en['form.submitAll'] || 'Submit all applications') + '</span> <i class="fas fa-arrow-right" aria-hidden="true"></i>' +
            '</button>';
          existingForm.appendChild(footer);
          bindAppRulesTriggers();
          bindAppCompletionWatchers();
          bindAppFormSubmitValidation();
          updateAppCompletion();
        }
      }, 100);
    }
    
    function handleFileUpload(input, stallNumber, requirementType) {
      if (input.files && input.files.length > 0) {
        var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
        const file = input.files[0];
        const fileListId = stallNumber === 'edit' ? 'fileList_edit' : 'fileList_' + stallNumber;
        const fileList = document.getElementById(fileListId);
        
        if (!fileList) return;
        
        if (file.size > 5 * 1024 * 1024) {
          alert(appT('alert.fileSize'));
          input.value = '';
          return;
        }
        
        if (fileList.style.display === 'none') {
          fileList.style.display = 'block';
          fileList.innerHTML = '<h6 class="file-list-heading"><i class="fas fa-paperclip"></i> <span data-translate="form.uploadedFiles">' + escapeHtml(en['form.uploadedFiles'] || 'Uploaded Files:') + '</span></h6>';
        }
        
        const existingFileId = 'file_' + stallNumber + '_' + requirementType;
        const existingFile = document.getElementById(existingFileId);
        if (existingFile) {
          existingFile.remove();
        }
        
        /* IDs must match PHP / generateRequirementsHTML: check_id_card_edit, check_id_card_1, … */
        var checkmarkId = (stallNumber === 'edit')
          ? ('check_' + requirementType + '_edit')
          : ('check_' + requirementType + '_' + stallNumber);
        const checkmark = document.getElementById(checkmarkId);
        if (checkmark) {
          checkmark.style.display = 'inline-flex';
        }
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.id = existingFileId;
        const fileSize = (file.size / 1024).toFixed(2);
        fileItem.innerHTML =
          '<span><i class="fas fa-file"></i> ' + escapeHtml(file.name) + ' (' + fileSize + ' KB)</span>' +
          '<button type="button" class="btn-remove-stall-file" onclick="removeFileItem(\'' + existingFileId.replace(/'/g, "\\'") + '\', \'' + String(input.id).replace(/'/g, "\\'") + '\', \'' + String(checkmarkId).replace(/'/g, "\\'") + '\')">' +
          '<span class="btn-remove-stall-file-x" aria-hidden="true">×</span> <span data-translate="form.remove">' + escapeHtml(en['form.remove'] || 'Remove') + '</span></button>';
        fileList.appendChild(fileItem);
        if (typeof updateAppCompletion === 'function') updateAppCompletion();
      }
    }
    
    // Load requirements from PHP
    const applicationRequirements = <?php echo $requirements_json; ?>;
    
    function getRequirementName(type) {
      var k = 'req.' + type;
      var t = appT(k);
      if (t !== k) return t;
      return appT('req.document');
    }
    
    function generateRequirementsHTML(index) {
      if (!applicationRequirements || applicationRequirements.length === 0) {
        return '';
      }
      var en = (window.APPLICATION_I18N && window.APPLICATION_I18N.en) || {};
      
      const reqTypes = ['id_card', 'dti', 'barangay', 'moa', 'lease', 'inspection', 'receipt'];
      let html = '';
      
      applicationRequirements.forEach((req, idx) => {
        const reqType = reqTypes[idx] || 'other_' + idx;
        const checkId = 'check_' + reqType + '_' + index;
        const fileId = 'file_' + reqType + '_' + index;
        const fileName = 'documents_' + index + '[]';
        
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
        html += '<button type="button" class="btn-add-file" onclick="document.getElementById(\'' + fileId + '\').click()">';
        html += '<i class="fas fa-plus"></i> <span data-translate="form.addFile">' + escapeHtml(en['form.addFile'] || 'Add file') + '</span>';
        html += '</button>';
        html += '</div>';
        html += '<input type="file" id="' + fileId + '" name="' + fileName + '" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display: none;" onchange="handleFileUpload(this, ' + index + ', \'' + reqType + '\'); updateAppCompletion();">';
        html += '</li>';
      });
      
      return html;
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
      
      if (checkmarkId) {
        const checkmark = document.getElementById(checkmarkId);
        if (checkmark) {
          checkmark.style.display = 'none';
        }
      }
      
      const fileList = fileItem ? fileItem.parentElement : null;
      if (fileList && fileList.querySelectorAll('.file-item').length === 0) {
        fileList.style.display = 'none';
      }
      if (typeof updateAppCompletion === 'function') {
        updateAppCompletion();
      } else if (typeof updateDocsAttachedCounters === 'function') {
        updateDocsAttachedCounters();
      }
    }

    function openAppRulesModal() {
      var modal = document.getElementById('appRulesModal');
      if (!modal) return;
      modal.hidden = false;
      requestAnimationFrame(function () { modal.classList.add('is-open'); });
      document.body.style.overflow = 'hidden';
    }
    function closeAppRulesModal() {
      var modal = document.getElementById('appRulesModal');
      if (!modal) return;
      modal.classList.remove('is-open');
      document.body.style.overflow = '';
      setTimeout(function () { modal.hidden = true; }, 220);
    }
    function bindAppRulesTriggers() {
      document.querySelectorAll('#openAppRules, .app-rules-link').forEach(function (btn) {
        if (btn.dataset.rulesBound === '1') return;
        btn.dataset.rulesBound = '1';
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          openAppRulesModal();
        });
      });
    }
    (function initAppRulesModal() {
      var modal = document.getElementById('appRulesModal');
      var closeBtn = document.getElementById('closeAppRules');
      if (closeBtn) closeBtn.addEventListener('click', closeAppRulesModal);
      if (modal) {
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeAppRulesModal();
        });
      }
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAppRulesModal();
      });
      bindAppRulesTriggers();
    })();

    function formatDocsAttachedText(filled, total) {
      var tpl = appT('form.docsAttached');
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

    function updateAppCompletion() {
      var bar = document.getElementById('appCompletionBar');
      var pctEl = document.getElementById('appCompletionPct');
      var root = document.getElementById('applicationForm') || document.querySelector('.app-form-ui form');
      if (!bar || !pctEl) {
        updateDocsAttachedCounters();
        return;
      }
      if (!root) {
        bar.style.width = '0%';
        pctEl.textContent = '0%';
        updateDocsAttachedCounters();
        return;
      }
      var fields = root.querySelectorAll('input[required], select[required], textarea[required]');
      var total = 0;
      var filled = 0;
      fields.forEach(function (el) {
        if (el.type === 'checkbox') {
          total += 1;
          if (el.checked) filled += 1;
          return;
        }
        if (el.type === 'file') return;
        total += 1;
        if (String(el.value || '').trim() !== '') filled += 1;
      });
      var fileChecks = root.querySelectorAll('.req-check');
      var fileTotal = fileChecks.length;
      var fileFilled = 0;
      fileChecks.forEach(function (c) {
        if (window.getComputedStyle(c).display !== 'none') fileFilled += 1;
      });
      total += fileTotal;
      filled += fileFilled;
      var pct = total ? Math.round((filled / total) * 100) : 0;
      bar.style.width = pct + '%';
      pctEl.textContent = pct + '%';
      updateDocsAttachedCounters();
    }
    function bindAppCompletionWatchers() {
      var root = document.getElementById('applicationForm') || document.querySelector('.app-form-ui form');
      if (!root || root.dataset.completionBound === '1') return;
      root.dataset.completionBound = '1';
      root.addEventListener('input', updateAppCompletion);
      root.addEventListener('change', updateAppCompletion);
    }

    function clearAppIncompleteMarks(root) {
      root.querySelectorAll('.is-incomplete').forEach(function (el) {
        el.classList.remove('is-incomplete');
      });
    }

    function markAppIncomplete(el) {
      if (!el) return;
      el.classList.add('is-incomplete');
      var row = el.closest('.req-row');
      if (row) row.classList.add('is-incomplete');
    }

    function scrollToAppMissing(el) {
      if (!el) return;
      var target = el.closest('.req-row') || el.closest('.form-box') || el;
      try {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (err) {
        target.scrollIntoView(true);
      }
      setTimeout(function () {
        try { el.focus({ preventScroll: true }); } catch (e2) { try { el.focus(); } catch (e3) {} }
      }, 280);
    }

    function validateApplicationForm(root) {
      clearAppIncompleteMarks(root);
      var firstMissing = null;

      // Stall picker (outside form for new apps)
      var numStalls = document.getElementById('num_stalls');
      var customStalls = document.getElementById('custom_stalls');
      if (numStalls && numStalls.value === 'custom') {
        var customVal = parseInt(customStalls && customStalls.value, 10);
        if (!customVal || customVal < 1) {
          markAppIncomplete(customStalls || numStalls);
          if (!firstMissing) firstMissing = customStalls || numStalls;
        }
      }

      root.querySelectorAll('input, select, textarea').forEach(function (el) {
        if (el.type === 'file' || el.type === 'hidden' || el.disabled) return;
        if (el.type === 'checkbox') {
          if (el.required && !el.checked) {
            markAppIncomplete(el);
            if (!firstMissing) firstMissing = el;
          }
          return;
        }
        if (!el.required) return;
        if (String(el.value || '').trim() === '') {
          markAppIncomplete(el);
          if (!firstMissing) firstMissing = el;
        }
      });

      // Every requirement row must have a file, including the last one (Official Receipt).
      var docsMissing = false;
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
        scrollToAppMissing(firstMissing);
        var msg;
        if (docsMissing && firstMissing.closest && firstMissing.closest('.req-row')) {
          msg = appT('alert.docsIncomplete');
          if (!msg || msg === 'alert.docsIncomplete') {
            msg = 'Documents are not complete. Attach every requirement, then submit again.';
          }
        } else {
          msg = appT('alert.incompleteForm');
          if (msg === 'alert.incompleteForm') {
            msg = 'Please complete all required fields and upload documents before submitting.';
          }
        }
        alert(msg);
        return false;
      }
      return true;
    }

    function bindAppFormSubmitValidation() {
      document.querySelectorAll('.app-form-ui form').forEach(function (form) {
        if (form.dataset.validateBound === '1') return;
        form.dataset.validateBound = '1';
        form.addEventListener('submit', function (e) {
          if (!validateApplicationForm(form)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }
        });
        form.addEventListener('input', function (e) {
          if (e.target && e.target.classList) e.target.classList.remove('is-incomplete');
          var row = e.target && e.target.closest ? e.target.closest('.req-row') : null;
          if (row) row.classList.remove('is-incomplete');
        });
        form.addEventListener('change', function (e) {
          if (e.target && e.target.classList) e.target.classList.remove('is-incomplete');
          var row = e.target && e.target.closest ? e.target.closest('.req-row') : null;
          if (row) row.classList.remove('is-incomplete');
        });
      });
    }

    if (numStallsSelect && !<?php echo $editing ? 'true' : 'false'; ?>) {
      updateStallForms();
    } else if (!numStallsSelect && !<?php echo $editing ? 'true' : 'false'; ?>) {
      const existingForm = document.getElementById('applicationForm');
      if (existingForm && !existingForm.querySelector('button[name="submit_application"]')) {
        updateStallForms();
      }
    }

    bindAppCompletionWatchers();
    bindAppFormSubmitValidation();
    updateAppCompletion();

    window.applicationOnLangChange = function () {
      /* Dynamic form uses data-translate / data-translate-stall; changeLanguage → translatePage updates all text without rebuilding forms. */
    };

    if (typeof translatePage === 'function') {
      translatePage(localStorage.getItem('preferredLanguage') || 'en');
    }

  </script>
  <?php
  require_once __DIR__ . '/includes/rating_helpers.php';
  $rating_source = 'application';
  $rating_already_done = dpmUserHasRated($user_id);
  include __DIR__ . '/includes/rating_modal.php';
  ?>
  <?php if (!empty($success_message) && !$rating_already_done): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof window.showRatingSuccessPopup === 'function') {
        setTimeout(function () { window.showRatingSuccessPopup('Application submitted successfully!'); }, 500);
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