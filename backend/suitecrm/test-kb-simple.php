<?php
/**
 * Simple KB test - minimal dependencies
 */

// Initialize properly
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'config.php';

// Include necessary files
require_once 'include/TimeDate.php';
$GLOBALS['timedate'] = new TimeDate();

require_once 'include/database/DBManagerFactory.php';
global $db;
$db = DBManagerFactory::getInstance();
$GLOBALS['db'] = $db;

// Simple output
header('Content-Type: text/plain');
echo "=== SIMPLE KB TEST ===\n\n";

// Test 1: Direct query
try {
    echo "1. Testing direct query:\n";
    $query = "SELECT COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0";
    $result = $db->query($query);
    $row = $db->fetchByAssoc($result);
    echo "   Articles count: " . ($row['count'] ?? 0) . "\n\n";
    
    echo "2. Testing categories:\n";
    $query = "SELECT id, name FROM aok_knowledge_base_categories WHERE deleted = 0";
    $result = $db->query($query);
    $count = 0;
    while ($row = $db->fetchByAssoc($result)) {
        echo "   - {$row['name']} (ID: {$row['id']})\n";
        $count++;
    }
    echo "   Total categories: $count\n\n";
    
    echo "3. Sample articles:\n";
    $query = "SELECT id, name, status FROM aok_knowledgebase WHERE deleted = 0 LIMIT 3";
    $result = $db->query($query);
    while ($row = $db->fetchByAssoc($result)) {
        echo "   - {$row['name']}\n";
        echo "     ID: {$row['id']}\n";
        echo "     Status: {$row['status']}\n\n";
    }
    
    echo "SUCCESS: Database queries working!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END TEST ===\n";