#!/usr/bin/env php
<?php
/**
 * Verify all API endpoints are working correctly
 * Run: php verify_apis.php
 */

// Load environment
require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$baseUrl = 'http://localhost:8080/custom/api';
$testEmail = 'john.doe@example.com';
$testPassword = 'admin123';

echo "=== API Verification Script ===\n\n";

// Test 1: Health Check
echo "1. Testing Health Check... ";
$response = file_get_contents($baseUrl . '/health');
$data = json_decode($response, true);
if ($data && $data['status'] === 'healthy') {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
}

// Test 2: Login
echo "2. Testing Login... ";
$loginData = json_encode(['email' => $testEmail, 'password' => $testPassword]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $loginData
    ]
]);
$response = @file_get_contents($baseUrl . '/auth/login', false, $context);
$authData = json_decode($response, true);

if ($authData && isset($authData['data']['access_token'])) {
    $token = $authData['data']['access_token'];
    echo "✓ PASS (Token: " . substr($token, 0, 20) . "...)\n";
} else {
    echo "✗ FAIL - Could not get auth token\n";
    echo "Response: " . $response . "\n";
    exit(1);
}

// Create authenticated context
$authContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $token\r\n"
    ]
]);

// Test 3: Dashboard Metrics
echo "3. Testing Dashboard Metrics... ";
$response = @file_get_contents($baseUrl . '/dashboard/metrics', false, $authContext);
$data = json_decode($response, true);
if ($data && isset($data['data']['totalLeads'])) {
    echo "✓ PASS (Leads: {$data['data']['totalLeads']})\n";
} else {
    echo "✗ FAIL\n";
}

// Test 4: Leads API
echo "4. Testing Leads API... ";
$response = @file_get_contents($baseUrl . '/leads', false, $authContext);
$data = json_decode($response, true);
if ($data && isset($data['data']) && is_array($data['data'])) {
    echo "✓ PASS (Count: " . count($data['data']) . ")\n";
} else {
    echo "✗ FAIL\n";
}

// Test 5: Contacts Unified View
echo "5. Testing Contacts Unified View... ";
// Get first contact ID
$response = @file_get_contents($baseUrl . '/contacts?limit=1', false, $authContext);
$contactsData = json_decode($response, true);
if ($contactsData && isset($contactsData['data'][0]['id'])) {
    $contactId = $contactsData['data'][0]['id'];
    $response = @file_get_contents($baseUrl . "/contacts/$contactId/unified", false, $authContext);
    $data = json_decode($response, true);
    if ($data && isset($data['data']['contact'])) {
        echo "✓ PASS\n";
    } else {
        echo "✗ FAIL - Unified view not working\n";
    }
} else {
    echo "✗ FAIL - No contacts found\n";
}

// Test 6: Opportunities API
echo "6. Testing Opportunities API... ";
$response = @file_get_contents($baseUrl . '/opportunities', false, $authContext);
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✓ PASS (Count: " . count($data['data']) . ")\n";
} else {
    echo "✗ FAIL\n";
}

// Test 7: Cases (Support Tickets) API
echo "7. Testing Support Tickets API... ";
$response = @file_get_contents($baseUrl . '/cases', false, $authContext);
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✓ PASS (Count: " . count($data['data']) . ")\n";
} else {
    echo "✗ FAIL\n";
}

// Test 8: Forms API
echo "8. Testing Forms API... ";
$response = @file_get_contents($baseUrl . '/forms', false, $authContext);
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✓ PASS (Count: " . count($data['data']) . ")\n";
} else {
    echo "✗ FAIL\n";
}

// Test 9: Knowledge Base API
echo "9. Testing Knowledge Base API... ";
$response = @file_get_contents($baseUrl . '/knowledge-base/articles', false, $authContext);
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✓ PASS (Articles: " . count($data['data']) . ")\n";
} else {
    echo "✗ FAIL\n";
}

// Test 10: AI Chat (Public Endpoint)
echo "10. Testing AI Chat API... ";
$chatData = json_encode([
    'message' => 'Hello, how can you help me?',
    'conversation_id' => null
]);
$chatContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $chatData
    ]
]);
$response = @file_get_contents($baseUrl . '/ai/chat', false, $chatContext);
$data = json_decode($response, true);
if ($data && isset($data['data']['message'])) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Check OPENAI_API_KEY\n";
}

// Test 11: Activity Tracking (Public Endpoint)
echo "11. Testing Activity Tracking API... ";
$trackData = json_encode([
    'page_url' => '/test-page',
    'page_title' => 'Test Page',
    'visitor_id' => 'test-visitor-123'
]);
$trackContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $trackData
    ]
]);
$response = @file_get_contents($baseUrl . '/track/pageview', false, $trackContext);
if ($response !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
}

// Test 12: Logout
echo "12. Testing Logout... ";
$logoutContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Authorization: Bearer $token\r\n"
    ]
]);
$response = @file_get_contents($baseUrl . '/auth/logout', false, $logoutContext);
if ($response !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL\n";
}

echo "\n=== API Verification Complete ===\n";