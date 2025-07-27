<?php
// Test KB controller directly
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

// Include the custom API controller
require_once 'custom/api/controllers/BaseController.php';
require_once 'custom/api/controllers/KnowledgeBaseController.php';

// Include Request/Response classes
require_once 'custom/api/Request.php';
require_once 'custom/api/Response.php';

echo "=== Testing KB Controller Directly ===\n\n";

try {
    // Create controller instance
    $controller = new \Api\Controllers\KnowledgeBaseController();
    
    // Create mock request
    $request = new \Api\Request('GET', '/kb/categories', []);
    
    // Test getCategories
    echo "1. Testing getCategories():\n";
    $response = $controller->getCategories($request);
    
    if ($response) {
        $data = $response->getData();
        echo "   Categories: " . count($data['data'] ?? []) . "\n";
        foreach (($data['data'] ?? []) as $cat) {
            echo "   - " . $cat['name'] . "\n";
        }
    } else {
        echo "   No response received\n";
    }
    
    // Test getArticles
    echo "\n2. Testing getArticles():\n";
    $response = $controller->getArticles($request);
    
    if ($response) {
        $data = $response->getData();
        echo "   Articles: " . count($data['data'] ?? []) . "\n";
        echo "   Total: " . ($data['meta']['total'] ?? 0) . "\n";
        foreach (($data['data'] ?? []) as $index => $article) {
            if ($index < 3) {
                echo "   - " . ($article['title'] ?? 'Untitled') . "\n";
            }
        }
    } else {
        echo "   No response received\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== END TEST ===\n";