<?php
// Debug getCurrentUser method
define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');

// Include necessary files
require_once('/var/www/html/custom/api/Request.php');
require_once('/var/www/html/custom/api/Response.php');
require_once('/var/www/html/custom/api/Auth/JWT.php');
require_once('/var/www/html/custom/api/controllers/BaseController.php');
require_once('/var/www/html/custom/api/controllers/AuthController.php');

// First login to get fresh token
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

echo "Fresh token obtained\n\n";

// Set up environment to test getCurrentUser
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/auth/me';

// Create controller
$controller = new \Api\Controllers\AuthController();

// Create mock request and response objects
$request = new \Api\Request('GET', '/auth/me', []);
$response = new \Api\Response();

echo "Testing getCurrentUser method directly...\n";

try {
    $result = $controller->getCurrentUser($request, $response);
    
    echo "Result type: " . gettype($result) . "\n";
    echo "Result class: " . get_class($result) . "\n";
    
    echo "\nResult data:\n";
    print_r($result->getData());
    
    echo "\nResult status code: " . $result->getStatusCode() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}