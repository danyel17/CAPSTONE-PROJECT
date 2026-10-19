<?php
/**
 * Add unique constraint to prevent duplicate applications per user
 * This ensures at the database level that each user can only have ONE application of each type
 */

require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed.");
}

echo "Adding unique constraint to prevent duplicate applications...\n";

try {
    // First, let's check if there are existing duplicates and clean them up
    echo "Checking for existing duplicates...\n";
    
    // Find duplicate applications (same user, same type)
    $duplicate_query = "
        SELECT user_id, application_type, COUNT(*) as count
        FROM vendors
        GROUP BY user_id, application_type
        HAVING COUNT(*) > 1
    ";
    
    $result = $conn->query($duplicate_query);
    $duplicates = [];
    
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
    }
    
    if (count($duplicates) > 0) {
        echo "Found " . count($duplicates) . " users with duplicate applications. Cleaning up...\n";
        
        // For each duplicate, keep the most recent one and delete the rest
        foreach ($duplicates as $dup) {
            $keep_query = "
                SELECT vendor_id FROM vendors 
                WHERE user_id = ? AND application_type = ?
                ORDER BY submitted_date DESC LIMIT 1
            ";
            $stmt = $conn->prepare($keep_query);
            $stmt->bind_param("is", $dup['user_id'], $dup['application_type']);
            $stmt->execute();
            $keep_result = $stmt->get_result();
            $keep_vendor = $keep_result->fetch_assoc();
            $keep_id = $keep_vendor['vendor_id'];
            $stmt->close();
            
            // Delete all others
            $delete_query = "
                DELETE FROM vendors 
                WHERE user_id = ? AND application_type = ? AND vendor_id != ?
            ";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("isi", $dup['user_id'], $dup['application_type'], $keep_id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            echo "User {$dup['user_id']} ({$dup['application_type']}): Kept #$keep_id, deleted $deleted duplicates\n";
        }
    } else {
        echo "No duplicates found. Database is clean.\n";
    }
    
    // Now add a unique index to prevent future duplicates
    // Note: MySQL doesn't support unique constraints with NULL values well, so we'll use a unique index
    echo "\nAdding unique index...\n";
    
    // Drop existing index if it exists
    try {
        $conn->query("ALTER TABLE vendors DROP INDEX IF EXISTS idx_user_application_type");
    } catch (Exception $e) {
        // Index might not exist, that's okay
    }
    
    // Create unique index on (user_id, application_type)
    // This ensures each user can only have ONE application of each type
    $conn->query("CREATE UNIQUE INDEX idx_user_application_type ON vendors (user_id, application_type)");
    
    echo "✓ Unique constraint added successfully!\n";
    echo "\nFrom now on, each user can only have ONE 'new' application and ONE 'renewal' application.\n";
    echo "Any attempt to create a duplicate will fail at the database level.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This might be because duplicates already exist. Please run cleanup_duplicates.php first.\n";
}

$conn->close();
?>











