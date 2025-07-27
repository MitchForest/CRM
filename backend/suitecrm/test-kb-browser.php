<?php
/**
 * Browser-accessible test for KB functionality
 * Access at: http://localhost:8080/test-kb-browser.php
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'config.php';
require_once 'include/database/DBManagerFactory.php';

global $db;
$db = DBManagerFactory::getInstance();

header('Content-Type: text/plain');

echo "=== KNOWLEDGE BASE DATABASE TEST ===\n\n";

// Test 1: Check if tables exist
echo "1. Checking KB tables exist:\n";
$tables = [
    'aok_knowledgebase',
    'aok_knowledge_base_categories', 
    'aok_knowledgebase_categories'
];

foreach ($tables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $db->query($query);
    $exists = $db->fetchByAssoc($result);
    echo "   - $table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\n2. Checking article count:\n";
$query = "SELECT COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0";
$result = $db->query($query);
$row = $db->fetchByAssoc($result);
echo "   - Total articles: " . ($row['count'] ?? 0) . "\n";

echo "\n3. Sample articles:\n";
$query = "SELECT id, name, status FROM aok_knowledgebase WHERE deleted = 0 LIMIT 5";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "   - {$row['name']} (ID: {$row['id']}, Status: {$row['status']})\n";
}

echo "\n4. Testing PDO vs SuiteCRM query style:\n";
// This will fail if PDO is used
try {
    $stmt = $db->prepare("SELECT * FROM aok_knowledgebase LIMIT 1");
    echo "   - PDO prepare(): ERROR - This should fail!\n";
} catch (Exception $e) {
    echo "   - PDO prepare(): EXPECTED FAILURE - " . get_class($e) . "\n";
}

// This should work
try {
    $result = $db->query("SELECT * FROM aok_knowledgebase LIMIT 1");
    $row = $db->fetchByAssoc($result);
    echo "   - SuiteCRM query(): SUCCESS\n";
} catch (Exception $e) {
    echo "   - SuiteCRM query(): FAILED - " . $e->getMessage() . "\n";
}

echo "\n5. Testing parameter escaping:\n";
$unsafe = "test's \"quoted\" value";
$safe = $db->quote($unsafe);
echo "   - Original: $unsafe\n";
echo "   - Escaped: $safe\n";

echo "\n=== END OF TEST ===\n";