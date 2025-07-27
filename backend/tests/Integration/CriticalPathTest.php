<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class CriticalPathTest extends TestCase
{
    private $baseUrl = 'http://backend:80/api';
    private $token;
    private $createdLeadId;
    
    /**
     * Test the complete user journey through the API
     */
    public function testCompleteUserJourney()
    {
        echo "\n🚀 Starting Critical Path Tests...\n\n";
        
        // 1. Can I log in?
        $this->testLogin();
        
        // 2. Can I create a lead with the actual field names we use?
        $this->testCreateLead();
        
        // 3. Can I retrieve that lead and get the data back correctly?
        $this->testGetLead();
        
        // 4. Can I update the lead?
        $this->testUpdateLead();
        
        // 5. Can I see it in the list?
        $this->testListLeads();
        
        // 6. Can I convert it to a contact?
        $this->testConvertLead();
        
        // 7. Does the dashboard show correct metrics?
        $this->testDashboardMetrics();
        
        echo "\n✅ All critical paths working!\n";
    }
    
    private function testLogin()
    {
        $response = $this->apiCall('POST', '/auth/login', [
            'email' => 'testadmin@test.com',
            'password' => 'admin'
        ]);
        
        $this->assertArrayHasKey('access_token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('first_name', $response['user']);
        $this->assertArrayHasKey('last_name', $response['user']);
        $this->assertArrayHasKey('email1', $response['user']);
        
        $this->token = $response['access_token'];
        
        echo "✅ Login works - Got token and user with snake_case fields\n";
    }
    
    private function testCreateLead()
    {
        $response = $this->apiCall('POST', '/crm/leads', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email1' => 'john@example.com',
            'phone_work' => '555-1234',
            'account_name' => 'Acme Corp',
            'lead_source' => 'website',
            'status' => 'new'
        ]);
        
        $this->assertArrayHasKey('data', $response);
        $lead = $response['data'];
        
        $this->assertEquals('john@example.com', $lead['email1']);
        $this->assertEquals('555-1234', $lead['phone_work']);
        $this->assertEquals('Acme Corp', $lead['account_name']);
        $this->assertNotEmpty($lead['id']);
        
        $this->createdLeadId = $lead['id'];
        
        echo "✅ Lead creation works with correct snake_case field names\n";
    }
    
    private function testGetLead()
    {
        $response = $this->apiCall('GET', "/crm/leads/{$this->createdLeadId}");
        
        $this->assertArrayHasKey('data', $response);
        $lead = $response['data'];
        
        $this->assertEquals('John', $lead['first_name']);
        $this->assertEquals('Doe', $lead['last_name']);
        $this->assertEquals('555-1234', $lead['phone_work']);
        $this->assertEquals('john@example.com', $lead['email1']);
        $this->assertEquals('website', $lead['lead_source']);
        
        echo "✅ Lead retrieval works - All fields returned correctly\n";
    }
    
    private function testUpdateLead()
    {
        $response = $this->apiCall('PUT', "/crm/leads/{$this->createdLeadId}", [
            'status' => 'qualified',
            'phone_mobile' => '555-9999'
        ]);
        
        // Get updated lead
        $updated = $this->apiCall('GET', "/crm/leads/{$this->createdLeadId}");
        $lead = $updated['data'];
        
        $this->assertEquals('qualified', $lead['status']);
        $this->assertEquals('555-9999', $lead['phone_mobile']);
        
        // AI score would typically be set by the AI service, not directly
        // So we'll skip testing direct AI score updates
        
        echo "✅ Lead update works - Status updated\n";
    }
    
    private function testListLeads()
    {
        $response = $this->apiCall('GET', '/crm/leads?page=1&limit=10');
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertArrayHasKey('page', $response['pagination']);
        $this->assertArrayHasKey('limit', $response['pagination']);
        $this->assertArrayHasKey('total', $response['pagination']);
        
        $this->assertGreaterThan(0, count($response['data']));
        
        // Verify our lead is in the list
        $found = false;
        foreach ($response['data'] as $lead) {
            if ($lead['id'] === $this->createdLeadId) {
                $found = true;
                $this->assertEquals('john@example.com', $lead['email1']);
                break;
            }
        }
        $this->assertTrue($found, 'Created lead should be in list');
        
        echo "✅ Lead listing with pagination works\n";
    }
    
    private function testConvertLead()
    {
        $response = $this->apiCall('POST', "/crm/leads/{$this->createdLeadId}/convert", [
            'create_opportunity' => true,
            'opportunity_name' => 'New Deal from John Doe'
        ]);
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('contact_id', $response['data']);
        
        // Verify contact was created with correct fields
        $contact = $this->apiCall('GET', "/crm/contacts/{$response['data']['contact_id']}");
        $this->assertEquals('John', $contact['data']['first_name']);
        $this->assertEquals('Doe', $contact['data']['last_name']);
        $this->assertEquals('john@example.com', $contact['data']['email1']);
        $this->assertEquals('555-1234', $contact['data']['phone_work']);
        
        // Verify lead is marked as converted
        $lead = $this->apiCall('GET', "/crm/leads/{$this->createdLeadId}");
        $this->assertEquals('converted', $lead['data']['status']);
        
        echo "✅ Lead to contact conversion works\n";
    }
    
    private function testDashboardMetrics()
    {
        $response = $this->apiCall('GET', '/crm/dashboard/metrics');
        
        $this->assertArrayHasKey('data', $response);
        $metrics = $response['data'];
        
        $this->assertArrayHasKey('total_leads', $metrics);
        $this->assertArrayHasKey('total_contacts', $metrics);
        $this->assertArrayHasKey('total_opportunities', $metrics);
        $this->assertArrayHasKey('total_cases', $metrics);
        
        $this->assertGreaterThan(0, $metrics['total_leads']);
        $this->assertGreaterThan(0, $metrics['total_contacts']);
        
        echo "✅ Dashboard metrics work - Shows real data\n";
    }
    
    /**
     * Make API call with proper error handling
     */
    private function apiCall($method, $endpoint, $data = null)
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $headers = ['Content-Type: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("CURL Error: $error");
        }
        
        if ($httpCode >= 400) {
            throw new \Exception("API call failed: $method $endpoint returned $httpCode\nResponse: $response");
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response: $response");
        }
        
        return $decoded;
    }
}