<?php
/**
 * Create Application Documents Table
 * This script creates a table to store uploaded documents from applications and renewals
 */

require_once 'config.php';

echo "<h2>Creating Application Documents Table</h2>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection established</p>";
    
    // Check if application_documents table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'application_documents'");
    
    if ($table_check->num_rows === 0) {
        echo "<p>Creating application_documents table...</p>";
        
        // Create the table
        $create_table_sql = "
        CREATE TABLE application_documents (
            document_id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            vendor_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size BIGINT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (application_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
            INDEX idx_application_id (application_id),
            INDEX idx_vendor_id (vendor_id),
            INDEX idx_upload_date (upload_date),
            INDEX idx_is_active (is_active)
        )";
        
        if ($conn->query($create_table_sql)) {
            echo "<p>✓ application_documents table created successfully</p>";
        } else {
            throw new Exception("Failed to create application_documents table: " . $conn->error);
        }
    } else {
        echo "<p>✓ application_documents table already exists</p>";
    }
    
    // Create uploads directory structure
    $directories = [
        'uploads/applications',
        'uploads/renewals'
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
    
    // Show current table structure
    echo "<h3>Current Table Structure:</h3>";
    $result = $conn->query("DESCRIBE application_documents");
    if ($result) {
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
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p>The application documents system is ready to store and manage uploaded files.</p>";
    
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
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>
