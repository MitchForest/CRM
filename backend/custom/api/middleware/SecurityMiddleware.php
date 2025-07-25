<?php
/**
 * Security Middleware
 * Input validation, XSS prevention, and security headers
 */

namespace Api\Middleware;

use Api\Request;
use Api\Response;

class SecurityMiddleware
{
    private $config;
    
    public function __construct()
    {
        $this->config = [
            'max_input_size' => 1048576, // 1MB
            'allowed_origins' => ['http://localhost:3000', 'http://localhost:5173'],
            'security_headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
            ]
        ];
    }
    
    /**
     * Process the request through security checks
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        // Validate request size
        if (!$this->validateRequestSize($request)) {
            return $response->withJson([
                'error' => 'Request too large',
                'message' => 'Request body exceeds maximum allowed size'
            ], 413);
        }
        
        // Validate and sanitize input
        $request = $this->sanitizeRequest($request);
        
        // Check for common attack patterns
        if ($this->detectAttackPatterns($request)) {
            return $response->withJson([
                'error' => 'Invalid request',
                'message' => 'Request contains potentially malicious content'
            ], 400);
        }
        
        // Process request
        $response = $next($request, $response);
        
        // Add security headers
        $response = $this->addSecurityHeaders($response, $request);
        
        return $response;
    }
    
    /**
     * Validate request size
     */
    private function validateRequestSize(Request $request)
    {
        $contentLength = $request->getHeaderLine('Content-Length');
        if ($contentLength && (int)$contentLength > $this->config['max_input_size']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize request data
     */
    private function sanitizeRequest(Request $request)
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            $sanitized = $this->sanitizeData($body);
            $request = $request->withParsedBody($sanitized);
        }
        
        return $request;
    }
    
    /**
     * Recursively sanitize data
     */
    private function sanitizeData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Sanitize key
                $cleanKey = $this->sanitizeString($key);
                if ($cleanKey !== $key) {
                    unset($data[$key]);
                    $key = $cleanKey;
                }
                
                // Sanitize value
                $data[$key] = $this->sanitizeData($value);
            }
            return $data;
        } elseif (is_string($data)) {
            return $this->sanitizeString($data);
        }
        
        return $data;
    }
    
    /**
     * Sanitize string input
     */
    private function sanitizeString($string)
    {
        if (!is_string($string)) {
            return $string;
        }
        
        // Remove null bytes
        $string = str_replace(chr(0), '', $string);
        
        // Strip tags but preserve content
        $string = strip_tags($string);
        
        // Encode special characters
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Limit string length
        if (strlen($string) > 10000) {
            $string = substr($string, 0, 10000);
        }
        
        return $string;
    }
    
    /**
     * Detect common attack patterns
     */
    private function detectAttackPatterns(Request $request)
    {
        $uri = $request->getUri()->getPath();
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        
        // Check for SQL injection patterns
        $sqlPatterns = [
            '/\bunion\s+select\b/i',
            '/\bdrop\s+table\b/i',
            '/\bexec\s*\(/i',
            '/\bscript\s*>/i',
            '/\b(and|or)\s+\d+\s*=\s*\d+/i',
            '/\'\s*(or|and)\s+\'.*\'\s*=\s*\'/i'
        ];
        
        // Check for XSS patterns
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/si',
            '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',
            '/javascript\s*:/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        // Check for path traversal
        $pathPatterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e/',
            '/\x00/'
        ];
        
        $allPatterns = array_merge($sqlPatterns, $xssPatterns, $pathPatterns);
        
        // Check URI
        foreach ($allPatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                error_log("Attack pattern detected in URI: {$uri}");
                return true;
            }
        }
        
        // Check all input data
        $checkData = array_merge(
            is_array($body) ? $body : [],
            is_array($query) ? $query : []
        );
        
        foreach ($checkData as $key => $value) {
            $checkString = is_string($value) ? $key . ' ' . $value : $key;
            foreach ($allPatterns as $pattern) {
                if (preg_match($pattern, $checkString)) {
                    error_log("Attack pattern detected in input: {$checkString}");
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response, Request $request)
    {
        // Add standard security headers
        foreach ($this->config['security_headers'] as $header => $value) {
            $response = $response->withHeader($header, $value);
        }
        
        // Add CSP header
        $csp = $this->buildContentSecurityPolicy();
        $response = $response->withHeader('Content-Security-Policy', $csp);
        
        // Add CORS headers for allowed origins
        $origin = $request->getHeaderLine('Origin');
        if (in_array($origin, $this->config['allowed_origins'])) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
        
        return $response;
    }
    
    /**
     * Build Content Security Policy
     */
    private function buildContentSecurityPolicy()
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://api.openai.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        return implode('; ', $policies);
    }
    
    /**
     * Validate API key format
     */
    public function validateApiKey($key)
    {
        // Must be 32+ characters, alphanumeric with dashes
        return preg_match('/^[a-zA-Z0-9\-]{32,}$/', $key);
    }
    
    /**
     * Validate email format
     */
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone format
     */
    public function validatePhone($phone)
    {
        // Basic international phone validation
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $phone) && strlen($phone) >= 10;
    }
    
    /**
     * Validate UUID format
     */
    public function validateUUID($uuid)
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }
}