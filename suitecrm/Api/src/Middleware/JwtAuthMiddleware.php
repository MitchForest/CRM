<?php
declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Api\Security\JWTManager;
use Slim\Psr7\Response as SlimResponse;

class JwtAuthMiddleware implements MiddlewareInterface
{
    private JWTManager $jwtManager;
    private array $publicRoutes = [
        'POST:/api/auth/login',
        'POST:/api/auth/refresh',
        'OPTIONS:.*' // Allow all OPTIONS requests
    ];

    public function __construct(string $secret)
    {
        $this->jwtManager = new JWTManager($secret);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        
        // Check if route is public
        if ($this->isPublicRoute($method, $path)) {
            return $handler->handle($request);
        }

        // Get Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('No authorization header');
        }

        // Extract token
        $token = $this->jwtManager->extractToken($authHeader);
        
        if (!$token) {
            return $this->unauthorizedResponse('Invalid authorization format');
        }

        try {
            // Decode and validate token
            $payload = $this->jwtManager->decode($token);
            
            // Add user info to request
            $request = $request->withAttribute('user', $payload);
            
            // Set current user in SuiteCRM
            if (isset($payload['user_id'])) {
                global $current_user;
                $current_user = \BeanFactory::getBean('Users', $payload['user_id']);
            }
            
            return $handler->handle($request);
            
        } catch (\Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    private function isPublicRoute(string $method, string $path): bool
    {
        foreach ($this->publicRoutes as $route) {
            list($routeMethod, $routePath) = explode(':', $route);
            
            if ($routeMethod === $method || $routeMethod === '*') {
                if ($routePath === '.*' || preg_match('#^' . $routePath . '$#', $path)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized',
            'message' => $message
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}