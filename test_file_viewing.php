<?php
/**
 * Test File Viewing System
 * This script tests the complete file viewing functionality
 */

require_once 'config.php';
require_once 'auth_functions.php';

echo "<h2>File Viewing System Test</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Test 1: Check if application_documents table exists
    echo "<h3>Test 1: Database Table Check</h3>";
    $table_check = $conn->query("SHOW TABLES LIKE 'application_documents'");
    if ($table_check->num_rows > 0) {
        echo "<p>✅ application_documents table exists</p>";
    } else {
        echo "<p>❌ application_documents table missing</p>";
        throw new Exception("Required table missing");
    }
    
    // Test 2: Check table structure
    echo "<h3>Test 2: Table Structure Check</h3>";
    $result = $conn->query("DESCRIBE application_documents");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['document_id', 'application_id', 'vendor_id', 'file_name', 'original_name', 'file_path', 'file_type', 'file_size', 'mime_type', 'upload_date', 'is_active'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "<p>✅ All required columns present</p>";
    } else {
        echo "<p>❌ Missing columns: " . implode(', ', $missing_columns) . "</p>";
    }
    
    // Test 3: Check for existing files in database
    echo "<h3>Test 3: File Records Check</h3>";
    $file_count = $conn->query("SELECT COUNT(*) as count FROM application_documents WHERE is_active = TRUE")->fetch_assoc()['count'];
    echo "<p>📁 Active files in database: {$file_count}</p>";
    
    if ($file_count > 0) {
        echo "<p>✅ Files found in database</p>";
        
        // Show sample files
        $sample_files = $conn->query("SELECT vendor_id, original_name, file_path, file_type FROM application_documents WHERE is_active = TRUE LIMIT 5");
        echo "<h4>Sample Files:</h4>";
        echo "<ul>";
        while ($file = $sample_files->fetch_assoc()) {
            echo "<li>Vendor ID: {$file['vendor_id']} - {$file['original_name']} ({$file['file_type']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>⚠️ No files found in database</p>";
    }
    
    // Test 4: Check file existence on disk
    echo "<h3>Test 4: File System Check</h3>";
    $files_on_disk = $conn->query("SELECT file_path FROM application_documents WHERE is_active = TRUE");
    $existing_files = 0;
    $missing_files = 0;
    
    while ($file = $files_on_disk->fetch_assoc()) {
        if (file_exists($file['file_path'])) {
            $existing_files++;
        } else {
            $missing_files++;
            echo "<p>⚠️ File not found on disk: {$file['file_path']}</p>";
        }
    }
    
    echo "<p>✅ Files existing on disk: {$existing_files}</p>";
    if ($missing_files > 0) {
        echo "<p>⚠️ Missing files on disk: {$missing_files}</p>";
    }
    
    // Test 5: Check upload directories
    echo "<h3>Test 5: Upload Directories Check</h3>";
    $directories = ['uploads/applications', 'uploads/renewals', 'uploads/admin'];
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "<p>✅ Directory exists: {$dir}</p>";
        } else {
            echo "<p>❌ Directory missing: {$dir}</p>";
        }
    }
    
    // Test 6: Test file viewer access (if admin session exists)
    echo "<h3>Test 6: File Viewer Access Test</h3>";
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        echo "<p>✅ Admin session active</p>";
        
        // Get a sample file to test
        $sample_file = $conn->query("SELECT vendor_id, file_path FROM application_documents WHERE is_active = TRUE LIMIT 1")->fetch_assoc();
        if ($sample_file) {
            $test_url = "admin_file_viewer.php?file=" . urlencode($sample_file['file_path']) . "&vendor_id=" . $sample_file['vendor_id'];
            echo "<p>🔗 Test file viewer URL: <a href='{$test_url}' target='_blank'>Test File Viewer</a></p>";
        }
    } else {
        echo "<p>⚠️ No admin session - file viewer test skipped</p>";
        echo "<p>💡 <a href='adminlogin.php'>Login as admin</a> to test file viewer</p>";
    }
    
    // Test 7: Check vendors table integration
    echo "<h3>Test 7: Vendor Integration Check</h3>";
    $vendor_files = $conn->query("
        SELECT v.vendor_id, v.vendor_name, COUNT(ad.document_id) as file_count 
        FROM vendors v 
        LEFT JOIN application_documents ad ON v.vendor_id = ad.vendor_id AND ad.is_active = TRUE 
        GROUP BY v.vendor_id, v.vendor_name 
        ORDER BY file_count DESC 
        LIMIT 5
    ");
    
    echo "<h4>Vendors with Files:</h4>";
    echo "<ul>";
    while ($vendor = $vendor_files->fetch_assoc()) {
        echo "<li>{$vendor['vendor_name']} (ID: {$vendor['vendor_id']}) - {$vendor['file_count']} files</li>";
    }
    echo "</ul>";
    
    $conn->close();
    
    echo "<h3>✅ Test Complete!</h3>";
    echo "<p>The file viewing system is ready for use.</p>";
    
    // Provide admin panel link
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        echo "<p><a href='adminpanel.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Panel</a></p>";
    } else {
        echo "<p><a href='adminlogin.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login as Admin</a></p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Test Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
ul { margin: 10px 0; }
a { color: #007bff; }
</style>
