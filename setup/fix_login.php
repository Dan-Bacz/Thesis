<?php
/**
 * Fix Login Issues Script
 * Creates missing tables and fixes password hash
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

echo "<h2>Fix Login Issues</h2>";

try {
    $db = new Database();
    
    // Create missing login_attempts table
    echo "<h3>1. Creating login_attempts table</h3>";
    $createLoginAttempts = "
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        success BOOLEAN DEFAULT FALSE
    )";
    
    $db->execute($createLoginAttempts);
    echo "<p style='color: green;'>login_attempts table created successfully</p>";
    
    // Fix admin password hash
    echo "<h3>2. Fixing Admin Password</h3>";
    
    // Check current admin user
    $adminUser = $db->fetch("SELECT * FROM users WHERE username = 'admin'");
    
    if ($adminUser) {
        // Update password hash to match 'Admin@123'
        $newPasswordHash = password_hash('Admin@123', PASSWORD_DEFAULT, ['cost' => HASH_COST]);
        
        $updateResult = $db->execute(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE username = 'admin'",
            [$newPasswordHash]
        );
        
        if ($updateResult->rowCount() > 0) {
            echo "<p style='color: green;'>Admin password updated successfully</p>";
            
            // Verify the new password
            $updatedAdmin = $db->fetch("SELECT * FROM users WHERE username = 'admin'");
            if (password_verify('Admin@123', $updatedAdmin['password_hash'])) {
                echo "<p style='color: green;'>Password verification successful</p>";
            } else {
                echo "<p style='color: red;'>Password verification still failed</p>";
            }
        } else {
            echo "<p style='color: orange;'>Password update failed (no changes made)</p>";
        }
    } else {
        echo "<p style='color: red;'>Admin user not found</p>";
    }
    
    // Test login function
    echo "<h3>3. Testing Login Function</h3>";
    
    // Create AuthController instance
    $authController = new AuthController();
    
    // Test login
    $loginResult = $authController->login('admin', 'Admin@123');
    
    echo "<p>Login result:</p>";
    echo "<pre>";
    print_r($loginResult);
    echo "</pre>";
    
    if ($loginResult['success']) {
        echo "<p style='color: green;'>Login test successful! Admin can now login.</p>";
        echo "<h3>4. Next Steps</h3>";
        echo "<ol>";
        echo "<li><a href='../views/auth/login.php'>Try logging in with:</a></li>";
        echo "<ul>";
        echo "<li>Username: admin</li>";
        echo "<li>Password: Admin@123</li>";
        echo "</ul>";
        echo "<li>Change the default password after login</li>";
        echo "<li>Delete this setup script for security</li>";
        echo "</ol>";
    } else {
        echo "<p style='color: red;'>Login test failed: " . $loginResult['message'] . "</p>";
    }
    
    // Check all tables
    echo "<h3>4. Database Tables Status</h3>";
    $tables = $db->fetchAll("SHOW TABLES");
    $tableNames = array_column($tables, 'Tables_in_if0_41724093_bjmp_personnel');
    
    echo "<p>All tables: " . implode(', ', $tableNames) . "</p>";
    
    $requiredTables = ['users', 'personnel', 'login_attempts', 'audit_logs', 'document_types', 'personal_documents', 'leave_applications', 'leave_credits', 'clearance_requirements', 'employee_clearance', 'service_records', 'system_settings'];
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $tableNames)) {
            echo "<p style='color: green;'>- $table: EXISTS</p>";
        } else {
            echo "<p style='color: red;'>- $table: MISSING</p>";
        }
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
    }
    a:hover {
        text-decoration: underline;
    }
</style>
