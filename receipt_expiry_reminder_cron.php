<?php
/**
 * The pending-receipt countdown is no longer sent.
 * Renewal payment reminders start when an admin approves the receipt.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/receipt_expiry_helpers.php';

$conn = getDBConnection();
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$conn->close();

echo "OK receipt reminder removed; renewal reminders start after receipt approval.\n";
