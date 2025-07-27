<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

// Check OAuth2 client
$query = "SELECT * FROM oauth2_clients WHERE id = 'suitecrm_client'";
$result = $db->query($query);
$client = $db->fetchByAssoc($result);

echo "OAuth2 Client:\n";
print_r($client);

// Check if secret matches
$plainSecret = 'secret';
$hashedSecret = hash('sha256', $plainSecret);
echo "\nExpected hash: " . $hashedSecret . "\n";
echo "Stored hash: " . ($client['secret'] ?? 'NONE') . "\n";
echo "Match: " . ($client['secret'] === $hashedSecret ? 'YES' : 'NO') . "\n";