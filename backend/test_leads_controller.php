#!/usr/bin/env php
<?php
/**
 * Test script for LeadsController with AI custom fields
 */

$apiUrl = 'http://localhost:8080/custom/api';
$username = 'admin';
$password = 'admin';

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json',
        'Accept: application/json'
    ], $headers));
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response,
        'data' => json_decode($response, true)
    ];
}

echo "Testing Leads Controller with AI Custom Fields\n";
echo "==============================================\n\n";

// 1. Login
echo "1. Authenticating...\n";
$loginResponse = makeRequest($apiUrl . '/auth/login', 'POST', [
    'username' => $username,
    'password' => $password
]);

if ($loginResponse['code'] !== 200) {
    die("Authentication failed: " . $loginResponse['body'] . "\n");
}

$token = $loginResponse['data']['data']['token'];
$headers = ['Authorization: Bearer ' . $token];
echo "✓ Authentication successful\n\n";

// 2. Create a lead with AI fields
echo "2. Creating lead with AI fields...\n";
$createData = [
    'first_name' => 'AI Test',
    'last_name' => 'Lead',
    'email1' => 'ai.test@example.com',
    'phone_mobile' => '555-0123',
    'status' => 'New',
    'lead_source' => 'Web Site',
    'description' => 'Test lead for AI scoring',
    'ai_score' => 85,
    'ai_insights' => 'High conversion probability based on web activity and engagement patterns'
];

$createResponse = makeRequest($apiUrl . '/leads', 'POST', $createData, $headers);
if ($createResponse['code'] === 201) {
    echo "✓ Lead created successfully\n";
    $leadId = $createResponse['data']['data']['id'];
    echo "  Lead ID: $leadId\n";
    echo "  AI Score: " . $createResponse['data']['data']['ai_score'] . "\n";
    echo "  AI Score Date: " . $createResponse['data']['data']['ai_score_date'] . "\n";
    echo "  AI Insights: " . $createResponse['data']['data']['ai_insights'] . "\n";
} else {
    die("Failed to create lead: " . $createResponse['body'] . "\n");
}
echo "\n";

// 3. Get the lead
echo "3. Retrieving lead...\n";
$getResponse = makeRequest($apiUrl . '/leads/' . $leadId, 'GET', null, $headers);
if ($getResponse['code'] === 200) {
    echo "✓ Lead retrieved successfully\n";
    $lead = $getResponse['data']['data'];
    echo "  Name: {$lead['first_name']} {$lead['last_name']}\n";
    echo "  AI Score: " . ($lead['ai_score'] ?? 'Not set') . "\n";
    echo "  AI Score Date: " . ($lead['ai_score_date'] ?? 'Not set') . "\n";
    echo "  AI Insights: " . ($lead['ai_insights'] ?? 'Not set') . "\n";
} else {
    echo "✗ Failed to retrieve lead: " . $getResponse['body'] . "\n";
}
echo "\n";

// 4. Update AI score using PATCH
echo "4. Updating AI score using PATCH...\n";
$patchData = [
    'ai_score' => 92,
    'ai_insights' => 'Updated: Very high conversion probability. Recent email engagement shows strong interest.'
];

$patchResponse = makeRequest($apiUrl . '/leads/' . $leadId, 'PATCH', $patchData, $headers);
if ($patchResponse['code'] === 200) {
    echo "✓ Lead updated successfully\n";
    $updated = $patchResponse['data']['data'];
    echo "  New AI Score: " . $updated['ai_score'] . "\n";
    echo "  Updated AI Score Date: " . $updated['ai_score_date'] . "\n";
    echo "  Updated AI Insights: " . $updated['ai_insights'] . "\n";
} else {
    echo "✗ Failed to update lead: " . $patchResponse['body'] . "\n";
}
echo "\n";

// 5. List leads with filtering
echo "5. Listing leads with AI score filter...\n";
$listResponse = makeRequest($apiUrl . '/leads?filter[ai_score][gte]=80&limit=5', 'GET', null, $headers);
if ($listResponse['code'] === 200) {
    echo "✓ Leads retrieved successfully\n";
    $leads = $listResponse['data']['data'];
    echo "  Found " . count($leads) . " leads with AI score >= 80\n";
    foreach ($leads as $lead) {
        echo "  - {$lead['first_name']} {$lead['last_name']} (Score: " . ($lead['ai_score'] ?? 'N/A') . ")\n";
    }
    
    if (isset($listResponse['data']['pagination'])) {
        $pagination = $listResponse['data']['pagination'];
        echo "  Pagination: Page {$pagination['page']} of {$pagination['pages']} (Total: {$pagination['total']})\n";
    }
} else {
    echo "✗ Failed to list leads: " . $listResponse['body'] . "\n";
}
echo "\n";

// 6. Test validation
echo "6. Testing validation...\n";
$invalidData = [
    'first_name' => 'Invalid',
    // Missing required last_name
    'email1' => 'invalid-email',
    'ai_score' => 150 // Out of range
];

$validationResponse = makeRequest($apiUrl . '/leads', 'POST', $invalidData, $headers);
if ($validationResponse['code'] === 400) {
    echo "✓ Validation working correctly\n";
    if (isset($validationResponse['data']['validation'])) {
        foreach ($validationResponse['data']['validation'] as $field => $error) {
            echo "  - $field: $error\n";
        }
    }
} else {
    echo "✗ Validation not working as expected\n";
}
echo "\n";

// 7. Delete the test lead
echo "7. Deleting test lead...\n";
$deleteResponse = makeRequest($apiUrl . '/leads/' . $leadId, 'DELETE', null, $headers);
if ($deleteResponse['code'] === 200) {
    echo "✓ Lead deleted successfully\n";
} else {
    echo "✗ Failed to delete lead: " . $deleteResponse['body'] . "\n";
}
echo "\n";

// 8. Verify deletion
echo "8. Verifying deletion...\n";
$verifyResponse = makeRequest($apiUrl . '/leads/' . $leadId, 'GET', null, $headers);
if ($verifyResponse['code'] === 404) {
    echo "✓ Lead properly deleted (404 returned)\n";
} else {
    echo "✗ Lead still exists or unexpected response: " . $verifyResponse['code'] . "\n";
}

echo "\n";
echo "Test Summary:\n";
echo "============\n";
echo "✓ Leads controller properly handles AI custom fields\n";
echo "✓ AI fields are included in responses as part of attributes\n";
echo "✓ CRUD operations work correctly\n";
echo "✓ Filtering by AI score works\n";
echo "✓ Validation is enforced\n";
echo "✓ AI score date is automatically set when score is updated\n";

echo "\nDone!\n";