<?php
/**
 * Client Satisfaction Rating endpoint.
 * Receives the survey (client profile + 10 criteria + feedback) from
 * application.php / renewal.php and stores it in client_ratings.
 * Responds with JSON.
 */
require_once 'config.php';
require_once 'auth_functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS client_ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    source VARCHAR(20) DEFAULT 'application',
    client_name VARCHAR(255) NULL,
    sex VARCHAR(10) NULL,
    age_group VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    contact_no VARCHAR(50) NULL,
    client_class VARCHAR(60) NULL,
    service_availed VARCHAR(255) NULL,
    transaction_date VARCHAR(50) NULL,
    q1 TINYINT NULL, q2 TINYINT NULL, q3 TINYINT NULL, q4 TINYINT NULL, q5 TINYINT NULL,
    q6 TINYINT NULL, q7 TINYINT NULL, q8 TINYINT NULL, q9 TINYINT NULL, q10 TINYINT NULL,
    overall_avg DECIMAL(3,2) NULL,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// One rating per user (ever): reject duplicates for logged-in users.
if ($user_id > 0) {
    $dupStmt = $conn->prepare("SELECT 1 FROM client_ratings WHERE user_id = ? LIMIT 1");
    if ($dupStmt) {
        $dupStmt->bind_param("i", $user_id);
        $dupStmt->execute();
        $dupStmt->store_result();
        $already = $dupStmt->num_rows > 0;
        $dupStmt->close();
        if ($already) {
            echo json_encode(['success' => true, 'message' => 'You have already submitted a rating. Thank you!', 'already' => true]);
            $conn->close();
            exit;
        }
    }
}

$clean = static function ($key, $max = 255) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    if ($v === '') return null;
    return mb_substr($v, 0, $max);
};

$source = $_POST['source'] ?? 'application';
if (!in_array($source, ['application', 'renewal'], true)) {
    $source = 'application';
}

$client_name    = $clean('client_name');
$sex            = $clean('sex', 10);
$age_group      = $clean('age_group', 30);
$address        = $clean('address');
$email          = $clean('email');
$contact_no     = $clean('contact_no', 50);
$client_class   = $clean('client_class', 60);
$service_availed = $clean('service_availed');
$transaction_date = $clean('transaction_date', 50);
$comments       = $clean('comments', 2000);

// Ratings q1..q10 (1-5)
$scores = [];
$sum = 0;
$count = 0;
for ($i = 1; $i <= 10; $i++) {
    $raw = isset($_POST['q' . $i]) ? (int) $_POST['q' . $i] : 0;
    if ($raw < 1 || $raw > 5) {
        $scores[$i] = null;
    } else {
        $scores[$i] = $raw;
        $sum += $raw;
        $count++;
    }
}

if ($count === 0) {
    echo json_encode(['success' => false, 'message' => 'Please rate at least one criterion.']);
    $conn->close();
    exit;
}

$overall_avg = round($sum / $count, 2);

$stmt = $conn->prepare("INSERT INTO client_ratings
    (user_id, source, client_name, sex, age_group, address, email, contact_no, client_class, service_availed, transaction_date,
     q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, overall_avg, comments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    $conn->close();
    exit;
}

$uid = $user_id > 0 ? $user_id : null;
$stmt->bind_param(
    "issssssssssiiiiiiiiiids",
    $uid, $source, $client_name, $sex, $age_group, $address, $email, $contact_no, $client_class, $service_availed, $transaction_date,
    $scores[1], $scores[2], $scores[3], $scores[4], $scores[5],
    $scores[6], $scores[7], $scores[8], $scores[9], $scores[10],
    $overall_avg, $comments
);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Thank you for your feedback!', 'rating_id' => $new_id]);
} else {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Failed to save rating: ' . $err]);
}
