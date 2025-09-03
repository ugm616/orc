<?php
// Home page template for PHP implementation

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = getPageData('Home');
    
    if ($auth->isLoggedIn()) {
        // Get posts for logged-in users
        $data['posts'] = getPosts($db);
    }
    
    renderTemplate('home_content', $data);
}
?>