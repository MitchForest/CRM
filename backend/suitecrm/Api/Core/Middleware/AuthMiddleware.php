<?php
namespace Api\Core\Middleware;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AuthMiddleware
{
    public function __invoke(Request $request, $handler): Response
    {
        // DISABLED FOR DEMO - ALL REQUESTS ARE AUTHORIZED
        return $handler->handle($request);
    }
}