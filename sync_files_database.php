<?php
/**
 * File Database Sync - Sync existing files with database
 * This script will scan the uploads directory and populate the application_documents table
 * with existing files that weren't properly recorded
 */

require_once 'config.php';

echo "<h2>File Database Sync</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Scan uploads/applications directory
    $applications_dir = 'uploads/applications/';
    $renewals_dir = 'uploads/renewals/';
    
    $files_processed = 0;
    $files_added = 0;
    $files_skipped = 0;
    
    // Process applications directory
    if (is_dir($applications_dir)) {
        echo "<h3>Processing Applications Directory</h3>";
        $files = scandir($applications_dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $applications_dir . $file;
            $files_processed++;
            
            // Parse filename to extract vendor_id and timestamp
            // Format: {vendor_id}_{timestamp}_{index}.{ext}
            if (preg_match('/^(\d+)_(\d+)_(\d+)\.(.+)$/', $file, $matches)) {
                $vendor_id = (int)$matches[1];
                $timestamp = $matches[2];
                $index = $matches[3];
                $extension = $matches[4];
                
                // Check if this file is already in database
                $check_stmt = $conn->prepare("SELECT document_id FROM application_documents WHERE file_path = ?");
                $check_stmt->bind_param("s", $file_path);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $check_stmt->close();
                
                if ($result->num_rows > 0) {
                    echo "<p>⏭️ Skipped (already in DB): {$file}</p>";
                    $files_skipped++;
                    continue;
                }
                
                // Check if vendor exists
                $vendor_stmt = $conn->prepare("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_id = ?");
                $vendor_stmt->bind_param("i", $vendor_id);
                $vendor_stmt->execute();
                $vendor_result = $vendor_stmt->get_result();
                $vendor_stmt->close();
                
                if ($vendor_result->num_rows === 0) {
                    echo "<p>⚠️ Vendor not found for file: {$file}</p>";
                    continue;
                }
                
                $vendor = $vendor_result->fetch_assoc();
                
                // Get file info
                $file_size = filesize($file_path);
                $mime_type = mime_content_type($file_path);
                
                // Generate original filename
                $original_name = $vendor['vendor_name'] . '_document_' . $index . '.' . $extension;
                
                // Insert into database
                $insert_stmt = $conn->prepare("
                    INSERT INTO application_documents 
                    (application_id, vendor_id, file_name, original_name, file_path, file_type, file_size, mime_type, upload_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))
                ");
                
                $file_type = $extension;
                $insert_stmt->bind_param("iissssssi", $vendor_id, $vendor_id, $file, $original_name, $file_path, $file_type, $file_size, $mime_type, $timestamp);
                
                if ($insert_stmt->execute()) {
                    echo "<p>✅ Added: {$file} (Vendor: {$vendor['vendor_name']})</p>";
                    $files_added++;
                } else {
                    echo "<p>❌ Failed to add: {$file} - " . $insert_stmt->error . "</p>";
                }
                
                $insert_stmt->close();
            } else {
                echo "<p>⚠️ Invalid filename format: {$file}</p>";
            }
        }
    }
    
    // Process renewals directory
    if (is_dir($renewals_dir)) {
        echo "<h3>Processing Renewals Directory</h3>";
        $files = scandir($renewals_dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $renewals_dir . $file;
            $files_processed++;
            
            // Parse filename to extract vendor_id and timestamp
            if (preg_match('/^(\d+)_(\d+)_(\d+)\.(.+)$/', $file, $matches)) {
                $vendor_id = (int)$matches[1];
                $timestamp = $matches[2];
                $index = $matches[3];
                $extension = $matches[4];
                
                // Check if this file is already in database
                $check_stmt = $conn->prepare("SELECT document_id FROM application_documents WHERE file_path = ?");
                $check_stmt->bind_param("s", $file_path);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $check_stmt->close();
                
                if ($result->num_rows > 0) {
                    echo "<p>⏭️ Skipped (already in DB): {$file}</p>";
                    $files_skipped++;
                    continue;
                }
                
                // Check if vendor exists
                $vendor_stmt = $conn->prepare("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_id = ?");
                $vendor_stmt->bind_param("i", $vendor_id);
                $vendor_stmt->execute();
                $vendor_result = $vendor_stmt->get_result();
                $vendor_stmt->close();
                
                if ($vendor_result->num_rows === 0) {
                    echo "<p>⚠️ Vendor not found for file: {$file}</p>";
                    continue;
                }
                
                $vendor = $vendor_result->fetch_assoc();
                
                // Get file info
                $file_size = filesize($file_path);
                $mime_type = mime_content_type($file_path);
                
                // Generate original filename
                $original_name = $vendor['vendor_name'] . '_renewal_document_' . $index . '.' . $extension;
                
                // Insert into database
                $insert_stmt = $conn->prepare("
                    INSERT INTO application_documents 
                    (application_id, vendor_id, file_name, original_name, file_path, file_type, file_size, mime_type, upload_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))
                ");
                
                $file_type = $extension;
                $insert_stmt->bind_param("iissssssi", $vendor_id, $vendor_id, $file, $original_name, $file_path, $file_type, $file_size, $mime_type, $timestamp);
                
                if ($insert_stmt->execute()) {
                    echo "<p>✅ Added: {$file} (Vendor: {$vendor['vendor_name']})</p>";
                    $files_added++;
                } else {
                    echo "<p>❌ Failed to add: {$file} - " . $insert_stmt->error . "</p>";
                }
                
                $insert_stmt->close();
            } else {
                echo "<p>⚠️ Invalid filename format: {$file}</p>";
            }
        }
    }
    
    echo "<h3>Sync Summary</h3>";
    echo "<p>📊 Files processed: {$files_processed}</p>";
    echo "<p>✅ Files added to database: {$files_added}</p>";
    echo "<p>⏭️ Files skipped (already in DB): {$files_skipped}</p>";
    
    // Show current database status
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM application_documents WHERE is_active = TRUE");
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    echo "<p>📁 Total active files in database: {$count}</p>";
    
    $conn->close();
    
    echo "<h3>✅ Sync Complete!</h3>";
    echo "<p>All existing files have been synchronized with the database.</p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Sync Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
</style>
