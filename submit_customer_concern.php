<?php
/**
 * Accepts POST message from customer support widget; stores in customer_concerns.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter your message.']);
    exit;
}

if (strlen($message) > 5000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long (max 5000 characters).']);
    exit;
}

$user_id = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$name = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : '';
$email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';

if ($name === '' && isset($_POST['full_name'])) {
    $name = trim((string) $_POST['full_name']);
}
if ($name === '' && isset($_POST['submitter_name'])) {
    $name = trim((string) $_POST['submitter_name']);
}
if ($email === '' && isset($_POST['email'])) {
    $email = trim((string) $_POST['email']);
}

if ($name !== '' && strlen($name) > 255) {
    $name = substr($name, 0, 255);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if ($email !== '' && strlen($email) > 255) {
    $email = substr($email, 0, 255);
}

$name = $name !== '' ? $name : null;
$email = $email !== '' ? $email : null;
$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
$ip = strlen($ip) > 45 ? substr($ip, 0, 45) : $ip;

$conn = getDBConnection();

try {
    $conn->query("CREATE TABLE IF NOT EXISTS customer_concerns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        submitter_name VARCHAR(255) NULL,
        submitter_email VARCHAR(255) NULL,
        message TEXT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($user_id > 0) {
        $stmt = $conn->prepare(
            'INSERT INTO customer_concerns (user_id, submitter_name, submitter_email, message, ip_address) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param('issss', $user_id, $name, $email, $message, $ip);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO customer_concerns (user_id, submitter_name, submitter_email, message, ip_address) VALUES (NULL, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param('ssss', $name, $email, $message, $ip);
    }

    if (!$stmt->execute()) {
        $execErr = $stmt->error;
        $stmt->close();
        throw new Exception($execErr !== '' ? $execErr : 'Insert failed');
    }
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Thank you. We received your message and will review it soon.']);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->close();
    }
    error_log('submit_customer_concern: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not save your message. Please try again later.']);
}
