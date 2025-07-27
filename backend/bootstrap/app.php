<?php

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Initialize database
require __DIR__ . '/../config/database.php';

// Create Slim App
$app = AppFactory::create();

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Add routing middleware
$app->addRoutingMiddleware();

// Add error middleware
$displayErrorDetails = $_ENV['APP_DEBUG'] === 'true';
$app->addErrorMiddleware($displayErrorDetails, true, true);

// CORS Middleware
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    
    $origins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
    $origin = $request->getHeaderLine('Origin');
    
    if (in_array($origin, $origins) || in_array('*', $origins)) {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin ?: '*');
    }
    
    return $response
        ->withHeader('Access-Control-Allow-Headers', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Handle preflight requests
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

return $app;