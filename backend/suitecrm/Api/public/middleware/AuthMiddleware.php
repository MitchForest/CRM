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
            
            // Set current user
            global $current_user;
            $current_user = \BeanFactory::getBean('Users', $payload['user_id']);
            
            if (empty($current_user->id)) {
                throw new \Exception('User not found');
            }
            
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