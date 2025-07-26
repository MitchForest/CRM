<?php
// Test auth functionality directly with email
define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');

// Include necessary files
require_once('/var/www/html/custom/api/Request.php');
require_once('/var/www/html/custom/api/Response.php');
require_once('/var/www/html/custom/api/Auth/JWT.php');
require_once('/var/www/html/custom/api/controllers/BaseController.php');
require_once('/var/www/html/custom/api/controllers/AuthController.php');

try {
    // Test with email
    $mockData = [
        'email' => 'john.doe@example.com',
        'password' => 'admin123'
    ];
    
    // Create controller
    $controller = new \Api\Controllers\AuthController();
    
    // Create mock request object
    $request = new \Api\Request('POST', '/auth/login', $mockData);
    
    // Test login
    echo "Testing login with email...\n";
    $response = $controller->login($request);
    
    echo "Response:\n";
    if ($response->getStatusCode() == 200) {
        echo "SUCCESS!\n";
        $data = $response->getData();
        print_r($data);
    } else {
        echo "FAILED\n";
        print_r($response);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}