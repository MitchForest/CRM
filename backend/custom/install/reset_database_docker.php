<?php
/**
 * Database Reset Script - Clears all data while keeping structure intact
 * WARNING: This will delete ALL data from the database!
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Load SuiteCRM bootstrap
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Use absolute path for Docker container
require_once('/var/www/html/include/entryPoint.php');

global $db;

echo "\n=== CRM Database Reset Script ===\n";
echo "WARNING: This will DELETE ALL DATA from the database!\n";
echo "Proceeding with reset...\n";

// Disable foreign key checks
$db->query("SET FOREIGN_KEY_CHECKS = 0");

// Get all tables in the database
$tablesResult = $db->query("SHOW TABLES");
$tables = [];
while ($row = $db->fetchByAssoc($tablesResult)) {
    $tables[] = array_values($row)[0];
}

echo "Found " . count($tables) . " tables to truncate.\n\n";

// Tables to preserve structure but clear data
$truncateTables = [];

// Tables to skip (system tables we want to keep)
$skipTables = [
    'config',
    'upgrade_history',
    'versions',
];

foreach ($tables as $table) {
    if (!in_array($table, $skipTables)) {
        $truncateTables[] = $table;
    }
}

// Truncate tables
$errors = [];
foreach ($truncateTables as $table) {
    try {
        echo "Truncating table: $table... ";
        $db->query("TRUNCATE TABLE `$table`");
        echo "✓\n";
    } catch (Exception $e) {
        echo "✗ (Error: " . $e->getMessage() . ")\n";
        $errors[] = $table;
    }
}

// Re-enable foreign key checks
$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n=== Reset Complete ===\n";
echo "Successfully truncated " . (count($truncateTables) - count($errors)) . " tables.\n";
if (count($errors) > 0) {
    echo "Failed to truncate " . count($errors) . " tables: " . implode(', ', $errors) . "\n";
}
echo "\nDatabase has been reset. You can now run the seeding scripts.\n";