<?php
/**
 * Test MySQL Queries Used in Admin Panel
 * This tests the exact queries to find SQL issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>MySQL Query Test</title>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; } .success { color: green; } .error { color: red; } .warning { color: orange; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; } table { border-collapse: collapse; width: 100%; margin: 10px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #4CAF50; color: white; }</style>";
echo "</head><body>";
echo "<h1>MySQL Query Test - Admin Panel Queries</h1>";

try {
    $conn = getDBConnection();
    if (!$conn) {
        die("<p class='error'>✗ Database connection failed</p>");
    }
    echo "<p class='success'>✓ Database connected</p>";
} catch (Exception $e) {
    die("<p class='error'>✗ Connection error: " . htmlspecialchars($e->getMessage()) . "</p>");
}

// Test 1: Check if vendors table exists and has data
echo "<h2>Test 1: Basic Vendors Query</h2>";
$test1 = "SELECT COUNT(*) as count FROM vendors";
echo "<p><strong>Query:</strong> <code>$test1</code></p>";
$result1 = $conn->query($test1);
if ($result1) {
    $row1 = $result1->fetch_assoc();
    $count = $row1['count'] ?? 0;
    echo "<p class='success'>✓ Query executed successfully</p>";
    echo "<p class='info'>Total vendors: <strong>$count</strong></p>";
    
    if ($count == 0) {
        echo "<p class='warning'>⚠ No vendors in database. Submit an application first.</p>";
    }
} else {
    echo "<p class='error'>✗ Query failed: " . htmlspecialchars($conn->error) . "</p>";
}

// Test 2: Simple SELECT * query (the one actually used in adminpanel.php)
echo "<h2>Test 2: Simple SELECT Query (Used in adminpanel.php line 3081)</h2>";
$test2 = "SELECT * FROM vendors ORDER BY vendor_id DESC";
echo "<p><strong>Query:</strong> <code>$test2</code></p>";
$result2 = $conn->query($test2);
if ($result2) {
    $rows2 = [];
    while ($row = $result2->fetch_assoc()) {
        $rows2[] = $row;
    }
    echo "<p class='success'>✓ Query executed successfully</p>";
    echo "<p class='info'>Rows returned: <strong>" . count($rows2) . "</strong></p>";
    
    if (count($rows2) > 0) {
        echo "<h3>Sample Data (first 5 rows):</h3>";
        echo "<table>";
        echo "<tr><th>Vendor ID</th><th>User ID</th><th>Vendor Name</th><th>Section</th><th>Block</th><th>Type</th><th>Status</th><th>Submitted Date</th></tr>";
        foreach (array_slice($rows2, 0, 5) as $row) {
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
        echo "<p class='warning'>⚠ Query returned 0 rows</p>";
    }
} else {
    echo "<p class='error'>✗ Query failed: " . htmlspecialchars($conn->error) . "</p>";
    echo "<p class='error'>Error code: " . $conn->errno . "</p>";
}

// Test 3: Complex query with JOIN (defined but not used in adminpanel.php)
echo "<h2>Test 3: Complex Query with JOIN (Defined but NOT used in adminpanel.php)</h2>";
$test3 = "
    SELECT v.*, 
           COALESCE(u.email, '') as email, 
           COALESCE(u.full_name, 'Unknown User') as user_name,
           CASE 
               WHEN v.review_status IS NOT NULL AND v.review_status != 'pending' THEN v.review_status
               WHEN v.is_reviewed = 1 AND v.reviewed_by IS NOT NULL THEN 
                   CASE WHEN v.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END
               ELSE COALESCE(v.status, 'pending')
           END as display_status,
           COALESCE(v.is_reviewed, 0) as is_reviewed,
           COALESCE(v.review_status, 'pending') as review_status,
           COALESCE(v.status, 'pending') as original_status
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.user_id
    WHERE v.vendor_id IS NOT NULL
    ORDER BY COALESCE(v.submitted_date, v.vendor_id) DESC, v.vendor_id DESC
";
echo "<p><strong>Query:</strong></p><pre>" . htmlspecialchars($test3) . "</pre>";
$result3 = $conn->query($test3);
if ($result3) {
    $rows3 = [];
    while ($row = $result3->fetch_assoc()) {
        $rows3[] = $row;
    }
    echo "<p class='success'>✓ Query executed successfully</p>";
    echo "<p class='info'>Rows returned: <strong>" . count($rows3) . "</strong></p>";
    
    if (count($rows3) > 0) {
        echo "<h3>Sample Data (first 3 rows):</h3>";
        echo "<table>";
        echo "<tr><th>Vendor ID</th><th>Vendor Name</th><th>Email</th><th>User Name</th><th>Display Status</th><th>Status</th></tr>";
        foreach (array_slice($rows3, 0, 3) as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['vendor_id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['vendor_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['user_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['display_status'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p class='error'>✗ Query failed: " . htmlspecialchars($conn->error) . "</p>";
    echo "<p class='error'>Error code: " . $conn->errno . "</p>";
    
    // Try to identify the issue
    if (strpos($conn->error, 'Unknown column') !== false) {
        echo "<p class='warning'>⚠ Missing column detected. Check if all columns exist in vendors table.</p>";
    }
    if (strpos($conn->error, 'syntax') !== false) {
        echo "<p class='warning'>⚠ SQL syntax error detected.</p>";
    }
}

// Test 4: Check for NULL values that might cause ORDER BY issues
echo "<h2>Test 4: Check NULL Values in submitted_date</h2>";
$test4 = "SELECT vendor_id, vendor_name, submitted_date, 
          CASE WHEN submitted_date IS NULL THEN 'NULL' ELSE 'HAS VALUE' END as date_status
          FROM vendors ORDER BY vendor_id DESC LIMIT 10";
$result4 = $conn->query($test4);
if ($result4) {
    echo "<table>";
    echo "<tr><th>Vendor ID</th><th>Vendor Name</th><th>Submitted Date</th><th>Date Status</th></tr>";
    while ($row = $result4->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['vendor_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['vendor_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['submitted_date'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['date_status'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ Query failed: " . htmlspecialchars($conn->error) . "</p>";
}

// Test 5: Test ORDER BY with COALESCE
echo "<h2>Test 5: ORDER BY with COALESCE (Used in complex query)</h2>";
$test5 = "SELECT vendor_id, vendor_name, submitted_date, 
          COALESCE(submitted_date, vendor_id) as order_value
          FROM vendors 
          ORDER BY COALESCE(submitted_date, vendor_id) DESC, vendor_id DESC
          LIMIT 10";
$result5 = $conn->query($test5);
if ($result5) {
    echo "<p class='success'>✓ ORDER BY with COALESCE works</p>";
    echo "<table>";
    echo "<tr><th>Vendor ID</th><th>Vendor Name</th><th>Submitted Date</th><th>Order Value</th></tr>";
    while ($row = $result5->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['vendor_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['vendor_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['submitted_date'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['order_value'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ ORDER BY query failed: " . htmlspecialchars($conn->error) . "</p>";
}

// Test 6: Check table structure
echo "<h2>Test 6: Vendors Table Structure</h2>";
$result6 = $conn->query("SHOW COLUMNS FROM vendors");
if ($result6) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($col = $result6->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ Failed to get table structure: " . htmlspecialchars($conn->error) . "</p>";
}

$conn->close();

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If Test 2 (simple query) works but adminpanel.php doesn't show data, the issue is NOT the MySQL query.</p>";
echo "<p>If Test 2 fails, there's a MySQL syntax or table structure issue.</p>";
echo "<p>If Test 3 (complex query) fails, there might be missing columns or syntax issues.</p>";
echo "<p><a href='adminpanel.php'>Go to Admin Panel</a> | <a href='check_adminpanel_debug.php'>Debug Check</a></p>";
echo "</body></html>";
?>
