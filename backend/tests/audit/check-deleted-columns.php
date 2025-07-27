<?php

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Initialize database
$capsule = require __DIR__ . '/../../config/database.php';

echo "=== CHECKING DELETED COLUMNS ===\n\n";

// Tables referenced in controllers with ->deleted
$tablesToCheck = [
    'documents' => 'DocumentController',
    'email_templates' => 'EmailController', 
    'cases' => 'CasesController',
    'contacts' => 'ContactsController',
    'opportunities' => 'OpportunitiesController',
    'tasks' => 'ActivitiesController (tasks)',
    'calls' => 'ActivitiesController (calls)',
    'meetings' => 'ActivitiesController (meetings)',
    'notes' => 'ActivitiesController (notes)',
    'leads' => 'LeadsController',
    'form_builder_forms' => 'FormBuilderController'
];

foreach ($tablesToCheck as $table => $controller) {
    $tableExists = DB::select("SHOW TABLES LIKE '{$table}'");
    if (!empty($tableExists)) {
        $columns = DB::select("SHOW COLUMNS FROM {$table} WHERE Field = 'deleted'");
        if (!empty($columns)) {
            echo "✅ Table {$table} has 'deleted' column (used by {$controller})\n";
        } else {
            echo "❌ Table {$table} MISSING 'deleted' column (used by {$controller})\n";
        }
    } else {
        echo "❌ Table {$table} does not exist (used by {$controller})\n";
    }
}