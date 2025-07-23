#!/bin/bash
#
# Simple Headless Configuration for SuiteCRM
# This configures basic settings without requiring full SuiteCRM bootstrap
#

echo "Configuring SuiteCRM for headless/API-only mode..."

# 1. Create config_override.php
cat > /var/www/html/config_override.php << 'EOF'
<?php
// Headless mode configuration for B2C CRM

// API-specific settings
$sugar_config['site_url'] = 'http://localhost:8080';
$sugar_config['api_enabled'] = true;
$sugar_config['rest_enabled'] = true;

// Security settings for API
$sugar_config['api_cors_enabled'] = true;
$sugar_config['api_cors_allowed_origins'] = ['http://localhost:5173', 'http://localhost:3000'];
$sugar_config['api_cors_allowed_methods'] = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
$sugar_config['api_cors_allowed_headers'] = ['Content-Type', 'Authorization', 'X-Requested-With'];

// Performance optimizations
$sugar_config['disable_count_query'] = true;
$sugar_config['save_query'] = 'populate_only';
$sugar_config['list_max_entries_per_page'] = 50;

// Cache settings
$sugar_config['external_cache_disabled'] = false;

// Session handling for API
$sugar_config['session_gc_maxlifetime'] = 86400; // 24 hours
EOF

echo "✓ Created config_override.php"

# 2. Create .htaccess for API routing
cat > /var/www/html/.htaccess << 'EOF'
# SuiteCRM Headless Mode Configuration

<IfModule mod_rewrite.c>
    Options +FollowSymLinks
    RewriteEngine On
    
    # Allow direct access to React frontend (if proxied)
    RewriteCond %{REQUEST_URI} ^/$ [OR]
    RewriteCond %{REQUEST_URI} ^/index\.html$
    RewriteRule ^ - [L]
    
    # Allow access to API endpoints
    RewriteCond %{REQUEST_URI} ^/custom/api [OR]
    RewriteCond %{REQUEST_URI} ^/api [OR]
    RewriteCond %{REQUEST_URI} ^/service [OR]
    RewriteCond %{REQUEST_URI} ^/cache [OR]
    RewriteCond %{REQUEST_URI} ^/upload [OR]
    RewriteCond %{REQUEST_URI} ^/themes
    RewriteRule ^ - [L]
    
    # Allow access to necessary resources
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Everything else goes to index.php
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Security headers for API
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Protect sensitive files
<FilesMatch "\.(log|tpl|mysql|sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>
EOF

echo "✓ Created .htaccess for headless mode"

# 3. Enable CORS for API in Apache config
cat > /etc/apache2/conf-available/api-cors.conf << 'EOF'
# CORS Configuration for API endpoints
<Directory /var/www/html/custom/api>
    Header always set Access-Control-Allow-Origin "http://localhost:5173"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Access-Control-Max-Age "3600"
    
    # Handle preflight OPTIONS requests
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=200,L]
</Directory>
EOF

# Enable the configuration
a2enconf api-cors 2>/dev/null || true

# 4. Clear cache
rm -rf /var/www/html/cache/* 2>/dev/null || true
mkdir -p /var/www/html/cache
chown -R www-data:www-data /var/www/html/cache

echo "✓ Cleared cache"

# 5. Set proper permissions
chown www-data:www-data /var/www/html/config_override.php 2>/dev/null || true
chown www-data:www-data /var/www/html/.htaccess

echo ""
echo "✅ Headless mode configuration complete!"
echo ""
echo "Next steps:"
echo "1. Restart Apache: service apache2 reload"
echo "2. Access the API at: http://localhost:8080/custom/api"
echo "3. Run the React frontend at: http://localhost:5173"