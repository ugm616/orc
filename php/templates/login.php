<?php
// Login template for PHP implementation

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireMethod('POST');
    
    if (!validateCSRF()) {
        setFlashMessage('error', 'Invalid security token');
        redirectTo('/login');
    }
    
    if (!$security->checkRateLimit('login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 60)) {
        setFlashMessage('error', 'Too many login attempts. Please try again later.');
        redirectTo('/login');
    }
    
    $accountId = sanitizeInput($_POST['account_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $result = $auth->login($accountId, $password);
    
    if ($result['success']) {
        redirectTo('/');
    } else {
        setFlashMessage('error', $result['error']);
        redirectTo('/login');
    }
}

// GET request - show login form
$data = getPageData('Login');
renderTemplate('login_content', $data);
?>