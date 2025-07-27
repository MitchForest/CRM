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
        // Set up validator factory with proper messages
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'validation', [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'string' => 'The :attribute must be a string.',
            'max' => [
                'string' => 'The :attribute may not be greater than :max characters.',
            ],
            'min' => [
                'string' => 'The :attribute must be at least :min characters.',
            ],
            'date' => 'The :attribute must be a valid date.',
            'url' => 'The :attribute must be a valid URL.',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'boolean' => 'The :attribute field must be true or false.',
            'array' => 'The :attribute must be an array.',
            'exists' => 'The selected :attribute is invalid.',
            'unique' => 'The :attribute has already been taken.',
            'size' => [
                'string' => 'The :attribute must be :size characters.',
            ],
            'regex' => 'The :attribute format is invalid.',
            'sometimes' => 'The :attribute is optional.',
        ]);
        
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
            $errors = $validator->errors()->toArray();
            error_log('Validation errors: ' . json_encode($errors));
            error_log('Validation data: ' . json_encode($data));
            error_log('Validation rules: ' . json_encode($rules));
            
            $firstError = array_values($errors)[0][0] ?? 'Validation failed';
            
            // Return a proper error response instead of throwing exception
            throw new \Exception($firstError . (count($errors) > 1 ? ' (and ' . (count($errors) - 1) . ' more error' . (count($errors) > 2 ? 's' : '') . ')' : ''));
        }
        
        return $validator->validated();
    }
    
    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        $params = $request->getQueryParams();
        
        return [
            'page' => max(1, intval($params['page'] ?? 1)),
            'limit' => min(100, max(1, intval($params['limit'] ?? 20))),
            'offset' => max(0, intval($params['offset'] ?? 0))
        ];
    }
    
    /**
     * Get current authenticated user ID
     */
    protected function getUserId(Request $request): ?string
    {
        return $request->getAttribute('user_id');
    }
}