<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Protect this page - require login
requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_email = $_SESSION['email'];

// Get user's applications
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT v.*, vd.document_name, vd.document_path, vd.document_type 
    FROM vendors v 
    LEFT JOIN vendor_documents vd ON v.vendor_id = vd.vendor_id 
    WHERE v.user_id = ? 
    ORDER BY v.submitted_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Group applications by vendor_id
$grouped_applications = [];
foreach ($applications as $app) {
    $vendor_id = $app['vendor_id'];
    if (!isset($grouped_applications[$vendor_id])) {
        $grouped_applications[$vendor_id] = [
            'vendor_id' => $app['vendor_id'],
            'vendor_name' => $app['vendor_name'],
            'section' => $app['section'],
            'application_type' => $app['application_type'],
            'status' => $app['status'],
            'submitted_date' => $app['submitted_date'],
            'reviewed_date' => $app['reviewed_date'],
            'admin_notes' => $app['admin_notes'],
            'documents' => []
        ];
    }
    
    if ($app['document_name']) {
        $grouped_applications[$vendor_id]['documents'][] = [
            'document_name' => $app['document_name'],
            'document_path' => $app['document_path'],
            'document_type' => $app['document_type']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Market Clearance System</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .nav-links {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #764ba2;
        }

        .content {
            padding: 30px;
        }

        .status-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }

        .status-card.pending {
            border-left-color: #ffc107;
        }

        .status-card.approved {
            border-left-color: #28a745;
        }

        .status-card.rejected {
            border-left-color: #dc3545;
        }

        .status-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .status-card.pending h3 {
            color: #ffc107;
        }

        .status-card.approved h3 {
            color: #28a745;
        }

        .status-card.rejected h3 {
            color: #dc3545;
        }

        .applications-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-header h2 {
            color: #333;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge.approved {
            background: #d4edda;
            color: #155724;
        }

        .badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.new {
            background: #cce5ff;
            color: #004085;
        }

        .badge.renewal {
            background: #e2e3e5;
            color: #383d41;
        }

        .btn-group {
            display: flex;
            gap: 5px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #1e7e34;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .no-applications {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-applications i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .close {
            font-size: 2rem;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        .document-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .document-type {
            font-size: 0.9rem;
            color: #666;
        }

        .download-btn {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .download-btn:hover {
            background: #0056b3;
        }

        .admin-notes {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .admin-notes h4 {
            margin-bottom: 10px;
            color: #495057;
        }

        .admin-notes p {
            color: #6c757d;
            font-style: italic;
        }

        /* Let wide table scroll horizontally instead of overflowing */
        .applications-table { overflow-x: auto; }
        .applications-table table { min-width: 640px; }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .header { padding: 22px 16px; }
            .header h1 { font-size: 1.7rem; }
            .header p { font-size: 0.95rem; }
            .nav-links { padding: 12px 16px; display: flex; flex-wrap: wrap; gap: 10px 16px; }
            .nav-links a { margin-right: 0; }
            .content { padding: 18px 14px; }
            .status-summary { grid-template-columns: 1fr; gap: 14px; margin-bottom: 22px; }
            .status-card h3 { font-size: 1.6rem; }
            th, td { padding: 11px 10px; font-size: 0.9rem; }
            .modal-content { margin: 8% auto; padding: 20px; width: 94%; }
            .document-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .download-btn { width: 100%; text-align: center; }
        }

        @media (max-width: 480px) {
            .header h1 { font-size: 1.4rem; }
            .btn { padding: 8px 12px; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-clipboard-list"></i> My Applications</h1>
            <p>Track your vendor applications and renewals</p>
        </div>

        <div class="nav-links" data-lang-preserve="en">
            <a href="application.php"><i class="fas fa-plus"></i> New Application</a>
            <a href="renewal.php"><i class="fas fa-sync"></i> Renewal</a>
            <a href="userlogin.php"><i class="fas fa-home"></i> Home</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="content">
            <?php
            // Calculate status counts
            $status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
            foreach ($grouped_applications as $app) {
                $status_counts[$app['status']]++;
            }
            ?>

            <div class="status-summary">
                <div class="status-card pending">
                    <h3><?php echo $status_counts['pending']; ?></h3>
                    <p>Pending Applications</p>
                </div>
                <div class="status-card approved">
                    <h3><?php echo $status_counts['approved']; ?></h3>
                    <p>Approved Applications</p>
                </div>
                <div class="status-card rejected">
                    <h3><?php echo $status_counts['rejected']; ?></h3>
                    <p>Rejected Applications</p>
                </div>
            </div>

            <div class="applications-table">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Application History</h2>
                </div>

                <?php if (empty($grouped_applications)): ?>
                <div class="no-applications">
                    <i class="fas fa-inbox"></i>
                    <h3>No Applications Found</h3>
                    <p>You haven't submitted any applications yet.</p>
                    <a href="application.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Submit New Application
                    </a>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vendor Name</th>
                            <th>Section</th>
                            <th>Type</th>
                            <th>Submitted Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped_applications as $app): ?>
                        <tr>
                            <td>#<?php echo $app['vendor_id']; ?></td>
                            <td><?php echo htmlspecialchars($app['vendor_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['section']); ?></td>
                            <td><span class="badge <?php echo $app['application_type']; ?>"><?php echo ucfirst($app['application_type']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($app['submitted_date'])); ?></td>
                            <td><span class="badge <?php echo $app['status']; ?>"><?php echo ucfirst($app['status']); ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-primary" onclick="viewApplication(<?php echo $app['vendor_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Application Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function viewApplication(vendorId) {
            // Get application data
            const applications = <?php echo json_encode($grouped_applications); ?>;
            const app = applications[vendorId];
            
            if (!app) return;
            
            let modalBody = `
                <div style="margin-bottom: 20px;">
                    <h3>Application Information</h3>
                    <p><strong>Vendor Name:</strong> ${app.vendor_name}</p>
                    <p><strong>Section:</strong> ${app.section}</p>
                    <p><strong>Type:</strong> <span class="badge ${app.application_type}">${app.application_type.charAt(0).toUpperCase() + app.application_type.slice(1)}</span></p>
                    <p><strong>Submitted:</strong> ${new Date(app.submitted_date).toLocaleDateString()}</p>
                    <p><strong>Status:</strong> <span class="badge ${app.status}">${app.status.charAt(0).toUpperCase() + app.status.slice(1)}</span></p>
                    ${app.reviewed_date ? `<p><strong>Reviewed:</strong> ${new Date(app.reviewed_date).toLocaleDateString()}</p>` : ''}
                </div>
            `;
            
            if (app.documents && app.documents.length > 0) {
                modalBody += `
                    <div style="margin-bottom: 20px;">
                        <h3>Uploaded Documents</h3>
                        ${app.documents.map(doc => `
                            <div class="document-item">
                                <div class="document-info">
                                    <div class="document-name">${doc.document_name}</div>
                                    <div class="document-type">${doc.document_type}</div>
                                </div>
                                <a href="${doc.document_path}" class="download-btn" target="_blank">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            if (app.admin_notes) {
                modalBody += `
                    <div class="admin-notes">
                        <h4><i class="fas fa-sticky-note"></i> Admin Notes</h4>
                        <p>${app.admin_notes}</p>
                    </div>
                `;
            }
            
            document.getElementById('modalBody').innerHTML = modalBody;
            document.getElementById('applicationModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('applicationModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('applicationModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
    <?php
    $cs_contact_href = 'index.php#contact';
    $cs_announcement_href = 'index.php#announcement';
    include __DIR__ . '/includes/mobile_enhancements.php';
    include __DIR__ . '/includes/customer_support_widget.php';
    ?>
</body>
</html>

