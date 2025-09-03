package theme

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

type Theme struct {
	Colors struct {
		Primary    string `json:"primary"`
		Secondary  string `json:"secondary"`
		Background string `json:"background"`
		Surface    string `json:"surface"`
		Text       string `json:"text"`
		TextMuted  string `json:"textMuted"`
		Border     string `json:"border"`
		Success    string `json:"success"`
		Warning    string `json:"warning"`
		Error      string `json:"error"`
	} `json:"colors"`
	
	Typography struct {
		FontFamily string `json:"fontFamily"`
		FontSize   string `json:"fontSize"`
		LineHeight string `json:"lineHeight"`
	} `json:"typography"`
	
	Layout struct {
		MaxWidth    string `json:"maxWidth"`
		Spacing     string `json:"spacing"`
		BorderRadius string `json:"borderRadius"`
		ShadowBase  string `json:"shadowBase"`
		ShadowLg    string `json:"shadowLg"`
	} `json:"layout"`
	
	Header struct {
		ShowLogo      bool   `json:"showLogo"`
		LogoPath      string `json:"logoPath"`
		Title         string `json:"title"`
		Subtitle      string `json:"subtitle"`
		Layout        string `json:"layout"` // center, left, right
	} `json:"header"`
	
	Footer struct {
		Text  string   `json:"text"`
		Links []Link   `json:"links"`
	} `json:"footer"`
}

type Link struct {
	Text string `json:"text"`
	URL  string `json:"url"`
}

// GetDefaultTheme returns the default theme configuration
func GetDefaultTheme() *Theme {
	theme := &Theme{}
	
	// Default colors (dark theme)
	theme.Colors.Primary = "#6366f1"
	theme.Colors.Secondary = "#8b5cf6"
	theme.Colors.Background = "#0f172a"
	theme.Colors.Surface = "#1e293b"
	theme.Colors.Text = "#f8fafc"
	theme.Colors.TextMuted = "#94a3b8"
	theme.Colors.Border = "#334155"
	theme.Colors.Success = "#10b981"
	theme.Colors.Warning = "#f59e0b"
	theme.Colors.Error = "#ef4444"
	
	// Default typography
	theme.Typography.FontFamily = `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`
	theme.Typography.FontSize = "16px"
	theme.Typography.LineHeight = "1.6"
	
	// Default layout
	theme.Layout.MaxWidth = "800px"
	theme.Layout.Spacing = "1rem"
	theme.Layout.BorderRadius = "0.5rem"
	theme.Layout.ShadowBase = "0 1px 3px 0 rgba(0, 0, 0, 0.1)"
	theme.Layout.ShadowLg = "0 10px 15px -3px rgba(0, 0, 0, 0.1)"
	
	// Default header
	theme.Header.ShowLogo = false
	theme.Header.LogoPath = ""
	theme.Header.Title = "Orc Social"
	theme.Header.Subtitle = "Privacy-First Social Network"
	theme.Header.Layout = "center"
	
	// Default footer
	theme.Footer.Text = "Powered by Orc Social"
	theme.Footer.Links = []Link{
		{Text: "Privacy", URL: "/privacy"},
		{Text: "Terms", URL: "/terms"},
	}
	
	return theme
}

// LoadTheme loads theme from file or returns default
func LoadTheme(configPath string) (*Theme, error) {
	theme := GetDefaultTheme()
	
	if _, err := os.Stat(configPath); os.IsNotExist(err) {
		// Create default theme file
		if err := SaveTheme(theme, configPath); err != nil {
			return nil, fmt.Errorf("failed to create default theme: %w", err)
		}
		return theme, nil
	}
	
	data, err := os.ReadFile(configPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read theme file: %w", err)
	}
	
	if err := json.Unmarshal(data, theme); err != nil {
		return nil, fmt.Errorf("failed to parse theme file: %w", err)
	}
	
	return theme, nil
}

// SaveTheme saves theme to file
func SaveTheme(theme *Theme, configPath string) error {
	// Ensure directory exists
	dir := filepath.Dir(configPath)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return fmt.Errorf("failed to create config directory: %w", err)
	}
	
	data, err := json.MarshalIndent(theme, "", "  ")
	if err != nil {
		return fmt.Errorf("failed to marshal theme: %w", err)
	}
	
	if err := os.WriteFile(configPath, data, 0644); err != nil {
		return fmt.Errorf("failed to write theme file: %w", err)
	}
	
	return nil
}

// GenerateCSS generates CSS variables from theme
func (t *Theme) GenerateCSS() string {
	var css strings.Builder
	
	css.WriteString(":root {\n")
	
	// Colors
	css.WriteString(fmt.Sprintf("  --color-primary: %s;\n", t.Colors.Primary))
	css.WriteString(fmt.Sprintf("  --color-secondary: %s;\n", t.Colors.Secondary))
	css.WriteString(fmt.Sprintf("  --color-background: %s;\n", t.Colors.Background))
	css.WriteString(fmt.Sprintf("  --color-surface: %s;\n", t.Colors.Surface))
	css.WriteString(fmt.Sprintf("  --color-text: %s;\n", t.Colors.Text))
	css.WriteString(fmt.Sprintf("  --color-text-muted: %s;\n", t.Colors.TextMuted))
	css.WriteString(fmt.Sprintf("  --color-border: %s;\n", t.Colors.Border))
	css.WriteString(fmt.Sprintf("  --color-success: %s;\n", t.Colors.Success))
	css.WriteString(fmt.Sprintf("  --color-warning: %s;\n", t.Colors.Warning))
	css.WriteString(fmt.Sprintf("  --color-error: %s;\n", t.Colors.Error))
	
	// Typography
	css.WriteString(fmt.Sprintf("  --font-family: %s;\n", t.Typography.FontFamily))
	css.WriteString(fmt.Sprintf("  --font-size: %s;\n", t.Typography.FontSize))
	css.WriteString(fmt.Sprintf("  --line-height: %s;\n", t.Typography.LineHeight))
	
	// Layout
	css.WriteString(fmt.Sprintf("  --max-width: %s;\n", t.Layout.MaxWidth))
	css.WriteString(fmt.Sprintf("  --spacing: %s;\n", t.Layout.Spacing))
	css.WriteString(fmt.Sprintf("  --border-radius: %s;\n", t.Layout.BorderRadius))
	css.WriteString(fmt.Sprintf("  --shadow-base: %s;\n", t.Layout.ShadowBase))
	css.WriteString(fmt.Sprintf("  --shadow-lg: %s;\n", t.Layout.ShadowLg))
	
	css.WriteString("}\n")
	
	return css.String()
}

// ValidateTheme validates theme configuration
func ValidateTheme(theme *Theme) error {
	// Validate colors (basic hex color check)
	colors := []string{
		theme.Colors.Primary, theme.Colors.Secondary, theme.Colors.Background,
		theme.Colors.Surface, theme.Colors.Text, theme.Colors.TextMuted,
		theme.Colors.Border, theme.Colors.Success, theme.Colors.Warning, theme.Colors.Error,
	}
	
	for _, color := range colors {
		if !isValidColor(color) {
			return fmt.Errorf("invalid color: %s", color)
		}
	}
	
	// Validate header layout
	validLayouts := map[string]bool{"center": true, "left": true, "right": true}
	if !validLayouts[theme.Header.Layout] {
		return fmt.Errorf("invalid header layout: %s", theme.Header.Layout)
	}
	
	return nil
}

// isValidColor checks if a color is a valid hex color or CSS color name
func isValidColor(color string) bool {
	if color == "" {
		return false
	}
	
	// Check hex colors
	if strings.HasPrefix(color, "#") {
		if len(color) != 4 && len(color) != 7 {
			return false
		}
		for _, r := range color[1:] {
			if !((r >= '0' && r <= '9') || (r >= 'a' && r <= 'f') || (r >= 'A' && r <= 'F')) {
				return false
			}
		}
		return true
	}
	
	// Allow rgb() and rgba() functions
	if strings.HasPrefix(color, "rgb(") || strings.HasPrefix(color, "rgba(") {
		return true
	}
	
	// Allow common CSS color names
	cssColors := map[string]bool{
		"black": true, "white": true, "red": true, "green": true, "blue": true,
		"yellow": true, "cyan": true, "magenta": true, "gray": true, "grey": true,
		"transparent": true,
	}
	
	return cssColors[strings.ToLower(color)]
}