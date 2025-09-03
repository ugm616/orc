package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"time"

	_ "modernc.org/sqlite"
)

type DB struct {
	*sql.DB
}

type Account struct {
	ID          string    `db:"id"`
	PassHash    string    `db:"pass_hash"`
	RecoverHash string    `db:"recover_hash"`
	DisplayName string    `db:"display_name"`
	CreatedAt   time.Time `db:"created_at"`
	IsAdmin     bool      `db:"is_admin"`
}

type Post struct {
	ID        int       `db:"id"`
	AuthorID  string    `db:"author_id"`
	Body      string    `db:"body"`
	URL       string    `db:"url"`
	CreatedAt time.Time `db:"created_at"`
	Author    string    `db:"author_name"` // Joined field
}

type Comment struct {
	ID        int       `db:"id"`
	PostID    int       `db:"post_id"`
	AuthorID  string    `db:"author_id"`
	ParentID  *int      `db:"parent_id"`
	Body      string    `db:"body"`
	CreatedAt time.Time `db:"created_at"`
	Author    string    `db:"author_name"` // Joined field
}

type RateLimit struct {
	Key         string    `db:"key"`
	Tokens      int       `db:"tokens"`
	RefreshedAt time.Time `db:"refreshed_at"`
}

type Setting struct {
	Key   string `db:"key"`
	Value string `db:"value"`
}

type Theme struct {
	Version int    `db:"version"`
	JSON    string `db:"json"`
}

func Init(dbPath string) (*DB, error) {
	// Ensure data directory exists
	dir := filepath.Dir(dbPath)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create data directory: %w", err)
	}

	db, err := sql.Open("sqlite", dbPath)
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	// Enable foreign keys and WAL mode for better performance
	if _, err := db.Exec("PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;"); err != nil {
		return nil, fmt.Errorf("failed to set pragmas: %w", err)
	}

	dbWrapper := &DB{db}
	if err := dbWrapper.createTables(); err != nil {
		return nil, fmt.Errorf("failed to create tables: %w", err)
	}

	return dbWrapper, nil
}

func (db *DB) createTables() error {
	schema := `
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
	`

	_, err := db.Exec(schema)
	return err
}