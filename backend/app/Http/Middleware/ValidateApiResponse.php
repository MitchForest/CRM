<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Illuminate\Database\Capsule\Manager as DB;

class ValidateApiResponse implements MiddlewareInterface
{
    private array $modelTableMap = [
        'leads' => 'leads',
        'contacts' => 'contacts',
        'opportunities' => 'opportunities',
        'cases' => 'cases',
        'accounts' => 'accounts',
        'users' => 'users',
        'activities' => ['calls', 'meetings', 'tasks', 'notes']
    ];
    
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = $handler->handle($request);
        
        // Only validate JSON responses in development
        if ($_ENV['APP_ENV'] !== 'development' || 
            !str_contains($response->getHeaderLine('Content-Type'), 'application/json')) {
            return $response;
        }
        
        // Get response body
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        if (!$data) {
            return $response;
        }
        
        // Check for violations
        $violations = $this->validateResponseData($data, $request->getUri()->getPath());
        
        if (!empty($violations)) {
            // In development, add warnings to response
            $data['_schema_warnings'] = $violations;
            
            // Rewrite response with warnings
            $response->getBody()->rewind();
            $response->getBody()->write(json_encode($data));
        }
        
        return $response;
    }
    
    private function validateResponseData(array $data, string $path): array
    {
        $violations = [];
        
        // Extract entity type from path (e.g., /api/crm/leads -> leads)
        if (preg_match('/\/api\/crm\/(\w+)/', $path, $matches)) {
            $entityType = $matches[1];
            
            if (isset($this->modelTableMap[$entityType])) {
                $tables = (array) $this->modelTableMap[$entityType];
                
                // Check data array
                if (isset($data['data'])) {
                    if (is_array($data['data']) && isset($data['data'][0])) {
                        // Multiple items
                        foreach ($data['data'] as $index => $item) {
                            $itemViolations = $this->validateEntity($item, $tables);
                            if (!empty($itemViolations)) {
                                $violations["data[{$index}]"] = $itemViolations;
                            }
                        }
                    } elseif (is_array($data['data']) && !empty($data['data'])) {
                        // Single item
                        $itemViolations = $this->validateEntity($data['data'], $tables);
                        if (!empty($itemViolations)) {
                            $violations['data'] = $itemViolations;
                        }
                    }
                }
            }
        }
        
        // Check for camelCase fields
        $camelCaseFields = $this->findCamelCaseFields($data);
        if (!empty($camelCaseFields)) {
            $violations['camelCase_fields'] = $camelCaseFields;
        }
        
        return $violations;
    }
    
    private function validateEntity(array $entity, array $tables): array
    {
        $violations = [];
        
        // Get columns from first matching table
        $columns = [];
        foreach ($tables as $table) {
            $tableColumns = $this->getTableColumns($table);
            if (!empty($tableColumns)) {
                $columns = array_merge($columns, $tableColumns);
            }
        }
        
        if (empty($columns)) {
            return $violations;
        }
        
        // Check each field in response
        foreach ($entity as $field => $value) {
            // Skip meta fields
            if (in_array($field, ['_links', '_meta', '_schema_warnings'])) {
                continue;
            }
            
            // Check if field exists in database
            if (!isset($columns[$field])) {
                $violations[] = "Field '{$field}' does not exist in database schema";
            }
            
            // Check for camelCase
            if ($field !== strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field))) {
                $violations[] = "Field '{$field}' uses camelCase instead of snake_case";
            }
        }
        
        return $violations;
    }
    
    private function findCamelCaseFields(array $data, string $prefix = ''): array
    {
        $camelCaseFields = [];
        
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            // Skip special keys
            if (in_array($key, ['_links', '_meta', '_schema_warnings']) || is_numeric($key)) {
                continue;
            }
            
            // Check if key is camelCase
            if ($key !== strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key))) {
                $camelCaseFields[] = $fullKey;
            }
            
            // Recurse into arrays and objects
            if (is_array($value) && !empty($value)) {
                $nestedFields = $this->findCamelCaseFields($value, $fullKey);
                $camelCaseFields = array_merge($camelCaseFields, $nestedFields);
            }
        }
        
        return $camelCaseFields;
    }
    
    private function getTableColumns(string $tableName): array
    {
        static $cache = [];
        
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        
        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $columnInfo = [];
            
            foreach ($columns as $column) {
                $columnInfo[$column->Field] = [
                    'type' => $column->Type,
                    'null' => $column->Null === 'YES'
                ];
            }
            
            $cache[$tableName] = $columnInfo;
            return $columnInfo;
        } catch (\Exception $e) {
            return [];
        }
    }
}