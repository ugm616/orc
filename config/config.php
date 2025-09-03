<?php
// Configuration class for PHP implementation

declare(strict_types=1);

class Config {
    private static ?Config $instance = null;
    private array $config = [];
    
    private function __construct() {
        // Load configuration from environment or defaults
        $this->config = [
            // Database settings
            'db_type' => $_ENV['ORC_DB_TYPE'] ?? 'sqlite',
            'db_host' => $_ENV['ORC_DB_HOST'] ?? 'localhost',
            'db_name' => $_ENV['ORC_DB_NAME'] ?? 'orc_social',
            'db_user' => $_ENV['ORC_DB_USER'] ?? '',
            'db_pass' => $_ENV['ORC_DB_PASS'] ?? '',
            'db_path' => $_ENV['ORC_DB_PATH'] ?? __DIR__ . '/../data/orc.db',
            
            // Security settings
            'tor_only_mode' => filter_var($_ENV['ORC_TOR_ONLY'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'allowed_ips' => explode(',', $_ENV['ORC_ALLOWED_IPS'] ?? '127.0.0.1,::1'),
            'base_path' => $_ENV['ORC_BASE_PATH'] ?? '',
            
            // Site settings
            'site_name' => $_ENV['ORC_SITE_NAME'] ?? 'Orc Social',
            'site_description' => $_ENV['ORC_SITE_DESC'] ?? 'Privacy-First Social Network',
            'theme_path' => $_ENV['ORC_THEME_PATH'] ?? __DIR__ . '/theme.json',
            'max_post_length' => (int)($_ENV['ORC_MAX_POST'] ?? 1000),
            'max_comment_length' => (int)($_ENV['ORC_MAX_COMMENT'] ?? 500),
            'registration_enabled' => filter_var($_ENV['ORC_REGISTRATION'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'link_previews_enabled' => filter_var($_ENV['ORC_LINK_PREVIEWS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            
            // Performance settings
            'rate_limit_auth' => (int)($_ENV['ORC_RATE_AUTH'] ?? 5),
            'rate_limit_post' => (int)($_ENV['ORC_RATE_POST'] ?? 10),
            'rate_limit_preview' => (int)($_ENV['ORC_RATE_PREVIEW'] ?? 3),
        ];
        
        // Override with config file if it exists
        $configFile = __DIR__ . '/config.local.php';
        if (file_exists($configFile)) {
            $localConfig = include $configFile;
            if (is_array($localConfig)) {
                $this->config = array_merge($this->config, $localConfig);
            }
        }
    }
    
    public static function getInstance(): Config {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get(string $key, mixed $default = null): mixed {
        return $this->config[$key] ?? $default;
    }
    
    public function set(string $key, mixed $value): void {
        $this->config[$key] = $value;
    }
    
    public function all(): array {
        return $this->config;
    }
}
?>