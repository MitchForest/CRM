# Phase 1 - Backend Implementation Guide

v8 API References:
- https://docs.suitecrm.com/developer/api/developer-setup-guide/requirements/
- https://docs.suitecrm.com/developer/api/developer-setup-guide/json-api/
- https://docs.suitecrm.com/developer/api/developer-setup-guide/configure-authentication/
- https://docs.suitecrm.com/developer/api/developer-setup-guide/getting-available-resources/
- https://docs.suitecrm.com/developer/api/developer-setup-guide/suitecrm_v8_api_set_up_for_postman/
- https://docs.suitecrm.com/developer/api/developer-setup-guide/managing-tokens/
- https://docs.suitecrm.com/developer/api/developer-setup-guide/customization/ 

## Overview
Phase 1 establishes the SuiteCRM backend with Docker, configures the v8 REST API, adds custom fields for AI scoring, and ensures proper authentication and CORS setup for the React frontend.

## Prerequisites
- Docker and Docker Compose
- Git
- Basic knowledge of PHP and MySQL
- Postman or similar API testing tool

## Step-by-Step Implementation

### 1. Project Setup and Docker Configuration

#### 1.1 Create Project Structure
```bash
# Create backend directory structure
mkdir -p backend/{custom,docker,data}
mkdir -p backend/custom/{Extension/modules,api/v8,include}
mkdir -p backend/docker/{mysql,apache}
cd backend
```

#### 1.2 Create Docker Compose Configuration
`docker-compose.yml`:
```yaml
version: '3.8'

services:
  mysql:
    image: mysql:5.7
    container_name: suitecrm_mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: suitecrm
      MYSQL_USER: suitecrm
      MYSQL_PASSWORD: suitecrm_password
    volumes:
      - ./data/mysql:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "3306:3306"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10

  suitecrm:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: suitecrm_app
    restart: unless-stopped
    depends_on:
      mysql:
        condition: service_healthy
    environment:
      DATABASE_HOST: mysql
      DATABASE_NAME: suitecrm
      DATABASE_USER: suitecrm
      DATABASE_PASSWORD: suitecrm_password
      ADMIN_USER: admin
      ADMIN_PASSWORD: admin123
      SITE_URL: http://localhost:8080
    volumes:
      - ./suitecrm:/var/www/html
      - ./custom:/var/www/html/custom
    ports:
      - "8080:80"

  redis:
    image: redis:alpine
    container_name: suitecrm_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
```

#### 1.3 Create Dockerfile
`Dockerfile`:
```dockerfile
FROM php:7.4-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    curl \
    wget \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo_mysql \
    zip \
    intl \
    xml \
    soap \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache
RUN a2enmod rewrite headers expires deflate
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Set PHP configuration
COPY docker/apache/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create directory for SuiteCRM
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Copy custom entrypoint
COPY docker/entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
```

#### 1.4 Create Apache Configuration
`docker/apache/000-default.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Enable CORS for API
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With"
    Header always set Access-Control-Max-Age "3600"
    
    # Handle preflight requests
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=200,L]

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

#### 1.5 Create PHP Configuration
`docker/apache/php.ini`:
```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
max_input_time = 300
max_input_vars = 10000
date.timezone = UTC
session.gc_maxlifetime = 3600
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING
display_errors = Off
log_errors = On
```

#### 1.6 Create Entrypoint Script
`docker/entrypoint.sh`:
```bash
#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
while ! mysqladmin ping -h"$DATABASE_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" --silent; do
    sleep 1
done

# Download SuiteCRM if not exists
if [ ! -f /var/www/html/index.php ]; then
    echo "Downloading SuiteCRM 7.14.6..."
    cd /tmp
    wget https://github.com/salesagility/SuiteCRM/archive/refs/tags/v7.14.6.zip
    unzip v7.14.6.zip
    mv SuiteCRM-7.14.6/* /var/www/html/
    rm -rf v7.14.6.zip SuiteCRM-7.14.6
    
    # Set permissions
    cd /var/www/html
    chown -R www-data:www-data .
    chmod -R 755 .
    chmod -R 775 cache custom modules themes data upload
    chmod 775 config_override.php 2>/dev/null || true
fi

# Run SuiteCRM installer if not installed
if [ ! -f /var/www/html/config.php ]; then
    echo "Installing SuiteCRM..."
    cd /var/www/html
    
    # Create silent install config
    cat > install_config.php << EOF
<?php
\$sugar_config_si = array(
    'setup_db_host_name' => '$DATABASE_HOST',
    'setup_db_database_name' => '$DATABASE_NAME',
    'setup_db_admin_user_name' => '$DATABASE_USER',
    'setup_db_admin_password' => '$DATABASE_PASSWORD',
    'setup_db_drop_tables' => 0,
    'setup_db_create_database' => 0,
    'setup_site_admin_user_name' => '$ADMIN_USER',
    'setup_site_admin_password' => '$ADMIN_PASSWORD',
    'setup_site_url' => '$SITE_URL',
    'setup_system_name' => 'SuiteCRM',
    'default_currency_iso4217' => 'USD',
    'default_currency_name' => 'US Dollars',
    'default_currency_symbol' => '$',
    'default_date_format' => 'Y-m-d',
    'default_time_format' => 'H:i',
    'default_decimal_seperator' => '.',
    'default_export_charset' => 'UTF-8',
    'default_language' => 'en_us',
    'default_locale_name_format' => 's f l',
    'default_number_grouping_seperator' => ',',
    'export_delimiter' => ',',
);
EOF

    # Run silent install
    php -r "
    \$_SERVER['HTTP_HOST'] = 'localhost';
    \$_SERVER['REQUEST_URI'] = '/install.php';
    require_once('install.php');
    "
    
    rm -f install_config.php
fi

# Install v8 API if not exists
if [ ! -d /var/www/html/Api/V8 ]; then
    echo "Installing SuiteCRM v8 API..."
    cd /var/www/html
    
    # The v8 API should already be included in 7.14.6
    # If not, we'd download and install it here
    
    # Configure v8 API
    if [ -f /var/www/html/Api/V8/Config/services.php ]; then
        echo "v8 API already installed"
    fi
fi

# Apply custom configurations
if [ -f /var/www/html/config.php ]; then
    echo "Applying custom configurations..."
    
    # Create config_override.php for additional settings
    cat > /var/www/html/config_override.php << 'EOF'
<?php
// API v8 Configuration
$sugar_config['api']['enabled'] = true;
$sugar_config['api']['v8']['enabled'] = true;

// JWT Configuration for v8 API
$sugar_config['api']['v8']['jwt']['secret'] = 'your-secret-key-change-in-production';
$sugar_config['api']['v8']['jwt']['expire_after'] = 3600; // 1 hour
$sugar_config['api']['v8']['jwt']['refresh_expire_after'] = 604800; // 7 days

// CORS Configuration
$sugar_config['api']['v8']['cors']['enabled'] = true;
$sugar_config['api']['v8']['cors']['origin'] = ['http://localhost:3000'];
$sugar_config['api']['v8']['cors']['methods'] = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
$sugar_config['api']['v8']['cors']['headers'] = ['Content-Type', 'Authorization', 'X-Requested-With'];

// Redis Cache Configuration
$sugar_config['external_cache_disabled'] = false;
$sugar_config['external_cache']['redis']['host'] = 'redis';
$sugar_config['external_cache']['redis']['port'] = 6379;

// Development settings
$sugar_config['developerMode'] = true;
$sugar_config['logger']['level'] = 'debug';
EOF
    
    chown www-data:www-data config_override.php
fi

# Execute the original command
exec "$@"
```

### 2. Custom Field Definitions

#### 2.1 Create AI Score Field for Leads
`custom/Extension/modules/Leads/Ext/Vardefs/ai_score.php`:
```php
<?php
$dictionary['Lead']['fields']['ai_score'] = array(
    'name' => 'ai_score',
    'vname' => 'LBL_AI_SCORE',
    'type' => 'int',
    'len' => 3,
    'comment' => 'AI-generated lead score (0-100)',
    'default' => 0,
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'unified_search' => false,
    'merge_filter' => 'disabled',
    'min' => 0,
    'max' => 100,
);

$dictionary['Lead']['fields']['ai_score_date'] = array(
    'name' => 'ai_score_date',
    'vname' => 'LBL_AI_SCORE_DATE',
    'type' => 'datetime',
    'comment' => 'Date when AI score was last calculated',
    'required' => false,
    'reportable' => true,
);

$dictionary['Lead']['fields']['ai_score_factors'] = array(
    'name' => 'ai_score_factors',
    'vname' => 'LBL_AI_SCORE_FACTORS',
    'type' => 'text',
    'comment' => 'JSON data of AI scoring factors',
    'required' => false,
    'reportable' => false,
);
```

#### 2.2 Create Language Labels for AI Score
`custom/Extension/modules/Leads/Ext/Language/en_us.ai_score.php`:
```php
<?php
$mod_strings['LBL_AI_SCORE'] = 'AI Score';
$mod_strings['LBL_AI_SCORE_DATE'] = 'AI Score Date';
$mod_strings['LBL_AI_SCORE_FACTORS'] = 'AI Score Factors';
```

#### 2.3 Create Health Score Fields for Accounts
`custom/Extension/modules/Accounts/Ext/Vardefs/health_score.php`:
```php
<?php
$dictionary['Account']['fields']['health_score'] = array(
    'name' => 'health_score',
    'vname' => 'LBL_HEALTH_SCORE',
    'type' => 'int',
    'len' => 3,
    'comment' => 'Customer health score (0-100)',
    'default' => 100,
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'unified_search' => false,
    'merge_filter' => 'disabled',
    'min' => 0,
    'max' => 100,
);

$dictionary['Account']['fields']['mrr'] = array(
    'name' => 'mrr',
    'vname' => 'LBL_MRR',
    'type' => 'currency',
    'comment' => 'Monthly Recurring Revenue',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'duplicate_merge' => 'enabled',
);

$dictionary['Account']['fields']['customer_since'] = array(
    'name' => 'customer_since',
    'vname' => 'LBL_CUSTOMER_SINCE',
    'type' => 'date',
    'comment' => 'Date when account became a customer',
    'required' => false,
    'reportable' => true,
    'audited' => true,
);
```

#### 2.4 Create Language Labels for Health Score
`custom/Extension/modules/Accounts/Ext/Language/en_us.health_score.php`:
```php
<?php
$mod_strings['LBL_HEALTH_SCORE'] = 'Health Score';
$mod_strings['LBL_MRR'] = 'Monthly Recurring Revenue';
$mod_strings['LBL_CUSTOMER_SINCE'] = 'Customer Since';
```

### 3. Custom API Endpoints Setup

#### 3.1 Create API Route Configuration
`custom/api/v8/routes/routes.php`:
```php
<?php
// Custom API routes for Phase 1
// These will be implemented in later phases but we set up the structure now

return [
    // Dashboard metrics endpoint
    'dashboard_metrics' => [
        'method' => 'GET',
        'route' => '/dashboard/metrics',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getMetrics',
        'secure' => true,
    ],
    
    // Lead AI scoring endpoint (Phase 3)
    'lead_ai_score' => [
        'method' => 'POST',
        'route' => '/leads/{id}/ai-score',
        'class' => 'Api\V8\Custom\Controller\AIController',
        'function' => 'scoreLead',
        'secure' => true,
    ],
];
```

#### 3.2 Create Basic Dashboard Controller
`custom/api/v8/controllers/DashboardController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class DashboardController extends BaseController
{
    public function getMetrics(Request $request, Response $response, array $args)
    {
        global $db;
        
        // Get total leads
        $leadsQuery = "SELECT COUNT(*) as total FROM leads WHERE deleted = 0";
        $leadsResult = $db->query($leadsQuery);
        $totalLeads = $leadsResult->fetch_assoc()['total'];
        
        // Get total accounts
        $accountsQuery = "SELECT COUNT(*) as total FROM accounts WHERE deleted = 0";
        $accountsResult = $db->query($accountsQuery);
        $totalAccounts = $accountsResult->fetch_assoc()['total'];
        
        // Get today's leads
        $today = date('Y-m-d');
        $todayLeadsQuery = "SELECT COUNT(*) as total FROM leads 
                           WHERE deleted = 0 
                           AND DATE(date_entered) = '$today'";
        $todayResult = $db->query($todayLeadsQuery);
        $newLeadsToday = $todayResult->fetch_assoc()['total'];
        
        // Pipeline value will be calculated in Phase 2 with Opportunities
        $pipelineValue = 0;
        
        return $response->withJson([
            'data' => [
                'total_leads' => (int)$totalLeads,
                'total_accounts' => (int)$totalAccounts,
                'new_leads_today' => (int)$newLeadsToday,
                'pipeline_value' => $pipelineValue,
            ]
        ]);
    }
}
```

### 4. Database Setup and Seeding

#### 4.1 Create Database Init Script
`docker/mysql/init.sql`:
```sql
-- Create database if not exists
CREATE DATABASE IF NOT EXISTS suitecrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suitecrm;

-- Grant privileges
GRANT ALL PRIVILEGES ON suitecrm.* TO 'suitecrm'@'%';
FLUSH PRIVILEGES;
```

#### 4.2 Create Seed Data Script
`custom/install/seed_data.php`:
```php
<?php
// This script will be run after SuiteCRM installation to add demo data

require_once('include/utils.php');
require_once('modules/Leads/Lead.php');
require_once('modules/Accounts/Account.php');
require_once('modules/Users/User.php');

// Get admin user
$admin = new User();
$admin->retrieve_by_string_fields(array('user_name' => 'admin'));

// Seed Leads
$leadSources = ['Website', 'Referral', 'Social Media', 'Email', 'Cold Call'];
$leadStatuses = ['New', 'Contacted', 'Qualified', 'Lost'];

$leadData = [
    [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john.smith@techcorp.com',
        'account_name' => 'TechCorp Inc.',
        'title' => 'CTO',
        'phone_mobile' => '555-0101',
        'lead_source' => 'Website',
        'status' => 'New',
        'ai_score' => 85,
    ],
    [
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'email' => 'sarah.j@innovate.io',
        'account_name' => 'Innovate.io',
        'title' => 'VP Sales',
        'phone_mobile' => '555-0102',
        'lead_source' => 'Referral',
        'status' => 'Contacted',
        'ai_score' => 72,
    ],
    [
        'first_name' => 'Michael',
        'last_name' => 'Brown',
        'email' => 'mbrown@startup.com',
        'account_name' => 'StartupXYZ',
        'title' => 'Founder',
        'phone_mobile' => '555-0103',
        'lead_source' => 'Social Media',
        'status' => 'Qualified',
        'ai_score' => 92,
    ],
    [
        'first_name' => 'Emily',
        'last_name' => 'Davis',
        'email' => 'emily@enterprise.com',
        'account_name' => 'Enterprise Solutions Ltd',
        'title' => 'Director of IT',
        'phone_mobile' => '555-0104',
        'lead_source' => 'Email',
        'status' => 'New',
        'ai_score' => 68,
    ],
    [
        'first_name' => 'Robert',
        'last_name' => 'Wilson',
        'email' => 'rwilson@globaltech.com',
        'account_name' => 'GlobalTech',
        'title' => 'CEO',
        'phone_mobile' => '555-0105',
        'lead_source' => 'Website',
        'status' => 'New',
        'ai_score' => 95,
    ],
];

foreach ($leadData as $data) {
    $lead = new Lead();
    foreach ($data as $field => $value) {
        $lead->$field = $value;
    }
    $lead->assigned_user_id = $admin->id;
    $lead->ai_score_date = date('Y-m-d H:i:s');
    $lead->ai_score_factors = json_encode([
        'company_size' => rand(15, 20),
        'industry_match' => rand(10, 15),
        'behavior_score' => rand(20, 25),
        'engagement' => rand(15, 20),
        'budget_signals' => rand(15, 20),
    ]);
    $lead->save();
}

// Seed Accounts
$accountTypes = ['Customer', 'Prospect', 'Partner'];
$industries = ['Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing'];

$accountData = [
    [
        'name' => 'Acme Corporation',
        'phone_office' => '555-1001',
        'website' => 'https://acmecorp.com',
        'industry' => 'Technology',
        'annual_revenue' => '5000000',
        'employees' => '100-250',
        'account_type' => 'Customer',
        'health_score' => 85,
        'mrr' => 4500,
        'customer_since' => '2023-01-15',
    ],
    [
        'name' => 'Global Innovations Inc',
        'phone_office' => '555-1002',
        'website' => 'https://globalinnovations.com',
        'industry' => 'Healthcare',
        'annual_revenue' => '10000000',
        'employees' => '250-500',
        'account_type' => 'Customer',
        'health_score' => 92,
        'mrr' => 8500,
        'customer_since' => '2022-06-20',
    ],
    [
        'name' => 'TechStart Solutions',
        'phone_office' => '555-1003',
        'website' => 'https://techstart.io',
        'industry' => 'Technology',
        'annual_revenue' => '2000000',
        'employees' => '50-100',
        'account_type' => 'Prospect',
        'health_score' => 0,
        'mrr' => 0,
    ],
    [
        'name' => 'Enterprise Systems Ltd',
        'phone_office' => '555-1004',
        'website' => 'https://enterprisesystems.com',
        'industry' => 'Finance',
        'annual_revenue' => '25000000',
        'employees' => '1000+',
        'account_type' => 'Customer',
        'health_score' => 78,
        'mrr' => 15000,
        'customer_since' => '2021-03-10',
    ],
];

foreach ($accountData as $data) {
    $account = new Account();
    foreach ($data as $field => $value) {
        $account->$field = $value;
    }
    $account->assigned_user_id = $admin->id;
    $account->save();
}

echo "Demo data seeded successfully!\n";
```

### 5. API Testing and Documentation

#### 5.1 Create Postman Collection
`docs/SuiteCRM_API_Collection.json`:
```json
{
  "info": {
    "name": "SuiteCRM v8 API - Phase 1",
    "description": "API endpoints for B2B CRM Platform",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "auth": {
    "type": "bearer",
    "bearer": [
      {
        "key": "token",
        "value": "{{access_token}}",
        "type": "string"
      }
    ]
  },
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8080/api/v8"
    },
    {
      "key": "access_token",
      "value": ""
    },
    {
      "key": "refresh_token",
      "value": ""
    }
  ],
  "item": [
    {
      "name": "Authentication",
      "item": [
        {
          "name": "Login",
          "event": [
            {
              "listen": "test",
              "script": {
                "exec": [
                  "const response = pm.response.json();",
                  "pm.collectionVariables.set('access_token', response.access_token);",
                  "pm.collectionVariables.set('refresh_token', response.refresh_token);"
                ],
                "type": "text/javascript"
              }
            }
          ],
          "request": {
            "method": "POST",
            "header": [],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"grant_type\": \"password\",\n    \"client_id\": \"sugar\",\n    \"username\": \"admin\",\n    \"password\": \"admin123\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            },
            "url": {
              "raw": "{{base_url}}/login",
              "host": ["{{base_url}}"],
              "path": ["login"]
            }
          }
        },
        {
          "name": "Refresh Token",
          "request": {
            "method": "POST",
            "header": [],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"refresh_token\": \"{{refresh_token}}\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            },
            "url": {
              "raw": "{{base_url}}/refresh",
              "host": ["{{base_url}}"],
              "path": ["refresh"]
            }
          }
        }
      ]
    },
    {
      "name": "Leads",
      "item": [
        {
          "name": "Get All Leads",
          "request": {
            "method": "GET",
            "header": [],
            "url": {
              "raw": "{{base_url}}/module/Leads?page[number]=1&page[size]=20",
              "host": ["{{base_url}}"],
              "path": ["module", "Leads"],
              "query": [
                {
                  "key": "page[number]",
                  "value": "1"
                },
                {
                  "key": "page[size]",
                  "value": "20"
                }
              ]
            }
          }
        },
        {
          "name": "Create Lead",
          "request": {
            "method": "POST",
            "header": [],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"data\": {\n        \"type\": \"Leads\",\n        \"attributes\": {\n            \"first_name\": \"Test\",\n            \"last_name\": \"Lead\",\n            \"email\": \"test@example.com\",\n            \"status\": \"New\",\n            \"lead_source\": \"Website\"\n        }\n    }\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            },
            "url": {
              "raw": "{{base_url}}/module/Leads",
              "host": ["{{base_url}}"],
              "path": ["module", "Leads"]
            }
          }
        }
      ]
    }
  ]
}
```

### 6. Startup and Configuration Scripts

#### 6.1 Create Startup Script
`scripts/start.sh`:
```bash
#!/bin/bash

echo "Starting SuiteCRM Backend..."

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo "Docker is not running. Please start Docker and try again."
    exit 1
fi

# Build and start containers
docker-compose up -d --build

# Wait for services to be ready
echo "Waiting for services to start..."
sleep 10

# Check if SuiteCRM is accessible
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|301\|302"; then
        echo "SuiteCRM is ready!"
        break
    fi
    echo "Waiting for SuiteCRM to be ready... (attempt $((attempt+1))/$max_attempts)"
    sleep 5
    attempt=$((attempt+1))
done

if [ $attempt -eq $max_attempts ]; then
    echo "SuiteCRM failed to start. Check the logs:"
    docker-compose logs suitecrm
    exit 1
fi

# Run Quick Repair and Rebuild
echo "Running Quick Repair and Rebuild..."
docker exec suitecrm_app php -f repair.php

# Seed demo data
echo "Seeding demo data..."
docker exec suitecrm_app php -f custom/install/seed_data.php

echo "Backend setup complete!"
echo "Access SuiteCRM at: http://localhost:8080"
echo "API endpoint: http://localhost:8080/api/v8"
echo "Admin credentials: admin / admin123"
```

#### 6.2 Create Repair Script
`repair.php`:
```php
<?php
// Quick Repair and Rebuild script
if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

global $current_user;
$current_user = new User();
$current_user->getSystemUser();

$repair = new RepairAndClear();
$repair->repairAndClearAll(
    ['clearAll'],
    ['Leads', 'Accounts'],
    true,
    false
);

echo "Quick Repair and Rebuild completed successfully!\n";
```

### 7. Testing Setup

#### 7.1 Create Backend Test Structure
```bash
mkdir -p tests/backend/{unit,integration,fixtures}
```

#### 7.2 Create PHPUnit Configuration
`tests/backend/phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="bootstrap.php"
         colors="true"
         verbose="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>unit</directory>
        </testsuite>
        <testsuite name="Integration Tests">
            <directory>integration</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">../../custom</directory>
        </whitelist>
    </filter>
</phpunit>
```

#### 7.3 Create Test Bootstrap
`tests/backend/bootstrap.php`:
```php
<?php
// Set up SuiteCRM environment for testing
if (!defined('sugarEntry')) define('sugarEntry', true);

// Set the working directory
chdir(dirname(__FILE__) . '/../..');

// Include SuiteCRM files
require_once('include/entryPoint.php');

// Set up test database connection
global $sugar_config;
$sugar_config['dbconfig']['db_name'] = 'suitecrm_test';
```

#### 7.4 Create API Integration Test
`tests/backend/integration/ApiAuthTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;

class ApiAuthTest extends TestCase
{
    protected $apiUrl = 'http://localhost:8080/api/v8';
    protected $client;
    
    public function setUp(): void
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 10.0,
        ]);
    }
    
    public function testLoginSuccess()
    {
        $response = $this->client->post('/login', [
            'json' => [
                'grant_type' => 'password',
                'client_id' => 'sugar',
                'username' => 'admin',
                'password' => 'admin123',
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
    }
    
    public function testLoginFailure()
    {
        $this->expectException(\GuzzleHttp\Exception\ClientException::class);
        
        $response = $this->client->post('/login', [
            'json' => [
                'grant_type' => 'password',
                'client_id' => 'sugar',
                'username' => 'admin',
                'password' => 'wrongpassword',
            ]
        ]);
    }
}
```

### 8. Integration Testing Script
`tests/integration/test-frontend-backend.sh`:
```bash
#!/bin/bash

echo "Testing Frontend-Backend Integration..."

# Test 1: API Health Check
echo "1. Testing API availability..."
if curl -f -s http://localhost:8080/api/v8/login > /dev/null; then
    echo "✓ API is accessible"
else
    echo "✗ API is not accessible"
    exit 1
fi

# Test 2: Authentication
echo "2. Testing authentication..."
AUTH_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/login \
    -H "Content-Type: application/json" \
    -d '{
        "grant_type": "password",
        "client_id": "sugar",
        "username": "admin",
        "password": "admin123"
    }')

ACCESS_TOKEN=$(echo $AUTH_RESPONSE | jq -r '.access_token')
if [ "$ACCESS_TOKEN" != "null" ] && [ -n "$ACCESS_TOKEN" ]; then
    echo "✓ Authentication successful"
else
    echo "✗ Authentication failed"
    echo "Response: $AUTH_RESPONSE"
    exit 1
fi

# Test 3: CORS Headers
echo "3. Testing CORS headers..."
CORS_HEADERS=$(curl -s -I -X OPTIONS http://localhost:8080/api/v8/module/Leads \
    -H "Origin: http://localhost:3000" \
    -H "Access-Control-Request-Method: GET" \
    -H "Access-Control-Request-Headers: Authorization")

if echo "$CORS_HEADERS" | grep -q "Access-Control-Allow-Origin"; then
    echo "✓ CORS headers present"
else
    echo "✗ CORS headers missing"
    exit 1
fi

# Test 4: Fetch Leads
echo "4. Testing Leads API..."
LEADS_RESPONSE=$(curl -s -X GET http://localhost:8080/api/v8/module/Leads \
    -H "Authorization: Bearer $ACCESS_TOKEN")

if echo "$LEADS_RESPONSE" | jq -e '.data' > /dev/null 2>&1; then
    echo "✓ Leads API working"
    LEAD_COUNT=$(echo "$LEADS_RESPONSE" | jq '.data | length')
    echo "  Found $LEAD_COUNT leads"
else
    echo "✗ Leads API failed"
    echo "Response: $LEADS_RESPONSE"
    exit 1
fi

# Test 5: Create a Lead
echo "5. Testing Lead creation..."
CREATE_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/module/Leads \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Leads",
            "attributes": {
                "first_name": "Integration",
                "last_name": "Test",
                "email": "integration@test.com",
                "status": "New"
            }
        }
    }')

if echo "$CREATE_RESPONSE" | jq -e '.data.id' > /dev/null 2>&1; then
    echo "✓ Lead creation successful"
    LEAD_ID=$(echo "$CREATE_RESPONSE" | jq -r '.data.id')
    echo "  Created lead ID: $LEAD_ID"
else
    echo "✗ Lead creation failed"
    echo "Response: $CREATE_RESPONSE"
    exit 1
fi

echo ""
echo "All integration tests passed! ✓"
```

## Definition of Success

### ✅ Phase 1 Backend Success Criteria:

1. **Docker Environment**
   - [ ] Docker containers start successfully
   - [ ] MySQL database is accessible
   - [ ] Redis cache is running
   - [ ] SuiteCRM is installed and accessible at http://localhost:8080

2. **SuiteCRM Configuration**
   - [ ] v8 API is enabled and accessible
   - [ ] JWT authentication is working
   - [ ] CORS headers are properly configured
   - [ ] Admin user can log in (admin/admin123)

3. **Custom Fields**
   - [ ] AI score fields added to Leads module
   - [ ] Health score and MRR fields added to Accounts module
   - [ ] Fields appear in API responses
   - [ ] Quick Repair and Rebuild completes successfully

4. **API Functionality**
   - [ ] Authentication endpoint returns JWT tokens
   - [ ] Token refresh mechanism works
   - [ ] Leads CRUD operations work via API
   - [ ] Accounts CRUD operations work via API
   - [ ] Pagination works correctly
   - [ ] Error responses follow JSON:API spec

5. **Demo Data**
   - [ ] At least 5 demo leads created
   - [ ] At least 4 demo accounts created
   - [ ] AI scores populated for leads
   - [ ] Health scores populated for customer accounts

6. **Testing**
   - [ ] API authentication tests pass
   - [ ] CORS tests pass
   - [ ] Integration tests with frontend pass
   - [ ] Postman collection works for all endpoints

### Manual Verification Steps:
1. Start Docker containers: `./scripts/start.sh`
2. Wait for installation to complete
3. Access SuiteCRM UI: http://localhost:8080
4. Login with admin/admin123
5. Navigate to Leads module and verify custom AI score field
6. Navigate to Accounts module and verify health score and MRR fields
7. Test API with Postman collection
8. Run integration tests: `./tests/integration/test-frontend-backend.sh`
9. Verify frontend can authenticate and fetch data

### Integration Checklist:
- [ ] Frontend at http://localhost:3000 can authenticate
- [ ] Frontend receives and displays leads data
- [ ] Frontend can create/update leads
- [ ] Frontend receives and displays accounts data
- [ ] Frontend can create/update accounts
- [ ] No CORS errors in browser console
- [ ] JWT token refresh works automatically

### Common Issues and Solutions:
1. **Permission errors**: Run `chmod -R 775 custom cache upload`
2. **API not found**: Ensure v8 API is installed, run repair script
3. **CORS errors**: Check Apache config and config_override.php
4. **Database connection**: Verify MySQL container is healthy
5. **Slow performance**: Check Redis is connected

### Next Phase Preview:
Phase 2 will add:
- Opportunities module with pipeline stages
- Activities (Calls, Meetings, Tasks, Notes)
- Cases module configuration
- Email viewing integration
- Enhanced dashboard with custom metrics endpoint