package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/ugm616/orc/internal/database"
	"github.com/ugm616/orc/internal/handlers"
	"github.com/ugm616/orc/internal/security"
)

func main() {
	// Initialize database
	db, err := database.Init("./data/orc.db")
	if err != nil {
		log.Fatal("Failed to initialize database:", err)
	}
	defer db.Close()

	// Initialize security middleware
	sec := security.New()

	// Initialize handlers
	h := handlers.New(db, sec)

	// Setup router
	mux := http.NewServeMux()
	
	// Static files
	mux.Handle("/static/", http.StripPrefix("/static/", http.FileServer(http.Dir("./web/static/"))))
	
	// Auth routes
	mux.HandleFunc("/", h.Home)
	mux.HandleFunc("/signup", h.Signup)
	mux.HandleFunc("/login", h.Login)
	mux.HandleFunc("/logout", h.Logout)
	mux.HandleFunc("/profile", h.Profile)
	
	// Post routes
	mux.HandleFunc("/post", h.CreatePost)
	mux.HandleFunc("/comment", h.CreateComment)
	mux.HandleFunc("/preview", h.LinkPreview)
	
	// Admin routes
	mux.HandleFunc("/admin", h.Admin)
	mux.HandleFunc("/admin/theme", h.AdminTheme)
	mux.HandleFunc("/admin/theme/preview", h.AdminThemePreview)
	mux.HandleFunc("/admin/theme/save", h.AdminThemeSave)
	mux.HandleFunc("/admin/theme/reset", h.AdminThemeReset)

	// Apply security middleware to all routes
	handler := sec.Middleware(mux)

	// Create server - IMPORTANT: Only bind to 127.0.0.1 for Tor-only access
	server := &http.Server{
		Addr:         "127.0.0.1:8080",
		Handler:      handler,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	// Graceful shutdown
	go func() {
		sigterm := make(chan os.Signal, 1)
		signal.Notify(sigterm, syscall.SIGINT, syscall.SIGTERM)
		<-sigterm

		log.Println("Shutting down server...")
		ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
		defer cancel()
		
		if err := server.Shutdown(ctx); err != nil {
			log.Printf("Server shutdown error: %v", err)
		}
	}()

	log.Printf("Orc Social server starting on %s (Tor-only access)", server.Addr)
	if err := server.ListenAndServe(); err != http.ErrServerClosed {
		log.Fatal("Server error:", err)
	}
}