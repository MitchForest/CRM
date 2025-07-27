<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

class SchemaValidationService
{
    private array $violations = [];
    
    /**
     * Validate a model against its database table
     */
    public function validateModel(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return ['error' => "Model class {$modelClass} not found"];
        }
        
        $model = new $modelClass();
        if (!$model instanceof Model) {
            return ['error' => "{$modelClass} is not an Eloquent model"];
        }
        
        $tableName = $model->getTable();
        $fillable = $model->getFillable();
        $hidden = $model->getHidden();
        $casts = $model->getCasts();
        
        // Get actual database columns
        $columns = $this->getTableColumns($tableName);
        if (empty($columns)) {
            return ['error' => "Table {$tableName} not found in database"];
        }
        
        $violations = [];
        
        // Check fillable fields exist in database
        foreach ($fillable as $field) {
            if (!isset($columns[$field])) {
                $violations[] = [
                    'type' => 'missing_column',
                    'field' => $field,
                    'message' => "Fillable field '{$field}' does not exist in table '{$tableName}'"
                ];
            }
        }
        
        // Check database columns are properly handled
        foreach ($columns as $columnName => $columnInfo) {
            // Skip auto-generated fields
            if (in_array($columnName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            // Check if column is fillable or hidden
            if (!in_array($columnName, $fillable) && !in_array($columnName, $hidden)) {
                $violations[] = [
                    'type' => 'unhandled_column',
                    'field' => $columnName,
                    'message' => "Database column '{$columnName}' is not in fillable or hidden arrays"
                ];
            }
            
            // Check type casting
            $expectedCast = $this->getExpectedCast($columnInfo['type']);
            if ($expectedCast && (!isset($casts[$columnName]) || $casts[$columnName] !== $expectedCast)) {
                $violations[] = [
                    'type' => 'missing_cast',
                    'field' => $columnName,
                    'message' => "Column '{$columnName}' of type '{$columnInfo['type']}' should be cast to '{$expectedCast}'"
                ];
            }
        }
        
        // Check for appends that don't match database columns
        if (property_exists($model, 'appends')) {
            $reflection = new \ReflectionClass($model);
            $appendsProperty = $reflection->getProperty('appends');
            $appendsProperty->setAccessible(true);
            $appends = $appendsProperty->getValue($model);
            
            if (!empty($appends)) {
                foreach ($appends as $append) {
                    $violations[] = [
                        'type' => 'invalid_append',
                        'field' => $append,
                        'message' => "Appended attribute '{$append}' creates a field that doesn't exist in database"
                    ];
                }
            }
        }
        
        return [
            'model' => $modelClass,
            'table' => $tableName,
            'violations' => $violations,
            'valid' => empty($violations),
            'columns' => $columns,
            'fillable' => $fillable,
            'hidden' => $hidden,
            'casts' => $casts
        ];
    }
    
    /**
     * Validate all models in the app
     */
    public function validateAllModels(): array
    {
        $modelsPath = app_path('Models');
        $results = [];
        
        if (!is_dir($modelsPath)) {
            return ['error' => 'Models directory not found'];
        }
        
        $files = glob($modelsPath . '/*.php');
        foreach ($files as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');
            
            // Skip abstract classes and traits
            if ((new \ReflectionClass($className))->isAbstract()) {
                continue;
            }
            
            $results[$className] = $this->validateModel($className);
        }
        
        return $results;
    }
    
    /**
     * Get table columns from database
     */
    private function getTableColumns(string $tableName): array
    {
        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $columnInfo = [];
            
            foreach ($columns as $column) {
                $columnInfo[$column->Field] = [
                    'type' => $column->Type,
                    'null' => $column->Null === 'YES',
                    'key' => $column->Key,
                    'default' => $column->Default,
                    'extra' => $column->Extra
                ];
            }
            
            return $columnInfo;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get expected cast type based on database column type
     */
    private function getExpectedCast(string $dbType): ?string
    {
        $type = strtolower($dbType);
        
        if (str_contains($type, 'int')) {
            return 'integer';
        }
        
        if (str_contains($type, 'tinyint(1)')) {
            return 'boolean';
        }
        
        if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            return 'float';
        }
        
        if (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
            return 'datetime';
        }
        
        if (str_contains($type, 'date')) {
            return 'date';
        }
        
        if (str_contains($type, 'json')) {
            return 'json';
        }
        
        if (str_contains($type, 'text') && (str_contains($type, 'json') || str_contains($type, 'array'))) {
            return 'array';
        }
        
        return null;
    }
    
    /**
     * Generate a report of all violations
     */
    public function generateReport(): array
    {
        $results = $this->validateAllModels();
        $summary = [
            'total_models' => 0,
            'valid_models' => 0,
            'invalid_models' => 0,
            'total_violations' => 0,
            'violations_by_type' => [],
            'models_with_violations' => []
        ];
        
        foreach ($results as $modelClass => $result) {
            if (isset($result['error'])) {
                continue;
            }
            
            $summary['total_models']++;
            
            if ($result['valid']) {
                $summary['valid_models']++;
            } else {
                $summary['invalid_models']++;
                $summary['models_with_violations'][$modelClass] = count($result['violations']);
                
                foreach ($result['violations'] as $violation) {
                    $summary['total_violations']++;
                    $type = $violation['type'];
                    if (!isset($summary['violations_by_type'][$type])) {
                        $summary['violations_by_type'][$type] = 0;
                    }
                    $summary['violations_by_type'][$type]++;
                }
            }
        }
        
        return [
            'summary' => $summary,
            'details' => $results
        ];
    }
}

// Helper function if not exists
if (!function_exists('app_path')) {
    function app_path($path = '') {
        return dirname(dirname(__DIR__)) . '/app' . ($path ? '/' . $path : '');
    }
}