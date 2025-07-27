<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

class SchemaService
{
    /**
     * Get complete database schema for all tables
     */
    public function getFullSchema(): array
    {
        // Remove cache for now - can add simple file caching later if needed
        $tables = $this->getCoreTables();
        $schema = [];
        
        foreach ($tables as $table) {
            $schema[$table] = [
                'fields' => $this->getTableFields($table),
                'indexes' => $this->getTableIndexes($table),
                'required' => $this->getRequiredFields($table),
                'enums' => $this->getEnumValues($table),
                'relations' => $this->getTableRelations($table)
            ];
        }
        
        return [
            'version' => $this->getSchemaVersion(),
            'generated_at' => (new \DateTime())->format('c'),
            'tables' => $schema
        ];
    }
    
    /**
     * Get validation rules for all tables
     */
    public function getValidationRules(): array
    {
        $tables = $this->getCoreTables();
        $rules = [];
        
        foreach ($tables as $table) {
            $rules[$table] = [
                'create' => $this->generateValidationRules($table),
                'update' => $this->generateValidationRules($table, false),
                'field_labels' => $this->getFieldLabels($table)
            ];
        }
        
        return $rules;
    }
    
    /**
     * Get enum values for dropdown fields
     */
    public function getEnumValues($table = null): array
    {
        $enums = [
            'leads' => [
                'status' => ['new', 'contacted', 'qualified', 'converted', 'dead'],
                'lead_source' => ['website', 'referral', 'cold_call', 'conference', 'advertisement'],
                'salutation' => ['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Prof.']
            ],
            'contacts' => [
                'salutation' => ['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Prof.'],
                'lead_source' => ['website', 'referral', 'conversion', 'other']
            ],
            'opportunities' => [
                'sales_stage' => ['prospecting', 'qualification', 'needs_analysis', 'value_proposition', 'decision_makers', 'perception_analysis', 'proposal', 'negotiation', 'closed_won', 'closed_lost'],
                'opportunity_type' => ['new_business', 'existing_business', 'renewal']
            ],
            'cases' => [
                'status' => ['new', 'assigned', 'pending', 'resolved', 'closed'],
                'priority' => ['P1', 'P2', 'P3'],
                'type' => ['bug', 'feature_request', 'question', 'other']
            ],
            'users' => [
                'status' => ['active', 'inactive']
            ]
        ];
        
        return $table ? ($enums[$table] ?? []) : $enums;
    }
    
    /**
     * Get OpenAPI specification
     */
    public function getOpenAPISpec(): array
    {
        $tables = $this->getCoreTables();
        $schemas = [];
        
        foreach ($tables as $table) {
            $schemas[ucfirst(rtrim($table, 's'))] = $this->generateOpenAPISchema($table);
        }
        
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Sassy CRM API',
                'version' => $this->getSchemaVersion(),
                'description' => 'Database-aligned REST API for Sassy CRM'
            ],
            'components' => [
                'schemas' => $schemas
            ]
        ];
    }
    
    /**
     * Get table fields with detailed information
     */
    private function getTableFields(string $table): array
    {
        $columns = DB::connection()->select("
            SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as type,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                COLUMN_KEY as `key`,
                EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$_ENV['DB_DATABASE'] ?? 'suitecrm', $table]);
        
        $fields = [];
        foreach ($columns as $column) {
            $fields[$column->name] = [
                'type' => $this->mapSQLTypeToGeneric($column->type),
                'sql_type' => $column->type,
                'max_length' => $column->max_length,
                'nullable' => $column->nullable === 'YES',
                'default' => $column->default_value,
                'primary' => $column->key === 'PRI',
                'unique' => $column->key === 'UNI',
                'auto_increment' => strpos($column->extra, 'auto_increment') !== false
            ];
        }
        
        return $fields;
    }
    
    /**
     * Get table indexes
     */
    private function getTableIndexes(string $table): array
    {
        $indexes = DB::connection()->select("SHOW INDEX FROM $table");
        $result = [];
        
        foreach ($indexes as $index) {
            if (!isset($result[$index->Key_name])) {
                $result[$index->Key_name] = [
                    'unique' => !$index->Non_unique,
                    'columns' => []
                ];
            }
            $result[$index->Key_name]['columns'][] = $index->Column_name;
        }
        
        return $result;
    }
    
    /**
     * Get required fields (NOT NULL without default)
     */
    private function getRequiredFields(string $table): array
    {
        $columns = DB::connection()->select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
            AND IS_NULLABLE = 'NO'
            AND COLUMN_DEFAULT IS NULL
            AND COLUMN_NAME NOT IN ('id', 'date_entered', 'date_modified', 'deleted')
        ", [$_ENV['DB_DATABASE'] ?? 'suitecrm', $table]);
        
        return array_column($columns, 'COLUMN_NAME');
    }
    
    /**
     * Get table relations (foreign keys)
     */
    private function getTableRelations(string $table): array
    {
        // Manually define relations since SuiteCRM doesn't use foreign key constraints
        $relations = [
            'leads' => [
                'assigned_user_id' => 'users',
                'converted_contact_id' => 'contacts',
                'converted_account_id' => 'accounts',
                'converted_opportunity_id' => 'opportunities'
            ],
            'contacts' => [
                'assigned_user_id' => 'users',
                'account_id' => 'accounts'
            ],
            'accounts' => [
                'assigned_user_id' => 'users'
            ],
            'opportunities' => [
                'assigned_user_id' => 'users',
                'account_id' => 'accounts'
            ],
            'cases' => [
                'assigned_user_id' => 'users',
                'account_id' => 'accounts',
                'contact_id' => 'contacts'
            ]
        ];
        
        return $relations[$table] ?? [];
    }
    
    /**
     * Generate validation rules for a table
     */
    private function generateValidationRules(string $table, bool $forCreate = true): array
    {
        $fields = $this->getTableFields($table);
        $required = $this->getRequiredFields($table);
        $rules = [];
        
        foreach ($fields as $fieldName => $field) {
            if (in_array($fieldName, ['id', 'date_entered', 'date_modified', 'deleted'])) {
                continue;
            }
            
            $fieldRules = [];
            
            // Required/optional
            if ($forCreate && in_array($fieldName, $required)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'sometimes';
            }
            
            // Type-based rules
            switch ($field['type']) {
                case 'string':
                    $fieldRules[] = 'string';
                    if ($field['max_length']) {
                        $fieldRules[] = 'max:' . $field['max_length'];
                    }
                    break;
                case 'integer':
                    $fieldRules[] = 'integer';
                    break;
                case 'decimal':
                    $fieldRules[] = 'numeric';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'datetime':
                    $fieldRules[] = 'date';
                    break;
                case 'json':
                    $fieldRules[] = 'array';
                    break;
            }
            
            // Special field rules
            if (strpos($fieldName, 'email') !== false) {
                $fieldRules[] = 'email';
            }
            if (strpos($fieldName, '_id') !== false && $fieldName !== 'id') {
                $relatedTable = $this->getTableRelations($table)[$fieldName] ?? null;
                if ($relatedTable) {
                    $fieldRules[] = 'exists:' . $relatedTable . ',id';
                }
            }
            if ($fieldName === 'website') {
                $fieldRules[] = 'url';
            }
            
            // Enum validation
            $enums = $this->getEnumValues($table);
            if (isset($enums[$fieldName])) {
                $fieldRules[] = 'in:' . implode(',', $enums[$fieldName]);
            }
            
            $rules[$fieldName] = implode('|', $fieldRules);
        }
        
        return $rules;
    }
    
    /**
     * Get user-friendly field labels
     */
    private function getFieldLabels(string $table): array
    {
        $labels = [
            'leads' => [
                'email1' => 'Email',
                'phone_work' => 'Work Phone',
                'phone_mobile' => 'Mobile Phone',
                'account_name' => 'Company',
                'primary_address_street' => 'Street Address',
                'primary_address_city' => 'City',
                'primary_address_state' => 'State/Province',
                'primary_address_postalcode' => 'Postal Code',
                'primary_address_country' => 'Country',
                'assigned_user_id' => 'Assigned To'
            ],
            'contacts' => [
                'email1' => 'Email',
                'phone_work' => 'Work Phone',
                'phone_mobile' => 'Mobile Phone',
                'account_id' => 'Account',
                'assigned_user_id' => 'Assigned To'
            ],
            'accounts' => [
                'name' => 'Account Name',
                'phone_office' => 'Office Phone',
                'email1' => 'Email',
                'billing_address_street' => 'Billing Street',
                'billing_address_city' => 'Billing City',
                'billing_address_state' => 'Billing State',
                'billing_address_postalcode' => 'Billing Postal Code',
                'billing_address_country' => 'Billing Country'
            ]
        ];
        
        return $labels[$table] ?? [];
    }
    
    /**
     * Generate OpenAPI schema for a table
     */
    private function generateOpenAPISchema(string $table): array
    {
        $fields = $this->getTableFields($table);
        $required = $this->getRequiredFields($table);
        $properties = [];
        
        foreach ($fields as $fieldName => $field) {
            $property = [
                'type' => $this->mapTypeToOpenAPI($field['type']),
            ];
            
            if ($field['max_length']) {
                $property['maxLength'] = $field['max_length'];
            }
            
            if ($fieldName === 'id') {
                $property['format'] = 'uuid';
                $property['readOnly'] = true;
            }
            
            if (strpos($fieldName, 'email') !== false) {
                $property['format'] = 'email';
            }
            
            if ($field['type'] === 'datetime') {
                $property['format'] = 'date-time';
            }
            
            if ($field['type'] === 'date') {
                $property['format'] = 'date';
            }
            
            // Add description for confusing fields
            if ($fieldName === 'email1') {
                $property['description'] = 'Primary email address (field name is email1, not email)';
            }
            if ($fieldName === 'account_name') {
                $property['description'] = 'Company name (field name is account_name, not company)';
            }
            
            $properties[$fieldName] = $property;
        }
        
        return [
            'type' => 'object',
            'required' => $required,
            'properties' => $properties
        ];
    }
    
    /**
     * Map SQL types to generic types
     */
    private function mapSQLTypeToGeneric(string $sqlType): string
    {
        if (strpos($sqlType, 'varchar') !== false || strpos($sqlType, 'text') !== false || strpos($sqlType, 'char') !== false) {
            return 'string';
        }
        if (strpos($sqlType, 'int') !== false || strpos($sqlType, 'tinyint') !== false) {
            return strpos($sqlType, 'tinyint(1)') !== false ? 'boolean' : 'integer';
        }
        if (strpos($sqlType, 'decimal') !== false || strpos($sqlType, 'float') !== false || strpos($sqlType, 'double') !== false) {
            return 'decimal';
        }
        if (strpos($sqlType, 'datetime') !== false || strpos($sqlType, 'timestamp') !== false) {
            return 'datetime';
        }
        if (strpos($sqlType, 'date') !== false) {
            return 'date';
        }
        if (strpos($sqlType, 'json') !== false) {
            return 'json';
        }
        return 'string';
    }
    
    /**
     * Map generic types to OpenAPI types
     */
    private function mapTypeToOpenAPI(string $type): string
    {
        $mapping = [
            'string' => 'string',
            'integer' => 'integer',
            'decimal' => 'number',
            'boolean' => 'boolean',
            'date' => 'string',
            'datetime' => 'string',
            'json' => 'object'
        ];
        
        return $mapping[$type] ?? 'string';
    }
    
    /**
     * Get core tables to expose
     */
    private function getCoreTables(): array
    {
        return [
            'leads',
            'contacts',
            'accounts',
            'opportunities',
            'cases',
            'users',
            'tasks',
            'calls',
            'meetings',
            'notes'
        ];
    }
    
    /**
     * Get schema version
     */
    private function getSchemaVersion(): string
    {
        // Could be stored in database or config
        return '1.0.0';
    }
}