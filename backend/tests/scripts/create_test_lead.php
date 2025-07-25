<?php
/**
 * Create a test lead for Phase 3 verification
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once '/var/www/html/include/entryPoint.php';
require_once '/var/www/html/modules/Leads/Lead.php';

global $db;

// Create test lead with specific ID
$lead = new Lead();
$lead->id = '4e901400-1da1-a6ca-3a7a-68838e181845';
$lead->new_with_id = true;

// Check if lead already exists
$existing = "SELECT id FROM leads WHERE id = '{$lead->id}' AND deleted = 0";
$result = $db->query($existing);
if ($db->fetchByAssoc($result)) {
    echo "Test lead already exists.\n";
    exit(0);
}

// Set lead data
$lead->first_name = 'Test';
$lead->last_name = 'Lead';
$lead->email1 = 'test.lead@example.com';
$lead->account_name = 'Test Company Inc';
$lead->title = 'VP of Engineering';
$lead->phone_work = '555-1234';
$lead->lead_source = 'Web Site';
$lead->status = 'New';
$lead->description = 'Test lead for Phase 3 AI scoring verification';

// Additional fields for AI scoring
$lead->website = 'https://testcompany.com';
$lead->employees_c = '100-500';
$lead->annual_revenue = '10000000';
$lead->industry = 'Technology';

// Save
$lead->save();

echo "Test lead created successfully: {$lead->id}\n";