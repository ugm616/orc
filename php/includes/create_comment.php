<?php
// Create comment handler for PHP implementation

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

$postId = (int)($_POST['post_id'] ?? 0);
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$body = sanitizeInput($_POST['body'] ?? '');

// Validate input
if (empty($body)) {
    setFlashMessage('error', 'Comment body is required');
    redirectTo('/');
}

if (strlen($body) > $config->get('max_comment_length', 500)) {
    setFlashMessage('error', 'Comment body too long');
    redirectTo('/');
}

// Verify post exists
$post = $db->fetchOne('SELECT id FROM posts WHERE id = ?', [$postId]);
if (!$post) {
    setFlashMessage('error', 'Post not found');
    redirectTo('/');
}

// Verify parent comment exists if specified
if ($parentId) {
    $parentComment = $db->fetchOne('SELECT id FROM comments WHERE id = ? AND post_id = ?', [$parentId, $postId]);
    if (!$parentComment) {
        setFlashMessage('error', 'Parent comment not found');
        redirectTo('/');
    }
}

// Create comment
if (createComment($db, $auth->getUserId(), $postId, $body, $parentId)) {
    setFlashMessage('success', 'Comment created successfully!');
} else {
    setFlashMessage('error', 'Failed to create comment');
}

redirectTo('/');
?>