<?php
// Signup template for PHP implementation

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireMethod('POST');
    
    if (!validateCSRF()) {
        setFlashMessage('error', 'Invalid security token');
        redirectTo('/signup');
    }
    
    if (!$security->checkRateLimit('signup:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 60)) {
        setFlashMessage('error', 'Too many signup attempts. Please try again later.');
        redirectTo('/signup');
    }
    
    $displayName = sanitizeInput($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $result = $auth->signup($displayName, $password, $confirmPassword);
    
    if ($result['success']) {
        $message = sprintf(
            'Account created successfully! Your account ID is: %s. Your recovery phrase is: %s. Please save these securely!',
            $result['account_id'],
            $result['recovery_phrase']
        );
        setFlashMessage('success', $message);
        redirectTo('/');
    } else {
        setFlashMessage('error', $result['error']);
        redirectTo('/signup');
    }
}

// GET request - show signup form
$data = getPageData('Sign Up');
renderTemplate('signup_content', $data);
?>