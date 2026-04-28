<?php
/**
 * Main Entry Point - BJMP Personnel Management System
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is logged in, redirect to login if not
if (!isLoggedIn()) {
    redirect('/views/auth/login.php');
}

// Get current user
$user = getCurrentUser();

// Redirect based on role
switch ($user['role']) {
    case 'admin':
    case 'hr':
        redirect('/views/dashboard/admin.php');
        break;
    case 'supervisor':
        redirect('/views/dashboard/supervisor.php');
        break;
    case 'employee':
        redirect('/views/dashboard/employee.php');
        break;
    default:
        redirect('/views/auth/login.php');
}
?>
