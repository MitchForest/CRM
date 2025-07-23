<?php
/**
 * Module Configuration Script for SaaS CRM
 * Disables unnecessary SuiteCRM modules for a streamlined SaaS experience
 */

// Prevent direct access
if (!defined('sugarEntry')) define('sugarEntry', true);

// Include SuiteCRM bootstrap first
$rootPath = dirname(__FILE__) . '/../..';
require_once($rootPath . '/include/entryPoint.php');

// Now change to SuiteCRM root
chdir($rootPath);

require_once('modules/Administration/QuickRepairAndRebuild.php');

// Modules to disable for SaaS CRM
$modulesToDisable = [
    // B2B modules not needed
    'Accounts',
    'Targets',
    'TargetLists',
    'Campaigns',
    'CampaignLog',
    'CampaignTrackers',
    'ProspectLists',
    'Prospects',
    
    // Advanced features not needed for MVP
    'Surveys',
    'Bugs',
    'Project',
    'ProjectTask',
    'AM_ProjectTemplates',
    'AOK_KnowledgeBase',
    'AOK_Knowledge_Base_Categories',
    
    // Mapping features
    'jjwg_Maps',
    'jjwg_Markers',
    'jjwg_Areas',
    'jjwg_Address_Cache',
    
    // Advanced reporting
    'AOR_Reports',
    'AOR_Scheduled_Reports',
    
    // Workflow (using custom implementation)
    'AOW_WorkFlow',
    'AOW_Processed',
    'AOW_Conditions',
    'AOW_Actions',
    
    // Portal features
    'AOP_AOP_Case_Events',
    'AOP_AOP_Case_Updates',
    
    // Events management
    'Events',
    'FP_events',
    'FP_Event_Locations'
];

echo "Starting module configuration...\n";

// Load module configuration
$moduleFile = 'include/modules.php';
if (!file_exists($moduleFile)) {
    die("Error: modules.php not found at $moduleFile\n");
}

require_once($moduleFile);

// Initialize modInvisList if not exists
if (!isset($modInvisList) || !is_array($modInvisList)) {
    $modInvisList = [];
}

$disabledCount = 0;

// Process modules to disable
foreach ($modulesToDisable as $module) {
    if (isset($beanList[$module])) {
        // Remove from visible list if present
        $modInvisList = array_diff($modInvisList, [$module]);
        
        // Add to invisible list
        if (!in_array($module, $modInvisList)) {
            $modInvisList[] = $module;
            $disabledCount++;
            echo "- Disabled: $module\n";
        }
    }
}

// Write updated configuration back to file
$write_result = write_array_to_file('beanList', $beanList, $moduleFile);
if (!$write_result) {
    die("Error: Failed to write beanList to $moduleFile\n");
}

$write_result = write_array_to_file('beanFiles', $beanFiles, $moduleFile);
if (!$write_result) {
    die("Error: Failed to write beanFiles to $moduleFile\n");
}

$write_result = write_array_to_file('modInvisList', $modInvisList, $moduleFile);
if (!$write_result) {
    die("Error: Failed to write modInvisList to $moduleFile\n");
}

echo "\nDisabled $disabledCount modules.\n";
echo "Running Quick Repair and Rebuild...\n";

// Clear all caches
try {
    $repair = new RepairAndClear();
    $repair->repairAndClearAll(['clearAll'], ['All Modules'], true, false);
    echo "Cache cleared successfully!\n";
} catch (Exception $e) {
    echo "Warning: Could not clear cache - " . $e->getMessage() . "\n";
    echo "You may need to manually clear the cache directory.\n";
}

echo "\nModule configuration complete!\n";
echo "The following modules remain active:\n";

// Show remaining active modules
$activeModules = array_diff(array_keys($beanList), $modInvisList);
sort($activeModules);

foreach ($activeModules as $module) {
    if (!in_array($module, ['Home', 'Administration', 'Currencies', 'Emails'])) {
        echo "  - $module\n";
    }
}

echo "\nIMPORTANT: Please restart your web server for changes to take full effect.\n";