<?php
namespace Tests\Integration\Controllers;

use Api\DTO\CaseDTO;

/**
 * Integration tests for CasesController
 */
class CasesControllerTest extends \SuiteCRMIntegrationTest
{
    private $controller;
    private $auth;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create authenticated user
        $this->auth = $this->createAuthenticatedUser();
        
        // Create controller instance
        $this->controller = new \Api\Controllers\CasesController();
    }
    
    /**
     * Test listing cases
     */
    public function testListCases()
    {
        // Create test contact for relationship
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Support',
            'last_name' => 'Customer'
        ]);
        
        // Create test cases
        $case1 = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Login issue',
            'status' => 'New',
            'priority' => 'P2',
            'type' => 'Bug',
            'description' => 'Customer cannot login',
            'contact_id' => $contact->id
        ]);
        
        $case2 = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Feature request',
            'status' => 'Assigned',
            'priority' => 'P3',
            'type' => 'Feature Request',
            'description' => 'Add dark mode'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/cases', [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->list($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        
        // Verify cases are in response
        $caseIds = array_column($data['data'], 'id');
        $this->assertContains($case1->id, $caseIds);
        $this->assertContains($case2->id, $caseIds);
        
        // Verify contact relationship is loaded
        foreach ($data['data'] as $caseData) {
            if ($caseData['id'] === $case1->id) {
                $this->assertEquals($contact->id, $caseData['contact_id']);
            }
        }
    }
    
    /**
     * Test creating a case
     */
    public function testCreateCase()
    {
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        $caseData = [
            'name' => 'Cannot export data',
            'status' => 'New',
            'priority' => 'P1',
            'type' => 'Bug',
            'description' => 'Export function returns error 500',
            'contact_id' => $contact->id,
            'source' => 'Email',
            'resolution' => ''
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', '/cases', $caseData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Mock request body
        $request->body = json_encode($caseData);
        
        // Call controller method
        $this->controller->create($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Cannot export data', $data['name']);
        $this->assertEquals('New', $data['status']);
        $this->assertEquals('P1', $data['priority']);
        $this->assertEquals('Open', $data['state']); // Auto-set based on status
        
        // Verify case was created in database
        $case = \BeanFactory::getBean('Cases', $data['id']);
        $this->assertNotNull($case);
        $this->assertEquals('Cannot export data', $case->name);
        $this->assertEquals($contact->id, $case->contact_id);
        $this->assertNotEmpty($case->case_number);
    }
    
    /**
     * Test adding case updates
     */
    public function testAddCaseUpdate()
    {
        // Create test case
        $case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Test case',
            'status' => 'Assigned',
            'priority' => 'P2'
        ]);
        
        $updateData = [
            'update' => 'I have reproduced the issue and found the root cause.',
            'internal' => false
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', "/cases/{$case->id}/updates", $updateData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $case->id];
        $request->body = json_encode($updateData);
        
        // Call controller method
        $this->controller->addUpdate($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Update added successfully', $data['message']);
        $this->assertArrayHasKey('case', $data);
        
        // Verify update was added
        $updatedCase = \BeanFactory::getBean('Cases', $case->id);
        $this->assertStringContainsString('I have reproduced the issue', $updatedCase->work_log);
    }
    
    /**
     * Test updating a case
     */
    public function testUpdateCase()
    {
        // Create test case
        $case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Original issue',
            'status' => 'New',
            'priority' => 'P3',
            'type' => 'Question'
        ]);
        
        $updateData = [
            'name' => 'Updated issue',
            'status' => 'Pending Input',
            'priority' => 'P2',
            'resolution' => 'Waiting for customer response'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/cases/{$case->id}", $updateData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $case->id];
        $request->body = json_encode($updateData);
        
        // Call controller method
        $this->controller->update($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated issue', $data['name']);
        $this->assertEquals('Pending Input', $data['status']);
        $this->assertEquals('P2', $data['priority']);
        
        // Verify case was updated in database
        $updatedCase = \BeanFactory::getBean('Cases', $case->id);
        $this->assertEquals('Updated issue', $updatedCase->name);
        $this->assertEquals('Pending Input', $updatedCase->status);
        $this->assertEquals('Waiting for customer response', $updatedCase->resolution);
    }
    
    /**
     * Test closing a case
     */
    public function testCloseCase()
    {
        // Create test case
        $case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Resolved issue',
            'status' => 'Assigned',
            'priority' => 'P2',
            'state' => 'Open'
        ]);
        
        $closeData = [
            'status' => 'Closed',
            'resolution' => 'Issue resolved by updating configuration'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/cases/{$case->id}", $closeData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $case->id];
        $request->body = json_encode($closeData);
        
        // Call controller method
        $this->controller->update($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Closed', $data['status']);
        $this->assertEquals('Closed', $data['state']); // Auto-updated based on status
        
        // Verify case was closed in database
        $closedCase = \BeanFactory::getBean('Cases', $case->id);
        $this->assertEquals('Closed', $closedCase->status);
        $this->assertEquals('Closed', $closedCase->state);
        $this->assertEquals('Issue resolved by updating configuration', $closedCase->resolution);
    }
    
    /**
     * Test deleting a case
     */
    public function testDeleteCase()
    {
        // Create test case
        $case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Delete me',
            'status' => 'New'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('DELETE', "/cases/{$case->id}", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $case->id];
        
        // Call controller method
        $this->controller->delete($request, $response);
        
        // Assert response
        $this->assertEquals(204, $response->getStatusCode());
        
        // Verify case was soft deleted
        $deletedCase = \BeanFactory::getBean('Cases', $case->id);
        $this->assertEquals(1, $deletedCase->deleted);
    }
    
    /**
     * Test case DTO validation
     */
    public function testCaseDTOValidation()
    {
        $dto = new CaseDTO();
        
        // Test empty DTO fails validation
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('name', $errors);
        
        // Test valid DTO passes validation
        $dto->setName('Test Case')
            ->setStatus('New')
            ->setPriority('P2')
            ->setType('Bug');
            
        $this->assertTrue($dto->validate());
        
        // Test invalid status
        $dto->setStatus('Invalid Status');
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('status', $errors);
        
        // Test closing without resolution
        $dto->setStatus('Closed');
        $dto->setState('Closed');
        $dto->setResolution(null);
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('resolution', $errors);
    }
    
    /**
     * Test filtering cases by status
     */
    public function testFilterCasesByStatus()
    {
        // Create cases with different statuses
        $newCase = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'New case',
            'status' => 'New'
        ]);
        
        $assignedCase = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Assigned case',
            'status' => 'Assigned'
        ]);
        
        $closedCase = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Closed case',
            'status' => 'Closed'
        ]);
        
        // Filter for 'New' status
        $result = $this->makeApiRequest('GET', '/cases', ['status' => 'New'], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        $caseIds = array_column($data['data'], 'id');
        
        $this->assertContains($newCase->id, $caseIds);
        $this->assertNotContains($assignedCase->id, $caseIds);
        $this->assertNotContains($closedCase->id, $caseIds);
    }
    
    /**
     * Test filtering cases by priority
     */
    public function testFilterCasesByPriority()
    {
        // Create cases with different priorities
        $p1Case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Critical issue',
            'priority' => 'P1'
        ]);
        
        $p2Case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Important issue',
            'priority' => 'P2'
        ]);
        
        $p3Case = \TestDatabaseHelper::createTestRecord('Cases', [
            'name' => 'Minor issue',
            'priority' => 'P3'
        ]);
        
        // Filter for P1 priority
        $result = $this->makeApiRequest('GET', '/cases', ['priority' => 'P1'], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        $caseIds = array_column($data['data'], 'id');
        
        $this->assertContains($p1Case->id, $caseIds);
        $this->assertNotContains($p2Case->id, $caseIds);
        $this->assertNotContains($p3Case->id, $caseIds);
    }
    
    /**
     * Test auto state update based on status
     */
    public function testAutoStateUpdate()
    {
        $dto = new CaseDTO();
        $dto->setName('Test Case')
            ->setPriority('P2')
            ->setType('Bug');
        
        // Test open statuses set state to Open
        $openStatuses = ['New', 'Assigned', 'Pending Input'];
        foreach ($openStatuses as $status) {
            $dto->setStatus($status);
            $this->assertEquals('Open', $dto->getState(), "Status $status should set state to Open");
        }
        
        // Test closed statuses set state to Closed
        $closedStatuses = ['Closed', 'Rejected', 'Duplicate'];
        foreach ($closedStatuses as $status) {
            $dto->setStatus($status);
            $this->assertEquals('Closed', $dto->getState(), "Status $status should set state to Closed");
        }
    }
}