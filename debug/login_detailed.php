<?php
/**
 * Detailed Login Debug Script
 * Identifies specific errors in login process
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

echo "<h2>Detailed Login Debug</h2>";

try {
    $db = new Database();
    
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<h3>1. Check Admin User Details</h3>";
    $adminUser = $db->fetch("SELECT * FROM users WHERE username = 'admin'");
    if ($adminUser) {
        echo "<p style='color: green;'>Admin user found</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><td>" . $adminUser['id'] . "</td></tr>";
        echo "<tr><th>Username</th><td>" . $adminUser['username'] . "</td></tr>";
        echo "<tr><th>Email</th><td>" . $adminUser['email'] . "</td></tr>";
        echo "<tr><th>Role</th><td>" . $adminUser['role'] . "</td></tr>";
        echo "<tr><th>Status</th><td>" . $adminUser['status'] . "</td></tr>";
        echo "<tr><th>Password Hash</th><td>" . substr($adminUser['password_hash'], 0, 20) . "...</td></tr>";
        echo "</table>";
    } else {
        echo "<p style='color: red;'>Admin user not found</p>";
        exit;
    }
    
    echo "<h3>2. Test Password Verification</h3>";
    $password = 'Admin@123';
    if (password_verify($password, $adminUser['password_hash'])) {
        echo "<p style='color: green;'>Password verification: SUCCESS</p>";
    } else {
        echo "<p style='color: red;'>Password verification: FAILED</p>";
        echo "<p>Testing password: '$password'</p>";
        echo "<p>Expected hash info:</p>";
        echo "<pre>";
        print_r(password_get_info($adminUser['password_hash']));
        echo "</pre>";
    }
    
    echo "<h3>3. Test Manual Login Query</h3>";
    try {
        $manualUser = $db->fetch(
            "SELECT u.*, p.employee_number, p.first_name, p.last_name 
             FROM users u 
             LEFT JOIN personnel p ON u.id = p.user_id 
             WHERE u.username = ?",
            ['admin']
        );
        
        if ($manualUser) {
            echo "<p style='color: green;'>Manual query: SUCCESS</p>";
            echo "<p>User found: " . $manualUser['username'] . "</p>";
            echo "<p>Status: " . $manualUser['status'] . "</p>";
        } else {
            echo "<p style='color: red;'>Manual query: FAILED - User not found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Manual query error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>4. Test AuthController Step by Step</h3>";
    
    try {
        $authController = new AuthController();
        echo "<p style='color: green;'>AuthController created successfully</p>";
        
        // Test isAccountLocked method
        echo "<p>Testing isAccountLocked...</p>";
        $reflection = new ReflectionClass($authController);
        $method = $reflection->getMethod('isAccountLocked');
        $method->setAccessible(true);
        
        $isLocked = $method->invoke($authController, 'admin');
        echo "<p>Account locked: " . ($isLocked ? 'YES' : 'NO') . "</p>";
        
        // Test login with detailed error catching
        echo "<p>Testing login with detailed error catching...</p>";
        
        // Temporarily modify the login method to show errors
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $result = $authController->login('admin', 'Admin@123');
        
        echo "<p>Login result:</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        if (!$result['success']) {
            echo "<p style='color: red;'>Login failed: " . $result['message'] . "</p>";
            
            // Check error logs
            echo "<h4>Recent Error Logs:</h4>";
            $errorLog = file_exists(ini_get('error_log')) ? file_get_contents(ini_get('error_log')) : 'No error log file found';
            echo "<pre style='background: #f8f9fa; padding: 10px; max-height: 200px; overflow-y: auto;'>";
            echo htmlspecialchars(substr($errorLog, -2000)); // Show last 2000 characters
            echo "</pre>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>AuthController error: " . $e->getMessage() . "</p>";
        echo "<p>File: " . $e->getFile() . "</p>";
        echo "<p>Line: " . $e->getLine() . "</p>";
        echo "<p>Trace:</p>";
        echo "<pre>";
        echo $e->getTraceAsString();
        echo "</pre>";
    }
    
    echo "<h3>5. Check Required Functions</h3>";
    $requiredFunctions = ['isLoggedIn', 'getCurrentUser', 'logActivity', 'redirect'];
    foreach ($requiredFunctions as $func) {
        if (function_exists($func)) {
            echo "<p style='color: green;'>Function $func: EXISTS</p>";
        } else {
            echo "<p style='color: red;'>Function $func: MISSING</p>";
        }
    }
    
    echo "<h3>6. Test Session</h3>";
    echo "<p>Session status: " . session_status() . "</p>";
    echo "<p>Session ID: " . session_id() . "</p>";
    
    if (!isset($_SESSION)) {
        echo "<p style='color: orange;'>Session not started, starting now...</p>";
        session_start();
    }
    
    echo "<h3>7. Direct Login Test (Bypassing AuthController)</h3>";
    try {
        // Simulate the exact login process
        $user = $db->fetch(
            "SELECT u.*, p.employee_number, p.first_name, p.last_name 
             FROM users u 
             LEFT JOIN personnel p ON u.id = p.user_id 
             WHERE u.username = ?",
            ['admin']
        );
        
        if ($user && password_verify('Admin@123', $user['password_hash'])) {
            echo "<p style='color: green;'>Direct login test: SUCCESS</p>";
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            $_SESSION['login_time'] = time();
            
            echo "<p>Session variables set successfully</p>";
            echo "<p>Ready to redirect to dashboard</p>";
            
            echo "<h3>8. Test Redirect</h3>";
            echo "<p>Would redirect to: /views/dashboard/admin.php</p>";
            echo "<a href='../views/dashboard/admin.php'>Test Admin Dashboard</a>";
            
        } else {
            echo "<p style='color: red;'>Direct login test: FAILED</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Direct login error: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #f8d7da;'>";
    echo "<strong>Fatal Error:</strong> " . $e->getMessage();
    echo "<br><strong>File:</strong> " . $e->getFile();
    echo "<br><strong>Line:</strong> " . $e->getLine();
    echo "<br><strong>Trace:</strong>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 1000px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h2, h3, h4 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 5px;
    }
    table {
        background: white;
        margin: 10px 0;
        border-collapse: collapse;
    }
    th {
        background: #007bff;
        color: white;
        padding: 8px;
        text-align: left;
    }
    td {
        padding: 8px;
        border: 1px solid #ddd;
    }
    pre {
        background: #f8f9fa;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow-x: auto;
        font-size: 12px;
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
