<?php
/**
 * SuiteCRM Silent Installation Script
 */

// Configuration for silent install
$config = [
    'setup_db_type' => 'mysql',
    'setup_db_host_name' => 'localhost',
    'setup_db_database_name' => 'suitecrm',
    'setup_db_admin_user_name' => 'root',
    'setup_db_admin_password' => 'root',
    'setup_db_create_database' => 1,
    'setup_db_drop_tables' => 0,
    'setup_db_username_is_privileged' => true,
    'setup_db_create_sugarsales_user' => false,
    'setup_db_sugarsales_user' => 'suitecrm',
    'setup_db_sugarsales_password' => 'suitecrm',
    'setup_site_url' => 'http://localhost:8080',
    'setup_site_admin_user_name' => 'admin',
    'setup_site_admin_password' => 'admin',
    'setup_site_sugarbeet_automatic_checks' => false,
    'setup_license_key' => '',
    'setup_system_name' => 'SuiteCRM',
    'default_currency_iso4217' => 'USD',
    'default_currency_name' => 'US Dollars',
    'default_currency_symbol' => '$',
    'default_date_format' => 'Y-m-d',
    'default_time_format' => 'H:i',
    'default_decimal_seperator' => '.',
    'default_export_charset' => 'UTF-8',
    'default_language' => 'en_us',
    'default_locale_name_format' => 's f l',
    'default_number_grouping_seperator' => ',',
    'export_delimiter' => ',',
];

// Write config to file
file_put_contents('config_si.php', '<?php $sugar_config_si = ' . var_export($config, true) . ';');

// Run the installer
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST['goto'] = 'SilentInstall';
$_REQUEST['cli'] = true;

// Set up the environment
define('sugarEntry', true);
chdir(dirname(__FILE__) . '/suitecrm');

// Include the installer
if (file_exists('install.php')) {
    include 'install.php';
} else {
    die("Install.php not found. Please ensure SuiteCRM is properly extracted.\n");
}