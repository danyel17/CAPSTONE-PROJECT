<?php
/**
 * User-facing API: returns application/vendor details and documents.
 * Verifies vendor belongs to logged-in user.
 */
require_once 'config.php';
require_once 'auth_functions.php';

header('Content-Type: application/json');

requireLogin();

$vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
if (!$vendor_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'application_documents'");
    $use_application_documents = ($table_check && $table_check->num_rows > 0);

    $stmt = $conn->prepare("
        SELECT v.*, u.email, u.full_name as user_name
        FROM vendors v
        JOIN users u ON v.user_id = u.user_id
        WHERE v.vendor_id = ? AND v.user_id = ?
    ");
    if (!$stmt) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        exit;
    }
    $stmt->bind_param("ii", $vendor_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();

    if (!$vendor) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Application not found or access denied.']);
        exit;
    }

    $documents = [];
    if ($use_application_documents) {
        $stmt = $conn->prepare("
            SELECT document_id, original_name as document_name, file_path as document_path,
                   file_type as document_type, mime_type, file_size, upload_date as uploaded_date
            FROM application_documents
            WHERE vendor_id = ? AND is_active = TRUE
            ORDER BY upload_date ASC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT document_id, document_name, document_path, document_type,
                   '' as mime_type, 0 as file_size, uploaded_date
            FROM vendor_documents
            WHERE vendor_id = ?
            ORDER BY uploaded_date ASC
        ");
    }
    if ($stmt) {
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $documents = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $conn->close();

    if (ob_get_level()) ob_clean();
    echo json_encode([
        'success' => true,
        'vendor' => $vendor,
        'documents' => $documents
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    if (ob_get_level()) ob_clean();
    error_log("get_application_details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred.']);
}
