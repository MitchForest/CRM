# Production Deployment Guide

## Overview
This guide provides step-by-step instructions for deploying the Phase 5 CRM to production.

## Prerequisites

- Docker and Docker Compose installed
- Domain name configured
- SSL certificates (Let's Encrypt recommended)
- Server with at least 2GB RAM
- MySQL/MariaDB database
- SMTP server for emails (optional)

## Deployment Steps

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo apt install docker-compose -y

# Create deployment directory
sudo mkdir -p /opt/crm
cd /opt/crm
```

### 2. Clone Repository

```bash
# Clone the repository
git clone https://github.com/your-org/crm.git .

# Checkout stable branch
git checkout main
```

### 3. Environment Configuration

#### Backend Configuration
```bash
# Copy and edit backend environment
cp backend/.env.example backend/.env
nano backend/.env
```

**Required backend settings:**
```env
# Security
JWT_SECRET=generate-very-secure-random-string-here
JWT_ACCESS_TOKEN_TTL=900
JWT_REFRESH_TOKEN_TTL=2592000

# Database
DATABASE_URL=mysql://crm_user:secure_password@localhost:3306/crm_production
DATABASE_HOST=localhost
DATABASE_PORT=3306
DATABASE_NAME=crm_production
DATABASE_USER=crm_user
DATABASE_PASSWORD=secure_password

# OpenAI
OPENAI_API_KEY=sk-your-production-key

# Application
APP_ENV=production
APP_DEBUG=false
SITE_URL=https://crm.yourdomain.com

# Email (optional)
SMTP_HOST=smtp.yourdomain.com
SMTP_PORT=587
SMTP_USER=noreply@yourdomain.com
SMTP_PASSWORD=smtp_password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=Your CRM
```

#### Frontend Configuration
```bash
# Copy and edit frontend environment
cp frontend/.env.example frontend/.env
nano frontend/.env
```

**Required frontend settings:**
```env
VITE_API_URL=https://crm.yourdomain.com/custom/api
VITE_APP_URL=https://crm.yourdomain.com
```

### 4. SSL Configuration

#### Using Let's Encrypt
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Generate certificates
sudo certbot certonly --standalone -d crm.yourdomain.com
```

#### Update Nginx Configuration
Create `/opt/crm/nginx.conf`:
```nginx
server {
    listen 80;
    server_name crm.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name crm.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/crm.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/crm.yourdomain.com/privkey.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Backend proxy
    location /custom/api {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    
    # Public assets for embeds
    location /custom/public {
        proxy_pass http://localhost:8080;
        add_header Access-Control-Allow-Origin "*";
    }
    
    # Frontend
    location / {
        proxy_pass http://localhost:5173;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### 5. Database Setup

```bash
# Create production database
mysql -u root -p << EOF
CREATE DATABASE crm_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON crm_production.* TO 'crm_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 6. Build and Deploy

```bash
# Build frontend
cd frontend
npm install
npm run build
cd ..

# Update docker-compose for production
cp docker-compose.yml docker-compose.prod.yml
```

Edit `docker-compose.prod.yml`:
```yaml
version: '3.8'

services:
  backend:
    build: ./backend
    ports:
      - "8080:80"
    environment:
      - APP_ENV=production
    volumes:
      - ./backend:/var/www/html
      - ./backend/.env:/var/www/html/.env
    depends_on:
      - mysql
    restart: always

  frontend:
    build: ./frontend
    ports:
      - "5173:80"
    volumes:
      - ./frontend/dist:/usr/share/nginx/html
    restart: always

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: crm_production
      MYSQL_USER: crm_user
      MYSQL_PASSWORD: secure_password
    volumes:
      - mysql_data:/var/lib/mysql
    restart: always

volumes:
  mysql_data:
```

### 7. Initial Deployment

```bash
# Start services
docker-compose -f docker-compose.prod.yml up -d

# Wait for MySQL to be ready
sleep 30

# Initialize database
docker-compose -f docker-compose.prod.yml exec backend php custom/install/reset_database.php
docker-compose -f docker-compose.prod.yml exec backend php custom/install/seed_phase5_data.php

# Create admin user
docker-compose -f docker-compose.prod.yml exec backend php custom/scripts/create_admin.php
```

### 8. Security Hardening

```bash
# Set proper permissions
sudo chown -R www-data:www-data backend/custom/
sudo chmod -R 755 backend/custom/
sudo chmod -R 775 backend/custom/cache/

# Firewall setup
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Fail2ban for brute force protection
sudo apt install fail2ban -y
```

### 9. Monitoring Setup

#### Application Monitoring
```bash
# Install monitoring agent (example: New Relic)
curl -Ls https://download.newrelic.com/install/newrelic-cli/scripts/install.sh | bash
```

#### Log Aggregation
```bash
# Configure log rotation
sudo nano /etc/logrotate.d/crm

# Add:
/opt/crm/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 10. Backup Configuration

Create `/opt/crm/backup.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/backups/crm"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
docker-compose -f /opt/crm/docker-compose.prod.yml exec -T mysql \
  mysqldump -u crm_user -p'secure_password' crm_production | \
  gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
  /opt/crm/backend/custom \
  /opt/crm/backend/.env

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete
```

Add to crontab:
```bash
# Daily backups at 2 AM
0 2 * * * /opt/crm/backup.sh
```

### 11. Performance Optimization

#### Enable OPcache
```ini
# Add to php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

#### Redis for Caching
```yaml
# Add to docker-compose.prod.yml
redis:
  image: redis:alpine
  restart: always
  volumes:
    - redis_data:/data
```

### 12. Testing Production

```bash
# Health check
curl https://crm.yourdomain.com/custom/api/health

# Test login
curl -X POST https://crm.yourdomain.com/custom/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}'

# Check embed scripts
curl https://crm.yourdomain.com/custom/public/js/tracking.js
curl https://crm.yourdomain.com/custom/public/js/chat-widget.js
curl https://crm.yourdomain.com/custom/public/js/forms-embed.js
```

## Maintenance Tasks

### Daily
- Check application logs
- Monitor disk space
- Verify backups completed

### Weekly
- Review error logs
- Check performance metrics
- Update visitor statistics

### Monthly
- Security updates
- Database optimization
- Review user access

### Update Procedure
```bash
# Backup first
./backup.sh

# Pull updates
git pull origin main

# Rebuild if needed
docker-compose -f docker-compose.prod.yml build

# Restart services
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d
```

## Troubleshooting

### Common Issues

1. **502 Bad Gateway**
   - Check backend container: `docker logs crm_backend_1`
   - Verify PHP-FPM is running
   - Check Nginx error logs

2. **Database Connection Failed**
   - Verify MySQL is running
   - Check credentials in .env
   - Test connection manually

3. **Chat/AI Not Working**
   - Verify OPENAI_API_KEY is set
   - Check API rate limits
   - Review AI error logs

4. **Forms Not Embedding**
   - Check CORS headers
   - Verify public assets accessible
   - Test embed script directly

### Debug Mode
```bash
# Enable debug temporarily
docker-compose -f docker-compose.prod.yml exec backend \
  sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Disable debug when done
docker-compose -f docker-compose.prod.yml exec backend \
  sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
```

## Rollback Procedure

```bash
# Stop services
docker-compose -f docker-compose.prod.yml down

# Restore database
gunzip < /backups/crm/db_20250726_020000.sql.gz | \
  docker-compose -f docker-compose.prod.yml exec -T mysql \
  mysql -u crm_user -p'secure_password' crm_production

# Restore files
tar -xzf /backups/crm/files_20250726_020000.tar.gz -C /

# Start services
docker-compose -f docker-compose.prod.yml up -d
```

## Security Checklist

- [ ] Strong passwords for all accounts
- [ ] SSL certificates installed and valid
- [ ] Firewall configured
- [ ] Fail2ban active
- [ ] Regular security updates
- [ ] Backup encryption enabled
- [ ] Access logs monitored
- [ ] Rate limiting configured

## Support Contacts

- **Technical Issues**: tech-support@yourcompany.com
- **Security Incidents**: security@yourcompany.com
- **Emergency**: +1-XXX-XXX-XXXX

---

**Remember:** Always test changes in staging before deploying to production!