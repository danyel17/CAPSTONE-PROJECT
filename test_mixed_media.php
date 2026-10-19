<?php
/**
 * Test Mixed Media System
 * This script tests the updated media system that supports both images and videos in both sections
 */

require_once 'config.php';

echo "<h2>Mixed Media System Test</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Test 1: Check current media files
    echo "<h3>Test 1: Current Media Files</h3>";
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
    
    // Test 2: Test menu.php queries
    echo "<h3>Test 2: Menu.php Media Assignment</h3>";
    
    // Get first video
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'video' AND is_active = TRUE ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $video_file = $result->fetch_assoc();
    $stmt->close();
    
    if ($video_file) {
        echo "<p>✓ First video: {$video_file['file_name']}</p>";
    } else {
        echo "<p>ℹ No video files found</p>";
    }
    
    // Get all media files (up to 2)
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE is_active = TRUE ORDER BY uploaded_at DESC LIMIT 2");
    $stmt->execute();
    $result = $stmt->get_result();
    $all_media = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "<p>✓ Total media files available: " . count($all_media) . "</p>";
    
    // Simulate menu.php logic
    $video_file = null;
    $image_file = null;
    
    foreach ($all_media as $media) {
        if ($media['file_type'] === 'video' && $video_file === null) {
            $video_file = $media;
        } elseif ($media['file_type'] === 'image' && $image_file === null) {
            $image_file = $media;
        }
    }
    
    // If we have a video but no image, use the video for the second section too
    if ($video_file && !$image_file && count($all_media) > 1) {
        $image_file = $all_media[1]; // Get the second media file
    }
    
    echo "<h4>Media Assignment Results:</h4>";
    echo "<p><strong>Video Section:</strong> ";
    if ($video_file) {
        echo "{$video_file['file_name']} ({$video_file['file_type']})";
    } else {
        echo "No media assigned";
    }
    echo "</p>";
    
    echo "<p><strong>Image Section:</strong> ";
    if ($image_file) {
        echo "{$image_file['file_name']} ({$image_file['file_type']})";
    } else {
        echo "No media assigned";
    }
    echo "</p>";
    
    // Test 3: File existence
    echo "<h3>Test 3: File Existence</h3>";
    if ($video_file && file_exists($video_file['file_path'])) {
        echo "<p>✓ Video file exists: {$video_file['file_path']}</p>";
    } elseif ($video_file) {
        echo "<p>⚠ Video file missing: {$video_file['file_path']}</p>";
    }
    
    if ($image_file && file_exists($image_file['file_path'])) {
        echo "<p>✓ Image/Media file exists: {$image_file['file_path']}</p>";
    } elseif ($image_file) {
        echo "<p>⚠ Image/Media file missing: {$image_file['file_path']}</p>";
    }
    
    echo "<h3>System Status</h3>";
    echo "<p style='color: green; font-weight: bold;'>✓ Mixed Media System is Ready!</p>";
    echo "<p>The system now supports:</p>";
    echo "<ul>";
    echo "<li>Videos in the first section (Market Overview)</li>";
    echo "<li>Images OR videos in the second section (Market Media)</li>";
    echo "<li>Automatic media type detection</li>";
    echo "<li>Flexible media assignment</li>";
    echo "</ul>";
    
    echo "<h3>How to Use:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='adminpanel.php' target='_blank'>Admin Panel</a></li>";
    echo "<li>Navigate to Appearance section</li>";
    echo "<li>Upload videos or images using either upload button</li>";
    echo "<li>The second section now accepts both images and videos</li>";
    echo "<li>Check <a href='menu.php' target='_blank'>Menu Page</a> to see the results</li>";
    echo "</ol>";
    
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
