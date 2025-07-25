<?php
/**
 * Setup test authentication for Phase 3 testing
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');

global $db, $current_user;

echo "Setting up test authentication...\n";

// 1. Get admin user
$admin = BeanFactory::getBean('Users', '1');
if (!$admin) {
    echo "Error: Admin user not found\n";
    exit(1);
}

echo "Admin user found: " . $admin->user_name . "\n";

// 2. Create a simple API token for testing
$token = md5(uniqid());
$expires = date('Y-m-d H:i:s', strtotime('+1 day'));

// Store token in session or custom table
$query = "INSERT INTO oauth2tokens (id, access_token, client, user_id, expires) 
          VALUES (UUID(), '$token', 'suitecrm_client', '1', '$expires')
          ON DUPLICATE KEY UPDATE access_token = '$token', expires = '$expires'";

$db->query($query);

echo "\nTest token created successfully!\n";
echo "Token: $token\n";
echo "Expires: $expires\n";
echo "\nUse this token in Authorization header: Bearer $token\n";

// 3. Create test leads if they don't exist
echo "\nCreating test leads...\n";

$testLeads = [
    ['first_name' => 'Test', 'last_name' => 'Lead1', 'email1' => 'test1@example.com'],
    ['first_name' => 'Test', 'last_name' => 'Lead2', 'email1' => 'test2@example.com'],
    ['first_name' => 'Test', 'last_name' => 'Lead3', 'email1' => 'test3@example.com'],
];

foreach ($testLeads as $leadData) {
    // Check if lead exists
    $query = "SELECT id FROM leads WHERE email1 = '{$leadData['email1']}' AND deleted = 0 LIMIT 1";
    $result = $db->query($query);
    $existing = $db->fetchByAssoc($result);
    
    if (!$existing) {
        $lead = BeanFactory::newBean('Leads');
        $lead->first_name = $leadData['first_name'];
        $lead->last_name = $leadData['last_name'];
        $lead->email1 = $leadData['email1'];
        $lead->status = 'New';
        $lead->assigned_user_id = '1';
        $lead->save();
        echo "Created lead: {$lead->first_name} {$lead->last_name} (ID: {$lead->id})\n";
    } else {
        echo "Lead already exists: {$leadData['email1']} (ID: {$existing['id']})\n";
    }
}

// 4. Create test accounts
echo "\nCreating test accounts...\n";

$testAccounts = [
    ['name' => 'Test Company 1', 'industry' => 'Technology'],
    ['name' => 'Test Company 2', 'industry' => 'Finance'],
    ['name' => 'Test Company 3', 'industry' => 'Healthcare'],
];

foreach ($testAccounts as $accountData) {
    // Check if account exists
    $query = "SELECT id FROM accounts WHERE name = '{$accountData['name']}' AND deleted = 0 LIMIT 1";
    $result = $db->query($query);
    $existing = $db->fetchByAssoc($result);
    
    if (!$existing) {
        $account = BeanFactory::newBean('Accounts');
        $account->name = $accountData['name'];
        $account->industry = $accountData['industry'];
        $account->assigned_user_id = '1';
        $account->save();
        echo "Created account: {$account->name} (ID: {$account->id})\n";
    } else {
        echo "Account already exists: {$accountData['name']} (ID: {$existing['id']})\n";
    }
}

// 5. Get IDs for testing
echo "\nTest Data IDs:\n";
$query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM leads WHERE deleted = 0 LIMIT 3";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "Lead: {$row['name']} = {$row['id']}\n";
}

$query = "SELECT id, name FROM accounts WHERE deleted = 0 LIMIT 3";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "Account: {$row['name']} = {$row['id']}\n";
}

echo "\nSetup complete!\n";