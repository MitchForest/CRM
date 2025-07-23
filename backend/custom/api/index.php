<?php
// Prevent direct access
if (!defined('sugarEntry')) define('sugarEntry', true);

// Change working directory to SuiteCRM root
chdir('../..');

// Include SuiteCRM bootstrap
require_once('include/entryPoint.php');
require_once('include/utils.php');
require_once('data/BeanFactory.php');

// API autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Api\\';
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize router
require_once 'Router.php';
require_once 'routes.php';

$router = new Api\Router();
configureRoutes($router);
$router->dispatch();