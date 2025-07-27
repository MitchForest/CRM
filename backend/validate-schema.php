<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use App\Services\SchemaValidationService;

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
    if (isset($result['valid']) && $result['valid']) {
        echo "├─ {$modelClass} → {$result['table']}\n";
    }
}

// Summary
if ($summary['invalid_models'] > 0) {
    echo "\n❌ Schema validation failed! Fix the violations above.\n";
} else {
    echo "\n✅ All models are properly aligned with database schema!\n";
}