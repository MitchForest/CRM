<?php
/**
 * Configure SuiteCRM for Headless/API-Only Mode
 * This script modifies SuiteCRM to work as a pure API backend
 */

// Prevent direct access
if (!defined('sugarEntry')) define('sugarEntry', true);

// Include SuiteCRM bootstrap first
$rootPath = dirname(__FILE__) . '/../..';
require_once($rootPath . '/include/entryPoint.php');

// Now change to SuiteCRM root
chdir($rootPath);

echo "Configuring SuiteCRM for headless mode...\n";

// 1. Update config_override.php to disable UI elements
$configOverride = <<<'PHP'
<?php
// Headless mode configuration for SaaS CRM

// Disable UI features
$sugar_config['disable_persistent_connections'] = false;
$sugar_config['disable_count_query'] = true;
$sugar_config['save_query'] = 'populate_only';
$sugar_config['verify_client_ip'] = false;

// API-specific settings
$sugar_config['site_url'] = 'http://localhost:8080';
$sugar_config['api_enabled'] = true;
$sugar_config['rest_enabled'] = true;

// Performance optimizations
$sugar_config['dump_slow_queries'] = false;
$sugar_config['slow_query_time_msec'] = 5000;
$sugar_config['max_record_fetch_size'] = 1000;
$sugar_config['list_max_entries_per_page'] = 50;

// Security settings for API
$sugar_config['api_cors_enabled'] = true;
$sugar_config['api_cors_allowed_origins'] = ['http://localhost:5173', 'http://localhost:3000'];
$sugar_config['api_cors_allowed_methods'] = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
$sugar_config['api_cors_allowed_headers'] = ['Content-Type', 'Authorization', 'X-Requested-With'];

// Disable unnecessary features
$sugar_config['disable_user_email_config'] = true;
$sugar_config['hide_subpanels'] = false;
$sugar_config['hide_history_contacts_emails'] = false;

// Cache settings
$sugar_config['external_cache_disabled'] = false;
$sugar_config['external_cache_disabled_apc'] = false;

// Session handling for API
$sugar_config['session_gc_maxlifetime'] = 86400; // 24 hours
$sugar_config['api_token_expiry'] = 3600; // 1 hour

PHP;

// Write config override
file_put_contents('config_override.php', $configOverride);
echo "✓ Created config_override.php\n";

// 2. Create .htaccess to redirect all non-API requests to React
$htaccess = <<<'HTACCESS'
# SuiteCRM Headless Mode Configuration

<IfModule mod_rewrite.c>
    Options +FollowSymLinks
    RewriteEngine On
    
    # Allow access to API endpoints
    RewriteCond %{REQUEST_URI} ^/custom/api [OR]
    RewriteCond %{REQUEST_URI} ^/api [OR]
    RewriteCond %{REQUEST_URI} ^/service [OR]
    RewriteCond %{REQUEST_URI} ^/cache [OR]
    RewriteCond %{REQUEST_URI} ^/upload [OR]
    RewriteCond %{REQUEST_URI} ^/themes [OR]
    RewriteCond %{REQUEST_URI} ^/include/javascript
    RewriteRule ^ - [L]
    
    # Block access to admin UI
    RewriteCond %{REQUEST_URI} ^/index\.php$ [OR]
    RewriteCond %{REQUEST_URI} ^/modules [OR]
    RewriteCond %{REQUEST_URI} ^/Admin
    RewriteRule ^ /api-only.html [L]
    
    # Original SuiteCRM rules for API
    RewriteRule ^cache/jsLanguage/(.._..).js$ index.php?entryPoint=jslang&module=app_strings&lang=$1 [L,QSA]
    RewriteRule ^cache/jsLanguage/(\w*)/(.._..).js$ index.php?entryPoint=jslang&module=$1&lang=$2 [L,QSA]
    
    # Prevent direct access to files
    RewriteRule (?i)\.log$ - [F]
    RewriteRule (?i)\.tpl$ - [F]
    RewriteRule (?i)\.mysql$ - [F]
    RewriteRule (?i)\.sql$ - [F]
    RewriteRule (?i)^.*/(soap|cache|upload|uploads|tmp|temp|log|logs)/.*$ - [F]
</IfModule>

# Security headers for API
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

HTACCESS;

// Backup existing .htaccess if exists
if (file_exists('.htaccess')) {
    copy('.htaccess', '.htaccess.backup');
    echo "✓ Backed up existing .htaccess to .htaccess.backup\n";
}

// Write new .htaccess
file_put_contents('.htaccess', $htaccess);
echo "✓ Created new .htaccess for headless mode\n";

// 3. Create API-only landing page
$apiOnlyPage = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API-Only Mode</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        .endpoints {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
            text-align: left;
        }
        code {
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 API-Only Mode</h1>
        <p>This SuiteCRM instance is configured for headless operation. The traditional UI has been disabled.</p>
        
        <div class="endpoints">
            <strong>Available Endpoints:</strong>
            <ul>
                <li>API Base: <code>/custom/api</code></li>
                <li>Auth: <code>POST /custom/api/auth/login</code></li>
                <li>Contacts: <code>/custom/api/contacts</code></li>
                <li>Leads: <code>/custom/api/leads</code></li>
                <li>More endpoints available...</li>
            </ul>
        </div>
        
        <p>For the frontend application, please access:</p>
        <p><a href="http://localhost:5173">http://localhost:5173</a></p>
        
        <p style="margin-top: 2rem; font-size: 0.9em; color: #999;">
            Powered by SuiteCRM in headless mode
        </p>
    </div>
</body>
</html>
HTML;

file_put_contents('api-only.html', $apiOnlyPage);
echo "✓ Created api-only.html landing page\n";

// 4. Update index.php to redirect to API-only page for web requests
$indexOverride = <<<'PHP'
<?php
/**
 * SuiteCRM Headless Mode Entry Point
 */

// Check if this is an API request
$isApiRequest = (
    strpos($_SERVER['REQUEST_URI'], '/custom/api') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/service') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/api') !== false ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// If not an API request and not accessing allowed resources, redirect to API-only page
if (!$isApiRequest && 
    strpos($_SERVER['REQUEST_URI'], '/cache') === false &&
    strpos($_SERVER['REQUEST_URI'], '/upload') === false &&
    strpos($_SERVER['REQUEST_URI'], '/themes') === false) {
    
    header('Location: /api-only.html');
    exit;
}

// Original SuiteCRM entry point
if (!defined('sugarEntry')) define('sugarEntry', true);

PHP;

// Read existing index.php
$existingIndex = file_get_contents('index.php');

// Only update if not already modified
if (strpos($existingIndex, 'SuiteCRM Headless Mode Entry Point') === false) {
    // Backup original
    copy('index.php', 'index.php.backup');
    echo "✓ Backed up original index.php\n";
    
    // Prepend our code
    $newIndex = $indexOverride . "\n" . $existingIndex;
    file_put_contents('index.php', $newIndex);
    echo "✓ Modified index.php for headless mode\n";
} else {
    echo "✓ index.php already configured for headless mode\n";
}

echo "\n✅ Headless mode configuration complete!\n";
echo "\nNext steps:\n";
echo "1. Restart your web server\n";
echo "2. Clear the cache directory: rm -rf cache/*\n";
echo "3. Access the API at: http://localhost:8080/custom/api\n";
echo "4. Run the React frontend at: http://localhost:5173\n";