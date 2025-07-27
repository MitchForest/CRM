<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

$clientId = 'suitecrm_client';
$clientSecretPlain = 'secret';
$clientSecretHashed = hash('sha256', $clientSecretPlain);

echo "Creating OAuth2 client with proper SQL...\n";

// Delete existing
$query = "DELETE FROM oauth2clients WHERE id = '$clientId'";
$db->query($query);

// Insert with proper quoting
$query = "INSERT INTO oauth2clients (id, name, secret, allowed_grant_type, is_confidential, deleted) VALUES (" .
    "'$clientId', " .
    "'SuiteCRM Client', " .
    "'$clientSecretHashed', " .
    "'password', " .
    "1, " .
    "0)";

echo "Query: $query\n";
$db->query($query);

// Verify
$query = "SELECT * FROM oauth2clients WHERE id = '$clientId'";
$result = $db->query($query);
$client = $db->fetchByAssoc($result);

if ($client) {
    echo "\nClient created successfully!\n";
    echo "ID: " . $client['id'] . "\n";
    echo "Name: " . $client['name'] . "\n";
    echo "Secret (first 20 chars): " . substr($client['secret'], 0, 20) . "...\n";
    echo "Grant type: " . $client['allowed_grant_type'] . "\n";
    
    // Now test authentication
    echo "\n\nTesting authentication...\n";
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
        echo "Access token (first 20 chars): " . substr($data['access_token'] ?? '', 0, 20) . "...\n";
    } else {
        echo "Failed to authenticate\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }
} else {
    echo "Failed to create client!\n";
}