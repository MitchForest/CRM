#!/usr/bin/env php
<?php
/**
 * Generate TypeScript types and Zod schemas from PHP DTOs
 * Usage: php generate-types.php
 */

require_once __DIR__ . '/../../suitecrm/include/entryPoint.php';

// Configure output paths
$typeScriptOutputPath = __DIR__ . '/../../../frontend/src/types/api.generated.ts';
$zodOutputPath = __DIR__ . '/../../../frontend/src/types/api.schemas.ts';

// DTO classes to process
$dtoClasses = [
    // Base DTOs
    'Api\DTO\Base\PaginationDTO',
    'Api\DTO\Base\ErrorDTO',
    
    // Entity DTOs
    'Api\DTO\ContactDTO',
    'Api\DTO\LeadDTO',
    'Api\DTO\OpportunityDTO',
    'Api\DTO\TaskDTO',
    'Api\DTO\CaseDTO',
    'Api\DTO\EmailDTO',
    'Api\DTO\CallDTO',
    'Api\DTO\MeetingDTO',
    'Api\DTO\NoteDTO',
    'Api\DTO\QuoteDTO',
    'Api\DTO\ActivityDTO',
    
    // Auth DTOs
    'Api\DTO\Auth\LoginRequestDTO',
    'Api\DTO\Auth\LoginResponseDTO',
    'Api\DTO\Auth\RefreshTokenRequestDTO',
    'Api\DTO\Auth\RefreshTokenResponseDTO',
    
    // Response DTOs
    'Api\DTO\Response\ApiResponseDTO',
    'Api\DTO\Response\ListResponseDTO',
];

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Api\\';
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Generate TypeScript interfaces
$typeScriptContent = "/**\n * Auto-generated TypeScript types from PHP DTOs\n * Generated: " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT MANUALLY\n */\n\n";

$zodContent = "/**\n * Auto-generated Zod schemas from PHP DTOs\n * Generated: " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT MANUALLY\n */\n\nimport { z } from 'zod';\n\n";

$generatedInterfaces = [];
$generatedSchemas = [];

foreach ($dtoClasses as $className) {
    if (!class_exists($className)) {
        echo "Warning: Class $className not found, skipping...\n";
        continue;
    }
    
    try {
        $reflection = new ReflectionClass($className);
        
        // Skip if not a DTO
        if (!$reflection->isSubclassOf('Api\DTO\Base\BaseDTO')) {
            echo "Warning: $className is not a DTO, skipping...\n";
            continue;
        }
        
        // Create instance to get type definitions
        $instance = $reflection->newInstance();
        
        // Get TypeScript interface
        if (method_exists($instance, 'getTypeScriptInterface')) {
            $interface = $instance->getTypeScriptInterface();
            if (!in_array($interface, $generatedInterfaces)) {
                $typeScriptContent .= $interface . "\n\n";
                $generatedInterfaces[] = $interface;
            }
        }
        
        // Get Zod schema
        if (method_exists($instance, 'getZodSchema')) {
            $schema = $instance->getZodSchema();
            if (!in_array($schema, $generatedSchemas)) {
                $zodContent .= $schema . "\n\n";
                $generatedSchemas[] = $schema;
            }
        }
        
        echo "✓ Generated types for $className\n";
        
    } catch (Exception $e) {
        echo "✗ Error processing $className: " . $e->getMessage() . "\n";
    }
}

// Add utility types
$typeScriptContent .= <<<TS
// Utility types
export type ApiResponse<T> = {
  success: boolean;
  data?: T;
  error?: ErrorResponse;
  pagination?: Pagination;
};

export type ListResponse<T> = {
  data: T[];
  pagination: Pagination;
};

// Re-export all schemas for convenience
export * from './api.schemas';
TS;

// Add utility schemas
$zodContent .= <<<TS
// Utility schemas
export const ApiResponseSchema = <T extends z.ZodType>(dataSchema: T) =>
  z.object({
    success: z.boolean(),
    data: dataSchema.optional(),
    error: ErrorResponseSchema.optional(),
    pagination: PaginationSchema.optional()
  });

export const ListResponseSchema = <T extends z.ZodType>(itemSchema: T) =>
  z.object({
    data: z.array(itemSchema),
    pagination: PaginationSchema
  });

// Type inference helpers
export type Contact = z.infer<typeof ContactSchema>;
export type Lead = z.infer<typeof LeadSchema>;
export type Opportunity = z.infer<typeof OpportunitySchema>;
export type Task = z.infer<typeof TaskSchema>;
export type Case = z.infer<typeof CaseSchema>;
export type Email = z.infer<typeof EmailSchema>;
export type Call = z.infer<typeof CallSchema>;
export type Meeting = z.infer<typeof MeetingSchema>;
export type Note = z.infer<typeof NoteSchema>;
export type Quote = z.infer<typeof QuoteSchema>;
export type Activity = z.infer<typeof ActivitySchema>;
export type Pagination = z.infer<typeof PaginationSchema>;
export type ErrorResponse = z.infer<typeof ErrorResponseSchema>;
TS;

// Create frontend directory if it doesn't exist
$frontendDir = dirname($typeScriptOutputPath);
if (!is_dir($frontendDir)) {
    mkdir($frontendDir, 0777, true);
}

// Write TypeScript file
file_put_contents($typeScriptOutputPath, $typeScriptContent);
echo "\n✓ TypeScript types written to: $typeScriptOutputPath\n";

// Write Zod schemas file
file_put_contents($zodOutputPath, $zodContent);
echo "✓ Zod schemas written to: $zodOutputPath\n";

echo "\nGeneration complete!\n";