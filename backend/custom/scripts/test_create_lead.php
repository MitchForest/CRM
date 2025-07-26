<?php
// Debug create lead
define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');

require_once('/var/www/html/custom/api/Request.php');
require_once('/var/www/html/custom/api/Response.php');
require_once('/var/www/html/custom/api/Auth/JWT.php');
require_once('/var/www/html/custom/api/controllers/BaseController.php');
require_once('/var/www/html/custom/api/controllers/LeadsController.php');
require_once('/var/www/html/custom/api/DTO/Base/BaseDTO.php');
require_once('/var/www/html/custom/api/DTO/Base/PaginationDTO.php');

// Get token
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

// Set auth header
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

try {
    $controller = new \Api\Controllers\LeadsController();
    
    $requestData = [
        'firstName' => 'Test',
        'lastName' => 'Lead',
        'email' => 'test.lead' . time() . '@example.com',
        'company' => 'Test Company',
        'status' => 'New'
    ];
    
    $request = new \Api\Request('POST', '/leads', $requestData);
    $response = new \Api\Response();
    
    echo "Testing create lead directly...\n";
    $result = $controller->create($request, $response);
    
    echo "Status code: " . $result->getStatusCode() . "\n";
    echo "Response data:\n";
    print_r($result->getData());
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}