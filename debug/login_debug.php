<?php
/**
 * Login Debug Script
 * Helps identify login issues
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

echo "<h2>Login Debug Information</h2>";

try {
    // Test database connection
    echo "<h3>1. Database Connection Test</h3>";
    $db = new Database();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Check if users table exists
    echo "<h3>2. Tables Check</h3>";
    $tables = $db->fetchAll("SHOW TABLES");
    $tableNames = array_column($tables, 'Tables_in_if0_41724093_bjmp_personnel');
    
    echo "<p>Found tables: " . implode(', ', $tableNames) . "</p>";
    
    if (in_array('users', $tableNames)) {
        echo "<p style='color: green;'>✅ Users table exists</p>";
        
        // Check if admin user exists
        $adminUser = $db->fetch("SELECT * FROM users WHERE username = 'admin'");
        if ($adminUser) {
            echo "<p style='color: green;'>✅ Admin user found</p>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><td>" . $adminUser['id'] . "</td></tr>";
            echo "<tr><th>Username</th><td>" . $adminUser['username'] . "</td></tr>";
            echo "<tr><th>Email</th><td>" . $adminUser['email'] . "</td></tr>";
            echo "<tr><th>Role</th><td>" . $adminUser['role'] . "</td></tr>";
            echo "<tr><th>Status</th><td>" . $adminUser['status'] . "</td></tr>";
            echo "</table>";
            
            // Test password verification
            echo "<h3>3. Password Verification Test</h3>";
            if (password_verify('Admin@123', $adminUser['password_hash'])) {
                echo "<p style='color: green;'>✅ Password verification successful</p>";
            } else {
                echo "<p style='color: red;'>❌ Password verification failed</p>";
                echo "<p>Expected hash for 'Admin@123': " . password_hash('Admin@123', PASSWORD_DEFAULT) . "</p>";
                echo "<p>Actual hash in database: " . $adminUser['password_hash'] . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Admin user not found</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Users table missing</p>";
    }
    
    // Check if personnel table exists
    if (in_array('personnel', $tableNames)) {
        echo "<p style='color: green;'>✅ Personnel table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Personnel table missing</p>";
    }
    
    // Check if login_attempts table exists
    if (in_array('login_attempts', $tableNames)) {
        echo "<p style='color: green;'>✅ Login attempts table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Login attempts table missing</p>";
    }
    
    // Test login function
    echo "<h3>4. Login Function Test</h3>";
    $authController = new AuthController();
    $loginResult = $authController->login('admin', 'Admin@123');
    
    echo "<p>Login result:</p>";
    echo "<pre>";
    print_r($loginResult);
    echo "</pre>";
    
    if ($loginResult['success']) {
        echo "<p style='color: green;'>✅ Login function successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Login function failed: " . $loginResult['message'] . "</p>";
    }
    
    // Check session configuration
    echo "<h3>5. Session Configuration</h3>";
    echo "<p>Session status: " . session_status() . "</p>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Session save path: " . session_save_path() . "</p>";
    
    // Test AuthController methods
    echo "<h3>6. AuthController Methods Test</h3>";
    if (method_exists($authController, 'validateSession')) {
        echo "<p style='color: green;'>✅ validateSession method exists</p>";
    } else {
        echo "<p style='color: red;'>❌ validateSession method missing</p>";
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
    }
</style>
