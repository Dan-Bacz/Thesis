<?php
/**
 * Printing and Report Generation Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class PrintController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Generate service record
     */
    public function generateServiceRecord($personnelId) {
        try {
            // Get personnel information
            $personnel = $this->db->fetch(
                "SELECT p.*, u.email, u.department, u.position 
                 FROM personnel p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 WHERE p.id = ?",
                [$personnelId]
            );
            
            if (!$personnel) {
                return ['success' => false, 'message' => 'Personnel not found'];
            }
            
            // Get service records
            $serviceRecords = $this->db->fetchAll(
                "SELECT * FROM service_records 
                 WHERE personnel_id = ? 
                 ORDER BY date_from DESC",
                [$personnelId]
            );
            
            // Get current clearance status
            $clearanceController = new ClearanceController();
            $clearanceStatus = $clearanceController->getClearanceSummary($personnelId);
            
            // Get document verification status
            $documents = $this->db->fetchAll(
                "SELECT dt.name, dt.category, pd.status, pd.verified_date
                 FROM personal_documents pd
                 LEFT JOIN document_types dt ON pd.document_type_id = dt.id
                 WHERE pd.personnel_id = ? AND dt.is_required = 1
                 ORDER BY dt.category, dt.name",
                [$personnelId]
            );
            
            $data = [
                'personnel' => $personnel,
                'service_records' => $serviceRecords,
                'clearance_status' => $clearanceStatus,
                'documents' => $documents,
                'generated_date' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['user']['full_name']
            ];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'service_record_generation', 'personnel', $personnelId);
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate service record error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating service record'];
        }
    }
    
    /**
     * Generate personnel data sheet
     */
    public function generatePersonnelDataSheet($personnelId) {
        try {
            // Get complete personnel information
            $personnel = $this->db->fetch(
                "SELECT p.*, u.email, u.department, u.position, u.employee_id
                 FROM personnel p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 WHERE p.id = ?",
                [$personnelId]
            );
            
            if (!$personnel) {
                return ['success' => false, 'message' => 'Personnel not found'];
            }
            
            // Get education records (if exists)
            $education = $this->db->fetchAll(
                "SELECT * FROM education_records WHERE personnel_id = ? ORDER BY year_graduated DESC",
                [$personnelId]
            );
            
            // Get training records (if exists)
            $trainings = $this->db->fetchAll(
                "SELECT * FROM training_records WHERE personnel_id = ? ORDER BY training_date DESC",
                [$personnelId]
            );
            
            // Get family information (if exists)
            $family = $this->db->fetchAll(
                "SELECT * FROM family_information WHERE personnel_id = ? ORDER BY relationship",
                [$personnelId]
            );
            
            $data = [
                'personnel' => $personnel,
                'education' => $education,
                'trainings' => $trainings,
                'family' => $family,
                'generated_date' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['user']['full_name']
            ];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'pds_generation', 'personnel', $personnelId);
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate PDS error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating personnel data sheet'];
        }
    }
    
    /**
     * Generate clearance certificate
     */
    public function generateClearanceCertificate($personnelId) {
        try {
            $clearanceController = new ClearanceController();
            
            // Check if fully cleared
            if (!$clearanceController->isFullyCleared($personnelId)) {
                return ['success' => false, 'message' => 'Personnel is not fully cleared'];
            }
            
            // Get personnel information
            $personnel = $this->db->fetch(
                "SELECT p.*, u.department, u.position 
                 FROM personnel p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 WHERE p.id = ?",
                [$personnelId]
            );
            
            if (!$personnel) {
                return ['success' => false, 'message' => 'Personnel not found'];
            }
            
            // Get clearance details
            $clearanceDetails = $clearanceController->getEmployeeClearance($personnelId);
            
            $data = [
                'personnel' => $personnel,
                'clearance_details' => $clearanceDetails,
                'certificate_date' => date('Y-m-d'),
                'issued_by' => $_SESSION['user']['full_name'],
                'position' => $_SESSION['user']['position']
            ];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'clearance_certificate_generation', 'employee_clearance', null, 
                       null, ['personnel_id' => $personnelId]);
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate clearance certificate error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating clearance certificate'];
        }
    }
    
    /**
     * Generate leave balance report
     */
    public function generateLeaveBalanceReport($personnelId = null, $year = null) {
        try {
            $year = $year ?: date('Y');
            
            $sql = "SELECT lc.*, p.first_name, p.last_name, p.employee_number
                    FROM leave_credits lc
                    LEFT JOIN personnel p ON lc.personnel_id = p.id
                    WHERE lc.year = ?";
            
            $params = [$year];
            
            if ($personnelId) {
                $sql .= " AND lc.personnel_id = ?";
                $params[] = $personnelId;
            }
            
            $sql .= " ORDER BY p.last_name, p.first_name, lc.leave_type";
            
            $leaveCredits = $this->db->fetchAll($sql, $params);
            
            $data = [
                'leave_credits' => $leaveCredits,
                'year' => $year,
                'generated_date' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['user']['full_name']
            ];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'leave_balance_report', 'leave_credits', null);
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate leave balance report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating leave balance report'];
        }
    }
    
    /**
     * Generate document status report
     */
    public function generateDocumentStatusReport($personnelId = null, $status = null) {
        try {
            $sql = "SELECT pd.*, p.first_name, p.last_name, p.employee_number, dt.name as document_type_name, dt.category
                    FROM personal_documents pd
                    LEFT JOIN personnel p ON pd.personnel_id = p.id
                    LEFT JOIN document_types dt ON pd.document_type_id = dt.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($personnelId) {
                $sql .= " AND pd.personnel_id = ?";
                $params[] = $personnelId;
            }
            
            if ($status) {
                $sql .= " AND pd.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY p.last_name, p.first_name, dt.category, dt.name";
            
            $documents = $this->db->fetchAll($sql, $params);
            
            $data = [
                'documents' => $documents,
                'generated_date' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['user']['full_name']
            ];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'document_status_report', 'personal_documents', null);
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate document status report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating document status report'];
        }
    }
    
    /**
     * Generate master list report
     */
    public function generateMasterList($department = null, $status = 'active') {
        try {
            $sql = "SELECT p.*, u.email, u.department, u.position, u.status as user_status
                    FROM personnel p
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE u.status = ?";
            
            $params = [$status];
            
            if ($department) {
                $sql .= " AND u.department = ?";
                $params[] = $department;
            }
            
            $sql .= " ORDER BY p.last_name, p.first_name";
            
            $personnel = $this->db->fetchAll($sql, $params);
            
            $data = [
                'personnel' => $personnel,
                'department' => $department,
                'status' => $status,
                'generated_date' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['user']['full_name']
            ];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'master_list_generation', 'personnel', null);
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate master list error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating master list'];
        }
    }
    
    /**
     * Generate audit trail report
     */
    public function generateAuditTrail($userId = null, $startDate = null, $endDate = null, $action = null) {
        try {
            $sql = "SELECT al.*, u.full_name, u.username
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($userId) {
                $sql .= " AND al.user_id = ?";
                $params[] = $userId;
            }
            
            if ($startDate) {
                $sql .= " AND al.created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            
            if ($endDate) {
                $sql .= " AND al.created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            
            if ($action) {
                $sql .= " AND al.action LIKE ?";
                $params[] = '%' . $action . '%';
            }
            
            $sql .= " ORDER BY al.created_at DESC";
            
            $auditLogs = $this->db->fetchAll($sql, $params);
            
            $data = [
                'audit_logs' => $auditLogs,
                'filters' => [
                    'user_id' => $userId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'action' => $action
                ],
                'generated_date' => date('Y-m-d H:i:s'),
                'generated_by' => $_SESSION['user']['full_name']
            ];
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            error_log("Generate audit trail error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating audit trail'];
        }
    }
    
    /**
     * Export to Excel (CSV format)
     */
    public function exportToCSV($data, $filename) {
        try {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($output, "\xEF\xBB\xBF");
            
            // Output CSV data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Export to CSV error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate PDF using HTML to PDF conversion
     */
    public function generatePDF($htmlContent, $filename) {
        try {
            // For InfinityFree, we'll use a simple HTML to PDF approach
            // In production, you might want to use a library like TCPDF or DOMPDF
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            // For now, we'll output as HTML with print-friendly CSS
            // In a real implementation, you would use a PDF library
            echo "<html>
            <head>
                <title>{$filename}</title>
                <style>
                    @media print {
                        body { font-family: Arial, sans-serif; size: portrait; }
                        .no-print { display: none; }
                        .page-break { page-break-before: always; }
                    }
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .footer { position: fixed; bottom: 0; text-align: center; font-size: 10px; }
                </style>
            </head>
            <body>
                {$htmlContent}
            </body>
            </html>";
            
            return true;
            
        } catch (Exception $e) {
            error_log("Generate PDF error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Print service record template
     */
    public function printServiceRecordTemplate($data) {
        $personnel = $data['personnel'];
        $serviceRecords = $data['service_records'];
        
        $html = "
        <div class='header'>
            <h2>Republic of the Philippines</h2>
            <h3>Bureau of Jail Management and Penology</h3>
            <h4>Service Record</h4>
        </div>
        
        <table>
            <tr>
                <td><strong>Name:</strong></td>
                <td>{$personnel['last_name']}, {$personnel['first_name']} {$personnel['middle_name']}</td>
            </tr>
            <tr>
                <td><strong>Employee Number:</strong></td>
                <td>{$personnel['employee_number']}</td>
            </tr>
            <tr>
                <td><strong>Position:</strong></td>
                <td>{$personnel['position']}</td>
            </tr>
            <tr>
                <td><strong>Department:</strong></td>
                <td>{$personnel['department']}</td>
            </tr>
        </table>
        
        <h3>Service History</h3>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Position</th>
                    <th>Rank</th>
                    <th>Station</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($serviceRecords as $record) {
            $period = formatDate($record['date_from']);
            if ($record['date_to']) {
                $period .= ' to ' . formatDate($record['date_to']);
            } else {
                $period .= ' to Present';
            }
            
            $html .= "
                <tr>
                    <td>{$period}</td>
                    <td>{$record['position']}</td>
                    <td>{$record['rank']}</td>
                    <td>{$record['station']}</td>
                    <td>{$record['status']}</td>
                    <td>{$record['remarks']}</td>
                </tr>";
        }
        
        $html .= "
            </tbody>
        </table>
        
        <div class='footer'>
            Generated on: {$data['generated_date']} by {$data['generated_by']}
        </div>";
        
        return $html;
    }
}
