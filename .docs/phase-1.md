# Phase 1: Foundation & Backend API - Implementation Plan

## Overview

Phase 1 establishes the foundation for our headless B2C CRM by setting up the development environment, configuring SuiteCRM, and building the API layer. By the end of this phase, we'll have a fully functional backend API that the React frontend can consume.

**Duration**: 3 weeks  
**Team Size**: 1-2 backend developers

## Week 1: Environment Setup & SuiteCRM Configuration

### Day 1-2: Docker Environment Setup

#### 1. Create Project Structure
```bash
mkdir suite-b2c-crm
cd suite-b2c-crm

# Create directory structure
mkdir -p frontend backend docker/{frontend,backend} docs tests
touch docker-compose.yml .env.example README.md
```

#### 2. Create Docker Compose Configuration
```yaml
# docker-compose.yml
version: '3.8'

services:
  backend:
    build: ./docker/backend
    container_name: suitecrm-backend
    ports:
      - "8080:80"
    environment:
      - MYSQL_HOST=db
      - MYSQL_DATABASE=suitecrm
      - MYSQL_USER=suitecrm
      - MYSQL_PASSWORD=suitecrm123
      - SITE_URL=http://localhost:8080
      - REDIS_HOST=cache
    volumes:
      - ./backend:/var/www/html
      - ./docker/backend/php.ini:/usr/local/etc/php/php.ini
    depends_on:
      - db
      - cache
    networks:
      - crm-network

  db:
    image: mariadb:10.11
    container_name: suitecrm-db
    environment:
      - MYSQL_ROOT_PASSWORD=root123
      - MYSQL_DATABASE=suitecrm
      - MYSQL_USER=suitecrm
      - MYSQL_PASSWORD=suitecrm123
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/db/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "3306:3306"
    networks:
      - crm-network

  cache:
    image: redis:7-alpine
    container_name: suitecrm-cache
    ports:
      - "6379:6379"
    networks:
      - crm-network

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: suitecrm-phpmyadmin
    environment:
      - PMA_HOST=db
      - PMA_USER=root
      - PMA_PASSWORD=root123
    ports:
      - "8081:80"
    depends_on:
      - db
    networks:
      - crm-network

volumes:
  db_data:

networks:
  crm-network:
    driver: bridge
```

#### 3. Create Backend Dockerfile
```dockerfile
# docker/backend/Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libicu-dev \
    libzip-dev \
    redis-tools \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN pecl install redis && docker-php-ext-enable redis

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/cache /var/www/html/upload /var/www/html/custom
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
```

#### 4. Apache Configuration
```apache
# docker/backend/apache.conf
<VirtualHost *:80>
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # API specific routing
    <Directory /var/www/html/custom/api>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

#### 5. PHP Configuration
```ini
# docker/backend/php.ini
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE
display_errors = On
log_errors = On
date.timezone = "UTC"

[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```

### Day 3-4: SuiteCRM Installation & Configuration

#### 1. Clone and Prepare SuiteCRM
```bash
# Clone SuiteCRM into backend directory
cd backend
git clone https://github.com/salesagility/SuiteCRM.git .
composer install --no-dev

# Set permissions
chmod -R 775 cache custom modules themes upload
chmod 775 config_override.php
```

#### 2. Create Installation Helper Script
```bash
# backend/install-suite.sh
#!/bin/bash

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
while ! mysqladmin ping -h"db" -P"3306" --silent; do
    sleep 1
done

echo "MySQL is ready!"

# Run SuiteCRM silent installer
php -r "
\$config = array(
    'setup_db_host_name' => 'db',
    'setup_db_database_name' => 'suitecrm',
    'setup_db_admin_user_name' => 'root',
    'setup_db_admin_password' => 'root123',
    'setup_db_drop_tables' => 0,
    'setup_db_create_database' => 1,
    'setup_db_pop_demo_data' => 0,
    'setup_site_admin_user_name' => 'admin',
    'setup_site_admin_password' => 'admin123',
    'setup_site_url' => 'http://localhost:8080',
    'default_currency_iso4217' => 'USD',
    'default_currency_name' => 'US Dollar',
    'default_currency_symbol' => '$',
    'default_date_format' => 'Y-m-d',
    'default_time_format' => 'H:i',
    'default_decimal_seperator' => '.',
    'default_export_charset' => 'UTF-8',
    'default_language' => 'en_us',
    'default_locale_name_format' => 's f l',
    'default_number_grouping_seperator' => ',',
    'export_delimiter' => ',',
    'license_key' => 'PUBLIC'
);

require_once('install/install_utils.php');
require_once('install/install_defaults.php');
install_perform_install(\$config);
"

echo "SuiteCRM installation complete!"
```

#### 3. Module Configuration Script
```php
# backend/custom/scripts/configure_modules.php
<?php
// Disable modules we don't need for B2C
$modulesToDisable = [
    'Accounts',
    'Targets',
    'TargetLists', 
    'Campaigns',
    'CampaignLog',
    'CampaignTrackers',
    'ProspectLists',
    'Prospects',
    'Surveys',
    'Bugs',
    'Project',
    'ProjectTask',
    'AM_ProjectTemplates',
    'AOK_KnowledgeBase',
    'AOK_Knowledge_Base_Categories',
    'jjwg_Maps',
    'jjwg_Markers',
    'jjwg_Areas',
    'jjwg_Address_Cache',
    'AOR_Reports',
    'AOR_Scheduled_Reports',
    'AOW_WorkFlow',
    'AOW_Processed',
    'AOW_Conditions',
    'AOW_Actions',
    'AOP_AOP_Case_Events',
    'AOP_AOP_Case_Updates',
    'Events',
    'FP_events',
    'FP_Event_Locations'
];

// Update module visibility
$moduleFile = 'include/modules.php';
if (file_exists($moduleFile)) {
    require_once($moduleFile);
    
    foreach ($modulesToDisable as $module) {
        if (isset($beanList[$module])) {
            unset($modInvisList[$module]);
            $modInvisList[] = $module;
        }
    }
    
    // Write back to file
    write_array_to_file('beanList', $beanList, $moduleFile);
    write_array_to_file('beanFiles', $beanFiles, $moduleFile);
    write_array_to_file('modInvisList', $modInvisList, $moduleFile);
}

// Clear cache
require_once('modules/Administration/QuickRepairAndRebuild.php');
$repair = new RepairAndClear();
$repair->repairAndClearAll(['clearAll'], ['All Modules'], true, false);

echo "Module configuration complete!\n";
```

### Day 5: Create API Foundation

#### 1. API Directory Structure
```bash
# Create API structure
mkdir -p backend/custom/api/{auth,controllers,middleware,utils}
touch backend/custom/api/{index.php,routes.php,.htaccess}
```

#### 2. API .htaccess
```apache
# backend/custom/api/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Add CORS headers
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, Authorization"
```

#### 3. API Bootstrap File
```php
# backend/custom/api/index.php
<?php
// Prevent direct access
if (!defined('sugarEntry')) define('sugarEntry', true);

// Change working directory to SuiteCRM root
chdir('../..');

// Include SuiteCRM bootstrap
require_once('include/entryPoint.php');
require_once('include/utils.php');
require_once('data/BeanFactory.php');

// API autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Api\\';
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize router
require_once 'Router.php';
require_once 'routes.php';

$router = new Api\Router();
configureRoutes($router);
$router->dispatch();
```

## Week 2: API Development

### Day 1-2: Core API Infrastructure

#### 1. Router Implementation
```php
# backend/custom/api/Router.php
<?php
namespace Api;

class Router {
    private $routes = [];
    private $middleware = [];
    
    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }
    
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->convertPathToRegex($path)
        ];
    }
    
    private function convertPathToRegex($path) {
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/custom/api', '', $path);
        
        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Create request object
        $request = new Request($method, $path, $this->getRequestData());
        
        // Run middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware->handle($request);
            if ($result === false) {
                return;
            }
        }
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);
                
                // Call handler
                list($class, $method) = explode('::', $route['handler']);
                $controller = new $class();
                $response = $controller->$method($request);
                
                // Send response
                $this->sendResponse($response);
                return;
            }
        }
        
        // No route found
        $this->sendError(404, 'Route not found');
    }
    
    private function getRequestData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        
        return $_POST;
    }
    
    private function sendResponse($response) {
        header('Content-Type: application/json');
        
        if ($response instanceof Response) {
            http_response_code($response->getStatusCode());
            echo json_encode($response->getData());
        } else {
            echo json_encode($response);
        }
    }
    
    private function sendError($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    }
}
```

#### 2. Request/Response Classes
```php
# backend/custom/api/Request.php
<?php
namespace Api;

class Request {
    private $method;
    private $path;
    private $data;
    private $params = [];
    private $headers = [];
    
    public function __construct($method, $path, $data) {
        $this->method = $method;
        $this->path = $path;
        $this->data = $data;
        $this->headers = getallheaders();
    }
    
    public function getMethod() {
        return $this->method;
    }
    
    public function getPath() {
        return $this->path;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }
    
    public function setParams($params) {
        $this->params = $params;
    }
    
    public function getParam($key, $default = null) {
        return $this->params[$key] ?? $default;
    }
    
    public function getHeader($key) {
        return $this->headers[$key] ?? null;
    }
    
    public function getAuthToken() {
        $auth = $this->getHeader('Authorization');
        if ($auth && strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }
        return null;
    }
}

# backend/custom/api/Response.php
<?php
namespace Api;

class Response {
    private $data;
    private $statusCode;
    
    public function __construct($data = null, $statusCode = 200) {
        $this->data = $data;
        $this->statusCode = $statusCode;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public static function success($data = null) {
        return new self(['success' => true, 'data' => $data], 200);
    }
    
    public static function created($data = null) {
        return new self(['success' => true, 'data' => $data], 201);
    }
    
    public static function error($message, $statusCode = 400) {
        return new self(['success' => false, 'error' => $message], $statusCode);
    }
    
    public static function notFound($message = 'Resource not found') {
        return new self(['success' => false, 'error' => $message], 404);
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        return new self(['success' => false, 'error' => $message], 401);
    }
}
```

### Day 3-4: Authentication System

#### 1. JWT Implementation
```php
# backend/custom/api/auth/JWT.php
<?php
namespace Api\Auth;

class JWT {
    private static $secret;
    private static $algorithm = 'HS256';
    
    public static function setSecret($secret) {
        self::$secret = $secret;
    }
    
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) != 3) {
            throw new \Exception('Invalid token format');
        }
        
        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);
        $signatureProvided = $parts[2];
        
        $base64Header = $parts[0];
        $base64Payload = $parts[1];
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($base64Signature !== $signatureProvided) {
            throw new \Exception('Invalid signature');
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token expired');
        }
        
        return $payload;
    }
}

// Set JWT secret from config
JWT::setSecret($_ENV['JWT_SECRET'] ?? 'your-secret-key-here');
```

#### 2. Auth Controller
```php
# backend/custom/api/controllers/AuthController.php
<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Api\Auth\JWT;

class AuthController extends BaseController {
    
    public function login(Request $request) {
        $username = $request->get('username');
        $password = $request->get('password');
        
        if (!$username || !$password) {
            return Response::error('Username and password required', 400);
        }
        
        // Authenticate using SuiteCRM auth
        $authController = new \AuthenticationController();
        
        if ($authController->login($username, md5($password), false)) {
            global $current_user;
            
            // Generate JWT token
            $payload = [
                'user_id' => $current_user->id,
                'username' => $current_user->user_name,
                'email' => $current_user->email1,
                'exp' => time() + (24 * 60 * 60), // 24 hours
                'iat' => time()
            ];
            
            $token = JWT::encode($payload);
            
            // Generate refresh token
            $refreshPayload = [
                'user_id' => $current_user->id,
                'type' => 'refresh',
                'exp' => time() + (30 * 24 * 60 * 60), // 30 days
                'iat' => time()
            ];
            
            $refreshToken = JWT::encode($refreshPayload);
            
            // Store refresh token in database
            $this->storeRefreshToken($current_user->id, $refreshToken);
            
            return Response::success([
                'accessToken' => $token,
                'refreshToken' => $refreshToken,
                'user' => [
                    'id' => $current_user->id,
                    'username' => $current_user->user_name,
                    'email' => $current_user->email1,
                    'firstName' => $current_user->first_name,
                    'lastName' => $current_user->last_name
                ]
            ]);
        }
        
        return Response::unauthorized('Invalid credentials');
    }
    
    public function refresh(Request $request) {
        $refreshToken = $request->get('refreshToken');
        
        if (!$refreshToken) {
            return Response::error('Refresh token required', 400);
        }
        
        try {
            $payload = JWT::decode($refreshToken);
            
            if ($payload['type'] !== 'refresh') {
                return Response::error('Invalid token type', 400);
            }
            
            // Verify refresh token in database
            if (!$this->verifyRefreshToken($payload['user_id'], $refreshToken)) {
                return Response::unauthorized('Invalid refresh token');
            }
            
            // Generate new access token
            $user = \BeanFactory::getBean('Users', $payload['user_id']);
            
            $newPayload = [
                'user_id' => $user->id,
                'username' => $user->user_name,
                'email' => $user->email1,
                'exp' => time() + (24 * 60 * 60),
                'iat' => time()
            ];
            
            $newToken = JWT::encode($newPayload);
            
            return Response::success([
                'accessToken' => $newToken
            ]);
            
        } catch (\Exception $e) {
            return Response::unauthorized($e->getMessage());
        }
    }
    
    public function logout(Request $request) {
        $token = $request->getAuthToken();
        
        if ($token) {
            try {
                $payload = JWT::decode($token);
                $this->removeRefreshToken($payload['user_id']);
            } catch (\Exception $e) {
                // Token might be invalid, but we still return success
            }
        }
        
        return Response::success(['message' => 'Logged out successfully']);
    }
    
    private function storeRefreshToken($userId, $token) {
        global $db;
        
        // Remove old tokens
        $db->query("DELETE FROM api_refresh_tokens WHERE user_id = '$userId'");
        
        // Store new token
        $id = create_guid();
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
        
        $db->query("INSERT INTO api_refresh_tokens (id, user_id, token, expires_at, created_at) 
                    VALUES ('$id', '$userId', '$token', '$expires', NOW())");
    }
    
    private function verifyRefreshToken($userId, $token) {
        global $db;
        
        $result = $db->query("SELECT * FROM api_refresh_tokens 
                              WHERE user_id = '$userId' 
                              AND token = '$token' 
                              AND expires_at > NOW()");
        
        return $db->fetchByAssoc($result) !== false;
    }
    
    private function removeRefreshToken($userId) {
        global $db;
        $db->query("DELETE FROM api_refresh_tokens WHERE user_id = '$userId'");
    }
}
```

#### 3. Auth Middleware
```php
# backend/custom/api/middleware/AuthMiddleware.php
<?php
namespace Api\Middleware;

use Api\Request;
use Api\Response;
use Api\Auth\JWT;

class AuthMiddleware {
    private $publicRoutes = [
        'POST:/auth/login',
        'POST:/auth/refresh'
    ];
    
    public function handle(Request $request) {
        $method = $request->getMethod();
        $path = $request->getPath();
        $route = $method . ':' . $path;
        
        // Check if route is public
        foreach ($this->publicRoutes as $publicRoute) {
            if (strpos($route, $publicRoute) === 0) {
                return true;
            }
        }
        
        // Get token
        $token = $request->getAuthToken();
        
        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No token provided']);
            return false;
        }
        
        try {
            $payload = JWT::decode($token);
            
            // Set current user
            global $current_user;
            $current_user = \BeanFactory::getBean('Users', $payload['user_id']);
            
            if (empty($current_user->id)) {
                throw new \Exception('User not found');
            }
            
            // Add user info to request
            $request->user = $current_user;
            
            return true;
            
        } catch (\Exception $e) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid token: ' . $e->getMessage()]);
            return false;
        }
    }
}
```

### Day 5: Module API Controllers

#### 1. Base Controller
```php
# backend/custom/api/controllers/BaseController.php
<?php
namespace Api\Controllers;

use Api\Response;

abstract class BaseController {
    
    protected function formatBean($bean, $fields = []) {
        $data = [];
        
        if (empty($fields)) {
            // Default fields based on module
            $fields = $this->getDefaultFields($bean->module_name);
        }
        
        foreach ($fields as $field) {
            if (isset($bean->field_defs[$field])) {
                $data[$field] = $bean->$field;
            }
        }
        
        return $data;
    }
    
    protected function getDefaultFields($module) {
        $defaultFields = [
            'Contacts' => ['id', 'first_name', 'last_name', 'email1', 'phone_mobile', 
                          'date_entered', 'date_modified', 'description'],
            'Leads' => ['id', 'first_name', 'last_name', 'email1', 'phone_mobile', 
                       'status', 'lead_source', 'date_entered'],
            'Opportunities' => ['id', 'name', 'amount', 'sales_stage', 'probability', 
                               'date_closed', 'description'],
            'Tasks' => ['id', 'name', 'status', 'priority', 'date_due', 'description', 
                       'contact_id', 'parent_type', 'parent_id'],
            'Emails' => ['id', 'name', 'date_sent', 'status', 'type', 'parent_type', 
                        'parent_id', 'from_addr', 'to_addrs']
        ];
        
        return $defaultFields[$module] ?? ['id', 'name', 'date_entered', 'date_modified'];
    }
    
    protected function buildWhereClause($filters) {
        $where = [];
        
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                // Handle operators
                foreach ($value as $op => $val) {
                    switch ($op) {
                        case 'like':
                            $where[] = "$field LIKE '%$val%'";
                            break;
                        case 'gt':
                            $where[] = "$field > '$val'";
                            break;
                        case 'lt':
                            $where[] = "$field < '$val'";
                            break;
                        case 'in':
                            $inValues = array_map(function($v) { return "'$v'"; }, $val);
                            $where[] = "$field IN (" . implode(',', $inValues) . ")";
                            break;
                        default:
                            $where[] = "$field = '$val'";
                    }
                }
            } else {
                $where[] = "$field = '$value'";
            }
        }
        
        return implode(' AND ', $where);
    }
    
    protected function getPaginationParams($request) {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        $limit = min($limit, 100); // Max 100 records
        
        $offset = ($page - 1) * $limit;
        
        return [$limit, $offset];
    }
}
```

#### 2. Contacts Controller
```php
# backend/custom/api/controllers/ContactsController.php
<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class ContactsController extends BaseController {
    
    public function list(Request $request) {
        $bean = \BeanFactory::newBean('Contacts');
        
        // Get filters
        $filters = $request->get('filters', []);
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $request->get('sort', 'last_name');
        $sortOrder = $request->get('order', 'ASC');
        
        // Get pagination
        list($limit, $offset) = $this->getPaginationParams($request);
        
        // Build query
        $query = $bean->create_new_list_query(
            "$sortField $sortOrder",
            $where,
            [],
            [],
            0,
            '',
            true,
            $bean,
            true
        );
        
        // Get total count
        $countResult = $bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = $bean->db->fetchByAssoc($countResult)['total'];
        
        // Add limit and offset
        $query .= " LIMIT $limit OFFSET $offset";
        
        // Execute query
        $result = $bean->db->query($query);
        $contacts = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $contact = \BeanFactory::newBean('Contacts');
            $contact->populateFromRow($row);
            $contacts[] = $this->formatBean($contact);
        }
        
        return Response::success([
            'data' => $contacts,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function get(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        // Get additional data
        $data = $this->formatBean($contact);
        
        // Add calculated fields
        $data['lifetimeValue'] = $this->calculateLifetimeValue($contact);
        $data['lastActivityDate'] = $this->getLastActivityDate($contact);
        
        return Response::success($data);
    }
    
    public function create(Request $request) {
        $contact = \BeanFactory::newBean('Contacts');
        
        // Set fields
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'description'];
        foreach ($fields as $field) {
            if ($request->get($field)) {
                $contact->$field = $request->get($field);
            }
        }
        
        // Validate required fields
        if (empty($contact->last_name)) {
            return Response::error('Last name is required', 400);
        }
        
        // Save
        $contact->save();
        
        return Response::created($this->formatBean($contact));
    }
    
    public function update(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        // Update fields
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'description'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $contact->$field = $request->get($field);
            }
        }
        
        // Save
        $contact->save();
        
        return Response::success($this->formatBean($contact));
    }
    
    public function delete(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        $contact->mark_deleted($id);
        
        return Response::success(['message' => 'Contact deleted successfully']);
    }
    
    public function activities(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        $activities = [];
        
        // Get related activities from different modules
        $modules = [
            'Tasks' => ['name', 'status', 'date_due', 'priority'],
            'Emails' => ['name', 'status', 'date_sent'],
            'Calls' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes'],
            'Meetings' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes'],
            'Notes' => ['name', 'description', 'date_entered']
        ];
        
        foreach ($modules as $module => $fields) {
            $bean = \BeanFactory::newBean($module);
            
            // Build where clause
            $where = "parent_type = 'Contacts' AND parent_id = '$id'";
            if ($module === 'Tasks') {
                $where = "contact_id = '$id'";
            }
            
            $query = $bean->create_new_list_query('date_entered DESC', $where);
            $result = $bean->db->query($query);
            
            while ($row = $bean->db->fetchByAssoc($result)) {
                $activity = [
                    'id' => $row['id'],
                    'type' => strtolower($module),
                    'module' => $module,
                    'date' => $row['date_entered'] ?? $row['date_start'] ?? $row['date_due']
                ];
                
                foreach ($fields as $field) {
                    if (isset($row[$field])) {
                        $activity[$field] = $row[$field];
                    }
                }
                
                $activities[] = $activity;
            }
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Apply pagination
        list($limit, $offset) = $this->getPaginationParams($request);
        $total = count($activities);
        $activities = array_slice($activities, $offset, $limit);
        
        return Response::success([
            'data' => $activities,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    private function calculateLifetimeValue($contact) {
        // This would calculate based on opportunities, orders, etc.
        // For now, return a placeholder
        return 0;
    }
    
    private function getLastActivityDate($contact) {
        global $db;
        
        $query = "SELECT MAX(date_entered) as last_date FROM (
            SELECT date_entered FROM tasks WHERE contact_id = '{$contact->id}' AND deleted = 0
            UNION
            SELECT date_entered FROM emails WHERE parent_type = 'Contacts' AND parent_id = '{$contact->id}' AND deleted = 0
            UNION
            SELECT date_entered FROM calls WHERE parent_type = 'Contacts' AND parent_id = '{$contact->id}' AND deleted = 0
            UNION
            SELECT date_entered FROM meetings WHERE parent_type = 'Contacts' AND parent_id = '{$contact->id}' AND deleted = 0
        ) as activities";
        
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        return $row['last_date'] ?? null;
    }
}
```

## Week 3: Complete API & Testing

### Day 1-2: Remaining API Controllers

#### 1. Leads Controller
```php
# backend/custom/api/controllers/LeadsController.php
<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class LeadsController extends BaseController {
    
    public function list(Request $request) {
        // Similar to ContactsController::list but for Leads
        $bean = \BeanFactory::newBean('Leads');
        
        $filters = $request->get('filters', []);
        $where = $this->buildWhereClause($filters);
        
        list($limit, $offset) = $this->getPaginationParams($request);
        
        $query = $bean->create_new_list_query(
            'date_entered DESC',
            $where
        );
        
        $countResult = $bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = $bean->db->fetchByAssoc($countResult)['total'];
        
        $query .= " LIMIT $limit OFFSET $offset";
        $result = $bean->db->query($query);
        
        $leads = [];
        while ($row = $bean->db->fetchByAssoc($result)) {
            $lead = \BeanFactory::newBean('Leads');
            $lead->populateFromRow($row);
            $leads[] = $this->formatBean($lead);
        }
        
        return Response::success([
            'data' => $leads,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function convert(Request $request) {
        $id = $request->getParam('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return Response::notFound('Lead not found');
        }
        
        // Create contact from lead
        $contact = \BeanFactory::newBean('Contacts');
        $contact->first_name = $lead->first_name;
        $contact->last_name = $lead->last_name;
        $contact->email1 = $lead->email1;
        $contact->phone_mobile = $lead->phone_mobile;
        $contact->description = $lead->description;
        $contact->lead_source = $lead->lead_source;
        $contact->save();
        
        // Create opportunity if requested
        $opportunity = null;
        if ($request->get('create_opportunity')) {
            $opportunity = \BeanFactory::newBean('Opportunities');
            $opportunity->name = $request->get('opportunity_name', $contact->first_name . ' ' . $contact->last_name . ' - Opportunity');
            $opportunity->amount = $request->get('opportunity_amount', 0);
            $opportunity->sales_stage = 'Prospecting';
            $opportunity->probability = 10;
            $opportunity->date_closed = date('Y-m-d', strtotime('+30 days'));
            $opportunity->save();
            
            // Relate to contact
            $opportunity->load_relationship('contacts');
            $opportunity->contacts->add($contact->id);
        }
        
        // Mark lead as converted
        $lead->status = 'Converted';
        $lead->contact_id = $contact->id;
        $lead->converted = 1;
        $lead->save();
        
        return Response::success([
            'contact' => $this->formatBean($contact),
            'opportunity' => $opportunity ? $this->formatBean($opportunity) : null
        ]);
    }
}
```

#### 2. Activities Controller
```php
# backend/custom/api/controllers/ActivitiesController.php
<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class ActivitiesController extends BaseController {
    
    public function list(Request $request) {
        $activities = [];
        
        // Define modules and their fields
        $modules = [
            'Tasks' => [
                'fields' => ['name', 'status', 'priority', 'date_due', 'contact_id', 'parent_type', 'parent_id'],
                'date_field' => 'date_due'
            ],
            'Emails' => [
                'fields' => ['name', 'status', 'date_sent', 'parent_type', 'parent_id', 'from_addr', 'to_addrs'],
                'date_field' => 'date_sent'
            ],
            'Calls' => [
                'fields' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes', 'parent_type', 'parent_id'],
                'date_field' => 'date_start'
            ],
            'Meetings' => [
                'fields' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes', 'parent_type', 'parent_id'],
                'date_field' => 'date_start'
            ],
            'Notes' => [
                'fields' => ['name', 'description', 'date_entered', 'parent_type', 'parent_id'],
                'date_field' => 'date_entered'
            ]
        ];
        
        // Get filters
        $filters = $request->get('filters', []);
        $typeFilter = $filters['type'] ?? null;
        $contactFilter = $filters['contact_id'] ?? null;
        $statusFilter = $filters['status'] ?? null;
        
        // Fetch from each module
        foreach ($modules as $module => $config) {
            if ($typeFilter && strtolower($module) !== strtolower($typeFilter)) {
                continue;
            }
            
            $bean = \BeanFactory::newBean($module);
            $where = [];
            
            if ($contactFilter) {
                if ($module === 'Tasks') {
                    $where[] = "contact_id = '$contactFilter'";
                } else {
                    $where[] = "(parent_type = 'Contacts' AND parent_id = '$contactFilter')";
                }
            }
            
            if ($statusFilter && in_array('status', $config['fields'])) {
                $where[] = "status = '$statusFilter'";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = $bean->create_new_list_query(
                $config['date_field'] . ' DESC',
                $whereClause
            );
            
            $result = $bean->db->query($query . " LIMIT 100");
            
            while ($row = $bean->db->fetchByAssoc($result)) {
                $activity = [
                    'id' => $row['id'],
                    'type' => strtolower($module),
                    'date' => $row[$config['date_field']] ?? $row['date_entered']
                ];
                
                foreach ($config['fields'] as $field) {
                    if (isset($row[$field])) {
                        $activity[$field] = $row[$field];
                    }
                }
                
                $activities[] = $activity;
            }
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Pagination
        list($limit, $offset) = $this->getPaginationParams($request);
        $total = count($activities);
        $activities = array_slice($activities, $offset, $limit);
        
        return Response::success([
            'data' => $activities,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function create(Request $request) {
        $type = $request->get('type');
        
        if (!$type) {
            return Response::error('Activity type is required', 400);
        }
        
        $moduleMap = [
            'task' => 'Tasks',
            'email' => 'Emails',
            'call' => 'Calls',
            'meeting' => 'Meetings',
            'note' => 'Notes'
        ];
        
        $module = $moduleMap[$type] ?? null;
        
        if (!$module) {
            return Response::error('Invalid activity type', 400);
        }
        
        $bean = \BeanFactory::newBean($module);
        
        // Set common fields
        $bean->name = $request->get('subject', $request->get('name'));
        
        if ($request->get('contact_id')) {
            if ($module === 'Tasks') {
                $bean->contact_id = $request->get('contact_id');
            } else {
                $bean->parent_type = 'Contacts';
                $bean->parent_id = $request->get('contact_id');
            }
        }
        
        // Set type-specific fields
        switch ($module) {
            case 'Tasks':
                $bean->status = $request->get('status', 'Not Started');
                $bean->priority = $request->get('priority', 'Medium');
                $bean->date_due = $request->get('date_due');
                break;
                
            case 'Emails':
                $bean->status = $request->get('status', 'sent');
                $bean->date_sent = $request->get('date_sent', date('Y-m-d H:i:s'));
                $bean->to_addrs = $request->get('to_addrs');
                $bean->from_addr = $request->get('from_addr');
                $bean->description_html = $request->get('body');
                break;
                
            case 'Calls':
            case 'Meetings':
                $bean->status = $request->get('status', 'Planned');
                $bean->date_start = $request->get('date_start');
                $bean->duration_hours = $request->get('duration_hours', 0);
                $bean->duration_minutes = $request->get('duration_minutes', 30);
                break;
                
            case 'Notes':
                $bean->description = $request->get('description');
                break;
        }
        
        $bean->save();
        
        return Response::created([
            'id' => $bean->id,
            'type' => $type
        ]);
    }
}
```

### Day 3: Database Setup & Routes

#### 1. Create Database Tables
```sql
# backend/custom/api/sql/api_tables.sql

-- Table for storing refresh tokens
CREATE TABLE IF NOT EXISTS api_refresh_tokens (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table for API rate limiting
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    requests INT DEFAULT 0,
    window_start DATETIME NOT NULL,
    INDEX idx_user_endpoint (user_id, endpoint),
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table for API logs (optional)
CREATE TABLE IF NOT EXISTS api_logs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36),
    method VARCHAR(10),
    endpoint VARCHAR(255),
    request_data TEXT,
    response_code INT,
    response_time FLOAT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME NOT NULL,
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

#### 2. Route Configuration
```php
# backend/custom/api/routes.php
<?php
function configureRoutes($router) {
    // Add middleware
    $router->addMiddleware(new \Api\Middleware\AuthMiddleware());
    
    // Authentication routes
    $router->post('/auth/login', 'Api\Controllers\AuthController::login');
    $router->post('/auth/refresh', 'Api\Controllers\AuthController::refresh');
    $router->post('/auth/logout', 'Api\Controllers\AuthController::logout');
    
    // Contact routes
    $router->get('/contacts', 'Api\Controllers\ContactsController::list');
    $router->get('/contacts/:id', 'Api\Controllers\ContactsController::get');
    $router->post('/contacts', 'Api\Controllers\ContactsController::create');
    $router->put('/contacts/:id', 'Api\Controllers\ContactsController::update');
    $router->delete('/contacts/:id', 'Api\Controllers\ContactsController::delete');
    $router->get('/contacts/:id/activities', 'Api\Controllers\ContactsController::activities');
    
    // Lead routes
    $router->get('/leads', 'Api\Controllers\LeadsController::list');
    $router->get('/leads/:id', 'Api\Controllers\LeadsController::get');
    $router->post('/leads', 'Api\Controllers\LeadsController::create');
    $router->put('/leads/:id', 'Api\Controllers\LeadsController::update');
    $router->delete('/leads/:id', 'Api\Controllers\LeadsController::delete');
    $router->post('/leads/:id/convert', 'Api\Controllers\LeadsController::convert');
    
    // Opportunity routes
    $router->get('/opportunities', 'Api\Controllers\OpportunitiesController::list');
    $router->get('/opportunities/:id', 'Api\Controllers\OpportunitiesController::get');
    $router->post('/opportunities', 'Api\Controllers\OpportunitiesController::create');
    $router->put('/opportunities/:id', 'Api\Controllers\OpportunitiesController::update');
    $router->delete('/opportunities/:id', 'Api\Controllers\OpportunitiesController::delete');
    
    // Activity routes (aggregated)
    $router->get('/activities', 'Api\Controllers\ActivitiesController::list');
    $router->post('/activities', 'Api\Controllers\ActivitiesController::create');
    $router->get('/activities/upcoming', 'Api\Controllers\ActivitiesController::upcoming');
    
    // Task routes (specific)
    $router->get('/tasks', 'Api\Controllers\TasksController::list');
    $router->get('/tasks/:id', 'Api\Controllers\TasksController::get');
    $router->post('/tasks', 'Api\Controllers\TasksController::create');
    $router->put('/tasks/:id', 'Api\Controllers\TasksController::update');
    $router->delete('/tasks/:id', 'Api\Controllers\TasksController::delete');
    
    // Quote routes
    $router->get('/quotes', 'Api\Controllers\QuotesController::list');
    $router->get('/quotes/:id', 'Api\Controllers\QuotesController::get');
    $router->post('/quotes', 'Api\Controllers\QuotesController::create');
    $router->put('/quotes/:id', 'Api\Controllers\QuotesController::update');
    $router->delete('/quotes/:id', 'Api\Controllers\QuotesController::delete');
    
    // Case (Support Ticket) routes
    $router->get('/cases', 'Api\Controllers\CasesController::list');
    $router->get('/cases/:id', 'Api\Controllers\CasesController::get');
    $router->post('/cases', 'Api\Controllers\CasesController::create');
    $router->put('/cases/:id', 'Api\Controllers\CasesController::update');
    $router->delete('/cases/:id', 'Api\Controllers\CasesController::delete');
    
    // Dashboard routes
    $router->get('/dashboard/stats', 'Api\Controllers\DashboardController::stats');
    $router->get('/dashboard/activities', 'Api\Controllers\DashboardController::recentActivities');
    $router->get('/dashboard/pipeline', 'Api\Controllers\DashboardController::pipeline');
}
```

### Day 4-5: Testing Suite

#### 1. Postman Collection
```json
{
  "info": {
    "name": "SuiteCRM B2C API",
    "description": "API collection for testing SuiteCRM headless endpoints",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "auth": {
    "type": "bearer",
    "bearer": [
      {
        "key": "token",
        "value": "{{accessToken}}",
        "type": "string"
      }
    ]
  },
  "variable": [
    {
      "key": "baseUrl",
      "value": "http://localhost:8080/custom/api"
    },
    {
      "key": "accessToken",
      "value": ""
    },
    {
      "key": "refreshToken",
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
                  "if (pm.response.code === 200) {",
                  "    const response = pm.response.json();",
                  "    pm.environment.set('accessToken', response.data.accessToken);",
                  "    pm.environment.set('refreshToken', response.data.refreshToken);",
                  "    pm.environment.set('userId', response.data.user.id);",
                  "}"
                ]
              }
            }
          ],
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"username\": \"admin\",\n    \"password\": \"admin123\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/auth/login",
              "host": ["{{baseUrl}}"],
              "path": ["auth", "login"]
            }
          }
        },
        {
          "name": "Refresh Token",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"refreshToken\": \"{{refreshToken}}\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/auth/refresh",
              "host": ["{{baseUrl}}"],
              "path": ["auth", "refresh"]
            }
          }
        }
      ]
    },
    {
      "name": "Contacts",
      "item": [
        {
          "name": "List Contacts",
          "request": {
            "method": "GET",
            "header": [],
            "url": {
              "raw": "{{baseUrl}}/contacts?page=1&limit=20",
              "host": ["{{baseUrl}}"],
              "path": ["contacts"],
              "query": [
                {
                  "key": "page",
                  "value": "1"
                },
                {
                  "key": "limit",
                  "value": "20"
                }
              ]
            }
          }
        },
        {
          "name": "Create Contact",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"first_name\": \"John\",\n    \"last_name\": \"Doe\",\n    \"email1\": \"john.doe@example.com\",\n    \"phone_mobile\": \"+1234567890\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/contacts",
              "host": ["{{baseUrl}}"],
              "path": ["contacts"]
            }
          }
        },
        {
          "name": "Get Contact Activities",
          "request": {
            "method": "GET",
            "header": [],
            "url": {
              "raw": "{{baseUrl}}/contacts/:id/activities",
              "host": ["{{baseUrl}}"],
              "path": ["contacts", ":id", "activities"],
              "variable": [
                {
                  "key": "id",
                  "value": "{{contactId}}"
                }
              ]
            }
          }
        }
      ]
    },
    {
      "name": "Activities",
      "item": [
        {
          "name": "List All Activities",
          "request": {
            "method": "GET",
            "header": [],
            "url": {
              "raw": "{{baseUrl}}/activities?page=1&limit=50",
              "host": ["{{baseUrl}}"],
              "path": ["activities"],
              "query": [
                {
                  "key": "page",
                  "value": "1"
                },
                {
                  "key": "limit",
                  "value": "50"
                }
              ]
            }
          }
        },
        {
          "name": "Create Task",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n    \"type\": \"task\",\n    \"subject\": \"Follow up with customer\",\n    \"contact_id\": \"{{contactId}}\",\n    \"status\": \"Not Started\",\n    \"priority\": \"High\",\n    \"date_due\": \"2024-12-31\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/activities",
              "host": ["{{baseUrl}}"],
              "path": ["activities"]
            }
          }
        }
      ]
    }
  ]
}
```

#### 2. API Testing Script
```bash
#!/bin/bash
# backend/custom/api/test-api.sh

API_URL="http://localhost:8080/custom/api"
USERNAME="admin"
PASSWORD="admin123"

echo "Testing SuiteCRM API..."

# Test login
echo -e "\n1. Testing Login..."
LOGIN_RESPONSE=$(curl -s -X POST $API_URL/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}")

ACCESS_TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"accessToken":"[^"]*' | grep -o '[^"]*$')

if [ -z "$ACCESS_TOKEN" ]; then
    echo "Login failed!"
    echo $LOGIN_RESPONSE
    exit 1
fi

echo "Login successful! Token: ${ACCESS_TOKEN:0:20}..."

# Test contacts endpoint
echo -e "\n2. Testing Contacts List..."
CONTACTS_RESPONSE=$(curl -s -X GET $API_URL/contacts \
  -H "Authorization: Bearer $ACCESS_TOKEN")

echo $CONTACTS_RESPONSE | python -m json.tool | head -20

# Test create contact
echo -e "\n3. Testing Create Contact..."
CREATE_RESPONSE=$(curl -s -X POST $API_URL/contacts \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "email1": "test@example.com"
  }')

CONTACT_ID=$(echo $CREATE_RESPONSE | grep -o '"id":"[^"]*' | grep -o '[^"]*$')
echo "Created contact with ID: $CONTACT_ID"

# Test activities
echo -e "\n4. Testing Activities..."
ACTIVITIES_RESPONSE=$(curl -s -X GET $API_URL/activities \
  -H "Authorization: Bearer $ACCESS_TOKEN")

echo $ACTIVITIES_RESPONSE | python -m json.tool | head -20

echo -e "\nAPI tests completed!"
```

## Deliverables Checklist

### Week 1 Deliverables
- [ ] Docker environment fully configured
- [ ] SuiteCRM installed and running
- [ ] Modules configured (disabled unnecessary ones)
- [ ] API directory structure created
- [ ] Basic routing system implemented

### Week 2 Deliverables
- [ ] JWT authentication working
- [ ] All core API endpoints implemented:
  - [ ] Auth (login, refresh, logout)
  - [ ] Contacts (CRUD + activities)
  - [ ] Leads (CRUD + convert)
  - [ ] Opportunities (CRUD)
  - [ ] Activities (list, create)
  - [ ] Tasks (CRUD)
  - [ ] Quotes (CRUD)
  - [ ] Cases (CRUD)
- [ ] Request/Response handling
- [ ] Error handling
- [ ] Pagination support

### Week 3 Deliverables
- [ ] Database tables created
- [ ] Postman collection complete
- [ ] API documentation
- [ ] Testing scripts
- [ ] Performance optimization
- [ ] Security hardening
- [ ] Ready for frontend development

## Next Steps

After completing Phase 1, you'll have:
1. A fully functional API backend
2. All endpoints tested and documented
3. Docker environment ready for development
4. Foundation ready for React frontend in Phase 2

The API can be tested immediately using Postman or curl, and the frontend team can begin development against real endpoints rather than mocks.