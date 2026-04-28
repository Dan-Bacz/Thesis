<?php
/**
 * Railway Deployment Setup Script
 * Creates admin account and configures system for Railway deployment
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "<h2>Railway Deployment Setup</h2>";

try {
    $db = new Database();
    
    // Check if admin exists
    echo "<h3>1. Checking Admin Account</h3>";
    $adminUser = $db->fetch("SELECT * FROM users WHERE username = 'admin'");
    
    if ($adminUser) {
        echo "<p style='color: green;'>Admin account already exists</p>";
    } else {
        echo "<p style='color: orange;'>Creating admin account...</p>";
        
        // Create admin account
        $passwordHash = password_hash('Admin@123', PASSWORD_DEFAULT, ['cost' => HASH_COST]);
        
        $userData = [
            'username' => 'admin',
            'password_hash' => $passwordHash,
            'email' => 'admin@bjmp.gov.ph',
            'full_name' => 'System Administrator',
            'role' => 'admin',
            'status' => 'active',
            'department' => 'IT Department',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $userId = $db->insert('users', $userData);
        
        // Create personnel record
        $personnelData = [
            'user_id' => $userId,
            'employee_number' => 'ADMIN001',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'position' => 'IT Administrator',
            'rank' => 'JDIR',
            'station' => 'BJMP National HQ',
            'department' => 'IT Department',
            'date_appointed' => date('Y-m-d'),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('personnel', $personnelData);
        
        // Initialize leave credits
        $leaveCreditsData = [
            'personnel_id' => $userId,
            'vacation_leave' => 15,
            'sick_leave' => 10,
            'emergency_leave' => 5,
            'maternity_leave' => 60,
            'paternity_leave' => 7,
            'special_leave' => 3,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('leave_credits', $leaveCreditsData);
        
        // Initialize clearance
        $clearanceData = [
            'personnel_id' => $userId,
            'status' => 'pending',
            'date_submitted' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('employee_clearance', $clearanceData);
        
        echo "<p style='color: green;'>Admin account created successfully</p>";
    }
    
    // Check environment
    echo "<h3>2. Environment Check</h3>";
    echo "<p>Environment: " . (getenv('ENVIRONMENT') ?: 'development') . "</p>";
    echo "<p>Database Host: " . (getenv('DB_HOST') ?: 'Not set') . "</p>";
    echo "<p>Base URL: " . (getenv('BASE_URL') ?: BASE_URL) . "</p>";
    
    // Test database connection
    echo "<h3>3. Database Connection Test</h3>";
    $tables = $db->fetchAll("SHOW TABLES");
    echo "<p>Tables found: " . count($tables) . "</p>";
    
    // Check required directories
    echo "<h3>4. Directory Check</h3>";
    $requiredDirs = ['uploads', 'logs', 'temp'];
    foreach ($requiredDirs as $dir) {
        if (is_dir(__DIR__ . '/../' . $dir)) {
            echo "<p style='color: green;'>✓ $dir directory exists</p>";
        } else {
            echo "<p style='color: orange;'>✗ $dir directory missing</p>";
        }
    }
    
    echo "<h3>5. Deployment Information</h3>";
    echo "<p><strong>Default Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: Admin@123</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Access your Railway app URL</li>";
    echo "<li>Login with default credentials</li>";
    echo "<li>Change default password immediately</li>";
    echo "<li>Test all system features</li>";
    echo "</ol>";
    
    echo "<div style='background: #e3f2fd; color: white; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🚀 Ready for Railway Deployment!</h3>";
    echo "<p>Your BJMP Personnel Management System is configured and ready for deployment.</p>";
    echo "</div>";
    
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
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h2, h3 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 5px;
    }
    ol, ul {
        background: white;
        padding: 15px;
        border-radius: 5px;
        margin: 10px 0;
    }
    li {
        margin: 5px 0;
    }
</style>
