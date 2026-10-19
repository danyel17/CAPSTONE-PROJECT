<?php
/**
 * Reset Vendor ID Auto-Increment
 * 
 * This script resets the AUTO_INCREMENT value for the vendors table
 * so that new applications start from ID 1 instead of continuing from the last ID.
 * 
 * WARNING: Only use this if you're sure there are no applications
 * or if you want to start fresh.
 */

require_once 'config.php';
require_once 'auth_functions.php';

// Require admin login for safety
requireAdminLogin();

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed.");
}

$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

if ($confirmed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check current AUTO_INCREMENT value
        $check_query = "SHOW TABLE STATUS LIKE 'vendors'";
        $result = $conn->query($check_query);
        $table_status = $result->fetch_assoc();
        $current_auto_increment = $table_status['Auto_increment'] ?? 1;
        
        // Count existing records
        $count_result = $conn->query("SELECT COUNT(*) as count FROM vendors");
        $count_row = $count_result->fetch_assoc();
        $record_count = $count_row['count'];
        
        // Reset AUTO_INCREMENT to 1
        $conn->query("ALTER TABLE vendors AUTO_INCREMENT = 1");
        
        // Verify the change
        $result = $conn->query($check_query);
        $table_status = $result->fetch_assoc();
        $new_auto_increment = $table_status['Auto_increment'] ?? 1;
        
        $success = true;
        $message = "Vendor ID auto-increment reset successfully!";
        $details = [
            'previous_auto_increment' => $current_auto_increment,
            'new_auto_increment' => $new_auto_increment,
            'existing_records' => $record_count
        ];
        
    } catch (Exception $e) {
        $success = false;
        $message = "Error: " . $e->getMessage();
        $details = [];
    }
    
    $conn->close();
} else {
    // Get current status
    $check_query = "SHOW TABLE STATUS LIKE 'vendors'";
    $result = $conn->query($check_query);
    $table_status = $result->fetch_assoc();
    $current_auto_increment = $table_status['Auto_increment'] ?? 1;
    
    $count_result = $conn->query("SELECT COUNT(*) as count FROM vendors");
    $count_row = $count_result->fetch_assoc();
    $record_count = $count_row['count'];
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Vendor ID - Admin</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 100%, #FFA07A 0%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 1rem;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .warning-box h3 {
            color: #856404;
            margin-bottom: 1rem;
        }
        .info-box {
            background: #d1ecf1;
            border: 2px solid #0c5460;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .info-box h3 {
            color: #0c5460;
            margin-bottom: 1rem;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            margin: 0.5rem 0;
        }
        .stat-box h3 {
            font-size: 2.5rem;
            margin: 0;
        }
        .stat-box p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        .btn-primary-large {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-primary-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-back {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        .btn-back:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .success-box h3 {
            color: #155724;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-redo"></i> Reset Vendor ID Auto-Increment</h1>
            
            <?php if (isset($success)): ?>
                <?php if ($success): ?>
                    <div class="success-box">
                        <h3><i class="fas fa-check-circle"></i> Reset Complete</h3>
                        <p><?php echo htmlspecialchars($message); ?></p>
                        <?php if (!empty($details)): ?>
                            <div class="info-box" style="margin-top: 1rem;">
                                <h4>Details:</h4>
                                <ul>
                                    <li><strong>Previous Auto-Increment:</strong> <?php echo $details['previous_auto_increment']; ?></li>
                                    <li><strong>New Auto-Increment:</strong> <?php echo $details['new_auto_increment']; ?></li>
                                    <li><strong>Existing Records:</strong> <?php echo $details['existing_records']; ?></li>
                                </ul>
                                <p style="margin-top: 1rem;"><strong>Note:</strong> New applications will now start from ID #1.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <a href="adminpanel.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Admin Panel
                    </a>
                    
                <?php else: ?>
                    <div class="warning-box">
                        <h3><i class="fas fa-exclamation-triangle"></i> Error</h3>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                    <a href="adminpanel.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Admin Panel
                    </a>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="info-box">
                    <h3><i class="fas fa-info-circle"></i> Current Status</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $current_auto_increment; ?></h3>
                                <p>Current Auto-Increment Value</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $record_count; ?></h3>
                                <p>Existing Records</p>
                            </div>
                        </div>
                    </div>
                    <p style="margin-top: 1rem;">
                        <strong>What this does:</strong><br>
                        This will reset the AUTO_INCREMENT counter for the vendors table to 1.
                        The next application created will have ID #1 instead of #<?php echo $current_auto_increment; ?>.
                    </p>
                    <p style="margin-top: 1rem;">
                        <strong>Note:</strong> This does NOT delete existing records. It only resets the counter.
                        If you have existing applications, their IDs will remain unchanged, but new applications will start from 1.
                    </p>
                </div>
                
                <div class="warning-box">
                    <h3><i class="fas fa-exclamation-triangle"></i> Important</h3>
                    <p>If you have existing applications with IDs, resetting the counter might cause ID conflicts if those records are deleted later. Make sure you understand this before proceeding.</p>
                </div>
                
                <form method="POST" action="reset_vendor_id.php" onsubmit="return confirm('Are you sure you want to reset the Vendor ID auto-increment to 1?');">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="yes" required>
                        <label class="form-check-label" for="confirm">
                            <strong>I understand and want to reset the Vendor ID counter to 1</strong>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-primary-large">
                        <i class="fas fa-redo"></i> Reset Vendor ID to 1
                    </button>
                </form>
                
                <a href="adminpanel.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Cancel - Back to Admin Panel
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>











