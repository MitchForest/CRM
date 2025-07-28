#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

echo "Running form builder tables migration...\n";

try {
    $schema = Capsule::schema();
    
    // Create form_builder_forms table
    if (!$schema->hasTable('form_builder_forms')) {
        $schema->create('form_builder_forms', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->json('fields');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->datetime('date_entered')->nullable();
            $table->datetime('date_modified')->nullable();
            $table->boolean('deleted')->default(false);
            
            $table->index('name');
            $table->index('is_active');
            $table->index('deleted');
        });
        echo "✓ Created form_builder_forms table\n";
    } else {
        echo "⚠ Table form_builder_forms already exists\n";
    }

    // Create form_builder_submissions table
    if (!$schema->hasTable('form_builder_submissions')) {
        $schema->create('form_builder_submissions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('form_id', 36);
            $table->json('data');
            $table->char('lead_id', 36)->nullable();
            $table->char('contact_id', 36)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->datetime('date_entered')->nullable();
            $table->boolean('deleted')->default(false);
            
            $table->index('form_id');
            $table->index('lead_id');
            $table->index('contact_id');
            $table->index('date_entered');
            $table->index('deleted');
            
            // Note: Foreign key constraints are commented out to avoid issues if referenced tables don't exist
            // $table->foreign('form_id')->references('id')->on('form_builder_forms');
            // $table->foreign('lead_id')->references('id')->on('leads');
            // $table->foreign('contact_id')->references('id')->on('contacts');
        });
        echo "✓ Created form_builder_submissions table\n";
    } else {
        echo "⚠ Table form_builder_submissions already exists\n";
    }

    echo "\n✅ Form builder migration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}