<?php
/**
 * Rate Limiting Middleware
 * Protects API endpoints from abuse
 */

namespace Api\Middleware;

use Api\Request;
use Api\Response;
use Exception;
use Predis\Client as RedisClient;

class RateLimitMiddleware
{
    private $redis;
    private $config;
    private $enabled = true;
    
    public function __construct()
    {
        global $sugar_config;
        
        // Load rate limit config
        $this->config = [
            'default' => [
                'requests' => 60,
                'window' => 60 // 60 requests per minute
            ],
            'public' => [
                'requests' => 30,
                'window' => 60 // 30 requests per minute for public endpoints
            ],
            'chat' => [
                'requests' => 20,
                'window' => 60 // 20 chat messages per minute
            ],
            'auth' => [
                'requests' => 5,
                'window' => 300 // 5 login attempts per 5 minutes
            ]
        ];
        
        // Initialize Redis for distributed rate limiting
        try {
            if (class_exists('\Predis\Client')) {
                $this->redis = new RedisClient([
                    'scheme' => 'tcp',
                    'host' => $sugar_config['external_cache']['redis']['host'] ?? 'redis',
                    'port' => $sugar_config['external_cache']['redis']['port'] ?? 6379,
                ]);
                $this->redis->ping();
            }
        } catch (Exception $e) {
            error_log('Rate limiter Redis connection failed: ' . $e->getMessage());
            $this->redis = null;
            // Fall back to file-based rate limiting
        }
    }
    
    /**
     * Process the request through rate limiting
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!$this->enabled) {
            return $next($request, $response);
        }
        
        // Determine rate limit type based on route
        $route = $request->getUri()->getPath();
        $limitType = $this->determineLimitType($route);
        
        // Get client identifier
        $clientId = $this->getClientIdentifier($request);
        
        // Check rate limit
        $limit = $this->config[$limitType];
        $key = "rate_limit:{$limitType}:{$clientId}";
        
        if (!$this->checkRateLimit($key, $limit['requests'], $limit['window'])) {
            return $this->rateLimitExceeded($response, $limit);
        }
        
        // Add rate limit headers to response
        $response = $next($request, $response);
        
        return $this->addRateLimitHeaders($response, $key, $limit);
    }
    
    /**
     * Determine which rate limit to apply
     */
    private function determineLimitType($route)
    {
        // Public endpoints
        if (strpos($route, '/track/') !== false || strpos($route, '/forms/') !== false && strpos($route, '/submit') !== false) {
            return 'public';
        }
        
        // Chat endpoints
        if (strpos($route, '/ai/chat') !== false) {
            return 'chat';
        }
        
        // Auth endpoints
        if (strpos($route, '/auth/login') !== false) {
            return 'auth';
        }
        
        return 'default';
    }
    
    /**
     * Get unique client identifier
     */
    private function getClientIdentifier(Request $request)
    {
        // For authenticated requests, use user ID
        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            // Validate token and extract user ID
            $userId = $this->getUserIdFromToken($token);
            if ($userId) {
                return 'user:' . $userId;
            }
        }
        
        // For public endpoints, use IP + User-Agent hash
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');
        
        // For visitor tracking, also consider visitor ID
        $body = $request->getParsedBody();
        $visitorId = $body['visitor_id'] ?? null;
        
        if ($visitorId && $visitorId !== 'undefined' && $visitorId !== 'anonymous') {
            return 'visitor:' . $visitorId;
        }
        
        return 'ip:' . md5($ip . ':' . $userAgent);
    }
    
    /**
     * Check if rate limit is exceeded
     */
    private function checkRateLimit($key, $limit, $window)
    {
        if ($this->redis) {
            return $this->checkRedisRateLimit($key, $limit, $window);
        } else {
            return $this->checkFileRateLimit($key, $limit, $window);
        }
    }
    
    /**
     * Redis-based rate limiting (recommended for production)
     */
    private function checkRedisRateLimit($key, $limit, $window)
    {
        try {
            $current = $this->redis->incr($key);
            
            if ($current === 1) {
                $this->redis->expire($key, $window);
            }
            
            return $current <= $limit;
        } catch (Exception $e) {
            error_log('Redis rate limit check failed: ' . $e->getMessage());
            return true; // Allow request on Redis failure
        }
    }
    
    /**
     * File-based rate limiting (fallback)
     */
    private function checkFileRateLimit($key, $limit, $window)
    {
        $cacheDir = sys_get_temp_dir() . '/crm_rate_limit/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        $file = $cacheDir . md5($key) . '.txt';
        $now = time();
        
        // Read existing requests
        $requests = [];
        if (file_exists($file)) {
            $data = file_get_contents($file);
            $requests = $data ? json_decode($data, true) : [];
        }
        
        // Remove expired requests
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        });
        
        // Check limit
        if (count($requests) >= $limit) {
            return false;
        }
        
        // Add current request
        $requests[] = $now;
        file_put_contents($file, json_encode(array_values($requests)), LOCK_EX);
        
        return true;
    }
    
    /**
     * Get remaining requests count
     */
    private function getRemainingRequests($key, $limit)
    {
        if ($this->redis) {
            try {
                $current = $this->redis->get($key) ?: 0;
                return max(0, $limit - $current);
            } catch (Exception $e) {
                return $limit;
            }
        }
        
        // For file-based, approximate
        return $limit;
    }
    
    /**
     * Get reset timestamp
     */
    private function getResetTime($key, $window)
    {
        if ($this->redis) {
            try {
                $ttl = $this->redis->ttl($key);
                return $ttl > 0 ? time() + $ttl : time() + $window;
            } catch (Exception $e) {
                return time() + $window;
            }
        }
        
        return time() + $window;
    }
    
    /**
     * Return rate limit exceeded response
     */
    private function rateLimitExceeded(Response $response, $limit)
    {
        return $response->withJson([
            'error' => 'Rate limit exceeded',
            'message' => "Too many requests. Please try again later.",
            'retry_after' => $limit['window']
        ], 429)
        ->withHeader('Retry-After', $limit['window']);
    }
    
    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(Response $response, $key, $limit)
    {
        $remaining = $this->getRemainingRequests($key, $limit['requests']);
        $reset = $this->getResetTime($key, $limit['window']);
        
        return $response
            ->withHeader('X-RateLimit-Limit', $limit['requests'])
            ->withHeader('X-RateLimit-Remaining', $remaining)
            ->withHeader('X-RateLimit-Reset', $reset);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(Request $request)
    {
        // Check for forwarded IP
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded) {
            $ips = explode(',', $forwarded);
            return trim($ips[0]);
        }
        
        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }
        
        // Fallback to remote address
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Extract user ID from JWT token
     */
    private function getUserIdFromToken($token)
    {
        try {
            // Simple JWT decode (you should use proper JWT library)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            
            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload['user_id'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Whitelist certain IPs or user agents
     */
    public function addWhitelist($pattern)
    {
        // Add IP or user agent pattern to whitelist
        // Implementation depends on your needs
    }
    
    /**
     * Temporarily disable rate limiting (for testing)
     */
    public function disable()
    {
        $this->enabled = false;
    }
    
    /**
     * Enable rate limiting
     */
    public function enable()
    {
        $this->enabled = true;
    }
}