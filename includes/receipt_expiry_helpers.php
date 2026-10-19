<?php
/**
 * Receipt review window: due date is the same calendar day one month after submission (Asia/Manila).
 * Example: submit April 7 → due May 7. PHP +1 month handles short months (e.g. Jan 31 → Feb 28/29).
 */
if (!function_exists('dpm_receipt_timezone')) {
    function dpm_receipt_timezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Manila');
    }
}

if (!function_exists('dpm_receipt_expiry_day')) {
    /**
     * Due date at start of that calendar day in Manila (00:00), one month after submission instant.
     */
    function dpm_receipt_expiry_day(string $createdAtMysql): DateTimeImmutable
    {
        $tz = dpm_receipt_timezone();
        $sub = new DateTimeImmutable($createdAtMysql, $tz);
        return $sub->modify('+1 month')->setTime(0, 0, 0);
    }
}

if (!function_exists('dpm_receipt_days_left_until_expiry')) {
    /**
     * Whole days from start of today (Manila) to the due date (0 = due today, negative = past due).
     */
    function dpm_receipt_days_left_until_expiry(string $createdAtMysql): int
    {
        $tz = dpm_receipt_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $expiryDay = dpm_receipt_expiry_day($createdAtMysql);
        return (int) floor(($expiryDay->getTimestamp() - $today->getTimestamp()) / 86400);
    }
}

if (!function_exists('dpm_receipt_reminder_days_left_threshold')) {
    /**
     * Pending receipts get one reminder email per day while whole days left until the due date are between 0 and this value (inclusive).
     */
    function dpm_receipt_reminder_days_left_threshold(): int
    {
        return 7;
    }
}

if (!function_exists('dpm_receipt_format_submitted')) {
    function dpm_receipt_format_submitted(string $createdAtMysql): string
    {
        try {
            $dt = new DateTimeImmutable($createdAtMysql, dpm_receipt_timezone());
            return $dt->format('F j, Y \a\t g:i A');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('dpm_receipt_format_expires')) {
    function dpm_receipt_format_expires(string $createdAtMysql): string
    {
        try {
            return dpm_receipt_expiry_day($createdAtMysql)->format('F j, Y');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('dpm_run_receipt_due_reminders')) {
    /**
     * One Gmail per pending receipt per countdown day (7 through 0).
     * Due date is one month after the receipt was sent.
     */
    function dpm_run_receipt_due_reminders(mysqli $conn): int
    {
        require_once __DIR__ . '/submission_notify_email.php';

        $conn->query("CREATE TABLE IF NOT EXISTS receipt_due_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_id INT NOT NULL,
            due_date DATE NOT NULL,
            days_left INT NOT NULL DEFAULT 0,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_receipt_due_days (receipt_id, due_date, days_left)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $res = $conn->query("SELECT r.id,
                COALESCE(NULLIF(TRIM(u.email), ''), NULLIF(TRIM(r.user_email), '')) AS user_email,
                COALESCE(NULLIF(TRIM(u.full_name), ''), NULLIF(TRIM(r.user_name), '')) AS user_name,
                r.created_at
            FROM receipts r
            LEFT JOIN users u ON u.user_id = r.user_id
            WHERE LOWER(TRIM(COALESCE(r.status, ''))) = 'pending'");
        if (!$res) {
            error_log('dpm_run_receipt_due_reminders: query failed ' . $conn->error);
            return 0;
        }

        $threshold = dpm_receipt_reminder_days_left_threshold();
        $checkStmt = $conn->prepare("SELECT 1 FROM receipt_due_reminders WHERE receipt_id = ? AND due_date = ? AND days_left = ? LIMIT 1");
        $insStmt = $conn->prepare("INSERT IGNORE INTO receipt_due_reminders (receipt_id, due_date, days_left) VALUES (?, ?, ?)");

        $sent = 0;
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            $email = trim((string) ($row['user_email'] ?? ''));
            $name = (string) ($row['user_name'] ?? '');
            $created = (string) ($row['created_at'] ?? '');
            if ($id <= 0 || $created === '' || $email === '') {
                continue;
            }

            $daysLeft = dpm_receipt_days_left_until_expiry($created);
            if ($daysLeft < 0 || $daysLeft > $threshold) {
                continue;
            }

            $dueDate = dpm_receipt_expiry_day($created)->format('Y-m-d');
            $already = false;
            if ($checkStmt) {
                $checkStmt->bind_param('isi', $id, $dueDate, $daysLeft);
                $checkStmt->execute();
                $checkStmt->store_result();
                $already = $checkStmt->num_rows > 0;
                $checkStmt->free_result();
            }
            if ($already) {
                continue;
            }

            $submittedDisp = dpm_receipt_format_submitted($created);
            $expiresDisp = dpm_receipt_expiry_day($created)->format('d/m/Y');
            if (!dpm_send_receipt_expiry_reminder_email($email, $name, $id, $submittedDisp, $expiresDisp, $daysLeft)) {
                continue;
            }

            if ($insStmt) {
                $insStmt->bind_param('isi', $id, $dueDate, $daysLeft);
                $insStmt->execute();
            }
            $sent++;
        }

        if ($checkStmt) {
            $checkStmt->close();
        }
        if ($insStmt) {
            $insStmt->close();
        }
        $res->free();

        error_log('dpm_run_receipt_due_reminders: reminders sent=' . $sent);
        return $sent;
    }
}
