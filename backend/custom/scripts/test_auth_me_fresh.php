<?php
// Test auth/me with completely fresh token
echo "1. Getting fresh token...\n";
$ch = curl_init('http://localhost/custom/api/index.php/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'john.doe@example.com',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login response code: $httpCode\n";
$data = json_decode($response, true);

if (!isset($data['data']['accessToken'])) {
    echo "Failed to get token:\n";
    print_r($data);
    exit;
}

$token = $data['data']['accessToken'];
echo "Token: " . substr($token, 0, 50) . "...\n\n";

// Wait a moment
sleep(1);

echo "2. Testing /auth/me...\n";
$ch = curl_init('http://localhost/custom/api/index.php/auth/me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Auth/me response code: $httpCode\n";
echo "Response:\n";
$decoded = json_decode($response, true);
print_r($decoded);

// Decode token to verify it's valid
echo "\n3. Verifying token contents...\n";
require_once('/var/www/html/custom/api/Auth/JWT.php');
try {
    $payload = \Api\Auth\JWT::decode($token);
    echo "Token is valid, user_id: " . $payload['user_id'] . "\n";
    echo "Expires at: " . date('Y-m-d H:i:s', $payload['exp']) . "\n";
} catch (Exception $e) {
    echo "Token decode error: " . $e->getMessage() . "\n";
}