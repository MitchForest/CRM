<?php

namespace App\Commands;

use App\Services\SchemaValidationService;

class ValidateSchemaCommand
{
    public function execute(): void
    {
        echo "🔍 Validating Backend Schema Alignment...\n\n";
        
        $validator = new SchemaValidationService();
        $report = $validator->generateReport();
        
        // Print summary
        $summary = $report['summary'];
        echo "📊 SUMMARY:\n";
        echo "├─ Total Models: {$summary['total_models']}\n";
        echo "├─ Valid Models: {$summary['valid_models']} ✅\n";
        echo "├─ Invalid Models: {$summary['invalid_models']} ❌\n";
        echo "└─ Total Violations: {$summary['total_violations']}\n\n";
        
        // Print violations by type
        if (!empty($summary['violations_by_type'])) {
            echo "⚠️  VIOLATIONS BY TYPE:\n";
            foreach ($summary['violations_by_type'] as $type => $count) {
                echo "├─ {$type}: {$count}\n";
            }
            echo "\n";
        }
        
        // Print details for models with violations
        if (!empty($summary['models_with_violations'])) {
            echo "❌ MODELS WITH VIOLATIONS:\n\n";
            
            foreach ($report['details'] as $modelClass => $result) {
                if (!$result['valid'] && !empty($result['violations'])) {
                    echo "📋 {$modelClass} (Table: {$result['table']})\n";
                    
                    foreach ($result['violations'] as $violation) {
                        echo "   ├─ [{$violation['type']}] {$violation['message']}\n";
                    }
                    echo "\n";
                }
            }
        }
        
        // Print valid models
        echo "✅ VALID MODELS:\n";
        foreach ($report['details'] as $modelClass => $result) {
            if ($result['valid']) {
                echo "├─ {$modelClass} → {$result['table']}\n";
            }
        }
        
        // Exit with error code if violations found
        if ($summary['invalid_models'] > 0) {
            echo "\n❌ Schema validation failed! Fix the violations above.\n";
            exit(1);
        } else {
            echo "\n✅ All models are properly aligned with database schema!\n";
            exit(0);
        }
    }
}

// Run the command if executed directly
if (php_sapi_name() === 'cli' && basename($_SERVER['argv'][0]) === basename(__FILE__)) {
    require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
    
    $command = new ValidateSchemaCommand();
    $command->execute();
}