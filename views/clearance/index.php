<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/ClearanceController.php';

// Check authentication
$authController = new AuthController();
$authController->validateSession();

$user = getCurrentUser();
$clearanceController = new ClearanceController();

// Handle clearance processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $clearanceId = $_POST['clearance_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $result = $clearanceController->processClearance($clearanceId, $status, $remarks);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Handle clearance initialization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initialize') {
    $personnelId = $_POST['personnel_id'] ?? '';
    
    $result = $clearanceController->initializeClearance($personnelId);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Get personnel list
$db = new Database();
$personnelList = $db->fetchAll("SELECT id, first_name, last_name, employee_number FROM personnel ORDER BY last_name, first_name");

// Get current personnel's clearance
$personnelId = $_GET['personnel_id'] ?? ($user['role'] === 'employee' ? $user['personnel_id'] : null);

if ($personnelId) {
    $employeeClearance = $clearanceController->getEmployeeClearance($personnelId);
    $clearanceSummary = $clearanceController->getClearanceSummary($personnelId);
    $isFullyCleared = $clearanceController->isFullyCleared($personnelId);
} else {
    $employeeClearance = [];
    $clearanceSummary = [];
    $isFullyCleared = false;
}

// Get pending clearances for processing
$pendingClearances = $clearanceController->getPendingClearances();

// Get clearance statistics
$clearanceStats = $clearanceController->getClearanceStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Management - <?php echo APP_NAME; ?></title>
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
        
        .clearance-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .clearance-card:hover {
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
        
        .status-cleared {
            background-color: #28a745;
            color: #fff;
        }
        
        .status-not_cleared {
            background-color: #dc3545;
            color: #fff;
        }
        
        .status-exempted {
            background-color: #6c757d;
            color: #fff;
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        
        .progress-ring circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .clearance-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        
        .department-badge {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
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
        
        .check-icon {
            color: #28a745;
            font-size: 1.5rem;
        }
        
        .pending-icon {
            color: #ffc107;
            font-size: 1.5rem;
        }
        
        .rejected-icon {
            color: #dc3545;
            font-size: 1.5rem;
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
                            <a class="nav-link" href="../documents/index.php">
                                <i class="fas fa-file-alt"></i> Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../leave/index.php">
                                <i class="fas fa-calendar-alt"></i> Leave Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../clearance/index.php">
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
                            <h1 class="h3 mb-0">Clearance Management</h1>
                            <p class="text-muted mb-0">Track and manage employee clearance status</p>
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
                
                <!-- Personnel Selection and Summary -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Select Personnel</label>
                                        <select name="personnel_id" class="form-select" onchange="this.form.submit()">
                                            <option value="">Select Personnel</option>
                                            <?php foreach ($personnelList as $person): ?>
                                                <option value="<?php echo $person['id']; ?>" <?php echo ($personnelId == $person['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?> (<?php echo htmlspecialchars($person['employee_number']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <?php if ($personnelId && !empty($employeeClearance)): ?>
                                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#certificateModal">
                                                    <i class="fas fa-certificate me-2"></i>Generate Certificate
                                                </button>
                                            <?php elseif ($personnelId && empty($employeeClearance)): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="initialize">
                                                    <input type="hidden" name="personnel_id" value="<?php echo $personnelId; ?>">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-play me-2"></i>Initialize Clearance
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($personnelId && !empty($clearanceSummary)): ?>
                        <div class="col-lg-4">
                            <div class="card clearance-summary">
                                <div class="card-body text-center">
                                    <h5 class="card-title mb-3">Clearance Summary</h5>
                                    <div class="progress-ring">
                                        <svg width="120" height="120">
                                            <circle cx="60" cy="60" r="50" stroke="rgba(255,255,255,0.3)" stroke-width="10" fill="none"/>
                                            <circle cx="60" cy="60" r="50" stroke="white" stroke-width="10" fill="none"
                                                    stroke-dasharray="<?php echo $clearanceSummary['completion_percentage'] * 3.14; ?> 314"
                                                    stroke-dashoffset="0"/>
                                        </svg>
                                        <div class="position-absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                                            <h4 class="mb-0"><?php echo $clearanceSummary['completion_percentage']; ?>%</h4>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small>
                                            <span class="text-success"><?php echo $clearanceSummary['cleared_count']; ?> Cleared</span> | 
                                            <span class="text-warning"><?php echo $clearanceSummary['pending_count']; ?> Pending</span>
                                        </small>
                                    </div>
                                    <?php if ($isFullyCleared): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-success">Fully Cleared</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pending Clearances for Processing -->
                <?php if (hasPermission('manage_clearance') && !empty($pendingClearances)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Pending Clearances Requiring Processing
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Department</th>
                                                    <th>Requirement</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($pendingClearances, 0, 5) as $clearance): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($clearance['first_name'] . ' ' . $clearance['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($clearance['department']); ?></td>
                                                        <td><?php echo htmlspecialchars($clearance['requirement_name']); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                                    data-bs-toggle="modal" data-bs-target="#processModal<?php echo $clearance['id']; ?>">
                                                                <i class="fas fa-check"></i> Process
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- Process Modal -->
                                                    <div class="modal fade" id="processModal<?php echo $clearance['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Process Clearance</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <input type="hidden" name="action" value="process">
                                                                    <input type="hidden" name="clearance_id" value="<?php echo $clearance['id']; ?>">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Employee</label>
                                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($clearance['first_name'] . ' ' . $clearance['last_name']); ?></p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Requirement</label>
                                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($clearance['requirement_name']); ?></p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Department</label>
                                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($clearance['department']); ?></p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Status</label>
                                                                            <select name="status" class="form-select" required>
                                                                                <option value="">Select Status</option>
                                                                                <option value="cleared">Cleared</option>
                                                                                <option value="not_cleared">Not Cleared</option>
                                                                                <option value="exempted">Exempted</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Remarks</label>
                                                                            <textarea name="remarks" class="form-control" rows="3"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success">Process Clearance</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Clearance Details -->
                <?php if ($personnelId && !empty($employeeClearance)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list-check me-2"></i>
                                        Clearance Requirements
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($employeeClearance as $clearance): ?>
                                            <div class="col-lg-6 col-xl-4 mb-3">
                                                <div class="card clearance-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($clearance['name']); ?></h6>
                                                            <span class="status-badge status-<?php echo $clearance['status']; ?>">
                                                                <?php echo ucfirst($clearance['status']); ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <p class="card-text text-muted small mb-2">
                                                            <?php echo htmlspecialchars($clearance['department']); ?>
                                                        </p>
                                                        
                                                        <?php if ($clearance['description']): ?>
                                                            <p class="card-text text-muted small mb-2">
                                                                <?php echo htmlspecialchars($clearance['description']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex align-items-center mb-2">
                                                            <?php
                                                            $icon = 'pending-icon';
                                                            switch ($clearance['status']) {
                                                                case 'cleared': $icon = 'check-icon'; break;
                                                                case 'not_cleared': $icon = 'rejected-icon'; break;
                                                                case 'exempted': $icon = 'check-icon'; break;
                                                            }
                                                            ?>
                                                            <i class="fas <?php echo $icon; ?> me-2"></i>
                                                            <small class="text-muted">
                                                                <?php if ($clearance['cleared_date']): ?>
                                                                    Processed: <?php echo formatDate($clearance['cleared_date']); ?>
                                                                <?php else: ?>
                                                                    Pending processing
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <?php if ($clearance['cleared_by_name']): ?>
                                                            <p class="card-text text-muted small mb-2">
                                                                <i class="fas fa-user me-1"></i>
                                                                Processed by: <?php echo htmlspecialchars($clearance['cleared_by_name']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($clearance['remarks']): ?>
                                                            <p class="card-text text-muted small mb-0">
                                                                <i class="fas fa-comment me-1"></i>
                                                                <?php echo htmlspecialchars($clearance['remarks']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (hasPermission('manage_clearance') && $clearance['status'] === 'pending'): ?>
                                                            <div class="mt-2">
                                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                                        data-bs-toggle="modal" data-bs-target="#processModal<?php echo $clearance['id']; ?>">
                                                                    <i class="fas fa-check"></i> Process
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Process Modal for each clearance -->
                                            <?php if (hasPermission('manage_clearance') && $clearance['status'] === 'pending'): ?>
                                                <div class="modal fade" id="processModal<?php echo $clearance['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Process Clearance</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="process">
                                                                <input type="hidden" name="clearance_id" value="<?php echo $clearance['id']; ?>">
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Requirement</label>
                                                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($clearance['name']); ?></p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Department</label>
                                                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($clearance['department']); ?></p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Status</label>
                                                                        <select name="status" class="form-select" required>
                                                                            <option value="">Select Status</option>
                                                                            <option value="cleared">Cleared</option>
                                                                            <option value="not_cleared">Not Cleared</option>
                                                                            <option value="exempted">Exempted</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Remarks</label>
                                                                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-success">Process Clearance</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($personnelId && empty($employeeClearance)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Clearance Not Initialized</h5>
                                <p class="text-muted">Click "Initialize Clearance" to start the clearance process for this employee.</p>
                            </div>
                        </div>
                    </div>
                <?php elseif (!$personnelId): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Select Personnel</h5>
                                <p class="text-muted">Choose an employee to view and manage their clearance status.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Certificate Modal -->
    <?php if ($personnelId && $isFullyCleared): ?>
        <div class="modal fade" id="certificateModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Clearance Certificate</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <h4>Republic of the Philippines</h4>
                            <h3>Bureau of Jail Management and Penology</h3>
                            <h5>Clearance Certificate</h5>
                        </div>
                        
                        <div class="mb-4">
                            <p>This is to certify that the following employee has been fully cleared from all obligations:</p>
                            
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Employee Name:</strong></td>
                                    <td><?php echo htmlspecialchars($personnelList[array_search($personnelId, array_column($personnelList, 'id'))]['first_name'] . ' ' . $personnelList[array_search($personnelId, array_column($personnelList, 'id'))]['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Employee Number:</strong></td>
                                    <td><?php echo htmlspecialchars($personnelList[array_search($personnelId, array_column($personnelList, 'id'))]['employee_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date Issued:</strong></td>
                                    <td><?php echo date('F j, Y'); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="mb-4">
                            <h6>Clearance Requirements Completed:</h6>
                            <ul class="list-group">
                                <?php foreach ($employeeClearance as $clearance): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($clearance['name']); ?>
                                        <span class="status-badge status-<?php echo $clearance['status']; ?>">
                                            <?php echo ucfirst($clearance['status']); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Certificate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
