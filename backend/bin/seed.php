#!/usr/bin/env php
<?php

/**
 * Database Seeder Command
 * 
 * Usage: php bin/seed.php [options]
 * 
 * Options:
 *   --fresh     Drop all tables and recreate before seeding
 *   --class     Run a specific seeder class (e.g., --class=UserSeeder)
 *   --help      Show this help message
 */

// Bootstrap the application
require_once dirname(__DIR__) . '/bootstrap/app.php';

use Illuminate\Database\Capsule\Manager as DB;

// Include seeder files
$seederDir = dirname(__DIR__) . '/database/seeders';
require_once $seederDir . '/BaseSeeder.php';
require_once $seederDir . '/UserSeeder.php';
require_once $seederDir . '/KnowledgeBaseSeeder.php';
require_once $seederDir . '/FormSeeder.php';
require_once $seederDir . '/LeadSeeder.php';
require_once $seederDir . '/ContactSeeder.php';
require_once $seederDir . '/OpportunitySeeder.php';
require_once $seederDir . '/ActivitySeeder.php';
require_once $seederDir . '/ActivityTrackingSeeder.php';
require_once $seederDir . '/CaseSeeder.php';
require_once $seederDir . '/AISeeder.php';
require_once $seederDir . '/DatabaseSeeder.php';

// Parse command line arguments
$options = getopt('', ['fresh', 'class:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Database Seeder Command

Usage: php bin/seed.php [options]

Options:
  --fresh     Drop all tables and recreate before seeding
  --class     Run a specific seeder class (e.g., --class=UserSeeder)
  --help      Show this help message

Examples:
  php bin/seed.php                    # Run all seeders
  php bin/seed.php --fresh           # Drop tables and reseed
  php bin/seed.php --class=UserSeeder # Run only UserSeeder

Available Seeders:
  - UserSeeder          Users and roles
  - KnowledgeBaseSeeder Knowledge base articles  
  - FormSeeder          Forms and submissions
  - LeadSeeder          Leads with various statuses
  - ContactSeeder       Contacts and accounts
  - OpportunitySeeder   Sales opportunities
  - ActivitySeeder      Activities (calls, meetings, etc)
  - ActivityTrackingSeeder Website visitor tracking
  - CaseSeeder          Support cases
  - AISeeder            AI lead scores and chat

HELP;
    exit(0);
}

// Check if we should drop tables first
if (isset($options['fresh'])) {
    echo "⚠️  WARNING: This will drop all tables and data!\n";
    echo "Are you sure you want to continue? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'yes') {
        echo "Operation cancelled.\n";
        exit(0);
    }
    
    echo "\nDropping all tables...\n";
    
    // Get all tables
    $tables = DB::select("SHOW TABLES");
    $dbName = $_ENV['DB_DATABASE'] ?? 'suitecrm';
    
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    
    foreach ($tables as $table) {
        $tableName = $table->{"Tables_in_{$dbName}"};
        echo "  Dropping table: {$tableName}\n";
        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
    }
    
    // Re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "\n✓ All tables dropped\n\n";
    
    // Run migrations to recreate tables
    echo "Running migrations to recreate tables...\n";
    exec('php bin/migrate.php', $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "✗ Migration failed!\n";
        echo implode("\n", $output);
        exit(1);
    }
    
    echo "✓ Tables recreated\n\n";
}

// Run specific seeder or all seeders
if (isset($options['class'])) {
    $seederClass = $options['class'];
    $className = "Database\\Seeders\\{$seederClass}";
    
    if (!class_exists($className)) {
        echo "✗ Error: Seeder class '{$seederClass}' not found\n";
        echo "Use --help to see available seeders\n";
        exit(1);
    }
    
    echo "Running {$seederClass}...\n";
    
    try {
        $seeder = new $className();
        $seeder->run();
        echo "\n✓ {$seederClass} completed successfully!\n";
    } catch (\Exception $e) {
        echo "\n✗ Error running {$seederClass}: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Run all seeders
    $seeder = new Database\Seeders\DatabaseSeeder();
    $seeder->run();
}

echo "\n";