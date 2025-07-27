<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

$clientId = 'suitecrm_client';
$clientSecretPlain = 'secret';
$clientSecretHashed = hash('sha256', $clientSecretPlain);

// Delete existing client if any
$db->query("DELETE FROM oauth2clients WHERE id = " . $db->quote($clientId));

// Create new client
$query = "INSERT INTO oauth2clients (id, name, secret, allowed_grant_type, is_confidential) VALUES (" .
    $db->quote($clientId) . ", " .
    $db->quote('SuiteCRM Client') . ", " .
    $db->quote($clientSecretHashed) . ", " .
    $db->quote('password') . ", " .
    "1)";

$db->query($query);

echo "Created OAuth2 client:\n";
echo "  ID: $clientId\n";
echo "  Secret (plain): $clientSecretPlain\n";
echo "  Secret (hashed): $clientSecretHashed\n";
echo "  Grant type: password\n";

// Verify
$result = $db->query("SELECT * FROM oauth2clients WHERE id = " . $db->quote($clientId));
$client = $db->fetchByAssoc($result);
echo "\nVerification:\n";
print_r($client);