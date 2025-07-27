<?php
// Test OAuth2 token generation
if (!defined('sugarEntry')) define('sugarEntry', true);
chdir('/var/www/html');

// Use minimal initialization to avoid issues
require_once 'vendor/autoload.php';
require_once 'config.php';

// Test OAuth2 token endpoint directly
$ch = curl_init('http://localhost/Api/access_token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'password',
    'client_id' => 'suitecrm_client',
    'client_secret' => 'secret',
    'username' => 'admin',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "OAuth2 Token Request:\n";
echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "\n";

if ($httpCode === 200) {
    $token = json_decode($response, true);
    if (isset($token['access_token'])) {
        echo "\nSuccess! Access token obtained.\n";
        echo "Token: " . substr($token['access_token'], 0, 50) . "...\n";
        
        // Test API call with token
        echo "\nTesting API call to AOK_KnowledgeBase:\n";
        $ch = curl_init('http://localhost/Api/V8/module/AOK_KnowledgeBase');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.api+json',
            'Authorization: Bearer ' . $token['access_token']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $apiResponse = curl_exec($ch);
        $apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "API HTTP Code: $apiHttpCode\n";
        if ($apiHttpCode === 200) {
            $data = json_decode($apiResponse, true);
            echo "Knowledge Base articles found: " . count($data['data'] ?? []) . "\n";
        } else {
            echo "API Response: " . substr($apiResponse, 0, 200) . "\n";
        }
    }
}