<?php
/**
 * Security Middleware for BJMP Personnel Management System
 * Provides additional security layers and protection
 */

class SecurityMiddleware {
    
    /**
     * Initialize security measures
     */
    public static function init() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self';");
        
        // Remove server signature
        header('Server: BJMP-PMS');
        
        // Force HTTPS in production
        if ($_SERVER['HTTP_HOST'] !== 'localhost' && !isset($_SERVER['HTTPS'])) {
            $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirect_url");
            exit();
        }
        
        // Initialize session security
        self::secureSession();
        
        // Check for suspicious activity
        self::checkSuspiciousActivity();
        
        // Rate limiting
        self::rateLimitCheck();
    }
    
    /**
     * Secure session configuration
     */
    private static function secureSession() {
        // Custom session name
        session_name('BJMP_SESSION');
        
        // Secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Session garbage collection
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);
    }
    
    /**
     * Check for suspicious activity
     */
    private static function checkSuspiciousActivity() {
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check for common attack patterns
        $suspiciousPatterns = [
            '/union\s+select/i',
            '/select\s+.*\s+from/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/drop\s+table/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i'
        ];
        
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::logSecurityEvent('SQL_INJECTION_ATTEMPT', [
                            'ip' => $clientIP,
                            'user_agent' => $userAgent,
                            'parameter' => $key,
                            'value' => $value
                        ]);
                        
                        // Block the request
                        http_response_code(403);
                        die('Access denied');
                    }
                }
            }
        }
        
        // Check for suspicious user agents
        $blockedUserAgents = [
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'zap',
            'burp',
            'scanner'
        ];
        
        foreach ($blockedUserAgents as $blocked) {
            if (stripos($userAgent, $blocked) !== false) {
                self::logSecurityEvent('SUSPICIOUS_USER_AGENT', [
                    'ip' => $clientIP,
                    'user_agent' => $userAgent
                ]);
                
                http_response_code(403);
                die('Access denied');
            }
        }
    }
    
    /**
     * Rate limiting check
     */
    private static function rateLimitCheck() {
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $requestKey = 'rate_limit_' . md5($clientIP);
        
        // Simple file-based rate limiting
        $rateLimitFile = sys_get_temp_dir() . '/' . $requestKey;
        $maxRequests = 100; // Maximum requests per hour
        $timeWindow = 3600; // 1 hour
        
        $currentData = [];
        if (file_exists($rateLimitFile)) {
            $currentData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        }
        
        $now = time();
        
        // Clean old entries
        $currentData = array_filter($currentData, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // Check rate limit
        if (count($currentData) >= $maxRequests) {
            self::logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                'ip' => $clientIP,
                'requests' => count($currentData)
            ]);
            
            http_response_code(429);
            die('Too many requests');
        }
        
        // Add current request
        $currentData[] = $now;
        file_put_contents($rateLimitFile, json_encode($currentData));
    }
    
    /**
     * Log security events
     */
    private static function logSecurityEvent($eventType, $details) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'ip_address' => $details['ip'] ?? 'unknown',
            'user_agent' => $details['user_agent'] ?? 'unknown',
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Also log to database if available
        try {
            $db = new Database();
            $db->execute(
                "INSERT INTO security_logs (event_type, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$eventType, $details['ip'] ?? 'unknown', $details['user_agent'] ?? 'unknown', json_encode($details)]
            );
        } catch (Exception $e) {
            // Log to file if database fails
            error_log("Security log database error: " . $e->getMessage());
        }
    }
    
    /**
     * Input sanitization
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * CSRF protection
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check if token is valid (1 hour expiry)
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Password strength validation
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // Check for common passwords
        $commonPasswords = ['password', '123456', 'qwerty', 'admin', 'letmein'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Password is too common";
        }
        
        return $errors;
    }
    
    /**
     * File upload security
     */
    public static function secureFileUpload($file) {
        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Check file size
        if ($file['size'] > $maxFileSize) {
            throw new Exception("File size exceeds maximum limit");
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detectedType, $allowedTypes)) {
            throw new Exception("File type not allowed");
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("File extension not allowed");
        }
        
        // Scan for malicious content
        $content = file_get_contents($file['tmp_name']);
        $maliciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i'
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new Exception("File contains malicious content");
            }
        }
        
        return true;
    }
    
    /**
     * Data encryption
     */
    public static function encrypt($data, $key = null) {
        $key = $key ?: hash('sha256', 'BJMP_ENCRYPTION_KEY_2024', true);
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Data decryption
     */
    public static function decrypt($encryptedData, $key = null) {
        $key = $key ?: hash('sha256', 'BJMP_ENCRYPTION_KEY_2024', true);
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate email with additional checks
     */
    public static function validateEmail($email) {
        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check for suspicious domains
        $suspiciousDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
        $domain = substr(strrchr($email, "@"), 1);
        
        foreach ($suspiciousDomains as $suspicious) {
            if (stripos($domain, $suspicious) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * IP whitelist/blacklist check
     */
    public static function checkIPAccess($clientIP) {
        // Blacklist (can be stored in database or config)
        $blacklistedIPs = [
            // Add known malicious IPs here
        ];
        
        if (in_array($clientIP, $blacklistedIPs)) {
            self::logSecurityEvent('BLACKLISTED_IP_ACCESS', ['ip' => $clientIP]);
            return false;
        }
        
        // Whitelist (if enabled)
        $whitelistEnabled = false; // Set to true to enable whitelist
        $whitelistedIPs = [
            '127.0.0.1',
            '::1'
        ];
        
        if ($whitelistEnabled && !in_array($clientIP, $whitelistedIPs)) {
            self::logSecurityEvent('WHITELIST_VIOLATION', ['ip' => $clientIP]);
            return false;
        }
        
        return true;
    }
}

// Initialize security middleware on every request
SecurityMiddleware::init();
