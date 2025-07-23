<?php
// CORS headers are handled by Apache configuration - removed duplicate headers

// @codingStandardsIgnoreStart
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}
// @codingStandardsIgnoreEnd

// For php-fpm we pass the "Authorization" header through HTTP_AUTHORIZATION
// using .htaccess rewrite rules. The rewrite rules result in apache prefixing
// the env var which gives us REDIRECT_HTTP_AUTHORIZATION.
if (!isset($_SERVER['HTTP_AUTHORIZATION']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

chdir(__DIR__ . '/../../');
require_once __DIR__ . '/../../include/entryPoint.php';

$app = new \Slim\App(\Api\Core\Loader\ContainerLoader::configure());
// closure shouldn't be created in static context under PHP7
$routeLoader = new \Api\Core\Loader\RouteLoader();
$routeLoader->configureRoutes($app);
