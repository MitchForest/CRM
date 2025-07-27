<?php
// Test routing path parsing

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v8/kb/categories';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "Original path: $path\n";

// Handle different access patterns
if (strpos($path, '/api/') !== false) {
    // When accessed via /api/...
    $path = preg_replace('#^(/custom)?/api(/index\.php)?#', '', $path);
    echo "After /api/ handling: $path\n";
} elseif (strpos($path, '/custom/api/') !== false) {
    // When accessed directly via /custom/api/index.php
    $path = preg_replace('#^/custom/api(/index\.php)?#', '', $path);
    echo "After /custom/api/ handling: $path\n";
}

// Check routes
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/Router.php';

$router = new Api\Router();
configureRoutes($router);

echo "\nChecking if route exists for: $path\n";
echo "Routes registered:\n";

// Show KB routes
$routes = [
    '/kb/categories',
    '/kb/articles', 
    '/kb/articles/{id}',
    '/kb/search'
];

foreach ($routes as $route) {
    echo "  - $route\n";
}