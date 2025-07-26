<?php
// Debug leads controller
define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');

// Include necessary files
require_once('/var/www/html/custom/api/Request.php');
require_once('/var/www/html/custom/api/Response.php');
require_once('/var/www/html/custom/api/Auth/JWT.php');
require_once('/var/www/html/custom/api/controllers/BaseController.php');
require_once('/var/www/html/custom/api/controllers/LeadsController.php');

// Include DTOs
require_once('/var/www/html/custom/api/DTO/Base/BaseDTO.php');
require_once('/var/www/html/custom/api/DTO/Base/PaginationDTO.php');

try {
    // Create controller
    $controller = new \Api\Controllers\LeadsController();
    
    // Create mock request object
    $request = new \Api\Request('GET', '/leads', []);
    
    // Create response object
    $response = new \Api\Response();
    
    // Test leads
    echo "Testing leads controller directly...\n";
    
    // Set auth header for testing
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoiZDc5ZTkyNTItZTU1NS02N2FhLWU5MGItNjg4NGYxZjNjNDcwIiwidXNlcm5hbWUiOiJqb2huLmRvZSIsImVtYWlsIjoiam9obi5kb2VAZXhhbXBsZS5jb20iLCJleHAiOjk5OTk5OTk5OTksImlhdCI6MTc1MzU0NTUwOX0.MltcdBr0K4e5Ws8x0kQHO5k4XmRXhOe-gOk6KNMaC_g';
    
    $result = $controller->index($request, $response);
    
    echo "Result:\n";
    print_r($result);
    
    echo "\nResult data:\n";
    print_r($result->getData());
    
    echo "\nResult status code:\n";
    echo $result->getStatusCode() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}