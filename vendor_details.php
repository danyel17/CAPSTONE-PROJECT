<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Protect this page - require admin login
requireAdminLogin();

$vendor_id = (int)($_GET['id'] ?? 0);

if ($vendor_id <= 0) {
    header("Location: adminpanel.php");
    exit();
}

$conn = getDBConnection();

// Get vendor details
$vendor_query = "
    SELECT v.*, u.email, u.full_name as user_name, u.phone_number
    FROM vendors v
    JOIN users u ON v.user_id = u.user_id
    WHERE v.vendor_id = ?
";
$vendor_stmt = $conn->prepare($vendor_query);
$vendor_stmt->bind_param("i", $vendor_id);
$vendor_stmt->execute();
$vendor_result = $vendor_stmt->get_result();

if ($vendor_result->num_rows === 0) {
    header("Location: adminpanel.php");
    exit();
}

$vendor = $vendor_result->fetch_assoc();
$vendor_stmt->close();

// Get vendor documents
$docs_query = "SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY uploaded_date ASC";
$docs_stmt = $conn->prepare($docs_query);
$docs_stmt->bind_param("i", $vendor_id);
$docs_stmt->execute();
$docs_result = $docs_stmt->get_result();
$documents = [];
while ($row = $docs_result->fetch_assoc()) {
    $documents[] = $row;
}
$docs_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Details - Admin Panel</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #3f51b5;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .content {
            padding: 30px;
        }
        
        .vendor-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3f51b5;
        }
        
        .info-card h3 {
            color: #3f51b5;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge.renewal {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .documents-section {
            margin-top: 30px;
        }
        
        .documents-section h2 {
            color: #3f51b5;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .document-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .document-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .document-icon {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #3f51b5;
            margin-bottom: 15px;
        }
        
        .document-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            word-break: break-word;
        }
        
        .document-info {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #3f51b5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2c3e8a;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .no-documents {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .no-documents i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #3f51b5;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .close-modal:hover {
            color: #495057;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
        }
        
        .modal-body iframe {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: 5px;
        }
        
        @media (max-width: 768px) {
            .vendor-info {
                grid-template-columns: 1fr;
            }
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user"></i> Vendor Application Details</h1>
            <a href="adminpanel.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>
        
        <div class="content">
            <div class="vendor-info">
                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Vendor Information</h3>
                    <div class="info-row">
                        <span class="info-label">Application ID:</span>
                        <span class="info-value">#<?php echo $vendor['vendor_id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Vendor Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($vendor['vendor_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($vendor['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($vendor['phone_number'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Application Details</h3>
                    <div class="info-row">
                        <span class="info-label">Section:</span>
                        <span class="info-value"><?php echo htmlspecialchars($vendor['section']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type:</span>
                        <span class="info-value">
                            <span class="badge <?php echo $vendor['application_type']; ?>">
                                <?php echo ucfirst($vendor['application_type']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="badge <?php echo $vendor['status']; ?>">
                                <?php echo ucfirst($vendor['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Submitted:</span>
                        <span class="info-value"><?php echo date('M d, Y H:i', strtotime($vendor['submitted_date'])); ?></span>
                    </div>
                    <?php if ($vendor['reviewed_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Reviewed:</span>
                        <span class="info-value"><?php echo date('M d, Y H:i', strtotime($vendor['reviewed_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($vendor['admin_notes'])): ?>
            <div class="info-card">
                <h3><i class="fas fa-sticky-note"></i> Admin Notes</h3>
                <p><?php echo nl2br(htmlspecialchars($vendor['admin_notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="documents-section">
                <h2><i class="fas fa-file-alt"></i> Uploaded Documents</h2>
                
                <?php if (empty($documents)): ?>
                <div class="no-documents">
                    <i class="fas fa-file-upload"></i>
                    <h3>No Documents Uploaded</h3>
                    <p>This vendor application has no uploaded documents.</p>
                </div>
                <?php else: ?>
                <div class="documents-grid">
                    <?php foreach ($documents as $doc): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <?php
                            $ext = strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION));
                            switch ($ext) {
                                case 'pdf':
                                    echo '<i class="fas fa-file-pdf"></i>';
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif':
                                    echo '<i class="fas fa-file-image"></i>';
                                    break;
                                case 'doc':
                                case 'docx':
                                    echo '<i class="fas fa-file-word"></i>';
                                    break;
                                default:
                                    echo '<i class="fas fa-file"></i>';
                            }
                            ?>
                        </div>
                        <div class="document-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                        <div class="document-info">
                            <div><strong>Type:</strong> <?php echo strtoupper($doc['document_type']); ?></div>
                            <div><strong>Size:</strong> <?php echo formatFileSize($doc['file_size']); ?></div>
                            <div><strong>Uploaded:</strong> <?php echo date('M d, Y H:i', strtotime($doc['uploaded_date'])); ?></div>
                        </div>
                        <div class="document-actions">
                            <button class="btn btn-primary" onclick="viewDocument('<?php echo $doc['document_path']; ?>', '<?php echo $doc['document_type']; ?>')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <a href="<?php echo $doc['document_path']; ?>" class="btn btn-success" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($vendor['status'] === 'pending'): ?>
            <div class="action-buttons">
                <button class="btn btn-success" onclick="approveVendor(<?php echo $vendor['vendor_id']; ?>)">
                    <i class="fas fa-check-circle"></i> Approve Application
                </button>
                <button class="btn btn-danger" onclick="rejectVendor(<?php echo $vendor['vendor_id']; ?>)">
                    <i class="fas fa-times-circle"></i> Reject Application
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Document Viewer Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Document Viewer</h3>
                <button class="close-modal" onclick="closeDocumentModal()">&times;</button>
            </div>
            <div class="modal-body" id="documentContent">
                <!-- Document content will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="actionModalTitle">Action</h3>
                <button class="close-modal" onclick="closeActionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="actionNotes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button class="btn btn-success" id="confirmActionBtn" onclick="confirmAction()">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                    <button class="btn btn-danger" onclick="closeActionModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentVendorId = <?php echo $vendor['vendor_id']; ?>;
        let currentAction = null;
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function viewDocument(path, type) {
            const modal = document.getElementById('documentModal');
            const content = document.getElementById('documentContent');
            
            if (type === 'pdf') {
                content.innerHTML = `<iframe src="${path}"></iframe>`;
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(type)) {
                content.innerHTML = `<img src="${path}" alt="Document">`;
            } else {
                content.innerHTML = `<p>Preview not available for this file type. <a href="${path}" download>Download to view</a></p>`;
            }
            
            modal.classList.add('show');
        }
        
        function closeDocumentModal() {
            document.getElementById('documentModal').classList.remove('show');
        }
        
        function approveVendor(vendorId) {
            currentAction = 'approve';
            document.getElementById('actionModalTitle').textContent = 'Approve Vendor Application';
            document.getElementById('confirmActionBtn').innerHTML = '<i class="fas fa-check"></i> Approve';
            document.getElementById('actionModal').classList.add('show');
        }
        
        function rejectVendor(vendorId) {
            currentAction = 'reject';
            document.getElementById('actionModalTitle').textContent = 'Reject Vendor Application';
            document.getElementById('confirmActionBtn').innerHTML = '<i class="fas fa-times"></i> Reject';
            document.getElementById('actionModal').classList.add('show');
        }
        
        function closeActionModal() {
            document.getElementById('actionModal').classList.remove('show');
            document.getElementById('actionNotes').value = '';
            currentAction = null;
        }
        
        function confirmAction() {
            const formData = new FormData();
            formData.append('action', currentAction === 'approve' ? 'approve_vendor' : 'reject_vendor');
            formData.append('vendor_id', currentVendorId);
            formData.append('notes', document.getElementById('actionNotes').value);
            
            fetch('adminpanel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeActionModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('An error occurred while processing the request.');
            });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const documentModal = document.getElementById('documentModal');
            const actionModal = document.getElementById('actionModal');
            
            if (event.target === documentModal) {
                closeDocumentModal();
            }
            if (event.target === actionModal) {
                closeActionModal();
            }
        }
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>



