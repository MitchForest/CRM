<?php
// Test if v8 API is accessible
error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiUrl = 'http://localhost:8080/Api/V8/meta/modules';

// Test without authentication first
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/vnd.api+json',
    'Content-Type: application/vnd.api+json'
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "CURL Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Code: $httpCode\n";
    echo "Response Length: " . strlen($response) . "\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "Verbose log:\n", $verboseLog, "\n";

curl_close($ch);

// Also test the login endpoint
echo "\n\nTesting login endpoint:\n";
$loginUrl = 'http://localhost:8080/Api/V8/oauth2/token';
$loginData = [
    'grant_type' => 'password',
    'client_id' => 'sugar',
    'username' => 'admin',
    'password' => 'admin'
];

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Login HTTP Code: $httpCode\n";
echo "Login Response: " . $response . "\n";

curl_close($ch);