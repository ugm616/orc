<?php
// Utility functions for PHP implementation

declare(strict_types=1);

function formatTimeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

function formatDateTime(string $datetime): string {
    return date('M j, Y g:i A', strtotime($datetime));
}

function truncateText(string $text, int $length = 100): string {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . '...';
}

function sanitizeInput(string $input): string {
    return trim(strip_tags($input));
}

function isValidUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function renderTemplate(string $templateName, array $data = []): void {
    global $auth, $security, $theme, $config;
    
    // Make global variables available to templates
    $data['auth'] = $auth;
    $data['security'] = $security;
    $data['theme'] = $theme;
    $data['config'] = $config;
    $data['csrf_token'] = $_SESSION['csrf_token'] ?? '';
    
    // Extract data array to variables
    extract($data);
    
    // Include base template
    include __DIR__ . '/../templates/base.php';
}

function getPageData(string $title, array $extra = []): array {
    global $auth, $theme;
    
    $data = [
        'title' => $title,
        'user' => $auth->getCurrentUser(),
        'theme' => $theme,
        'is_admin' => $auth->isAdmin(),
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
        'error' => $_SESSION['error'] ?? null,
        'success' => $_SESSION['success'] ?? null,
    ];
    
    // Clear flash messages
    unset($_SESSION['error'], $_SESSION['success']);
    
    return array_merge($data, $extra);
}

function setFlashMessage(string $type, string $message): void {
    $_SESSION[$type] = $message;
}

function redirectTo(string $path, int $code = 302): void {
    header("Location: {$path}", true, $code);
    exit;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function validateCSRF(): bool {
    global $security;
    
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    
    return $security->validateCSRFToken($sessionToken, $submittedToken);
}

function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

function checkPostRateLimit(Database $db, string $userId): bool {
    global $security;
    return $security->checkRateLimit("post:{$userId}", 10, 60);
}

function checkCommentRateLimit(Database $db, string $userId): bool {
    global $security;
    return $security->checkRateLimit("comment:{$userId}", 20, 60);
}

function createPost(Database $db, string $authorId, string $body, string $url = ''): bool {
    try {
        return $db->execute(
            'INSERT INTO posts (author_id, body, url) VALUES (?, ?, ?)',
            [$authorId, $body, $url]
        );
    } catch (Exception $e) {
        error_log("Failed to create post: " . $e->getMessage());
        return false;
    }
}

function createComment(Database $db, string $authorId, int $postId, string $body, ?int $parentId = null): bool {
    try {
        return $db->execute(
            'INSERT INTO comments (author_id, post_id, body, parent_id) VALUES (?, ?, ?, ?)',
            [$authorId, $postId, $body, $parentId]
        );
    } catch (Exception $e) {
        error_log("Failed to create comment: " . $e->getMessage());
        return false;
    }
}

function getPosts(Database $db, int $limit = 50): array {
    return $db->fetchAll(
        'SELECT p.id, p.author_id, p.body, p.url, p.created_at, a.display_name as author_name
         FROM posts p
         JOIN accounts a ON p.author_id = a.id
         ORDER BY p.created_at DESC
         LIMIT ?',
        [$limit]
    );
}

function getComments(Database $db, int $postId): array {
    return $db->fetchAll(
        'SELECT c.id, c.post_id, c.author_id, c.parent_id, c.body, c.created_at, a.display_name as author_name
         FROM comments c
         JOIN accounts a ON c.author_id = a.id
         WHERE c.post_id = ?
         ORDER BY c.created_at ASC',
        [$postId]
    );
}

function buildCommentTree(array $comments): array {
    $tree = [];
    $lookup = [];
    
    // First pass: create lookup table
    foreach ($comments as $comment) {
        $comment['replies'] = [];
        $lookup[$comment['id']] = $comment;
    }
    
    // Second pass: build tree
    foreach ($lookup as $comment) {
        if ($comment['parent_id']) {
            if (isset($lookup[$comment['parent_id']])) {
                $lookup[$comment['parent_id']]['replies'][] = &$lookup[$comment['id']];
            }
        } else {
            $tree[] = &$lookup[$comment['id']];
        }
    }
    
    return $tree;
}

function renderComments(array $comments, int $depth = 0): void {
    foreach ($comments as $comment): ?>
        <div class="comment" style="margin-left: <?= $depth * 1 ?>rem;">
            <div class="flex justify-between items-start mb-2">
                <strong><?= htmlspecialchars($comment['author_name']) ?></strong>
                <span class="text-muted text-sm"><?= formatTimeAgo($comment['created_at']) ?></span>
            </div>
            <div class="mb-3"><?= htmlspecialchars($comment['body']) ?></div>
            
            <?php if (!empty($comment['replies'])): ?>
                <?php renderComments($comment['replies'], $depth + 1); ?>
            <?php endif; ?>
        </div>
    <?php endforeach;
}
?>