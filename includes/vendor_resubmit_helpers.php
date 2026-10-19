<?php

/**
 * Allow vendors to reset incomplete applications back to pending for admin re-review.
 */
function dpmDisableLegacyStatusAutoFix(mysqli $conn): void
{
    try {
        $conn->query('DROP EVENT IF EXISTS auto_fix_vendor_statuses');
    } catch (Throwable $e) {
        error_log('Drop auto_fix_vendor_statuses event notice: ' . $e->getMessage());
    }
    try {
        $conn->query('DROP PROCEDURE IF EXISTS fix_all_vendor_statuses');
    } catch (Throwable $e) {
        error_log('Drop fix_all_vendor_statuses procedure notice: ' . $e->getMessage());
    }
}

function dpmSqlSkipPendingResubmit(string $alias = 'v'): string
{
    return "AND NOT ({$alias}.status = 'pending' AND COALESCE({$alias}.review_status, 'pending') = 'pending' AND COALESCE({$alias}.is_reviewed, 0) = 0 AND {$alias}.reviewed_by IS NULL AND {$alias}.reviewed_date IS NULL)";
}

function dpmEnsureApprovalActionsSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS approval_actions (
            action_id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            admin_id INT NOT NULL,
            action_type ENUM('approve', 'incomplete', 'reject', 'pending_review', 'request_changes', 'resubmit') NOT NULL,
            admin_notes TEXT,
            rejection_reason VARCHAR(255) DEFAULT NULL,
            status_before ENUM('pending', 'approved', 'incomplete', 'resubmitted', 'rejected') NOT NULL DEFAULT 'pending',
            status_after ENUM('pending', 'approved', 'incomplete', 'resubmitted', 'rejected') NOT NULL DEFAULT 'pending',
            action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            INDEX idx_vendor_id (vendor_id),
            INDEX idx_admin_id (admin_id),
            INDEX idx_action_type (action_type),
            INDEX idx_action_date (action_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Upgrade older installs where incomplete/resubmit were missing from ENUM columns.
        $conn->query("ALTER TABLE approval_actions MODIFY action_type ENUM('approve', 'incomplete', 'reject', 'pending_review', 'request_changes', 'resubmit') NOT NULL");
        $conn->query("ALTER TABLE approval_actions MODIFY status_before ENUM('pending', 'approved', 'incomplete', 'resubmitted', 'rejected') NOT NULL DEFAULT 'pending'");
        $conn->query("ALTER TABLE approval_actions MODIFY status_after ENUM('pending', 'approved', 'incomplete', 'resubmitted', 'rejected') NOT NULL DEFAULT 'pending'");

        // Repair rows saved before the ENUM migration (action_type was blank).
        $conn->query("UPDATE approval_actions SET action_type = 'incomplete', status_after = 'incomplete'
            WHERE (action_type = '' OR action_type IS NULL) AND admin_notes LIKE 'Incomplete Reason:%'");
        $conn->query("UPDATE approval_actions SET action_type = 'resubmit', status_after = 'pending',
            status_before = IF(status_before = '' OR status_before IS NULL, 'incomplete', status_before)
            WHERE (action_type = '' OR action_type IS NULL) AND admin_notes LIKE 'Vendor resubmitted via%'");
    } catch (Throwable $e) {
        error_log('approval_actions schema notice: ' . $e->getMessage());
    }

    $done = true;
}

function dpmNormalizeApprovalActionType(string $actionType, string $adminNotes = ''): string
{
    $actionType = strtolower(trim($actionType));
    if (in_array($actionType, ['approve', 'incomplete', 'reject'], true)) {
        return $actionType;
    }
    if (stripos($adminNotes, 'Incomplete Reason:') === 0) {
        return 'incomplete';
    }
    return $actionType;
}

function dpmEnsureVendorResubmitTrigger(mysqli $conn): void
{
    try {
        $conn->query('DROP TRIGGER IF EXISTS prevent_status_reset_to_pending');
        $conn->query("CREATE TRIGGER prevent_status_reset_to_pending BEFORE UPDATE ON vendors FOR EACH ROW BEGIN
            IF NEW.status = 'pending'
               AND NEW.review_status = 'pending'
               AND NEW.is_reviewed = 0
               AND NEW.reviewed_by IS NULL
               AND NEW.reviewed_date IS NULL
               AND (NEW.admin_notes IS NULL OR NEW.admin_notes = '')
            THEN
                SET NEW.status = 'pending',
                    NEW.review_status = 'pending',
                    NEW.is_reviewed = 0;
            ELSEIF (OLD.status IN ('approved', 'incomplete')
                OR OLD.is_reviewed = 1
                OR OLD.review_status IN ('approved', 'incomplete')
                OR OLD.reviewed_by IS NOT NULL
                OR EXISTS(SELECT 1 FROM approval_actions WHERE vendor_id = OLD.vendor_id AND action_type IN ('approve', 'incomplete')))
                AND NEW.status = 'pending'
            THEN
                SET NEW.status = COALESCE(OLD.review_status, OLD.status,
                    CASE WHEN OLD.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END),
                    NEW.review_status = COALESCE(OLD.review_status, OLD.status,
                    CASE WHEN OLD.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END),
                    NEW.is_reviewed = 1,
                    NEW.reviewed_date = COALESCE(OLD.reviewed_date, NOW()),
                    NEW.reviewed_by = COALESCE(OLD.reviewed_by, NEW.reviewed_by),
                    NEW.admin_notes = COALESCE(OLD.admin_notes, NEW.admin_notes);
            END IF;

            IF NEW.review_status IS NOT NULL AND NEW.review_status != 'pending' AND NEW.status != NEW.review_status THEN
                SET NEW.status = NEW.review_status;
            END IF;

            IF NEW.reviewed_by IS NOT NULL AND NEW.reviewed_date IS NOT NULL THEN
                SET NEW.is_reviewed = 1;
                IF NEW.review_status IS NULL OR NEW.review_status = 'pending' THEN
                    SET NEW.review_status = CASE WHEN NEW.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END;
                    SET NEW.status = NEW.review_status;
                END IF;
            END IF;
        END");
    } catch (Throwable $e) {
        error_log('Vendor resubmit trigger notice: ' . $e->getMessage());
    }
}

function dpmLogVendorResubmit(mysqli $conn, int $vendorId, int $userId, string $statusBefore, string $source = 'vendor'): void
{
    if ($vendorId <= 0) {
        return;
    }

    try {
        dpmEnsureApprovalActionsSchema($conn);

        $statusBefore = in_array($statusBefore, ['pending', 'approved', 'incomplete', 'resubmitted'], true)
            ? $statusBefore
            : 'incomplete';
        $notes = 'Vendor resubmitted via ' . $source;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $logStmt = $conn->prepare(
            "INSERT INTO approval_actions (vendor_id, admin_id, action_type, admin_notes, status_before, status_after, ip_address, user_agent)
             VALUES (?, ?, 'resubmit', ?, ?, 'pending', ?, ?)"
        );
        if (!$logStmt) {
            return;
        }
        $logStmt->bind_param('iissss', $vendorId, $userId, $notes, $statusBefore, $ipAddress, $userAgent);
        $logStmt->execute();
        $logStmt->close();
    } catch (Throwable $e) {
        error_log('Vendor resubmit log notice: ' . $e->getMessage());
    }
}

/**
 * How many files in this upload field actually arrived (empty requirement slots do not count).
 */
function dpm_count_uploaded_files(string $key): int
{
    if (!isset($_FILES[$key]['error'])) {
        return 0;
    }
    $errors = $_FILES[$key]['error'];
    $names = $_FILES[$key]['name'] ?? [];
    if (!is_array($errors)) {
        return ((int) $errors === UPLOAD_ERR_OK && trim((string) $names) !== '') ? 1 : 0;
    }
    $count = 0;
    foreach ($errors as $i => $err) {
        $name = trim((string) ($names[$i] ?? ''));
        if ((int) $err === UPLOAD_ERR_OK && $name !== '') {
            $count++;
        }
    }
    return $count;
}
