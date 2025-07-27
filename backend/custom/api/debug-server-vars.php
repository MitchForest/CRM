<?php
// Debug server variables when accessed via Apache

echo "=== SERVER VARIABLES ===\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'not set') . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'not set') . "\n";
echo "\n";

// Parse the path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "Parsed path: $path\n";

// Test routing
if (strpos($path, '/api/v8/') !== false) {
    $processed = preg_replace('#^/api/v8#', '', $path);
    echo "After /api/v8 processing: $processed\n";
}