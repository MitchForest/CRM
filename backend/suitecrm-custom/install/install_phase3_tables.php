<?php
/**
 * Phase 3 - Install Custom Tables
 * Run this script to create all Phase 3 database tables
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

function installPhase3Tables() {
    global $db;
    
    echo "Installing Phase 3 Custom Tables...\n\n";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/sql/phase3_tables.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found at $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', 
            preg_split('/;\s*$/m', $sql)
        )
    );
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Process DELIMITER commands separately
    $delimiter = ';';
    $skipNext = false;
    
    foreach ($statements as $statement) {
        if ($skipNext) {
            $skipNext = false;
            continue;
        }
        
        // Handle DELIMITER changes
        if (preg_match('/^DELIMITER\s+(.+)$/i', $statement, $matches)) {
            $delimiter = trim($matches[1]);
            continue;
        }
        
        // Skip empty statements
        if (empty($statement)) {
            continue;
        }
        
        // Add delimiter back if it was removed
        if (!preg_match('/' . preg_quote($delimiter, '/') . '$/', $statement)) {
            $statement .= $delimiter;
        }
        
        // Handle special delimiter ($$)
        if ($delimiter !== ';') {
            // Collect statements until we find the delimiter
            $fullStatement = $statement;
            $skipNext = true; // Assuming the next statement is part of this one
            
            // Reset delimiter after trigger/procedure
            if (strpos($statement, 'END$$') !== false) {
                $delimiter = ';';
                $skipNext = false;
            }
        }
        
        try {
            // Extract table/view name for logging
            $objectName = 'Unknown';
            if (preg_match('/CREATE\s+(TABLE|VIEW|TRIGGER)\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                $objectName = $matches[2];
            }
            
            echo "Creating $objectName... ";
            
            $result = $db->query($statement);
            
            if ($result) {
                echo "✓ Success\n";
                $successCount++;
            } else {
                throw new Exception($db->lastError());
            }
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Check if it's a "already exists" error
            if (strpos($errorMsg, 'already exists') !== false) {
                echo "⚠ Already exists (skipped)\n";
                $successCount++;
            } else {
                echo "✗ Error: $errorMsg\n";
                $errors[] = "$objectName: $errorMsg";
                $errorCount++;
            }
        }
    }
    
    echo "\n" . str_repeat('-', 50) . "\n";
    echo "Installation Summary:\n";
    echo "✓ Successful: $successCount\n";
    echo "✗ Errors: $errorCount\n";
    
    if (!empty($errors)) {
        echo "\nError Details:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    // Verify critical tables exist
    echo "\nVerifying critical tables...\n";
    $criticalTables = [
        'form_builder_forms',
        'form_builder_submissions',
        'knowledge_base_articles',
        'activity_tracking_visitors',
        'ai_chat_conversations',
        'ai_lead_scoring_history'
    ];
    
    $allTablesExist = true;
    foreach ($criticalTables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $result = $db->query($query);
        
        if ($db->fetchByAssoc($result)) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' is missing!\n";
            $allTablesExist = false;
        }
    }
    
    if ($allTablesExist) {
        echo "\n✅ All critical tables installed successfully!\n";
        
        // Grant permissions
        echo "\nGranting permissions...\n";
        try {
            // Get current database name
            $dbName = $db->database;
            $dbUser = $GLOBALS['sugar_config']['dbconfig']['db_user_name'] ?? 'suitecrm';
            
            // Grant permissions (this may fail if user doesn't have GRANT privilege)
            $grantQuery = "GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'%'";
            $db->query($grantQuery);
            echo "✓ Permissions granted\n";
        } catch (Exception $e) {
            echo "⚠ Could not grant permissions (may already have them)\n";
        }
        
    } else {
        echo "\n❌ Some critical tables are missing. Please check the errors above.\n";
        return false;
    }
    
    return true;
}

// Add helper function to check if we're running from CLI
function isCliMode() {
    return php_sapi_name() === 'cli';
}

// Main execution
if (isCliMode()) {
    // Running from command line
    $result = installPhase3Tables();
    exit($result ? 0 : 1);
} else {
    // Running from web browser
    header('Content-Type: text/plain; charset=utf-8');
    installPhase3Tables();
}