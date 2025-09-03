package handlers

import (
	"database/sql"
	"fmt"
	"html/template"
	"net/http"
	"path/filepath"
	"strings"
	"time"

	"github.com/ugm616/orc/internal/auth"
	"github.com/ugm616/orc/internal/database"
	"github.com/ugm616/orc/internal/security"
	"github.com/ugm616/orc/internal/theme"
)

type Handlers struct {
	db       *database.DB
	security *security.Security
	theme    *theme.Theme
}

type PageData struct {
	Title       string
	CSRFToken   string
	User        *database.Account
	Theme       *theme.Theme
	Posts       []database.Post
	Comments    []database.Comment
	Error       string
	Success     string
	IsAdmin     bool
}

func New(db *database.DB, sec *security.Security) *Handlers {
	h := &Handlers{
		db:       db,
		security: sec,
	}
	
	// Load theme
	var err error
	h.theme, err = theme.LoadTheme("./configs/theme.json")
	if err != nil {
		// Use default theme if loading fails
		h.theme = theme.GetDefaultTheme()
	}
	
	return h
}

// Home displays the main feed
func (h *Handlers) Home(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}
	
	user := h.getCurrentUser(r)
	
	data := &PageData{
		Title: "Home",
		User:  user,
		Theme: h.theme,
	}
	
	if user != nil {
		data.IsAdmin = user.IsAdmin
		
		// Get posts with author names
		rows, err := h.db.Query(`
			SELECT p.id, p.author_id, p.body, p.url, p.created_at, a.display_name
			FROM posts p
			JOIN accounts a ON p.author_id = a.id
			ORDER BY p.created_at DESC
			LIMIT 50
		`)
		if err != nil {
			data.Error = "Failed to load posts"
		} else {
			defer rows.Close()
			
			for rows.Next() {
				var post database.Post
				err := rows.Scan(&post.ID, &post.AuthorID, &post.Body, &post.URL, &post.CreatedAt, &post.Author)
				if err != nil {
					continue
				}
				data.Posts = append(data.Posts, post)
			}
		}
		
		// Get CSRF token for forms
		token, _ := h.security.GenerateCSRFToken()
		data.CSRFToken = token
		h.setCSRFToken(w, token)
	}
	
	h.renderTemplate(w, "home", data)
}

// Signup handles user registration
func (h *Handlers) Signup(w http.ResponseWriter, r *http.Request) {
	data := &PageData{
		Title: "Sign Up",
		Theme: h.theme,
	}
	
	if r.Method == "GET" {
		token, _ := h.security.GenerateCSRFToken()
		data.CSRFToken = token
		h.setCSRFToken(w, token)
		h.renderTemplate(w, "signup", data)
		return
	}
	
	// Handle POST
	if !h.security.CheckRateLimit("signup:"+r.RemoteAddr, 5, time.Minute) {
		data.Error = "Too many signup attempts. Please try again later."
		h.renderTemplate(w, "signup", data)
		return
	}
	
	if err := r.ParseForm(); err != nil {
		data.Error = "Invalid form data"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	// Validate CSRF token
	if !h.security.ValidateCSRFToken(h.getCSRFToken(r), r.FormValue("csrf_token")) {
		data.Error = "Invalid security token"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	displayName := strings.TrimSpace(r.FormValue("display_name"))
	password := r.FormValue("password")
	confirmPassword := r.FormValue("confirm_password")
	
	// Validate input
	if err := auth.ValidateDisplayName(displayName); err != nil {
		data.Error = err.Error()
		h.renderTemplate(w, "signup", data)
		return
	}
	
	if err := auth.ValidatePassword(password); err != nil {
		data.Error = err.Error()
		h.renderTemplate(w, "signup", data)
		return
	}
	
	if password != confirmPassword {
		data.Error = "Passwords do not match"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	// Generate account ID and recovery phrase
	accountID, err := auth.GenerateAccountID()
	if err != nil {
		data.Error = "Failed to generate account ID"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	recoveryPhrase, err := auth.GenerateRecoveryPhrase()
	if err != nil {
		data.Error = "Failed to generate recovery phrase"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	// Hash password and recovery phrase
	passHash, err := auth.HashPassword(password)
	if err != nil {
		data.Error = "Failed to hash password"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	recoverHash, err := auth.HashPassword(recoveryPhrase)
	if err != nil {
		data.Error = "Failed to hash recovery phrase"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	// Check if this is the first user (make them admin)
	var userCount int
	err = h.db.QueryRow("SELECT COUNT(*) FROM accounts").Scan(&userCount)
	if err != nil {
		data.Error = "Database error"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	isAdmin := userCount == 0
	
	// Create account
	_, err = h.db.Exec(`
		INSERT INTO accounts (id, pass_hash, recover_hash, display_name, is_admin)
		VALUES (?, ?, ?, ?, ?)
	`, accountID, passHash, recoverHash, displayName, isAdmin)
	
	if err != nil {
		data.Error = "Failed to create account"
		h.renderTemplate(w, "signup", data)
		return
	}
	
	// Set session
	h.setSession(w, accountID)
	
	// Show success with account details
	data.Success = fmt.Sprintf("Account created successfully! Your account ID is: %s. Your recovery phrase is: %s. Please save these securely!", accountID, recoveryPhrase)
	data.User = &database.Account{ID: accountID, DisplayName: displayName, IsAdmin: isAdmin}
	h.renderTemplate(w, "signup", data)
}

// Login handles user authentication
func (h *Handlers) Login(w http.ResponseWriter, r *http.Request) {
	data := &PageData{
		Title: "Login",
		Theme: h.theme,
	}
	
	if r.Method == "GET" {
		token, _ := h.security.GenerateCSRFToken()
		data.CSRFToken = token
		h.setCSRFToken(w, token)
		h.renderTemplate(w, "login", data)
		return
	}
	
	// Handle POST
	if !h.security.CheckRateLimit("login:"+r.RemoteAddr, 5, time.Minute) {
		data.Error = "Too many login attempts. Please try again later."
		h.renderTemplate(w, "login", data)
		return
	}
	
	if err := r.ParseForm(); err != nil {
		data.Error = "Invalid form data"
		h.renderTemplate(w, "login", data)
		return
	}
	
	// Validate CSRF token
	if !h.security.ValidateCSRFToken(h.getCSRFToken(r), r.FormValue("csrf_token")) {
		data.Error = "Invalid security token"
		h.renderTemplate(w, "login", data)
		return
	}
	
	accountID := strings.TrimSpace(r.FormValue("account_id"))
	password := r.FormValue("password")
	
	if !auth.ValidateAccountID(accountID) {
		data.Error = "Invalid account ID format"
		h.renderTemplate(w, "login", data)
		return
	}
	
	// Get user from database
	var user database.Account
	err := h.db.QueryRow(`
		SELECT id, pass_hash, display_name, is_admin
		FROM accounts WHERE id = ?
	`, accountID).Scan(&user.ID, &user.PassHash, &user.DisplayName, &user.IsAdmin)
	
	if err == sql.ErrNoRows {
		data.Error = "Invalid account ID or password"
		h.renderTemplate(w, "login", data)
		return
	} else if err != nil {
		data.Error = "Database error"
		h.renderTemplate(w, "login", data)
		return
	}
	
	// Verify password
	if !auth.VerifyPassword(password, user.PassHash) {
		data.Error = "Invalid account ID or password"
		h.renderTemplate(w, "login", data)
		return
	}
	
	// Set session
	h.setSession(w, user.ID)
	
	// Redirect to home
	http.Redirect(w, r, "/", http.StatusSeeOther)
}

// Logout handles user logout
func (h *Handlers) Logout(w http.ResponseWriter, r *http.Request) {
	h.clearSession(w)
	http.Redirect(w, r, "/", http.StatusSeeOther)
}

// Profile shows user profile
func (h *Handlers) Profile(w http.ResponseWriter, r *http.Request) {
	user := h.getCurrentUser(r)
	if user == nil {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}
	
	data := &PageData{
		Title:   "Profile",
		User:    user,
		Theme:   h.theme,
		IsAdmin: user.IsAdmin,
	}
	
	h.renderTemplate(w, "profile", data)
}

// CreatePost handles post creation
func (h *Handlers) CreatePost(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	user := h.getCurrentUser(r)
	if user == nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	
	if !h.security.CheckRateLimit("post:"+user.ID, 10, time.Minute) {
		http.Error(w, "Rate limit exceeded", http.StatusTooManyRequests)
		return
	}
	
	if err := r.ParseForm(); err != nil {
		http.Error(w, "Invalid form data", http.StatusBadRequest)
		return
	}
	
	// Validate CSRF token
	if !h.security.ValidateCSRFToken(h.getCSRFToken(r), r.FormValue("csrf_token")) {
		http.Error(w, "Invalid security token", http.StatusForbidden)
		return
	}
	
	body := strings.TrimSpace(r.FormValue("body"))
	urlStr := strings.TrimSpace(r.FormValue("url"))
	
	if len(body) == 0 {
		http.Error(w, "Post body is required", http.StatusBadRequest)
		return
	}
	
	if len(body) > 1000 {
		http.Error(w, "Post body too long", http.StatusBadRequest)
		return
	}
	
	// Validate URL if provided
	if urlStr != "" {
		if err := h.security.ValidateURL(urlStr); err != nil {
			http.Error(w, "Invalid URL: "+err.Error(), http.StatusBadRequest)
			return
		}
	}
	
	// Create post
	_, err := h.db.Exec(`
		INSERT INTO posts (author_id, body, url)
		VALUES (?, ?, ?)
	`, user.ID, h.security.EscapeHTML(body), urlStr)
	
	if err != nil {
		http.Error(w, "Failed to create post", http.StatusInternalServerError)
		return
	}
	
	http.Redirect(w, r, "/", http.StatusSeeOther)
}

// Helper functions for session management
func (h *Handlers) setSession(w http.ResponseWriter, userID string) {
	http.SetCookie(w, &http.Cookie{
		Name:     "session",
		Value:    userID,
		Path:     "/",
		HttpOnly: true,
		Secure:   false, // Set to true in production with HTTPS
		SameSite: http.SameSiteStrictMode,
		MaxAge:   86400 * 30, // 30 days
	})
}

func (h *Handlers) clearSession(w http.ResponseWriter) {
	http.SetCookie(w, &http.Cookie{
		Name:     "session",
		Value:    "",
		Path:     "/",
		HttpOnly: true,
		MaxAge:   -1,
	})
}

func (h *Handlers) getCurrentUser(r *http.Request) *database.Account {
	cookie, err := r.Cookie("session")
	if err != nil {
		return nil
	}
	
	var user database.Account
	err = h.db.QueryRow(`
		SELECT id, display_name, is_admin
		FROM accounts WHERE id = ?
	`, cookie.Value).Scan(&user.ID, &user.DisplayName, &user.IsAdmin)
	
	if err != nil {
		return nil
	}
	
	return &user
}

// CSRF token management
func (h *Handlers) setCSRFToken(w http.ResponseWriter, token string) {
	http.SetCookie(w, &http.Cookie{
		Name:     "csrf_token",
		Value:    token,
		Path:     "/",
		HttpOnly: false, // Need to be accessible to JavaScript
		Secure:   false,
		SameSite: http.SameSiteStrictMode,
		MaxAge:   3600, // 1 hour
	})
}

func (h *Handlers) getCSRFToken(r *http.Request) string {
	cookie, err := r.Cookie("csrf_token")
	if err != nil {
		return ""
	}
	return cookie.Value
}

// Template rendering
func (h *Handlers) renderTemplate(w http.ResponseWriter, name string, data *PageData) {
	tmplPath := filepath.Join("web", "templates", name+".html")
	
	tmpl, err := template.ParseFiles(tmplPath, "web/templates/base.html")
	if err != nil {
		http.Error(w, "Template error", http.StatusInternalServerError)
		return
	}
	
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := tmpl.ExecuteTemplate(w, "base", data); err != nil {
		http.Error(w, "Template execution error", http.StatusInternalServerError)
	}
}