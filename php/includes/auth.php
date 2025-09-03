<?php
// Authentication system for PHP implementation

declare(strict_types=1);

class Auth {
    private Database $db;
    private ?array $currentUser = null;
    
    public function __construct(Database $db) {
        $this->db = $db;
        $this->loadCurrentUser();
    }
    
    private function loadCurrentUser(): void {
        if (isset($_SESSION['user_id'])) {
            $this->currentUser = $this->db->fetchOne(
                'SELECT id, display_name, is_admin, created_at FROM accounts WHERE id = ?',
                [$_SESSION['user_id']]
            );
        }
    }
    
    public function generateAccountId(): string {
        // Generate random 12-digit account ID
        return str_pad((string)random_int(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
    }
    
    public function generateRecoveryPhrase(): string {
        $words = [
            'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta',
            'iota', 'kappa', 'lambda', 'mu', 'nu', 'xi', 'omicron', 'pi',
            'rho', 'sigma', 'tau', 'upsilon', 'phi', 'chi', 'psi', 'omega',
            'storm', 'ocean', 'mountain', 'forest', 'river', 'stone', 'flame', 'wind',
            'shadow', 'light', 'moon', 'star', 'sun', 'cloud', 'rain', 'snow',
            'eagle', 'wolf', 'bear', 'fox', 'hawk', 'owl', 'deer', 'lion',
            'tiger', 'dragon', 'phoenix', 'crow', 'dove', 'swan', 'robin', 'falcon',
        ];
        
        $phrase = [];
        for ($i = 0; $i < 6; $i++) {
            $phrase[] = $words[array_rand($words)];
        }
        
        return implode(' ', $phrase);
    }
    
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 1,
            'threads' => 4,
        ]);
    }
    
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public function validateAccountId(string $accountId): bool {
        return preg_match('/^\d{12}$/', $accountId) === 1;
    }
    
    public function validatePassword(string $password): ?string {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters';
        }
        if (strlen($password) > 128) {
            return 'Password must be no more than 128 characters';
        }
        return null;
    }
    
    public function validateDisplayName(string $name): ?string {
        $name = trim($name);
        if (strlen($name) < 1) {
            return 'Display name is required';
        }
        if (strlen($name) > 50) {
            return 'Display name must be no more than 50 characters';
        }
        return null;
    }
    
    public function signup(string $displayName, string $password, string $confirmPassword): array {
        // Validate input
        if ($error = $this->validateDisplayName($displayName)) {
            return ['success' => false, 'error' => $error];
        }
        
        if ($error = $this->validatePassword($password)) {
            return ['success' => false, 'error' => $error];
        }
        
        if ($password !== $confirmPassword) {
            return ['success' => false, 'error' => 'Passwords do not match'];
        }
        
        // Generate account ID and recovery phrase
        $accountId = $this->generateAccountId();
        $recoveryPhrase = $this->generateRecoveryPhrase();
        
        // Hash password and recovery phrase
        $passHash = $this->hashPassword($password);
        $recoverHash = $this->hashPassword($recoveryPhrase);
        
        // Check if this is the first user (make them admin)
        $userCount = $this->db->fetchOne('SELECT COUNT(*) as count FROM accounts')['count'] ?? 0;
        $isAdmin = $userCount == 0;
        
        try {
            // Create account
            $this->db->execute(
                'INSERT INTO accounts (id, pass_hash, recover_hash, display_name, is_admin) VALUES (?, ?, ?, ?, ?)',
                [$accountId, $passHash, $recoverHash, trim($displayName), $isAdmin ? 1 : 0]
            );
            
            // Set session
            $_SESSION['user_id'] = $accountId;
            $this->loadCurrentUser();
            
            return [
                'success' => true,
                'account_id' => $accountId,
                'recovery_phrase' => $recoveryPhrase,
                'is_admin' => $isAdmin
            ];
        } catch (Exception $e) {
            error_log("Signup failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create account'];
        }
    }
    
    public function login(string $accountId, string $password): array {
        if (!$this->validateAccountId($accountId)) {
            return ['success' => false, 'error' => 'Invalid account ID format'];
        }
        
        // Get user from database
        $user = $this->db->fetchOne(
            'SELECT id, pass_hash, display_name, is_admin FROM accounts WHERE id = ?',
            [$accountId]
        );
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid account ID or password'];
        }
        
        // Verify password
        if (!$this->verifyPassword($password, $user['pass_hash'])) {
            return ['success' => false, 'error' => 'Invalid account ID or password'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $this->loadCurrentUser();
        
        return ['success' => true];
    }
    
    public function logout(): void {
        unset($_SESSION['user_id']);
        $this->currentUser = null;
        session_destroy();
    }
    
    public function isLoggedIn(): bool {
        return $this->currentUser !== null;
    }
    
    public function getCurrentUser(): ?array {
        return $this->currentUser;
    }
    
    public function getUserId(): ?string {
        return $this->currentUser['id'] ?? null;
    }
    
    public function getDisplayName(): ?string {
        return $this->currentUser['display_name'] ?? null;
    }
    
    public function isAdmin(): bool {
        return (bool)($this->currentUser['is_admin'] ?? false);
    }
    
    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
    
    public function requireAdmin(): void {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}
?>