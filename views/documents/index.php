<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/DocumentController.php';

// Check authentication
$authController = new AuthController();
$authController->validateSession();

$user = getCurrentUser();
$documentController = new DocumentController();

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $personnelId = $_POST['personnel_id'] ?? $user['personnel_id'];
    $documentTypeId = $_POST['document_type_id'] ?? '';
    $expiryDate = $_POST['expiry_date'] ?? null;
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $result = $documentController->uploadDocument($personnelId, $documentTypeId, $_FILES['document'], $expiryDate);
        
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('error', $result['message']);
        }
    } else {
        setFlashMessage('error', 'Please select a file to upload');
    }
    
    header('Location: index.php');
    exit();
}

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $documentId = $_POST['document_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $result = $documentController->verifyDocument($documentId, $status, $remarks);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Handle document deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $documentId = $_POST['document_id'] ?? '';
    
    $result = $documentController->deleteDocument($documentId);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Get documents
$personnelId = $_GET['personnel_id'] ?? ($user['role'] === 'employee' ? $user['personnel_id'] : null);
$status = $_GET['status'] ?? null;

if ($personnelId) {
    $documents = $documentController->getPersonnelDocuments($personnelId, $status);
} else {
    // Get all documents for admin/hr
    $db = new Database();
    $sql = "SELECT pd.*, p.first_name, p.last_name, dt.name as document_type_name, dt.category
            FROM personal_documents pd
            LEFT JOIN personnel p ON pd.personnel_id = p.id
            LEFT JOIN document_types dt ON pd.document_type_id = dt.id";
    
    $params = [];
    if ($status) {
        $sql .= " WHERE pd.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY pd.upload_date DESC";
    $documents = $db->fetchAll($sql, $params);
}

// Get document types
$documentTypes = $documentController->getDocumentTypes();

// Get personnel list (for admin/hr)
$personnelList = [];
if (hasPermission('view_all_personnel')) {
    $db = new Database();
    $personnelList = $db->fetchAll("SELECT id, first_name, last_name, employee_number FROM personnel ORDER BY last_name, first_name");
}

// Get expiring documents
$expiringDocuments = $documentController->getExpiringDocuments();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            width: 25px;
        }
        
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .document-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .document-card:hover {
            transform: translateY(-3px);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        
        .status-verified {
            background-color: #28a745;
            color: #fff;
        }
        
        .status-rejected {
            background-color: #dc3545;
            color: #fff;
        }
        
        .status-expired {
            background-color: #6c757d;
            color: #fff;
        }
        
        .file-icon {
            font-size: 2rem;
            color: #667eea;
        }
        
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .top-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .alert-dismissible {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">
                            <i class="fas fa-shield-alt"></i> BJMP
                        </h4>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard/admin.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../personnel/index.php">
                                <i class="fas fa-users"></i> Personnel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../documents/index.php">
                                <i class="fas fa-file-alt"></i> Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../leave/index.php">
                                <i class="fas fa-calendar-alt"></i> Leave Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../clearance/index.php">
                                <i class="fas fa-check-circle"></i> Clearance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reports/index.php">
                                <i class="fas fa-print"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../settings/index.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Top header -->
                <div class="top-header mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Document Management</h1>
                            <p class="text-muted mb-0">Manage and verify personnel documents</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Flash messages -->
                <?php $success = getFlashMessage('success'); ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php $error = getFlashMessage('error'); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters and Upload -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <?php if (hasPermission('view_all_personnel')): ?>
                                        <div class="col-md-4">
                                            <label class="form-label">Personnel</label>
                                            <select name="personnel_id" class="form-select">
                                                <option value="">All Personnel</option>
                                                <?php foreach ($personnelList as $person): ?>
                                                    <option value="<?php echo $person['id']; ?>" <?php echo ($personnelId == $person['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="verified" <?php echo ($status == 'verified') ? 'selected' : ''; ?>>Verified</option>
                                            <option value="rejected" <?php echo ($status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="expired" <?php echo ($status == 'expired') ? 'selected' : ''; ?>>Expired</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter me-2"></i>Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="fas fa-upload me-2"></i>Upload Document
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expiring Documents Alert -->
                <?php if (!empty($expiringDocuments)): ?>
                    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Documents Expiring Soon</h6>
                        <div class="row mt-2">
                            <?php foreach (array_slice($expiringDocuments, 0, 3) as $doc): ?>
                                <div class="col-md-4">
                                    <small>
                                        <strong><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($doc['document_type_name']); ?><br>
                                        Expires: <?php echo formatDate($doc['expiry_date']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Documents Grid -->
                <div class="row">
                    <?php if (empty($documents)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No documents found</h5>
                                <p class="text-muted">Upload your first document to get started.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card document-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="file-icon">
                                                <?php
                                                $icon = 'fa-file';
                                                if (in_array(strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION)), ['pdf'])) {
                                                    $icon = 'fa-file-pdf';
                                                } elseif (in_array(strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])) {
                                                    $icon = 'fa-file-image';
                                                } elseif (in_array(strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION)), ['doc', 'docx'])) {
                                                    $icon = 'fa-file-word';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <span class="status-badge status-<?php echo $doc['status']; ?>">
                                                <?php echo ucfirst($doc['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <h6 class="card-title mb-2"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                        <p class="card-text text-muted small mb-2">
                                            <?php echo htmlspecialchars($doc['document_type_name'] ?? 'Unknown Type'); ?>
                                        </p>
                                        <p class="card-text text-muted small mb-3">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                                        </p>
                                        <p class="card-text text-muted small mb-3">
                                            <i class="fas fa-calendar me-1"></i>
                                            Uploaded: <?php echo formatDate($doc['upload_date']); ?>
                                            <?php if ($doc['expiry_date']): ?>
                                                <br><i class="fas fa-clock me-1"></i>
                                                Expires: <?php echo formatDate($doc['expiry_date']); ?>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <div class="d-flex gap-2">
                                            <a href="download.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            
                                            <?php if (hasPermission('manage_documents') && $doc['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success flex-fill" 
                                                        data-bs-toggle="modal" data-bs-target="#verifyModal<?php echo $doc['id']; ?>">
                                                    <i class="fas fa-check"></i> Verify
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('manage_documents') || $doc['personnel_id'] == $user['personnel_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $doc['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Verify Modal -->
                            <?php if (hasPermission('manage_documents') && $doc['status'] === 'pending'): ?>
                                <div class="modal fade" id="verifyModal<?php echo $doc['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Verify Document</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="verify">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Document</label>
                                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($doc['document_name']); ?></p>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="">Select Status</option>
                                                            <option value="verified">Verified</option>
                                                            <option value="rejected">Rejected</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Remarks</label>
                                                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Verify Document</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?php echo $doc['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Delete Document</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete "<strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>"?</p>
                                                <p class="text-danger small">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <div class="modal-body">
                        <?php if (hasPermission('view_all_personnel')): ?>
                            <div class="mb-3">
                                <label class="form-label">Personnel</label>
                                <select name="personnel_id" class="form-select" required>
                                    <option value="">Select Personnel</option>
                                    <?php foreach ($personnelList as $person): ?>
                                        <option value="<?php echo $person['id']; ?>">
                                            <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="personnel_id" value="<?php echo $user['personnel_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select name="document_type_id" class="form-select" required>
                                <option value="">Select Document Type</option>
                                <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                        <?php if ($type['is_required']) echo '<span class="text-danger">*</span>'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <div class="upload-area">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                                <p class="mb-2">Drag and drop your file here or click to browse</p>
                                <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                <small class="text-muted">Allowed formats: PDF, JPG, PNG, DOC, DOCX (Max: 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Expiry Date (if applicable)</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
