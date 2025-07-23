<?php
/**
 * Minimal SuiteCRM initialization for API
 * Avoids issues with Administration bean during API calls
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Change to SuiteCRM root
chdir(realpath(__DIR__ . '/../..'));

// Load configuration
require_once 'config.php';

// Load Composer autoloader
$autoloader = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Set up globals
global $sugar_config, $db, $current_user, $log;
$GLOBALS['sugar_config'] = $sugar_config;

// Load essential includes
require_once 'include/utils.php';
require_once 'include/SugarObjects/SugarConfig.php';

// Initialize logger
require_once 'include/SugarLogger/LoggerManager.php';
$log = LoggerManager::getLogger('SugarCRM');
$GLOBALS['log'] = $log;

// Initialize TimeDate
require_once 'include/TimeDate.php';
$GLOBALS['timedate'] = new TimeDate();

// Initialize database
require_once 'include/database/DBManagerFactory.php';
$db = DBManagerFactory::getInstance();
$GLOBALS['db'] = $db;

// Load modules
require_once 'include/modules.php';

// Load VardefManager
require_once 'modules/Administration/QuickRepairAndRebuild.php';
require_once 'include/utils/file_utils.php';
require_once 'include/utils/sugar_file_utils.php';

// Initialize current user (system user for API)
require_once 'data/BeanFactory.php';
require_once 'data/SugarBean.php';
require_once 'include/VarDefHandler/VarDefHandler.php';
require_once 'modules/Users/User.php';

// Try a simpler approach - just set a minimal current user without full initialization
$current_user = new stdClass();
$current_user->id = '1';
$current_user->user_name = 'admin';
$current_user->is_admin = 1;
$current_user->authenticated = true;
$GLOBALS['current_user'] = $current_user;

// Skip Administration bean initialization that's causing issues
// The API doesn't need it for basic operations

return true;