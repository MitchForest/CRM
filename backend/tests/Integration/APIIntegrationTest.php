<?php
// Comprehensive test script to verify all API operations after integration fixes

$baseUrl = 'http://localhost:8080/Api/V8';
$username = 'apiuser';
$password = 'apiuser123';

// Color codes for output
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

// Test results tracking
$testResults = [];
$totalTests = 0;
$passedTests = 0;

// Function to get OAuth2 token
function getToken($username, $password) {
    $tokenUrl = 'http://localhost:8080/Api/access_token';
    
    $data = [
        'grant_type' => 'password',
        'client_id' => 'suitecrm_client',
        'client_secret' => 'secret123',
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

// Test function
function runTest($testName, $testFunc) {
    global $totalTests, $passedTests, $green, $red, $reset;
    
    $totalTests++;
    echo "\n{$testName}: ";
    
    try {
        $result = $testFunc();
        if ($result) {
            echo "{$green}✓ PASSED{$reset}\n";
            $passedTests++;
            return true;
        } else {
            echo "{$red}✗ FAILED{$reset}\n";
            return false;
        }
    } catch (Exception $e) {
        echo "{$red}✗ FAILED - {$e->getMessage()}{$reset}\n";
        return false;
    }
}

echo "=== SuiteCRM v8 API Comprehensive Integration Test ===\n";
echo "Testing all CRUD operations after integration fixes\n\n";

// Get token
echo "Authenticating...\n";
$token = getToken($username, $password);
if (!$token) {
    die("{$red}Failed to authenticate. Cannot proceed with tests.{$reset}\n");
}
echo "{$green}✓ Authentication successful{$reset}\n";

// Test variables
$testLeadId = null;
$testAccountId = null;

echo "\n{$blue}=== LEADS MODULE TESTS ==={$reset}\n";

// Test 1: Create Lead with email field (should map to email1)
runTest("Create Lead with email field", function() use ($baseUrl, $token, &$testLeadId) {
    $leadData = [
        'data' => [
            'type' => 'Leads',
            'attributes' => [
                'first_name' => 'Integration',
                'last_name' => 'Test Lead',
                'email1' => 'integration.test@example.com',
                'phone_work' => '555-1234',
                'status' => 'New',
                'lead_source' => 'Website',
                'description' => 'Created by integration test'
            ]
        ]
    ];
    
    $result = apiRequest('POST', $baseUrl . '/module', $leadData, $token);
    
    if ($result['code'] === 201 || $result['code'] === 200) {
        $testLeadId = $result['response']['data']['id'] ?? null;
        return !empty($testLeadId);
    }
    
    echo "  Response code: {$result['code']}\n";
    echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    return false;
});

// Test 2: Read Lead and verify fields
runTest("Read Lead and verify email field", function() use ($baseUrl, $token, $testLeadId) {
    if (!$testLeadId) return false;
    
    $result = apiRequest('GET', $baseUrl . '/module/Leads/' . $testLeadId, null, $token);
    
    if ($result['code'] === 200) {
        $data = $result['response']['data']['attributes'] ?? [];
        
        // Check if email is returned as email1
        $emailCorrect = isset($data['email1']) && $data['email1'] === 'integration.test@example.com';
        $firstNameCorrect = isset($data['first_name']) && $data['first_name'] === 'Integration';
        
        // Check for custom fields
        $hasAiScore = array_key_exists('ai_score', $data);
        $hasAiInsights = array_key_exists('ai_insights', $data);
        $hasAiScoreDate = array_key_exists('ai_score_date', $data);
        
        echo "  Email field: " . ($emailCorrect ? "✓" : "✗") . "\n";
        echo "  First name: " . ($firstNameCorrect ? "✓" : "✗") . "\n";
        echo "  Custom AI fields present: " . ($hasAiScore && $hasAiInsights && $hasAiScoreDate ? "✓" : "✗") . "\n";
        
        return $emailCorrect && $firstNameCorrect;
    }
    
    return false;
});

// Test 3: Update Lead using PATCH /module
runTest("Update Lead using PATCH /module", function() use ($baseUrl, $token, $testLeadId) {
    if (!$testLeadId) return false;
    
    $updateData = [
        'data' => [
            'type' => 'Leads',
            'id' => $testLeadId,
            'attributes' => [
                'first_name' => 'Updated',
                'status' => 'Assigned',
                'description' => 'Updated by integration test at ' . date('Y-m-d H:i:s')
            ]
        ]
    ];
    
    $result = apiRequest('PATCH', $baseUrl . '/module', $updateData, $token);
    
    if ($result['code'] === 200) {
        // Verify the update by reading the lead again
        $verifyResult = apiRequest('GET', $baseUrl . '/module/Leads/' . $testLeadId, null, $token);
        if ($verifyResult['code'] === 200) {
            $data = $verifyResult['response']['data']['attributes'] ?? [];
            $updated = $data['first_name'] === 'Updated' && 
                      $data['status'] === 'Assigned' &&
                      strpos($data['description'], 'Updated by integration test') !== false;
            
            echo "  Update verified: " . ($updated ? "✓" : "✗") . "\n";
            return $updated;
        }
    }
    
    echo "  Response code: {$result['code']}\n";
    echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    return false;
});

// Test 4: List Leads with pagination
runTest("List Leads with pagination", function() use ($baseUrl, $token) {
    $result = apiRequest('GET', $baseUrl . '/module/Leads?page[number]=1&page[size]=5', null, $token);
    
    if ($result['code'] === 200) {
        $hasData = isset($result['response']['data']) && is_array($result['response']['data']);
        $hasMeta = isset($result['response']['meta']);
        
        echo "  Has data array: " . ($hasData ? "✓" : "✗") . "\n";
        echo "  Has pagination meta: " . ($hasMeta ? "✓" : "✗") . "\n";
        
        return $hasData && $hasMeta;
    }
    
    return false;
});

// Test 5: Search Leads
runTest("Search Leads by name", function() use ($baseUrl, $token) {
    $searchUrl = $baseUrl . '/module/Leads?filter[first_name][like]=%Updated%';
    $result = apiRequest('GET', $searchUrl, null, $token);
    
    return $result['code'] === 200 && isset($result['response']['data']);
});

echo "\n{$blue}=== ACCOUNTS MODULE TESTS ==={$reset}\n";

// Test 6: Create Account
runTest("Create Account", function() use ($baseUrl, $token, &$testAccountId) {
    $accountData = [
        'data' => [
            'type' => 'Accounts',
            'attributes' => [
                'name' => 'Integration Test Account',
                'industry' => 'Technology',
                'website' => 'https://test.example.com',
                'phone_office' => '555-5678',
                'billing_address_city' => 'Test City',
                'billing_address_country' => 'USA',
                'description' => 'Created by integration test'
            ]
        ]
    ];
    
    $result = apiRequest('POST', $baseUrl . '/module', $accountData, $token);
    
    if ($result['code'] === 201 || $result['code'] === 200) {
        $testAccountId = $result['response']['data']['id'] ?? null;
        return !empty($testAccountId);
    }
    
    echo "  Response code: {$result['code']}\n";
    return false;
});

// Test 7: Read Account and check custom fields
runTest("Read Account with custom fields", function() use ($baseUrl, $token, $testAccountId) {
    if (!$testAccountId) return false;
    
    $result = apiRequest('GET', $baseUrl . '/module/Accounts/' . $testAccountId, null, $token);
    
    if ($result['code'] === 200) {
        $data = $result['response']['data']['attributes'] ?? [];
        
        // Check standard fields
        $nameCorrect = isset($data['name']) && $data['name'] === 'Integration Test Account';
        
        // Check for custom fields
        $hasHealthScore = array_key_exists('health_score', $data);
        $hasMrr = array_key_exists('mrr', $data);
        $hasLastActivity = array_key_exists('last_activity', $data);
        
        echo "  Name field: " . ($nameCorrect ? "✓" : "✗") . "\n";
        echo "  Custom fields present: " . ($hasHealthScore && $hasMrr && $hasLastActivity ? "✓" : "✗") . "\n";
        
        return $nameCorrect;
    }
    
    return false;
});

// Test 8: Update Account
runTest("Update Account using PATCH", function() use ($baseUrl, $token, $testAccountId) {
    if (!$testAccountId) return false;
    
    $updateData = [
        'data' => [
            'type' => 'Accounts',
            'id' => $testAccountId,
            'attributes' => [
                'name' => 'Updated Test Account',
                'industry' => 'Finance',
                'annual_revenue' => '1000000'
            ]
        ]
    ];
    
    $result = apiRequest('PATCH', $baseUrl . '/module', $updateData, $token);
    
    if ($result['code'] === 200) {
        // Verify the update
        $verifyResult = apiRequest('GET', $baseUrl . '/module/Accounts/' . $testAccountId, null, $token);
        if ($verifyResult['code'] === 200) {
            $data = $verifyResult['response']['data']['attributes'] ?? [];
            $updated = $data['name'] === 'Updated Test Account' && 
                      $data['industry'] === 'Finance';
            
            echo "  Update verified: " . ($updated ? "✓" : "✗") . "\n";
            return $updated;
        }
    }
    
    echo "  Response code: {$result['code']}\n";
    return false;
});

echo "\n{$blue}=== CLEANUP ==={$reset}\n";

// Test 9: Delete Lead
runTest("Delete Lead", function() use ($baseUrl, $token, $testLeadId) {
    if (!$testLeadId) return false;
    
    $result = apiRequest('DELETE', $baseUrl . '/module/Leads/' . $testLeadId, null, $token);
    
    if ($result['code'] === 200 || $result['code'] === 204) {
        // Verify deletion
        $verifyResult = apiRequest('GET', $baseUrl . '/module/Leads/' . $testLeadId, null, $token);
        return $verifyResult['code'] === 404;
    }
    
    return false;
});

// Test 10: Delete Account
runTest("Delete Account", function() use ($baseUrl, $token, $testAccountId) {
    if (!$testAccountId) return false;
    
    $result = apiRequest('DELETE', $baseUrl . '/module/Accounts/' . $testAccountId, null, $token);
    
    if ($result['code'] === 200 || $result['code'] === 204) {
        // Verify deletion
        $verifyResult = apiRequest('GET', $baseUrl . '/module/Accounts/' . $testAccountId, null, $token);
        return $verifyResult['code'] === 404;
    }
    
    return false;
});

// Summary
echo "\n{$blue}=== TEST SUMMARY ==={$reset}\n";
echo "Total tests: {$totalTests}\n";
echo "Passed: {$green}{$passedTests}{$reset}\n";
echo "Failed: {$red}" . ($totalTests - $passedTests) . "{$reset}\n";
echo "Success rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";

if ($passedTests === $totalTests) {
    echo "\n{$green}✓ ALL TESTS PASSED! The API integration is working correctly.{$reset}\n";
} else {
    echo "\n{$red}✗ Some tests failed. Please review the output above.{$reset}\n";
}

echo "\n=== Test Complete ===\n";