<?php
require_once 'config.php';
require_once 'auth_functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

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

$upload_dir = 'uploads/profiles/images/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

$filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
$path = $upload_dir . $filename;
if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload.']);
    exit;
}

$conn = getDBConnection();
$conn->begin_transaction();
try {
    // Upsert user profile row for vendor
    $check = $conn->prepare("SELECT profile_id FROM user_profiles WHERE user_id=? AND profile_type='vendor'");
    $check->bind_param('i', $user_id);
    $check->execute();
    $res = $check->get_result();
    $exists = $res->num_rows > 0;
    $check->close();

    if ($exists) {
        $upd = $conn->prepare("UPDATE user_profiles SET profile_image=? WHERE user_id=? AND profile_type='vendor'");
        $upd->bind_param('si', $path, $user_id);
        if (!$upd->execute()) throw new Exception('DB update failed');
        $upd->close();
    } else {
        $ins = $conn->prepare("INSERT INTO user_profiles (user_id, profile_type, profile_image, profile_status) VALUES (?, 'vendor', ?, 'active')");
        $ins->bind_param('is', $user_id, $path);
        if (!$ins->execute()) throw new Exception('DB insert failed');
        $ins->close();
    }

    $conn->commit();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Profile photo updated!', 'file_url' => $path . '?v=' . time()]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}



















