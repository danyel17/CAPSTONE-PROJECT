<?php
/**
 * Sends the 7-to-0 due emails once per Manila day when someone opens the site.
 * Each vendor still gets only one message per countdown day.
 */
if (!function_exists('dpm_kick_due_reminders')) {
    function dpm_kick_due_reminders(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $stamp = $dir . DIRECTORY_SEPARATOR . 'due_reminder_lastrun.txt';
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

        $fh = @fopen($stamp, 'c+');
        if (!$fh) {
            return;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return;
        }
        $existing = stream_get_contents($fh);
        if (trim((string) $existing) === $today) {
            flock($fh, LOCK_UN);
            fclose($fh);
            return;
        }

        try {
            require_once __DIR__ . '/renewal_payment_reminder.php';
            require_once __DIR__ . '/receipt_expiry_helpers.php';
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                flock($fh, LOCK_UN);
                fclose($fh);
                return;
            }
            $conn->set_charset('utf8mb4');
            $conn->query("SET time_zone = '+08:00'");
            dpm_run_renewal_payment_reminders($conn);
            dpm_run_receipt_due_reminders($conn);
            $conn->close();

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $today);
            fflush($fh);
        } catch (Throwable $e) {
            error_log('dpm_kick_due_reminders: ' . $e->getMessage());
        }

        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

if (PHP_SAPI !== 'cli') {
    register_shutdown_function('dpm_kick_due_reminders');
}
