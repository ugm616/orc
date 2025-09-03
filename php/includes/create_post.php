<?php
// Create post handler for PHP implementation

declare(strict_types=1);

requireMethod('POST');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

if (!validateCSRF()) {
    http_response_code(403);
    exit('Invalid security token');
}

$body = sanitizeInput($_POST['body'] ?? '');
$url = sanitizeInput($_POST['url'] ?? '');

// Validate input
if (empty($body)) {
    setFlashMessage('error', 'Post body is required');
    redirectTo('/');
}

if (strlen($body) > $config->get('max_post_length', 1000)) {
    setFlashMessage('error', 'Post body too long');
    redirectTo('/');
}

// Validate URL if provided
if ($url && !isValidUrl($url)) {
    setFlashMessage('error', 'Invalid URL format');
    redirectTo('/');
}

if ($url) {
    $urlError = $security->validateUrl($url);
    if ($urlError) {
        setFlashMessage('error', 'Invalid URL: ' . $urlError);
        redirectTo('/');
    }
}

// Create post
if (createPost($db, $auth->getUserId(), $body, $url)) {
    setFlashMessage('success', 'Post created successfully!');
} else {
    setFlashMessage('error', 'Failed to create post');
}

redirectTo('/');
?>