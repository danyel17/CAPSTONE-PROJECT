<?php
/**
 * Media Management Database Setup and Test Script
 * This script ensures the media_files table exists and is properly configured
 */

require_once 'config.php';

echo "<h2>Media Management Database Setup</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Check if media_files table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'media_files'");
    
    if ($table_check->num_rows === 0) {
        echo "<p>Creating media_files table...</p>";
        
        // Create the table
        $create_table_sql = "
        CREATE TABLE media_files (
            media_id INT AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type ENUM('video', 'image') NOT NULL,
            file_size BIGINT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            display_order INT DEFAULT 0,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_file_type (file_type),
            INDEX idx_is_active (is_active),
            INDEX idx_display_order (display_order)
        )";
        
        if ($conn->query($create_table_sql)) {
            echo "<p>✓ media_files table created successfully</p>";
        } else {
            throw new Exception("Failed to create media_files table: " . $conn->error);
        }
    } else {
        echo "<p>✓ media_files table already exists</p>";
    }
    
    // Create necessary directories
    $directories = [
        'assets/videos',
        'assets/imgs'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "<p>✓ Created directory: {$dir}</p>";
            } else {
                echo "<p>✗ Failed to create directory: {$dir}</p>";
            }
        } else {
            echo "<p>✓ Directory already exists: {$dir}</p>";
        }
    }
    
    // Test database operations
    echo "<h3>Testing Database Operations</h3>";
    
    // Check if there are any media files
    $result = $conn->query("SELECT COUNT(*) as count FROM media_files WHERE is_active = TRUE");
    $count = $result->fetch_assoc()['count'];
    echo "<p>✓ Current active media files: {$count}</p>";
    
    // Test video query
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'video' AND is_active = TRUE ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $video_file = $result->fetch_assoc();
    $stmt->close();
    
    if ($video_file) {
        echo "<p>✓ Latest video file: {$video_file['file_name']}</p>";
        echo "<p>  - Path: {$video_file['file_path']}</p>";
        echo "<p>  - Title: {$video_file['title']}</p>";
    } else {
        echo "<p>ℹ No video files found</p>";
    }
    
    // Test image query
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'image' AND is_active = TRUE ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $image_file = $result->fetch_assoc();
    $stmt->close();
    
    if ($image_file) {
        echo "<p>✓ Latest image file: {$image_file['file_name']}</p>";
        echo "<p>  - Path: {$image_file['file_path']}</p>";
        echo "<p>  - Title: {$image_file['title']}</p>";
    } else {
        echo "<p>ℹ No image files found</p>";
    }
    
    // Test file existence
    if ($video_file && file_exists($video_file['file_path'])) {
        echo "<p>✓ Video file exists on disk</p>";
    } elseif ($video_file) {
        echo "<p>⚠ Video file missing from disk: {$video_file['file_path']}</p>";
    }
    
    if ($image_file && file_exists($image_file['file_path'])) {
        echo "<p>✓ Image file exists on disk</p>";
    } elseif ($image_file) {
        echo "<p>⚠ Image file missing from disk: {$image_file['file_path']}</p>";
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p>Your media management system is ready to use.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Go to Admin Panel → Appearance</li>";
    echo "<li>Upload a video file using 'Upload New Video'</li>";
    echo "<li>Upload an image file using 'Upload New Image'</li>";
    echo "<li>Check menu.php to see the uploaded media</li>";
    echo "</ol>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Setup Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
ol { margin: 10px 0; padding-left: 20px; }
</style>
