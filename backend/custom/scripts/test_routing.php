<?php
// Test routing
echo "Testing routing paths...\n\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/custom/api/index.php/auth/login';

echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Parsed path: " . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . "\n";

// Test regex
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "\nOriginal path: $path\n";

// Remove /custom/api/index.php prefix
$path = preg_replace('#^/custom/api/index\.php#', '', $path);
echo "After removing prefix: $path\n";

// Test with different patterns
$test_uris = [
    '/api/auth/login',
    '/custom/api/auth/login',
    '/custom/api/index.php/auth/login'
];

echo "\nTesting different URI patterns:\n";
foreach ($test_uris as $uri) {
    $path = parse_url($uri, PHP_URL_PATH);
    echo "\nURI: $uri\n";
    echo "Parsed: $path\n";
    
    // Apply routing logic
    if (strpos($path, '/api/') !== false) {
        $result = preg_replace('#^(/custom)?/api(/index\.php)?#', '', $path);
    } elseif (strpos($path, '/custom/api/') !== false) {
        $result = preg_replace('#^/custom/api(/index\.php)?#', '', $path);
    } else {
        $result = $path;
    }
    echo "Result: $result\n";
}