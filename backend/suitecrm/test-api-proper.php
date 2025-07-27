<?php
// Proper SuiteCRM API test
if (!defined('sugarEntry')) define('sugarEntry', true);

// Change to docroot
chdir('/var/www/html');

// Load through proper entry point
require_once 'include/entryPoint.php';

echo "SuiteCRM initialized.\n\n";

// Test OAuth2 client
global $db;
$query = "SELECT * FROM oauth2clients WHERE id = 'suitecrm_client'";
$result = $db->query($query);
$client = $db->fetchByAssoc($result);

echo "OAuth2 Client:\n";
if ($client) {
    echo "- Found: " . $client['name'] . "\n";
    echo "- Grant type: " . $client['allowed_grant_type'] . "\n";
} else {
    echo "- Not found\n";
}

// Check V8 API modules
echo "\nChecking API access to modules:\n";

// Try to get module list through internal API
require_once 'Api/V8/Helper/ModuleListProvider.php';
$moduleProvider = new \Api\V8\Helper\ModuleListProvider();
$modules = $moduleProvider->getModuleList();

echo "Available modules: " . count($modules) . "\n";

// Check if AOK_KnowledgeBase is available
$foundKB = false;
foreach ($modules as $key => $module) {
    $moduleName = is_array($module) ? $key : $module;
    if ($moduleName === 'AOK_KnowledgeBase' || $moduleName === 'AOK_Knowledge_Base_Categories') {
        echo "- Found KB module: $moduleName\n";
        $foundKB = true;
    }
}

if (!$foundKB) {
    echo "- Knowledge Base modules not found in API\n";
    echo "- Available modules:\n";
    foreach ($modules as $key => $module) {
        $moduleName = is_array($module) ? $key : $module;
        echo "  - $moduleName\n";
    }
}