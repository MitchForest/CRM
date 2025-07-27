#!/usr/bin/env php
<?php

/**
 * Check seeding status - shows current data counts
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use Illuminate\Database\Capsule\Manager as DB;

echo "\n=== Database Seeding Status ===\n\n";

$tables = [
    'users' => 'Users (should have 10+ after seeding)',
    'knowledge_base_articles' => 'Knowledge Base Articles (12)',
    'leads' => 'Leads (500)',
    'contacts' => 'Contacts (~375)',
    'accounts' => 'Accounts (125)',
    'opportunities' => 'Opportunities (200)',
    'cases' => 'Support Cases (150)',
    'form_builder_forms' => 'Forms (5)',
    'form_builder_submissions' => 'Form Submissions (475+)',
    'calls' => 'Calls',
    'meetings' => 'Meetings',
    'notes' => 'Notes',
    'tasks' => 'Tasks',
    'activity_tracking_visitors' => 'Visitors',
    'activity_tracking_sessions' => 'Sessions',
    'activity_tracking_page_views' => 'Page Views',
    'ai_chat_conversations' => 'Chat Conversations',
    'ai_chat_messages' => 'Chat Messages',
    'ai_lead_scoring_history' => 'Lead Scores',
];

$totalExpected = 0;
$totalActual = 0;

foreach ($tables as $table => $description) {
    try {
        $count = DB::table($table)->count();
        $totalActual += $count;
        
        // Extract expected count from description if present
        preg_match('/\((\d+)\+?\)/', $description, $matches);
        $expected = isset($matches[1]) ? $matches[1] : '';
        
        if ($expected) {
            $totalExpected += intval($expected);
            $status = $count >= intval($expected) ? '✓' : '⚠';
            printf("%-30s: %6d %s (expected: %s)\n", 
                substr($description, 0, strpos($description, '(')), 
                $count, 
                $status,
                $expected . (strpos($description, '+') !== false ? '+' : '')
            );
        } else {
            printf("%-30s: %6d\n", $description, $count);
        }
    } catch (\Exception $e) {
        printf("%-30s: ERROR - %s\n", $description, $e->getMessage());
    }
}

echo "\n" . str_repeat('─', 50) . "\n";
echo "Total records: " . number_format($totalActual) . "\n";

// Check for test users
echo "\n=== Test Users Status ===\n";
try {
    $testUsers = DB::table('users')
        ->where('email1', 'LIKE', '%techflow.com%')
        ->select('user_name', 'email1', 'title', 'department')
        ->get();
    
    if ($testUsers->count() > 0) {
        echo "Found " . $testUsers->count() . " test users:\n";
        foreach ($testUsers as $user) {
            echo "  • {$user->user_name} ({$user->email1}) - {$user->title}\n";
        }
    } else {
        echo "⚠ No test users found. Run UserSeeder first.\n";
    }
} catch (\Exception $e) {
    echo "ERROR checking users: " . $e->getMessage() . "\n";
}

echo "\n=== Recommended Next Steps ===\n";

if ($totalActual == 0) {
    echo "1. Start by running: docker-compose exec backend php bin/seed.php --class=UserSeeder\n";
    echo "2. Then run: docker-compose exec backend php bin/seed.php --class=KnowledgeBaseSeeder\n";
    echo "3. Continue with other seeders as documented in seeding-tracker.md\n";
} elseif (DB::table('leads')->count() == 0) {
    echo "1. Users and KB articles are seeded ✓\n";
    echo "2. Next run: docker-compose exec backend php bin/seed.php --class=LeadSeeder\n";
} elseif (DB::table('contacts')->count() == 0) {
    echo "1. Leads are seeded ✓\n";
    echo "2. Next run: docker-compose exec backend php bin/seed.php --class=ContactSeeder\n";
} else {
    echo "1. Check the table counts above\n";
    echo "2. Run missing seeders individually\n";
    echo "3. See .docs/phase-7/seeding-tracker.md for details\n";
}

echo "\n";