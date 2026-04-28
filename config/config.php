<?php
/**
 * Application Configuration for BJMP Personnel Management System
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 when using HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Application constants
define('APP_NAME', 'BJMP Personnel Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://bjmpmahayag.ct.ws'); // Update with your InfinityFree subdomain
define('APP_PATH', __DIR__ . '/..');

// Security constants
define('HASH_COST', 12);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', APP_PATH . '/uploads/');
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Email settings (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@bjmp.gov.ph');
define('FROM_NAME', 'BJMP Personnel System');

// Pagination
define('ITEMS_PER_PAGE', 20);

// User roles
define('ROLES', [
    'admin' => 'Administrator',
    'hr' => 'HR Personnel',
    'supervisor' => 'Supervisor',
    'employee' => 'Employee'
]);

// Leave types
define('LEAVE_TYPES', [
    'Vacation' => 'Vacation Leave',
    'Sick' => 'Sick Leave',
    'Maternity' => 'Maternity Leave',
    'Paternity' => 'Paternity Leave',
    'Emergency' => 'Emergency Leave',
    'Special Privilege' => 'Special Privilege Leave',
    'Study Leave' => 'Study Leave'
]);

// Document categories
define('DOCUMENT_CATEGORIES', [
    'Basic Information' => 'Basic Information',
    'Clearance' => 'Clearance Documents',
    'Health' => 'Health Documents',
    'Education' => 'Education Documents',
    'Professional' => 'Professional Documents',
    'Training' => 'Training Documents'
]);

// Custom error handler
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}");
    
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>
                <strong>Error:</strong> {$errstr}<br>
                <small>File: {$errfile} Line: {$errline}</small>
              </div>";
    }
}

// Custom exception handler
function handleException($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>
                <strong>Fatal Error:</strong> {$exception->getMessage()}<br>
                <small>File: {$exception->getFile()} Line: {$exception->getLine()}</small>
              </div>";
    }
}

set_error_handler('handleError');
set_exception_handler('handleException');

// Helper functions
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function formatPhoneNumber($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

function calculateAge($birthDate) {
    $today = new DateTime();
    $birth = new DateTime($birthDate);
    return $today->diff($birth)->y;
}

function logActivity($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $db = new Database();
        
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        $db->insert('audit_logs', $data);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['user'];
    }
    return null;
}

// Check user role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Check if user has permission
function hasPermission($permission) {
    $user = getCurrentUser();
    
    if (!$user) return false;
    
    // Admin has all permissions
    if ($user['role'] === 'admin') return true;
    
    // Define role permissions
    $permissions = [
        'admin' => ['view_all_personnel', 'manage_documents', 'approve_leave', 'manage_clearance', 'approve_accounts', 'assign_prm_officer', 'system_settings', 'manage_users', 'view_audit_logs'],
        'prm_officer' => ['view_assigned_personnel', 'manage_assigned_documents', 'approve_assigned_leave', 'manage_assigned_clearance', 'view_reports'],
        'officer' => ['view_own_profile', 'manage_own_documents', 'apply_leave', 'view_own_clearance']
    ];
    
    return in_array($permission, $permissions[$user['role']] ?? []);
}

// Redirect function
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    $message = $_SESSION['flash'][$type] ?? '';
    unset($_SESSION['flash'][$type]);
    return $message;
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
