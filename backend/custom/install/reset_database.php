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

chdir(dirname(__FILE__) . '/../../..');
require_once('include/entryPoint.php');

global $db;

echo "\n=== CRM Database Reset Script ===\n";
echo "WARNING: This will DELETE ALL DATA from the database!\n";
echo "Are you sure you want to continue? (yes/no): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$response = trim($line);
fclose($handle);

if ($response !== 'yes') {
    echo "Operation cancelled.\n";
    exit(0);
}

echo "\nStarting database reset...\n";

try {
    // Disable foreign key checks
    $db->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Core tables to clear (preserving structure)
    $tablesToClear = [
        // Leads & Opportunities
        'leads',
        'leads_audit',
        'leads_cstm',
        'opportunities',
        'opportunities_audit',
        'opportunities_cstm',
        'opportunities_contacts',
        
        // Accounts & Contacts
        'accounts',
        'accounts_audit',
        'accounts_cstm',
        'accounts_contacts',
        'accounts_opportunities',
        'contacts',
        'contacts_audit',
        'contacts_cstm',
        'contacts_cases',
        
        // Cases (Support Tickets)
        'cases',
        'cases_audit',
        'cases_cstm',
        
        // Activities
        'calls',
        'calls_contacts',
        'calls_leads',
        'calls_users',
        'meetings',
        'meetings_contacts',
        'meetings_leads',
        'meetings_users',
        'tasks',
        'notes',
        'emails',
        'emails_beans',
        'emails_text',
        'emails_email_addr_rel',
        
        // Email Addresses
        'email_addresses',
        'email_addr_bean_rel',
        
        // Custom Tables (Phase 3)
        'form_builder_forms',
        'form_builder_submissions',
        'knowledge_base_articles',
        'knowledge_base_feedback',
        'activity_tracking_visitors',
        'activity_tracking_sessions',
        'activity_tracking_pageviews',
        'activity_tracking_events',
        'ai_chat_conversations',
        'ai_chat_messages',
        'customer_health_scores',
        'ai_lead_scores',
        
        // Relationships
        'relationships',
        'linked_documents',
        
        // Other data tables
        'job_queue',
        'schedulers_times',
        'tracker',
        'user_preferences',
        'oauth_tokens',
        'oauth_nonce',
        'api_refresh_tokens'
    ];
    
    foreach ($tablesToClear as $table) {
        // Check if table exists
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($db->fetchByAssoc($result)) {
            echo "Clearing table: $table...";
            
            // Truncate table (faster than DELETE and resets auto-increment)
            $db->query("TRUNCATE TABLE `$table`");
            
            echo " Done\n";
        } else {
            echo "Table $table does not exist, skipping...\n";
        }
    }
    
    // Special handling for users table - keep admin user
    echo "Resetting users (keeping admin)...";
    $db->query("DELETE FROM users WHERE user_name != 'admin'");
    echo " Done\n";
    
    // Clear ACL tables but keep default roles
    echo "Clearing ACL data...";
    $db->query("DELETE FROM acl_actions WHERE id NOT IN (SELECT action_id FROM acl_roles_actions WHERE deleted = 0)");
    $db->query("DELETE FROM acl_roles_users WHERE deleted = 0");
    echo " Done\n";
    
    // Clear security groups data
    echo "Clearing security groups data...";
    $db->query("TRUNCATE TABLE securitygroups_records");
    $db->query("TRUNCATE TABLE securitygroups_users");
    $db->query("DELETE FROM securitygroups WHERE id != ''"); // Keep structure
    echo " Done\n";
    
    // Re-enable foreign key checks
    $db->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Clear cache
    echo "\nClearing cache directories...";
    $cacheDirectories = [
        'cache/smarty/templates_c',
        'cache/smarty/cache',
        'cache/modules',
        'cache/themes',
        'cache/javascript',
        'cache/include'
    ];
    
    foreach ($cacheDirectories as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    echo " Done\n";
    
    echo "\n=== Database reset complete! ===\n";
    echo "All data has been cleared except:\n";
    echo "- Admin user account\n";
    echo "- Table structures\n";
    echo "- System configuration\n";
    echo "\nYou can now run the seed script to populate with sample data.\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}