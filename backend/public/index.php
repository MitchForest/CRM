<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Bootstrap the application
$app = require __DIR__ . '/../bootstrap/app.php';

// Load API routes
$apiRoutes = require __DIR__ . '/../routes/api.php';
$apiRoutes($app);

// Default route
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'name' => $_ENV['APP_NAME'] ?? 'Modern CRM API',
        'version' => '1.0.0',
        'status' => 'healthy'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Run the application
$app->run();