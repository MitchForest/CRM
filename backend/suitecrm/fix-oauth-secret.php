<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

$clientId = 'suitecrm_client';
$clientSecretPlain = 'secret';
$clientSecretHashed = hash('sha256', $clientSecretPlain);

echo "Updating OAuth2 client secret...\n";
echo "Plain secret: $clientSecretPlain\n";
echo "SHA256 hash: $clientSecretHashed\n";

// Update the secret to be the hash
$query = "UPDATE oauth2clients SET secret = " . $db->quote($clientSecretHashed) . 
         " WHERE id = " . $db->quote($clientId);
$db->query($query);

// Verify
$result = $db->query("SELECT * FROM oauth2clients WHERE id = " . $db->quote($clientId));
$client = $db->fetchByAssoc($result);

echo "\nVerification:\n";
echo "ID: " . $client['id'] . "\n";
echo "Secret in DB: " . $client['secret'] . "\n";
echo "Secret matches hash: " . ($client['secret'] === $clientSecretHashed ? 'YES' : 'NO') . "\n";