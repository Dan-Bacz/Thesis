<?php
/**
 * InfinityFree Deployment Configuration
 * BJMP Personnel Management System
 */

// Error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Database configuration for InfinityFree
define('DB_HOST', 'sql311.infinityfree.com'); // Update with your InfinityFree SQL host
define('DB_NAME', 'if0_12345678_bjmp_personnel'); // Update with your database name
define('DB_USER', 'if0_12345678_bjmp_user'); // Update with your database username
define('DB_PASS', 'YourSecurePassword123'); // Update with your database password

// Application configuration
define('APP_NAME', 'BJMP Personnel Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://your-subdomain.infinityfreeapp.com'); // Update with your InfinityFree subdomain
define('APP_PATH', __DIR__);

// Security settings
define('HASH_COST', 12);
define('SESSION_TIMEOUT', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900);

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB (InfinityFree limit)
define('UPLOAD_PATH', APP_PATH . '/uploads/');
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Email settings (using InfinityFree's built-in mail)
define('FROM_EMAIL', 'noreply@your-subdomain.infinityfreeapp.com');
define('FROM_NAME', 'BJMP Personnel System');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Timezone
date_default_timezone_set('Asia/Manila');

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Custom error handler for production
function productionErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    
    // Log error but don't display it
    error_log("[$errorType] $errstr in $errfile on line $errline");
    
    // Show user-friendly error page for critical errors
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        include 'views/errors/500.php';
        exit();
    }
    
    return true;
}

// Set error handler
set_error_handler('productionErrorHandler');

// Custom exception handler
function productionExceptionHandler($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    http_response_code(500);
    include 'views/errors/500.php';
    exit();
}

set_exception_handler('productionExceptionHandler');

// Database connection class for InfinityFree
class InfinityFreeDatabase {
    private $host = DB_HOST;
    private $dbname = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = 'utf8mb4';
    public $pdo;
    
    public function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT        => true // Connection pooling for InfinityFree
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Log error but don't expose details
            error_log("Database connection failed: " . $e->getMessage());
            
            // Show user-friendly error
            http_response_code(503);
            include 'views/errors/503.php';
            exit();
        }
    }
    
    // Reuse the same methods from the original Database class
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->execute($sql, $values);
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "{$column} = ?";
            $values[] = $value;
        }
        
        $setClause = implode(', ', $setClause);
        $values = array_merge($values, $whereParams);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $this->execute($sql, $values);
        
        return $this->pdo->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->execute($sql, $params);
        
        return $this->pdo->rowCount();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

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
        $db = new InfinityFreeDatabase();
        
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
        'hr' => ['view_all_personnel', 'manage_documents', 'approve_leave', 'manage_clearance'],
        'supervisor' => ['view_team_personnel', 'approve_team_leave', 'view_team_documents'],
        'employee' => ['view_own_profile', 'manage_own_documents', 'apply_leave', 'view_own_clearance']
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

// Constants for the application
define('ROLES', [
    'admin' => 'Administrator',
    'hr' => 'HR Personnel',
    'supervisor' => 'Supervisor',
    'employee' => 'Employee'
]);

define('LEAVE_TYPES', [
    'Vacation' => 'Vacation Leave',
    'Sick' => 'Sick Leave',
    'Maternity' => 'Maternity Leave',
    'Paternity' => 'Paternity Leave',
    'Emergency' => 'Emergency Leave',
    'Special Privilege' => 'Special Privilege Leave',
    'Study Leave' => 'Study Leave'
]);

define('DOCUMENT_CATEGORIES', [
    'Basic Information' => 'Basic Information',
    'Clearance' => 'Clearance Documents',
    'Health' => 'Health Documents',
    'Education' => 'Education Documents',
    'Professional' => 'Professional Documents',
    'Training' => 'Training Documents'
]);

// Auto-load classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/controllers/',
        __DIR__ . '/middleware/',
        __DIR__ . '/models/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load security middleware
require_once __DIR__ . '/middleware/SecurityMiddleware.php';
?>
