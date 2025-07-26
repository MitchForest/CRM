<?php
/**
 * Basic Security for MVP
 * Just the essentials
 */

namespace Api\Middleware;

use Api\Request;
use Api\Response;

class BasicSecurityMiddleware
{
    public function handle(Request $request)
    {
        // Basic size check
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ($contentLength && (int)$contentLength > 1048576) { // 1MB
            http_response_code(413);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Request too large']);
            return false;
        }
        
        // Continue processing
        return true;
    }
    
    public function __invoke(Request $request, Response $response, callable $next)
    {
        // Basic size check
        $contentLength = $request->getHeaderLine('Content-Length');
        if ($contentLength && (int)$contentLength > 1048576) { // 1MB
            return $response->withJson(['error' => 'Request too large'], 413);
        }
        
        // Sanitize input
        $body = $request->getParsedBody();
        if (is_array($body)) {
            $body = $this->sanitizeArray($body);
            $request = $request->withParsedBody($body);
        }
        
        // Process request
        $response = $next($request, $response);
        
        // Add basic security headers
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY');
    }
    
    private function sanitizeArray($data)
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            }
        }
        return $data;
    }
}