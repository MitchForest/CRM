<?php
// Test leads API endpoint
echo "1. Getting token...\n";
$ch = curl_init('http://localhost/custom/api/index.php/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'john.doe@example.com',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['data']['accessToken'];
curl_close($ch);

echo "2. Testing GET /leads...\n";
$ch = curl_init('http://localhost/custom/api/index.php/leads');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response code: $httpCode\n";
echo "Raw response: " . substr($response, 0, 200) . "...\n\n";

$decoded = json_decode($response, true);
echo "Decoded response structure:\n";
echo "- Is array: " . (is_array($decoded) ? 'YES' : 'NO') . "\n";
echo "- Has 'data' key: " . (isset($decoded['data']) ? 'YES' : 'NO') . "\n";
echo "- Is 'data' an array: " . (isset($decoded['data']) && is_array($decoded['data']) ? 'YES' : 'NO') . "\n";
echo "- Number of leads: " . (isset($decoded['data']) ? count($decoded['data']) : 0) . "\n";

if (isset($decoded['data']) && count($decoded['data']) > 0) {
    echo "\nFirst lead:\n";
    print_r($decoded['data'][0]);
}

echo "\n3. Testing POST /leads...\n";
$ch = curl_init('http://localhost/custom/api/index.php/leads');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'firstName' => 'Test',
    'lastName' => 'Lead',
    'email' => 'test.lead@example.com',
    'company' => 'Test Company',
    'status' => 'New'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Create lead response code: $httpCode (expecting 201)\n";
$decoded = json_decode($response, true);
if (isset($decoded['data'])) {
    echo "Created lead ID: " . ($decoded['data']['id'] ?? 'unknown') . "\n";
}