<?php
// Debug V8 API issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing V8 API OAuth2\n\n";

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/Api/access_token';
$_POST = [
    'grant_type' => 'password',
    'client_id' => 'suitecrm_client',
    'client_secret' => 'secret',
    'username' => 'admin',
    'password' => 'admin123'
];

try {
    chdir('/var/www/html');
    require_once 'Api/index.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}