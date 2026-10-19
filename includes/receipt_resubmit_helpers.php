<?php
/**
 * Receipt resubmit helpers — avoid duplicate receipts; allow editing rejected ones.
 */

function dpmEnsureReceiptNotificationColumn(mysqli $conn): void
{
    $chk = $conn->query("SHOW COLUMNS FROM approval_notifications LIKE 'receipt_id'");
    if ($chk && $chk->num_rows === 0) {
        @$conn->query("ALTER TABLE approval_notifications ADD COLUMN receipt_id INT NULL DEFAULT NULL AFTER vendor_id");
        @$conn->query("ALTER TABLE approval_notifications ADD INDEX idx_receipt_id (receipt_id)");
    }
}

function dpmUserActiveReceipt(mysqli $conn, int $user_id): ?array
{
    if ($user_id <= 0) {
        return null;
    }
    $stmt = $conn->prepare("SELECT id, status, section, block, notes, receipt_file_path, created_at, reviewed_at
        FROM receipts
        WHERE user_id = ? AND LOWER(TRIM(COALESCE(status, ''))) IN ('pending', 'rejected')
        ORDER BY id DESC
        LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function dpmReceiptCanSubmitNew(mysqli $conn, int $user_id): array
{
    $active = dpmUserActiveReceipt($conn, $user_id);
    if (!$active) {
        return ['allowed' => true, 'reason' => ''];
    }
    $status = strtolower(trim((string)($active['status'] ?? '')));
    if ($status === 'pending') {
        return [
            'allowed' => false,
            'reason' => 'You already have a receipt pending review. Please wait for the admin to approve or mark it invalid.',
            'receipt_id' => (int)($active['id'] ?? 0),
            'status' => 'pending',
        ];
    }
    if ($status === 'rejected') {
        return [
            'allowed' => false,
            'reason' => 'Your receipt was marked invalid. Open Notifications and use Edit to update and resubmit the same receipt.',
            'receipt_id' => (int)($active['id'] ?? 0),
            'status' => 'rejected',
        ];
    }
    return ['allowed' => true, 'reason' => ''];
}

function dpmReceiptUploadDir(): string
{
    return __DIR__ . '/../uploads/receipts/';
}

function dpmReceiptValidateImageUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'File upload error.'];
    }
    $max_file_size = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max_file_size) {
        return ['ok' => false, 'message' => 'File size exceeds 5MB limit.'];
    }
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime_type && !in_array($mime_type, $allowed_types, true)) {
            return ['ok' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.'];
        }
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions, true)) {
        return ['ok' => false, 'message' => 'Invalid file extension. Use .jpg, .jpeg, .png, or .gif.'];
    }
    return ['ok' => true, 'extension' => $ext];
}

function dpmReceiptStoreUploadedFile(array $file, int $user_id): array
{
    $check = dpmReceiptValidateImageUpload($file);
    if (!$check['ok']) {
        return ['ok' => false, 'message' => $check['message'] ?? 'Invalid file.'];
    }
    $upload_dir = dpmReceiptUploadDir();
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        return ['ok' => false, 'message' => 'Failed to create upload directory.'];
    }
    $ext = $check['extension'] ?? 'jpg';
    $file_name = 'receipt_' . $user_id . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
    $file_path_fs = $upload_dir . $file_name;
    $file_path_db = 'uploads/receipts/' . $file_name;
    if (!move_uploaded_file($file['tmp_name'], $file_path_fs)) {
        return ['ok' => false, 'message' => 'Failed to save uploaded file.'];
    }
    return ['ok' => true, 'path_db' => $file_path_db, 'path_fs' => $file_path_fs];
}

function dpmReceiptDeleteFileIfExists(?string $path_db): void
{
    if ($path_db === null || trim($path_db) === '') {
        return;
    }
    $rel = ltrim(str_replace('\\', '/', trim($path_db)), '/');
    $full = __DIR__ . '/../' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

function dpmLogReceiptActivity(mysqli $conn, int $user_id, string $activity_type, string $details): void
{
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS user_activity_log (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity_type VARCHAR(100) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address) VALUES (?, ?, ?, ?)");
        if ($log_stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log_stmt->bind_param('isss', $user_id, $activity_type, $details, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Throwable $e) {
        error_log('dpmLogReceiptActivity: ' . $e->getMessage());
    }
}
