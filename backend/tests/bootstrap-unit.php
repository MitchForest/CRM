<?php
/**
 * Unit Test Bootstrap - No Database Required
 */

// Define test environment
define('PHPUNIT_TEST', true);

// Set up paths
$projectRoot = realpath(__DIR__ . '/..');
$apiPath = $projectRoot . '/custom/api';

// Autoload API classes
spl_autoload_register(function ($class) use ($apiPath) {
    // API classes
    $prefix = 'Api\\';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) === 0) {
        $relative_class = substr($class, $len);
        $file = $apiPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Load any required API files that don't depend on SuiteCRM
require_once $apiPath . '/Request.php';
require_once $apiPath . '/Response.php';

echo "Unit test bootstrap loaded.\n";
echo "API Path: {$apiPath}\n";