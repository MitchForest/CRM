<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * StrictModel enforces database schema compliance
 * 
 * Features:
 * - Validates fillable fields against actual database columns
 * - Prevents setting non-existent columns
 * - Warns about missing casts
 * - Ensures no camelCase fields
 */
abstract class StrictModel extends Model
{
    protected static array $schemaCache = [];
    protected bool $strictMode = true;
    
    /**
     * Boot the model and add schema validation
     */
    protected static function boot()
    {
        parent::boot();
        
        // Validate on save
        static::saving(function ($model) {
            if ($model->strictMode) {
                $model->validateSchema();
            }
        });
    }
    
    /**
     * Set an attribute on the model
     * 
     * @throws \InvalidArgumentException if column doesn't exist in strict mode
     */
    public function setAttribute($key, $value)
    {
        if ($this->strictMode && !$this->isValidColumn($key)) {
            throw new \InvalidArgumentException(
                "Column '{$key}' does not exist in table '{$this->getTable()}'"
            );
        }
        
        // Check for camelCase
        if ($this->strictMode && $this->isCamelCase($key)) {
            throw new \InvalidArgumentException(
                "Column '{$key}' uses camelCase. Use snake_case instead."
            );
        }
        
        return parent::setAttribute($key, $value);
    }
    
    /**
     * Validate model schema
     */
    public function validateSchema(): array
    {
        $violations = [];
        $columns = $this->getTableSchema();
        
        // Check fillable fields
        foreach ($this->fillable as $field) {
            if (!isset($columns[$field])) {
                $violations[] = "Fillable field '{$field}' does not exist in database";
            }
        }
        
        // Check for missing fillable fields
        foreach ($columns as $column => $info) {
            // Skip system fields
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            if (!in_array($column, $this->fillable) && !in_array($column, $this->hidden)) {
                $violations[] = "Database column '{$column}' is not in fillable or hidden arrays";
            }
        }
        
        // Check casts
        foreach ($columns as $column => $info) {
            $expectedCast = $this->getExpectedCast($info['type']);
            if ($expectedCast && !isset($this->casts[$column])) {
                $violations[] = "Column '{$column}' should be cast to '{$expectedCast}'";
            }
        }
        
        // Check for appends
        if (!empty($this->appends)) {
            foreach ($this->appends as $append) {
                $violations[] = "Appended attribute '{$append}' creates non-database field";
            }
        }
        
        if (!empty($violations)) {
            throw new \RuntimeException(
                "Model validation failed for " . get_class($this) . ":\n" . 
                implode("\n", $violations)
            );
        }
        
        return $violations;
    }
    
    /**
     * Get table schema from database
     */
    protected function getTableSchema(): array
    {
        $table = $this->getTable();
        
        if (isset(static::$schemaCache[$table])) {
            return static::$schemaCache[$table];
        }
        
        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
            $schema = [];
            
            foreach ($columns as $column) {
                $schema[$column->Field] = [
                    'type' => $column->Type,
                    'null' => $column->Null === 'YES',
                    'key' => $column->Key,
                    'default' => $column->Default,
                    'extra' => $column->Extra
                ];
            }
            
            static::$schemaCache[$table] = $schema;
            return $schema;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Check if column exists in database
     */
    protected function isValidColumn(string $key): bool
    {
        // Allow timestamps and special fields
        if (in_array($key, ['created_at', 'updated_at', 'deleted_at', 'remember_token'])) {
            return true;
        }
        
        $schema = $this->getTableSchema();
        return isset($schema[$key]);
    }
    
    /**
     * Check if string is camelCase
     */
    protected function isCamelCase(string $str): bool
    {
        return $str !== strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
    }
    
    /**
     * Get expected cast type for database column type
     */
    protected function getExpectedCast(string $dbType): ?string
    {
        $type = strtolower($dbType);
        
        if (str_contains($type, 'tinyint(1)')) {
            return 'boolean';
        }
        
        if (str_contains($type, 'int')) {
            return 'integer';
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
        
        return null;
    }
    
    /**
     * Get only database columns from attributes
     */
    public function getDatabaseAttributes(): array
    {
        $schema = $this->getTableSchema();
        $attributes = [];
        
        foreach ($this->attributes as $key => $value) {
            if (isset($schema[$key])) {
                $attributes[$key] = $value;
            }
        }
        
        return $attributes;
    }
    
    /**
     * Convert model to array ensuring only database fields
     */
    public function toArrayStrict(): array
    {
        $array = parent::toArray();
        $schema = $this->getTableSchema();
        $result = [];
        
        foreach ($array as $key => $value) {
            // Only include actual database columns
            if (isset($schema[$key]) || in_array($key, ['created_at', 'updated_at'])) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}