<?php
/**
 * Simple Rate Limiting for MVP
 * No Redis, just session-based
 */

namespace Api\Middleware;

use Api\Request;
use Api\Response;

class SimplerRateLimitMiddleware
{
    private $limits = [
        'default' => ['requests' => 60, 'window' => 60],
        'chat' => ['requests' => 20, 'window' => 60]
    ];
    
    public function __invoke(Request $request, Response $response, callable $next)
    {
        session_start();
        
        $route = $request->getUri()->getPath();
        $limitType = strpos($route, '/ai/chat') !== false ? 'chat' : 'default';
        $limit = $this->limits[$limitType];
        
        $key = 'rate_limit_' . $limitType;
        $now = time();
        
        // Initialize or clean old requests
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Remove old requests outside window
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $limit) {
            return $timestamp > ($now - $limit['window']);
        });
        
        // Check limit
        if (count($_SESSION[$key]) >= $limit['requests']) {
            return $response->withJson([
                'error' => 'Too many requests. Please try again later.'
            ], 429);
        }
        
        // Add current request
        $_SESSION[$key][] = $now;
        
        return $next($request, $response);
    }
}