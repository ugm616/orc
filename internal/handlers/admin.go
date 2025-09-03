package handlers

import (
	"encoding/json"
	"net/http"
	"os"

	"github.com/ugm616/orc/internal/theme"
)

// Admin shows admin dashboard
func (h *Handlers) Admin(w http.ResponseWriter, r *http.Request) {
	user := h.getCurrentUser(r)
	if user == nil || !user.IsAdmin {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return
	}
	
	data := &PageData{
		Title:   "Admin Dashboard",
		User:    user,
		Theme:   h.theme,
		IsAdmin: true,
	}
	
	h.renderTemplate(w, "admin", data)
}

// AdminTheme shows theme customization interface
func (h *Handlers) AdminTheme(w http.ResponseWriter, r *http.Request) {
	user := h.getCurrentUser(r)
	if user == nil || !user.IsAdmin {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return
	}
	
	data := &PageData{
		Title:   "Theme Customization",
		User:    user,
		Theme:   h.theme,
		IsAdmin: true,
	}
	
	// Generate CSRF token
	token, _ := h.security.GenerateCSRFToken()
	data.CSRFToken = token
	h.setCSRFToken(w, token)
	
	h.renderTemplate(w, "admin/theme", data)
}

// AdminThemePreview generates live theme preview
func (h *Handlers) AdminThemePreview(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	user := h.getCurrentUser(r)
	if user == nil || !user.IsAdmin {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return
	}
	
	if err := r.ParseForm(); err != nil {
		http.Error(w, "Invalid form data", http.StatusBadRequest)
		return
	}
	
	// Parse theme data from form
	themeData := &theme.Theme{}
	
	// Colors
	themeData.Colors.Primary = r.FormValue("primary")
	themeData.Colors.Secondary = r.FormValue("secondary")
	themeData.Colors.Background = r.FormValue("background")
	themeData.Colors.Surface = r.FormValue("surface")
	themeData.Colors.Text = r.FormValue("text")
	themeData.Colors.TextMuted = r.FormValue("textMuted")
	themeData.Colors.Border = r.FormValue("border")
	themeData.Colors.Success = r.FormValue("success")
	themeData.Colors.Warning = r.FormValue("warning")
	themeData.Colors.Error = r.FormValue("error")
	
	// Typography
	themeData.Typography.FontFamily = r.FormValue("fontFamily")
	themeData.Typography.FontSize = r.FormValue("fontSize")
	themeData.Typography.LineHeight = r.FormValue("lineHeight")
	
	// Layout
	themeData.Layout.MaxWidth = r.FormValue("maxWidth")
	themeData.Layout.Spacing = r.FormValue("spacing")
	themeData.Layout.BorderRadius = r.FormValue("borderRadius")
	themeData.Layout.ShadowBase = r.FormValue("shadowBase")
	themeData.Layout.ShadowLg = r.FormValue("shadowLg")
	
	// Header
	themeData.Header.ShowLogo = r.FormValue("showLogo") == "true"
	themeData.Header.LogoPath = r.FormValue("logoPath")
	themeData.Header.Title = r.FormValue("title")
	themeData.Header.Subtitle = r.FormValue("subtitle")
	themeData.Header.Layout = r.FormValue("headerLayout")
	
	// Footer
	themeData.Footer.Text = r.FormValue("footerText")
	
	// Validate theme
	if err := theme.ValidateTheme(themeData); err != nil {
		http.Error(w, "Invalid theme: "+err.Error(), http.StatusBadRequest)
		return
	}
	
	// Generate CSS
	css := themeData.GenerateCSS()
	
	response := map[string]string{
		"css":    css,
		"status": "success",
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

// AdminThemeSave saves theme configuration
func (h *Handlers) AdminThemeSave(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	user := h.getCurrentUser(r)
	if user == nil || !user.IsAdmin {
		http.Error(w, "Forbidden", http.StatusForbidden)
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
	
	// Parse theme data from form (same as preview)
	themeData := &theme.Theme{}
	
	// Colors
	themeData.Colors.Primary = r.FormValue("primary")
	themeData.Colors.Secondary = r.FormValue("secondary")
	themeData.Colors.Background = r.FormValue("background")
	themeData.Colors.Surface = r.FormValue("surface")
	themeData.Colors.Text = r.FormValue("text")
	themeData.Colors.TextMuted = r.FormValue("textMuted")
	themeData.Colors.Border = r.FormValue("border")
	themeData.Colors.Success = r.FormValue("success")
	themeData.Colors.Warning = r.FormValue("warning")
	themeData.Colors.Error = r.FormValue("error")
	
	// Typography
	themeData.Typography.FontFamily = r.FormValue("fontFamily")
	themeData.Typography.FontSize = r.FormValue("fontSize")
	themeData.Typography.LineHeight = r.FormValue("lineHeight")
	
	// Layout
	themeData.Layout.MaxWidth = r.FormValue("maxWidth")
	themeData.Layout.Spacing = r.FormValue("spacing")
	themeData.Layout.BorderRadius = r.FormValue("borderRadius")
	themeData.Layout.ShadowBase = r.FormValue("shadowBase")
	themeData.Layout.ShadowLg = r.FormValue("shadowLg")
	
	// Header
	themeData.Header.ShowLogo = r.FormValue("showLogo") == "true"
	themeData.Header.LogoPath = r.FormValue("logoPath")
	themeData.Header.Title = r.FormValue("title")
	themeData.Header.Subtitle = r.FormValue("subtitle")
	themeData.Header.Layout = r.FormValue("headerLayout")
	
	// Footer
	themeData.Footer.Text = r.FormValue("footerText")
	
	// Parse footer links
	footerLinks := r.Form["footerLinks"]
	for i := 0; i < len(footerLinks); i += 2 {
		if i+1 < len(footerLinks) {
			link := theme.Link{
				Text: footerLinks[i],
				URL:  footerLinks[i+1],
			}
			themeData.Footer.Links = append(themeData.Footer.Links, link)
		}
	}
	
	// Validate theme
	if err := theme.ValidateTheme(themeData); err != nil {
		http.Error(w, "Invalid theme: "+err.Error(), http.StatusBadRequest)
		return
	}
	
	// Save theme to file
	if err := theme.SaveTheme(themeData, "./configs/theme.json"); err != nil {
		http.Error(w, "Failed to save theme: "+err.Error(), http.StatusInternalServerError)
		return
	}
	
	// Generate and save CSS file
	css := themeData.GenerateCSS()
	if err := os.WriteFile("./web/static/css/theme-vars.css", []byte(css), 0644); err != nil {
		http.Error(w, "Failed to save CSS: "+err.Error(), http.StatusInternalServerError)
		return
	}
	
	// Update in-memory theme
	h.theme = themeData
	
	response := map[string]string{
		"status":  "success",
		"message": "Theme saved successfully!",
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

// AdminThemeReset resets theme to defaults
func (h *Handlers) AdminThemeReset(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	
	user := h.getCurrentUser(r)
	if user == nil || !user.IsAdmin {
		http.Error(w, "Forbidden", http.StatusForbidden)
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
	
	// Get default theme
	defaultTheme := theme.GetDefaultTheme()
	
	// Save default theme
	if err := theme.SaveTheme(defaultTheme, "./configs/theme.json"); err != nil {
		http.Error(w, "Failed to reset theme: "+err.Error(), http.StatusInternalServerError)
		return
	}
	
	// Generate and save default CSS
	css := defaultTheme.GenerateCSS()
	if err := os.WriteFile("./web/static/css/theme-vars.css", []byte(css), 0644); err != nil {
		http.Error(w, "Failed to save CSS: "+err.Error(), http.StatusInternalServerError)
		return
	}
	
	// Update in-memory theme
	h.theme = defaultTheme
	
	response := map[string]interface{}{
		"status":  "success",
		"message": "Theme reset to defaults!",
		"theme":   defaultTheme,
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}