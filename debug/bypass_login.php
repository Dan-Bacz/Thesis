<?php
/**
 * Bypass Login Test
 * Tests login without AuthController to isolate the issue
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "<h2>Bypass Login Test</h2>";

try {
    $db = new Database();
    
    echo "<h3>1. Direct Database Login Test</h3>";
    
    // Test direct database query
    $user = $db->fetch(
        "SELECT u.*, p.employee_number, p.first_name, p.last_name 
         FROM users u 
         LEFT JOIN personnel p ON u.id = p.user_id 
         WHERE u.username = ?",
        ['admin']
    );
    
    if ($user) {
        echo "<p style='color: green;'>User found in database</p>";
        echo "<p>Username: " . $user['username'] . "</p>";
        echo "<p>Status: " . $user['status'] . "</p>";
        echo "<p>Role: " . $user['role'] . "</p>";
        
        // Test password
        if (password_verify('Admin@123', $user['password_hash'])) {
            echo "<p style='color: green;'>Password verification: SUCCESS</p>";
            
            // Set session manually
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            $_SESSION['login_time'] = time();
            
            echo "<p style='color: green;'>Session set successfully</p>";
            echo "<p><strong>Direct login test: PASSED</strong></p>";
            
            echo "<h3>2. Test Dashboard Access</h3>";
            echo "<p>Session data:</p>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            
            echo "<p><a href='../views/dashboard/admin.php'>Test Admin Dashboard</a></p>";
            
        } else {
            echo "<p style='color: red;'>Password verification: FAILED</p>";
        }
    } else {
        echo "<p style='color: red;'>User not found in database</p>";
    }
    
    echo "<h3>3. Test AuthController Methods Individually</h3>";
    
    // Load AuthController
    require_once __DIR__ . '/../controllers/AuthController.php';
    
    try {
        $authController = new AuthController();
        echo "<p style='color: green;'>AuthController loaded successfully</p>";
        
        // Test if methods exist
        $methods = ['login', 'isAccountLocked', 'recordFailedAttempt', 'resetFailedAttempts'];
        foreach ($methods as $method) {
            if (method_exists($authController, $method)) {
                echo "<p style='color: green;'>Method $method: EXISTS</p>";
            } else {
                echo "<p style='color: red;'>Method $method: MISSING</p>";
            }
        }
        
        // Test isAccountLocked
        $reflection = new ReflectionClass($authController);
        $isAccountLocked = $reflection->getMethod('isAccountLocked');
        $isAccountLocked->setAccessible(true);
        
        $locked = $isAccountLocked->invoke($authController, 'admin');
        echo "<p>Account locked status: " . ($locked ? 'LOCKED' : 'NOT LOCKED') . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>AuthController error: " . $e->getMessage() . "</p>";
        echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    }
    
    echo "<h3>4. Test Required Functions</h3>";
    $requiredFunctions = ['logActivity', 'redirect', 'isLoggedIn', 'getCurrentUser'];
    foreach ($requiredFunctions as $func) {
        if (function_exists($func)) {
            echo "<p style='color: green;'>Function $func: EXISTS</p>";
        } else {
            echo "<p style='color: red;'>Function $func: MISSING</p>";
        }
    }
    
    echo "<h3>5. Test logActivity Function</h3>";
    try {
        if (function_exists('logActivity')) {
            logActivity(1, 'test_login', 'users', 1);
            echo "<p style='color: green;'>logActivity: SUCCESS</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>logActivity error: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #f8d7da;'>";
    echo "<strong>Error:</strong> " . $e->getMessage();
    echo "<br><strong>File:</strong> " . $e->getFile();
    echo "<br><strong>Line:</strong> " . $e->getLine();
    echo "</div>";
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 900px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h2, h3 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 5px;
    }
    pre {
        background: #f8f9fa;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow-x: auto;
    }
    a {
        color: #007bff;
        text-decoration: none;
        font-weight: bold;
    }
    a:hover {
        text-decoration: underline;
    }
</style>
