<?php
// Test leads endpoint
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

// Now test /leads
$ch = curl_init('http://localhost/custom/api/index.php/leads');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$decoded = json_decode($response, true);
print_r($decoded);

// Check structure
echo "\nStructure check:\n";
echo "Has 'data' key: " . (isset($decoded['data']) ? 'YES' : 'NO') . "\n";
echo "Is 'data' an array: " . (is_array($decoded['data'] ?? null) ? 'YES' : 'NO') . "\n";