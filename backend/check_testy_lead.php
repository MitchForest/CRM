<?php
// Check if "testy testy" lead exists in database

// Get the path to SuiteCRM's config
require_once 'suitecrm/config.php';

// Create database connection
$dbconfig = $sugar_config['dbconfig'];
$dsn = "mysql:host={$dbconfig['db_host_name']};port={$dbconfig['db_port']};dbname={$dbconfig['db_name']}";

try {
    $pdo = new PDO($dsn, $dbconfig['db_user_name'], $dbconfig['db_password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n\n";
    
    // Query for leads with "testy" in name
    $query = "SELECT id, first_name, last_name, date_entered, date_modified, assigned_user_id 
              FROM leads 
              WHERE last_name LIKE '%testy%' OR first_name LIKE '%testy%' 
              ORDER BY date_entered DESC 
              LIMIT 10";
    
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        echo "Found " . count($results) . " lead(s) with 'testy' in the name:\n\n";
        foreach ($results as $lead) {
            echo "ID: {$lead['id']}\n";
            echo "Name: {$lead['first_name']} {$lead['last_name']}\n";
            echo "Date Created: {$lead['date_entered']}\n";
            echo "Date Modified: {$lead['date_modified']}\n";
            echo "Assigned User ID: {$lead['assigned_user_id']}\n";
            echo "---------------------------------\n";
        }
    } else {
        echo "No leads found with 'testy' in the name.\n";
    }
    
    // Also check for any recently created leads
    echo "\n\nChecking for recently created leads (last 24 hours):\n";
    $query2 = "SELECT id, first_name, last_name, date_entered 
               FROM leads 
               WHERE date_entered >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               ORDER BY date_entered DESC 
               LIMIT 5";
    
    $stmt2 = $pdo->query($query2);
    $recent = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recent) > 0) {
        echo "\nRecent leads:\n";
        foreach ($recent as $lead) {
            echo "- {$lead['first_name']} {$lead['last_name']} (Created: {$lead['date_entered']})\n";
        }
    } else {
        echo "No leads created in the last 24 hours.\n";
    }
    
} catch (PDOException $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}