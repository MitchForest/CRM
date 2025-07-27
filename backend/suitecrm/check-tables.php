<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

echo "=== Checking Database Tables ===\n\n";

// Get all tables
$result = $db->query("SHOW TABLES");
$tables = [];
while ($row = $db->fetchByAssoc($result)) {
    $tables[] = array_values($row)[0];
}

echo "Total tables: " . count($tables) . "\n\n";

// Tables we definitely need for our custom CRM
$needed_tables = [
    // Core CRM
    'users',
    'leads', 
    'contacts',
    'accounts',
    'opportunities',
    'cases',
    'notes',
    'calls',
    'meetings',
    'tasks',
    'emails',
    'email_addresses',
    'email_addr_bean_rel',
    
    // Knowledge Base
    'aok_knowledgebase',
    'aok_knowledge_base_categories',
    
    // OAuth/Auth
    'oauth2clients',
    'oauth2tokens',
    
    // Config/System
    'config',
    'user_preferences',
    'acl_actions',
    'acl_roles',
    'acl_roles_actions',
    'acl_roles_users',
    
    // Relationships
    'accounts_contacts',
    'accounts_opportunities', 
    'opportunities_contacts',
    'contacts_cases',
    'leads_notes',
    'accounts_cases',
    
    // Custom tables for our features
    'form_builder_forms',
    'form_builder_submissions',
    'ai_chat_conversations',
    'ai_chat_messages',
    'activity_tracking',
    'ai_lead_scores',
    'customer_health_scores'
];

// Check which needed tables exist
echo "Checking needed tables:\n";
foreach ($needed_tables as $table) {
    $exists = in_array($table, $tables) ? "✓" : "✗";
    echo "$exists $table\n";
}

// Find potentially unnecessary tables
$unnecessary = array_diff($tables, $needed_tables);
echo "\n\nPotentially unnecessary tables (" . count($unnecessary) . "):\n";
$i = 0;
foreach ($unnecessary as $table) {
    echo $table . "\n";
    $i++;
    if ($i > 20) {
        echo "... and " . (count($unnecessary) - 20) . " more\n";
        break;
    }
}

echo "\n=== Summary ===\n";
echo "Total tables: " . count($tables) . "\n";
echo "Needed tables: " . count($needed_tables) . "\n";
echo "Potentially unnecessary: " . count($unnecessary) . "\n";