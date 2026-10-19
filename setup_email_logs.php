<?php
/**
 * Setup Email Logs Table
 * This script will create the email_logs table in your database
 * Run this once to set up the email logging system
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Setup Email Logs Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Setup Email Logs Table</h1>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    echo "<div class='info'>✓ Connected to database: " . DB_NAME . "</div>";
    
    // Read SQL file
    $sql_file = __DIR__ . '/setup_email_logs.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("SQL file not found: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Remove comments and empty lines for cleaner execution
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*$/m', '', $sql);
    
    echo "<div class='info'>✓ SQL file loaded</div>";
    echo "<div class='info'>Executing SQL...</div>";
    
    // Execute SQL
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        echo "<div class='success'>✓ Email logs table created successfully!</div>";
        
        // Verify table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'email_logs'");
        if ($check_table && $check_table->num_rows > 0) {
            echo "<div class='success'>✓ Table 'email_logs' verified in database</div>";
            
            // Show table structure
            $describe = $conn->query("DESCRIBE email_logs");
            if ($describe) {
                echo "<h2>Table Structure:</h2>";
                echo "<pre>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                while ($row = $describe->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</pre>";
            }
        } else {
            echo "<div class='error'>⚠ Table may not have been created. Please check manually.</div>";
        }
        
    } else {
        throw new Exception("Error executing SQL: " . $conn->error);
    }
    
    $conn->close();
    
    echo "<div class='info'><strong>Note:</strong> The email logs table will also be created automatically when you use the forgot password feature. This script is just for manual setup.</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    
    if (isset($conn) && $conn->error) {
        echo "<div class='error'>Database Error: " . htmlspecialchars($conn->error) . "</div>";
    }
}

echo "</div></body></html>";
?>






