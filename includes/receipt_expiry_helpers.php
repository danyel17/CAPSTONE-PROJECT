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
     * Pending-receipt countdown emails are no longer sent.
     * The renewal payment reminder starts when an admin approves the receipt.
     */
    function dpm_run_receipt_due_reminders(mysqli $conn): int
    {
        return 0;
    }
}
