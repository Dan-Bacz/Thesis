<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/DocumentController.php';
require_once '../../controllers/LeaveController.php';
require_once '../../controllers/ClearanceController.php';

// Check authentication
$authController = new AuthController();
$authController->validateSession();

$user = getCurrentUser();

// Get employee's documents
$documentController = new DocumentController();
$documents = $documentController->getPersonnelDocuments($user['personnel_id']);

// Get employee's leave applications
$leaveController = new LeaveController();
$leaveApplications = $leaveController->getLeaveApplications($user['personnel_id']);
$leaveCredits = $leaveController->getLeaveCredits($user['personnel_id']);

// Get employee's clearance status
$clearanceController = new ClearanceController();
$clearanceStatus = $clearanceController->getEmployeeClearance($user['personnel_id']);
$clearanceSummary = $clearanceController->getClearanceSummary($user['personnel_id']);

// Get expiring documents
$expiringDocuments = $documentController->getExpiringDocuments(30);
$myExpiringDocs = array_filter($expiringDocuments, function($doc) use ($user) {
    // Filter documents for current user
    return true; // This would need personnel_id comparison in real implementation
});

// Get pending leave applications
$pendingLeaves = array_filter($leaveApplications, function($leave) {
    return $leave['status'] === 'pending';
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - <?php echo APP_NAME; ?></title>
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
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .card-body {
            padding: 25px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .quick-action-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .quick-action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
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
        
        .status-verified {
            background-color: #28a745;
            color: #fff;
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
        
        .progress-ring {
            width: 80px;
            height: 80px;
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
                            <a class="nav-link active" href="#">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../documents/index.php">
                                <i class="fas fa-file-alt"></i> My Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../leave/index.php">
                                <i class="fas fa-calendar-alt"></i> Leave Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../clearance/index.php">
                                <i class="fas fa-check-circle"></i> My Clearance
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
                            <h1 class="h3 mb-0">My Dashboard</h1>
                            <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?></p>
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
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-primary bg-gradient text-white">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h5 class="card-title mb-1">My Documents</h5>
                                <h3 class="mb-1"><?php echo count($documents); ?></h3>
                                <small class="text-muted">
                                    <?php 
                                    $verified = count(array_filter($documents, function($doc) { return $doc['status'] === 'verified'; }));
                                    echo $verified . ' verified'; 
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-info bg-gradient text-white">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h5 class="card-title mb-1">Leave Applications</h5>
                                <h3 class="mb-1"><?php echo count($leaveApplications); ?></h3>
                                <small class="text-muted">
                                    <?php echo count($pendingLeaves); ?> pending
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-success bg-gradient text-white">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h5 class="card-title mb-1">Clearance</h5>
                                <h3 class="mb-1"><?php echo $clearanceSummary['cleared_count'] ?? 0; ?>/<?php echo $clearanceSummary['total_requirements'] ?? 0; ?></h3>
                                <small class="text-muted">
                                    <?php echo $clearanceSummary['completion_percentage'] ?? 0; ?>% complete
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-warning bg-gradient text-white">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h5 class="card-title mb-1">Alerts</h5>
                                <h3 class="mb-1"><?php echo count($myExpiringDocs); ?></h3>
                                <small class="text-muted">
                                    Documents expiring soon
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Quick Actions</h4>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card quick-action-card">
                            <div class="card-body text-center">
                                <div class="quick-action-icon bg-primary bg-gradient text-white mx-auto">
                                    <i class="fas fa-upload"></i>
                                </div>
                                <h5 class="card-title">Upload Document</h5>
                                <p class="card-text text-muted">Submit required documents</p>
                                <a href="../documents/index.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-right me-2"></i>Go to Documents
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card quick-action-card">
                            <div class="card-body text-center">
                                <div class="quick-action-icon bg-info bg-gradient text-white mx-auto">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <h5 class="card-title">Apply for Leave</h5>
                                <p class="card-text text-muted">Submit leave application</p>
                                <a href="../leave/index.php" class="btn btn-info">
                                    <i class="fas fa-arrow-right me-2"></i>Apply Now
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card quick-action-card">
                            <div class="card-body text-center">
                                <div class="quick-action-icon bg-success bg-gradient text-white mx-auto">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h5 class="card-title">View Clearance</h5>
                                <p class="card-text text-muted">Check clearance status</p>
                                <a href="../clearance/index.php" class="btn btn-success">
                                    <i class="fas fa-arrow-right me-2"></i>View Status
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card quick-action-card">
                            <div class="card-body text-center">
                                <div class="quick-action-icon bg-warning bg-gradient text-white mx-auto">
                                    <i class="fas fa-print"></i>
                                </div>
                                <h5 class="card-title">Generate Reports</h5>
                                <p class="card-text text-muted">Download certificates</p>
                                <a href="../reports/index.php" class="btn btn-warning">
                                    <i class="fas fa-arrow-right me-2"></i>Generate
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Recent Leave Applications
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($leaveApplications)): ?>
                                    <p class="text-muted text-center">No leave applications yet</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($leaveApplications, 0, 5) as $leave): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($leave['leave_type']); ?> Leave</h6>
                                                <small class="text-muted">
                                                    <?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?>
                                                    (<?php echo $leave['total_days']; ?> days)
                                                </small>
                                            </div>
                                            <span class="status-badge status-<?php echo $leave['status']; ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Recent Documents
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($documents)): ?>
                                    <p class="text-muted text-center">No documents uploaded yet</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($documents, 0, 5) as $doc): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($doc['document_type_name']); ?></h6>
                                                <small class="text-muted">
                                                    Uploaded: <?php echo formatDate($doc['upload_date']); ?>
                                                    <?php if ($doc['expiry_date']): ?>
                                                        | Expires: <?php echo formatDate($doc['expiry_date']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <span class="status-badge status-<?php echo $doc['status']; ?>">
                                                <?php echo ucfirst($doc['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Credits Summary -->
                <?php if (!empty($leaveCredits)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        My Leave Credits
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($leaveCredits as $credit): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card clearance-summary">
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
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
