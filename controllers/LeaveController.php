<?php
/**
 * Leave Management Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class LeaveController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Apply for leave
     */
    public function applyLeave($personnelId, $leaveType, $startDate, $endDate, $reason) {
        try {
            $this->db->beginTransaction();
            
            // Validate dates
            if (strtotime($startDate) < strtotime(date('Y-m-d'))) {
                return ['success' => false, 'message' => 'Start date cannot be in the past'];
            }
            
            if (strtotime($endDate) < strtotime($startDate)) {
                return ['success' => false, 'message' => 'End date must be after start date'];
            }
            
            // Calculate total days
            $totalDays = $this->calculateLeaveDays($startDate, $endDate);
            
            // Check leave credits
            $credits = $this->getLeaveCredits($personnelId, $leaveType);
            if ($credits['remaining_credits'] < $totalDays) {
                return ['success' => false, 'message' => 'Insufficient leave credits. Available: ' . $credits['remaining_credits'] . ' days'];
            }
            
            // Check for overlapping leave applications
            $overlap = $this->checkLeaveOverlap($personnelId, $startDate, $endDate);
            if ($overlap) {
                return ['success' => false, 'message' => 'Leave dates overlap with existing application'];
            }
            
            // Create leave application
            $leaveData = [
                'personnel_id' => $personnelId,
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'reason' => sanitize($reason),
                'status' => 'pending'
            ];
            
            $leaveId = $this->db->insert('leave_applications', $leaveData);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'leave_application', 'leave_applications', $leaveId);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Leave application submitted successfully', 'leave_id' => $leaveId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Leave application error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while submitting leave application'];
        }
    }
    
    /**
     * Get leave applications
     */
    public function getLeaveApplications($personnelId = null, $status = null, $page = 1) {
        try {
            $offset = ($page - 1) * ITEMS_PER_PAGE;
            
            $sql = "SELECT la.*, p.first_name, p.last_name, p.employee_number,
                           u.full_name as approved_by_name
                    FROM leave_applications la
                    LEFT JOIN personnel p ON la.personnel_id = p.id
                    LEFT JOIN users u ON la.approved_by = u.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($personnelId) {
                $sql .= " AND la.personnel_id = ?";
                $params[] = $personnelId;
            }
            
            if ($status) {
                $sql .= " AND la.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY la.created_at DESC LIMIT ? OFFSET ?";
            $params[] = ITEMS_PER_PAGE;
            $params[] = $offset;
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Get leave applications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Approve/reject leave application
     */
    public function processLeaveApplication($leaveId, $status, $rejectionReason = null) {
        try {
            $this->db->beginTransaction();
            
            // Get leave application
            $leave = $this->db->fetch(
                "SELECT * FROM leave_applications WHERE id = ?",
                [$leaveId]
            );
            
            if (!$leave) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Leave application not found'];
            }
            
            if ($leave['status'] !== 'pending') {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Leave application has already been processed'];
            }
            
            // Update leave application
            $updateData = [
                'status' => $status,
                'approved_by' => $_SESSION['user_id'],
                'approved_date' => date('Y-m-d H:i:s')
            ];
            
            if ($status === 'rejected' && $rejectionReason) {
                $updateData['rejection_reason'] = sanitize($rejectionReason);
            }
            
            $this->db->update('leave_applications', $updateData, 'id = ?', [$leaveId]);
            
            // If approved, update leave credits
            if ($status === 'approved') {
                $this->updateLeaveCredits($leave['personnel_id'], $leave['leave_type'], $leave['total_days']);
            }
            
            // Log activity
            logActivity($_SESSION['user_id'], 'leave_processing', 'leave_applications', $leaveId,
                       ['status' => $leave['status']], ['status' => $status]);
            
            $this->db->commit();
            
            $message = $status === 'approved' ? 'Leave application approved' : 'Leave application rejected';
            return ['success' => true, 'message' => $message];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Leave processing error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing leave application'];
        }
    }
    
    /**
     * Cancel leave application
     */
    public function cancelLeaveApplication($leaveId) {
        try {
            $this->db->beginTransaction();
            
            // Get leave application
            $leave = $this->db->fetch(
                "SELECT * FROM leave_applications WHERE id = ?",
                [$leaveId]
            );
            
            if (!$leave) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Leave application not found'];
            }
            
            if ($leave['status'] !== 'pending') {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Cannot cancel processed leave application'];
            }
            
            // Update status
            $this->db->update('leave_applications', ['status' => 'cancelled'], 'id = ?', [$leaveId]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'leave_cancellation', 'leave_applications', $leaveId);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Leave application cancelled successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Leave cancellation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while cancelling leave application'];
        }
    }
    
    /**
     * Get leave credits
     */
    public function getLeaveCredits($personnelId, $leaveType = null) {
        try {
            $sql = "SELECT * FROM leave_credits WHERE personnel_id = ? AND year = ?";
            $params = [$personnelId, date('Y')];
            
            if ($leaveType) {
                $sql .= " AND leave_type = ?";
                $params[] = $leaveType;
            }
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Get leave credits error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Initialize leave credits for the year
     */
    public function initializeLeaveCredits($personnelId) {
        try {
            $year = date('Y');
            
            // Check if credits already exist
            $existing = $this->db->fetch(
                "SELECT COUNT(*) as count FROM leave_credits WHERE personnel_id = ? AND year = ?",
                [$personnelId, $year]
            );
            
            if ($existing['count'] > 0) {
                return ['success' => true, 'message' => 'Leave credits already initialized'];
            }
            
            // Default leave credits based on type
            $defaultCredits = [
                'Vacation' => 15,
                'Sick' => 15,
                'Maternity' => 105,
                'Paternity' => 7,
                'Emergency' => 3,
                'Special Privilege' => 3,
                'Study Leave' => 6
            ];
            
            foreach ($defaultCredits as $leaveType => $credits) {
                $creditData = [
                    'personnel_id' => $personnelId,
                    'leave_type' => $leaveType,
                    'total_credits' => $credits,
                    'year' => $year
                ];
                
                $this->db->insert('leave_credits', $creditData);
            }
            
            return ['success' => true, 'message' => 'Leave credits initialized successfully'];
            
        } catch (Exception $e) {
            error_log("Initialize leave credits error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while initializing leave credits'];
        }
    }
    
    /**
     * Update leave credits
     */
    private function updateLeaveCredits($personnelId, $leaveType, $daysUsed) {
        $currentCredits = $this->db->fetch(
            "SELECT used_credits FROM leave_credits WHERE personnel_id = ? AND leave_type = ? AND year = ?",
            [$personnelId, $leaveType, date('Y')]
        );
        
        if ($currentCredits) {
            $newUsedCredits = $currentCredits['used_credits'] + $daysUsed;
            
            $this->db->execute(
                "UPDATE leave_credits SET used_credits = ? WHERE personnel_id = ? AND leave_type = ? AND year = ?",
                [$newUsedCredits, $personnelId, $leaveType, date('Y')]
            );
        }
    }
    
    /**
     * Calculate leave days (excluding weekends)
     */
    private function calculateLeaveDays($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        $days = 0;
        foreach ($period as $day) {
            if ($day->format('N') < 6) { // Monday to Friday
                $days++;
            }
        }
        
        return $days;
    }
    
    /**
     * Check for overlapping leave applications
     */
    private function checkLeaveOverlap($personnelId, $startDate, $endDate) {
        $overlap = $this->db->fetch(
            "SELECT COUNT(*) as count FROM leave_applications 
             WHERE personnel_id = ? AND status IN ('pending', 'approved')
             AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))",
            [$personnelId, $startDate, $startDate, $endDate, $endDate]
        );
        
        return $overlap['count'] > 0;
    }
    
    /**
     * Get leave statistics
     */
    public function getLeaveStatistics($personnelId = null) {
        try {
            $sql = "SELECT leave_type, status, COUNT(*) as count, SUM(total_days) as total_days
                    FROM leave_applications";
            
            $params = [];
            
            if ($personnelId) {
                $sql .= " WHERE personnel_id = ?";
                $params[] = $personnelId;
            }
            
            $sql .= " GROUP BY leave_type, status ORDER BY leave_type, status";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Get leave statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending leave applications for approval
     */
    public function getPendingLeaveApplications($supervisorId = null) {
        try {
            $sql = "SELECT la.*, p.first_name, p.last_name, p.employee_number
                    FROM leave_applications la
                    LEFT JOIN personnel p ON la.personnel_id = p.id
                    WHERE la.status = 'pending'
                    ORDER BY la.start_date ASC";
            
            return $this->db->fetchAll($sql);
            
        } catch (Exception $e) {
            error_log("Get pending leave applications error: " . $e->getMessage());
            return [];
        }
    }
}
