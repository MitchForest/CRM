<?php

use Slim\Routing\RouteCollectorProxy;
use App\Http\Controllers\AuthController;

// Authentication Routes (public endpoints)
return function (RouteCollectorProxy $api) {
    
    $api->group('/auth', function (RouteCollectorProxy $auth) {
        
        // Login with email/password
        $auth->post('/login', [AuthController::class, 'login'])
            ->setName('auth.login');
        
        // Refresh access token using refresh token
        $auth->post('/refresh', [AuthController::class, 'refresh'])
            ->setName('auth.refresh');
        
        // Logout (requires auth - will be added to protected group)
        $auth->post('/logout', [AuthController::class, 'logout'])
            ->setName('auth.logout')
            ->add(new \App\Http\Middleware\JwtMiddleware());
        
        // Get current user info (requires auth)
        $auth->get('/me', [AuthController::class, 'me'])
            ->setName('auth.me')
            ->add(new \App\Http\Middleware\JwtMiddleware());
        
        // Password reset request
        $auth->post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->setName('auth.forgot-password');
        
        // Reset password with token
        $auth->post('/reset-password', [AuthController::class, 'resetPassword'])
            ->setName('auth.reset-password');
        
    });
};