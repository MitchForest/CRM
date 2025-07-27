<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../../vendor/autoload.php';

// Initialize database
$capsule = require __DIR__ . '/../../config/database.php';

echo "=== FIXING MISSING FOREIGN KEYS ===\n\n";

// Add missing columns to activity_tracking_sessions
echo "Updating activity_tracking_sessions table...\n";
Capsule::schema()->table('activity_tracking_sessions', function (Blueprint $table) {
    if (!Capsule::schema()->hasColumn('activity_tracking_sessions', 'lead_id')) {
        $table->char('lead_id', 36)->nullable()->after('visitor_id');
        $table->index('lead_id');
    }
    if (!Capsule::schema()->hasColumn('activity_tracking_sessions', 'contact_id')) {
        $table->char('contact_id', 36)->nullable()->after('lead_id');
        $table->index('contact_id');
    }
});

// Add missing account_id to contacts
echo "Updating contacts table...\n";
Capsule::schema()->table('contacts', function (Blueprint $table) {
    if (!Capsule::schema()->hasColumn('contacts', 'account_id')) {
        $table->char('account_id', 36)->nullable()->after('assigned_user_id');
        $table->index('account_id');
    }
});

// Add missing contact_id to meetings
echo "Updating meetings table...\n";
Capsule::schema()->table('meetings', function (Blueprint $table) {
    if (!Capsule::schema()->hasColumn('meetings', 'contact_id')) {
        $table->char('contact_id', 36)->nullable()->after('parent_id');
        $table->index('contact_id');
    }
});

// Add missing contact_id to calls
echo "Updating calls table...\n";
Capsule::schema()->table('calls', function (Blueprint $table) {
    if (!Capsule::schema()->hasColumn('calls', 'contact_id')) {
        $table->char('contact_id', 36)->nullable()->after('parent_id');
        $table->index('contact_id');
    }
});

// Add missing contact_id to customer_health_scores
echo "Updating customer_health_scores table...\n";
Capsule::schema()->table('customer_health_scores', function (Blueprint $table) {
    if (!Capsule::schema()->hasColumn('customer_health_scores', 'contact_id')) {
        $table->char('contact_id', 36)->nullable()->after('account_id');
        $table->index('contact_id');
    }
});

// Add missing deleted column to knowledge_base_feedback
echo "Updating knowledge_base_feedback table...\n";
Capsule::schema()->table('knowledge_base_feedback', function (Blueprint $table) {
    if (!Capsule::schema()->hasColumn('knowledge_base_feedback', 'deleted')) {
        $table->boolean('deleted')->default(false)->after('date_entered');
    }
});

echo "\n✅ All missing foreign keys have been added!\n";