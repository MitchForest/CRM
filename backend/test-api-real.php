<?php
// Test the actual API endpoints with real requests

echo "=== Testing API Endpoints with Real SuiteCRM Data ===\n\n";

$baseUrl = 'http://localhost:8080/custom/api/index.php';

// Helper function to make API requests
function apiRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $url = "http://localhost:8080/custom/api/index.php" . $endpoint;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && $method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Test 1: Login
echo "Test 1: Login\n";
$loginResponse = apiRequest('/auth/login', 'POST', [
    'username' => 'admin',
    'password' => 'admin'
]);

if ($loginResponse['code'] === 200) {
    echo "✓ Login successful\n";
    $token = $loginResponse['body']['access_token'];
    echo "Token: " . substr($token, 0, 20) . "...\n";
} else {
    echo "✗ Login failed: " . json_encode($loginResponse) . "\n";
    exit(1);
}

// Test 2: Create a Contact
echo "\nTest 2: Create Contact\n";
$contactData = [
    'firstName' => 'API Test',
    'lastName' => 'Contact',
    'email' => 'apitest@example.com',
    'phoneMobile' => '+1234567890',
    'leadSource' => 'Web Site'
];

$createResponse = apiRequest('/contacts', 'POST', $contactData, $token);
if ($createResponse['code'] === 201) {
    echo "✓ Contact created successfully\n";
    $contactId = $createResponse['body']['id'];
    echo "Contact ID: $contactId\n";
} else {
    echo "✗ Create contact failed: " . json_encode($createResponse) . "\n";
    $contactId = null;
}

// Test 3: Get Contact
if ($contactId) {
    echo "\nTest 3: Get Contact\n";
    $getResponse = apiRequest("/contacts/$contactId", 'GET', null, $token);
    if ($getResponse['code'] === 200) {
        echo "✓ Contact retrieved successfully\n";
        $contact = $getResponse['body'];
        echo "Name: {$contact['firstName']} {$contact['lastName']}\n";
        echo "Email: {$contact['email']}\n";
    } else {
        echo "✗ Get contact failed: " . json_encode($getResponse) . "\n";
    }
}

// Test 4: Update Contact
if ($contactId) {
    echo "\nTest 4: Update Contact\n";
    $updateData = [
        'firstName' => 'Updated API',
        'description' => 'Updated via API test'
    ];
    
    $updateResponse = apiRequest("/contacts/$contactId", 'PUT', $updateData, $token);
    if ($updateResponse['code'] === 200) {
        echo "✓ Contact updated successfully\n";
    } else {
        echo "✗ Update contact failed: " . json_encode($updateResponse) . "\n";
    }
}

// Test 5: Create Lead
echo "\nTest 5: Create Lead\n";
$leadData = [
    'firstName' => 'API Test',
    'lastName' => 'Lead',
    'email' => 'apilead@example.com',
    'status' => 'New',
    'leadSource' => 'Cold Call',
    'leadScore' => 75
];

$leadResponse = apiRequest('/leads', 'POST', $leadData, $token);
if ($leadResponse['code'] === 201) {
    echo "✓ Lead created successfully\n";
    $leadId = $leadResponse['body']['id'];
    echo "Lead ID: $leadId\n";
} else {
    echo "✗ Create lead failed: " . json_encode($leadResponse) . "\n";
    $leadId = null;
}

// Test 6: Create Opportunity
echo "\nTest 6: Create Opportunity\n";
$oppData = [
    'name' => 'API Test Opportunity',
    'amount' => 50000,
    'salesStage' => 'Proposal/Price Quote',
    'dateClosed' => date('Y-m-d', strtotime('+30 days')),
    'probability' => 65
];

$oppResponse = apiRequest('/opportunities', 'POST', $oppData, $token);
if ($oppResponse['code'] === 201) {
    echo "✓ Opportunity created successfully\n";
    $oppId = $oppResponse['body']['id'];
    echo "Opportunity ID: $oppId\n";
} else {
    echo "✗ Create opportunity failed: " . json_encode($oppResponse) . "\n";
    $oppId = null;
}

// Test 7: List Contacts with pagination
echo "\nTest 7: List Contacts with Pagination\n";
$listResponse = apiRequest('/contacts?page=1&limit=5', 'GET', null, $token);
if ($listResponse['code'] === 200) {
    echo "✓ Contact list retrieved successfully\n";
    echo "Total records: " . $listResponse['body']['pagination']['total'] . "\n";
    echo "Retrieved: " . count($listResponse['body']['data']) . " contacts\n";
} else {
    echo "✗ List contacts failed: " . json_encode($listResponse) . "\n";
}

// Clean up - Delete test records
echo "\nCleaning up test data...\n";
if ($contactId) {
    $deleteResponse = apiRequest("/contacts/$contactId", 'DELETE', null, $token);
    echo "Delete contact: " . ($deleteResponse['code'] === 200 ? '✓' : '✗') . "\n";
}
if ($leadId) {
    $deleteResponse = apiRequest("/leads/$leadId", 'DELETE', null, $token);
    echo "Delete lead: " . ($deleteResponse['code'] === 200 ? '✓' : '✗') . "\n";
}
if ($oppId) {
    $deleteResponse = apiRequest("/opportunities/$oppId", 'DELETE', null, $token);
    echo "Delete opportunity: " . ($deleteResponse['code'] === 200 ? '✓' : '✗') . "\n";
}

echo "\n=== API Integration Test Complete ===\n";