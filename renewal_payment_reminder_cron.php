<?php
/**
 * Renewal payment reminder — daily job.
 *
 * Also runs once per day from the website (includes/due_reminder_kick.php).
 * You can still run it by hand:
 *   php "C:\Users\user\Desktop\Market Clearance System\renewal_payment_reminder_cron.php"
 *
 * Approved stalls are due one month after approval. From 7 days before that
 * date through the due date (0), the vendor gets one Gmail each day:
 * "Your due is 7 days on dd/mm/yyyy" down to 0 days.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/renewal_payment_reminder.php';

$conn = getDBConnection();
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$sent = dpm_run_renewal_payment_reminders($conn);
$conn->close();

echo "OK renewal_reminders_sent={$sent}\n";
