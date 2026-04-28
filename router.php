<?php
/**
 * PHP Built-in Server Router
 * Handles URL rewriting for PHP built-in server
 */

$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);

// Remove query string
$request_path = strtok($request_path, '?');

// Define routes
$routes = [
    '/' => 'index.php',
    '/login' => 'views/auth/login.php',
    '/signup' => 'views/auth/signup.php',
    '/logout' => 'views/auth/logout.php',
    '/dashboard' => 'views/dashboard/admin.php',
    '/admin' => 'views/dashboard/admin.php',
    '/prm' => 'views/dashboard/prm_officer.php',
    '/officer' => 'views/dashboard/officer.php',
];

// Check if file exists directly
if (file_exists(__DIR__ . $request_path) && is_file(__DIR__ . $request_path)) {
    // Serve static files directly
    return false;
}

// Check for route mapping
if (isset($routes[$request_path])) {
    include __DIR__ . '/' . $routes[$request_path];
    return true;
}

// Check for dynamic routes
if (strpos($request_path, '/views/') === 0) {
    $file_path = __DIR__ . $request_path;
    if (file_exists($file_path)) {
        include $file_path;
        return true;
    }
}

// Check for API routes
if (strpos($request_path, '/api/') === 0) {
    // Handle API requests
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found']);
    return true;
}

// Default to 404
http_response_code(404);
echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #dc3545; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>404 - Page Not Found</h1>
    <p>The page you are looking for does not exist.</p>
    <p><a href="/">Go to Home</a></p>
</body>
</html>';
return true;

?>
