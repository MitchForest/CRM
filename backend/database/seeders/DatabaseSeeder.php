<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

// Include all seeder files
require_once __DIR__ . '/BaseSeeder.php';
require_once __DIR__ . '/UserSeeder.php';
require_once __DIR__ . '/KnowledgeBaseSeeder.php';
require_once __DIR__ . '/FormSeeder.php';
require_once __DIR__ . '/LeadSeeder.php';
require_once __DIR__ . '/ContactSeeder.php';
require_once __DIR__ . '/OpportunitySeeder.php';
require_once __DIR__ . '/ActivitySeeder.php';
require_once __DIR__ . '/ActivityTrackingSeeder.php';
require_once __DIR__ . '/CaseSeeder.php';
require_once __DIR__ . '/AISeeder.php';

class DatabaseSeeder
{
    private $seeders = [
        'UserSeeder' => 'Users and roles',
        'KnowledgeBaseSeeder' => 'Knowledge base articles',
        'LeadSeeder' => 'Leads with various statuses',
        'ContactSeeder' => 'Contacts and accounts',
        'OpportunitySeeder' => 'Sales opportunities',
        'FormSeeder' => 'Forms and form submissions',
        'ActivitySeeder' => 'Activities (calls, meetings, notes, tasks)',
        'ActivityTrackingSeeder' => 'Website visitor tracking',
        'CaseSeeder' => 'Support cases',
        'AISeeder' => 'AI lead scores and chat conversations',
    ];
    
    public function run(): void
    {
        echo "\n=== Starting Database Seeding ===\n";
        echo "This will populate your database with realistic demo data.\n\n";
        
        $startTime = microtime(true);
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            echo "✓ Database connection successful\n\n";
        } catch (\Exception $e) {
            echo "✗ Database connection failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        
        // Clean up temporary files
        $this->cleanupTempFiles();
        
        // Run seeders in order
        foreach ($this->seeders as $seederClass => $description) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Running {$seederClass} - {$description}\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            $seederStart = microtime(true);
            
            try {
                $className = "Database\\Seeders\\{$seederClass}";
                $seeder = new $className();
                $seeder->run();
                
                $duration = round(microtime(true) - $seederStart, 2);
                echo "✓ Completed in {$duration} seconds\n\n";
            } catch (\Exception $e) {
                echo "✗ Error: " . $e->getMessage() . "\n";
                echo "  File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
                
                // Ask if user wants to continue
                echo "\nDo you want to continue with the remaining seeders? (y/n): ";
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
                if (trim($line) !== 'y') {
                    echo "Seeding aborted.\n";
                    exit(1);
                }
                fclose($handle);
            }
        }
        
        // Clean up temporary files
        $this->cleanupTempFiles();
        
        // Display summary
        $this->displaySummary();
        
        $totalTime = round(microtime(true) - $startTime, 2);
        echo "\n✓ Database seeding completed in {$totalTime} seconds!\n\n";
        
        echo "You can now log in with these credentials:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Admin: john.smith@techflow.com / password123\n";
        echo "SDR: sarah.chen@techflow.com / password123\n";
        echo "AE: david.park@techflow.com / password123\n";
        echo "CSM: alex.thompson@techflow.com / password123\n";
        echo "Support: kevin.liu@techflow.com / password123\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
    
    private function cleanupTempFiles(): void
    {
        $files = [
            'user_ids.json',
            'lead_ids.json',
            'contact_ids.json',
            'account_ids.json',
            'opportunity_ids.json',
        ];
        
        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
    
    private function displaySummary(): void
    {
        echo "\n=== Seeding Summary ===\n";
        
        $tables = [
            'users' => 'Users',
            'leads' => 'Leads',
            'contacts' => 'Contacts',
            'accounts' => 'Accounts',
            'opportunities' => 'Opportunities',
            'cases' => 'Support Cases',
            'calls' => 'Calls',
            'meetings' => 'Meetings',
            'notes' => 'Notes',
            'tasks' => 'Tasks',
            'knowledge_base_articles' => 'KB Articles',
            'form_builder_forms' => 'Forms',
            'form_builder_submissions' => 'Form Submissions',
            'activity_tracking_visitors' => 'Visitors',
            'activity_tracking_sessions' => 'Sessions',
            'ai_chat_conversations' => 'Chat Conversations',
            'ai_lead_scoring_history' => 'Lead Scores',
        ];
        
        foreach ($tables as $table => $label) {
            try {
                $count = DB::table($table)->count();
                echo sprintf("%-25s: %6d records\n", $label, $count);
            } catch (\Exception $e) {
                // Table might not exist, skip it
            }
        }
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    // Bootstrap the application
    require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
    
    $seeder = new DatabaseSeeder();
    $seeder->run();
}