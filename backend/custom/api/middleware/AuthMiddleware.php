<?php
namespace Api\Middleware;

use Api\Request;
use Api\Response;
use Api\Auth\JWT;

class AuthMiddleware {
    private $publicRoutes = [
        'POST:/auth/login',
        'POST:/auth/refresh'
    ];
    
    public function handle(Request $request) {
        $method = $request->getMethod();
        $path = $request->getPath();
        $route = $method . ':' . $path;
        
        // Check if route is public
        foreach ($this->publicRoutes as $publicRoute) {
            if (strpos($route, $publicRoute) === 0) {
                return true;
            }
        }
        
        // Get token
        $token = $request->getAuthToken();
        
        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No token provided']);
            return false;
        }
        
        try {
            $payload = JWT::decode($token);
            
            // Set current user without loading full bean
            global $current_user;
            $current_user = new \stdClass();
            $current_user->id = $payload['user_id'];
            $current_user->user_name = $payload['username'] ?? 'admin';
            $current_user->authenticated = true;
            
            // Add user info to request
            $request->user = $current_user;
            
            return true;
            
        } catch (\Exception $e) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid token: ' . $e->getMessage()]);
            return false;
        }
    }
}