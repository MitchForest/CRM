<?php
// Debug script to test BeanFactory issue
chdir('/var/www/html');

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

echo "Loading config...\n";
require_once('config.php');

echo "Loading utils...\n";
require_once('include/utils.php');

echo "Loading BeanFactory...\n";
require_once('data/BeanFactory.php');

echo "Testing BeanFactory::newBean('Administration')...\n";
$admin = BeanFactory::newBean('Administration');

echo "Result: ";
var_dump($admin);

if ($admin === false) {
    echo "\nBeanFactory returned false. Checking module list...\n";
    
    // Check if beanList is populated
    global $beanList;
    echo "beanList keys: ";
    if (isset($beanList)) {
        echo implode(', ', array_keys($beanList));
    } else {
        echo "beanList not set!";
    }
    echo "\n";
}