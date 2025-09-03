<?php
// Home content template for PHP implementation
?>
<?php if ($auth->isLoggedIn()): ?>
    <!-- Post Creation Form -->
    <div class="card">
        <h2 class="text-xl font-bold mb-4">Create Post</h2>
        <form action="/post" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <textarea name="body" placeholder="What's on your mind?" class="form-input" rows="4" maxlength="1000" required></textarea>
            
            <input type="url" name="url" placeholder="Optional: Add a link" class="form-input">
            
            <button type="submit" class="btn">Post</button>
            <span class="text-muted ml-4">Max 1000 characters</span>
        </form>
    </div>

    <!-- Posts Feed -->
    <?php if ($posts ?? []): ?>
        <?php foreach ($posts as $post): ?>
            <article class="card post">
                <div class="flex justify-between items-start mb-2">
                    <strong><?= htmlspecialchars($post['author_name']) ?></strong>
                    <span class="text-muted text-sm"><?= formatDateTime($post['created_at']) ?></span>
                </div>
                
                <div class="mb-3">
                    <?= htmlspecialchars($post['body']) ?>
                </div>
                
                <?php if ($post['url']): ?>
                    <div class="mb-3">
                        <a href="<?= htmlspecialchars($post['url']) ?>" target="_blank" class="text-primary hover:underline"><?= htmlspecialchars($post['url']) ?></a>
                        <button onclick="loadPreview('<?= htmlspecialchars($post['url']) ?>', <?= $post['id'] ?>)" class="btn-secondary ml-2 text-sm">Preview</button>
                        <div id="preview-<?= $post['id'] ?>" class="mt-2 hidden"></div>
                    </div>
                <?php endif; ?>
                
                <!-- Comment Form -->
                <details class="mt-4">
                    <summary class="cursor-pointer text-primary">Add Comment</summary>
                    <form action="/comment" method="post" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        
                        <textarea name="body" placeholder="Write a comment..." class="form-input" rows="2" maxlength="500" required></textarea>
                        
                        <button type="submit" class="btn">Comment</button>
                        <span class="text-muted ml-4">Max 500 characters</span>
                    </form>
                </details>
                
                <!-- Comments -->
                <?php 
                $comments = getComments($db, $post['id']);
                $commentTree = buildCommentTree($comments);
                if ($commentTree):
                ?>
                    <div class="mt-4">
                        <?php renderComments($commentTree); ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card text-center text-muted">
            <p>No posts yet. Be the first to share something!</p>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Welcome page for non-logged in users -->
    <div class="card text-center">
        <h1 class="text-3xl font-bold mb-4">Welcome to <?= htmlspecialchars($theme->header['title'] ?? 'Orc Social') ?></h1>
        <p class="text-lg text-muted mb-6"><?= htmlspecialchars($theme->header['subtitle'] ?? 'Privacy-First Social Network') ?></p>
        
        <div class="space-x-4">
            <a href="/signup" class="btn">Join Now</a>
            <a href="/login" class="btn-secondary">Login</a>
        </div>
        
        <div class="mt-8 text-left">
            <h2 class="text-xl font-bold mb-4">Privacy-First Social Networking</h2>
            <ul class="space-y-2 text-muted">
                <li>✓ Tor-only access for maximum privacy</li>
                <li>✓ No tracking or data collection</li>
                <li>✓ Anonymous account IDs</li>
                <li>✓ End-to-end encryption ready</li>
                <li>✓ Self-hosted and decentralized</li>
            </ul>
        </div>
    </div>
<?php endif; ?>