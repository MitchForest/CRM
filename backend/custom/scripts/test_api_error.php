<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Test login via API endpoint
$ch = curl_init('http://localhost/api/auth/login');
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

echo "HTTP Code: $httpCode\n";
echo "Response:\n$response\n";

// Also check if we can access the API index directly
echo "\n\nTesting API index directly...\n";
ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/login';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Set input data
$input = json_encode(['email' => 'john.doe@example.com', 'password' => 'admin123']);
file_put_contents('php://input', $input);

// Include the API index
try {
    require_once('/var/www/html/custom/api/index.php');
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
$output = ob_get_clean();
echo "Direct output:\n$output\n";