<?php
// Test native V8 API access to Knowledge Base

// First, let's check if we can access the KB module directly
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

global $db;
$db = DBManagerFactory::getInstance();

echo "=== Testing Native SuiteCRM Knowledge Base ===\n\n";

// 1. Check if KB tables exist
echo "1. Checking KB tables:\n";
$tables = ['aok_knowledgebase', 'aok_knowledge_base_categories'];
foreach ($tables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $db->query($query);
    $exists = $db->fetchByAssoc($result);
    echo "   - $table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

// 2. Count KB articles
echo "\n2. Knowledge Base Articles:\n";
$query = "SELECT COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0";
$result = $db->query($query);
$row = $db->fetchByAssoc($result);
echo "   - Total articles: " . ($row['count'] ?? 0) . "\n";

// 3. List some articles
echo "\n3. Sample articles:\n";
$query = "SELECT id, name, status FROM aok_knowledgebase WHERE deleted = 0 LIMIT 5";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "   - {$row['name']} (Status: {$row['status']})\n";
}

// 4. Check module definition
echo "\n4. Module configuration:\n";
if (file_exists('modules/AOK_KnowledgeBase/AOK_KnowledgeBase.php')) {
    echo "   - AOK_KnowledgeBase module: EXISTS\n";
    require_once 'modules/AOK_KnowledgeBase/AOK_KnowledgeBase.php';
    $kb = new AOK_KnowledgeBase();
    echo "   - Module table: " . $kb->table_name . "\n";
    echo "   - Object name: " . $kb->object_name . "\n";
    echo "   - Module name: " . $kb->module_name . "\n";
} else {
    echo "   - AOK_KnowledgeBase module: MISSING\n";
}

echo "\n=== END TEST ===\n";