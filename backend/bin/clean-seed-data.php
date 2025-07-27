#!/usr/bin/env php
<?php

/**
 * Clean seed data from database
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use Illuminate\Database\Capsule\Manager as DB;

echo "Cleaning seed data...\n";

try {
    // Clean in reverse order of dependencies
    
    // AI data
    DB::table('ai_chat_messages')->delete();
    DB::table('ai_chat_conversations')->delete();
    DB::table('ai_lead_scoring_history')->delete();
    echo "✓ Cleaned AI data\n";
    
    // Activity tracking
    DB::table('activity_tracking_page_views')->delete();
    DB::table('activity_tracking_sessions')->delete();
    DB::table('activity_tracking_visitors')->delete();
    echo "✓ Cleaned activity tracking data\n";
    
    // Support cases
    DB::table('contacts_cases')->delete();
    DB::table('cases')->delete();
    echo "✓ Cleaned support cases\n";
    
    // Activities
    DB::table('calls')->delete();
    DB::table('meetings')->delete();
    DB::table('notes')->delete();
    DB::table('tasks')->delete();
    echo "✓ Cleaned activities\n";
    
    // Opportunities
    DB::table('opportunities_contacts')->delete();
    DB::table('opportunities')->delete();
    echo "✓ Cleaned opportunities\n";
    
    // Accounts and contacts
    DB::table('accounts_contacts')->delete();
    DB::table('contacts')->delete();
    DB::table('accounts')->delete();
    echo "✓ Cleaned accounts and contacts\n";
    
    // Leads
    DB::table('leads')->delete();
    echo "✓ Cleaned leads\n";
    
    // Forms
    DB::table('form_builder_submissions')->delete();
    DB::table('form_builder_forms')->delete();
    echo "✓ Cleaned forms\n";
    
    // Knowledge base
    DB::table('knowledge_base_feedback')->delete();
    DB::table('knowledge_base_articles')->delete();
    echo "✓ Cleaned knowledge base\n";
    
    // Users (only test users)
    DB::table('users')->where('email1', 'LIKE', '%techflow.com')->delete();
    echo "✓ Cleaned test users\n";
    
    echo "\n✓ All seed data cleaned successfully!\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}