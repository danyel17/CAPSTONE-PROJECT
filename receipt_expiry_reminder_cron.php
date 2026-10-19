<?php
/**
 * Receipt due reminder — daily job.
 *
 * Also runs once per day from the website (includes/due_reminder_kick.php).
 * Pending receipts are due one month after they were sent. From 7 days before
 * that date through the due date (0), the user gets one Gmail each day.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/receipt_expiry_helpers.php';

$conn = getDBConnection();
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$sent = dpm_run_receipt_due_reminders($conn);
$conn->close();

echo "OK reminders_sent={$sent}\n";
