<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $user = getCurrentUser();
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
            redirect('/views/dashboard/employee.php');
    }
}

$error = '';
$success = '';

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new AuthController();
    $result = $authController->register($_POST);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .signup-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1200px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .signup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .signup-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .signup-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .signup-body {
            padding: 40px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h4 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .required {
            color: #dc3545;
        }
        
        .row {
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .signup-body {
                padding: 20px;
            }
            
            .signup-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-header">
            <h2><i class="fas fa-shield-alt me-2"></i>BJMP Personnel Registration</h2>
            <p>Create your account to access the Personnel Management System</p>
        </div>
        
        <div class="signup-body">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <form method="POST" id="signupForm">
                    <!-- Account Information -->
                    <div class="form-section">
                        <h4><i class="fas fa-user me-2"></i>Account Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="required">*</span></label>
                                <input type="text" name="username" class="form-control" required 
                                       pattern="[a-zA-Z0-9_]{3,20}" 
                                       title="Username must be 3-20 characters, letters, numbers, and underscores only">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="required">*</span></label>
                                <input type="password" name="password" class="form-control" required 
                                       minlength="8" 
                                       title="Password must be at least 8 characters long">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password <span class="required">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h4><i class="fas fa-id-card me-2"></i>Personal Information</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Suffix</label>
                                <select name="suffix" class="form-select">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Birth Date <span class="required">*</span></label>
                                <input type="date" name="birth_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender <span class="required">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h4><i class="fas fa-phone me-2"></i>Contact Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Mobile Number <span class="required">*</span></label>
                                <input type="tel" name="mobile_number" class="form-control" required 
                                       pattern="[0-9]{11}" 
                                       title="Enter 11-digit mobile number (e.g., 09XXXXXXXXX)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telephone Number</label>
                                <input type="tel" name="telephone_number" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label">Address <span class="required">*</span></label>
                                <input type="text" name="address" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- BJMP Employment Information -->
                    <div class="form-section">
                        <h4><i class="fas fa-briefcase me-2"></i>BJMP Employment Information</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Employee Number <span class="required">*</span></label>
                                <input type="text" name="employee_number" class="form-control" required 
                                       pattern="[A-Z]{2}[0-9]{6}" 
                                       title="Format: AA123456 (2 letters + 6 digits)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Position <span class="required">*</span></label>
                                <input type="text" name="position" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rank <span class="required">*</span></label>
                                <select name="rank" class="form-select" required>
                                    <option value="">Select Rank</option>
                                    <option value="JO1">JO1 (Jail Officer 1)</option>
                                    <option value="JO2">JO2 (Jail Officer 2)</option>
                                    <option value="JO3">JO3 (Jail Officer 3)</option>
                                    <option value="JINS1">JINS1 (Jail Inspector 1)</option>
                                    <option value="JINS2">JINS2 (Jail Inspector 2)</option>
                                    <option value="JINS3">JINS3 (Jail Inspector 3)</option>
                                    <option value="JCSIN1">JCSIN1 (Jail Chief Inspector 1)</option>
                                    <option value="JCSIN2">JCSIN2 (Jail Chief Inspector 2)</option>
                                    <option value="JCSIN3">JCSIN3 (Jail Chief Inspector 3)</option>
                                    <option value="JSUPT">JSUPT (Jail Superintendent)</option>
                                    <option value="JSSUPT">JSSUPT (Jail Senior Superintendent)</option>
                                    <option value="JCHIEF">JCHIEF (Jail Chief)</option>
                                    <option value="JDIR">JDIR (Jail Director)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Station/Office <span class="required">*</span></label>
                                <input type="text" name="station" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department <span class="required">*</span></label>
                                <input type="text" name="department" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date of Appointment <span class="required">*</span></label>
                                <input type="date" name="date_appointed" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Educational Background -->
                    <div class="form-section">
                        <h4><i class="fas fa-graduation-cap me-2"></i>Educational Background</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Highest Educational Attainment <span class="required">*</span></label>
                                <select name="education" class="form-select" required>
                                    <option value="">Select Education Level</option>
                                    <option value="High School">High School Graduate</option>
                                    <option value="College">College Graduate</option>
                                    <option value="Bachelor's">Bachelor's Degree</option>
                                    <option value="Master's">Master's Degree</option>
                                    <option value="Doctorate">Doctorate Degree</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course/Degree</label>
                                <input type="text" name="course" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact -->
                    <div class="form-section">
                        <h4><i class="fas fa-user-friends me-2"></i>Emergency Contact</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Emergency Contact Person <span class="required">*</span></label>
                                <input type="text" name="emergency_contact_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Relationship <span class="required">*</span></label>
                                <input type="text" name="emergency_contact_relationship" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Emergency Contact Number <span class="required">*</span></label>
                                <input type="tel" name="emergency_contact_number" class="form-control" required 
                                       pattern="[0-9]{11}" 
                                       title="Enter 11-digit mobile number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-signup">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Sign In Here</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password and Confirm Password do not match!');
                return false;
            }
            
            // Password strength validation
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character!');
                return false;
            }
        });
        
        // Employee number formatting
        document.querySelector('input[name="employee_number"]').addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase();
            e.target.value = value;
        });
    </script>
</body>
</html>
