<?php
// Security utilities for PHP implementation

declare(strict_types=1);

class Security {
    private array $rateLimits = [];
    
    public function setSecurityHeaders(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; connect-src \'self\'; font-src \'self\'; object-src \'none\'; media-src \'none\'; frame-src \'none\';');
        
        // Prevent caching of sensitive pages
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, 'admin') !== false || strpos($uri, 'profile') !== false) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    
    public function enforceTorOnly(): void {
        $config = Config::getInstance();
        $allowedIps = $config->get('allowed_ips', ['127.0.0.1', '::1']);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check if client IP is in allowed list
        if (!in_array($clientIp, $allowedIps)) {
            http_response_code(403);
            exit('Access denied. This site is only accessible via Tor.');
        }
        
        // Additional checks for Tor headers (if using Mode B with edge VPS)
        $xForwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $xRealIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
        
        if ($xForwardedFor && !in_array($xForwardedFor, $allowedIps)) {
            http_response_code(403);
            exit('Access denied. This site is only accessible via Tor.');
        }
        
        if ($xRealIp && !in_array($xRealIp, $allowedIps)) {
            http_response_code(403);
            exit('Access denied. This site is only accessible via Tor.');
        }
    }
    
    public function generateCSRFToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    public function validateCSRFToken(string $sessionToken, string $submittedToken): bool {
        if (empty($sessionToken) || empty($submittedToken)) {
            return false;
        }
        return hash_equals($sessionToken, $submittedToken);
    }
    
    public function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public function checkRateLimit(string $key, int $maxTokens, int $windowSeconds): bool {
        $now = time();
        
        // Clean up old entries
        foreach ($this->rateLimits as $k => $limit) {
            if ($now - $limit['refreshed_at'] > 3600) { // 1 hour cleanup
                unset($this->rateLimits[$k]);
            }
        }
        
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [
                'tokens' => $maxTokens - 1,
                'refreshed_at' => $now,
                'max_tokens' => $maxTokens,
                'window' => $windowSeconds
            ];
            return true;
        }
        
        $limit = &$this->rateLimits[$key];
        
        // Calculate tokens to add based on time elapsed
        $elapsed = $now - $limit['refreshed_at'];
        $tokensToAdd = intval($elapsed / $windowSeconds * $maxTokens);
        
        if ($tokensToAdd > 0) {
            $limit['tokens'] = min($limit['tokens'] + $tokensToAdd, $maxTokens);
            $limit['refreshed_at'] = $now;
        }
        
        if ($limit['tokens'] > 0) {
            $limit['tokens']--;
            return true;
        }
        
        return false;
    }
    
    public function validateUrl(string $url): ?string {
        // Parse URL
        $parsed = parse_url($url);
        if (!$parsed) {
            return 'Invalid URL format';
        }
        
        // Only allow HTTP and HTTPS
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return 'Only HTTP and HTTPS URLs are allowed';
        }
        
        // Get hostname
        $hostname = $parsed['host'] ?? '';
        if (empty($hostname)) {
            return 'Invalid hostname';
        }
        
        // Resolve hostname to IP
        $ips = gethostbynamel($hostname);
        if ($ips === false) {
            return 'Could not resolve hostname';
        }
        
        // Check for private IP ranges
        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return 'Private IP addresses are not allowed';
            }
        }
        
        return null; // Valid
    }
    
    private function isPrivateIp(string $ip): bool {
        // Check if IP is in private ranges
        $long = ip2long($ip);
        if ($long === false) {
            return true; // Invalid IP, treat as private
        }
        
        // Private ranges:
        // 10.0.0.0/8
        // 172.16.0.0/12  
        // 192.168.0.0/16
        // 127.0.0.0/8 (loopback)
        // 169.254.0.0/16 (link-local)
        
        return (
            ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) ||
            ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) ||
            ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) ||
            ($long >= ip2long('127.0.0.0') && $long <= ip2long('127.255.255.255')) ||
            ($long >= ip2long('169.254.0.0') && $long <= ip2long('169.254.255.255'))
        );
    }
    
    public function fetchUrlPreview(string $url): array {
        // Create context with timeout and user agent
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'OrcSocial/1.0',
                'max_redirects' => 3,
            ]
        ]);
        
        // Fetch content with size limit (1MB)
        $content = file_get_contents($url, false, $context, 0, 1024 * 1024);
        if ($content === false) {
            return ['error' => 'Failed to fetch URL'];
        }
        
        // Extract title
        $title = 'Link Preview';
        if (preg_match('/<title[^>]*>([^<]+)</title>/i', $content, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (strlen($title) > 100) {
                $title = substr($title, 0, 100) . '...';
            }
        }
        
        // Extract description from meta tag
        $description = '';
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $description = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (strlen($description) > 200) {
                $description = substr($description, 0, 200) . '...';
            }
        }
        
        return [
            'title' => $this->escapeHtml($title),
            'description' => $this->escapeHtml($description),
            'url' => $url
        ];
    }
}
?>