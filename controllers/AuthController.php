<?php
/**
 * Authentication Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * User login
     */
    public function login($username, $password) {
        try {
            // Check if account is locked due to too many attempts
            if ($this->isAccountLocked($username)) {
                return ['success' => false, 'message' => 'Account temporarily locked due to too many failed attempts. Please try again later.'];
            }
            
            // Get user from database (include pending accounts for proper error messaging)
            $user = $this->db->fetch(
                "SELECT u.*, p.employee_number, p.first_name, p.last_name 
                 FROM users u 
                 LEFT JOIN personnel p ON u.id = p.user_id 
                 WHERE u.username = ?",
                [$username]
            );
            
            if (!$user) {
                $this->recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // Check account status
            if ($user['status'] === 'pending') {
                return ['success' => false, 'message' => 'Your account is pending admin approval. Please contact your administrator.'];
            }
            
            if ($user['status'] === 'inactive') {
                return ['success' => false, 'message' => 'Your account has been deactivated. Please contact your administrator.'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // Reset failed attempts on successful login
            $this->resetFailedAttempts($username);
            
            // Update last login
            $this->db->execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            $_SESSION['login_time'] = time();
            
            // Log activity
            logActivity($user['id'], 'login');
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during login'];
        }
    }
    
    /**
     * User logout
     */
    public function logout() {
        if (isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            logActivity($userId, 'logout');
        }
        
        session_destroy();
        redirect('views/auth/login.php');
    }
    
    /**
     * Check if account is locked
     */
    private function isAccountLocked($username) {
        $attempts = $this->db->fetch(
            "SELECT COUNT(*) as attempt_count, MAX(attempt_time) as last_attempt 
             FROM login_attempts 
             WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$username, LOGIN_TIMEOUT]
        );
        
        return $attempts['attempt_count'] >= MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($username) {
        $this->db->execute(
            "INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())",
            [$username, $_SERVER['REMOTE_ADDR']]
        );
    }
    
    /**
     * Reset failed login attempts
     */
    private function resetFailedAttempts($username) {
        $this->db->execute(
            "DELETE FROM login_attempts WHERE username = ?",
            [$username]
        );
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $user = $this->db->fetch(
                "SELECT password_hash FROM users WHERE id = ?",
                [$userId]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
            }
            
            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            
            // Update password
            $this->db->execute(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [$newPasswordHash, $userId]
            );
            
            // Log activity
            logActivity($userId, 'password_change');
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while changing password'];
        }
    }
    
    /**
     * Register new personnel account
     */
    public function register($data) {
        try {
            $db = new Database();
            
            // Validate required fields
            $requiredFields = [
                'username', 'password', 'confirm_password', 'email',
                'first_name', 'last_name', 'birth_date', 'gender',
                'mobile_number', 'address', 'employee_number', 'position', 'rank',
                'station', 'department', 'date_appointed', 'education',
                'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number'
            ];
            
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => 'All required fields must be filled'];
                }
            }
            
            // Validate password match
            if ($data['password'] !== $data['confirm_password']) {
                return ['success' => false, 'message' => 'Password and confirm password do not match'];
            }
            
            // Validate password strength
            $passwordErrors = $this->validatePasswordStrength($data['password']);
            if (!empty($passwordErrors)) {
                return ['success' => false, 'message' => implode(', ', $passwordErrors)];
            }
            
            // Check if username already exists
            $existingUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$data['username']]);
            if ($existingUser) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            
            // Check if email already exists
            $existingEmail = $db->fetch("SELECT id FROM users WHERE email = ?", [$data['email']]);
            if ($existingEmail) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            // Check if employee number already exists
            $existingEmployee = $db->fetch("SELECT id FROM personnel WHERE employee_number = ?", [$data['employee_number']]);
            if ($existingEmployee) {
                return ['success' => false, 'message' => 'Employee number already exists'];
            }
            
            // Start transaction
            $db->beginTransaction();
            
            // Insert user record
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            $userData = [
                'username' => $data['username'],
                'password_hash' => $passwordHash,
                'email' => $data['email'],
                'full_name' => $data['first_name'] . ' ' . $data['last_name'],
                'role' => 'officer', // Default role for new registrations
                'status' => 'pending', // Pending admin approval
                'department' => $data['department'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $userId = $db->insert('users', $userData);
            
            // Insert personnel record
            $personnelData = [
                'user_id' => $userId,
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'mobile_number' => $data['mobile_number'],
                'telephone_number' => $data['telephone_number'] ?? null,
                'address' => $data['address'],
                'position' => $data['position'],
                'rank' => $data['rank'],
                'station' => $data['station'],
                'department' => $data['department'],
                'date_appointed' => $data['date_appointed'],
                'education' => $data['education'],
                'course' => $data['course'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_relationship' => $data['emergency_contact_relationship'],
                'emergency_contact_number' => $data['emergency_contact_number'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $personnelId = $db->insert('personnel', $personnelData);
            
            // Initialize leave credits for new employee
            $leaveTypes = ['Vacation', 'Sick', 'Maternity', 'Paternity', 'Emergency'];
            foreach ($leaveTypes as $leaveType) {
                $leaveCredits = [
                    'personnel_id' => $personnelId,
                    'leave_type' => $leaveType,
                    'total_credits' => $this->getDefaultLeaveCredits($leaveType),
                    'used_credits' => 0,
                    'year' => date('Y'),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $db->insert('leave_credits', $leaveCredits);
            }
            
            // Initialize clearance requirements
            $clearanceRequirements = $db->fetchAll("SELECT id, requirement_name FROM clearance_requirements");
            foreach ($clearanceRequirements as $requirement) {
                $clearanceData = [
                    'personnel_id' => $personnelId,
                    'requirement_id' => $requirement['id'],
                    'status' => 'pending',
                    'notes' => null,
                    'processed_by' => null,
                    'processed_date' => null,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $db->insert('employee_clearance', $clearanceData);
            }
            
            // Log activity
            logActivity($userId, 'user_registration', 'users', $userId);
            logActivity($userId, 'personnel_creation', 'personnel', $personnelId);
            
            // Commit transaction
            $db->commit();
            
            return ['success' => true, 'message' => 'Account created successfully! Your account is pending admin approval. You will be notified once approved.'];
            
        } catch (Exception $e) {
            // Rollback transaction
            if (isset($db)) {
                $db->rollback();
            }
            
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration. Please try again.'];
        }
    }
    
    /**
     * Get default leave credits based on leave type
     */
    private function getDefaultLeaveCredits($leaveType) {
        $credits = [
            'Vacation' => 15,
            'Sick' => 15,
            'Maternity' => 105,
            'Paternity' => 7,
            'Emergency' => 5
        ];
        
        return $credits[$leaveType] ?? 15;
    }
    
    /**
     * Validate password strength
     */
    private function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    /**
     * Get pending accounts for admin approval
     */
    public function getPendingAccounts() {
        try {
            $db = new Database();
            $pendingAccounts = $db->fetchAll(
                "SELECT u.id, u.username, u.email, u.full_name, u.created_at,
                        p.employee_number, p.first_name, p.last_name, p.position, p.rank, p.station
                 FROM users u 
                 LEFT JOIN personnel p ON u.id = p.user_id 
                 WHERE u.status = 'pending' 
                 ORDER BY u.created_at DESC"
            );
            
            return ['success' => true, 'data' => $pendingAccounts];
            
        } catch (Exception $e) {
            error_log("Get pending accounts error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve pending accounts'];
        }
    }
    
    /**
     * Approve pending account
     */
    public function approveAccount($userId, $role = 'officer') {
        try {
            $db = new Database();
            
            // Validate role
            $validRoles = ['admin', 'prm_officer', 'officer'];
            if (!in_array($role, $validRoles)) {
                return ['success' => false, 'message' => 'Invalid role specified'];
            }
            
            // Update user status and role
            $result = $db->execute(
                "UPDATE users SET status = 'active', role = ?, updated_at = NOW() WHERE id = ?",
                [$role, $userId]
            );
            
            if ($result->rowCount() > 0) {
                // Log activity
                logActivity($userId, 'account_approved', 'users', $userId);
                
                return ['success' => true, 'message' => 'Account approved successfully'];
            } else {
                return ['success' => false, 'message' => 'Account not found or already processed'];
            }
            
        } catch (Exception $e) {
            error_log("Approve account error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to approve account'];
        }
    }
    
    /**
     * Reject pending account
     */
    public function rejectAccount($userId, $reason = '') {
        try {
            $db = new Database();
            
            // Start transaction
            $db->beginTransaction();
            
            // Get user info before deletion
            $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if (!$user) {
                $db->rollback();
                return ['success' => false, 'message' => 'Account not found'];
            }
            
            // Delete related records
            $db->execute("DELETE FROM personnel WHERE user_id = ?", [$userId]);
            $db->execute("DELETE FROM leave_credits WHERE personnel_id IN (SELECT id FROM personnel WHERE user_id = ?)", [$userId]);
            $db->execute("DELETE FROM employee_clearance WHERE personnel_id IN (SELECT id FROM personnel WHERE user_id = ?)", [$userId]);
            
            // Delete user account
            $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
            
            // Log activity
            logActivity($userId, 'account_rejected', 'users', $userId, ['reason' => $reason]);
            
            // Commit transaction
            $db->commit();
            
            return ['success' => true, 'message' => 'Account rejected and removed successfully'];
            
        } catch (Exception $e) {
            // Rollback transaction
            if (isset($db)) {
                $db->rollback();
            }
            
            error_log("Reject account error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reject account'];
        }
    }
    
    /**
     * Get all users for admin management
     */
    public function getAllUsers() {
        try {
            $db = new Database();
            $users = $db->fetchAll(
                "SELECT u.id, u.username, u.email, u.full_name, u.role, u.status, u.created_at,
                        p.employee_number, p.first_name, p.last_name, p.position, p.rank, p.station,
                        CASE WHEN u.id IN (SELECT user_id FROM personnel WHERE assigned_prm_officer_id IS NOT NULL) 
                             THEN (SELECT CONCAT(p2.first_name, ' ', p2.last_name) 
                                   FROM personnel p2 WHERE p2.id = (SELECT assigned_prm_officer_id FROM personnel WHERE user_id = u.id))
                             ELSE NULL END as assigned_prm_officer
                 FROM users u 
                 LEFT JOIN personnel p ON u.id = p.user_id 
                 ORDER BY u.created_at DESC"
            );
            
            return ['success' => true, 'data' => $users];
            
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve users'];
        }
    }
    
    /**
     * Assign PRM officer to personnel
     */
    public function assignPRMOfficer($personnelId, $prmOfficerId) {
        try {
            $db = new Database();
            
            // Validate that PRM officer exists and has correct role
            $prmOfficer = $db->fetch(
                "SELECT u.id, p.first_name, p.last_name 
                 FROM users u 
                 JOIN personnel p ON u.id = p.user_id 
                 WHERE u.id = ? AND u.role = 'prm_officer' AND u.status = 'active'",
                [$prmOfficerId]
            );
            
            if (!$prmOfficer) {
                return ['success' => false, 'message' => 'Invalid PRM officer selected'];
            }
            
            // Update personnel record
            $result = $db->execute(
                "UPDATE personnel SET assigned_prm_officer_id = ?, updated_at = NOW() WHERE id = ?",
                [$prmOfficerId, $personnelId]
            );
            
            if ($result->rowCount() > 0) {
                // Log activity
                logActivity($prmOfficerId, 'prm_officer_assigned', 'personnel', $personnelId);
                
                return ['success' => true, 'message' => 'PRM officer assigned successfully'];
            } else {
                return ['success' => false, 'message' => 'Personnel not found'];
            }
            
        } catch (Exception $e) {
            error_log("Assign PRM officer error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to assign PRM officer'];
        }
    }
    
    /**
     * Get PRM officers for assignment dropdown
     */
    public function getPRMOfficers() {
        try {
            $db = new Database();
            $prmOfficers = $db->fetchAll(
                "SELECT u.id, p.first_name, p.last_name, p.employee_number, p.position
                 FROM users u 
                 JOIN personnel p ON u.id = p.user_id 
                 WHERE u.role = 'prm_officer' AND u.status = 'active'
                 ORDER BY p.last_name, p.first_name"
            );
            
            return ['success' => true, 'data' => $prmOfficers];
            
        } catch (Exception $e) {
            error_log("Get PRM officers error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve PRM officers'];
        }
    }
    
    /**
     * Get personnel assigned to PRM officer
     */
    public function getAssignedPersonnel($prmOfficerId) {
        try {
            $db = new Database();
            $personnel = $db->fetchAll(
                "SELECT p.*, u.username, u.email, u.status
                 FROM personnel p 
                 JOIN users u ON p.user_id = u.id 
                 WHERE p.assigned_prm_officer_id = ? 
                 ORDER BY p.last_name, p.first_name",
                [$prmOfficerId]
            );
            
            return ['success' => true, 'data' => $personnel];
            
        } catch (Exception $e) {
            error_log("Get assigned personnel error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve assigned personnel'];
        }
    }
    
    /**
     * Reset password (for admin use)
     */
    public function resetPassword($userId, $newPassword) {
        try {
            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            
            // Update password
            $this->db->execute(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [$passwordHash, $userId]
            );
            
            // Log activity
            logActivity($_SESSION['user_id'], 'password_reset', 'users', $userId);
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while resetting password'];
        }
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if (isLoggedIn()) {
            $loginTime = $_SESSION['login_time'] ?? 0;
            
            if (time() - $loginTime > SESSION_TIMEOUT) {
                $this->logout();
            }
            
            // Update login time
            $_SESSION['login_time'] = time();
        }
    }
    
    /**
     * Validate session
     */
    public function validateSession() {
        if (!isLoggedIn()) {
            redirect('views/auth/login.php');
        }
        
        $this->checkSessionTimeout();
        
        // Check if user is still active
        $user = getCurrentUser();
        if ($user['status'] !== 'active') {
            $this->logout();
        }
    }
}
