# Installation Guide
## BJMP Personnel Management System

### Prerequisites
- Web server (Apache/Nginx)
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.2+
- SSL certificate
- File system write permissions

### Step 1: Database Setup

1. **Create Database**
   ```sql
   CREATE DATABASE bjmp_personnel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Create User**
   ```sql
   CREATE USER 'bjmp_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON bjmp_personnel.* TO 'bjmp_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Import Schema**
   ```bash
   mysql -u bjmp_user -p bjmp_personnel < database.sql
   ```

### Step 2: Configuration

1. **Database Configuration**
   Edit `config/database.php`:
   ```php
   private $host = 'localhost';
   private $dbname = 'bjmp_personnel';
   private $username = 'bjmp_user';
   private $password = 'secure_password';
   ```

2. **Application Configuration**
   Edit `config/config.php`:
   ```php
   define('BASE_URL', 'https://your-domain.com');
   define('FROM_EMAIL', 'noreply@your-domain.com');
   ```

### Step 3: File System Setup

1. **Create Directories**
   ```bash
   mkdir -p uploads/documents
   mkdir -p logs
   ```

2. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   chmod 755 uploads/documents/
   ```

### Step 4: Create Admin User

```sql
INSERT INTO users (username, password_hash, email, full_name, employee_id, role, status) 
VALUES ('admin', '$2y$12$K2x.Fd8uYQ6zZQjN8rM2OeQhL5fN4vXcW7sY6zZQjN8rM2OeQhL', 'admin@bjmp.gov.ph', 'System Administrator', 'ADMIN001', 'admin', 'active');
```

### Step 5: Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Hide server signature
ServerTokens Prod
ServerSignature Off
```

#### Nginx
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /var/www/html;
    index index.php;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Security headers
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

### Step 6: Verification

1. **Test Database Connection**
   - Access the login page
   - Try logging in with admin credentials

2. **Test File Upload**
   - Upload a test document
   - Verify it appears in the uploads directory

3. **Test Security Features**
   - Verify HTTPS is working
   - Check security headers are present

## InfinityFree Specific Instructions

### 1. Account Setup
1. Create InfinityFree account
2. Register subdomain
3. Access control panel

### 2. Database Setup
1. Go to MySQL Database in control panel
2. Create database
3. Note database credentials
4. Import database schema via phpMyAdmin

### 3. File Upload
1. Compress project files to ZIP
2. Upload via File Manager
3. Extract files

### 4. Configuration Update
Edit `deploy/infinityfree.php` with your credentials:
```php
define('DB_HOST', 'sql311.infinityfree.com');
define('DB_NAME', 'if0_12345678_bjmp_personnel');
define('DB_USER', 'if0_12345678_bjmp_user');
define('DB_PASS', 'your_password');
```

### 5. Final Steps
1. Set file permissions via File Manager
2. Test application
3. Create admin user via phpMyAdmin

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Verify database credentials
- Check database server status
- Ensure user has proper permissions

**File Upload Not Working**
- Check upload directory permissions
- Verify PHP upload limits
- Check disk space

**Session Issues**
- Clear browser cache
- Check session save path
- Verify cookie settings

**404 Errors**
- Check .htaccess file
- Verify mod_rewrite is enabled
- Check file paths

### Error Logging
Enable error logging for debugging:
```php
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');
```

## Security Checklist

- [ ] HTTPS enabled
- [ ] Security headers configured
- [ ] File permissions set correctly
- [ ] Database credentials secured
- [ ] Admin password changed
- [ ] Backup systems configured
- [ ] Monitoring enabled
- [ ] Firewall configured

## Performance Optimization

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_personnel_user_id ON personnel(user_id);
CREATE INDEX idx_documents_personnel_id ON personal_documents(personnel_id);
```

### PHP Optimization
```ini
; Enable OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000

; Increase memory limit
memory_limit=256M

; Optimize upload settings
upload_max_filesize=5M
post_max_size=6M
max_execution_time=30
```

## Backup Strategy

### Automated Backup Script
```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"

# Database backup
mysqldump -u bjmp_user -p bjmp_personnel > $BACKUP_DIR/db_backup_$DATE.sql

# File backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz uploads/ logs/

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### Cron Job Setup
```bash
# Add to crontab for daily backup at 2 AM
0 2 * * * /path/to/backup.sh
```

## Support

For installation issues:
1. Check error logs
2. Verify all prerequisites
3. Review configuration files
4. Test components individually

Contact support if issues persist.
