<?php
/**
 * API Verification Script - Tests all Phase 5 endpoints
 */

// Configuration
$apiUrl = 'http://localhost/api';
$testEmail = 'john.doe@example.com';
$testPassword = 'admin123';

// Colors for output
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$reset = "\033[0m";

echo "\n=== API Verification Script ===\n";
echo "Testing all Phase 5 endpoints...\n\n";

$accessToken = '';
$failedTests = [];
$passedTests = [];

// Helper function to make API calls
function apiCall($method, $endpoint, $data = null, $token = null) {
    global $apiUrl;
    
    $ch = curl_init($apiUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
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
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response
    ];
}

// Test function
function testEndpoint($name, $method, $endpoint, $data = null, $expectedCode = 200, $token = null) {
    global $green, $red, $yellow, $reset, $failedTests, $passedTests;
    
    echo "Testing: $name... ";
    
    $response = apiCall($method, $endpoint, $data, $token);
    
    if ($response['code'] === $expectedCode) {
        echo "{$green}✓ PASSED{$reset} (HTTP {$response['code']})\n";
        $passedTests[] = $name;
        return $response['data'];
    } else {
        echo "{$red}✗ FAILED{$reset} (Expected: $expectedCode, Got: {$response['code']})\n";
        if ($response['data']) {
            echo "  Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "  Raw Response: " . substr($response['raw'], 0, 200) . "\n";
        }
        $failedTests[] = $name;
        return null;
    }
}

// 1. Test Authentication
echo "\n{$yellow}=== Testing Authentication ==={$reset}\n";

$loginData = testEndpoint(
    'POST /auth/login',
    'POST',
    '/auth/login',
    ['email' => $testEmail, 'password' => $testPassword],
    200
);

if ($loginData && isset($loginData['access_token'])) {
    $accessToken = $loginData['access_token'];
    echo "  Access token obtained successfully\n";
} else {
    echo "{$red}CRITICAL: Cannot proceed without authentication token{$reset}\n";
    exit(1);
}

// Test token refresh
if (isset($loginData['refresh_token'])) {
    testEndpoint(
        'POST /auth/refresh',
        'POST',
        '/auth/refresh',
        ['refresh_token' => $loginData['refresh_token']],
        200
    );
}

// 2. Test Dashboard Endpoints
echo "\n{$yellow}=== Testing Dashboard ==={$reset}\n";

testEndpoint('GET /dashboard/metrics', 'GET', '/dashboard/metrics', null, 200, $accessToken);
testEndpoint('GET /dashboard/activities', 'GET', '/dashboard/activities', null, 200, $accessToken);
testEndpoint('GET /dashboard/pipeline', 'GET', '/dashboard/pipeline', null, 200, $accessToken);

// 3. Test Lead Endpoints
echo "\n{$yellow}=== Testing Leads ==={$reset}\n";

testEndpoint('GET /leads', 'GET', '/leads', null, 200, $accessToken);
testEndpoint('GET /leads?status=New', 'GET', '/leads?status=New', null, 200, $accessToken);
testEndpoint('GET /leads?search=david', 'GET', '/leads?search=david', null, 200, $accessToken);

// Get first lead for detail test
$leadsResponse = apiCall('GET', '/leads', null, $accessToken);
if ($leadsResponse['data'] && isset($leadsResponse['data']['data'][0])) {
    $leadId = $leadsResponse['data']['data'][0]['id'];
    testEndpoint("GET /leads/$leadId", 'GET', "/leads/$leadId", null, 200, $accessToken);
}

// 4. Test Contact Endpoints
echo "\n{$yellow}=== Testing Contacts ==={$reset}\n";

testEndpoint('GET /contacts', 'GET', '/contacts', null, 200, $accessToken);
testEndpoint('GET /contacts/unified', 'GET', '/contacts/unified', null, 200, $accessToken);
testEndpoint('GET /contacts?type=company', 'GET', '/contacts?type=company', null, 200, $accessToken);
testEndpoint('GET /contacts?type=person', 'GET', '/contacts?type=person', null, 200, $accessToken);

// Get first contact for detail test
$contactsResponse = apiCall('GET', '/contacts', null, $accessToken);
if ($contactsResponse['data'] && isset($contactsResponse['data']['data'][0])) {
    $contactId = $contactsResponse['data']['data'][0]['id'];
    testEndpoint("GET /contacts/$contactId", 'GET', "/contacts/$contactId", null, 200, $accessToken);
    testEndpoint("GET /contacts/$contactId/unified", 'GET', "/contacts/$contactId/unified", null, 200, $accessToken);
}

// 5. Test Opportunity Endpoints
echo "\n{$yellow}=== Testing Opportunities ==={$reset}\n";

testEndpoint('GET /opportunities', 'GET', '/opportunities', null, 200, $accessToken);
testEndpoint('GET /opportunities?stage=Proposal', 'GET', '/opportunities?stage=Proposal', null, 200, $accessToken);

// Get first opportunity for detail test
$oppsResponse = apiCall('GET', '/opportunities', null, $accessToken);
if ($oppsResponse['data'] && isset($oppsResponse['data']['data'][0])) {
    $oppId = $oppsResponse['data']['data'][0]['id'];
    testEndpoint("GET /opportunities/$oppId", 'GET', "/opportunities/$oppId", null, 200, $accessToken);
}

// 6. Test Case (Support Ticket) Endpoints
echo "\n{$yellow}=== Testing Support Tickets ==={$reset}\n";

testEndpoint('GET /cases', 'GET', '/cases', null, 200, $accessToken);
testEndpoint('GET /cases?status=Open', 'GET', '/cases?status=Open', null, 200, $accessToken);
testEndpoint('GET /cases?priority=High', 'GET', '/cases?priority=High', null, 200, $accessToken);

// Get first case for detail test
$casesResponse = apiCall('GET', '/cases', null, $accessToken);
if ($casesResponse['data'] && isset($casesResponse['data']['data'][0])) {
    $caseId = $casesResponse['data']['data'][0]['id'];
    testEndpoint("GET /cases/$caseId", 'GET', "/cases/$caseId", null, 200, $accessToken);
}

// 7. Test Form Builder Endpoints
echo "\n{$yellow}=== Testing Form Builder ==={$reset}\n";

testEndpoint('GET /forms', 'GET', '/forms', null, 200, $accessToken);
testEndpoint('GET /forms/active', 'GET', '/forms/active', null, 200, $accessToken);

// Get first form for submissions test
$formsResponse = apiCall('GET', '/forms', null, $accessToken);
if ($formsResponse['data'] && isset($formsResponse['data']['data'][0])) {
    $formId = $formsResponse['data']['data'][0]['id'];
    testEndpoint("GET /forms/$formId", 'GET', "/forms/$formId", null, 200, $accessToken);
    testEndpoint("GET /forms/$formId/submissions", 'GET', "/forms/$formId/submissions", null, 200, $accessToken);
}

// 8. Test Knowledge Base Endpoints
echo "\n{$yellow}=== Testing Knowledge Base ==={$reset}\n";

testEndpoint('GET /kb/categories', 'GET', '/kb/categories', null, 200, $accessToken);
testEndpoint('GET /kb/articles', 'GET', '/kb/articles', null, 200, $accessToken);
testEndpoint('GET /kb/articles?search=login', 'GET', '/kb/articles?search=login', null, 200, $accessToken);

// Get first article for detail test
$articlesResponse = apiCall('GET', '/kb/articles', null, $accessToken);
if ($articlesResponse['data'] && isset($articlesResponse['data']['data'][0])) {
    $articleId = $articlesResponse['data']['data'][0]['id'];
    testEndpoint("GET /kb/articles/$articleId", 'GET', "/kb/articles/$articleId", null, 200, $accessToken);
}

// 9. Test AI Chat Endpoints
echo "\n{$yellow}=== Testing AI Chat ==={$reset}\n";

// Create a new conversation
$convData = testEndpoint(
    'POST /ai/chat/start',
    'POST',
    '/ai/chat/start',
    ['contactId' => $contactId ?? null],
    200,
    $accessToken
);

if ($convData && isset($convData['conversationId'])) {
    $conversationId = $convData['conversationId'];
    
    // Send a message
    testEndpoint(
        'POST /ai/chat/message',
        'POST',
        '/ai/chat/message',
        [
            'conversationId' => $conversationId,
            'message' => 'What are your business hours?'
        ],
        200,
        $accessToken
    );
}

// 10. Test Activity Tracking
echo "\n{$yellow}=== Testing Activity Tracking ==={$reset}\n";

testEndpoint('GET /activities', 'GET', '/activities', null, 200, $accessToken);
testEndpoint('GET /activities?type=call', 'GET', '/activities?type=call', null, 200, $accessToken);

// 11. Test User Profile
echo "\n{$yellow}=== Testing User Profile ==={$reset}\n";

testEndpoint('GET /auth/me', 'GET', '/auth/me', null, 200, $accessToken);

// 12. Test Logout
echo "\n{$yellow}=== Testing Logout ==={$reset}\n";

testEndpoint('POST /auth/logout', 'POST', '/auth/logout', null, 200, $accessToken);

// Summary
echo "\n{$yellow}=== Test Summary ==={$reset}\n";
echo "Total tests: " . (count($passedTests) + count($failedTests)) . "\n";
echo "{$green}Passed: " . count($passedTests) . "{$reset}\n";
echo "{$red}Failed: " . count($failedTests) . "{$reset}\n";

if (count($failedTests) > 0) {
    echo "\nFailed tests:\n";
    foreach ($failedTests as $test) {
        echo "  - $test\n";
    }
    exit(1);
} else {
    echo "\n{$green}All tests passed successfully!{$reset}\n";
    echo "\nThe API is fully functional and ready for use.\n";
    exit(0);
}