<?php
// Debug auth/me endpoint thoroughly
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

echo "Token obtained: " . substr($token, 0, 50) . "...\n\n";

// Now test /auth/me with verbose output
$ch = curl_init('http://localhost/custom/api/index.php/auth/me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "HTTP Code: $httpCode\n";
echo "Raw Response: '$response'\n";
echo "Response length: " . strlen($response) . "\n\n";

$decoded = json_decode($response, true);
echo "Decoded Response:\n";
var_dump($decoded);

echo "\nChecking response structure:\n";
echo "Is array: " . (is_array($decoded) ? 'YES' : 'NO') . "\n";
echo "Has 'data' key: " . (isset($decoded['data']) ? 'YES' : 'NO') . "\n";
echo "Has 'success' key: " . (isset($decoded['success']) ? 'YES' : 'NO') . "\n";

if (isset($decoded['data'])) {
    echo "\nData contents:\n";
    print_r($decoded['data']);
}

curl_close($ch);