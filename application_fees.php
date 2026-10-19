<?php
/**
 * Application fee rates shown on menu.php (Application Fees carousel).
 */

if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/config.php';
}

function application_fees_defaults(): array
{
    return [
        'new' => [
            'id' => 'new',
            'title' => 'New Application',
            'subtitle' => 'Stall application for new vendors',
            'price' => 250,
            'href' => 'application.php',
        ],
        'renewal' => [
            'id' => 'renewal',
            'title' => 'Renewal',
            'subtitle' => 'Annual renewal for existing vendors',
            'price' => 215,
            'href' => 'renewal.php',
        ],
    ];
}

function application_fees_normalize_price($value): int
{
    if (!is_numeric($value)) {
        return 0;
    }
    return max(0, (int) round((float) $value));
}

function application_fees_ensure_table(mysqli $conn): bool
{
    if (!$conn) {
        return false;
    }

    $sql = "CREATE TABLE IF NOT EXISTS application_fee_rates (
        fee_type VARCHAR(20) NOT NULL PRIMARY KEY,
        title VARCHAR(120) NOT NULL DEFAULT '',
        subtitle VARCHAR(255) NOT NULL DEFAULT '',
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        link_href VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return (bool) $conn->query($sql);
}

function application_fees_seed_defaults(mysqli $conn): void
{
    if (!$conn) {
        return;
    }

    $defaults = application_fees_defaults();
    $stmt = $conn->prepare("INSERT IGNORE INTO application_fee_rates
        (fee_type, title, subtitle, price, link_href)
        VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('application_fees_seed_defaults prepare failed: ' . $conn->error);
        return;
    }

    foreach ($defaults as $type => $fee) {
        $feeType = (string) $type;
        $title = (string) ($fee['title'] ?? '');
        $subtitle = (string) ($fee['subtitle'] ?? '');
        $price = (float) application_fees_normalize_price($fee['price'] ?? 0);
        $href = (string) ($fee['href'] ?? '');
        $stmt->bind_param('sssds', $feeType, $title, $subtitle, $price, $href);
        if (!$stmt->execute()) {
            error_log('application_fees_seed_defaults execute failed: ' . $stmt->error);
        }
    }
    $stmt->close();
}

function application_fees_get_all(?mysqli $conn = null): array
{
    $defaults = application_fees_defaults();
    $close = false;

    if (!$conn) {
        $conn = getDBConnection();
        $close = true;
    }

    if (!$conn) {
        return array_values($defaults);
    }

    if (!application_fees_ensure_table($conn)) {
        if ($close) {
            $conn->close();
        }
        return array_values($defaults);
    }

    application_fees_seed_defaults($conn);

    $fees = $defaults;
    $result = $conn->query("SELECT fee_type, title, subtitle, price, link_href, updated_at
        FROM application_fee_rates
        WHERE fee_type IN ('new', 'renewal')");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $type = (string) ($row['fee_type'] ?? '');
            if (!isset($defaults[$type])) {
                continue;
            }
            $fees[$type] = [
                'id' => $type,
                'title' => (string) ($row['title'] ?? $defaults[$type]['title']),
                'subtitle' => (string) ($row['subtitle'] ?? $defaults[$type]['subtitle']),
                'price' => application_fees_normalize_price($row['price'] ?? $defaults[$type]['price']),
                'href' => (string) ($row['link_href'] ?? $defaults[$type]['href']),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
    }

    if ($close) {
        $conn->close();
    }

    return [$fees['new'], $fees['renewal']];
}

function application_fees_get_by_type(?mysqli $conn = null, string $fee_type = 'new'): ?array
{
    foreach (application_fees_get_all($conn) as $fee) {
        if (($fee['id'] ?? '') === $fee_type) {
            return $fee;
        }
    }
    return null;
}

function application_fees_save(?mysqli $conn, string $fee_type, array $data): array
{
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    $defaults = application_fees_defaults();
    if (!isset($defaults[$fee_type])) {
        return ['success' => false, 'message' => 'Invalid fee type.'];
    }

    if (!application_fees_ensure_table($conn)) {
        return ['success' => false, 'message' => 'Failed to prepare fee rates table.'];
    }

    application_fees_seed_defaults($conn);

    $title = trim((string) ($data['title'] ?? $defaults[$fee_type]['title']));
    $subtitle = trim((string) ($data['subtitle'] ?? $defaults[$fee_type]['subtitle']));
    $price = application_fees_normalize_price($data['price'] ?? 0);
    $href = trim((string) ($data['href'] ?? $defaults[$fee_type]['href']));

    if ($title === '') {
        return ['success' => false, 'message' => 'Title is required.'];
    }
    if (function_exists('mb_strlen') && mb_strlen($title) > 120) {
        $title = mb_substr($title, 0, 120);
    } elseif (strlen($title) > 120) {
        $title = substr($title, 0, 120);
    }
    if (function_exists('mb_strlen') && mb_strlen($subtitle) > 255) {
        $subtitle = mb_substr($subtitle, 0, 255);
    } elseif (strlen($subtitle) > 255) {
        $subtitle = substr($subtitle, 0, 255);
    }
    if ($href === '') {
        $href = $defaults[$fee_type]['href'];
    }

    $stmt = $conn->prepare("INSERT INTO application_fee_rates
        (fee_type, title, subtitle, price, link_href)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            subtitle = VALUES(subtitle),
            price = VALUES(price),
            link_href = VALUES(link_href)");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $priceFloat = (float) $price;
    $feeType = (string) $fee_type;
    $stmt->bind_param('sssds', $feeType, $title, $subtitle, $priceFloat, $href);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Failed to save fee rate.' . ($error ? ' ' . $error : '')];
    }

    return [
        'success' => true,
        'message' => ucfirst($fee_type === 'new' ? 'new application' : 'renewal') . ' fee updated successfully.',
        'fee' => [
            'id' => $fee_type,
            'title' => $title,
            'subtitle' => $subtitle,
            'price' => $price,
            'href' => $href,
        ],
    ];
}

function application_fees_save_batch(?mysqli $conn, array $items): array
{
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    $saved = [];
    foreach ($items as $type => $data) {
        $result = application_fees_save($conn, (string) $type, is_array($data) ? $data : []);
        if (!$result['success']) {
            return $result;
        }
        $saved[] = $result['fee'] ?? null;
    }

    return [
        'success' => true,
        'message' => 'Application fees saved successfully.',
        'fees' => array_values(array_filter($saved)),
    ];
}
