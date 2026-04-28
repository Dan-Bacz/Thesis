-- BJMP Personnel Management System Database Schema
-- Created for InfinityFree PHP/MySQL Hosting

-- Users table for authentication and authorization
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    role ENUM('admin', 'hr', 'supervisor', 'employee') DEFAULT 'employee',
    department VARCHAR(50),
    position VARCHAR(100),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Personnel information table
CREATE TABLE personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    employee_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    suffix VARCHAR(10),
    birth_date DATE,
    gender ENUM('Male', 'Female', 'Other'),
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Divorced'),
    address TEXT,
    contact_number VARCHAR(20),
    emergency_contact VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    date_hired DATE,
    employment_status ENUM('Regular', 'Contractual', 'Probationary', 'Job Order') DEFAULT 'Regular',
    rank VARCHAR(50),
    assigned_prm_officer_id INT NULL,
    station_assignment VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Document types table
CREATE TABLE document_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    is_required BOOLEAN DEFAULT FALSE,
    expiry_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Personal documents storage
CREATE TABLE personal_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    document_type_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_type VARCHAR(50),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE NULL,
    status ENUM('pending', 'verified', 'rejected', 'expired') DEFAULT 'pending',
    verified_by INT NULL,
    verified_date TIMESTAMP NULL,
    remarks TEXT,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Leave applications
CREATE TABLE leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    leave_type ENUM('Vacation', 'Sick', 'Maternity', 'Paternity', 'Emergency', 'Special Privilege', 'Study Leave') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT NULL,
    approved_date TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Leave credits tracking
CREATE TABLE leave_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    leave_type ENUM('Vacation', 'Sick', 'Maternity', 'Paternity', 'Emergency', 'Special Privilege', 'Study Leave') NOT NULL,
    total_credits DECIMAL(5,2) DEFAULT 0,
    used_credits DECIMAL(5,2) DEFAULT 0,
    remaining_credits DECIMAL(5,2) GENERATED ALWAYS AS (total_credits - used_credits) STORED,
    year INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    UNIQUE KEY unique_leave_credit (personnel_id, leave_type, year)
);

-- Clearance requirements
CREATE TABLE clearance_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    department VARCHAR(50),
    is_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Employee clearance status
CREATE TABLE employee_clearance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    clearance_requirement_id INT NOT NULL,
    status ENUM('pending', 'cleared', 'not_cleared', 'exempted') DEFAULT 'pending',
    cleared_by INT NULL,
    cleared_date TIMESTAMP NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (clearance_requirement_id) REFERENCES clearance_requirements(id),
    FOREIGN KEY (cleared_by) REFERENCES users(id)
);

-- Service records
CREATE TABLE service_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE,
    position VARCHAR(100) NOT NULL,
    rank VARCHAR(50),
    station VARCHAR(100),
    salary_grade VARCHAR(10),
    step VARCHAR(5),
    status ENUM('active', 'transferred', 'promoted', 'retired', 'separated') DEFAULT 'active',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
);

-- Audit logs for security
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- System settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Insert default document types
INSERT INTO document_types (name, description, category, is_required, expiry_required) VALUES
('Personal Data Sheet', 'Updated Personal Data Sheet', 'Basic Information', TRUE, TRUE),
('Birth Certificate', 'NSO/PSA Birth Certificate', 'Basic Information', TRUE, FALSE),
('Marriage Certificate', 'Marriage Contract (if married)', 'Basic Information', FALSE, FALSE),
('NBI Clearance', 'National Bureau of Investigation Clearance', 'Clearance', TRUE, TRUE),
('Police Clearance', 'Police Clearance Certificate', 'Clearance', TRUE, TRUE),
('Barangay Clearance', 'Barangay Clearance Certificate', 'Clearance', TRUE, TRUE),
('Medical Certificate', 'Medical/Fitness Certificate', 'Health', TRUE, TRUE),
('Diploma/TOR', 'Diploma or Transcript of Records', 'Education', TRUE, FALSE),
('PRC License', 'Professional Regulation Commission License', 'Professional', FALSE, TRUE),
('Training Certificates', 'Training and Seminar Certificates', 'Training', FALSE, FALSE);

-- Insert default clearance requirements
INSERT INTO clearance_requirements (name, description, department, is_required) VALUES
('Property Clearance', 'Clearance for issued government property', 'Property Office', TRUE),
('Account Clearance', 'Clearance from accounting department', 'Accounting', TRUE),
('Library Clearance', 'Clearance from library for borrowed materials', 'Library', TRUE),
('Medical Clearance', 'Final medical examination clearance', 'Medical Office', TRUE),
('Administrative Clearance', 'Final administrative clearance', 'HR Department', TRUE),
('IT Equipment Clearance', 'Clearance for issued IT equipment', 'IT Department', TRUE);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'BJMP Personnel Management System', 'System name display'),
('max_file_size', '5242880', 'Maximum file upload size in bytes (5MB)'),
('allowed_file_types', 'pdf,jpg,jpeg,png,doc,docx', 'Allowed file extensions'),
('password_min_length', '8', 'Minimum password length'),
('session_timeout', '3600', 'Session timeout in seconds');

-- Insert default admin account
-- Username: admin
-- Password: Admin@123
INSERT INTO users (username, password_hash, email, full_name, role, status, department, created_at) VALUES
('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj6ukx.LrUpm', 'admin@bjmp.gov.ph', 'System Administrator', 'admin', 'active', 'IT Department', NOW());

-- Insert corresponding personnel record for admin
INSERT INTO personnel (user_id, employee_number, first_name, last_name, position, rank, station, department, date_appointed, status, created_at) VALUES
(1, 'ADMIN001', 'System', 'Administrator', 'IT Administrator', 'JDIR', 'BJMP National HQ', 'IT Department', CURDATE(), 'active', NOW());

-- Create indexes for better performance
CREATE INDEX idx_personnel_user ON personnel(user_id);
CREATE INDEX idx_documents_personnel ON personal_documents(personnel_id);
CREATE INDEX idx_documents_type ON personal_documents(document_type_id);
CREATE INDEX idx_leave_personnel ON leave_applications(personnel_id);
CREATE INDEX idx_clearance_personnel ON employee_clearance(personnel_id);
CREATE INDEX idx_service_personnel ON service_records(personnel_id);
CREATE INDEX idx_audit_user ON audit_logs(user_id);
CREATE INDEX idx_audit_created ON audit_logs(created_at);
