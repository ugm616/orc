# Security Configuration Guide for Orc Social

## Overview

This document outlines security best practices and configuration guidelines for deploying Orc Social in a production environment. Following these guidelines ensures maximum privacy and security for your users.

## Core Security Principles

### 1. Tor-Only Access
- **NEVER** bind to `0.0.0.0` or public interfaces
- Always use `127.0.0.1` or Unix sockets
- Configure firewalls to block clearnet access
- Regularly verify Tor-only accessibility

### 2. Data Minimization
- Store only essential user data
- Use anonymous account IDs instead of usernames
- Avoid logging IP addresses or user agents
- Implement automatic data cleanup policies

### 3. Defense in Depth
- Multiple layers of security controls
- Rate limiting at application and network level
- Input validation and output encoding
- Regular security audits and updates

## Network Security

### Tor Configuration

#### Recommended torrc Settings
```
# Basic hidden service
HiddenServiceDir /var/lib/tor/orc-social/
HiddenServicePort 443 127.0.0.1:8080
HiddenServiceVersion 3

# Security enhancements
HiddenServiceSingleHopMode 0
HiddenServiceNonAnonymousMode 0
DisableDebuggerAttachment 1
SafeLogging 1

# Performance tuning
CircuitBuildTimeout 30
KeepalivePeriod 60
NewCircuitPeriod 30
MaxCircuitDirtiness 600

# Disable exit functionality
ExitPolicy reject *:*

# Client authorization (optional but recommended)
# HiddenServiceAuthorizeClient stealth client1,client2
```

#### Firewall Configuration
```bash
# UFW Configuration
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from 127.0.0.1 to 127.0.0.1 port 8080
sudo ufw enable

# iptables Configuration (alternative)
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT
iptables -A INPUT -i lo -j ACCEPT
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -A INPUT -p tcp --dport 22 -j ACCEPT  # SSH only if needed
```

### Network Monitoring

#### Connection Monitoring
```bash
# Monitor active connections
netstat -tlnp | grep :8080
ss -tlnp | grep :8080

# Check for unexpected bindings
lsof -i :8080
```

#### Traffic Analysis
```bash
# Monitor Tor traffic
sudo journalctl -u tor -f | grep -E "(connection|circuit)"

# Check for clearnet leaks
sudo tcpdump -i any port 80 or port 443 | grep -v 127.0.0.1
```

## Application Security

### Authentication Security

#### Password Policy
- Minimum 8 characters (enforced in code)
- Maximum 128 characters to prevent DoS
- Argon2id hashing with proper parameters
- No password hints or recovery questions

#### Session Management
```php
// PHP session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');    // Enable with HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', 86400); // 24 hours
```

#### Account Security
- Random 12-digit account IDs
- Recovery phrases with BIP39-style words
- No email or phone number requirements
- Optional account deletion

### Input Validation

#### Content Validation
```go
// Post content limits
const (
    MaxPostLength    = 1000
    MaxCommentLength = 500
    MaxURLLength     = 2048
)

// Validation functions
func validatePost(content string) error {
    if len(content) == 0 {
        return errors.New("content cannot be empty")
    }
    if len(content) > MaxPostLength {
        return errors.New("content too long")
    }
    return nil
}
```

#### URL Validation
```go
func validateURL(url string) error {
    parsed, err := url.Parse(url)
    if err != nil {
        return err
    }
    
    // Only allow HTTP/HTTPS
    if parsed.Scheme != "http" && parsed.Scheme != "https" {
        return errors.New("invalid scheme")
    }
    
    // Block private IPs
    ips, err := net.LookupIP(parsed.Hostname())
    if err != nil {
        return err
    }
    
    for _, ip := range ips {
        if isPrivateIP(ip) {
            return errors.New("private IP not allowed")
        }
    }
    
    return nil
}
```

### Output Encoding

#### HTML Escaping
```php
// Always escape user content
echo htmlspecialchars($userContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// For attributes
echo 'data-value="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
```

#### JSON Encoding
```go
// Proper JSON encoding
func jsonResponse(w http.ResponseWriter, data interface{}) {
    w.Header().Set("Content-Type", "application/json")
    if err := json.NewEncoder(w).Encode(data); err != nil {
        http.Error(w, "JSON encoding error", 500)
    }
}
```

### Rate Limiting

#### Configuration
```go
// Rate limit configuration
var rateLimits = map[string]RateLimit{
    "auth":    {MaxTokens: 5,  Window: time.Minute},
    "post":    {MaxTokens: 10, Window: time.Minute},
    "comment": {MaxTokens: 20, Window: time.Minute},
    "preview": {MaxTokens: 3,  Window: time.Minute},
}
```

#### Implementation
```go
type RateLimit struct {
    Tokens      int
    RefreshedAt time.Time
    MaxTokens   int
    Window      time.Duration
}

func (rl *RateLimit) Allow() bool {
    now := time.Now()
    elapsed := now.Sub(rl.RefreshedAt)
    
    // Refill tokens
    tokensToAdd := int(elapsed / rl.Window * time.Duration(rl.MaxTokens))
    if tokensToAdd > 0 {
        rl.Tokens = min(rl.Tokens + tokensToAdd, rl.MaxTokens)
        rl.RefreshedAt = now
    }
    
    if rl.Tokens > 0 {
        rl.Tokens--
        return true
    }
    return false
}
```

## Database Security

### SQLite Configuration
```sql
-- Enable foreign keys
PRAGMA foreign_keys = ON;

-- Use WAL mode for better concurrency
PRAGMA journal_mode = WAL;

-- Secure delete
PRAGMA secure_delete = ON;

-- Optimize performance
PRAGMA cache_size = -64000;  -- 64MB cache
PRAGMA temp_store = MEMORY;
```

### MySQL Configuration
```sql
-- Use utf8mb4 for full Unicode support
CREATE DATABASE orc_social CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user
CREATE USER 'orcsocial'@'localhost' IDENTIFIED BY 'strong_random_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON orc_social.* TO 'orcsocial'@'localhost';
FLUSH PRIVILEGES;
```

### Data Protection
```bash
# File permissions for SQLite
chmod 600 /opt/orc-social/data/orc.db
chown orcsocial:orcsocial /opt/orc-social/data/orc.db

# Directory permissions
chmod 700 /opt/orc-social/data/
```

## Content Security

### Content Security Policy
```
Content-Security-Policy: 
    default-src 'self'; 
    script-src 'self' 'unsafe-inline'; 
    style-src 'self' 'unsafe-inline'; 
    img-src 'self' data:; 
    connect-src 'self'; 
    font-src 'self'; 
    object-src 'none'; 
    media-src 'none'; 
    frame-src 'none';
```

### Security Headers
```go
func securityHeaders(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("X-Content-Type-Options", "nosniff")
        w.Header().Set("X-Frame-Options", "DENY")
        w.Header().Set("X-XSS-Protection", "1; mode=block")
        w.Header().Set("Referrer-Policy", "no-referrer")
        w.Header().Set("Strict-Transport-Security", "max-age=31536000")
        
        next.ServeHTTP(w, r)
    })
}
```

### CSRF Protection
```go
func generateCSRFToken() string {
    bytes := make([]byte, 32)
    rand.Read(bytes)
    return hex.EncodeToString(bytes)
}

func validateCSRF(sessionToken, submittedToken string) bool {
    return subtle.ConstantTimeCompare(
        []byte(sessionToken), 
        []byte(submittedToken)
    ) == 1
}
```

## System Hardening

### Process Isolation
```ini
# systemd service hardening
[Service]
User=orcsocial
Group=orcsocial
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/opt/orc-social/data
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
RestrictRealtime=true
MemoryDenyWriteExecute=true
LockPersonality=true
```

### File Permissions
```bash
# Application files
chmod 755 /opt/orc-social/
chmod 755 /opt/orc-social/orc-social
chmod -R 644 /opt/orc-social/web/
chmod -R 644 /opt/orc-social/configs/
chmod 600 /opt/orc-social/configs/theme.json

# Data directory
chmod 700 /opt/orc-social/data/
chmod 600 /opt/orc-social/data/*

# User ownership
chown -R orcsocial:orcsocial /opt/orc-social/
```

### Log Security
```bash
# Log file permissions
chmod 640 /var/log/orc-social/*
chown orcsocial:adm /var/log/orc-social/*

# Logrotate configuration
cat > /etc/logrotate.d/orc-social << EOF
/var/log/orc-social/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 0640 orcsocial adm
    postrotate
        systemctl reload orc-social
    endscript
}
EOF
```

## Monitoring and Alerting

### Security Monitoring
```bash
#!/bin/bash
# Security monitoring script

# Check for failed authentication attempts
FAILED_AUTHS=$(journalctl -u orc-social --since "1 hour ago" | grep -c "authentication failed")
if [ "$FAILED_AUTHS" -gt 10 ]; then
    echo "ALERT: $FAILED_AUTHS failed authentication attempts in the last hour"
fi

# Check for rate limit violations
RATE_LIMITS=$(journalctl -u orc-social --since "1 hour ago" | grep -c "rate limit exceeded")
if [ "$RATE_LIMITS" -gt 50 ]; then
    echo "ALERT: $RATE_LIMITS rate limit violations in the last hour"
fi

# Check for suspicious database queries
DB_ERRORS=$(journalctl -u orc-social --since "1 hour ago" | grep -c "database error")
if [ "$DB_ERRORS" -gt 5 ]; then
    echo "ALERT: $DB_ERRORS database errors in the last hour"
fi
```

### Intrusion Detection
```bash
# Monitor for file changes
# Use AIDE or similar tool
aide --init
aide --check

# Monitor network connections
# Alert on unexpected outbound connections
netstat -tulpn | grep -v "127.0.0.1\|::1" | grep ESTABLISHED
```

## Incident Response

### Preparation
1. **Security Contact**: Designate security contact
2. **Response Plan**: Document incident response procedures
3. **Backup Strategy**: Ensure secure, offline backups
4. **Recovery Procedures**: Test restoration procedures

### Detection
1. **Log Monitoring**: Continuous monitoring of security logs
2. **Anomaly Detection**: Automated alerts for unusual activity
3. **User Reports**: Secure channel for security reports

### Response
1. **Isolation**: Isolate affected systems
2. **Assessment**: Determine scope and impact
3. **Containment**: Stop ongoing attack
4. **Eradication**: Remove attack vectors
5. **Recovery**: Restore normal operations
6. **Lessons Learned**: Update security measures

## Security Checklist

### Pre-Deployment
- [ ] Code security review completed
- [ ] Dependencies audit passed
- [ ] Configuration hardening applied
- [ ] Security testing performed
- [ ] Backup procedures tested

### Post-Deployment
- [ ] Tor-only access verified
- [ ] Security headers present
- [ ] Rate limiting functional
- [ ] CSRF protection working
- [ ] Input validation tested
- [ ] Output encoding verified
- [ ] Database security confirmed
- [ ] File permissions correct
- [ ] Monitoring configured
- [ ] Incident response plan ready

### Ongoing Maintenance
- [ ] Security patches applied monthly
- [ ] Logs reviewed weekly
- [ ] Configuration audited quarterly
- [ ] Security testing annual
- [ ] Backup recovery tested semi-annually

This security guide should be followed closely to ensure the highest level of protection for your Orc Social deployment.