<?php
/**
 * Test KB controller using API bootstrap
 */

// Use the API's init file
require_once __DIR__ . '/init.php';

// Now test the controller
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/controllers/KnowledgeBaseController.php';

header('Content-Type: text/plain');
echo "=== KB CONTROLLER TEST ===\n\n";

$controller = new Api\Controllers\KnowledgeBaseController();

// Test 1: Get Categories
echo "1. Testing getCategories():\n";
try {
    $request = new Api\Request('GET', '/kb/categories', []);
    $response = $controller->getCategories($request);
    $data = $response->getData();
    
    if (is_array($data) && isset($data['data'])) {
        echo "   SUCCESS: " . count($data['data']) . " categories\n";
        print_r($data);
    } else {
        echo "   FAILED: Invalid response\n";
        echo "   Response: " . json_encode($data) . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n2. Testing getArticles():\n";
try {
    // Simulate query parameters for testing
    $_GET = ['limit' => '5'];
    $request = new Api\Request('GET', '/kb/articles', []);
    $response = $controller->getArticles($request);
    $data = $response->getData();
    $_GET = []; // Clean up
    
    if (is_array($data) && isset($data['data'])) {
        echo "   SUCCESS: " . count($data['data']) . " articles\n";
        echo "   Total: " . ($data['meta']['total'] ?? 'unknown') . "\n";
        
        // Show article details
        foreach ($data['data'] as $idx => $article) {
            echo "   Article $idx: {$article['title']} (ID: {$article['id']})\n";
        }
    } else {
        echo "   FAILED: Invalid response\n";
        echo "   Response: " . json_encode($data) . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== END TEST ===\n";