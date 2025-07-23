<?php
// Quick Repair and Rebuild script
if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

global $current_user;
$current_user = new User();
$current_user->getSystemUser();

echo "Starting Quick Repair and Rebuild...\n";

$repair = new RepairAndClear();
$repair->repairAndClearAll(
    ['clearAll'],
    ['Leads', 'Accounts'],
    true,
    false
);

// Also rebuild extensions
echo "Rebuilding extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_all(true);

echo "Quick Repair and Rebuild completed successfully!\n";