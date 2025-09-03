# Orc Social Deployment Guide

## Quick Setup Guide

### Go Implementation (Recommended)

1. **System Requirements**
   - Ubuntu 20.04+ or similar Linux distribution
   - Go 1.21+ 
   - Tor (latest stable)
   - 512MB RAM minimum
   - 1GB disk space

2. **Installation**
   ```bash
   # Install dependencies
   sudo apt update
   sudo apt install golang-1.21 tor git sqlite3
   
   # Clone and build
   git clone https://github.com/ugm616/orc.git
   cd orc
   go build -o orc-social ./cmd/server
   
   # Install system-wide
   sudo mkdir -p /opt/orc-social
   sudo cp orc-social /opt/orc-social/
   sudo cp -r web configs /opt/orc-social/
   sudo mkdir -p /opt/orc-social/data
   
   # Create user and set permissions
   sudo useradd -r -s /bin/false orcsocial
   sudo chown -R orcsocial:orcsocial /opt/orc-social
   sudo chmod 755 /opt/orc-social
   sudo chmod 700 /opt/orc-social/data
   
   # Install service
   sudo cp services/orc-social.service /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable orc-social
   ```

3. **Configure Tor**
   ```bash
   # Add to /etc/tor/torrc
   sudo tee -a /etc/tor/torrc << EOF
   HiddenServiceDir /var/lib/tor/orc-social/
   HiddenServicePort 443 127.0.0.1:8080
   EOF
   
   # Restart Tor
   sudo systemctl restart tor
   
   # Get your onion address
   sudo cat /var/lib/tor/orc-social/hostname
   ```

4. **Start Service**
   ```bash
   sudo systemctl start orc-social
   sudo systemctl status orc-social
   ```

### PHP Implementation (Shared Hosting)

#### Mode A: User-space Tor

1. **Upload Files**
   - Upload `php/` contents to your web root
   - Upload `config/` directory above web root (not web-accessible)
   - Upload `web/static/` to a web-accessible location

2. **Configure Database**
   ```php
   // Edit config/config.php
   'db_type' => 'sqlite',
   'db_path' => '/path/above/webroot/data/orc.db',
   ```

3. **Install User-space Tor**
   ```bash
   # On your hosting account
   mkdir ~/tor
   cd ~/tor
   wget https://dist.torproject.org/tor-0.4.7.10.tar.gz
   tar -xzf tor-0.4.7.10.tar.gz
   cd tor-0.4.7.10
   ./configure --prefix=$HOME/tor
   make && make install
   
   # Configure
   mkdir -p ~/tor/etc
   cat > ~/tor/etc/torrc << EOF
   DataDirectory ~/tor/data
   HiddenServiceDir ~/tor/orc-social/
   HiddenServicePort 80 127.0.0.1:8080
   SocksPort 0
   EOF
   
   # Start Tor
   ~/tor/bin/tor -f ~/tor/etc/torrc &
   ```

#### Mode B: Tor Edge VPS

1. **VPS Setup** (Tor edge server)
   ```bash
   # On your VPS
   sudo apt install tor nginx
   
   # Configure Tor
   sudo tee -a /etc/tor/torrc << EOF
   HiddenServiceDir /var/lib/tor/orc-social/
   HiddenServicePort 80 127.0.0.1:8080
   EOF
   
   # Configure Nginx proxy
   sudo tee /etc/nginx/sites-available/orc-social << EOF
   server {
       listen 127.0.0.1:8080;
       location / {
           proxy_pass https://your-shared-hosting.com;
           proxy_set_header Host your-shared-hosting.com;
           proxy_set_header X-Real-IP \$remote_addr;
           proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
       }
   }
   EOF
   
   sudo ln -s /etc/nginx/sites-available/orc-social /etc/nginx/sites-enabled/
   sudo systemctl restart tor nginx
   ```

2. **Shared Hosting Setup**
   ```bash
   # Upload files and update .htaccess
   # Uncomment IP allowlist section in .htaccess
   # Add your VPS IP to the allowed list
   ```

## Post-Installation Checklist

### Security Verification

1. **Test Tor-only Access**
   ```bash
   # This should fail (clearnet blocked)
   curl http://your-server-ip:8080/
   
   # This should work (via Tor)
   curl --socks5 127.0.0.1:9050 http://your-onion-address.onion/
   ```

2. **Verify Security Headers**
   ```bash
   curl -I --socks5 127.0.0.1:9050 http://your-onion-address.onion/
   ```
   
   Should show:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Content-Security-Policy: ...`

3. **Test Rate Limiting**
   ```bash
   # Rapid requests should eventually return 429
   for i in {1..20}; do curl --socks5 127.0.0.1:9050 http://your-onion-address.onion/; done
   ```

### Functional Testing

1. **Account Creation**
   - Navigate to your onion address
   - Create the first account (becomes admin)
   - Save account ID and recovery phrase securely

2. **Basic Functionality**
   - Create a post
   - Add a comment
   - Test link preview (if enabled)
   - Verify logout/login works

3. **Admin Features**
   - Access `/admin` as the admin user
   - Test theme customization
   - Verify admin dashboard shows statistics

### Performance Optimization

1. **Database Optimization**
   ```bash
   # For SQLite
   sqlite3 /opt/orc-social/data/orc.db "VACUUM; ANALYZE;"
   
   # For MySQL
   mysql -u root -p -e "OPTIMIZE TABLE posts, comments, accounts;"
   ```

2. **Log Rotation**
   ```bash
   # Add to /etc/logrotate.d/orc-social
   /var/log/orc-social/*.log {
       daily
       missingok
       rotate 52
       compress
       delaycompress
       notifempty
       create 0644 orcsocial orcsocial
   }
   ```

## Backup Strategy

### Automated Backup Script

```bash
#!/bin/bash
# /opt/orc-social/backup.sh

BACKUP_DIR="/backup/orc-social"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

# Database backup
if [ -f "/opt/orc-social/data/orc.db" ]; then
    cp "/opt/orc-social/data/orc.db" "$BACKUP_DIR/orc_$DATE.db"
fi

# Configuration backup
tar -czf "$BACKUP_DIR/config_$DATE.tar.gz" /opt/orc-social/configs/

# Keep only last 30 days
find "$BACKUP_DIR" -name "*.db" -mtime +30 -delete
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

Add to crontab:
```bash
# Daily backup at 2 AM
0 2 * * * /opt/orc-social/backup.sh >> /var/log/orc-backup.log 2>&1
```

## Monitoring and Maintenance

### Health Check Script

```bash
#!/bin/bash
# /opt/orc-social/healthcheck.sh

# Check service status
if ! systemctl is-active --quiet orc-social; then
    echo "ERROR: orc-social service is not running"
    exit 1
fi

# Check Tor service
if ! systemctl is-active --quiet tor; then
    echo "ERROR: Tor service is not running"
    exit 1
fi

# Check database connectivity
if ! sqlite3 /opt/orc-social/data/orc.db "SELECT 1;" > /dev/null 2>&1; then
    echo "ERROR: Database is not accessible"
    exit 1
fi

# Check disk space
DISK_USAGE=$(df /opt/orc-social | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    echo "WARNING: Disk usage is $DISK_USAGE%"
fi

echo "All checks passed"
```

### Update Procedure

1. **Backup Current Installation**
   ```bash
   /opt/orc-social/backup.sh
   ```

2. **Update Code**
   ```bash
   cd /path/to/orc
   git pull
   go build -o orc-social ./cmd/server
   ```

3. **Deploy Update**
   ```bash
   sudo systemctl stop orc-social
   sudo cp orc-social /opt/orc-social/
   sudo cp -r web/* /opt/orc-social/web/
   sudo systemctl start orc-social
   ```

4. **Verify Update**
   ```bash
   sudo systemctl status orc-social
   /opt/orc-social/healthcheck.sh
   ```

## Troubleshooting

### Common Issues

1. **Service Won't Start**
   ```bash
   # Check logs
   journalctl -u orc-social -f
   
   # Check permissions
   ls -la /opt/orc-social/
   
   # Test manual start
   sudo -u orcsocial /opt/orc-social/orc-social
   ```

2. **Can't Access via Tor**
   ```bash
   # Check Tor logs
   journalctl -u tor -f
   
   # Verify hidden service
   ls -la /var/lib/tor/orc-social/
   cat /var/lib/tor/orc-social/hostname
   
   # Test Tor connectivity
   curl --socks5 127.0.0.1:9050 http://check.torproject.org/
   ```

3. **Database Issues**
   ```bash
   # Check database file
   ls -la /opt/orc-social/data/
   
   # Test database
   sqlite3 /opt/orc-social/data/orc.db ".tables"
   
   # Check permissions
   sudo -u orcsocial sqlite3 /opt/orc-social/data/orc.db ".tables"
   ```

### Log Analysis

```bash
# Application logs
journalctl -u orc-social --since "1 hour ago"

# Tor logs
journalctl -u tor --since "1 hour ago"

# System logs
journalctl --since "1 hour ago" | grep -i error
```

## Security Considerations

### Hardening Checklist

- [ ] Tor is properly configured with v3 onion service
- [ ] Application binds only to 127.0.0.1
- [ ] Database is not web-accessible
- [ ] Log files don't contain sensitive information
- [ ] Regular security updates are applied
- [ ] Backup encryption is configured
- [ ] Rate limiting is working
- [ ] CSRF protection is enabled
- [ ] Security headers are present

### Regular Maintenance Tasks

- **Weekly**: Review logs for suspicious activity
- **Monthly**: Update system packages and dependencies
- **Quarterly**: Review and update security configurations
- **Annually**: Full security audit and penetration testing

This completes the deployment guide for Orc Social. Follow these procedures for a secure and reliable installation.