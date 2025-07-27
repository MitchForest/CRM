<?php

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Initialize database
$capsule = require __DIR__ . '/../../config/database.php';

echo "\n=== CRM ALIGNMENT AUDIT ===\n";
echo "Checking for misalignments between database, models, and code...\n\n";

$issues = [];
$warnings = [];

// Get all model files
$modelPath = __DIR__ . '/app/Models';
$modelFiles = glob($modelPath . '/*.php');

// Track statistics
$stats = [
    'models_checked' => 0,
    'missing_columns' => 0,
    'extra_columns' => 0,
    'cast_mismatches' => 0,
    'relationship_issues' => 0,
    'controller_issues' => 0,
];

foreach ($modelFiles as $modelFile) {
    $modelName = basename($modelFile, '.php');
    $modelClass = "App\\Models\\{$modelName}";
    
    // Skip abstract classes and interfaces
    if (!class_exists($modelClass)) {
        continue;
    }
    
    $reflection = new ReflectionClass($modelClass);
    if ($reflection->isAbstract()) {
        continue;
    }
    
    $stats['models_checked']++;
    
    try {
        $model = new $modelClass;
        $table = $model->getTable();
        
        echo "\nChecking model: {$modelName} (table: {$table})\n";
        echo str_repeat('-', 50) . "\n";
        
        // Check if table exists
        $tableExists = DB::select("SHOW TABLES LIKE '{$table}'");
        if (empty($tableExists)) {
            $issues[] = "ERROR: Table '{$table}' does not exist for model {$modelName}";
            continue;
        }
        
        // Get database columns
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        $dbColumns = array_map(function($col) {
            return $col->Field;
        }, $columns);
        
        // Get model fillable attributes
        $fillable = $model->getFillable();
        
        // Get model casts
        $casts = $model->getCasts();
        
        // Check fillable fields exist in database
        foreach ($fillable as $field) {
            if (!in_array($field, $dbColumns)) {
                $issues[] = "Model {$modelName}: fillable field '{$field}' does not exist in table";
                $stats['missing_columns']++;
            }
        }
        
        // Check casts reference existing columns
        foreach ($casts as $field => $type) {
            if (!in_array($field, $dbColumns)) {
                $issues[] = "Model {$modelName}: cast for '{$field}' references non-existent column";
                $stats['cast_mismatches']++;
            }
        }
        
        // Check model relationships
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip Laravel's built-in methods and constructors
            if (in_array($methodName, ['__construct', '__get', '__set', '__call', 'getAttribute', 'setAttribute', 'getTable', 'getFillable', 'getCasts'])) {
                continue;
            }
            
            // Check if method has no parameters (relationship methods typically don't)
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }
            
            // Try to detect relationship methods
            try {
                $returnType = $method->getReturnType();
                if ($returnType) {
                    $typeName = $returnType->getName();
                    if (strpos($typeName, 'Illuminate\\Database\\Eloquent\\Relations') !== false) {
                        // This is a relationship method
                        $result = $model->$methodName();
                        
                        // Check foreign keys
                        if (method_exists($result, 'getForeignKeyName')) {
                            $foreignKey = $result->getForeignKeyName();
                            $relatedModel = $result->getRelated();
                            $relatedTable = $relatedModel->getTable();
                            
                            // For belongsTo, check if foreign key exists in current table
                            if ($result instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                                if (!in_array($foreignKey, $dbColumns)) {
                                    $issues[] = "Model {$modelName}: relationship '{$methodName}' expects foreign key '{$foreignKey}' which doesn't exist";
                                    $stats['relationship_issues']++;
                                }
                            }
                            // For hasMany/hasOne, check if foreign key exists in related table
                            elseif ($result instanceof \Illuminate\Database\Eloquent\Relations\HasMany || 
                                    $result instanceof \Illuminate\Database\Eloquent\Relations\HasOne) {
                                $relatedTableExists = DB::select("SHOW TABLES LIKE '{$relatedTable}'");
                                if (!empty($relatedTableExists)) {
                                    $relatedColumns = DB::select("SHOW COLUMNS FROM {$relatedTable}");
                                    $relatedDbColumns = array_map(function($col) {
                                        return $col->Field;
                                    }, $relatedColumns);
                                    
                                    if (!in_array($foreignKey, $relatedDbColumns)) {
                                        $warnings[] = "Model {$modelName}: relationship '{$methodName}' expects '{$foreignKey}' in table '{$relatedTable}'";
                                        $stats['relationship_issues']++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore methods that throw exceptions
            }
        }
        
        // Check for hardcoded 'deleted' column references
        if ($model instanceof \App\Models\BaseModel) {
            if (!in_array('deleted', $dbColumns)) {
                $issues[] = "Model {$modelName}: extends BaseModel but has no 'deleted' column";
            }
        }
        
    } catch (Exception $e) {
        $issues[] = "Error checking model {$modelName}: " . $e->getMessage();
    }
}

// Check controllers for hardcoded field references
echo "\n\nChecking controllers for hardcoded field references...\n";
echo str_repeat('=', 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers';
$controllerFiles = glob($controllerPath . '/*.php');

foreach ($controllerFiles as $controllerFile) {
    $controllerName = basename($controllerFile, '.php');
    $content = file_get_contents($controllerFile);
    
    // Common problematic field patterns
    $fieldPatterns = [
        '/->converted\b/' => 'converted',
        '/\[\'converted\'\]/' => 'converted',
        '/\["converted"\]/' => 'converted',
        '/->converted_opp_id\b/' => 'converted_opp_id',
        '/\[\'converted_opp_id\'\]/' => 'converted_opp_id',
        '/\["converted_opp_id"\]/' => 'converted_opp_id',
        '/->deleted\b/' => 'deleted (in non-SuiteCRM table)',
        '/\[\'deleted\'\]/' => 'deleted (in non-SuiteCRM table)',
        '/\["deleted"\]/' => 'deleted (in non-SuiteCRM table)',
    ];
    
    foreach ($fieldPatterns as $pattern => $field) {
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            if ($count > 0) {
                // Get line numbers for better reporting
                $lines = explode("\n", $content);
                $lineNumbers = [];
                $contentOffset = 0;
                foreach ($lines as $lineNum => $line) {
                    if (preg_match($pattern, $line)) {
                        $lineNumbers[] = $lineNum + 1;
                    }
                }
                $warnings[] = "Controller {$controllerName}: references field '{$field}' {$count} times on lines: " . implode(', ', $lineNumbers);
                $stats['controller_issues'] += $count;
            }
        }
    }
}

// Summary Report
echo "\n\n=== AUDIT SUMMARY ===\n";
echo str_repeat('=', 50) . "\n";
echo "Models checked: {$stats['models_checked']}\n";
echo "Missing columns: {$stats['missing_columns']}\n";
echo "Extra columns: {$stats['extra_columns']}\n";
echo "Cast mismatches: {$stats['cast_mismatches']}\n";
echo "Relationship issues: {$stats['relationship_issues']}\n";
echo "Controller field issues: {$stats['controller_issues']}\n";
echo "\nTotal issues found: " . count($issues) . "\n";
echo "Total warnings: " . count($warnings) . "\n";

if (count($issues) > 0) {
    echo "\n\n=== CRITICAL ISSUES ===\n";
    foreach ($issues as $issue) {
        echo "❌ {$issue}\n";
    }
}

if (count($warnings) > 0) {
    echo "\n\n=== WARNINGS ===\n";
    foreach ($warnings as $warning) {
        echo "⚠️  {$warning}\n";
    }
}

// Check specific tables for expected columns
echo "\n\n=== FOREIGN KEY VERIFICATION ===\n";
echo str_repeat('=', 50) . "\n";

$foreignKeyChecks = [
    'form_builder_submissions' => ['form_id'],
    'knowledge_base_feedback' => ['article_id'],
    'activity_tracking_sessions' => ['lead_id'],
    'lead_scores' => ['lead_id'],
    'chat_conversations' => ['lead_id'],
    'form_submissions' => ['lead_id'],
    'tasks' => ['parent_id', 'parent_type'],
    'calls' => ['parent_id', 'parent_type'],
    'meetings' => ['parent_id', 'parent_type', 'contact_id'],
    'notes' => ['parent_id', 'parent_type'],
];

foreach ($foreignKeyChecks as $table => $expectedColumns) {
    $tableExists = DB::select("SHOW TABLES LIKE '{$table}'");
    if (!empty($tableExists)) {
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        $dbColumns = array_map(function($col) {
            return $col->Field;
        }, $columns);
        
        echo "\nTable: {$table}\n";
        foreach ($expectedColumns as $column) {
            if (in_array($column, $dbColumns)) {
                echo "  ✅ {$column} exists\n";
            } else {
                echo "  ❌ {$column} MISSING\n";
                $issues[] = "Table {$table} missing expected foreign key: {$column}";
            }
        }
    } else {
        echo "\n❌ Table {$table} does not exist\n";
    }
}

echo "\n\n=== AUDIT COMPLETE ===\n";
echo "Found " . count($issues) . " critical issues and " . count($warnings) . " warnings\n\n";

// Write detailed report
$reportFile = __DIR__ . '/alignment-audit-report.txt';
$report = "CRM Alignment Audit Report\n";
$report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
$report .= "STATISTICS:\n";
foreach ($stats as $key => $value) {
    $report .= "  " . str_replace('_', ' ', ucfirst($key)) . ": {$value}\n";
}
$report .= "\n\nCRITICAL ISSUES:\n";
foreach ($issues as $issue) {
    $report .= "- {$issue}\n";
}
$report .= "\n\nWARNINGS:\n";
foreach ($warnings as $warning) {
    $report .= "- {$warning}\n";
}

file_put_contents($reportFile, $report);
echo "Detailed report written to: alignment-audit-report.txt\n";