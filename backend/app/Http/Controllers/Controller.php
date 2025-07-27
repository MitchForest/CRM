<?php

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    protected ValidatorFactory $validator;
    
    public function __construct()
    {
        // Set up validator factory
        $loader = new ArrayLoader();
        $translator = new Translator($loader, 'en');
        $this->validator = new ValidatorFactory($translator);
    }
    
    /**
     * Return JSON response
     */
    protected function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
    
    /**
     * Return error response
     */
    protected function error(Response $response, string $message, int $status = 400, array $errors = []): Response
    {
        $data = ['message' => $message];
        
        if (!empty($errors)) {
            $data['errors'] = $errors;
        }
        
        return $this->json($response, $data, $status);
    }
    
    /**
     * Validate request data
     */
    protected function validate(Request $request, array $rules): array
    {
        $data = $request->getParsedBody() ?? [];
        
        $validator = $this->validator->make($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        return $validator->validated();
    }
    
    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        $params = $request->getQueryParams();
        
        $page = max(1, intval($params['page'] ?? 1));
        $perPage = min(100, max(1, intval($params['per_page'] ?? 20)));
        
        return [$page, $perPage];
    }
    
    /**
     * Get current authenticated user ID
     */
    protected function getUserId(Request $request): ?string
    {
        return $request->getAttribute('user_id');
    }
}