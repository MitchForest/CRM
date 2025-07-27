<?php

/**
 * Generate Model Fillable Arrays from Database Schema
 * 
 * This script connects to the database and generates the exact
 * fillable arrays that should be used in each Eloquent model.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configure database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? 'suitecrm',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? 'root',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Tables to generate fillable arrays for
$tables = [
    'leads' => 'Lead',
    'contacts' => 'Contact',
    'accounts' => 'Account',
    'opportunities' => 'Opportunity',
    'cases' => 'Case',
    'users' => 'User',
    'tasks' => 'Task',
    'calls' => 'Call',
    'meetings' => 'Meeting',
    'notes' => 'Note'
];

// Fields that should never be fillable
$excludedFields = [
    'id',
    'date_entered',
    'date_modified',
    'deleted'  // We'll handle soft deletes through Eloquent
];

echo "<?php\n\n";
echo "/**\n";
echo " * Generated Model Fillable Arrays\n";
echo " * Generated from database schema on " . date('Y-m-d H:i:s') . "\n";
echo " * \n";
echo " * Copy these arrays into your respective models.\n";
echo " */\n\n";

foreach ($tables as $tableName => $modelName) {
    try {
        // Get columns from database
        $columns = Capsule::select("SHOW COLUMNS FROM {$tableName}");
        
        echo "// =====================================\n";
        echo "// Model: {$modelName}\n";
        echo "// Table: {$tableName}\n";
        echo "// =====================================\n\n";
        
        echo "protected \$fillable = [\n";
        
        $fillableFields = [];
        foreach ($columns as $column) {
            if (!in_array($column->Field, $excludedFields)) {
                $fillableFields[] = $column->Field;
                
                // Add comment about field type for clarity
                $comment = "// {$column->Type}";
                if ($column->Null === 'NO' && $column->Default === null && $column->Field !== 'id') {
                    $comment .= " REQUIRED";
                }
                
                echo "    '{$column->Field}', {$comment}\n";
            }
        }
        
        echo "];\n\n";
        
        // Generate validation rules
        echo "// Suggested validation rules based on database schema:\n";
        echo "/*\n";
        echo "protected function rules() {\n";
        echo "    return [\n";
        
        foreach ($columns as $column) {
            if (in_array($column->Field, $excludedFields)) {
                continue;
            }
            
            $rules = [];
            
            // Required/optional
            if ($column->Null === 'NO' && $column->Default === null) {
                $rules[] = 'required';
            } else {
                $rules[] = 'sometimes';
            }
            
            // Data type rules
            if (strpos($column->Type, 'varchar') !== false) {
                preg_match('/varchar\((\d+)\)/', $column->Type, $matches);
                $rules[] = 'string';
                if (isset($matches[1])) {
                    $rules[] = 'max:' . $matches[1];
                }
            } elseif (strpos($column->Type, 'text') !== false) {
                $rules[] = 'string';
                $rules[] = 'max:65535';
            } elseif (strpos($column->Type, 'int') !== false) {
                $rules[] = 'integer';
            } elseif (strpos($column->Type, 'decimal') !== false) {
                $rules[] = 'numeric';
            } elseif (strpos($column->Type, 'datetime') !== false) {
                $rules[] = 'date';
            } elseif (strpos($column->Type, 'date') !== false) {
                $rules[] = 'date';
            } elseif (strpos($column->Type, 'tinyint(1)') !== false) {
                $rules[] = 'boolean';
            }
            
            // Special field rules
            if (strpos($column->Field, 'email') !== false) {
                $rules[] = 'email';
            }
            if (strpos($column->Field, '_id') !== false && $column->Field !== 'id') {
                // Assume foreign key
                $rules[] = 'exists:' . str_replace('_id', 's', $column->Field) . ',id';
            }
            
            echo "        '{$column->Field}' => '" . implode('|', $rules) . "',\n";
        }
        
        echo "    ];\n";
        echo "}\n";
        echo "*/\n\n";
        
    } catch (\Exception $e) {
        echo "// ERROR processing table {$tableName}: " . $e->getMessage() . "\n\n";
    }
}

// Generate field mapping documentation
echo "// =====================================\n";
echo "// FIELD MAPPING REFERENCE\n";
echo "// =====================================\n";
echo "/*\n";
echo "Key fields that differ from common naming:\n";
echo "- Email: Use 'email1' (not 'email')\n";
echo "- Phone: Use 'phone_work', 'phone_mobile' (not generic 'phone')\n";
echo "- Company: Use 'account_name' (not 'company')\n";
echo "- Address: Use 'primary_address_*' prefix\n";
echo "- User reference: Use 'assigned_user_id'\n";
echo "- Soft delete: Use 'deleted' (tinyint 0/1)\n";
echo "*/\n";