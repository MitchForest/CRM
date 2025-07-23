<?php
function configureRoutes($router) {
    // Health check route (no auth required)
    $router->get('/health', 'Api\Controllers\HealthController::check', ['skipAuth' => true]);
    
    // Add middleware
    $router->addMiddleware(new \Api\Middleware\AuthMiddleware());
    
    // Authentication routes
    $router->post('/auth/login', 'Api\Controllers\AuthController::login');
    $router->post('/auth/refresh', 'Api\Controllers\AuthController::refresh');
    $router->post('/auth/logout', 'Api\Controllers\AuthController::logout');
}