<?php
// Database abstraction layer for PHP implementation

declare(strict_types=1);

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;
    private Config $config;
    
    private function __construct() {
        $this->config = Config::getInstance();
        $this->connect();
        $this->createTables();
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect(): void {
        $dbType = $this->config->get('db_type');
        
        try {
            if ($dbType === 'sqlite') {
                $dbPath = $this->config->get('db_path');
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                $this->pdo = new PDO("sqlite:$dbPath");
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            } elseif ($dbType === 'mysql') {
                $host = $this->config->get('db_host');
                $dbname = $this->config->get('db_name');
                $user = $this->config->get('db_user');
                $pass = $this->config->get('db_pass');
                
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $this->pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } else {
                throw new Exception("Unsupported database type: $dbType");
            }
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    private function createTables(): void {
        $dbType = $this->config->get('db_type');
        
        if ($dbType === 'sqlite') {
            $sql = $this->getSQLiteSchema();
        } else {
            $sql = $this->getMySQLSchema();
        }
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create tables: " . $e->getMessage());
            throw new Exception("Failed to initialize database");
        }
    }
    
    private function getSQLiteSchema(): string {
        return "
            CREATE TABLE IF NOT EXISTS accounts (
                id TEXT PRIMARY KEY,
                pass_hash TEXT NOT NULL,
                recover_hash TEXT NOT NULL,
                display_name TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_admin BOOLEAN DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_id TEXT NOT NULL,
                body TEXT NOT NULL CHECK(length(body) <= 1000),
                url TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                author_id TEXT NOT NULL,
                parent_id INTEGER,
                body TEXT NOT NULL CHECK(length(body) <= 500),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS rate_limits (
                key TEXT PRIMARY KEY,
                tokens INTEGER NOT NULL,
                refreshed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS theme (
                version INTEGER PRIMARY KEY DEFAULT 1,
                json TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_posts_created_at ON posts(created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id);
            CREATE INDEX IF NOT EXISTS idx_comments_parent_id ON comments(parent_id);
        ";
    }
    
    private function getMySQLSchema(): string {
        return "
            CREATE TABLE IF NOT EXISTS accounts (
                id VARCHAR(12) PRIMARY KEY,
                pass_hash VARCHAR(255) NOT NULL,
                recover_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_admin BOOLEAN DEFAULT FALSE,
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                author_id VARCHAR(12) NOT NULL,
                body TEXT NOT NULL,
                url VARCHAR(2048) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE CASCADE,
                INDEX idx_created_at (created_at DESC),
                INDEX idx_author (author_id),
                CONSTRAINT chk_body_length CHECK (CHAR_LENGTH(body) <= 1000)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                author_id VARCHAR(12) NOT NULL,
                parent_id INT,
                body TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES accounts(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
                INDEX idx_post_id (post_id),
                INDEX idx_parent_id (parent_id),
                INDEX idx_created_at (created_at),
                CONSTRAINT chk_comment_body_length CHECK (CHAR_LENGTH(body) <= 500)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS rate_limits (
                key VARCHAR(255) PRIMARY KEY,
                tokens INT NOT NULL,
                refreshed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS settings (
                key VARCHAR(255) PRIMARY KEY,
                value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS theme (
                version INT PRIMARY KEY DEFAULT 1,
                json TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
    
    public function getPDO(): PDO {
        return $this->pdo;
    }
    
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }
}
?>