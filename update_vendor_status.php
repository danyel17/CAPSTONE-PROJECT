<?php
require_once 'config.php';
require_once 'auth_functions.php';
// Only admins can update application status
requireAdminLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
$vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
$new_status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';
if ($vendor_id <= 0 || !in_array($new_status, ['pending','approved','rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid vendor ID or status value.']);
    exit;
}
$conn = getDBConnection();
$conn->begin_transaction();
try {
    // Check if vendor exists
    $stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id=? FOR UPDATE");
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    if (!$vendor) {
        $conn->rollback();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Vendor not found.']);
        exit;
    }
    // Actually update status
    $admin_id = $_SESSION['user_id'];
    $stmt2 = $conn->prepare("UPDATE vendors SET status=?, reviewed_by=?, reviewed_date=NOW() WHERE vendor_id=?");
    $stmt2->bind_param("sii", $new_status, $admin_id, $vendor_id);
    if (!$stmt2->execute() || $stmt2->affected_rows < 1) {
        $conn->rollback();
        $stmt2->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update vendor status.']);
        exit;
    }
    $stmt2->close();
    $conn->commit();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Status updated!', 'new_status' => $new_status]);
    exit;

} catch(Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}



















