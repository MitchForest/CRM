<?php
// Programmatic SuiteCRM installation
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_REQUEST['current_step'] = '5';
$_REQUEST['goto'] = 'Next';
$_REQUEST['setup_db_host_name'] = 'mysql';
$_REQUEST['setup_db_database_name'] = 'suitecrm';
$_REQUEST['setup_db_admin_user_name'] = 'root';
$_REQUEST['setup_db_admin_password'] = 'root';
$_REQUEST['setup_db_sugarsales_user'] = 'suitecrm';
$_REQUEST['setup_db_sugarsales_password'] = 'suitecrm';
$_REQUEST['setup_db_drop_tables'] = 0;
$_REQUEST['setup_db_create_database'] = 0;
$_REQUEST['setup_db_create_sugarsales_user'] = 0;
$_REQUEST['setup_site_admin_user_name'] = 'admin';
$_REQUEST['setup_site_admin_password'] = 'admin123';
$_REQUEST['setup_site_admin_password_retype'] = 'admin123';
$_REQUEST['setup_site_url'] = 'http://localhost:8080';
$_REQUEST['setup_system_name'] = 'SuiteCRM';
$_REQUEST['default_currency_iso4217'] = 'USD';
$_REQUEST['default_currency_name'] = 'US Dollar';
$_REQUEST['default_currency_symbol'] = '$';
$_REQUEST['default_date_format'] = 'Y-m-d';
$_REQUEST['default_time_format'] = 'H:i';
$_REQUEST['default_language'] = 'en_us';
$_REQUEST['default_locale_name_format'] = 's f l';

// Run the installer
define('sugarEntry', true);
require_once 'install.php';