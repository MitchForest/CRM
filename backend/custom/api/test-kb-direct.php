<?php
/**
 * Direct test of Knowledge Base controller
 * Run this to validate our PDO conversions are correct
 */

// Bootstrap minimal SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
chdir(realpath(__DIR__ . '/../..'));
require_once 'config.php';

// Set up database connection
require_once 'include/database/DBManagerFactory.php';
global $db;
$db = DBManagerFactory::getInstance();

// Test our KB controller directly
require_once __DIR__ . '/controllers/KnowledgeBaseController.php';
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';

$controller = new Api\Controllers\KnowledgeBaseController();

echo "=== TESTING KNOWLEDGE BASE CONTROLLER ===\n\n";

// Test 1: Get Categories
echo "TEST 1: Get Categories\n";
echo "----------------------\n";
try {
    $request = new Api\Request('GET', '/kb/categories', [], []);
    $response = $controller->getCategories($request);
    $data = json_decode($response->getBody(), true);
    
    if (isset($data['data'])) {
        echo "SUCCESS: Got " . count($data['data']) . " categories\n";
        foreach ($data['data'] as $cat) {
            echo "  - {$cat['name']} (ID: {$cat['id']}, Articles: {$cat['article_count']})\n";
        }
    } else {
        echo "FAIL: " . json_encode($data) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 2: Get Articles
echo "TEST 2: Get Articles\n";
echo "--------------------\n";
try {
    $request = new Api\Request('GET', '/kb/articles', ['limit' => 5], []);
    $response = $controller->getArticles($request);
    $data = json_decode($response->getBody(), true);
    
    if (isset($data['data'])) {
        echo "SUCCESS: Got " . count($data['data']) . " articles\n";
        echo "Total articles: " . ($data['meta']['total'] ?? 'unknown') . "\n";
        foreach ($data['data'] as $article) {
            echo "  - {$article['title']} (ID: {$article['id']})\n";
        }
    } else {
        echo "FAIL: " . json_encode($data) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 3: Search Articles
echo "TEST 3: Search Articles\n";
echo "-----------------------\n";
try {
    $request = new Api\Request('GET', '/kb/search', ['q' => 'test'], []);
    $response = $controller->searchArticles($request);
    $data = json_decode($response->getBody(), true);
    
    if (isset($data['data'])) {
        echo "SUCCESS: Search returned " . count($data['data']['results']) . " results\n";
        foreach ($data['data']['results'] as $result) {
            echo "  - {$result['title']}\n";
        }
    } else {
        echo "FAIL: " . json_encode($data) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 4: Database Query Test
echo "TEST 4: Direct Database Query Test\n";
echo "----------------------------------\n";
try {
    // Test if our query style works
    $query = "SELECT COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0";
    $result = $db->query($query);
    $row = $db->fetchByAssoc($result);
    echo "Total KB articles in database: " . ($row['count'] ?? 0) . "\n";
    
    // Test parameter escaping
    $testParam = "test's article";
    $safeParam = $db->quote($testParam);
    echo "Parameter escaping test: '$testParam' => $safeParam\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END OF TESTS ===\n";