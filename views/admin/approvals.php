<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';

// Check authentication and admin role
$authController = new AuthController();
$authController->validateSession();

if (!hasRole('admin')) {
    redirect('/views/dashboard/admin.php');
}

$authController = new AuthController();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve':
            $userId = $_POST['user_id'] ?? '';
            $role = $_POST['role'] ?? 'officer';
            $result = $authController->approveAccount($userId, $role);
            
            if ($result['success']) {
                setFlashMessage('success', $result['message']);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'reject':
            $userId = $_POST['user_id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            $result = $authController->rejectAccount($userId, $reason);
            
            if ($result['success']) {
                setFlashMessage('success', $result['message']);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'assign_prm':
            $personnelId = $_POST['personnel_id'] ?? '';
            $prmOfficerId = $_POST['prm_officer_id'] ?? '';
            $result = $authController->assignPRMOfficer($personnelId, $prmOfficerId);
            
            if ($result['success']) {
                setFlashMessage('success', $result['message']);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
    }
    
    header('Location: approvals.php');
    exit();
}

// Get data
$pendingAccounts = $authController->getPendingAccounts();
$allUsers = $authController->getAllUsers();
$prmOfficers = $authController->getPRMOfficers();

$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approvals - <?php echo APP_NAME; ?></title>
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
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
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
        
        .status-active {
            background-color: #28a745;
            color: #fff;
        }
        
        .status-inactive {
            background-color: #dc3545;
            color: #fff;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .role-admin {
            background-color: #6f42c1;
            color: #fff;
        }
        
        .role-prm {
            background-color: #17a2b8;
            color: #fff;
        }
        
        .role-officer {
            background-color: #6c757d;
            color: #fff;
        }
        
        .alert-dismissible {
            border-radius: 10px;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 0.875rem;
            border-radius: 6px;
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
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
                            <a class="nav-link active" href="approvals.php">
                                <i class="fas fa-user-check"></i> Account Approvals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> User Management
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
                            <h1 class="h3 mb-0">Account Approvals</h1>
                            <p class="text-muted mb-0">Manage pending account approvals and PRM officer assignments</p>
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
                
                <!-- Pending Accounts -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-clock me-2"></i>
                            Pending Account Approvals
                            <?php if ($pendingAccounts['success'] && !empty($pendingAccounts['data'])): ?>
                                <span class="badge bg-warning ms-2"><?php echo count($pendingAccounts['data']); ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($pendingAccounts['success'] && !empty($pendingAccounts['data'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Employee Number</th>
                                            <th>Position</th>
                                            <th>Rank</th>
                                            <th>Station</th>
                                            <th>Applied</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingAccounts['data'] as $account): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($account['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($account['username']); ?></td>
                                                <td><?php echo htmlspecialchars($account['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($account['position']); ?></td>
                                                <td><?php echo htmlspecialchars($account['rank']); ?></td>
                                                <td><?php echo htmlspecialchars($account['station']); ?></td>
                                                <td><?php echo formatDate($account['created_at'], 'M d, Y'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-success btn-action" 
                                                                data-bs-toggle="modal" data-bs-target="#approveModal"
                                                                data-user-id="<?php echo $account['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?>">
                                                            <i class="fas fa-check me-1"></i>Approve
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger btn-action"
                                                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                                data-user-id="<?php echo $account['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?>">
                                                            <i class="fas fa-times me-1"></i>Reject
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                <h5 class="text-muted">No Pending Approvals</h5>
                                <p class="text-muted">All accounts have been processed</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- All Users Management -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>
                            User Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($allUsers['success'] && !empty($allUsers['data'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Employee Number</th>
                                            <th>Position</th>
                                            <th>Assigned PRM Officer</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allUsers['data'] as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($user['position']); ?></td>
                                                <td>
                                                    <?php if ($user['assigned_prm_officer']): ?>
                                                        <span class="text-success"><?php echo htmlspecialchars($user['assigned_prm_officer']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['role'] === 'officer' && $user['status'] === 'active'): ?>
                                                        <button type="button" class="btn btn-sm btn-primary btn-action"
                                                                data-bs-toggle="modal" data-bs-target="#assignModal"
                                                                data-personnel-id="<?php echo $user['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                            <i class="fas fa-user-plus me-1"></i>Assign PRM
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users text-muted fa-3x mb-3"></i>
                                <h5 class="text-muted">No Users Found</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="user_id" id="approve_user_id">
                    <div class="modal-body">
                        <p>Approve account for: <strong id="approve_name"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Assign Role</label>
                            <select name="role" class="form-select" required>
                                <option value="officer">Officer</option>
                                <option value="prm_officer">PRM Officer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Approve Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" id="reject_user_id">
                    <div class="modal-body">
                        <p>Reject account for: <strong id="reject_name"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection (Optional)</label>
                            <textarea name="reason" class="form-control" rows="3" 
                                      placeholder="Enter reason for rejection..."></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action will permanently delete the account and all associated data.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Reject Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign PRM Officer Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign PRM Officer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_prm">
                    <input type="hidden" name="personnel_id" id="assign_personnel_id">
                    <div class="modal-body">
                        <p>Assign PRM Officer for: <strong id="assign_name"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Select PRM Officer</label>
                            <select name="prm_officer_id" class="form-select" required>
                                <option value="">Choose PRM Officer</option>
                                <?php if ($prmOfficers['success']): ?>
                                    <?php foreach ($prmOfficers['data'] as $officer): ?>
                                        <option value="<?php echo $officer['id']; ?>">
                                            <?php echo htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']); ?>
                                            (<?php echo htmlspecialchars($officer['employee_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Assign PRM Officer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle modal data population
        document.getElementById('approveModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('approve_user_id').value = userId;
            document.getElementById('approve_name').textContent = name;
        });
        
        document.getElementById('rejectModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('reject_user_id').value = userId;
            document.getElementById('reject_name').textContent = name;
        });
        
        document.getElementById('assignModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const personnelId = button.getAttribute('data-personnel-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('assign_personnel_id').value = personnelId;
            document.getElementById('assign_name').textContent = name;
        });
    </script>
</body>
</html>
