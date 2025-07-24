<?php
// Test script to verify SuiteCRM v8 API field behavior and update operations

$baseUrl = 'http://localhost:8080/Api/V8';
$username = 'apiuser';
$password = 'apiuser123';

// Function to get OAuth2 token
function getToken($username, $password) {
    $tokenUrl = 'http://localhost:8080/Api/access_token';
    
    $data = [
        'grant_type' => 'password',
        'client_id' => 'suitecrm_client',
        'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'test_secret_for_development_only',
        'username' => $username,
        'password' => $password
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "Failed to get token. HTTP Code: $httpCode\n";
        echo "Response: $response\n";
        return null;
    }
    
    $tokenData = json_decode($response, true);
    return $tokenData['access_token'] ?? null;
}

// Function to make API request
function apiRequest($method, $endpoint, $data = null, $token = null) {
    $ch = curl_init($endpoint);
    
    $headers = [
        'Content-Type: application/vnd.api+json',
        'Accept: application/vnd.api+json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && $method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

echo "=== SuiteCRM v8 API Field Verification Test ===\n\n";

// Get token
echo "1. Getting OAuth2 token...\n";
$token = getToken($username, $password);
if (!$token) {
    die("Failed to authenticate\n");
}
echo "✓ Token obtained successfully\n\n";

// Test 1: Create a lead with email vs email1
echo "2. Testing lead creation with different email fields...\n";

// Test with 'email' field
$leadData1 = [
    'data' => [
        'type' => 'Leads',
        'attributes' => [
            'first_name' => 'Test',
            'last_name' => 'Email Field',
            'email' => 'test-email@example.com',
            'status' => 'New'
        ]
    ]
];

$result1 = apiRequest('POST', $baseUrl . '/module', $leadData1, $token);
echo "Create with 'email' field - HTTP Code: {$result1['code']}\n";
if ($result1['code'] === 201 || $result1['code'] === 200) {
    $leadId1 = $result1['response']['data']['id'] ?? null;
    echo "✓ Lead created with ID: $leadId1\n";
    
    // Check if email was saved
    $checkResult = apiRequest('GET', $baseUrl . '/module/Leads/' . $leadId1, null, $token);
    $savedEmail = $checkResult['response']['data']['attributes']['email1'] ?? 'NOT FOUND';
    echo "  Email saved as: $savedEmail\n";
    
    if ($savedEmail === 'NOT FOUND') {
        echo "  ⚠️  Email field was NOT saved!\n";
    }
} else {
    echo "✗ Failed to create lead\n";
    echo "Response: " . json_encode($result1['response'], JSON_PRETTY_PRINT) . "\n";
}

echo "\n";

// Test with 'email1' field
$leadData2 = [
    'data' => [
        'type' => 'Leads',
        'attributes' => [
            'first_name' => 'Test',
            'last_name' => 'Email1 Field',
            'email1' => 'test-email1@example.com',
            'status' => 'New'
        ]
    ]
];

$result2 = apiRequest('POST', $baseUrl . '/module', $leadData2, $token);
echo "Create with 'email1' field - HTTP Code: {$result2['code']}\n";
if ($result2['code'] === 201 || $result2['code'] === 200) {
    $leadId2 = $result2['response']['data']['id'] ?? null;
    echo "✓ Lead created with ID: $leadId2\n";
    
    // Check if email was saved
    $checkResult = apiRequest('GET', $baseUrl . '/module/Leads/' . $leadId2, null, $token);
    $savedEmail = $checkResult['response']['data']['attributes']['email1'] ?? 'NOT FOUND';
    echo "  Email saved as: $savedEmail\n";
    
    if ($savedEmail === 'test-email1@example.com') {
        echo "  ✓ Email field was saved correctly!\n";
    }
} else {
    echo "✗ Failed to create lead\n";
    echo "Response: " . json_encode($result2['response'], JSON_PRETTY_PRINT) . "\n";
}

echo "\n";

// Test 3: Test update operations
echo "3. Testing update operations...\n";

if (isset($leadId2)) {
    // Test PATCH to /module endpoint
    $updateData1 = [
        'data' => [
            'type' => 'Leads',
            'id' => $leadId2,
            'attributes' => [
                'first_name' => 'Updated'
            ]
        ]
    ];
    
    echo "\nTesting PATCH to /module endpoint...\n";
    $result = apiRequest('PATCH', $baseUrl . '/module', $updateData1, $token);
    echo "HTTP Code: {$result['code']}\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    
    // Test PATCH to /module/Leads/{id} endpoint
    echo "\nTesting PATCH to /module/Leads/{id} endpoint...\n";
    $result = apiRequest('PATCH', $baseUrl . '/module/Leads/' . $leadId2, $updateData1, $token);
    echo "HTTP Code: {$result['code']}\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    
    // Test PUT to /module/Leads/{id} endpoint
    echo "\nTesting PUT to /module/Leads/{id} endpoint...\n";
    $result = apiRequest('PUT', $baseUrl . '/module/Leads/' . $leadId2, $updateData1, $token);
    echo "HTTP Code: {$result['code']}\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
}

// Test 4: Check custom fields
echo "\n4. Checking if custom fields are returned...\n";
if (isset($leadId2)) {
    $result = apiRequest('GET', $baseUrl . '/module/Leads/' . $leadId2, null, $token);
    if ($result['code'] === 200) {
        $attributes = $result['response']['data']['attributes'] ?? [];
        
        // Check for custom fields
        $customFields = ['ai_score', 'ai_score_date', 'ai_insights'];
        foreach ($customFields as $field) {
            if (isset($attributes[$field])) {
                echo "✓ Custom field '$field' found: " . $attributes[$field] . "\n";
            } else {
                echo "✗ Custom field '$field' NOT found\n";
            }
        }
    }
}

// Clean up - delete test leads
echo "\n5. Cleaning up test data...\n";
if (isset($leadId1)) {
    apiRequest('DELETE', $baseUrl . '/module/Leads/' . $leadId1, null, $token);
    echo "✓ Deleted test lead 1\n";
}
if (isset($leadId2)) {
    apiRequest('DELETE', $baseUrl . '/module/Leads/' . $leadId2, null, $token);
    echo "✓ Deleted test lead 2\n";
}

echo "\n=== Test Complete ===\n";