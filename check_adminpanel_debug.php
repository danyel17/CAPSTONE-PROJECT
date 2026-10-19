<?php
/**
 * Admin Panel Debug Checker
 * This script checks for common issues preventing data display
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Admin Panel Debug</title>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; } .success { color: green; } .error { color: red; } .warning { color: orange; } .info { color: blue; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }</style>";
echo "</head><body>";
echo "<h1>Admin Panel Debug Checker</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
try {
    $conn = getDBConnection();
    if ($conn) {
        echo "<p class='success'>✓ Database connection successful</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test 2: Check vendors count
echo "<h2>2. Vendors in Database</h2>";
$count_result = $conn->query("SELECT COUNT(*) as count FROM vendors");
if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $total_vendors = $count_row['count'] ?? 0;
    echo "<p class='info'>Total vendors: <strong>$total_vendors</strong></p>";
    
    if ($total_vendors == 0) {
        echo "<p class='warning'>⚠ No vendors found. Submit an application first.</p>";
    }
} else {
    echo "<p class='error'>✗ Failed to count vendors: " . $conn->error . "</p>";
}

// Test 3: Test the exact query used in adminpanel.php
echo "<h2>3. Testing Admin Panel Query</h2>";
$test_query = "SELECT * FROM vendors ORDER BY vendor_id DESC";
$test_result = $conn->query($test_query);
if ($test_result) {
    $test_count = $test_result->num_rows;
    echo "<p class='success'>✓ Query executed successfully</p>";
    echo "<p class='info'>Rows returned: <strong>$test_count</strong></p>";
    
    if ($test_count > 0) {
        echo "<h3>Sample Data:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Vendor ID</th><th>Name</th><th>Section</th><th>Block</th><th>Type</th><th>Status</th><th>Submitted Date</th></tr>";
        $test_result->data_seek(0); // Reset pointer
        $sample_count = 0;
        while ($row = $test_result->fetch_assoc() && $sample_count < 5) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['vendor_id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['vendor_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['section'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['block'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['application_type'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['submitted_date'] ?? 'N/A') . "</td>";
            echo "</tr>";
            $sample_count++;
        }
        echo "</table>";
    }
} else {
    echo "<p class='error'>✗ Query failed: " . $conn->error . "</p>";
}

// Test 4: Check PHP error log location
echo "<h2>4. PHP Error Log Location</h2>";
$error_log = ini_get('error_log');
if ($error_log) {
    echo "<p class='info'>Error log file: <strong>$error_log</strong></p>";
    if (file_exists($error_log)) {
        echo "<p class='success'>✓ Error log file exists</p>";
        $log_size = filesize($error_log);
        echo "<p class='info'>Log file size: " . number_format($log_size) . " bytes</p>";
        
        // Show last 20 lines of error log
        if ($log_size > 0) {
            echo "<h3>Last 20 lines from error log:</h3>";
            $lines = file($error_log);
            $last_lines = array_slice($lines, -20);
            echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
        }
    } else {
        echo "<p class='warning'>⚠ Error log file does not exist</p>";
    }
} else {
    echo "<p class='warning'>⚠ Error log not configured</p>";
}

// Test 5: Check if adminpanel.php has syntax errors
echo "<h2>5. PHP Syntax Check</h2>";
$adminpanel_file = __DIR__ . '/adminpanel.php';
if (file_exists($adminpanel_file)) {
    $output = [];
    $return_var = 0;
    exec("php -l \"$adminpanel_file\" 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "<p class='success'>✓ No syntax errors in adminpanel.php</p>";
    } else {
        echo "<p class='error'>✗ Syntax errors found:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    }
} else {
    echo "<p class='error'>✗ adminpanel.php not found</p>";
}

// Test 6: Check variable scope simulation
echo "<h2>6. Variable Scope Test</h2>";
echo "<p class='info'>Simulating adminpanel.php data loading...</p>";

// Simulate the exact code from adminpanel.php
$recent_applications = [];
try {
    $simple_result = $conn->query("SELECT * FROM vendors ORDER BY vendor_id DESC");
    if ($simple_result) {
        while ($row = $simple_result->fetch_assoc()) {
            $row['email'] = '';
            $row['user_name'] = 'Unknown User';
            $row['display_status'] = $row['status'] ?? 'pending';
            $row['original_status'] = $row['status'] ?? 'pending';
            $row['is_reviewed'] = isset($row['is_reviewed']) ? $row['is_reviewed'] : 0;
            $row['review_status'] = $row['review_status'] ?? 'pending';
            $row['application_type'] = $row['application_type'] ?? 'new';
            $row['section'] = $row['section'] ?? '';
            $row['block'] = $row['block'] ?? '';
            $row['submitted_date'] = $row['submitted_date'] ?? date('Y-m-d H:i:s');
            $recent_applications[] = $row;
        }
        echo "<p class='success'>✓ Data loaded successfully</p>";
        echo "<p class='info'>Applications loaded: <strong>" . count($recent_applications) . "</strong></p>";
        
        if (count($recent_applications) > 0) {
            echo "<p class='success'>✓ Data should display in adminpanel.php</p>";
            echo "<h3>First Application:</h3>";
            echo "<pre>" . htmlspecialchars(json_encode($recent_applications[0], JSON_PRETTY_PRINT)) . "</pre>";
        } else {
            echo "<p class='warning'>⚠ No applications loaded - this matches what you see in adminpanel.php</p>";
        }
    } else {
        echo "<p class='error'>✗ Query failed: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If vendors exist but adminpanel.php shows 'No applications found':";
echo "<ul>";
echo "<li>Check PHP error logs (shown above)</li>";
echo "<li>Open browser console (F12) and check for JavaScript errors</li>";
echo "<li>Check Network tab for failed AJAX requests</li>";
echo "<li>View page source and look for the DEBUG comment at the top</li>";
echo "</ul>";
echo "</li>";
echo "<li>If no vendors exist:";
echo "<ul>";
echo "<li>Submit a test application from application.php or renewal.php</li>";
echo "<li>Verify it was saved using test_database_connection.php</li>";
echo "</ul>";
echo "</li>";
echo "</ol>";

echo "<p><a href='adminpanel.php'>Go to Admin Panel</a> | <a href='test_database_connection.php'>Database Test</a></p>";
echo "</body></html>";
?>
