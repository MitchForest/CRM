<?php
// Test status codes for POST endpoints

function testEndpoint($name, $method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    $status = $httpCode == 201 ? '✓' : '✗';
    
    echo "$status $name: HTTP $httpCode";
    if ($httpCode != 201) {
        echo " (expected 201)";
    }
    echo "\n";
    
    return ['code' => $httpCode, 'data' => $responseData];
}

// Get auth token
echo "Getting auth token...\n";
$ch = curl_init('http://localhost/custom/api/index.php/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'john.doe@example.com',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['data']['accessToken'] ?? null;
curl_close($ch);

if (!$token) {
    die("Failed to get auth token\n");
}

echo "\nTesting POST endpoints:\n";
echo "======================\n";

// Test POST /leads
$leadData = [
    'firstName' => 'Test',
    'lastName' => 'Lead_' . time(),
    'email' => 'test.lead.' . time() . '@example.com',
    'company' => 'Test Company',
    'status' => 'New'
];
testEndpoint('POST /leads', 'POST', 'http://localhost/custom/api/index.php/leads', $leadData, $token);

// Test POST /cases
$caseData = [
    'name' => 'Test Case ' . time(),
    'status' => 'Open',
    'priority' => 'Medium',
    'type' => 'Technical',
    'description' => 'Test case description'
];
testEndpoint('POST /cases', 'POST', 'http://localhost/custom/api/index.php/cases', $caseData, $token);

// Test POST /contacts
$contactData = [
    'firstName' => 'Test',
    'lastName' => 'Contact_' . time(),
    'email' => 'test.contact.' . time() . '@example.com',
    'type' => 'person'
];
testEndpoint('POST /contacts', 'POST', 'http://localhost/custom/api/index.php/contacts', $contactData, $token);

// Test POST /opportunities
$oppData = [
    'name' => 'Test Opportunity ' . time(),
    'amount' => 10000,
    'sales_stage' => 'Qualified',
    'date_closed' => date('Y-m-d', strtotime('+30 days'))
];
testEndpoint('POST /opportunities', 'POST', 'http://localhost/custom/api/index.php/opportunities', $oppData, $token);

// Test POST /activities
$activityData = [
    'type' => 'call',
    'name' => 'Test Call ' . time(),
    'status' => 'Planned',
    'date_start' => date('Y-m-d H:i:s', strtotime('+1 hour'))
];
testEndpoint('POST /activities', 'POST', 'http://localhost/custom/api/index.php/activities', $activityData, $token);

echo "\nDone!\n";