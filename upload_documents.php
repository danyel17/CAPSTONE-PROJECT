<?php
/**
 * Application Document Upload Handler
 * Handles file uploads from application.php and renewal.php
 */

require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $application_type = $_POST['application_type'] ?? 'new'; // 'new' or 'renewal'
    $section = $_POST['section'] ?? '';
    
    if (empty($section)) {
        echo json_encode(['success' => false, 'message' => 'Section is required']);
        exit;
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['documents']) || empty($_FILES['documents']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'No files uploaded']);
        exit;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Get or create vendor record
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE user_id = ? AND status = 'pending' ORDER BY submitted_date DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$vendor) {
        // Create new vendor record
        $stmt = $conn->prepare("INSERT INTO vendors (user_id, vendor_name, section, application_type, status) VALUES (?, ?, ?, ?, 'pending')");
        $vendor_name = $_SESSION['full_name'] ?? 'Unknown Vendor';
        $stmt->bind_param("isss", $user_id, $vendor_name, $section, $application_type);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create vendor record: " . $stmt->error);
        }
        
        $vendor_id = $conn->insert_id;
        $stmt->close();
    } else {
        $vendor_id = $vendor['vendor_id'];
    }
    
    // Process uploaded files
    $uploaded_files = [];
    $upload_dir = $application_type === 'renewal' ? 'uploads/renewals' : 'uploads/applications';
    
    // Ensure upload directory exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $files = $_FILES['documents'];
    $file_count = count($files['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue; // Skip files with errors
        }
        
        $original_name = $files['name'][$i];
        $file_size = $files['size'][$i];
        $file_tmp = $files['tmp_name'][$i];
        $file_type = $files['type'][$i];
        
        // Validate file size (5MB limit)
        if ($file_size > 5 * 1024 * 1024) {
            continue; // Skip files that are too large
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file_type, $allowed_types)) {
            continue; // Skip files with invalid types
        }
        
        // Generate unique filename
        $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . '/' . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $file_path)) {
            // Save file info to database
            $stmt = $conn->prepare("INSERT INTO application_documents (application_id, vendor_id, file_name, original_name, file_path, file_type, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $file_type_short = pathinfo($original_name, PATHINFO_EXTENSION);
            $stmt->bind_param("iissssis", $vendor_id, $vendor_id, $unique_filename, $original_name, $file_path, $file_type_short, $file_size, $file_type);
            
            if ($stmt->execute()) {
                $uploaded_files[] = [
                    'original_name' => $original_name,
                    'file_path' => $file_path,
                    'file_size' => $file_size
                ];
            }
            $stmt->close();
        }
    }
    
    if (empty($uploaded_files)) {
        echo json_encode(['success' => false, 'message' => 'No valid files were uploaded']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => count($uploaded_files) . ' file(s) uploaded successfully',
        'files' => $uploaded_files
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}
