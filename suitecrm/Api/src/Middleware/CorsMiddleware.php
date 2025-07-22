<?php
declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins = [
        'http://localhost:3000',
        'http://localhost:3001'
    ];

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = $handler->handle($request);
        
        $origin = $request->getHeaderLine('Origin');
        
        if (in_array($origin, $this->allowedOrigins)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Max-Age', '86400');
        }
        
        return $response;
    }
}