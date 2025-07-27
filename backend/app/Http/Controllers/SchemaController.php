<?php

namespace App\Http\Controllers;

use App\Services\SchemaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SchemaController extends Controller
{
    private SchemaService $schemaService;
    
    public function __construct()
    {
        parent::__construct();
        // Manual instantiation for Slim (no automatic DI)
        $this->schemaService = new SchemaService();
    }
    
    /**
     * Get complete database schema
     * 
     * @return Response
     */
    public function getFullSchema(Request $request, Response $response, array $args): Response
    {
        $schema = $this->schemaService->getFullSchema();
        
        return $this->json($response, $schema);
    }
    
    /**
     * Get validation rules for all tables
     * 
     * @return Response
     */
    public function getValidationRules(Request $request, Response $response, array $args): Response
    {
        $rules = $this->schemaService->getValidationRules();
        
        return $this->json($response, [
            'generated_at' => (new \DateTime())->format('c'),
            'rules' => $rules
        ]);
    }
    
    /**
     * Get enum values for dropdown fields
     * 
     * @param Request $request
     * @return Response
     */
    public function getEnumValues(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $table = $params['table'] ?? null;
        $enums = $this->schemaService->getEnumValues($table);
        
        return $this->json($response, [
            'table' => $table,
            'enums' => $enums
        ]);
    }
    
    /**
     * Get OpenAPI specification
     * 
     * @return Response
     */
    public function getOpenAPISpec(Request $request, Response $response, array $args): Response
    {
        $spec = $this->schemaService->getOpenAPISpec();
        
        return $this->json($response, $spec);
    }
    
    /**
     * Get TypeScript types (frontend helper)
     * 
     * @return Response
     */
    public function getTypeScriptTypes(Request $request, Response $response, array $args): Response
    {
        $schema = $this->schemaService->getFullSchema();
        $output = "// Generated from database schema on " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($schema['tables'] as $tableName => $tableInfo) {
            $interfaceName = $this->tableNameToInterface($tableName);
            $output .= "export interface {$interfaceName}DB {\n";
            
            foreach ($tableInfo['fields'] as $fieldName => $field) {
                $tsType = $this->mapToTypeScriptType($field);
                $nullable = $field['nullable'] ? ' | null' : '';
                $output .= "  {$fieldName}: {$tsType}{$nullable};\n";
            }
            
            $output .= "}\n\n";
            
            // Create request/response types
            $output .= "export type {$interfaceName}CreateRequest = Pick<{$interfaceName}DB,\n";
            $output .= "  " . $this->getCreateFields($tableName, $tableInfo) . "\n";
            $output .= ">;\n\n";
            
            $output .= "export type {$interfaceName}UpdateRequest = Partial<{$interfaceName}CreateRequest>;\n\n";
            
            $output .= "export type {$interfaceName}Response = {$interfaceName}DB;\n\n";
        }
        
        $response->getBody()->write($output);
        return $response
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Disposition', 'attachment; filename="database-types.ts"');
    }
    
    /**
     * Get field mapping documentation
     * 
     * @return Response
     */
    public function getFieldMapping(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, [
            'critical_mappings' => [
                'leads' => [
                    'email' => 'Use email1, not email',
                    'company' => 'Use account_name, not company',
                    'phone' => 'Use phone_work or phone_mobile, not generic phone'
                ],
                'contacts' => [
                    'email' => 'Use email1, not email',
                    'phone' => 'Use phone_work or phone_mobile'
                ],
                'accounts' => [
                    'company_name' => 'Use name field',
                    'phone' => 'Use phone_office, not phone_work'
                ],
                'users' => [
                    'email' => 'Just email, not email1 (different from other modules)'
                ]
            ],
            'address_patterns' => [
                'leads_contacts' => 'primary_address_*',
                'accounts' => 'billing_address_*',
                'users' => 'address_* (no prefix)'
            ],
            'common_mistakes' => [
                'email_vs_email1' => 'Most modules use email1, except users table',
                'company_vs_account_name' => 'Leads use account_name for company',
                'phone_fields' => 'Always use specific fields like phone_work, phone_mobile',
                'soft_deletes' => 'Use deleted field (0/1), never actually DELETE records'
            ]
        ]);
    }
    
    /**
     * Convert table name to TypeScript interface name
     */
    private function tableNameToInterface(string $tableName): string
    {
        // Handle special pluralization cases
        if (substr($tableName, -3) === 'ies') {
            // opportunities -> opportunity, activities -> activity
            $name = substr($tableName, 0, -3) . 'y';
        } elseif (substr($tableName, -4) === 'sses') {
            // classes -> class, addresses -> address
            $name = substr($tableName, 0, -2);
        } elseif (substr($tableName, -1) === 's') {
            // Regular plural: leads -> lead, contacts -> contact
            $name = substr($tableName, 0, -1);
        } else {
            // Not plural or already singular
            $name = $tableName;
        }
        return ucfirst($name);
    }
    
    /**
     * Map database types to TypeScript types
     */
    private function mapToTypeScriptType(array $field): string
    {
        switch ($field['type']) {
            case 'string':
                return 'string';
            case 'integer':
                return 'number';
            case 'decimal':
                return 'number';
            case 'boolean':
                return 'boolean';
            case 'date':
            case 'datetime':
                return 'Date | string';
            case 'json':
                return 'Record<string, any>';
            default:
                return 'string';
        }
    }
    
    /**
     * Get fields for create request type
     */
    private function getCreateFields(string $tableName, array $tableInfo): string
    {
        $excludeFields = ['id', 'date_entered', 'date_modified', 'deleted'];
        $fields = [];
        
        foreach ($tableInfo['fields'] as $fieldName => $field) {
            if (!in_array($fieldName, $excludeFields)) {
                $fields[] = "'{$fieldName}'";
            }
        }
        
        return implode(' | ', $fields);
    }
}