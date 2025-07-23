<?php
namespace Tests\Integration\Controllers;

use Api\DTO\LeadDTO;

/**
 * Integration tests for LeadsController
 */
class LeadsControllerTest extends \SuiteCRMIntegrationTest
{
    private $controller;
    private $auth;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create authenticated user
        $this->auth = $this->createAuthenticatedUser();
        
        // Create controller instance
        $this->controller = new \Api\Controllers\LeadsController();
    }
    
    /**
     * Test listing leads
     */
    public function testListLeads()
    {
        // Create test leads
        $lead1 = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Test',
            'last_name' => 'Lead One',
            'email1' => 'lead1@example.com',
            'status' => 'New',
            'lead_source' => 'Web Site'
        ]);
        
        $lead2 = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Test',
            'last_name' => 'Lead Two',
            'email1' => 'lead2@example.com',
            'status' => 'Assigned',
            'lead_source' => 'Email'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/leads', [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->list($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        
        // Verify leads are in response
        $leadIds = array_column($data['data'], 'id');
        $this->assertContains($lead1->id, $leadIds);
        $this->assertContains($lead2->id, $leadIds);
    }
    
    /**
     * Test creating a lead
     */
    public function testCreateLead()
    {
        $leadData = [
            'first_name' => 'New',
            'last_name' => 'Lead',
            'email1' => 'new.lead@example.com',
            'phone_mobile' => '+1234567890',
            'lead_source' => 'Cold Call',
            'status' => 'New',
            'title' => 'CEO',
            'department' => 'Executive',
            'description' => 'Interested in our product'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', '/leads', $leadData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Mock request body
        $request->body = json_encode($leadData);
        
        // Call controller method
        $this->controller->create($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New', $data['first_name']);
        $this->assertEquals('Lead', $data['last_name']);
        $this->assertEquals('new.lead@example.com', $data['email1']);
        $this->assertEquals('New', $data['status']);
        
        // Verify lead was created in database
        $lead = \BeanFactory::getBean('Leads', $data['id']);
        $this->assertNotNull($lead);
        $this->assertEquals('New', $lead->first_name);
        $this->assertEquals('CEO', $lead->title);
    }
    
    /**
     * Test converting lead to contact
     */
    public function testConvertLead()
    {
        // Create test lead
        $lead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Convert',
            'last_name' => 'Me',
            'email1' => 'convert.me@example.com',
            'phone_mobile' => '+1111111111',
            'status' => 'Qualified',
            'title' => 'Manager',
            'department' => 'Sales',
            'lead_source' => 'Web Site'
        ]);
        
        $convertData = [
            'create_opportunity' => true,
            'opportunity_name' => 'New Opportunity from Lead',
            'opportunity_amount' => 50000
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', "/leads/{$lead->id}/convert", $convertData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $lead->id];
        $request->body = json_encode($convertData);
        
        // Call controller method
        $this->controller->convert($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('contact_id', $data);
        $this->assertArrayHasKey('opportunity_id', $data);
        $this->assertEquals('Lead converted successfully', $data['message']);
        
        // Verify lead was converted
        $convertedLead = \BeanFactory::getBean('Leads', $lead->id);
        $this->assertEquals('Converted', $convertedLead->status);
        $this->assertNotEmpty($convertedLead->contact_id);
        
        // Verify contact was created
        $contact = \BeanFactory::getBean('Contacts', $data['contact_id']);
        $this->assertNotNull($contact);
        $this->assertEquals('Convert', $contact->first_name);
        $this->assertEquals('Me', $contact->last_name);
        $this->assertEquals('convert.me@example.com', $contact->email1);
        
        // Verify opportunity was created
        $opportunity = \BeanFactory::getBean('Opportunities', $data['opportunity_id']);
        $this->assertNotNull($opportunity);
        $this->assertEquals('New Opportunity from Lead', $opportunity->name);
        $this->assertEquals(50000, $opportunity->amount);
    }
    
    /**
     * Test updating a lead
     */
    public function testUpdateLead()
    {
        // Create test lead
        $lead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Original',
            'last_name' => 'Lead',
            'email1' => 'original@example.com',
            'status' => 'New'
        ]);
        
        $updateData = [
            'first_name' => 'Updated',
            'status' => 'Working',
            'description' => 'Updated description'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/leads/{$lead->id}", $updateData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $lead->id];
        $request->body = json_encode($updateData);
        
        // Call controller method
        $this->controller->update($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated', $data['first_name']);
        $this->assertEquals('Working', $data['status']);
        
        // Verify lead was updated in database
        $updatedLead = \BeanFactory::getBean('Leads', $lead->id);
        $this->assertEquals('Updated', $updatedLead->first_name);
        $this->assertEquals('Working', $updatedLead->status);
        $this->assertEquals('Updated description', $updatedLead->description);
    }
    
    /**
     * Test deleting a lead
     */
    public function testDeleteLead()
    {
        // Create test lead
        $lead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'email1' => 'delete.me@example.com'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('DELETE', "/leads/{$lead->id}", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $lead->id];
        
        // Call controller method
        $this->controller->delete($request, $response);
        
        // Assert response
        $this->assertEquals(204, $response->getStatusCode());
        
        // Verify lead was soft deleted
        $deletedLead = \BeanFactory::getBean('Leads', $lead->id);
        $this->assertEquals(1, $deletedLead->deleted);
    }
    
    /**
     * Test lead status filtering
     */
    public function testFilterLeadsByStatus()
    {
        // Create leads with different statuses
        $newLead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'New',
            'last_name' => 'Lead',
            'status' => 'New'
        ]);
        
        $workingLead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Working',
            'last_name' => 'Lead',
            'status' => 'Working'
        ]);
        
        $convertedLead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Converted',
            'last_name' => 'Lead',
            'status' => 'Converted'
        ]);
        
        // Filter for 'New' status
        $result = $this->makeApiRequest('GET', '/leads', ['status' => 'New'], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        $leadIds = array_column($data['data'], 'id');
        
        $this->assertContains($newLead->id, $leadIds);
        $this->assertNotContains($workingLead->id, $leadIds);
        $this->assertNotContains($convertedLead->id, $leadIds);
    }
    
    /**
     * Test lead DTO validation
     */
    public function testLeadDTOValidation()
    {
        $dto = new LeadDTO();
        
        // Test empty DTO fails validation
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('first_name', $errors);
        
        // Test valid DTO passes validation
        $dto->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john.doe@example.com')
            ->setStatus('New');
            
        $this->assertTrue($dto->validate());
        
        // Test invalid status
        $dto->setStatus('InvalidStatus');
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('status', $errors);
    }
    
    /**
     * Test lead source validation
     */
    public function testLeadSourceValidation()
    {
        $lead = \TestDatabaseHelper::createTestRecord('Leads', [
            'first_name' => 'Source',
            'last_name' => 'Test',
            'lead_source' => 'Web Site'
        ]);
        
        // Filter by lead source
        $result = $this->makeApiRequest('GET', '/leads', ['lead_source' => 'Web Site'], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify filtered results
        foreach ($data['data'] as $leadData) {
            if ($leadData['id'] === $lead->id) {
                $this->assertEquals('Web Site', $leadData['lead_source']);
            }
        }
    }
    
    /**
     * Test lead pagination
     */
    public function testLeadPagination()
    {
        // Create multiple leads
        for ($i = 1; $i <= 15; $i++) {
            \TestDatabaseHelper::createTestRecord('Leads', [
                'first_name' => 'Lead',
                'last_name' => "Number $i",
                'email1' => "lead$i@example.com"
            ]);
        }
        
        // Request first page
        $result = $this->makeApiRequest('GET', '/leads', ['page' => 1, 'pageSize' => 10], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(10, $data['data']);
        $this->assertEquals(1, $data['page']);
        $this->assertEquals(10, $data['pageSize']);
        $this->assertGreaterThanOrEqual(15, $data['total']);
    }
}