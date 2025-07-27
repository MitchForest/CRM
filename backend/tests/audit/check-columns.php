<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Initialize database
$capsule = require __DIR__ . '/config/database.php';

echo "=== CHECKING DATABASE COLUMNS ===\n\n";

$tables = [
    'activity_tracking_sessions',
    'contacts', 
    'meetings',
    'calls',
    'customer_health_scores',
    'knowledge_base_feedback'
];

foreach ($tables as $table) {
    $tableExists = DB::select("SHOW TABLES LIKE '{$table}'");
    if (!empty($tableExists)) {
        echo "Table: {$table}\n";
        echo str_repeat('-', 50) . "\n";
        
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        foreach ($columns as $column) {
            echo "  {$column->Field} ({$column->Type})" . ($column->Null === 'NO' ? ' NOT NULL' : '') . "\n";
        }
        echo "\n";
    } else {
        echo "❌ Table {$table} does not exist\n\n";
    }
}

// Check the tables mentioned as missing
echo "\n=== CHECKING TABLES THAT MIGHT BE MISSING ===\n\n";

$missingTables = [
    'lead_scores',
    'chat_conversations', 
    'form_submissions',
    'ai_lead_scoring_history',
    'ai_chat_conversations',
    'ai_chat_messages'
];

foreach ($missingTables as $table) {
    $tableExists = DB::select("SHOW TABLES LIKE '{$table}'");
    if (!empty($tableExists)) {
        echo "✅ Table {$table} exists\n";
    } else {
        echo "❌ Table {$table} does not exist\n";
    }
}