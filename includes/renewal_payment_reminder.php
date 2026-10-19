<?php
/**
 * Renewal payment reminder.
 *
 * After an application/renewal is APPROVED, the next renewal payment is due one
 * month later (Asia/Manila). This file provides the due-date math and the Gmail
 * email builder used by renewal_payment_reminder_cron.php.
 *
 * Reminder schedule (one email per day, per vendor, per due-date cycle):
 *   7, 6, 5, 4, 3, 2, 1 day(s) left, and on the due date (0 = today).
 *
 * Non-invasive by design: the due date is derived from vendors.reviewed_date, so
 * nothing in the admin approval code needs to change.
 */

require_once __DIR__ . '/submission_notify_email.php'; // dpm_mail_send_html()
require_once dirname(__DIR__) . '/application_fees.php'; // same rates as adminpanel → Application Fees

if (!function_exists('dpm_renewal_timezone')) {
    function dpm_renewal_timezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Manila');
    }
}

if (!function_exists('dpm_renewal_reminder_days_before')) {
    /**
     * Start sending daily reminders this many days before the due date (inclusive 0..N).
     */
    function dpm_renewal_reminder_days_before(): int
    {
        return 7;
    }
}

if (!function_exists('dpm_renewal_due_day')) {
    /**
     * Due date at start of day (Manila, 00:00), one month after the approval instant.
     * PHP "+1 month" clamps short months (e.g. Jan 31 -> Feb 28/29).
     */
    function dpm_renewal_due_day(string $approvedAtMysql): DateTimeImmutable
    {
        $tz = dpm_renewal_timezone();
        $approved = new DateTimeImmutable($approvedAtMysql, $tz);
        return $approved->modify('+1 month')->setTime(0, 0, 0);
    }
}

if (!function_exists('dpm_renewal_days_left_until_due')) {
    /**
     * Whole days from start of today (Manila) to the due date. 0 = due today, negative = past due.
     */
    function dpm_renewal_days_left_until_due(string $approvedAtMysql): int
    {
        $tz = dpm_renewal_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $due = dpm_renewal_due_day($approvedAtMysql);
        return (int) floor(($due->getTimestamp() - $today->getTimestamp()) / 86400);
    }
}

if (!function_exists('dpm_renewal_format_due')) {
    function dpm_renewal_format_due(string $approvedAtMysql): string
    {
        try {
            return dpm_renewal_due_day($approvedAtMysql)->format('d/m/Y');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('dpm_renewal_fee_amount')) {
    /**
     * Renewal fee (pesos) from adminpanel → Application Fees → Renewal price.
     * Reads application_fee_rates (fee_type = 'renewal'); falls back to 215 if unset.
     */
    function dpm_renewal_fee_amount(?mysqli $conn = null): int
    {
        if (function_exists('application_fees_get_by_type')) {
            $fee = application_fees_get_by_type($conn, 'renewal');
            if (is_array($fee) && isset($fee['price']) && (int) $fee['price'] > 0) {
                return (int) $fee['price'];
            }
        }
        $defaults = function_exists('application_fees_defaults') ? application_fees_defaults() : [];
        return (int) ($defaults['renewal']['price'] ?? 215);
    }
}

if (!function_exists('dpm_send_renewal_payment_reminder_email')) {
    /**
     * Daily renewal-payment reminder for days_left in 0..7.
     * days_left = 0 means the due date is today.
     *
     * @return bool true if SMTP reported send OK
     */
    function dpm_send_renewal_payment_reminder_email(
        string $toEmail,
        string $toName,
        string $stallLocation,
        string $dueDisplay,
        int $daysLeft,
        int $feeAmount
    ): bool {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $root = dirname(__DIR__);
        $daysLeft = max(0, $daysLeft);
        $safeName = htmlspecialchars($toName !== '' ? $toName : 'Vendor', ENT_QUOTES, 'UTF-8');
        $safeLoc = htmlspecialchars($stallLocation, ENT_QUOTES, 'UTF-8');
        $safeDue = htmlspecialchars($dueDisplay, ENT_QUOTES, 'UTF-8');
        $amountTxt = '₱' . number_format(max(0, $feeAmount));
        $unit = $daysLeft === 1 ? 'day' : 'days';
        $dueLine = 'Your due is ' . $daysLeft . ' ' . $unit . ' on ' . ($dueDisplay !== '' ? $dueDisplay : 'your due date') . '.';
        $subject = $dueLine . ' — Daet Public Market';
        $headline = 'Your due is ' . $daysLeft . ' ' . $unit;
        $intro = 'Your due is <strong>' . $daysLeft . ' ' . $unit . '</strong> on <strong>' . $safeDue . '</strong>.';
        $countdown = '<p style="margin:0;font-size:18px;color:#b45309;"><strong>' . htmlspecialchars($dueLine, ENT_QUOTES, 'UTF-8') . '</strong></p>';

        $logo_html = '';
        $logo_path = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs' . DIRECTORY_SEPARATOR . 'logo.jpeg';
        if (is_file($logo_path) && is_readable($logo_path)) {
            $fs = @filesize($logo_path);
            if ($fs && $fs < 200000) {
                $data = @file_get_contents($logo_path);
                if ($data !== false && $data !== '') {
                    $b64 = base64_encode($data);
                    if (strlen($b64) < 300000) {
                        $logo_html = "<img src='data:image/jpeg;base64," . $b64 . "' alt='Daet Public Market' style='max-width:140px;height:auto;margin-bottom:12px;display:block;' />";
                    }
                }
            }
        }

        $locBlock = $safeLoc !== ''
            ? '<p style="margin:0 0 8px 0;"><strong>Stall:</strong> ' . $safeLoc . '</p>'
            : '';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;font-family:Arial,sans-serif;background:#f4f4f4;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
            . '<div style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);color:#fff;padding:24px;border-radius:12px 12px 0 0;text-align:center;">'
            . $logo_html . '<h1 style="margin:0;font-size:22px;">' . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . '</h1></div>'
            . '<div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 12px 12px;">'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>' . $intro . '</p>'
            . '<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:16px;margin:16px 0;">'
            . $locBlock
            . '<p style="margin:0 0 8px 0;"><strong>Amount due:</strong> ' . htmlspecialchars($amountTxt, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Due date:</strong> ' . $safeDue . '</p>'
            . $countdown
            . '</div>'
            . '<p>Please settle your renewal on or before the due date to keep your stall clearance active. You can renew through the <strong>Renewal</strong> page on the website.</p>'
            . '<p>If you have already paid, you may ignore this message.</p>'
            . '<p style="color:#666;font-size:13px;margin-top:24px;">— Daet Public Market Office<br><span style="font-size:12px;">This is an automated message.</span></p>'
            . '</div></div></body></html>';

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        if (dpm_mail_send_html($toEmail, $toName, $subject, $html, $plain)) {
            error_log('dpm_send_renewal_payment_reminder_email: sent to ' . $toEmail . ' days_left=' . $daysLeft);
            return true;
        }
        return false;
    }
}

if (!function_exists('dpm_run_renewal_payment_reminders')) {
    /**
     * Email each approved vendor once per countdown day (7, 6, 5, 4, 3, 2, 1, 0)
     * when the one-month renewal due date is inside that window.
     */
    function dpm_run_renewal_payment_reminders(mysqli $conn): int
    {
        $conn->query("CREATE TABLE IF NOT EXISTS renewal_payment_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            due_date DATE NOT NULL,
            days_left INT NOT NULL DEFAULT 0,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_vendor_due_days (vendor_id, due_date, days_left)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $colCheck = $conn->query("SHOW COLUMNS FROM renewal_payment_reminders LIKE 'days_left'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE renewal_payment_reminders ADD COLUMN days_left INT NOT NULL DEFAULT 0 AFTER due_date");
            $idx = $conn->query("SHOW INDEX FROM renewal_payment_reminders WHERE Key_name = 'uniq_vendor_due'");
            if ($idx && $idx->num_rows > 0) {
                $conn->query("ALTER TABLE renewal_payment_reminders DROP INDEX uniq_vendor_due");
            }
            $idx2 = $conn->query("SHOW INDEX FROM renewal_payment_reminders WHERE Key_name = 'uniq_vendor_due_days'");
            if (!$idx2 || $idx2->num_rows === 0) {
                $conn->query("ALTER TABLE renewal_payment_reminders ADD UNIQUE KEY uniq_vendor_due_days (vendor_id, due_date, days_left)");
            }
        }

        $feeAmount = dpm_renewal_fee_amount($conn);
        $threshold = dpm_renewal_reminder_days_before();

        $sql = "SELECT v.vendor_id, v.vendor_name, v.section, v.block, v.reviewed_date,
                       u.email AS user_email, u.full_name AS user_name
                FROM vendors v
                INNER JOIN users u ON u.user_id = v.user_id
                WHERE LOWER(TRIM(COALESCE(v.status, ''))) = 'approved'
                  AND v.reviewed_date IS NOT NULL
                  AND u.email IS NOT NULL AND u.email <> ''";
        $res = $conn->query($sql);
        if (!$res) {
            error_log('dpm_run_renewal_payment_reminders: query failed ' . $conn->error);
            return 0;
        }

        $checkStmt = $conn->prepare("SELECT 1 FROM renewal_payment_reminders WHERE vendor_id = ? AND due_date = ? AND days_left = ? LIMIT 1");
        $insStmt = $conn->prepare("INSERT IGNORE INTO renewal_payment_reminders (vendor_id, due_date, days_left) VALUES (?, ?, ?)");

        $sent = 0;
        while ($row = $res->fetch_assoc()) {
            $vendorId = (int) ($row['vendor_id'] ?? 0);
            $approvedAt = (string) ($row['reviewed_date'] ?? '');
            $email = trim((string) ($row['user_email'] ?? ''));
            $name = (string) ($row['user_name'] ?? ($row['vendor_name'] ?? ''));
            if ($vendorId <= 0 || $approvedAt === '' || $email === '') {
                continue;
            }

            $daysLeft = dpm_renewal_days_left_until_due($approvedAt);
            if ($daysLeft < 0 || $daysLeft > $threshold) {
                continue;
            }

            $dueDate = dpm_renewal_due_day($approvedAt)->format('Y-m-d');
            $already = false;
            if ($checkStmt) {
                $checkStmt->bind_param('isi', $vendorId, $dueDate, $daysLeft);
                $checkStmt->execute();
                $checkStmt->store_result();
                $already = $checkStmt->num_rows > 0;
                $checkStmt->free_result();
            }
            if ($already) {
                continue;
            }

            $section = trim((string) ($row['section'] ?? ''));
            $block = trim((string) ($row['block'] ?? ''));
            $stallLocation = trim($section . ($section !== '' && $block !== '' ? ' — ' : '') . $block);
            $dueDisplay = dpm_renewal_format_due($approvedAt);

            if (!dpm_send_renewal_payment_reminder_email($email, $name, $stallLocation, $dueDisplay, $daysLeft, $feeAmount)) {
                continue;
            }

            if ($insStmt) {
                $insStmt->bind_param('isi', $vendorId, $dueDate, $daysLeft);
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

        error_log('dpm_run_renewal_payment_reminders: reminders sent=' . $sent);
        return $sent;
    }
}
