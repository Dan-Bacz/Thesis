<?php
/**
 * Default Admin Account Setup Script
 * Run this script to create the default admin account
 */

// Include configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "<h2>BJMP Personnel Management System - Admin Setup</h2>";

try {
    $db = new Database();
    
    // Check if admin account already exists
    $existingAdmin = $db->fetch("SELECT id FROM users WHERE username = 'admin'");
    
    if ($existingAdmin) {
        echo "<div style='color: orange; padding: 10px; border: 1px solid orange; background: #fff3cd; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>Warning:</strong> Admin account already exists!";
        echo "</div>";
        
        echo "<h3>Current Admin Credentials:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><td><strong>Username:</strong></td><td>admin</td></tr>";
        echo "<tr><td><strong>Password:</strong></td><td>Admin@123</td></tr>";
        echo "</table>";
        
        echo "<p><em>Note: You can change the password after logging in.</em></p>";
    } else {
        // Create default admin account
        $passwordHash = password_hash('Admin@123', PASSWORD_DEFAULT, ['cost' => HASH_COST]);
        
        // Start transaction
        $db->beginTransaction();
        
        // Insert admin user
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
        
        // Insert admin personnel record
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
        
        $personnelId = $db->insert('personnel', $personnelData);
        
        // Commit transaction
        $db->commit();
        
        echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #d4edda; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>Success:</strong> Default admin account created successfully!";
        echo "</div>";
        
        echo "<h3>Default Admin Credentials:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><td><strong>Username:</strong></td><td>admin</td></tr>";
        echo "<tr><td><strong>Password:</strong></td><td>Admin@123</td></tr>";
        echo "</table>";
        
        echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #f8d7da; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>IMPORTANT:</strong> Please change the default password after first login for security!";
        echo "</div>";
    }
    
    // Test database connection
    echo "<h3>Database Connection Test:</h3>";
    $testQuery = $db->fetch("SELECT COUNT(*) as count FROM users");
    echo "<p>Total users in database: " . $testQuery['count'] . "</p>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='../views/auth/login.php'>Login to the system</a> using the credentials above</li>";
    echo "<li>Change the default password immediately</li>";
    echo "<li>Start approving new officer accounts</li>";
    echo "<li>Assign PRM officers to manage personnel</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #f8d7da; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Ensure database is created and schema is imported</li>";
    echo "<li>Check database connection details in config/database.php</li>";
    echo "<li>Verify database user has proper permissions</li>";
    echo "</ul>";
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
    }
    table {
        background: white;
        margin: 10px 0;
    }
    td {
        padding: 8px;
    }
    a {
        color: #007bff;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
</style>
