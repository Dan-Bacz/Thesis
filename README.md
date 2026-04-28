# BJMP Personnel Management System

A comprehensive web-based personnel management system designed for the Bureau of Jail Management and Penology (BJMP) to securely manage personnel documents, leave applications, clearance status, and generate reports.

## Features

### Core Modules
- **Document Management**: Secure storage and verification of personnel documents
- **Leave Management**: Digital submission and processing of leave applications
- **Clearance Tracking**: Digital clearance status monitoring and certificate generation
- **Report Generation**: Service records, personnel data sheets, and various reports
- **User Management**: Role-based access control with audit logging

### Security Features
- Secure authentication with session management
- Role-based authorization system
- Input validation and sanitization
- CSRF protection
- SQL injection prevention
- File upload security scanning
- Audit trail logging
- Rate limiting and IP filtering

### Technical Specifications
- **Backend**: PHP 8.0+ with PDO
- **Frontend**: Bootstrap 5, Font Awesome 6
- **Database**: MySQL/MariaDB
- **Hosting**: Optimized for InfinityFree
- **Security**: OWASP best practices

## System Requirements

### Server Requirements
- PHP 8.0 or higher
- MySQL 5.7 or MariaDB 10.2+
- Web server (Apache/Nginx)
- SSL certificate (HTTPS required)
- File upload permissions

### PHP Extensions Required
- PDO MySQL
- OpenSSL
- GD Library
- Fileinfo
- JSON
- Session
- mbstring

## Installation

### 1. Database Setup

1. Create a new database in your MySQL server
2. Import the database schema:

```sql
mysql -u username -p database_name < database.sql
```

### 2. Configuration

1. Update database credentials in `config/database.php`:

```php
private $host = 'localhost';
private $dbname = 'your_database_name';
private $username = 'your_username';
private $password = 'your_password';
```

2. Update application settings in `config/config.php`:

```php
define('BASE_URL', 'https://your-domain.com');
define('FROM_EMAIL', 'noreply@your-domain.com');
```

### 3. File Permissions

Set appropriate permissions for upload directories:

```bash
chmod 755 uploads/
chmod 755 logs/
```

### 4. Create Admin User

Run the following SQL to create the first admin user:

```sql
INSERT INTO users (username, password_hash, email, full_name, employee_id, role, status) 
VALUES ('admin', '$2y$12$your_hashed_password', 'admin@bjmp.gov.ph', 'System Administrator', 'ADMIN001', 'admin', 'active');
```

## Deployment on InfinityFree

### 1. Upload Files

1. Compress all project files into a ZIP archive
2. Upload to InfinityFree File Manager
3. Extract the files

### 2. Database Configuration

1. Access InfinityFree Control Panel
2. Go to MySQL Database
3. Create database and note credentials
4. Import the database schema

### 3. Update Configuration

1. Edit `deploy/infinityfree.php` with your database credentials
2. Update BASE_URL with your InfinityFree subdomain

### 4. Set File Permissions

Use InfinityFree File Manager to set permissions:
- `uploads/` directory: 755
- `logs/` directory: 755

## User Roles and Permissions

### Administrator (admin)
- Full system access
- User management
- System configuration
- All report generation

### HR Personnel (hr)
- Personnel management
- Document verification
- Leave approval
- Clearance processing

### Supervisor (supervisor)
- Team member management
- Leave approval for team
- Team document viewing

### Employee (employee)
- Personal profile management
- Document upload
- Leave application
- Clearance status viewing

## Security Considerations

### Password Policy
- Minimum 8 characters
- Must include uppercase, lowercase, numbers, and special characters
- Password hashing with bcrypt

### File Upload Security
- File type validation
- Size restrictions (5MB max)
- Content scanning for malicious code
- Secure file storage

### Session Security
- Secure cookies
- Session timeout (1 hour)
- CSRF protection
- IP-based session validation

### Data Protection
- Input sanitization
- SQL injection prevention
- XSS protection
- Audit logging

## API Documentation

### Authentication Endpoints

#### Login
```
POST /views/auth/login.php
Content-Type: application/x-www-form-urlencoded

username=admin&password=password123
```

#### Logout
```
GET /views/auth/logout.php
```

### Document Management

#### Upload Document
```
POST /controllers/DocumentController.php?action=upload
Content-Type: multipart/form-data

personnel_id=1&document_type_id=1&file=@document.pdf
```

#### Get Documents
```
GET /controllers/DocumentController.php?personnel_id=1&status=pending
```

### Leave Management

#### Apply for Leave
```
POST /controllers/LeaveController.php?action=apply
Content-Type: application/x-www-form-urlencoded

personnel_id=1&leave_type=Vacation&start_date=2024-01-01&end_date=2024-01-05&reason=Family vacation
```

## Troubleshooting

### Common Issues

#### Database Connection Error
- Check database credentials in config files
- Verify database server is running
- Ensure user has proper permissions

#### File Upload Issues
- Check upload directory permissions
- Verify file size limits
- Ensure PHP upload limits are sufficient

#### Session Issues
- Check session save path permissions
- Verify cookie settings
- Clear browser cache and cookies

#### Performance Issues
- Optimize database queries
- Enable database caching
- Monitor server resources

### Error Codes

| Code | Description | Solution |
|------|-------------|----------|
| 401 | Unauthorized | Check login credentials |
| 403 | Forbidden | Verify user permissions |
| 404 | Not Found | Check URL and file paths |
| 500 | Server Error | Check error logs |
| 503 | Service Unavailable | Database connection issue |

## Maintenance

### Regular Tasks

1. **Database Backup**
   - Weekly full backups
   - Daily incremental backups
   - Store backups securely

2. **Log Review**
   - Monitor security logs
   - Check error logs
   - Review audit trails

3. **System Updates**
   - Update PHP dependencies
   - Apply security patches
   - Update third-party libraries

4. **Performance Monitoring**
   - Monitor server load
   - Check database performance
   - Optimize slow queries

### Security Maintenance

1. **Password Policy Enforcement**
   - Regular password changes
   - Account lockout monitoring
   - Suspicious activity detection

2. **File System Security**
   - Regular permission checks
   - Malware scanning
   - Backup verification

3. **Network Security**
   - SSL certificate renewal
   - Firewall configuration
   - DDoS protection

## Support

### Technical Support
- Email: support@bjmp.gov.ph
- Documentation: [System Wiki]
- Issue Tracker: [GitHub Issues]

### Training Resources
- User Manual
- Video Tutorials
- FAQ Section

## License

This project is proprietary software for BJMP internal use only.

## Version History

### v1.0.0 (Current)
- Initial release
- Core functionality implemented
- Security features integrated
- InfinityFree deployment ready

---

**Note**: This system contains sensitive personnel data and should only be deployed in a secure, controlled environment with proper access controls and regular security audits.
