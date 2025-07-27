<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

$clientId = 'suitecrm_client';
$clientSecretPlain = 'secret';
$clientSecretHashed = hash('sha256', $clientSecretPlain);

// Delete existing
$db->query("DELETE FROM oauth2clients WHERE id = " . $db->quote($clientId));

// Create with hashed secret
require_once 'modules/OAuth2Clients/OAuth2Clients.php';
$client = new OAuth2Clients();
$client->id = $clientId;
$client->new_with_id = true;
$client->name = 'SuiteCRM Client';
$client->secret = $clientSecretHashed; // Set the hash directly
$client->is_confidential = 1;
$client->allowed_grant_type = 'password';
$client->save();

echo "Created OAuth2 client:\n";
echo "ID: $clientId\n";
echo "Plain secret: $clientSecretPlain\n";
echo "SHA256 hash: $clientSecretHashed\n";

// Test authentication
echo "\nTesting OAuth2 authentication...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/Api/access_token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'password',
    'client_id' => $clientId,
    'client_secret' => $clientSecretPlain,
    'username' => 'admin',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "SUCCESS! Access token received\n";
    echo "Token type: " . ($data['token_type'] ?? 'unknown') . "\n";
    echo "Expires in: " . ($data['expires_in'] ?? 'unknown') . " seconds\n";
} else {
    echo "FAILED!\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}