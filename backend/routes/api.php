<?php

use Slim\Routing\RouteCollectorProxy;
use App\Http\Middleware\JwtMiddleware;

// API Routes - Main entry point for all API routes
return function ($app) {
    
    // API version prefix
    $app->group('/api', function (RouteCollectorProxy $api) {
        
        // Health check (no auth required)
        $api->get('/health', function ($request, $response) {
            $data = [
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'service' => 'Sassy CRM API'
            ];
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        });
        
        // Load auth routes (public)
        $authRoutes = require __DIR__ . '/auth.php';
        $authRoutes($api);
        
        // Load public routes (no auth)
        $publicRoutes = require __DIR__ . '/public.php';
        $publicRoutes($api);
        
        // Protected routes group (requires JWT)
        $api->group('', function (RouteCollectorProxy $protected) {
            
            // Load CRM routes
            $crmRoutes = require __DIR__ . '/crm.php';
            $crmRoutes($protected);
            
            // Load admin routes
            $adminRoutes = require __DIR__ . '/admin.php';
            $adminRoutes($protected);
            
        })->add(new JwtMiddleware());
        
    });
};