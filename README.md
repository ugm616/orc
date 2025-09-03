# Orc Social - Privacy-First Social Network

Orc Social is a complete privacy-first social network designed exclusively for Tor v3 onion services. It provides a secure, anonymous, and decentralized platform for social interaction without compromising user privacy.

## 🔒 Core Privacy Principles

- **Tor-only access**: Zero clearnet visibility, accessible only via Tor hidden services
- **Local binding**: Services listen only on 127.0.0.1 or Unix sockets
- **No external calls**: All assets served locally, no CDNs or external dependencies
- **Data minimization**: Store only essential data with anonymous account IDs
- **Complete anonymity**: No IP logging, no user agent storage, no tracking

## 🚀 Dual Implementation

### 1. Go Implementation (Self-hosted)
- Single binary deployment with SQLite
- High performance and low resource usage
- Built-in theme system and admin panel
- Systemd service integration

### 2. PHP Implementation (Shared hosting)
- Compatible with standard shared hosting
- MySQL or SQLite database support
- Tor enforcement options for different hosting scenarios
- Easy deployment with .htaccess configuration

## ⚡ Features

### Core Functionality
- **User System**: Anonymous account IDs with recovery phrases
- **Posts & Comments**: Text posts (1000 chars) with optional URLs and nested comments (500 chars)
- **Feed**: Reverse-chronological posts with safe link previews
- **Security**: CSRF protection, XSS prevention, rate limiting, SSRF protection

### Admin Features
- **Theme Customization**: Complete visual customization with live preview
- **Site Management**: User management, content moderation, system monitoring
- **Security Dashboard**: Rate limit monitoring, security alerts, audit logs

### Advanced Security
- **Authentication**: Argon2id password hashing with secure session management
- **Rate Limiting**: Configurable limits for auth (5/min), posting (10/min), previews (3/min)
- **Link Previews**: SSRF protection with private IP blocking and size limits
- **Headers**: Strict CSP, no-referrer policy, frame protection

## 🛠 Technology Stack

- **Backend**: Go 1.21+ or PHP 8.x (no frameworks)
- **Database**: SQLite (Go) or MySQL/SQLite (PHP)
- **Frontend**: Tailwind CSS + plain ES6 JavaScript (≤150 lines total)
- **Templates**: Server-side rendering with theme variables
- **Security**: Built-in CSRF, XSS, and rate limiting protection

## 📦 Installation

### Go Implementation (Recommended)

1. **Prerequisites**
   ```bash
   # Install Go 1.21+
   sudo apt install golang-1.21 tor
   ```

2. **Build and Install**
   ```bash
   git clone https://github.com/ugm616/orc.git
   cd orc
   go build -o orc-social ./cmd/server
   sudo mkdir -p /opt/orc-social
   sudo cp orc-social /opt/orc-social/
   sudo cp -r web configs /opt/orc-social/
   sudo cp services/orc-social.service /etc/systemd/system/
   ```

3. **Configure Tor**
   ```bash
   sudo cp configs/torrc /etc/tor/torrc.d/orc-social
   sudo systemctl restart tor
   ```

4. **Start Service**
   ```bash
   sudo systemctl enable orc-social
   sudo systemctl start orc-social
   ```

### PHP Implementation

1. **Upload Files**
   ```bash
   # Upload php/ directory to your web root
   # Upload config/ directory above web root
   # Upload web/static/ to web-accessible location
   ```

2. **Configure Database**
   ```bash
   # For MySQL: Create database and update config/config.php
   # For SQLite: Ensure data/ directory is writable
   ```

3. **Set Permissions**
   ```bash
   chmod 755 php/
   chmod 600 config/config.php
   chmod 755 web/static/
   ```

4. **Configure Tor (Mode B)**
   ```bash
   # Configure your Tor edge VPS to forward to your hosting
   # Update .htaccess IP allowlist as needed
   ```

## 🔧 Configuration

### Environment Variables (Go)
```bash
export ORC_BIND_ADDR=127.0.0.1:8080
export ORC_DB_PATH=/opt/orc-social/data/orc.db
export ORC_CONFIG_PATH=/opt/orc-social/configs
```

### PHP Configuration
Edit `config/config.php` or use environment variables:
```php
// Database settings
'db_type' => 'sqlite', // or 'mysql'
'db_path' => '/path/to/orc.db',

// Security settings
'tor_only_mode' => true,
'allowed_ips' => ['127.0.0.1', '::1'],

// Site settings
'site_name' => 'Orc Social',
'max_post_length' => 1000,
'registration_enabled' => true,
```

## 🌐 Deployment Modes

### Mode A: User-space Tor (Shared Hosting)
- Run Tor in user space on shared hosting
- No public virtual host configuration
- Serves only on localhost/private network

### Mode B: Tor Edge VPS + Shared Hosting
- Tor runs on separate VPS
- Forwards traffic to shared hosting via IP allowlist
- Maximum compatibility with hosting providers

### Mode C: Self-hosted with Tor
- Complete control over both application and Tor
- Recommended for maximum security and performance
- Uses Go implementation with systemd service

## 🛡 Security Features

### Network Security
- **Tor-only binding**: Never listen on 0.0.0.0
- **Private IP blocking**: Prevents SSRF attacks
- **Rate limiting**: Token bucket algorithm with configurable limits
- **Request validation**: Input sanitization and type checking

### Application Security
- **CSRF Protection**: Per-form tokens with validation
- **XSS Prevention**: Content escaping and CSP headers
- **Session Security**: HttpOnly, Secure, SameSite cookies
- **Password Security**: Argon2id with proper parameters

### Infrastructure Security
- **Systemd hardening**: NoNewPrivileges, ProtectSystem, etc.
- **File permissions**: Restricted access to sensitive files
- **Process isolation**: Dedicated user account and group
- **Resource limits**: Memory, file, and process limits

## 🎨 Theme Customization

### Admin Panel
- Access `/admin/theme` as an administrator
- Live preview of changes before applying
- Color picker for all theme elements
- Typography and layout configuration
- Logo and branding options

### Manual Customization
Edit `configs/theme.json`:
```json
{
  "colors": {
    "primary": "#6366f1",
    "background": "#0f172a",
    "text": "#f8fafc"
  },
  "typography": {
    "fontFamily": "system-ui, sans-serif",
    "fontSize": "16px"
  }
}
```

## 🔍 Monitoring

### Logs
- **Go**: Uses standard Go logging to stdout/stderr
- **PHP**: Logs to error_log with configurable levels
- **Tor**: Monitor `/var/log/tor/` for connection issues

### Health Checks
```bash
# Check service status
systemctl status orc-social

# Check Tor hidden service
curl --socks5 127.0.0.1:9050 http://your-onion-address.onion/

# Check database
sqlite3 /opt/orc-social/data/orc.db ".tables"
```

## 🔧 Maintenance

### Backup
```bash
# Database backup
cp /opt/orc-social/data/orc.db /backup/orc-$(date +%Y%m%d).db

# Configuration backup
tar -czf /backup/orc-config-$(date +%Y%m%d).tar.gz /opt/orc-social/configs/
```

### Updates
```bash
# Go implementation
cd orc
git pull
go build -o orc-social ./cmd/server
sudo systemctl stop orc-social
sudo cp orc-social /opt/orc-social/
sudo systemctl start orc-social

# PHP implementation
git pull
rsync -av php/ /path/to/webroot/
```

## 🐛 Troubleshooting

### Common Issues

1. **Service won't start**
   - Check logs: `journalctl -u orc-social -f`
   - Verify permissions on data directory
   - Check port availability: `netstat -tlnp | grep 8080`

2. **Can't access via Tor**
   - Verify Tor is running: `systemctl status tor`
   - Check hidden service directory permissions
   - Verify torrc configuration

3. **Database errors**
   - Check file permissions on database file
   - Verify SQLite/MySQL is installed
   - Check disk space: `df -h`

### Debug Mode
Enable debug logging in Go:
```bash
export ORC_DEBUG=true
systemctl restart orc-social
```

Enable debug in PHP:
```php
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

## 📋 Requirements

### Minimum System Requirements
- **RAM**: 512MB (Go) / 256MB (PHP)
- **Storage**: 1GB for application + database growth
- **CPU**: Any modern processor (very low CPU usage)
- **Network**: Tor connection required

### Software Requirements
- **Go**: 1.21+ (for Go implementation)
- **PHP**: 8.0+ with PDO, OpenSSL (for PHP implementation)
- **Database**: SQLite 3.x or MySQL 5.7+
- **Web Server**: Built-in (Go) or Apache/Nginx (PHP)
- **Tor**: Latest stable version

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Development Setup
```bash
git clone https://github.com/ugm616/orc.git
cd orc
go mod tidy
go run ./cmd/server
```

## 📄 License

This project is released under the MIT License. See LICENSE file for details.

## ⚠️ Security Notice

This software is designed for privacy and security, but no system is perfect. Always:
- Keep software updated
- Monitor logs for suspicious activity
- Use strong passwords and recovery phrases
- Regularly backup your data
- Follow operational security best practices

## 🌍 Community

- **Issues**: Report bugs and feature requests on GitHub
- **Discussions**: Join community discussions
- **Security**: Report security issues privately

---

**Remember**: Your privacy and security depend not just on the software, but also on proper configuration, maintenance, and operational security practices.