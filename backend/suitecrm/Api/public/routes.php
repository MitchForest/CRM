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
    
    // Leads routes
    $router->get('/leads', 'Api\Controllers\LeadsController::index');
    $router->get('/leads/{id}', 'Api\Controllers\LeadsController::show');
    $router->post('/leads', 'Api\Controllers\LeadsController::create');
    $router->put('/leads/{id}', 'Api\Controllers\LeadsController::update');
    $router->patch('/leads/{id}', 'Api\Controllers\LeadsController::patch');
    $router->delete('/leads/{id}', 'Api\Controllers\LeadsController::delete');
    
    // Dashboard routes (Phase 2)
    $router->get('/dashboard/metrics', 'Api\Controllers\DashboardController::getMetrics');
    $router->get('/dashboard/pipeline', 'Api\Controllers\DashboardController::getPipelineData');
    $router->get('/dashboard/activities', 'Api\Controllers\DashboardController::getActivityMetrics');
    $router->get('/dashboard/cases', 'Api\Controllers\DashboardController::getCaseMetrics');
    
    // Email routes (Phase 2)
    $router->get('/emails/{id}/view', 'Api\Controllers\EmailController::viewEmail');
    
    // Document routes (Phase 2)
    $router->get('/documents/{id}/download', 'Api\Controllers\DocumentController::downloadDocument');
}