#!/bin/bash
#
# Setup Frontend Proxy Configuration
# This script configures Apache to proxy frontend requests to Vite dev server
#

echo "Setting up frontend proxy configuration..."

# Create Apache configuration for proxying to Vite
cat > /tmp/frontend-proxy.conf << 'EOF'
# Frontend Proxy Configuration for SaaS CRM

# Proxy all non-API requests to Vite dev server
<IfModule mod_proxy.c>
    ProxyRequests Off
    ProxyPreserveHost On
    
    # Don't proxy API requests
    ProxyPass /custom/api !
    ProxyPass /api !
    ProxyPass /service !
    ProxyPass /cache !
    ProxyPass /upload !
    ProxyPass /themes !
    ProxyPass /api-only.html !
    
    # Proxy everything else to Vite dev server
    ProxyPass / http://frontend:5173/
    ProxyPassReverse / http://frontend:5173/
    
    # WebSocket support for Vite HMR
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/?(.*) "ws://frontend:5173/$1" [P,L]
</IfModule>

# CORS Headers for API
<IfModule mod_headers.c>
    # Set CORS headers for API endpoints
    <Location ~ "^/(custom/api|api|service)">
        Header set Access-Control-Allow-Origin "http://localhost:5173"
        Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
        Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
        Header set Access-Control-Allow-Credentials "true"
        Header set Access-Control-Max-Age "3600"
    </Location>
</IfModule>
EOF

echo "✓ Created frontend proxy configuration"

# Create docker-compose override for development
cat > /tmp/docker-compose.override.yml << 'EOF'
version: '3.8'

services:
  backend:
    build:
      context: ./docker/backend
      args:
        - ENABLE_XDEBUG=true
    environment:
      - ENVIRONMENT=development
      - FRONTEND_URL=http://localhost:5173
    volumes:
      - ./backend:/var/www/html
      - ./docker/backend/frontend-proxy.conf:/etc/apache2/sites-available/frontend-proxy.conf
    command: >
      bash -c "
        a2enmod proxy proxy_http rewrite headers &&
        a2ensite frontend-proxy &&
        apache2-foreground
      "

  frontend:
    build: ./docker/frontend
    container_name: suitecrm-frontend
    ports:
      - "5173:5173"
    volumes:
      - ./frontend:/app
      - /app/node_modules
    environment:
      - VITE_API_URL=http://localhost:8080/custom/api
    command: npm run dev -- --host 0.0.0.0
    networks:
      - crm-network
    depends_on:
      - backend
EOF

echo "✓ Created docker-compose.override.yml for development"
echo ""
echo "To apply these changes:"
echo "1. Copy the proxy config: docker cp /tmp/frontend-proxy.conf suitecrm-backend:/etc/apache2/sites-available/"
echo "2. Enable the proxy in the container:"
echo "   docker exec suitecrm-backend a2enmod proxy proxy_http"
echo "   docker exec suitecrm-backend a2ensite frontend-proxy"
echo "   docker exec suitecrm-backend service apache2 reload"
echo "3. Or use the docker-compose.override.yml file for automatic setup"

# Make script executable
chmod +x /tmp/setup_frontend_proxy.sh