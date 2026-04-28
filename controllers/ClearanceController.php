<?php
/**
 * Clearance Management Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class ClearanceController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get clearance requirements
     */
    public function getClearanceRequirements() {
        try {
            $sql = "SELECT * FROM clearance_requirements ORDER BY department, name";
            return $this->db->fetchAll($sql);
            
        } catch (Exception $e) {
            error_log("Get clearance requirements error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get employee clearance status
     */
    public function getEmployeeClearance($personnelId) {
        try {
            $sql = "SELECT ec.*, cr.name, cr.description, cr.department, u.full_name as cleared_by_name
                    FROM employee_clearance ec
                    LEFT JOIN clearance_requirements cr ON ec.clearance_requirement_id = cr.id
                    LEFT JOIN users u ON ec.cleared_by = u.id
                    WHERE ec.personnel_id = ?
                    ORDER BY cr.department, cr.name";
            
            return $this->db->fetchAll($sql, [$personnelId]);
            
        } catch (Exception $e) {
            error_log("Get employee clearance error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Initialize clearance for employee
     */
    public function initializeClearance($personnelId) {
        try {
            $this->db->beginTransaction();
            
            // Check if clearance already initialized
            $existing = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employee_clearance WHERE personnel_id = ?",
                [$personnelId]
            );
            
            if ($existing['count'] > 0) {
                $this->db->rollback();
                return ['success' => true, 'message' => 'Clearance already initialized'];
            }
            
            // Get all clearance requirements
            $requirements = $this->getClearanceRequirements();
            
            foreach ($requirements as $requirement) {
                $clearanceData = [
                    'personnel_id' => $personnelId,
                    'clearance_requirement_id' => $requirement['id'],
                    'status' => 'pending'
                ];
                
                $this->db->insert('employee_clearance', $clearanceData);
            }
            
            // Log activity
            logActivity($_SESSION['user_id'], 'clearance_initialization', 'employee_clearance', null, 
                       null, ['personnel_id' => $personnelId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Clearance initialized successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Initialize clearance error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while initializing clearance'];
        }
    }
    
    /**
     * Process clearance (clear/reject/exempt)
     */
    public function processClearance($clearanceId, $status, $remarks = null) {
        try {
            $this->db->beginTransaction();
            
            // Get current clearance
            $current = $this->db->fetch(
                "SELECT ec.*, p.first_name, p.last_name 
                 FROM employee_clearance ec
                 LEFT JOIN personnel p ON ec.personnel_id = p.id
                 WHERE ec.id = ?",
                [$clearanceId]
            );
            
            if (!$current) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Clearance record not found'];
            }
            
            // Update clearance status
            $updateData = [
                'status' => $status,
                'cleared_by' => $_SESSION['user_id'],
                'cleared_date' => date('Y-m-d H:i:s'),
                'remarks' => sanitize($remarks)
            ];
            
            $this->db->update('employee_clearance', $updateData, 'id = ?', [$clearanceId]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'clearance_processing', 'employee_clearance', $clearanceId,
                       ['status' => $current['status']], ['status' => $status]);
            
            $this->db->commit();
            
            $statusMessage = [
                'cleared' => 'Clearance approved',
                'not_cleared' => 'Clearance rejected',
                'exempted' => 'Clearance exempted'
            ];
            
            return ['success' => true, 'message' => $statusMessage[$status] ?? 'Clearance updated'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Process clearance error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing clearance'];
        }
    }
    
    /**
     * Get clearance summary
     */
    public function getClearanceSummary($personnelId) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_requirements,
                        SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) as cleared_count,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN status = 'not_cleared' THEN 1 ELSE 0 END) as not_cleared_count,
                        SUM(CASE WHEN status = 'exempted' THEN 1 ELSE 0 END) as exempted_count
                    FROM employee_clearance 
                    WHERE personnel_id = ?";
            
            $summary = $this->db->fetch($sql, [$personnelId]);
            
            // Calculate completion percentage
            $completionPercentage = 0;
            if ($summary['total_requirements'] > 0) {
                $completionPercentage = (($summary['cleared_count'] + $summary['exempted_count']) / $summary['total_requirements']) * 100;
            }
            
            $summary['completion_percentage'] = round($completionPercentage, 2);
            
            return $summary;
            
        } catch (Exception $e) {
            error_log("Get clearance summary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all pending clearances
     */
    public function getPendingClearances($department = null) {
        try {
            $sql = "SELECT ec.*, cr.name as requirement_name, cr.department,
                           p.first_name, p.last_name, p.employee_number
                    FROM employee_clearance ec
                    LEFT JOIN clearance_requirements cr ON ec.clearance_requirement_id = cr.id
                    LEFT JOIN personnel p ON ec.personnel_id = p.id
                    WHERE ec.status = 'pending'";
            
            $params = [];
            
            if ($department) {
                $sql .= " AND cr.department = ?";
                $params[] = $department;
            }
            
            $sql .= " ORDER BY cr.department, p.last_name, p.first_name";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Get pending clearances error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get clearance statistics
     */
    public function getClearanceStatistics() {
        try {
            $sql = "SELECT 
                        cr.department,
                        COUNT(*) as total_requirements,
                        SUM(CASE WHEN ec.status = 'cleared' THEN 1 ELSE 0 END) as cleared_count,
                        SUM(CASE WHEN ec.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN ec.status = 'not_cleared' THEN 1 ELSE 0 END) as not_cleared_count,
                        SUM(CASE WHEN ec.status = 'exempted' THEN 1 ELSE 0 END) as exempted_count
                    FROM clearance_requirements cr
                    LEFT JOIN employee_clearance ec ON cr.id = ec.clearance_requirement_id
                    GROUP BY cr.department
                    ORDER BY cr.department";
            
            return $this->db->fetchAll($sql);
            
        } catch (Exception $e) {
            error_log("Get clearance statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate clearance report
     */
    public function generateClearanceReport($personnelId = null, $department = null) {
        try {
            $sql = "SELECT p.first_name, p.last_name, p.employee_number,
                           cr.department, cr.name as requirement_name, ec.status,
                           ec.cleared_date, ec.remarks, u.full_name as cleared_by_name
                    FROM personnel p
                    LEFT JOIN employee_clearance ec ON p.id = ec.personnel_id
                    LEFT JOIN clearance_requirements cr ON ec.clearance_requirement_id = cr.id
                    LEFT JOIN users u ON ec.cleared_by = u.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($personnelId) {
                $sql .= " AND p.id = ?";
                $params[] = $personnelId;
            }
            
            if ($department) {
                $sql .= " AND cr.department = ?";
                $params[] = $department;
            }
            
            $sql .= " ORDER BY p.last_name, p.first_name, cr.department, cr.name";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Generate clearance report error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if employee is fully cleared
     */
    public function isFullyCleared($personnelId) {
        try {
            $sql = "SELECT COUNT(*) as pending_count
                    FROM employee_clearance ec
                    LEFT JOIN clearance_requirements cr ON ec.clearance_requirement_id = cr.id
                    WHERE ec.personnel_id = ? 
                    AND ec.status = 'pending' 
                    AND cr.is_required = 1";
            
            $result = $this->db->fetch($sql, [$personnelId]);
            
            return $result['pending_count'] == 0;
            
        } catch (Exception $e) {
            error_log("Check fully cleared error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update clearance requirement
     */
    public function updateClearanceRequirement($requirementId, $name, $description, $department, $isRequired) {
        try {
            $updateData = [
                'name' => sanitize($name),
                'description' => sanitize($description),
                'department' => sanitize($department),
                'is_required' => $isRequired ? 1 : 0
            ];
            
            $this->db->update('clearance_requirements', $updateData, 'id = ?', [$requirementId]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'clearance_requirement_update', 'clearance_requirements', $requirementId);
            
            return ['success' => true, 'message' => 'Clearance requirement updated successfully'];
            
        } catch (Exception $e) {
            error_log("Update clearance requirement error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating clearance requirement'];
        }
    }
    
    /**
     * Add new clearance requirement
     */
    public function addClearanceRequirement($name, $description, $department, $isRequired) {
        try {
            $requirementData = [
                'name' => sanitize($name),
                'description' => sanitize($description),
                'department' => sanitize($department),
                'is_required' => $isRequired ? 1 : 0
            ];
            
            $requirementId = $this->db->insert('clearance_requirements', $requirementData);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'clearance_requirement_add', 'clearance_requirements', $requirementId);
            
            return ['success' => true, 'message' => 'Clearance requirement added successfully', 'requirement_id' => $requirementId];
            
        } catch (Exception $e) {
            error_log("Add clearance requirement error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while adding clearance requirement'];
        }
    }
    
    /**
     * Delete clearance requirement
     */
    public function deleteClearanceRequirement($requirementId) {
        try {
            // Check if requirement is in use
            $inUse = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employee_clearance WHERE clearance_requirement_id = ?",
                [$requirementId]
            );
            
            if ($inUse['count'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete requirement that is already in use'];
            }
            
            $this->db->delete('clearance_requirements', 'id = ?', [$requirementId]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'clearance_requirement_delete', 'clearance_requirements', $requirementId);
            
            return ['success' => true, 'message' => 'Clearance requirement deleted successfully'];
            
        } catch (Exception $e) {
            error_log("Delete clearance requirement error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting clearance requirement'];
        }
    }
}
