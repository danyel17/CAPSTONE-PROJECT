<?php
/**
 * JSON API: upload official receipt image from menu (home) page.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/includes/receipt_resubmit_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a receipt.']);
    exit;
}

if (!isset($_FILES['receipt_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$conn = getDBConnection();

try {
    if (!$conn) {
        throw new Exception('Database connection failed.');
    }

    $create_table_query = "CREATE TABLE IF NOT EXISTS receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        section VARCHAR(100) DEFAULT NULL,
        block VARCHAR(100) DEFAULT NULL,
        receipt_file_path VARCHAR(500) NOT NULL,
        notes TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        reviewed_by INT NULL,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_section (section),
        INDEX idx_block (block)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($create_table_query)) {
        throw new Exception('Failed to ensure receipts table: ' . $conn->error);
    }

    foreach (['section' => "ALTER TABLE receipts ADD COLUMN section VARCHAR(100) DEFAULT NULL",
              'block' => "ALTER TABLE receipts ADD COLUMN block VARCHAR(100) DEFAULT NULL",
              'expiry_reminder_sent' => "ALTER TABLE receipts ADD COLUMN expiry_reminder_sent TINYINT(1) NOT NULL DEFAULT 0"] as $col => $alter) {
        $chk = $conn->query("SHOW COLUMNS FROM receipts LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            @$conn->query($alter);
        }
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        throw new Exception('User not logged in.');
    }

    $submit_check = dpmReceiptCanSubmitNew($conn, $user_id);
    if (!$submit_check['allowed']) {
        throw new Exception($submit_check['reason'] ?? 'You cannot submit a new receipt right now.');
    }

    // Use account email from database when valid (session can be empty or stale).
    $user_email = trim((string)($_SESSION['email'] ?? ''));
    $em_stmt = $conn->prepare('SELECT email FROM users WHERE user_id = ? LIMIT 1');
    if ($em_stmt) {
        $em_stmt->bind_param('i', $user_id);
        $em_stmt->execute();
        $em_res = $em_stmt->get_result();
        if ($em_row = $em_res->fetch_assoc()) {
            $db_email = trim((string)($em_row['email'] ?? ''));
            if ($db_email !== '' && filter_var($db_email, FILTER_VALIDATE_EMAIL)) {
                $user_email = $db_email;
            }
        }
        $em_stmt->close();
    }

    $file = $_FILES['receipt_file'];
    $stored = dpmReceiptStoreUploadedFile($file, $user_id);
    if (!$stored['ok']) {
        throw new Exception($stored['message'] ?? 'Failed to save uploaded file.');
    }
    $file_path_db = $stored['path_db'];

    $user_name = (string)($_SESSION['full_name'] ?? 'Unknown User');
    // $user_email already resolved from DB + session above — do not overwrite with session only.
    $raw_notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
    $notes = function_exists('mb_substr')
        ? mb_substr($raw_notes, 0, 2000, 'UTF-8')
        : substr($raw_notes, 0, 2000);

    $section = '';
    $block = '';

    $vendor_stmt = $conn->prepare("SELECT section, block FROM vendors WHERE user_id = ? AND status = 'approved' ORDER BY reviewed_date DESC LIMIT 1");
    if ($vendor_stmt) {
        $vendor_stmt->bind_param('i', $user_id);
        $vendor_stmt->execute();
        $vendor_result = $vendor_stmt->get_result();
        if ($vendor_row = $vendor_result->fetch_assoc()) {
            $section = trim((string)($vendor_row['section'] ?? ''));
            $block = trim((string)($vendor_row['block'] ?? ''));
        }
        $vendor_stmt->close();
    }

    if ($section === '' || $block === '') {
        $vendor_stmt2 = $conn->prepare("SELECT section, block FROM vendors WHERE user_id = ? ORDER BY submitted_date DESC LIMIT 1");
        if ($vendor_stmt2) {
            $vendor_stmt2->bind_param('i', $user_id);
            $vendor_stmt2->execute();
            $vendor_result2 = $vendor_stmt2->get_result();
            if ($vendor_row2 = $vendor_result2->fetch_assoc()) {
                if ($section === '') {
                    $section = trim((string)($vendor_row2['section'] ?? ''));
                }
                if ($block === '') {
                    $block = trim((string)($vendor_row2['block'] ?? ''));
                }
            }
            $vendor_stmt2->close();
        }
    }

    $stmt = $conn->prepare('INSERT INTO receipts (user_id, user_name, user_email, section, block, receipt_file_path, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        dpmReceiptDeleteFileIfExists($file_path_db);
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('issssss', $user_id, $user_name, $user_email, $section, $block, $file_path_db, $notes);

    if (!$stmt->execute()) {
        dpmReceiptDeleteFileIfExists($file_path_db);
        throw new Exception('Failed to save receipt: ' . $stmt->error);
    }

    $receipt_id = (int) $stmt->insert_id;
    $stmt->close();

    $created_at_row = date('Y-m-d H:i:s');
    $ca_stmt = $conn->prepare('SELECT created_at FROM receipts WHERE id = ? LIMIT 1');
    if ($ca_stmt) {
        $ca_stmt->bind_param('i', $receipt_id);
        $ca_stmt->execute();
        $ca_res = $ca_stmt->get_result();
        if ($ca_row = $ca_res->fetch_assoc()) {
            $created_at_row = (string)($ca_row['created_at'] ?? $created_at_row);
        }
        $ca_stmt->close();
    }

    // Same detail line as profile activity log (applications/renewals list)
    $det = 'Receipt sent';
    $loc = trim(implode(' · ', array_filter([$section, $block])));
    if ($loc !== '') {
        $det .= ': ' . $loc;
    }
    $notes_trim = trim($notes);
    if ($notes_trim !== '') {
        $maxN = 120;
        if (function_exists('mb_strlen') && mb_strlen($notes_trim) > $maxN) {
            $det .= ' — ' . mb_substr($notes_trim, 0, $maxN) . '…';
        } elseif (strlen($notes_trim) > $maxN) {
            $det .= ' — ' . substr($notes_trim, 0, $maxN) . '…';
        } else {
            $det .= ' — ' . $notes_trim;
        }
    }

    $loc = trim(implode(', ', array_filter([
        $section !== '' ? $section : '',
        $block !== '' ? 'Block ' . $block : '',
    ], function ($part) { return $part !== ''; })));
    if ($loc === '') {
        $loc = 'your stall';
    }
    $receipt_message = "Your receipt for {$loc} has been submitted and is now pending review. Please wait for the approval.";
    recordApprovalNotification(
        $conn,
        $user_id,
        null,
        'pending_review',
        'Receipt Submitted',
        $receipt_message,
        'medium',
        false,
        'profile.php',
        $receipt_id
    );

    dpmLogReceiptActivity($conn, $user_id, 'receipt_upload', $det);

    $conn->close();

    $notify_to = trim($user_email);
    $email_attempted = $notify_to !== '' && filter_var($notify_to, FILTER_VALIDATE_EMAIL);
    $email_sent = false;
    if ($email_attempted) {
        try {
            require_once __DIR__ . '/includes/receipt_expiry_helpers.php';
            require_once __DIR__ . '/includes/submission_notify_email.php';
            $email_sent = dpm_notify_submission_email('receipt', $notify_to, $user_name, [
                'receipt_id' => $receipt_id,
                'detail_line' => $det,
                'submitted_display' => dpm_receipt_format_submitted($created_at_row),
                'expires_display' => dpm_receipt_format_expires($created_at_row),
            ]);
        } catch (Throwable $mailEx) {
            error_log('submit_menu_receipt: confirmation email skipped — ' . $mailEx->getMessage());
            $email_sent = false;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Receipt submitted successfully. The office will review it soon.',
        'receipt_id' => $receipt_id,
        'activity_details' => $det,
        'email_sent' => $email_sent,
        'email_attempted' => $email_attempted,
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->close();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
