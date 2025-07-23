<?php
/**
 * Run Quick Repair and Rebuild
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

chdir(dirname(__FILE__) . '/suitecrm');

require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

global $current_user;
$current_user = new User();
$current_user->getSystemUser();

echo "Running Quick Repair and Rebuild...\n";

$repair = new RepairAndClear();
$repair->repairAndClearAll(
    ['clearAll'],
    ['All'],
    true,
    false
);

// Also rebuild .htaccess
if (file_exists('modules/Administration/UpgradeAccess.php')) {
    require_once('modules/Administration/UpgradeAccess.php');
    rebuildHTACCESS();
    echo ".htaccess file rebuilt\n";
}

echo "Quick Repair and Rebuild completed!\n";