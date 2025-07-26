<?php
// Test login directly
$apiUrl = 'http://localhost/api';
$testEmail = 'john.doe@example.com';
$testPassword = 'admin123';

$ch = curl_init($apiUrl . '/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $testEmail,
    'password' => $testPassword
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode != 200) {
    echo "\nVerbose output:\n";
    echo $verboseLog;
}

curl_close($ch);