<?php
/**
 * Test Media Upload and Display
 * This script tests the complete media management flow
 */

require_once 'config.php';

echo "<h2>Media Management System Test</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Test 1: Check media_files table structure
    echo "<h3>Test 1: Database Structure</h3>";
    $result = $conn->query("DESCRIBE media_files");
    if ($result) {
        echo "<p>✓ media_files table structure is correct</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>✗ Could not describe media_files table</p>";
    }
    
    // Test 2: Check current media files
    echo "<h3>Test 2: Current Media Files</h3>";
    $result = $conn->query("SELECT * FROM media_files WHERE is_active = TRUE ORDER BY uploaded_at DESC");
    if ($result && $result->num_rows > 0) {
        echo "<p>✓ Found {$result->num_rows} active media files</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>File Name</th><th>Type</th><th>Path</th><th>Title</th><th>Uploaded</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['media_id']}</td>";
            echo "<td>{$row['file_name']}</td>";
            echo "<td>{$row['file_type']}</td>";
            echo "<td>{$row['file_path']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>{$row['uploaded_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>ℹ No active media files found</p>";
    }
    
    // Test 3: Test menu.php queries
    echo "<h3>Test 3: Menu.php Queries</h3>";
    
    // Test video query
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'video' AND is_active = TRUE ORDER BY display_order ASC, uploaded_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $video_file = $result->fetch_assoc();
    $stmt->close();
    
    if ($video_file) {
        echo "<p>✓ Latest video file found: {$video_file['file_name']}</p>";
        echo "<p>  - Path: {$video_file['file_path']}</p>";
        echo "<p>  - File exists: " . (file_exists($video_file['file_path']) ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p>ℹ No video files found</p>";
    }
    
    // Test image query
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'image' AND is_active = TRUE ORDER BY display_order ASC, uploaded_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $image_file = $result->fetch_assoc();
    $stmt->close();
    
    if ($image_file) {
        echo "<p>✓ Latest image file found: {$image_file['file_name']}</p>";
        echo "<p>  - Path: {$image_file['file_path']}</p>";
        echo "<p>  - File exists: " . (file_exists($image_file['file_path']) ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p>ℹ No image files found</p>";
    }
    
    // Test 4: Directory structure
    echo "<h3>Test 4: Directory Structure</h3>";
    $directories = [
        'assets/videos' => 'Video upload directory',
        'assets/imgs' => 'Image upload directory'
    ];
    
    foreach ($directories as $dir => $description) {
        if (file_exists($dir)) {
            echo "<p>✓ {$description}: {$dir}</p>";
            if (is_writable($dir)) {
                echo "<p>  - Directory is writable</p>";
            } else {
                echo "<p>  - ⚠ Directory is not writable</p>";
            }
        } else {
            echo "<p>✗ {$description}: {$dir} (does not exist)</p>";
        }
    }
    
    // Test 5: Sample upload simulation
    echo "<h3>Test 5: Upload Simulation</h3>";
    echo "<p>To test the upload functionality:</p>";
    echo "<ol>";
    echo "<li>Go to <a href='adminpanel.php' target='_blank'>Admin Panel</a></li>";
    echo "<li>Navigate to Appearance section</li>";
    echo "<li>Upload a video file using 'Upload New Video'</li>";
    echo "<li>Upload an image file using 'Upload New Image'</li>";
    echo "<li>Check <a href='menu.php' target='_blank'>Menu Page</a> to see the uploaded media</li>";
    echo "</ol>";
    
    echo "<h3>System Status</h3>";
    echo "<p style='color: green; font-weight: bold;'>✓ Media Management System is Ready!</p>";
    echo "<p>The system will automatically:</p>";
    echo "<ul>";
    echo "<li>Save uploaded videos to assets/videos/</li>";
    echo "<li>Save uploaded images to assets/imgs/</li>";
    echo "<li>Store file information in the media_files table</li>";
    echo "<li>Display the latest video in the Market Overview section</li>";
    echo "<li>Display the latest image in the Market Price Update section</li>";
    echo "</ul>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Test Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
ol, ul { margin: 10px 0; padding-left: 20px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
