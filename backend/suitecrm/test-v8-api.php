<?php
// Test V8 API basic functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing SuiteCRM V8 API\n\n";

// Check if API files exist
$apiPath = '/var/www/html/Api/';
echo "1. Checking API files:\n";
echo "   - Api/index.php: " . (file_exists($apiPath . 'index.php') ? "EXISTS" : "MISSING") . "\n";
echo "   - Api/V8/: " . (is_dir($apiPath . 'V8') ? "EXISTS" : "MISSING") . "\n";
echo "   - Api/Core/app.php: " . (file_exists($apiPath . 'Core/app.php') ? "EXISTS" : "MISSING") . "\n";

// Check OAuth2 keys
echo "\n2. Checking OAuth2 keys:\n";
$keysPath = '/var/www/html/Api/V8/OAuth2/';
echo "   - private.key: " . (file_exists($keysPath . 'private.key') ? "EXISTS" : "MISSING") . "\n";
echo "   - public.key: " . (file_exists($keysPath . 'public.key') ? "EXISTS" : "MISSING") . "\n";

// Check .htaccess
echo "\n3. Checking .htaccess:\n";
echo "   - Api/.htaccess: " . (file_exists($apiPath . '.htaccess') ? "EXISTS" : "MISSING") . "\n";
if (file_exists($apiPath . '.htaccess')) {
    echo "   Contents:\n";
    echo file_get_contents($apiPath . '.htaccess');
}

echo "\n4. Checking mod_rewrite:\n";
$modules = apache_get_modules();
echo "   - mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "ENABLED" : "DISABLED") . "\n";