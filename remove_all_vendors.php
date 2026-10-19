<?php
/**
 * Remove ALL Vendor Accounts and Applications
 * 
 * WARNING: This will permanently delete:
 * - All vendor applications (vendors table)
 * - All vendor user accounts (users with user_type = 'vendor')
 * - All associated documents, notifications, and related data
 * 
 * This action CANNOT be undone!
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
$results = [
    'vendors_deleted' => 0,
    'users_deleted' => 0,
    'documents_deleted' => 0,
    'notifications_deleted' => 0,
    'actions_deleted' => 0
];

if ($confirmed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->autocommit(false);
    
    try {
        // Step 1: Count before deletion
        $count_vendors = $conn->query("SELECT COUNT(*) as count FROM vendors")->fetch_assoc()['count'];
        $count_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'vendor'")->fetch_assoc()['count'];
        $count_docs = $conn->query("SELECT COUNT(*) as count FROM application_documents")->fetch_assoc()['count'];
        $count_notifs = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
        
        // Check if approval_actions table exists
        $actions_count = 0;
        $actions_exists = $conn->query("SHOW TABLES LIKE 'approval_actions'");
        if ($actions_exists && $actions_exists->num_rows > 0) {
            $actions_count = $conn->query("SELECT COUNT(*) as count FROM approval_actions")->fetch_assoc()['count'];
        }
        
        // Step 2: Delete all application documents
        $conn->query("DELETE FROM application_documents");
        $results['documents_deleted'] = $conn->affected_rows;
        
        // Step 3: Delete all notifications
        $conn->query("DELETE FROM notifications");
        $results['notifications_deleted'] = $conn->affected_rows;
        
        // Step 4: Delete all approval actions (if table exists)
        if ($actions_exists && $actions_exists->num_rows > 0) {
            $conn->query("DELETE FROM approval_actions");
            $results['actions_deleted'] = $conn->affected_rows;
        }
        
        // Step 5: Delete all vendor applications
        $conn->query("DELETE FROM vendors");
        $results['vendors_deleted'] = $conn->affected_rows;
        
        // Step 6: Delete all vendor user accounts
        // This will cascade delete related records due to foreign keys
        $conn->query("DELETE FROM users WHERE user_type = 'vendor'");
        $results['users_deleted'] = $conn->affected_rows;
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        $success = true;
        $message = "All vendor accounts and applications have been permanently deleted.";
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->autocommit(true);
        
        $success = false;
        $message = "Error during deletion: " . $e->getMessage();
    }
    
    $conn->close();
} else {
    // Get counts for confirmation page
    $count_vendors = $conn->query("SELECT COUNT(*) as count FROM vendors")->fetch_assoc()['count'];
    $count_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'vendor'")->fetch_assoc()['count'];
    $count_docs = $conn->query("SELECT COUNT(*) as count FROM application_documents")->fetch_assoc()['count'];
    $count_notifs = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
    
    $actions_count = 0;
    $actions_exists = $conn->query("SHOW TABLES LIKE 'approval_actions'");
    if ($actions_exists && $actions_exists->num_rows > 0) {
        $actions_count = $conn->query("SELECT COUNT(*) as count FROM approval_actions")->fetch_assoc()['count'];
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remove All Vendors - Admin</title>
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
        .danger-box {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .danger-box h3 {
            color: #721c24;
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
        .btn-danger-large {
            background: #dc3545;
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
        .btn-danger-large:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
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
            <h1><i class="fas fa-exclamation-triangle"></i> Remove All Vendor Accounts</h1>
            
            <?php if ($confirmed && isset($success)): ?>
                <?php if ($success): ?>
                    <div class="success-box">
                        <h3><i class="fas fa-check-circle"></i> Deletion Complete</h3>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $results['vendors_deleted']; ?></h3>
                                <p>Applications Deleted</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $results['users_deleted']; ?></h3>
                                <p>Vendor Accounts Deleted</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $results['documents_deleted']; ?></h3>
                                <p>Documents Deleted</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $results['notifications_deleted']; ?></h3>
                                <p>Notifications Deleted</p>
                            </div>
                        </div>
                    </div>
                    
                    <a href="adminpanel.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Admin Panel
                    </a>
                    
                <?php else: ?>
                    <div class="danger-box">
                        <h3><i class="fas fa-times-circle"></i> Error</h3>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                    <a href="adminpanel.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Admin Panel
                    </a>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="danger-box">
                    <h3><i class="fas fa-exclamation-triangle"></i> WARNING: This Action Cannot Be Undone!</h3>
                    <p>This will permanently delete:</p>
                    <ul>
                        <li><strong>All vendor applications</strong> (<?php echo $count_vendors; ?> records)</li>
                        <li><strong>All vendor user accounts</strong> (<?php echo $count_users; ?> accounts)</li>
                        <li><strong>All application documents</strong> (<?php echo $count_docs; ?> files)</li>
                        <li><strong>All notifications</strong> (<?php echo $count_notifs; ?> notifications)</li>
                        <?php if ($actions_count > 0): ?>
                        <li><strong>All approval actions</strong> (<?php echo $actions_count; ?> records)</li>
                        <?php endif; ?>
                    </ul>
                    <p><strong>Admin accounts will NOT be affected.</strong></p>
                </div>
                
                <div class="warning-box">
                    <h3><i class="fas fa-info-circle"></i> What Will Be Deleted</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $count_vendors; ?></h3>
                                <p>Vendor Applications</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $count_users; ?></h3>
                                <p>Vendor Accounts</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $count_docs; ?></h3>
                                <p>Documents</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <h3><?php echo $count_notifs; ?></h3>
                                <p>Notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="remove_all_vendors.php" onsubmit="return confirm('Are you absolutely sure? This will delete ALL vendor data permanently!');">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="yes" required>
                        <label class="form-check-label" for="confirm">
                            <strong>I understand this action cannot be undone and I want to proceed</strong>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-danger-large">
                        <i class="fas fa-trash-alt"></i> DELETE ALL VENDOR ACCOUNTS AND APPLICATIONS
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











