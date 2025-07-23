<?php
namespace Tests\Integration\Controllers;

use Api\DTO\OpportunityDTO;

/**
 * Integration tests for OpportunitiesController
 */
class OpportunitiesControllerTest extends \SuiteCRMIntegrationTest
{
    private $controller;
    private $auth;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create authenticated user
        $this->auth = $this->createAuthenticatedUser();
        
        // Create controller instance
        $this->controller = new \Api\Controllers\OpportunitiesController();
    }
    
    /**
     * Test listing opportunities
     */
    public function testListOpportunities()
    {
        // Create test contact for relationship
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Test',
            'last_name' => 'Contact'
        ]);
        
        // Create test opportunities
        $opp1 = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Big Deal',
            'amount' => 100000,
            'sales_stage' => 'Prospecting',
            'probability' => 10,
            'date_closed' => date('Y-m-d', strtotime('+30 days'))
        ]);
        
        // Link contact to opportunity
        $opp1->load_relationship('contacts');
        $opp1->contacts->add($contact->id);
        
        $opp2 = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Medium Deal',
            'amount' => 50000,
            'sales_stage' => 'Negotiation',
            'probability' => 70,
            'date_closed' => date('Y-m-d', strtotime('+15 days'))
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/opportunities', [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->list($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        
        // Verify opportunities are in response
        $oppIds = array_column($data['data'], 'id');
        $this->assertContains($opp1->id, $oppIds);
        $this->assertContains($opp2->id, $oppIds);
        
        // Verify contact relationship is loaded
        foreach ($data['data'] as $oppData) {
            if ($oppData['id'] === $opp1->id) {
                $this->assertNotEmpty($oppData['contact_name']);
            }
        }
    }
    
    /**
     * Test creating an opportunity
     */
    public function testCreateOpportunity()
    {
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'John',
            'last_name' => 'Customer'
        ]);
        
        $oppData = [
            'name' => 'New Business Opportunity',
            'amount' => 75000,
            'sales_stage' => 'Qualification',
            'probability' => 20,
            'date_closed' => date('Y-m-d', strtotime('+60 days')),
            'lead_source' => 'Web Site',
            'opportunity_type' => 'New Business',
            'description' => 'Great opportunity for Q4',
            'contact_id' => $contact->id
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', '/opportunities', $oppData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Mock request body
        $request->body = json_encode($oppData);
        
        // Call controller method
        $this->controller->create($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Business Opportunity', $data['name']);
        $this->assertEquals(75000, $data['amount']);
        $this->assertEquals('Qualification', $data['sales_stage']);
        $this->assertEquals(20, $data['probability']);
        
        // Verify opportunity was created in database
        $opp = \BeanFactory::getBean('Opportunities', $data['id']);
        $this->assertNotNull($opp);
        $this->assertEquals('New Business Opportunity', $opp->name);
        $this->assertEquals(75000, $opp->amount);
        
        // Verify contact relationship
        $opp->load_relationship('contacts');
        $contacts = $opp->contacts->getBeans();
        $this->assertCount(1, $contacts);
        $this->assertEquals($contact->id, array_key_first($contacts));
    }
    
    /**
     * Test AI analysis endpoint
     */
    public function testAnalyzeOpportunity()
    {
        // Create test opportunity
        $opp = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Enterprise Deal',
            'amount' => 250000,
            'sales_stage' => 'Proposal',
            'probability' => 60,
            'date_closed' => date('Y-m-d', strtotime('+45 days')),
            'description' => 'Large enterprise customer interested in our solution'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('POST', "/opportunities/{$opp->id}/analyze", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $opp->id];
        
        // Call controller method
        $this->controller->analyze($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('opportunity', $data);
        $this->assertArrayHasKey('analysis', $data);
        
        // Verify analysis fields
        $analysis = $data['analysis'];
        $this->assertArrayHasKey('win_probability', $analysis);
        $this->assertArrayHasKey('risk_factors', $analysis);
        $this->assertArrayHasKey('recommendations', $analysis);
        $this->assertArrayHasKey('next_best_actions', $analysis);
        
        // Verify opportunity was updated with AI fields
        $updatedOpp = \BeanFactory::getBean('Opportunities', $opp->id);
        $this->assertNotEmpty($updatedOpp->ai_win_probability);
        $this->assertNotEmpty($updatedOpp->ai_recommendations);
    }
    
    /**
     * Test updating an opportunity
     */
    public function testUpdateOpportunity()
    {
        // Create test opportunity
        $opp = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Original Deal',
            'amount' => 50000,
            'sales_stage' => 'Prospecting',
            'probability' => 10
        ]);
        
        $updateData = [
            'name' => 'Updated Deal',
            'amount' => 80000,
            'sales_stage' => 'Negotiation',
            'probability' => 70,
            'next_step' => 'Send final proposal'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/opportunities/{$opp->id}", $updateData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $opp->id];
        $request->body = json_encode($updateData);
        
        // Call controller method
        $this->controller->update($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated Deal', $data['name']);
        $this->assertEquals(80000, $data['amount']);
        $this->assertEquals('Negotiation', $data['sales_stage']);
        $this->assertEquals(70, $data['probability']);
        
        // Verify opportunity was updated in database
        $updatedOpp = \BeanFactory::getBean('Opportunities', $opp->id);
        $this->assertEquals('Updated Deal', $updatedOpp->name);
        $this->assertEquals(80000, $updatedOpp->amount);
        $this->assertEquals('Send final proposal', $updatedOpp->next_step);
    }
    
    /**
     * Test deleting an opportunity
     */
    public function testDeleteOpportunity()
    {
        // Create test opportunity
        $opp = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Delete Me Deal',
            'amount' => 25000
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('DELETE', "/opportunities/{$opp->id}", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $opp->id];
        
        // Call controller method
        $this->controller->delete($request, $response);
        
        // Assert response
        $this->assertEquals(204, $response->getStatusCode());
        
        // Verify opportunity was soft deleted
        $deletedOpp = \BeanFactory::getBean('Opportunities', $opp->id);
        $this->assertEquals(1, $deletedOpp->deleted);
    }
    
    /**
     * Test opportunity DTO validation
     */
    public function testOpportunityDTOValidation()
    {
        $dto = new OpportunityDTO();
        
        // Test empty DTO fails validation
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('sales_stage', $errors);
        
        // Test valid DTO passes validation
        $dto->setName('Test Opportunity')
            ->setAmount(50000)
            ->setSalesStage('Prospecting')
            ->setProbability(10)
            ->setDateClosed(date('Y-m-d', strtotime('+30 days')));
            
        $this->assertTrue($dto->validate());
        
        // Test invalid sales stage
        $dto->setSalesStage('Invalid Stage');
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('sales_stage', $errors);
        
        // Test probability validation
        $dto->setSalesStage('Prospecting');
        $dto->setProbability(150); // Over 100
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('probability', $errors);
    }
    
    /**
     * Test filtering opportunities by stage
     */
    public function testFilterOpportunitiesByStage()
    {
        // Create opportunities in different stages
        $prospecting = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Prospecting Deal',
            'sales_stage' => 'Prospecting'
        ]);
        
        $negotiation = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Negotiation Deal',
            'sales_stage' => 'Negotiation'
        ]);
        
        $closedWon = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Won Deal',
            'sales_stage' => 'Closed Won'
        ]);
        
        // Filter for 'Negotiation' stage
        $result = $this->makeApiRequest('GET', '/opportunities', ['sales_stage' => 'Negotiation'], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        $oppIds = array_column($data['data'], 'id');
        
        $this->assertNotContains($prospecting->id, $oppIds);
        $this->assertContains($negotiation->id, $oppIds);
        $this->assertNotContains($closedWon->id, $oppIds);
    }
    
    /**
     * Test opportunity amount range filtering
     */
    public function testFilterOpportunitiesByAmount()
    {
        // Create opportunities with different amounts
        $small = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Small Deal',
            'amount' => 10000
        ]);
        
        $medium = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Medium Deal',
            'amount' => 50000
        ]);
        
        $large = \TestDatabaseHelper::createTestRecord('Opportunities', [
            'name' => 'Large Deal',
            'amount' => 200000
        ]);
        
        // Filter for amounts >= 50000
        $result = $this->makeApiRequest('GET', '/opportunities', [
            'amount' => json_encode(['gte' => 50000])
        ], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        $oppIds = array_column($data['data'], 'id');
        
        $this->assertNotContains($small->id, $oppIds);
        $this->assertContains($medium->id, $oppIds);
        $this->assertContains($large->id, $oppIds);
    }
    
    /**
     * Test opportunity probability auto-update
     */
    public function testProbabilityAutoUpdate()
    {
        $dto = new OpportunityDTO();
        $dto->setName('Test Deal')
            ->setAmount(50000)
            ->setSalesStage('Proposal')
            ->setDateClosed(date('Y-m-d', strtotime('+30 days')));
        
        // Verify probability is auto-set based on stage
        $this->assertTrue($dto->validate());
        $this->assertEquals(65, $dto->getProbability());
        
        // Change stage and verify probability updates
        $dto->setSalesStage('Closed Won');
        $this->assertEquals(100, $dto->getProbability());
    }
}