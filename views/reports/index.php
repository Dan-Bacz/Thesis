<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/PrintController.php';

// Check authentication
$authController = new AuthController();
$authController->validateSession();

$user = getCurrentUser();
$printController = new PrintController();

// Get personnel list for reports
$db = new Database();
$personnelList = $db->fetchAll("SELECT id, first_name, last_name, employee_number FROM personnel ORDER BY last_name, first_name");

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $personnelId = $_POST['personnel_id'] ?? null;
    $year = $_POST['year'] ?? date('Y');
    $department = $_POST['department'] ?? null;
    $status = $_POST['status'] ?? null;
    
    switch ($action) {
        case 'service_record':
            $result = $printController->generateServiceRecord($personnelId);
            if ($result['success']) {
                $reportData = $result['data'];
                $html = $printController->printServiceRecordTemplate($reportData);
                $filename = 'service_record_' . $reportData['personnel']['employee_number'] . '_' . date('Y-m-d') . '.pdf';
                $printController->generatePDF($html, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'pds':
            $result = $printController->generatePersonnelDataSheet($personnelId);
            if ($result['success']) {
                $reportData = $result['data'];
                $html = "<h2>Personnel Data Sheet</h2><pre>" . print_r($reportData, true) . "</pre>";
                $filename = 'pds_' . $reportData['personnel']['employee_number'] . '_' . date('Y-m-d') . '.pdf';
                $printController->generatePDF($html, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'clearance_certificate':
            $result = $printController->generateClearanceCertificate($personnelId);
            if ($result['success']) {
                $reportData = $result['data'];
                $html = "<h2>Clearance Certificate</h2><pre>" . print_r($reportData, true) . "</pre>";
                $filename = 'clearance_certificate_' . date('Y-m-d') . '.pdf';
                $printController->generatePDF($html, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'leave_balance':
            $result = $printController->generateLeaveBalanceReport($personnelId, $year);
            if ($result['success']) {
                $reportData = $result['data'];
                $csvData = [];
                $csvData[] = ['Employee', 'Leave Type', 'Total Credits', 'Used Credits', 'Remaining Credits'];
                
                foreach ($reportData['leave_credits'] as $credit) {
                    $csvData[] = [
                        $credit['first_name'] . ' ' . $credit['last_name'],
                        $credit['leave_type'],
                        $credit['total_credits'],
                        $credit['used_credits'],
                        $credit['remaining_credits']
                    ];
                }
                
                $filename = 'leave_balance_' . $year . '_' . date('Y-m-d') . '.csv';
                $printController->exportToCSV($csvData, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'document_status':
            $result = $printController->generateDocumentStatusReport($personnelId, $status);
            if ($result['success']) {
                $reportData = $result['data'];
                $csvData = [];
                $csvData[] = ['Employee', 'Document Type', 'Category', 'Status', 'Upload Date', 'Expiry Date'];
                
                foreach ($reportData['documents'] as $doc) {
                    $csvData[] = [
                        $doc['first_name'] . ' ' . $doc['last_name'],
                        $doc['document_type_name'],
                        $doc['category'],
                        $doc['status'],
                        $doc['upload_date'],
                        $doc['expiry_date'] ?? 'N/A'
                    ];
                }
                
                $filename = 'document_status_' . date('Y-m-d') . '.csv';
                $printController->exportToCSV($csvData, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'master_list':
            $result = $printController->generateMasterList($department, $status);
            if ($result['success']) {
                $reportData = $result['data'];
                $csvData = [];
                $csvData[] = ['Employee Number', 'Name', 'Position', 'Department', 'Email', 'Status'];
                
                foreach ($reportData['personnel'] as $person) {
                    $csvData[] = [
                        $person['employee_number'],
                        $person['last_name'] . ', ' . $person['first_name'] . ' ' . ($person['middle_name'] ?? ''),
                        $person['position'],
                        $person['department'],
                        $person['email'],
                        $person['user_status']
                    ];
                }
                
                $filename = 'master_list_' . date('Y-m-d') . '.csv';
                $printController->exportToCSV($csvData, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
            
        case 'audit_trail':
            $userId = $_POST['user_id'] ?? null;
            $startDate = $_POST['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? null;
            $action = $_POST['action_filter'] ?? null;
            
            $result = $printController->generateAuditTrail($userId, $startDate, $endDate, $action);
            if ($result['success']) {
                $reportData = $result['data'];
                $csvData = [];
                $csvData[] = ['Date', 'User', 'Action', 'Table', 'IP Address'];
                
                foreach ($reportData['audit_logs'] as $log) {
                    $csvData[] = [
                        $log['created_at'],
                        $log['full_name'],
                        $log['action'],
                        $log['table_name'] ?? 'N/A',
                        $log['ip_address']
                    ];
                }
                
                $filename = 'audit_trail_' . date('Y-m-d') . '.csv';
                $printController->exportToCSV($csvData, $filename);
            } else {
                setFlashMessage('error', $result['message']);
            }
            break;
    }
    
    header('Location: index.php');
    exit();
}

// Get users for audit trail filter
$usersList = $db->fetchAll("SELECT id, username, full_name FROM users ORDER BY full_name");

// Get departments for master list filter
$departments = $db->fetchAll("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
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
        
        .report-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
        }
        
        .report-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
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
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-report {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
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
                            <a class="nav-link" href="../clearance/index.php">
                                <i class="fas fa-check-circle"></i> Clearance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../reports/index.php">
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
                            <h1 class="h3 mb-0">Reports & Printing</h1>
                            <p class="text-muted mb-0">Generate and export various reports and documents</p>
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
                
                <!-- Personnel Reports -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">
                            <i class="fas fa-user-tie me-2"></i>
                            Personnel Reports
                        </h4>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon bg-primary bg-gradient text-white mx-auto">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h5 class="card-title">Service Record</h5>
                                <p class="card-text text-muted">Generate comprehensive service record for personnel</p>
                                <button type="button" class="btn btn-primary btn-report" data-bs-toggle="modal" data-bs-target="#serviceRecordModal">
                                    <i class="fas fa-download me-2"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon bg-info bg-gradient text-white mx-auto">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <h5 class="card-title">Personnel Data Sheet</h5>
                                <p class="card-text text-muted">Generate complete personnel data sheet</p>
                                <button type="button" class="btn btn-info btn-report" data-bs-toggle="modal" data-bs-target="#pdsModal">
                                    <i class="fas fa-download me-2"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon bg-success bg-gradient text-white mx-auto">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <h5 class="card-title">Clearance Certificate</h5>
                                <p class="card-text text-muted">Generate clearance certificate for fully cleared personnel</p>
                                <button type="button" class="btn btn-success btn-report" data-bs-toggle="modal" data-bs-target="#clearanceModal">
                                    <i class="fas fa-download me-2"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Management Reports -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">
                            <i class="fas fa-chart-bar me-2"></i>
                            Management Reports
                        </h4>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon bg-warning bg-gradient text-white mx-auto">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h5 class="card-title">Leave Balance Report</h5>
                                <p class="card-text text-muted">Export leave credits and balance information</p>
                                <button type="button" class="btn btn-warning btn-report" data-bs-toggle="modal" data-bs-target="#leaveBalanceModal">
                                    <i class="fas fa-file-csv me-2"></i>Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon bg-secondary bg-gradient text-white mx-auto">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <h5 class="card-title">Document Status Report</h5>
                                <p class="card-text text-muted">Export document verification status</p>
                                <button type="button" class="btn btn-secondary btn-report" data-bs-toggle="modal" data-bs-target="#documentStatusModal">
                                    <i class="fas fa-file-csv me-2"></i>Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card report-card">
                            <div class="card-body text-center">
                                <div class="report-icon bg-dark bg-gradient text-white mx-auto">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h5 class="card-title">Master List</h5>
                                <p class="card-text text-muted">Export complete personnel master list</p>
                                <button type="button" class="btn btn-dark btn-report" data-bs-toggle="modal" data-bs-target="#masterListModal">
                                    <i class="fas fa-file-csv me-2"></i>Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Reports -->
                <?php if (hasRole('admin')): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <h4 class="mb-3">
                                <i class="fas fa-shield-alt me-2"></i>
                                System Reports
                            </h4>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card report-card">
                                <div class="card-body text-center">
                                    <div class="report-icon bg-danger bg-gradient text-white mx-auto">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <h5 class="card-title">Audit Trail</h5>
                                    <p class="card-text text-muted">Export system activity logs</p>
                                    <button type="button" class="btn btn-danger btn-report" data-bs-toggle="modal" data-bs-target="#auditTrailModal">
                                        <i class="fas fa-file-csv me-2"></i>Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Service Record Modal -->
    <div class="modal fade" id="serviceRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Service Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="service_record">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Personnel</label>
                            <select name="personnel_id" class="form-select" required>
                                <option value="">Choose personnel</option>
                                <?php foreach ($personnelList as $person): ?>
                                    <option value="<?php echo $person['id']; ?>">
                                        <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?> (<?php echo htmlspecialchars($person['employee_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Generate PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- PDS Modal -->
    <div class="modal fade" id="pdsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Personnel Data Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="pds">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Personnel</label>
                            <select name="personnel_id" class="form-select" required>
                                <option value="">Choose personnel</option>
                                <?php foreach ($personnelList as $person): ?>
                                    <option value="<?php echo $person['id']; ?>">
                                        <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?> (<?php echo htmlspecialchars($person['employee_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-download me-2"></i>Generate PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Clearance Certificate Modal -->
    <div class="modal fade" id="clearanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Clearance Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="clearance_certificate">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Personnel</label>
                            <select name="personnel_id" class="form-select" required>
                                <option value="">Choose personnel</option>
                                <?php foreach ($personnelList as $person): ?>
                                    <option value="<?php echo $person['id']; ?>">
                                        <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?> (<?php echo htmlspecialchars($person['employee_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Note: Certificate can only be generated for personnel who are fully cleared.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Generate PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Leave Balance Modal -->
    <div class="modal fade" id="leaveBalanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Leave Balance Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="leave_balance">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Personnel (Optional)</label>
                            <select name="personnel_id" class="form-select">
                                <option value="">All Personnel</option>
                                <?php foreach ($personnelList as $person): ?>
                                    <option value="<?php echo $person['id']; ?>">
                                        <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" min="2020" max="<?php echo date('Y') + 1; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Document Status Modal -->
    <div class="modal fade" id="documentStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Document Status Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="document_status">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Personnel (Optional)</label>
                            <select name="personnel_id" class="form-select">
                                <option value="">All Personnel</option>
                                <?php foreach ($personnelList as $person): ?>
                                    <option value="<?php echo $person['id']; ?>">
                                        <?php echo htmlspecialchars($person['last_name'] . ', ' . $person['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status (Optional)</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Master List Modal -->
    <div class="modal fade" id="masterListModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Master List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="master_list">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department (Optional)</label>
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                                <option value="">All Status</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Audit Trail Modal -->
    <div class="modal fade" id="auditTrailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Audit Trail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="audit_trail">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">User (Optional)</label>
                                <select name="user_id" class="form-select">
                                    <option value="">All Users</option>
                                    <?php foreach ($usersList as $userItem): ?>
                                        <option value="<?php echo $userItem['id']; ?>">
                                            <?php echo htmlspecialchars($userItem['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Action Filter (Optional)</label>
                                <input type="text" name="action_filter" class="form-control" placeholder="e.g., login, document_upload">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date (Optional)</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date (Optional)</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
