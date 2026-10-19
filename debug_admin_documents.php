<?php
/**
 * Debug Script for Admin Panel Document Viewing
 * This script tests the exact query used by the admin panel
 */

require_once 'config.php';

echo "<h2>Admin Panel Document Viewing Debug</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Test with vendor_id = 3 (the one with documents)
    $vendor_id = 3;
    
    echo "<h3>Testing Vendor ID: {$vendor_id}</h3>";
    
    // Test 1: Check if vendor exists
    echo "<h4>Test 1: Vendor Details</h4>";
    $stmt = $conn->prepare("
        SELECT v.*, u.email, u.full_name as user_name 
        FROM vendors v 
        JOIN users u ON v.user_id = u.user_id 
        WHERE v.vendor_id = ?
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if ($vendor) {
        echo "<p>✓ Vendor found: {$vendor['vendor_name']}</p>";
        echo "<p>  - User: {$vendor['user_name']}</p>";
        echo "<p>  - Section: {$vendor['section']}</p>";
        echo "<p>  - Type: {$vendor['application_type']}</p>";
        echo "<p>  - Status: {$vendor['status']}</p>";
    } else {
        echo "<p>✗ Vendor not found</p>";
        exit;
    }
    
    // Test 2: Check documents using the exact query from admin panel
    echo "<h4>Test 2: Documents Query (Admin Panel)</h4>";
    $stmt = $conn->prepare("
        SELECT 
            document_id,
            original_name as document_name,
            file_path as document_path,
            file_type as document_type,
            file_size,
            upload_date as uploaded_date
        FROM application_documents 
        WHERE vendor_id = ? AND is_active = TRUE 
        ORDER BY upload_date DESC
    ");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "<p>✓ Found " . count($documents) . " documents</p>";
    
    if (count($documents) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Document ID</th><th>Name</th><th>Path</th><th>Type</th><th>Size</th><th>Upload Date</th></tr>";
        foreach ($documents as $doc) {
            echo "<tr>";
            echo "<td>{$doc['document_id']}</td>";
            echo "<td>{$doc['document_name']}</td>";
            echo "<td>{$doc['document_path']}</td>";
            echo "<td>{$doc['document_type']}</td>";
            echo "<td>" . formatFileSize($doc['file_size']) . "</td>";
            echo "<td>{$doc['uploaded_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test 3: Check if files exist on disk
        echo "<h4>Test 3: File Existence</h4>";
        foreach ($documents as $doc) {
            $file_path = $doc['document_path'];
            if (file_exists($file_path)) {
                echo "<p>✓ File exists: {$file_path}</p>";
                echo "<p>  - Size: " . filesize($file_path) . " bytes</p>";
            } else {
                echo "<p>✗ File missing: {$file_path}</p>";
            }
        }
        
        // Test 4: Test file access
        echo "<h4>Test 4: File Access Test</h4>";
        foreach ($documents as $doc) {
            $file_path = $doc['document_path'];
            if (file_exists($file_path)) {
                $mime_type = mime_content_type($file_path);
                echo "<p>✓ File accessible: {$doc['document_name']}</p>";
                echo "<p>  - MIME Type: {$mime_type}</p>";
                echo "<p>  - <a href='{$file_path}' target='_blank'>Test Direct Access</a></p>";
            }
        }
        
    } else {
        echo "<p>ℹ No documents found for this vendor</p>";
        
        // Debug: Check all documents
        echo "<h4>Debug: All Documents in Database</h4>";
        $result = $conn->query("SELECT * FROM application_documents ORDER BY upload_date DESC");
        if ($result && $result->num_rows > 0) {
            echo "<p>Found {$result->num_rows} total documents:</p>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Vendor ID</th><th>Original Name</th><th>File Path</th><th>Upload Date</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['document_id']}</td>";
                echo "<td>{$row['vendor_id']}</td>";
                echo "<td>{$row['original_name']}</td>";
                echo "<td>{$row['file_path']}</td>";
                echo "<td>{$row['upload_date']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No documents found in database</p>";
        }
    }
    
    // Test 5: Simulate the exact JSON response
    echo "<h4>Test 5: JSON Response Simulation</h4>";
    $json_response = [
        'success' => true,
        'vendor' => $vendor,
        'documents' => $documents
    ];
    
    echo "<p>JSON Response:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars(json_encode($json_response, JSON_PRETTY_PRINT));
    echo "</pre>";
    
    echo "<h3>Debug Complete!</h3>";
    if (count($documents) > 0) {
        echo "<p style='color: green;'>✓ Documents found - Modal should display them</p>";
        echo "<p>If the modal is still not showing documents, the issue is likely in the JavaScript modal rendering.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No documents found - Check if files were uploaded correctly</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Debug Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3, h4 { color: #333; }
p { margin: 5px 0; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { overflow-x: auto; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
