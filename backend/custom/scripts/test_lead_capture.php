<?php
// Test lead capture and conversion flow

function testPublicFormSubmission() {
    echo "Testing public form submission...\n";
    
    // Get form ID from database
    $formId = 'form-contact-demo';  // From seed data
    
    // Submit form data (public endpoint, no auth required)
    $ch = curl_init("http://localhost/custom/api/index.php/forms/{$formId}/submit");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'name' => 'Test Lead ' . time(),
        'email' => 'testlead' . time() . '@example.com',
        'company' => 'Test Company Inc',
        'phone' => '555-0123',
        'message' => 'I am interested in your product demo'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Response code: $httpCode\n";
    $data = json_decode($response, true);
    
    if ($httpCode == 200 || $httpCode == 201) {
        echo "✓ Form submitted successfully\n";
        if (isset($data['data']['submission_id'])) {
            echo "  Submission ID: " . $data['data']['submission_id'] . "\n";
            return $data['data']['submission_id'];
        }
    } else {
        echo "✗ Form submission failed\n";
        echo "  Response: " . json_encode($data) . "\n";
    }
    
    return null;
}

function testPublicLeadForm() {
    echo "\nTesting public lead form endpoint...\n";
    
    $ch = curl_init("http://localhost/custom/api/index.php/public/lead-form");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'firstName' => 'Public',
        'lastName' => 'Lead ' . time(),
        'email' => 'publiclead' . time() . '@example.com',
        'company' => 'Public Company LLC',
        'phone' => '555-9876',
        'message' => 'Lead from public form',
        'source' => 'website_form'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Response code: $httpCode\n";
    $data = json_decode($response, true);
    
    if ($httpCode == 200 || $httpCode == 201) {
        echo "✓ Lead created successfully\n";
        if (isset($data['data']['id'])) {
            echo "  Lead ID: " . $data['data']['id'] . "\n";
            return $data['data']['id'];
        }
    } else {
        echo "✗ Lead creation failed\n";
        echo "  Response: " . json_encode($data) . "\n";
    }
    
    return null;
}

function testLeadConversion($token, $leadId) {
    echo "\nTesting lead conversion...\n";
    
    // First, get the lead details
    $ch = curl_init("http://localhost/custom/api/index.php/leads/{$leadId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $leadData = json_decode($response, true);
    curl_close($ch);
    
    if (!isset($leadData['data'])) {
        echo "✗ Failed to get lead data\n";
        return;
    }
    
    echo "  Lead: {$leadData['data']['first_name']} {$leadData['data']['last_name']}\n";
    
    // Convert lead to contact and opportunity
    // This would typically be done through a specific conversion endpoint
    // For now, we'll create the contact and opportunity manually
    
    // Create contact from lead
    $ch = curl_init("http://localhost/custom/api/index.php/contacts");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'firstName' => $leadData['data']['first_name'],
        'lastName' => $leadData['data']['last_name'],
        'email' => $leadData['data']['email1'],
        'phone' => $leadData['data']['phone_mobile'],
        'type' => 'person',
        'leadId' => $leadId  // Reference to original lead
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contactData = json_decode($response, true);
    curl_close($ch);
    
    if ($httpCode == 201 && isset($contactData['data']['id'])) {
        echo "✓ Contact created: {$contactData['data']['id']}\n";
        
        // Create opportunity
        $ch = curl_init("http://localhost/custom/api/index.php/opportunities");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'name' => 'Opportunity for ' . $leadData['data']['first_name'] . ' ' . $leadData['data']['last_name'],
            'amount' => 50000,
            'salesStage' => 'Qualified',
            'closeDate' => date('Y-m-d', strtotime('+30 days')),
            'contactIds' => [$contactData['data']['id']],
            'leadSource' => 'Website',
            'description' => 'Converted from lead #' . $leadId
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $oppData = json_decode($response, true);
        curl_close($ch);
        
        if ($httpCode == 201 && isset($oppData['data']['id'])) {
            echo "✓ Opportunity created: {$oppData['data']['id']}\n";
            
            // Update lead as converted
            $ch = curl_init("http://localhost/custom/api/index.php/leads/{$leadId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'converted' => 1,
                'converted_contact_id' => $contactData['data']['id'],
                'converted_opportunity_id' => $oppData['data']['id'],
                'status' => 'Converted'
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                echo "✓ Lead marked as converted\n";
            } else {
                echo "✗ Failed to mark lead as converted\n";
            }
        } else {
            echo "✗ Failed to create opportunity\n";
        }
    } else {
        echo "✗ Failed to create contact\n";
    }
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

echo "✓ Got auth token\n\n";

// Test 1: Public form submission
$submissionId = testPublicFormSubmission();

// Test 2: Public lead form
$leadId = testPublicLeadForm();

// Test 3: Lead conversion (if we got a lead ID)
if ($leadId) {
    testLeadConversion($token, $leadId);
}

echo "\nLead capture flow test complete!\n";