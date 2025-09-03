<?php
// Orc Social - PHP Implementation for Shared Hosting
// Main entry point with routing

declare(strict_types=1);

// Set error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start session with secure settings
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0'); // Set to '1' with HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
session_start();

// Include configuration and core files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/utils.php';

// Initialize components
$config = Config::getInstance();
$db = Database::getInstance();
$auth = new Auth($db);
$security = new Security();
$theme = Theme::load($config->get('theme_path', '../config/theme.json'));

// Apply security headers
$security->setSecurityHeaders();

// Tor enforcement (if enabled)
if ($config->get('tor_only_mode', false)) {
    $security->enforceTorOnly();
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';

// Remove base path if running in subdirectory
$basePath = $config->get('base_path', '');
if ($basePath && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath)) ?: '/';
}

// Rate limiting
$limitKey = 'global:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$security->checkRateLimit($limitKey, 60, 60)) { // 60 requests per minute
    http_response_code(429);
    exit('Rate limit exceeded');
}

// CSRF token generation for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = $security->generateCSRFToken();
}

// Route handler function
function route(string $method, string $pattern, callable $handler): void {
    global $path;
    
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        return;
    }
    
    // Simple routing - exact match or parameter extraction
    if ($pattern === $path) {
        $handler();
        exit;
    }
    
    // Parameter routing (basic implementation)
    $patternRegex = preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern);
    $patternRegex = '#^' . $patternRegex . '$#';
    
    if (preg_match($patternRegex, $path, $matches)) {
        array_shift($matches); // Remove full match
        $handler(...$matches);
        exit;
    }
}

// Route definitions
route('GET', '/', function() {
    require __DIR__ . '/templates/home.php';
});

route('GET', '/signup', function() {
    require __DIR__ . '/templates/signup.php';
});

route('POST', '/signup', function() {
    require __DIR__ . '/templates/signup.php';
});

route('GET', '/login', function() {
    require __DIR__ . '/templates/login.php';
});

route('POST', '/login', function() {
    require __DIR__ . '/templates/login.php';
});

route('GET', '/logout', function() {
    global $auth;
    $auth->logout();
    header('Location: /');
    exit;
});

route('GET', '/profile', function() {
    global $auth;
    if (!$auth->isLoggedIn()) {
        header('Location: /login');
        exit;
    }
    require __DIR__ . '/templates/profile.php';
});

route('POST', '/post', function() {
    global $auth, $security;
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    if (!$security->checkRateLimit('post:' . $auth->getUserId(), 10, 60)) {
        http_response_code(429);
        exit('Rate limit exceeded');
    }
    
    require __DIR__ . '/includes/create_post.php';
});

route('POST', '/comment', function() {
    global $auth, $security;
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    if (!$security->checkRateLimit('comment:' . $auth->getUserId(), 20, 60)) {
        http_response_code(429);
        exit('Rate limit exceeded');
    }
    
    require __DIR__ . '/includes/create_comment.php';
});

route('POST', '/preview', function() {
    global $auth, $security;
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    if (!$security->checkRateLimit('preview:' . $auth->getUserId(), 3, 60)) {
        http_response_code(429);
        exit('Rate limit exceeded');
    }
    
    require __DIR__ . '/includes/link_preview.php';
});

route('GET', '/admin', function() {
    global $auth;
    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }
    require __DIR__ . '/admin/dashboard.php';
});

route('GET', '/admin/theme', function() {
    global $auth;
    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }
    require __DIR__ . '/admin/theme.php';
});

route('POST', '/admin/theme/save', function() {
    global $auth;
    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }
    require __DIR__ . '/admin/theme_save.php';
});

route('POST', '/admin/theme/preview', function() {
    global $auth;
    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }
    require __DIR__ . '/admin/theme_preview.php';
});

route('POST', '/admin/theme/reset', function() {
    global $auth;
    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }
    require __DIR__ . '/admin/theme_reset.php';
});

// Static file serving (for development - use web server in production)
if (strpos($path, '/static/') === 0) {
    $filePath = __DIR__ . '/..' . $path;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        
        if (isset($contentTypes[$ext])) {
            header('Content-Type: ' . $contentTypes[$ext]);
            readfile($filePath);
            exit;
        }
    }
}

// 404 - Not Found
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 - Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>';
?>