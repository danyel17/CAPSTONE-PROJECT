<?php
/**
 * Database Connection Test Script
 * Run this file to verify database connectivity and data flow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h1>Database Connection Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

// Test 1: Database Connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    $conn = getDBConnection();
    if ($conn) {
        echo "<p class='success'>✓ Database connection successful!</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed!</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Check if vendors table exists
echo "<h2>Test 2: Vendors Table Check</h2>";
$table_check = $conn->query("SHOW TABLES LIKE 'vendors'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p class='success'>✓ Vendors table exists</p>";
} else {
    echo "<p class='error'>✗ Vendors table does NOT exist!</p>";
    echo "<p class='info'>You may need to run the database setup script.</p>";
}

// Test 3: Count vendors
echo "<h2>Test 3: Vendor Count</h2>";
$count_result = $conn->query("SELECT COUNT(*) as count FROM vendors");
if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $total_vendors = $count_row['count'] ?? 0;
    echo "<p class='info'>Total vendors in database: <strong>$total_vendors</strong></p>";
    
    if ($total_vendors == 0) {
        echo "<p class='error'>⚠ No vendors found in database. Applications may not have been submitted yet.</p>";
    } else {
        echo "<p class='success'>✓ Found $total_vendors vendor(s) in database</p>";
    }
} else {
    echo "<p class='error'>✗ Failed to count vendors: " . $conn->error . "</p>";
}

// Test 4: Show sample vendors
echo "<h2>Test 4: Sample Vendors</h2>";
$sample_result = $conn->query("SELECT vendor_id, user_id, vendor_name, section, block, application_type, status, submitted_date FROM vendors ORDER BY vendor_id DESC LIMIT 10");
if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Vendor ID</th><th>User ID</th><th>Name</th><th>Section</th><th>Block</th><th>Type</th><th>Status</th><th>Submitted Date</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['vendor_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['user_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['vendor_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['section'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['block'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['application_type'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['submitted_date'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No vendors found or query failed: " . ($conn->error ?? 'Unknown error') . "</p>";
}

// Test 5: Check users table
echo "<h2>Test 5: Users Table Check</h2>";
$users_check = $conn->query("SELECT COUNT(*) as count FROM users");
if ($users_check) {
    $users_row = $users_check->fetch_assoc();
    $total_users = $users_row['count'] ?? 0;
    echo "<p class='info'>Total users in database: <strong>$total_users</strong></p>";
} else {
    echo "<p class='error'>✗ Failed to count users: " . $conn->error . "</p>";
}

// Test 6: Check column structure
echo "<h2>Test 6: Vendors Table Structure</h2>";
$columns_result = $conn->query("SHOW COLUMNS FROM vendors");
if ($columns_result) {
    echo "<p class='info'>Vendors table columns:</p><ul>";
    $required_columns = ['vendor_id', 'user_id', 'vendor_name', 'section', 'block', 'application_type', 'status', 'submitted_date'];
    $existing_columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
        echo "<li>" . htmlspecialchars($col['Field']) . " (" . htmlspecialchars($col['Type']) . ")</li>";
    }
    echo "</ul>";
    
    $missing_columns = array_diff($required_columns, $existing_columns);
    if (!empty($missing_columns)) {
        echo "<p class='error'>⚠ Missing required columns: " . implode(', ', $missing_columns) . "</p>";
    } else {
        echo "<p class='success'>✓ All required columns exist</p>";
    }
} else {
    echo "<p class='error'>✗ Failed to get column structure: " . $conn->error . "</p>";
}

// Test 7: Test INSERT (dry run - won't actually insert)
echo "<h2>Test 7: INSERT Statement Test</h2>";
$test_insert = $conn->prepare("INSERT INTO vendors (user_id, vendor_name, section, block, application_type, status, submitted_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
if ($test_insert) {
    echo "<p class='success'>✓ INSERT statement prepared successfully</p>";
    $test_insert->close();
} else {
    echo "<p class='error'>✗ Failed to prepare INSERT statement: " . $conn->error . "</p>";
}

// Test 8: Check recent applications query (same as adminpanel.php)
echo "<h2>Test 8: Admin Panel Query Test</h2>";
$admin_query = $conn->query("SELECT * FROM vendors ORDER BY vendor_id DESC");
if ($admin_query) {
    $admin_count = $admin_query->num_rows;
    echo "<p class='success'>✓ Admin panel query executed successfully</p>";
    echo "<p class='info'>Found <strong>$admin_count</strong> vendor(s) with this query</p>";
    
    if ($admin_count > 0) {
        echo "<p class='success'>✓ Data should display in adminpanel.php</p>";
    } else {
        echo "<p class='error'>⚠ No data found - adminpanel.php will show 'No applications found'</p>";
    }
} else {
    echo "<p class='error'>✗ Admin panel query failed: " . $conn->error . "</p>";
}

$conn->close();

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If all tests pass, your database connection is working correctly.</p>";
echo "<p>If vendors exist but adminpanel.php doesn't show them, check:</p>";
echo "<ul>";
echo "<li>PHP error logs</li>";
echo "<li>Browser console for JavaScript errors</li>";
echo "<li>Network tab for failed requests</li>";
echo "</ul>";
echo "<p><a href='adminpanel.php'>Go to Admin Panel</a></p>";
?>
