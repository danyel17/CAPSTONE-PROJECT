<?php
require_once 'config.php';
require_once 'auth_functions.php';

header('Content-Type: application/json');
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['user_id'];
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

// Validate file type and size
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$file_info = pathinfo($_FILES['profile_pic']['name'] ?? '');
$extension = strtolower($file_info['extension'] ?? '');
if (!in_array($extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit;
}
if (($_FILES['profile_pic']['size'] ?? 0) > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size > 2MB.']);
    exit;
}

$upload_dir = 'uploads/admin/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

$profile_pic_filename = 'admin_' . $admin_id . '_' . time() . '.' . $extension;
$upload_path = $upload_dir . $profile_pic_filename;
if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload.']);
    exit;
}

// Save to DB
$conn = getDBConnection();
$conn->begin_transaction();
try {
    // Ensure admin profile exists
    $check = $conn->prepare('SELECT admin_id FROM admin_profiles WHERE admin_id = ?');
    $check->bind_param('i', $admin_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        $ins = $conn->prepare("INSERT INTO admin_profiles (admin_id, site_name, profile_photo) VALUES (?, 'Market Clearance Portal', NULL)");
        $ins->bind_param('i', $admin_id);
        if (!$ins->execute()) {
            throw new Exception('Failed to create admin profile.');
        }
        $ins->close();
    }

    $stmt = $conn->prepare('UPDATE admin_profiles SET profile_photo=? WHERE admin_id=?');
    $stmt->bind_param('si', $profile_pic_filename, $admin_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update profile in DB.');
    }
    $stmt->close();

    $conn->commit();
    $conn->close();

    // Cache-busting query param so the browser doesn't show an old image
    $file_url = $upload_path . '?v=' . time();

    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated!',
        'profile_pic' => $profile_pic_filename,
        'file_url' => $file_url
    ]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
