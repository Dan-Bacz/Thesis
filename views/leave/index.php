<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/LeaveController.php';

// Check authentication
$authController = new AuthController();
$authController->validateSession();

$user = getCurrentUser();
$leaveController = new LeaveController();

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    $personnelId = $_POST['personnel_id'] ?? $user['personnel_id'];
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    $result = $leaveController->applyLeave($personnelId, $leaveType, $startDate, $endDate, $reason);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Handle leave processing (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $leaveId = $_POST['leave_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $rejectionReason = $_POST['rejection_reason'] ?? '';
    
    $result = $leaveController->processLeaveApplication($leaveId, $status, $rejectionReason);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Handle leave cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $leaveId = $_POST['leave_id'] ?? '';
    
    $result = $leaveController->cancelLeaveApplication($leaveId);
    
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    header('Location: index.php');
    exit();
}

// Get leave applications
$personnelId = $_GET['personnel_id'] ?? ($user['role'] === 'employee' ? $user['personnel_id'] : null);
$status = $_GET['status'] ?? null;
$page = $_GET['page'] ?? 1;

if ($personnelId) {
    $leaveApplications = $leaveController->getLeaveApplications($personnelId, $status, $page);
} else {
    $leaveApplications = $leaveController->getLeaveApplications(null, $status, $page);
}

// Get personnel list (for admin/hr)
$personnelList = [];
if (hasPermission('view_all_personnel')) {
    $db = new Database();
    $personnelList = $db->fetchAll("SELECT id, first_name, last_name, employee_number FROM personnel ORDER BY last_name, first_name");
}

// Get leave credits for current user
$leaveCredits = $leaveController->getLeaveCredits($user['personnel_id']);

// Get pending leave applications for approval
$pendingLeaves = $leaveController->getPendingLeaveApplications();

// Get statistics
$statistics = $leaveController->getLeaveStatistics($personnelId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - <?php echo APP_NAME; ?></title>
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
        
        .leave-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .leave-card:hover {
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
        
        .status-approved {
            background-color: #28a745;
            color: #fff;
        }
        
        .status-rejected {
            background-color: #dc3545;
            color: #fff;
        }
        
        .status-cancelled {
            background-color: #6c757d;
            color: #fff;
        }
        
        .leave-type-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .credits-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
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
        
        .calendar-icon {
            color: #667eea;
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
                            <a class="nav-link active" href="../leave/index.php">
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
                            <h1 class="h3 mb-0">Leave Management</h1>
                            <p class="text-muted mb-0">Apply and manage leave applications</p>
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
                
                <!-- Leave Credits -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    My Leave Credits
                                </h5>
                                <div class="row">
                                    <?php foreach ($leaveCredits as $credit): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card credits-card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($credit['leave_type']); ?></h6>
                                                    <h4 class="mb-1"><?php echo $credit['remaining_credits']; ?></h4>
                                                    <small>days remaining</small>
                                                    <div class="mt-2">
                                                        <small><?php echo $credit['used_credits']; ?> used / <?php echo $credit['total_credits']; ?> total</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#applyModal">
                                    <i class="fas fa-plus-circle me-2"></i>Apply for Leave
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Applications for Approval -->
                <?php if (hasPermission('approve_leave') && !empty($pendingLeaves)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Pending Leave Applications Requiring Approval
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Leave Type</th>
                                                    <th>Duration</th>
                                                    <th>Reason</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($pendingLeaves, 0, 5) as $leave): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                                        <td><?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?> (<?php echo $leave['total_days']; ?> days)</td>
                                                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)); ?>...</td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                                    data-bs-toggle="modal" data-bs-target="#processModal<?php echo $leave['id']; ?>">
                                                                <i class="fas fa-check"></i> Review
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- Process Modal -->
                                                    <div class="modal fade" id="processModal<?php echo $leave['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Review Leave Application</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <input type="hidden" name="action" value="process">
                                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Employee</label>
                                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Leave Type</label>
                                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($leave['leave_type']); ?></p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Duration</label>
                                                                            <p class="form-control-plaintext">
                                                <?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?>
                                                (<?php echo $leave['total_days']; ?> days)
                                            </p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Reason</label>
                                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($leave['reason']); ?></p>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Action</label>
                                                                            <select name="status" class="form-select" required>
                                                                                <option value="">Select Action</option>
                                                                                <option value="approved">Approve</option>
                                                                                <option value="rejected">Reject</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
                                                                            <label class="form-label">Rejection Reason</label>
                                                                            <textarea name="rejection_reason" class="form-control" rows="3"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success">Process Application</button>
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
                
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
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
                                            <option value="approved" <?php echo ($status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo ($status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
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
                </div>
                
                <!-- Leave Applications -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Leave Applications
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($leaveApplications)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No leave applications found</h5>
                                        <p class="text-muted">Apply for leave to get started.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Leave Type</th>
                                                    <th>Duration</th>
                                                    <th>Days</th>
                                                    <th>Reason</th>
                                                    <th>Status</th>
                                                    <th>Applied On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leaveApplications as $leave): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>
                                                            <?php if ($leave['approved_by_name']): ?>
                                                                <br><small class="text-muted">Approved by: <?php echo htmlspecialchars($leave['approved_by_name']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="leave-type-icon bg-light text-primary">
                                                                <?php
                                                                $icon = 'fa-calendar';
                                                                switch ($leave['leave_type']) {
                                                                    case 'Vacation': $icon = 'fa-umbrella-beach'; break;
                                                                    case 'Sick': $icon = 'fa-heartbeat'; break;
                                                                    case 'Maternity': $icon = 'fa-baby'; break;
                                                                    case 'Paternity': $icon = 'fa-baby-carriage'; break;
                                                                    case 'Emergency': $icon = 'fa-ambulance'; break;
                                                                    case 'Special Privilege': $icon = 'fa-star'; break;
                                                                    case 'Study Leave': $icon = 'fa-graduation-cap'; break;
                                                                }
                                                                ?>
                                                                <i class="fas <?php echo $icon; ?>"></i>
                                                            </div>
                                                            <?php echo htmlspecialchars($leave['leave_type']); ?>
                                                        </td>
                                                        <td>
                                                            <?php echo formatDate($leave['start_date']); ?><br>
                                                            <?php echo formatDate($leave['end_date']); ?>
                                                        </td>
                                                        <td><?php echo $leave['total_days']; ?></td>
                                                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)); ?><?php echo strlen($leave['reason']) > 50 ? '...' : ''; ?></td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $leave['status']; ?>">
                                                                <?php echo ucfirst($leave['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatDate($leave['created_at']); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <?php if ($leave['status'] === 'pending' && ($leave['personnel_id'] == $user['personnel_id'] || hasPermission('approve_leave'))): ?>
                                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                            data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $leave['id']; ?>">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if (hasPermission('approve_leave') && $leave['status'] === 'pending'): ?>
                                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                                            data-bs-toggle="modal" data-bs-target="#processModal<?php echo $leave['id']; ?>">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- Cancel Modal -->
                                                    <?php if ($leave['status'] === 'pending' && ($leave['personnel_id'] == $user['personnel_id'] || hasPermission('approve_leave'))): ?>
                                                        <div class="modal fade" id="cancelModal<?php echo $leave['id']; ?>" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Cancel Leave Application</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <form method="POST">
                                                                        <input type="hidden" name="action" value="cancel">
                                                                        <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                                        <div class="modal-body">
                                                                            <p>Are you sure you want to cancel this leave application?</p>
                                                                            <p><strong><?php echo htmlspecialchars($leave['leave_type']); ?></strong> leave from <?php echo formatDate($leave['start_date']); ?> to <?php echo formatDate($leave['end_date']); ?></p>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                                                                            <button type="submit" class="btn btn-warning">Yes, Cancel</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Apply Leave Modal -->
    <div class="modal fade" id="applyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="apply">
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
                            <label class="form-label">Leave Type</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="">Select Leave Type</option>
                                <?php foreach (LEAVE_TYPES as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide rejection reason based on status selection
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                const rejectionReasonDiv = this.closest('.modal').querySelector('#rejectionReasonDiv');
                if (this.value === 'rejected') {
                    rejectionReasonDiv.style.display = 'block';
                } else {
                    rejectionReasonDiv.style.display = 'none';
                }
            });
        });
        
        // Set minimum date to today
        document.querySelectorAll('input[type="date"]').forEach(input => {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('min', today);
        });
    </script>
</body>
</html>
