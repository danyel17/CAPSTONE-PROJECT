<?php
/**
 * Cleanup Duplicate Vendor Applications and Accounts
 * 
 * This script identifies and removes duplicate vendor applications based on:
 * - Same user_id
 * - Same vendor_name
 * - Same section
 * - Same block
 * - Same application_type
 * 
 * It keeps the most recent application (or approved one if exists) and deletes the rest.
 */

require_once 'config.php';
require_once 'auth_functions.php';

// Require admin login for safety
requireAdminLogin();

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed.");
}

$results = [
    'duplicates_found' => 0,
    'duplicates_removed' => 0,
    'orphaned_docs_removed' => 0,
    'details' => []
];

// Start transaction
$conn->autocommit(false);

try {
    // Step 1: Find duplicate vendor applications
    // Group by user_id, vendor_name, section, block, application_type
    $duplicate_query = "
        SELECT 
            user_id,
            vendor_name,
            section,
            COALESCE(block, '') as block,
            application_type,
            COUNT(*) as duplicate_count,
            GROUP_CONCAT(vendor_id ORDER BY 
                CASE WHEN status = 'approved' THEN 1 
                     WHEN status = 'pending' THEN 2 
                     ELSE 3 END,
                submitted_date DESC
            ) as vendor_ids
        FROM vendors
        GROUP BY user_id, vendor_name, section, COALESCE(block, ''), application_type
        HAVING COUNT(*) > 1
    ";
    
    $result = $conn->query($duplicate_query);
    $duplicates = [];
    
    while ($row = $result->fetch_assoc()) {
        $vendor_ids = explode(',', $row['vendor_ids']);
        $duplicates[] = [
            'user_id' => $row['user_id'],
            'vendor_name' => $row['vendor_name'],
            'section' => $row['section'],
            'block' => $row['block'],
            'application_type' => $row['application_type'],
            'count' => $row['duplicate_count'],
            'vendor_ids' => $vendor_ids
        ];
    }
    
    $results['duplicates_found'] = count($duplicates);
    
    // Step 2: For each duplicate group, keep the first one (most recent or approved) and delete the rest
    foreach ($duplicates as $dup) {
        $vendor_ids = $dup['vendor_ids'];
        $keep_id = (int)$vendor_ids[0]; // Keep the first one (already sorted by status and date)
        $delete_ids = array_slice($vendor_ids, 1); // Delete the rest
        
        foreach ($delete_ids as $delete_id) {
            $delete_id = (int)$delete_id;
            
            // Get vendor info for logging
            $info_stmt = $conn->prepare("SELECT vendor_name, section, status FROM vendors WHERE vendor_id = ?");
            $info_stmt->bind_param("i", $delete_id);
            $info_stmt->execute();
            $info_result = $info_stmt->get_result();
            $vendor_info = $info_result->fetch_assoc();
            $info_stmt->close();
            
            // Delete associated documents first (CASCADE should handle this, but being explicit)
            $doc_stmt = $conn->prepare("DELETE FROM application_documents WHERE vendor_id = ?");
            $doc_stmt->bind_param("i", $delete_id);
            $doc_stmt->execute();
            $docs_deleted = $doc_stmt->affected_rows;
            $doc_stmt->close();
            
            // Delete associated notifications
            $notif_stmt = $conn->prepare("DELETE FROM notifications WHERE vendor_id = ?");
            $notif_stmt->bind_param("i", $delete_id);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Delete approval actions
            $action_stmt = $conn->prepare("DELETE FROM approval_actions WHERE vendor_id = ?");
            $action_stmt->bind_param("i", $delete_id);
            $action_stmt->execute();
            $action_stmt->close();
            
            // Delete the vendor record
            $del_stmt = $conn->prepare("DELETE FROM vendors WHERE vendor_id = ?");
            $del_stmt->bind_param("i", $delete_id);
            $del_stmt->execute();
            $del_stmt->close();
            
            $results['duplicates_removed']++;
            $results['orphaned_docs_removed'] += $docs_deleted;
            
            $results['details'][] = [
                'action' => 'deleted',
                'vendor_id' => $delete_id,
                'vendor_name' => $vendor_info['vendor_name'] ?? 'Unknown',
                'section' => $vendor_info['section'] ?? 'Unknown',
                'status' => $vendor_info['status'] ?? 'Unknown',
                'kept_id' => $keep_id,
                'docs_deleted' => $docs_deleted
            ];
        }
    }
    
    // Step 3: Find and remove orphaned vendor accounts (users with no applications)
    // Only remove vendor accounts, not admin accounts
    $orphaned_query = "
        SELECT u.user_id, u.email, u.full_name
        FROM users u
        LEFT JOIN vendors v ON u.user_id = v.user_id
        WHERE u.user_type = 'vendor'
          AND v.vendor_id IS NULL
          AND u.user_id != 1
    ";
    
    $orphaned_result = $conn->query($orphaned_query);
    $orphaned_users = [];
    
    while ($row = $orphaned_result->fetch_assoc()) {
        $orphaned_users[] = $row;
    }
    
    // Delete orphaned vendor accounts (CASCADE will handle related records)
    foreach ($orphaned_users as $user) {
        $del_user_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND user_type = 'vendor'");
        $del_user_stmt->bind_param("i", $user['user_id']);
        $del_user_stmt->execute();
        $del_user_stmt->close();
        
        $results['details'][] = [
            'action' => 'deleted_orphaned_user',
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ];
    }
    
    // Commit transaction
    $conn->commit();
    $conn->autocommit(true);
    
    $success = true;
    $message = "Cleanup completed successfully!";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $conn->autocommit(true);
    
    $success = false;
    $message = "Error during cleanup: " . $e->getMessage();
    $results['error'] = $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Duplicates - Admin</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 1rem;
        }
        .stat-box h3 {
            font-size: 2.5rem;
            margin: 0;
        }
        .stat-box p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .details-table th,
        .details-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .details-table th {
            background: #f8f9fa;
            font-weight: 600;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-broom"></i> Cleanup Results</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-box">
                        <h3><?php echo $results['duplicates_found']; ?></h3>
                        <p>Duplicate Groups Found</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <h3><?php echo $results['duplicates_removed']; ?></h3>
                        <p>Applications Removed</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <h3><?php echo $results['orphaned_docs_removed']; ?></h3>
                        <p>Documents Removed</p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($results['details'])): ?>
                <h3>Details</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['details'] as $detail): ?>
                                <tr>
                                    <td>
                                        <?php if ($detail['action'] === 'deleted'): ?>
                                            <span class="badge bg-danger">Deleted</span>
                                        <?php elseif ($detail['action'] === 'deleted_orphaned_user'): ?>
                                            <span class="badge bg-warning">Orphaned User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($detail['action'] === 'deleted'): ?>
                                            #<?php echo $detail['vendor_id']; ?>
                                        <?php else: ?>
                                            User #<?php echo $detail['user_id']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($detail['vendor_name'] ?? $detail['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($detail['section'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (isset($detail['status'])): ?>
                                            <span class="badge bg-<?php echo $detail['status'] === 'approved' ? 'success' : ($detail['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($detail['status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($detail['kept_id'])): ?>
                                            Kept: #<?php echo $detail['kept_id']; ?>
                                        <?php endif; ?>
                                        <?php if (isset($detail['docs_deleted']) && $detail['docs_deleted'] > 0): ?>
                                            <br><small><?php echo $detail['docs_deleted']; ?> docs removed</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <a href="adminpanel.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>
    </div>
</body>
</html>











