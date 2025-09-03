package handlers

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// CreateComment handles comment creation
func (h *Handlers) CreateComment(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	user := h.getCurrentUser(r)
	if user == nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	
	if !h.security.CheckRateLimit("comment:"+user.ID, 20, time.Minute) {
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
	
	postIDStr := r.FormValue("post_id")
	parentIDStr := r.FormValue("parent_id")
	body := strings.TrimSpace(r.FormValue("body"))
	
	postID, err := strconv.Atoi(postIDStr)
	if err != nil {
		http.Error(w, "Invalid post ID", http.StatusBadRequest)
		return
	}
	
	var parentID *int
	if parentIDStr != "" {
		pid, err := strconv.Atoi(parentIDStr)
		if err != nil {
			http.Error(w, "Invalid parent ID", http.StatusBadRequest)
			return
		}
		parentID = &pid
	}
	
	if len(body) == 0 {
		http.Error(w, "Comment body is required", http.StatusBadRequest)
		return
	}
	
	if len(body) > 500 {
		http.Error(w, "Comment body too long", http.StatusBadRequest)
		return
	}
	
	// Verify post exists
	var exists bool
	err = h.db.QueryRow("SELECT EXISTS(SELECT 1 FROM posts WHERE id = ?)", postID).Scan(&exists)
	if err != nil || !exists {
		http.Error(w, "Post not found", http.StatusNotFound)
		return
	}
	
	// Verify parent comment exists if specified
	if parentID != nil {
		err = h.db.QueryRow("SELECT EXISTS(SELECT 1 FROM comments WHERE id = ? AND post_id = ?)", *parentID, postID).Scan(&exists)
		if err != nil || !exists {
			http.Error(w, "Parent comment not found", http.StatusNotFound)
			return
		}
	}
	
	// Create comment
	_, err = h.db.Exec(`
		INSERT INTO comments (post_id, author_id, parent_id, body)
		VALUES (?, ?, ?, ?)
	`, postID, user.ID, parentID, h.security.EscapeHTML(body))
	
	if err != nil {
		http.Error(w, "Failed to create comment", http.StatusInternalServerError)
		return
	}
	
	http.Redirect(w, r, "/", http.StatusSeeOther)
}

// LinkPreview generates safe link previews
func (h *Handlers) LinkPreview(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	user := h.getCurrentUser(r)
	if user == nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	
	if !h.security.CheckRateLimit("preview:"+user.ID, 3, time.Minute) {
		http.Error(w, "Rate limit exceeded", http.StatusTooManyRequests)
		return
	}
	
	if err := r.ParseForm(); err != nil {
		http.Error(w, "Invalid form data", http.StatusBadRequest)
		return
	}
	
	urlStr := strings.TrimSpace(r.FormValue("url"))
	if urlStr == "" {
		http.Error(w, "URL is required", http.StatusBadRequest)
		return
	}
	
	// Validate URL
	if err := h.security.ValidateURL(urlStr); err != nil {
		http.Error(w, "Invalid URL: "+err.Error(), http.StatusBadRequest)
		return
	}
	
	// Create HTTP client with timeout and size limit
	client := &http.Client{
		Timeout: 10 * time.Second,
	}
	
	req, err := http.NewRequest("GET", urlStr, nil)
	if err != nil {
		http.Error(w, "Failed to create request", http.StatusInternalServerError)
		return
	}
	
	// Set user agent
	req.Header.Set("User-Agent", "OrcSocial/1.0")
	
	resp, err := client.Do(req)
	if err != nil {
		http.Error(w, "Failed to fetch URL", http.StatusInternalServerError)
		return
	}
	defer resp.Body.Close()
	
	if resp.StatusCode != 200 {
		http.Error(w, fmt.Sprintf("HTTP %d", resp.StatusCode), http.StatusBadRequest)
		return
	}
	
	// Limit response size to 1MB
	limitedReader := io.LimitReader(resp.Body, 1024*1024)
	body, err := io.ReadAll(limitedReader)
	if err != nil {
		http.Error(w, "Failed to read response", http.StatusInternalServerError)
		return
	}
	
	bodyStr := string(body)
	
	// Extract title (simple approach)
	title := "Link Preview"
	if start := strings.Index(bodyStr, "<title>"); start != -1 {
		start += 7
		if end := strings.Index(bodyStr[start:], "</title>"); end != -1 {
			title = strings.TrimSpace(bodyStr[start : start+end])
			if len(title) > 100 {
				title = title[:100] + "..."
			}
		}
	}
	
	// Extract description from meta tag
	description := ""
	if start := strings.Index(bodyStr, `name="description"`); start != -1 {
		if contentStart := strings.Index(bodyStr[start:], `content="`); contentStart != -1 {
			contentStart = start + contentStart + 9
			if contentEnd := strings.Index(bodyStr[contentStart:], `"`); contentEnd != -1 {
				description = strings.TrimSpace(bodyStr[contentStart : contentStart+contentEnd])
				if len(description) > 200 {
					description = description[:200] + "..."
				}
			}
		}
	}
	
	preview := map[string]string{
		"title":       h.security.EscapeHTML(title),
		"description": h.security.EscapeHTML(description),
		"url":         urlStr,
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(preview)
}