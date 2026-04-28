<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';

// Check authentication and authorization
$authController = new AuthController();
$authController->validateSession();

$user = getCurrentUser();

// Check if user has admin role
if (!hasRole('admin') && !hasRole('hr')) {
    setFlashMessage('error', 'Access denied. You do not have permission to access this page.');
    redirect('views/dashboard/employee.php');
}

// Get dashboard statistics
$db = new Database();

// Personnel statistics
$personnelStats = $db->fetch(
    "SELECT COUNT(*) as total, 
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
     FROM users u 
     LEFT JOIN personnel p ON u.id = p.user_id"
);

// Document statistics
$documentStats = $db->fetch(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
     FROM personal_documents"
);

// Leave statistics
$leaveStats = $db->fetch(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
     FROM leave_applications"
);

// Clearance statistics
$clearanceStats = $db->fetch(
    "SELECT COUNT(*) as total_requirements,
            SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) as cleared,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
     FROM employee_clearance"
);

// Recent activities
$recentActivities = $db->fetchAll(
    "SELECT al.*, u.full_name 
     FROM audit_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC 
     LIMIT 10"
);

// Expiring documents
$expiringDocs = $db->fetchAll(
    "SELECT pd.*, p.first_name, p.last_name, dt.name as document_type_name
     FROM personal_documents pd
     LEFT JOIN personnel p ON pd.personnel_id = p.id
     LEFT JOIN document_types dt ON pd.document_type_id = dt.id
     WHERE pd.expiry_date IS NOT NULL 
     AND pd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND pd.expiry_date >= CURDATE()
     AND pd.status = 'verified'
     ORDER BY pd.expiry_date ASC
     LIMIT 5"
);

// Pending leave applications
$pendingLeaves = $db->fetchAll(
    "SELECT la.*, p.first_name, p.last_name
     FROM leave_applications la
     LEFT JOIN personnel p ON la.personnel_id = p.id
     WHERE la.status = 'pending'
     ORDER BY la.created_at DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-approved {
            background-color: #28a745;
        }
        
        .badge-rejected {
            background-color: #dc3545;
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
                            <h1 class="h3 mb-0">Admin Dashboard</h1>
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
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-primary bg-gradient text-white">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h5 class="card-title mb-1">Personnel</h5>
                                <h3 class="mb-1"><?php echo $personnelStats['total']; ?></h3>
                                <small class="text-muted">
                                    <span class="text-success"><?php echo $personnelStats['active']; ?> active</span> | 
                                    <span class="text-danger"><?php echo $personnelStats['inactive']; ?> inactive</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-info bg-gradient text-white">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h5 class="card-title mb-1">Documents</h5>
                                <h3 class="mb-1"><?php echo $documentStats['total']; ?></h3>
                                <small class="text-muted">
                                    <span class="badge badge-pending"><?php echo $documentStats['pending']; ?> pending</span> | 
                                    <span class="badge badge-approved"><?php echo $documentStats['verified']; ?> verified</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon bg-warning bg-gradient text-white">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h5 class="card-title mb-1">Leave Applications</h5>
                                <h3 class="mb-1"><?php echo $leaveStats['total']; ?></h3>
                                <small class="text-muted">
                                    <span class="badge badge-pending"><?php echo $leaveStats['pending']; ?> pending</span> | 
                                    <span class="badge badge-approved"><?php echo $leaveStats['approved']; ?> approved</span>
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
                                <h3 class="mb-1"><?php echo $clearanceStats['total_requirements']; ?></h3>
                                <small class="text-muted">
                                    <span class="badge badge-approved"><?php echo $clearanceStats['cleared']; ?> cleared</span> | 
                                    <span class="badge badge-pending"><?php echo $clearanceStats['pending']; ?> pending</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alerts and Activities -->
                <div class="row">
                    <!-- Expiring Documents -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Expiring Documents
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($expiringDocs)): ?>
                                    <p class="text-muted text-center">No documents expiring soon</p>
                                <?php else: ?>
                                    <?php foreach ($expiringDocs as $doc): ?>
                                        <div class="alert alert-warning alert-sm mb-2">
                                            <small>
                                                <strong><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></strong><br>
                                                <?php echo htmlspecialchars($doc['document_type_name']); ?><br>
                                                Expires: <?php echo formatDate($doc['expiry_date']); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Leave Applications -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Pending Leave Applications
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pendingLeaves)): ?>
                                    <p class="text-muted text-center">No pending applications</p>
                                <?php else: ?>
                                    <?php foreach ($pendingLeaves as $leave): ?>
                                        <div class="activity-item">
                                            <small>
                                                <strong><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></strong><br>
                                                <?php echo htmlspecialchars($leave['leave_type']); ?> Leave<br>
                                                <?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Recent Activities
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivities)): ?>
                                    <p class="text-muted text-center">No recent activities</p>
                                <?php else: ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-item">
                                            <small>
                                                <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong><br>
                                                <?php echo htmlspecialchars($activity['action']); ?><br>
                                                <span class="text-muted"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></span>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
