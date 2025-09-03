<?php
// Link preview handler for PHP implementation

declare(strict_types=1);

requireMethod('POST');

if (!$auth->isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$url = sanitizeInput($_POST['url'] ?? '');

if (empty($url)) {
    jsonResponse(['error' => 'URL is required'], 400);
}

// Validate URL
$urlError = $security->validateUrl($url);
if ($urlError) {
    jsonResponse(['error' => 'Invalid URL: ' . $urlError], 400);
}

if (!$config->get('link_previews_enabled', true)) {
    jsonResponse(['error' => 'Link previews are disabled'], 400);
}

// Fetch preview
try {
    $preview = $security->fetchUrlPreview($url);
    jsonResponse($preview);
} catch (Exception $e) {
    error_log("Link preview failed: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch preview'], 500);
}
?>