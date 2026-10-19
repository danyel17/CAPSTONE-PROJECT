<?php
/**
 * Media Management Setup Script
 * Run this script to set up the enhanced media management system
 */

require_once 'config.php';

echo "<h2>Setting up Enhanced Media Management System</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Read and execute the media management schema
    $schema_sql = file_get_contents('media_management_schema.sql');
    
    if (!$schema_sql) {
        throw new Exception("Could not read media_management_schema.sql");
    }
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schema_sql)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            if ($conn->query($statement)) {
                $success_count++;
                echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
            } else {
                $error_count++;
                echo "<p>✗ Error executing: " . substr($statement, 0, 50) . "... - " . $conn->error . "</p>";
            }
        } catch (Exception $e) {
            $error_count++;
            echo "<p>✗ Exception executing: " . substr($statement, 0, 50) . "... - " . $e->getMessage() . "</p>";
        }
    }
    
    // Create necessary directories
    $directories = [
        'assets/videos',
        'assets/imgs',
        'assets/thumbnails',
        'uploads/media'
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
    
    // Test media management functionality
    echo "<h3>Testing Media Management Functions</h3>";
    
    require_once 'media_functions.php';
    
    try {
        $manager = new MediaManager();
        $categories = $manager->getCategories();
        echo "<p>✓ Media categories loaded: " . count($categories) . " categories</p>";
        
        $sections = $manager->getSections();
        echo "<p>✓ Media sections loaded: " . count($sections) . " sections</p>";
        
        $settings = $manager->getMediaSettings();
        echo "<p>✓ Media settings loaded: " . count($settings) . " settings</p>";
        
        echo "<p>✓ Media management system is working correctly!</p>";
        
    } catch (Exception $e) {
        echo "<p>✗ Error testing media management: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>Setup Summary</h3>";
    echo "<p>✓ SQL statements executed successfully: {$success_count}</p>";
    echo "<p>✗ SQL statements with errors: {$error_count}</p>";
    
    if ($error_count === 0) {
        echo "<h3 style='color: green;'>🎉 Media Management System Setup Complete!</h3>";
        echo "<p>You can now:</p>";
        echo "<ul>";
        echo "<li>Upload and organize media files by category and section</li>";
        echo "<li>Set featured media for different sections</li>";
        echo "<li>Manage media through the admin panel</li>";
        echo "<li>Display organized media on the homepage</li>";
        echo "</ul>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ol>";
        echo "<li>Go to the admin panel</li>";
        echo "<li>Navigate to the Appearance section</li>";
        echo "<li>Upload your first media files</li>";
        echo "<li>Set them as featured for the homepage</li>";
        echo "</ol>";
    } else {
        echo "<h3 style='color: orange;'>⚠️ Setup completed with some errors</h3>";
        echo "<p>Please check the error messages above and fix any issues.</p>";
    }
    
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
ul, ol { margin: 10px 0; padding-left: 20px; }
</style>
