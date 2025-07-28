#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "Checking database data...\n\n";

try {
    // Check opportunities
    $opportunityCount = Capsule::table('opportunities')->where('deleted', 0)->count();
    echo "Opportunities: {$opportunityCount} records\n";
    
    // Check forms
    $formCount = Capsule::table('form_builder_forms')->where('deleted', 0)->count();
    echo "Forms: {$formCount} records\n";
    
    // Check form submissions
    $submissionCount = Capsule::table('form_builder_submissions')->where('deleted', 0)->count();
    echo "Form Submissions: {$submissionCount} records\n";
    
    // Show sample opportunities
    echo "\nSample Opportunities:\n";
    $opportunities = Capsule::table('opportunities')
        ->where('deleted', 0)
        ->limit(5)
        ->get(['id', 'name', 'amount', 'sales_stage']);
    
    foreach ($opportunities as $opp) {
        echo "  - {$opp->name}: \${$opp->amount} ({$opp->sales_stage})\n";
    }
    
    // Show sample forms
    echo "\nSample Forms:\n";
    $forms = Capsule::table('form_builder_forms')
        ->where('deleted', 0)
        ->limit(5)
        ->get(['id', 'name', 'description']);
    
    foreach ($forms as $form) {
        echo "  - {$form->name}: {$form->description}\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}