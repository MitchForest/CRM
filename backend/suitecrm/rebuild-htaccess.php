<?php
// Rebuild .htaccess file
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

global $current_user;
$current_user = new User();
$current_user->getSystemUser();

echo "Rebuilding .htaccess file...\n";

// Load the rebuild functions
require_once 'modules/Administration/QuickRepairAndRebuild.php';
$repair = new RepairAndClear();

// Rebuild .htaccess
if (method_exists($repair, 'rebuildHtaccess')) {
    $repair->rebuildHtaccess();
    echo ".htaccess rebuilt successfully!\n";
} else {
    // Alternative method
    require_once 'install/install_utils.php';
    if (function_exists('rebuild_htaccess')) {
        rebuild_htaccess();
        echo ".htaccess rebuilt using install utils!\n";
    } else {
        echo "Could not find rebuild_htaccess function\n";
    }
}

// Also clear various caches
$repair->clearTpls();
$repair->clearJsFiles();
$repair->clearVardefs();
$repair->clearJsLangFiles();
$repair->clearLanguageCache();

echo "Caches cleared.\n";