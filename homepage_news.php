<?php
/**
 * Homepage Announcements & News cards (4 slots on index.php).
 */

function homepage_news_default_cards(): array
{
    return [
        1 => [
            'card_slot' => 1,
            'image_path' => null,
            'tag_type' => 'imp',
            'tag_label' => 'Important',
            'card_date' => 'April 10, 2026',
            'title' => 'Market Slot Application Now Open',
            'summary' => 'New and renewal applications for market stall slots are now being accepted. Visit the market office or apply online through our portal.',
            'full_details' => "New and renewal applications for market stall slots are now being accepted.\n\nVisit the market office or apply online through our vendor portal. Prepare your required documents and complete your application for review by the market administration.",
        ],
        2 => [
            'card_slot' => 2,
            'image_path' => null,
            'tag_type' => 'notice',
            'tag_label' => 'Notice',
            'card_date' => 'April 5, 2026',
            'title' => 'Market Clearance Schedule',
            'summary' => 'Under construction',
            'full_details' => 'Market clearance schedule details will be posted here soon. Please check back for updates.',
        ],
        3 => [
            'card_slot' => 3,
            'image_path' => null,
            'tag_type' => 'event',
            'tag_label' => 'Event',
            'card_date' => 'March 28, 2026',
            'title' => '2026',
            'summary' => 'Under construction',
            'full_details' => 'Event information for 2026 will be posted here soon.',
        ],
        4 => [
            'card_slot' => 4,
            'image_path' => null,
            'tag_type' => 'update',
            'tag_label' => 'Update',
            'card_date' => 'March 15, 2026',
            'title' => 'Vendor Guidelines',
            'summary' => 'Under Construction',
            'full_details' => 'Updated vendor guidelines will be published here soon.',
        ],
    ];
}

function homepage_news_allowed_tag_types(): array
{
    return ['imp', 'notice', 'event', 'update'];
}

function homepage_news_tag_class(string $tag_type): string
{
    $map = [
        'imp' => 'tag--imp',
        'notice' => 'tag--notice',
        'event' => 'tag--event',
        'update' => 'tag--update',
    ];
    return $map[$tag_type] ?? 'tag--update';
}

function homepage_news_ensure_table(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS homepage_news_cards (
        card_slot TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        image_path VARCHAR(500) DEFAULT NULL,
        tag_type VARCHAR(20) NOT NULL DEFAULT 'update',
        tag_label VARCHAR(80) NOT NULL DEFAULT 'Update',
        card_date VARCHAR(100) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL DEFAULT '',
        summary TEXT,
        full_details LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return (bool) $conn->query($sql);
}

function homepage_news_seed_defaults(mysqli $conn): void
{
    $defaults = homepage_news_default_cards();
    $stmt = $conn->prepare("INSERT IGNORE INTO homepage_news_cards
        (card_slot, image_path, tag_type, tag_label, card_date, title, summary, full_details)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return;
    }
    foreach ($defaults as $slot => $card) {
        $slot = (int) $slot;
        $image = $card['image_path'];
        $tag_type = $card['tag_type'];
        $tag_label = $card['tag_label'];
        $card_date = $card['card_date'];
        $title = $card['title'];
        $summary = $card['summary'];
        $full_details = $card['full_details'];
        $stmt->bind_param('isssssss', $slot, $image, $tag_type, $tag_label, $card_date, $title, $summary, $full_details);
        $stmt->execute();
    }
    $stmt->close();
}

function homepage_news_get_cards(?mysqli $conn = null): array
{
    $defaults = homepage_news_default_cards();
    $close = false;
    if (!$conn) {
        $conn = getDBConnection();
        $close = true;
    }
    if (!$conn) {
        return array_values($defaults);
    }

    homepage_news_ensure_table($conn);
    homepage_news_seed_defaults($conn);

    $cards = $defaults;
    $result = $conn->query("SELECT card_slot, image_path, tag_type, tag_label, card_date, title, summary, full_details
        FROM homepage_news_cards ORDER BY card_slot ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $slot = (int) ($row['card_slot'] ?? 0);
            if ($slot < 1 || $slot > 4) {
                continue;
            }
            $cards[$slot] = [
                'card_slot' => $slot,
                'image_path' => $row['image_path'] ?: null,
                'tag_type' => in_array($row['tag_type'], homepage_news_allowed_tag_types(), true)
                    ? $row['tag_type'] : 'update',
                'tag_label' => (string) ($row['tag_label'] ?? 'Update'),
                'card_date' => (string) ($row['card_date'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'summary' => (string) ($row['summary'] ?? ''),
                'full_details' => (string) ($row['full_details'] ?? ''),
            ];
        }
        $result->free();
    }

    if ($close) {
        $conn->close();
    }

    ksort($cards);
    return array_values($cards);
}

function homepage_news_get_card(mysqli $conn, int $slot): ?array
{
    if ($slot < 1 || $slot > 4) {
        return null;
    }
    $cards = homepage_news_get_cards($conn);
    return $cards[$slot - 1] ?? null;
}

function homepage_news_save_card(mysqli $conn, int $slot, array $data): array
{
    if ($slot < 1 || $slot > 4) {
        return ['success' => false, 'message' => 'Invalid card slot.'];
    }

    homepage_news_ensure_table($conn);
    homepage_news_seed_defaults($conn);

    $tag_type = trim((string) ($data['tag_type'] ?? 'update'));
    if (!in_array($tag_type, homepage_news_allowed_tag_types(), true)) {
        $tag_type = 'update';
    }
    $tag_label = trim((string) ($data['tag_label'] ?? 'Update'));
    $card_date = trim((string) ($data['card_date'] ?? ''));
    $title = trim((string) ($data['title'] ?? ''));
    $summary = trim((string) ($data['summary'] ?? ''));
    $full_details = trim((string) ($data['full_details'] ?? ''));

    if ($title === '') {
        return ['success' => false, 'message' => 'Title is required.'];
    }

    $stmt = $conn->prepare("UPDATE homepage_news_cards
        SET tag_type = ?, tag_label = ?, card_date = ?, title = ?, summary = ?, full_details = ?
        WHERE card_slot = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $stmt->bind_param('ssssssi', $tag_type, $tag_label, $card_date, $title, $summary, $full_details, $slot);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Failed to save card.'];
    }

    return ['success' => true, 'message' => 'News card saved successfully.'];
}

function homepage_news_update_image(mysqli $conn, int $slot, string $image_path): array
{
    if ($slot < 1 || $slot > 4) {
        return ['success' => false, 'message' => 'Invalid card slot.'];
    }

    homepage_news_ensure_table($conn);
    homepage_news_seed_defaults($conn);

    $stmt = $conn->prepare("UPDATE homepage_news_cards SET image_path = ? WHERE card_slot = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $stmt->bind_param('si', $image_path, $slot);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Failed to save image.'];
    }

    return ['success' => true, 'message' => 'Photo uploaded successfully.', 'file_path' => $image_path];
}

function homepage_news_remove_image(mysqli $conn, int $slot): array
{
    if ($slot < 1 || $slot > 4) {
        return ['success' => false, 'message' => 'Invalid card slot.'];
    }

    homepage_news_ensure_table($conn);

    $stmt = $conn->prepare("UPDATE homepage_news_cards SET image_path = NULL WHERE card_slot = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $stmt->bind_param('i', $slot);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Photo removed.']
        : ['success' => false, 'message' => 'Failed to remove photo.'];
}

function homepage_news_image_url(string $pr, ?string $image_path, string $fallback): string
{
    if (!empty($image_path)) {
        $rel = ltrim(str_replace('\\', '/', $image_path), '/');
        $parts = explode('/', $rel);
        return $pr . implode('/', array_map('rawurlencode', $parts));
    }
    return $fallback;
}
