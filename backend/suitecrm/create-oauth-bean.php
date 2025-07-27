<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

// Delete existing client
$db->query("DELETE FROM oauth2clients WHERE id = 'suitecrm_client'");

// Create using bean
require_once 'modules/OAuth2Clients/OAuth2Clients.php';
$client = new OAuth2Clients();
$client->id = 'suitecrm_client';
$client->new_with_id = true;
$client->name = 'SuiteCRM Client';
$client->secret = 'secret'; // Bean might hash this automatically
$client->is_confidential = 1;
$client->allowed_grant_type = 'password';
$client->save();

echo "Created OAuth2 client using bean\n";

// Verify
$result = $db->query("SELECT * FROM oauth2clients WHERE id = 'suitecrm_client'");
$row = $db->fetchByAssoc($result);

echo "\nStored in database:\n";
echo "ID: " . $row['id'] . "\n";
echo "Name: " . $row['name'] . "\n";
echo "Secret: " . $row['secret'] . "\n";
echo "Secret length: " . strlen($row['secret']) . "\n";

// Check if it's hashed
$expectedHash = hash('sha256', 'secret');
echo "\nExpected SHA256 hash: $expectedHash\n";
echo "Secret matches hash: " . ($row['secret'] === $expectedHash ? 'YES' : 'NO') . "\n";