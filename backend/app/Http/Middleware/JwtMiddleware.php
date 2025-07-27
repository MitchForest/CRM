<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        // Debug logging
        error_log('JWT Middleware - Path: ' . $request->getUri()->getPath());
        error_log('JWT Middleware - Auth Header: ' . ($authHeader ?: 'MISSING'));
        
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Missing authorization header');
        }
        
        // Extract token from Bearer scheme
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid authorization format');
        }
        
        $token = $matches[1];
        
        try {
            // Decode JWT token
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            
            error_log('JWT Decoded successfully - User ID: ' . ($decoded->user_id ?? $decoded->sub ?? 'unknown'));
            
            // Add user info to request
            $request = $request->withAttribute('user_id', $decoded->user_id ?? $decoded->sub ?? null);
            $request = $request->withAttribute('user_data', $decoded);
            
            // Continue to next middleware
            return $handler->handle($request);
            
        } catch (Exception $e) {
            error_log('JWT Decode Error: ' . $e->getMessage());
            return $this->unauthorizedResponse('Invalid or expired token');
        }
    }
    
    private function unauthorizedResponse(string $message): Response
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['message' => $message]));
        
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}