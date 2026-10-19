<?php
// Quick test file to check if data exists
require_once 'config.php';

$conn = getDBConnection();

echo "<h1>Database Diagnostic Test</h1>";

// Test 1: Count vendors
$result = $conn->query("SELECT COUNT(*) as count FROM vendors");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p><strong>Total Vendors:</strong> " . ($row['count'] ?? 0) . "</p>";
} else {
    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
}

// Test 2: Count users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p><strong>Total Users:</strong> " . ($row['count'] ?? 0) . "</p>";
} else {
    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
}

// Test 3: Show recent vendors
echo "<h2>Recent Vendors (Last 10)</h2>";
$result = $conn->query("SELECT vendor_id, vendor_name, status, user_id FROM vendors ORDER BY vendor_id DESC LIMIT 10");
if ($result) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Status</th><th>User ID</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['vendor_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['vendor_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
}

// Test 4: Test the actual query used in adminpanel
echo "<h2>Test Admin Panel Query</h2>";
$test_query = "
    SELECT v.*, 
           COALESCE(u.email, '') as email, 
           COALESCE(u.full_name, 'Unknown User') as user_name,
           COALESCE(v.status, 'pending') as display_status
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.user_id
    WHERE v.vendor_id IS NOT NULL
    ORDER BY COALESCE(v.submitted_date, v.vendor_id) DESC, v.vendor_id DESC
    LIMIT 10
";
$result = $conn->query($test_query);
if ($result) {
    echo "<p><strong>Query Success!</strong> Found " . $result->num_rows . " rows</p>";
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Email</th><th>User Name</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['vendor_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['vendor_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['display_status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><strong>Query Failed:</strong> " . $conn->error . "</p>";
}

$conn->close();
?>
