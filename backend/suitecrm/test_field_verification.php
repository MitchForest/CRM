<?php
/**
 * Test script to verify correct field names for SuiteCRM v8 API
 * This will help us understand what field names the API expects vs what it returns
 */

// Test configuration
$apiUrl = 'http://localhost:8080/Api';
$clientId = 'suitecrm_client';
$clientSecret = 'secret123';
$username = 'apiuser';
$password = 'apiuser123';

// Colors for output
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "{$blue}=== SuiteCRM Field Name Verification ==={$reset}\n\n";

// Step 1: Authenticate
echo "1. Authenticating...\n";
$authData = http_build_query([
    'grant_type' => 'password',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'username' => $username,
    'password' => $password
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$apiUrl/access_token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $authData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$authResponse = curl_exec($ch);
$authData = json_decode($authResponse, true);
curl_close($ch);

if (!isset($authData['access_token'])) {
    echo "{$red}✗ Authentication failed{$reset}\n";
    exit(1);
}

$accessToken = $authData['access_token'];
echo "{$green}✓ Authentication successful{$reset}\n\n";

// Step 2: Test field names for Leads
echo "2. Testing Lead field names...\n";

// Test different email field names
$emailFieldTests = [
    'email' => 'test1@example.com',
    'email1' => 'test2@example.com',
    'email_address' => 'test3@example.com',
    'primary_email' => 'test4@example.com'
];

foreach ($emailFieldTests as $fieldName => $value) {
    echo "\n   Testing field: {$yellow}$fieldName{$reset}\n";
    
    $leadData = [
        'data' => [
            'type' => 'Leads',
            'attributes' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                $fieldName => $value,
                'status' => 'New'
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/vnd.api+json',
        'Accept: application/vnd.api+json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        echo "   {$green}✓ Field '$fieldName' accepted (201 Created){$reset}\n";
        
        // Get the created lead to see what fields are returned
        $responseData = json_decode($response, true);
        if (isset($responseData['data']['id'])) {
            $leadId = $responseData['data']['id'];
            
            // Fetch the lead to see field names
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module/Leads/$leadId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/vnd.api+json'
            ]);
            
            $getResponse = curl_exec($ch);
            curl_close($ch);
            
            $getData = json_decode($getResponse, true);
            if (isset($getData['data']['attributes'])) {
                echo "   Returned fields: ";
                $attrs = $getData['data']['attributes'];
                
                // Check which email fields exist
                $emailFields = array_filter(array_keys($attrs), function($key) {
                    return strpos($key, 'email') !== false;
                });
                
                if (!empty($emailFields)) {
                    echo implode(', ', $emailFields) . "\n";
                } else {
                    echo "No email fields found\n";
                }
            }
            
            // Clean up - delete the test lead
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module/Leads/$leadId");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } else {
        echo "   {$red}✗ Field '$fieldName' rejected (HTTP $httpCode){$reset}\n";
        $errorData = json_decode($response, true);
        if (isset($errorData['errors'])) {
            echo "   Error: " . json_encode($errorData['errors'][0]['detail'] ?? $errorData['errors']) . "\n";
        }
    }
}

// Step 3: Test update methods
echo "\n3. Testing update methods...\n";

// Create a lead for testing updates
$createData = [
    'data' => [
        'type' => 'Leads',
        'attributes' => [
            'first_name' => 'Update',
            'last_name' => 'Test',
            'email1' => 'update@test.com',
            'status' => 'New'
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/vnd.api+json',
    'Accept: application/vnd.api+json'
]);

$createResponse = curl_exec($ch);
curl_close($ch);

$createData = json_decode($createResponse, true);
if (isset($createData['data']['id'])) {
    $testLeadId = $createData['data']['id'];
    echo "{$green}✓ Created test lead: $testLeadId{$reset}\n";
    
    // Test PATCH to /module with ID in body
    echo "\n   Testing: PATCH /module (ID in body)\n";
    $updateData = [
        'data' => [
            'type' => 'Leads',
            'id' => $testLeadId,
            'attributes' => [
                'first_name' => 'Updated'
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/vnd.api+json',
        'Accept: application/vnd.api+json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   {$green}✓ PATCH /module works (200 OK){$reset}\n";
    } else {
        echo "   {$red}✗ PATCH /module failed (HTTP $httpCode){$reset}\n";
    }
    
    // Test PATCH to /module/Leads/{id}
    echo "\n   Testing: PATCH /module/Leads/{id}\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module/Leads/$testLeadId");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/vnd.api+json',
        'Accept: application/vnd.api+json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   {$green}✓ PATCH /module/Leads/{id} works (200 OK){$reset}\n";
    } else {
        echo "   {$red}✗ PATCH /module/Leads/{id} failed (HTTP $httpCode){$reset}\n";
        
        // Check allowed methods
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module/Leads/$testLeadId");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "OPTIONS");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (preg_match('/Allow: (.+)/i', $response, $matches)) {
            echo "   Allowed methods: {$yellow}" . trim($matches[1]) . "{$reset}\n";
        }
    }
    
    // Clean up
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module/Leads/$testLeadId");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Step 4: List all available fields for Leads
echo "\n4. Listing all Lead fields from a real record...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$apiUrl/V8/module/Leads?page[size]=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Accept: application/vnd.api+json'
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['data'][0]['attributes'])) {
    $fields = array_keys($data['data'][0]['attributes']);
    echo "Available fields:\n";
    foreach ($fields as $field) {
        $value = $data['data'][0]['attributes'][$field];
        if (strpos($field, 'email') !== false || strpos($field, 'phone') !== false || 
            strpos($field, 'name') !== false || strpos($field, 'status') !== false) {
            echo "   - {$yellow}$field{$reset}: " . 
                 (is_string($value) ? substr($value, 0, 50) : gettype($value)) . "\n";
        }
    }
    
    // Check for custom fields
    echo "\nChecking for AI custom fields:\n";
    $customFields = ['ai_score', 'ai_score_date', 'ai_insights', 'ai_score_c', 'ai_insights_c'];
    foreach ($customFields as $field) {
        if (isset($data['data'][0]['attributes'][$field])) {
            echo "   {$green}✓ Found: $field{$reset}\n";
        } else {
            echo "   {$red}✗ Not found: $field{$reset}\n";
        }
    }
}

echo "\n{$blue}=== Test Complete ==={$reset}\n";