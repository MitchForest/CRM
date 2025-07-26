<?php
/**
 * Comprehensive API Test Script
 * Tests all Phase 5 API endpoints
 */

// Configuration
// Use direct path to avoid htaccess issues
$apiUrl = 'http://localhost/custom/api/index.php';
$testEmail = 'john.doe@example.com';
$testPassword = 'admin123';

// Colors for output
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "\n{$blue}=== Comprehensive API Test Suite ==={$reset}\n";
echo "Testing all Phase 5 API endpoints...\n\n";

$accessToken = '';
$testResults = [];
$totalTests = 0;
$passedTests = 0;

// Helper function to make API calls
function apiCall($method, $endpoint, $data = null, $token = null) {
    global $apiUrl;
    
    $ch = curl_init($apiUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

// Test function
function testEndpoint($category, $name, $method, $endpoint, $data = null, $expectedCode = 200, $token = null, $validateResponse = null) {
    global $green, $red, $yellow, $reset, $totalTests, $passedTests, $testResults;
    
    $totalTests++;
    echo "  Testing: $name... ";
    
    $response = apiCall($method, $endpoint, $data, $token);
    
    $passed = false;
    $message = '';
    
    if ($response['error']) {
        $message = "CURL Error: " . $response['error'];
    } elseif ($response['code'] === $expectedCode) {
        if ($validateResponse && is_callable($validateResponse)) {
            $validationResult = $validateResponse($response['data']);
            if ($validationResult === true) {
                $passed = true;
            } else {
                $message = "Validation failed: " . $validationResult;
            }
        } else {
            $passed = true;
        }
    } else {
        $message = "Expected: $expectedCode, Got: {$response['code']}";
        if ($response['data'] && isset($response['data']['error'])) {
            $message .= " - " . $response['data']['error'];
        }
    }
    
    if ($passed) {
        echo "{$green}✓ PASSED{$reset}\n";
        $passedTests++;
    } else {
        echo "{$red}✗ FAILED{$reset} ($message)\n";
    }
    
    if (!isset($testResults[$category])) {
        $testResults[$category] = ['passed' => 0, 'failed' => 0, 'tests' => []];
    }
    
    $testResults[$category]['tests'][] = [
        'name' => $name,
        'passed' => $passed,
        'message' => $message
    ];
    
    if ($passed) {
        $testResults[$category]['passed']++;
    } else {
        $testResults[$category]['failed']++;
    }
    
    return $response['data'];
}

// 1. Test Authentication
echo "{$yellow}1. Authentication Tests{$reset}\n";

$loginData = testEndpoint(
    'Authentication',
    'POST /auth/login',
    'POST',
    '/auth/login',
    ['email' => $testEmail, 'password' => $testPassword],
    200,
    null,
    function($data) {
        return isset($data['data']['accessToken']) && isset($data['data']['refreshToken']) ? true : 'Missing tokens';
    }
);

if ($loginData && isset($loginData['data']['accessToken'])) {
    $accessToken = $loginData['data']['accessToken'];
    $refreshToken = $loginData['data']['refreshToken'];
    echo "  {$green}Access token obtained{$reset}\n";
    
    // Test refresh
    testEndpoint(
        'Authentication',
        'POST /auth/refresh',
        'POST',
        '/auth/refresh',
        ['refreshToken' => $refreshToken],
        200,
        null,
        function($data) {
            return isset($data['data']['accessToken']) ? true : 'Missing new access token';
        }
    );
    
    // Test profile
    testEndpoint(
        'Authentication',
        'GET /auth/me',
        'GET',
        '/auth/me',
        null,
        200,
        $accessToken,
        function($data) {
            return isset($data['data']['id']) && isset($data['data']['email']) ? true : 'Missing user data';
        }
    );
} else {
    echo "  {$red}Cannot proceed without authentication{$reset}\n";
    exit(1);
}

// 2. Test Dashboard
echo "\n{$yellow}2. Dashboard Tests{$reset}\n";

testEndpoint('Dashboard', 'GET /dashboard/metrics', 'GET', '/dashboard/metrics', null, 200, $accessToken);
testEndpoint('Dashboard', 'GET /dashboard/pipeline', 'GET', '/dashboard/pipeline', null, 200, $accessToken);
testEndpoint('Dashboard', 'GET /dashboard/activities', 'GET', '/dashboard/activities', null, 200, $accessToken);

// 3. Test Leads
echo "\n{$yellow}3. Lead Management Tests{$reset}\n";

$leadsData = testEndpoint(
    'Leads',
    'GET /leads',
    'GET',
    '/leads',
    null,
    200,
    $accessToken,
    function($data) {
        return isset($data['data']) && is_array($data['data']) ? true : 'Invalid response structure';
    }
);

if ($leadsData && isset($leadsData['data'][0])) {
    $leadId = $leadsData['data'][0]['id'];
    testEndpoint('Leads', "GET /leads/$leadId", 'GET', "/leads/$leadId", null, 200, $accessToken);
}

// Create new lead
$newLead = testEndpoint(
    'Leads',
    'POST /leads',
    'POST',
    '/leads',
    [
        'firstName' => 'Test',
        'lastName' => 'Lead',
        'email' => 'test.lead@example.com',
        'company' => 'Test Company',
        'status' => 'New'
    ],
    201,
    $accessToken
);

// 4. Test Contacts
echo "\n{$yellow}4. Contact Management Tests{$reset}\n";

testEndpoint('Contacts', 'GET /contacts', 'GET', '/contacts', null, 200, $accessToken);
testEndpoint('Contacts', 'GET /contacts/unified', 'GET', '/contacts/unified', null, 200, $accessToken);

// 5. Test Opportunities
echo "\n{$yellow}5. Opportunity Management Tests{$reset}\n";

testEndpoint('Opportunities', 'GET /opportunities', 'GET', '/opportunities', null, 200, $accessToken);

// 6. Test Cases (Support Tickets)
echo "\n{$yellow}6. Support Ticket Tests{$reset}\n";

testEndpoint('Cases', 'GET /cases', 'GET', '/cases', null, 200, $accessToken);

// Create support ticket
$newCase = testEndpoint(
    'Cases',
    'POST /cases',
    'POST',
    '/cases',
    [
        'name' => 'Test Support Ticket',
        'description' => 'This is a test ticket created via API',
        'status' => 'Open',
        'priority' => 'Medium'
    ],
    201,
    $accessToken
);

// 7. Test Activities
echo "\n{$yellow}7. Activity Tests{$reset}\n";

testEndpoint('Activities', 'GET /activities', 'GET', '/activities', null, 200, $accessToken);

// 8. Test Knowledge Base
echo "\n{$yellow}8. Knowledge Base Tests{$reset}\n";

testEndpoint('Knowledge Base', 'GET /kb/categories', 'GET', '/kb/categories', null, 200, $accessToken);
testEndpoint('Knowledge Base', 'GET /kb/articles', 'GET', '/kb/articles', null, 200, $accessToken);
testEndpoint('Knowledge Base', 'GET /kb/search?q=login', 'GET', '/kb/search?q=login', null, 200, $accessToken);

// 9. Test Forms
echo "\n{$yellow}9. Form Builder Tests{$reset}\n";

$formsData = testEndpoint('Forms', 'GET /forms', 'GET', '/forms', null, 200, $accessToken);
testEndpoint('Forms', 'GET /forms/active', 'GET', '/forms/active', null, 200, $accessToken);

if ($formsData && isset($formsData['data'][0])) {
    $formId = $formsData['data'][0]['id'];
    testEndpoint('Forms', "GET /forms/$formId", 'GET', "/forms/$formId", null, 200, null); // Public endpoint
    
    // Test form submission (public endpoint)
    testEndpoint(
        'Forms',
        "POST /forms/$formId/submit",
        'POST',
        "/forms/$formId/submit",
        [
            'firstName' => 'Form',
            'lastName' => 'Test',
            'email' => 'formtest@example.com',
            'message' => 'Testing form submission'
        ],
        200,
        null // Public endpoint
    );
}

// 10. Test AI Chat
echo "\n{$yellow}10. AI Chat Tests{$reset}\n";

// Start conversation
$convData = testEndpoint(
    'AI Chat',
    'POST /ai/chat/start',
    'POST',
    '/ai/chat/start',
    ['contactId' => null],
    200,
    $accessToken
);

if ($convData && isset($convData['conversationId'])) {
    $conversationId = $convData['conversationId'];
    
    // Send message
    $chatResponse = testEndpoint(
        'AI Chat',
        'POST /ai/chat/message',
        'POST',
        '/ai/chat/message',
        [
            'conversationId' => $conversationId,
            'message' => 'I need help with login issues'
        ],
        200,
        $accessToken
    );
    
    // Test intent-based actions
    if ($chatResponse && isset($chatResponse['metadata']['intent'])) {
        if ($chatResponse['metadata']['intent'] === 'support') {
            testEndpoint(
                'AI Chat',
                'POST /ai/chat/create-ticket',
                'POST',
                '/ai/chat/create-ticket',
                [
                    'conversationId' => $conversationId,
                    'summary' => 'Login issue from chat',
                    'priority' => 'High'
                ],
                200,
                $accessToken
            );
        }
    }
}

// 11. Test Activity Tracking
echo "\n{$yellow}11. Activity Tracking Tests{$reset}\n";

// Public endpoints for tracking
testEndpoint(
    'Activity Tracking',
    'POST /track/pageview',
    'POST',
    '/track/pageview',
    [
        'visitor_id' => 'test-visitor-123',
        'page_url' => '/test-page',
        'title' => 'Test Page'
    ],
    200,
    null // Public endpoint
);

// 12. Test Analytics
echo "\n{$yellow}12. Analytics Tests{$reset}\n";

testEndpoint('Analytics', 'GET /analytics/overview', 'GET', '/analytics/overview', null, 200, $accessToken);
testEndpoint('Analytics', 'GET /analytics/conversion-funnel', 'GET', '/analytics/conversion-funnel', null, 200, $accessToken);
testEndpoint('Analytics', 'GET /analytics/lead-sources', 'GET', '/analytics/lead-sources', null, 200, $accessToken);

// 13. Test Logout
echo "\n{$yellow}13. Cleanup Tests{$reset}\n";

testEndpoint('Authentication', 'POST /auth/logout', 'POST', '/auth/logout', null, 200, $accessToken);

// Summary
echo "\n{$blue}=== Test Summary ==={$reset}\n";
echo "Total tests: $totalTests\n";
echo "{$green}Passed: $passedTests{$reset}\n";
echo "{$red}Failed: " . ($totalTests - $passedTests) . "{$reset}\n";

if ($totalTests > 0) {
    $successRate = round(($passedTests / $totalTests) * 100, 2);
    $color = $successRate >= 80 ? $green : ($successRate >= 60 ? $yellow : $red);
    echo "Success rate: {$color}{$successRate}%{$reset}\n";
}

// Detailed results by category
echo "\n{$blue}Results by Category:{$reset}\n";
foreach ($testResults as $category => $results) {
    $categoryRate = $results['passed'] / ($results['passed'] + $results['failed']) * 100;
    $categoryColor = $categoryRate >= 80 ? $green : ($categoryRate >= 60 ? $yellow : $red);
    
    echo "\n$category: {$categoryColor}" . round($categoryRate, 1) . "%{$reset} ";
    echo "({$results['passed']} passed, {$results['failed']} failed)\n";
    
    // Show failed tests
    foreach ($results['tests'] as $test) {
        if (!$test['passed']) {
            echo "  {$red}✗{$reset} {$test['name']}: {$test['message']}\n";
        }
    }
}

echo "\n";
exit($totalTests === $passedTests ? 0 : 1);