package security

import (
	"crypto/rand"
	"encoding/hex"
	"html"
	"net"
	"net/http"
	"net/url"
	"strings"
	"sync"
	"time"
)

type Security struct {
	rateLimits map[string]*RateLimit
	mutex      sync.RWMutex
}

type RateLimit struct {
	Tokens      int
	RefreshedAt time.Time
	MaxTokens   int
	RefillRate  time.Duration
}

func New() *Security {
	s := &Security{
		rateLimits: make(map[string]*RateLimit),
	}
	
	// Clean up old rate limit entries every hour
	go s.cleanupRoutine()
	
	return s
}

// Middleware applies security headers and CSRF protection
func (s *Security) Middleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Security headers
		w.Header().Set("X-Content-Type-Options", "nosniff")
		w.Header().Set("X-Frame-Options", "DENY")
		w.Header().Set("X-XSS-Protection", "1; mode=block")
		w.Header().Set("Referrer-Policy", "no-referrer")
		w.Header().Set("Content-Security-Policy", 
			"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; media-src 'none'; frame-src 'none';")
		
		// Prevent caching of sensitive pages
		if strings.Contains(r.URL.Path, "admin") || strings.Contains(r.URL.Path, "profile") {
			w.Header().Set("Cache-Control", "no-cache, no-store, must-revalidate")
			w.Header().Set("Pragma", "no-cache")
			w.Header().Set("Expires", "0")
		}

		next.ServeHTTP(w, r)
	})
}

// GenerateCSRFToken creates a new CSRF token
func (s *Security) GenerateCSRFToken() (string, error) {
	bytes := make([]byte, 32)
	if _, err := rand.Read(bytes); err != nil {
		return "", err
	}
	return hex.EncodeToString(bytes), nil
}

// ValidateCSRFToken validates a CSRF token from session
func (s *Security) ValidateCSRFToken(sessionToken, submittedToken string) bool {
	if sessionToken == "" || submittedToken == "" {
		return false
	}
	return sessionToken == submittedToken
}

// EscapeHTML safely escapes HTML content
func (s *Security) EscapeHTML(text string) string {
	return html.EscapeString(text)
}

// CheckRateLimit implements token bucket rate limiting
func (s *Security) CheckRateLimit(key string, maxTokens int, refillRate time.Duration) bool {
	s.mutex.Lock()
	defer s.mutex.Unlock()
	
	now := time.Now()
	
	limit, exists := s.rateLimits[key]
	if !exists {
		limit = &RateLimit{
			Tokens:      maxTokens - 1,
			RefreshedAt: now,
			MaxTokens:   maxTokens,
			RefillRate:  refillRate,
		}
		s.rateLimits[key] = limit
		return true
	}
	
	// Refill tokens based on time elapsed
	elapsed := now.Sub(limit.RefreshedAt)
	tokensToAdd := int(elapsed / limit.RefillRate)
	
	if tokensToAdd > 0 {
		limit.Tokens += tokensToAdd
		if limit.Tokens > limit.MaxTokens {
			limit.Tokens = limit.MaxTokens
		}
		limit.RefreshedAt = now
	}
	
	if limit.Tokens > 0 {
		limit.Tokens--
		return true
	}
	
	return false
}

// ValidateURL checks if a URL is safe for link previews
func (s *Security) ValidateURL(urlStr string) error {
	u, err := url.Parse(urlStr)
	if err != nil {
		return err
	}
	
	// Only allow HTTP and HTTPS
	if u.Scheme != "http" && u.Scheme != "https" {
		return ErrInvalidScheme
	}
	
	// Resolve hostname to IP
	ips, err := net.LookupIP(u.Hostname())
	if err != nil {
		return err
	}
	
	// Block private IP ranges
	for _, ip := range ips {
		if s.isPrivateIP(ip) {
			return ErrPrivateIP
		}
	}
	
	return nil
}

// isPrivateIP checks if an IP is in a private range
func (s *Security) isPrivateIP(ip net.IP) bool {
	if ip.IsLoopback() || ip.IsLinkLocalUnicast() || ip.IsLinkLocalMulticast() {
		return true
	}
	
	privateRanges := []string{
		"10.0.0.0/8",
		"172.16.0.0/12",
		"192.168.0.0/16",
		"169.254.0.0/16",
		"fc00::/7",
	}
	
	for _, rangeStr := range privateRanges {
		_, cidr, _ := net.ParseCIDR(rangeStr)
		if cidr.Contains(ip) {
			return true
		}
	}
	
	return false
}

// cleanupRoutine removes old rate limit entries
func (s *Security) cleanupRoutine() {
	ticker := time.NewTicker(time.Hour)
	defer ticker.Stop()
	
	for range ticker.C {
		s.mutex.Lock()
		cutoff := time.Now().Add(-24 * time.Hour)
		
		for key, limit := range s.rateLimits {
			if limit.RefreshedAt.Before(cutoff) {
				delete(s.rateLimits, key)
			}
		}
		s.mutex.Unlock()
	}
}

// Common errors
var (
	ErrInvalidScheme = &SecurityError{"invalid URL scheme"}
	ErrPrivateIP     = &SecurityError{"private IP address not allowed"}
)

type SecurityError struct {
	Message string
}

func (e *SecurityError) Error() string {
	return e.Message
}