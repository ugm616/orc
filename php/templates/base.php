<?php
// Base template for PHP implementation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Orc Social') ?> - <?= htmlspecialchars($theme->header['title'] ?? 'Orc Social') ?></title>
    <link rel="stylesheet" href="/static/css/tailwind.base.css">
    <link rel="stylesheet" href="/static/css/theme-vars.css">
    <style>
        body {
            font-family: var(--font-family);
            font-size: var(--font-size);
            line-height: var(--line-height);
            background-color: var(--color-background);
            color: var(--color-text);
        }
        
        .container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: var(--spacing);
        }
        
        .card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            padding: var(--spacing);
            margin-bottom: var(--spacing);
            box-shadow: var(--shadow-base);
        }
        
        .btn {
            background-color: var(--color-primary);
            color: var(--color-background);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: var(--color-secondary);
        }
        
        .btn-danger {
            background-color: var(--color-error);
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-surface);
            color: var(--color-text);
            margin-bottom: 1rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        
        .text-muted { color: var(--color-text-muted); }
        .text-error { color: var(--color-error); }
        .text-success { color: var(--color-success); }
        
        .header {
            background-color: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .footer {
            background-color: var(--color-surface);
            border-top: 1px solid var(--color-border);
            padding: 1rem 0;
            margin-top: 2rem;
            text-align: center;
        }
        
        .nav-link {
            color: var(--color-text);
            text-decoration: none;
            margin-right: 1rem;
            padding: 0.5rem;
        }
        
        .nav-link:hover {
            color: var(--color-primary);
        }
        
        .post {
            border-left: 3px solid var(--color-primary);
            padding-left: 1rem;
        }
        
        .comment {
            border-left: 2px solid var(--color-secondary);
            padding-left: 0.75rem;
            margin-left: 1rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="flex justify-between items-center">
                <div class="<?= ($theme->header['layout'] ?? 'center') === 'center' ? 'text-center w-full' : (($theme->header['layout'] ?? 'center') === 'right' ? 'text-right w-full' : '') ?>">
                    <?php if ($theme->header['showLogo'] ?? false): ?>
                        <img src="<?= htmlspecialchars($theme->header['logoPath'] ?? '') ?>" alt="Logo" class="inline-block h-8 mr-2">
                    <?php endif; ?>
                    <h1 class="text-2xl font-bold inline-block"><?= htmlspecialchars($theme->header['title'] ?? 'Orc Social') ?></h1>
                    <?php if ($theme->header['subtitle'] ?? ''): ?>
                        <p class="text-muted"><?= htmlspecialchars($theme->header['subtitle']) ?></p>
                    <?php endif; ?>
                </div>
                
                <nav class="<?= ($theme->header['layout'] ?? 'center') === 'center' ? 'absolute right-4 top-4' : '' ?>">
                    <?php if ($auth->isLoggedIn()): ?>
                        <a href="/" class="nav-link">Home</a>
                        <a href="/profile" class="nav-link">Profile</a>
                        <?php if ($auth->isAdmin()): ?>
                            <a href="/admin" class="nav-link">Admin</a>
                        <?php endif; ?>
                        <a href="/logout" class="nav-link">Logout</a>
                        <span class="text-muted"><?= htmlspecialchars($auth->getDisplayName() ?? '') ?></span>
                    <?php else: ?>
                        <a href="/" class="nav-link">Home</a>
                        <a href="/login" class="nav-link">Login</a>
                        <a href="/signup" class="nav-link">Sign Up</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if ($error ?? null): ?>
            <div class="card text-error">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success ?? null): ?>
            <div class="card text-success">
                <strong>Success:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php
        // Include the specific page content
        $contentFile = __DIR__ . "/{$templateName}.php";
        if (file_exists($contentFile)) {
            include $contentFile;
        } else {
            echo '<div class="card"><h1>Page Not Found</h1><p>The requested page could not be found.</p></div>';
        }
        ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p class="text-muted"><?= htmlspecialchars($theme->footer['text'] ?? 'Powered by Orc Social') ?></p>
            <?php if ($theme->footer['links'] ?? []): ?>
                <div class="mt-2">
                    <?php foreach ($theme->footer['links'] as $link): ?>
                        <a href="<?= htmlspecialchars($link['url'] ?? '#') ?>" class="text-muted hover:text-primary mr-4"><?= htmlspecialchars($link['text'] ?? '') ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>

    <script src="/static/js/app.js"></script>
</body>
</html>