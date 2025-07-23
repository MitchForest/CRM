<?php
namespace Tests\Integration\Controllers;

use Api\DTO\ContactDTO;

/**
 * Integration tests for ContactsController
 */
class ContactsControllerTest extends \SuiteCRMIntegrationTest
{
    private $controller;
    private $auth;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create authenticated user
        $this->auth = $this->createAuthenticatedUser();
        
        // Create controller instance
        $this->controller = new \Api\Controllers\ContactsController();
    }
    
    /**
     * Test listing contacts
     */
    public function testListContacts()
    {
        // Create test contacts
        $contact1 = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email1' => 'john.doe@example.com'
        ]);
        
        $contact2 = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email1' => 'jane.smith@example.com'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/contacts', [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->list($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pageSize', $data);
        
        // Verify contacts are in response
        $contactIds = array_column($data['data'], 'id');
        $this->assertContains($contact1->id, $contactIds);
        $this->assertContains($contact2->id, $contactIds);
    }
    
    /**
     * Test creating a contact
     */
    public function testCreateContact()
    {
        $contactData = [
            'first_name' => 'New',
            'last_name' => 'Contact',
            'email1' => 'new.contact@example.com',
            'phone_mobile' => '+1234567890',
            'lead_source' => 'Web Site'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', '/contacts', $contactData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Mock request body
        $request->body = json_encode($contactData);
        
        // Call controller method
        $this->controller->create($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New', $data['first_name']);
        $this->assertEquals('Contact', $data['last_name']);
        $this->assertEquals('new.contact@example.com', $data['email1']);
        
        // Verify contact was created in database
        $contact = \BeanFactory::getBean('Contacts', $data['id']);
        $this->assertNotNull($contact);
        $this->assertEquals('New', $contact->first_name);
    }
    
    /**
     * Test getting a single contact
     */
    public function testGetContact()
    {
        // Create test contact
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email1' => 'test.user@example.com',
            'phone_work' => '+9876543210'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', "/contacts/{$contact->id}", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $contact->id];
        
        // Call controller method
        $this->controller->get($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($contact->id, $data['id']);
        $this->assertEquals('Test', $data['first_name']);
        $this->assertEquals('User', $data['last_name']);
        $this->assertEquals('test.user@example.com', $data['email1']);
    }
    
    /**
     * Test updating a contact
     */
    public function testUpdateContact()
    {
        // Create test contact
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email1' => 'original@example.com'
        ]);
        
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email1' => 'updated@example.com',
            'phone_mobile' => '+1111111111'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/contacts/{$contact->id}", $updateData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $contact->id];
        $request->body = json_encode($updateData);
        
        // Call controller method
        $this->controller->update($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated', $data['first_name']);
        $this->assertEquals('updated@example.com', $data['email1']);
        $this->assertEquals('+1111111111', $data['phone_mobile']);
        
        // Verify contact was updated in database
        $updatedContact = \BeanFactory::getBean('Contacts', $contact->id);
        $this->assertEquals('Updated', $updatedContact->first_name);
        $this->assertEquals('updated@example.com', $updatedContact->email1);
    }
    
    /**
     * Test deleting a contact
     */
    public function testDeleteContact()
    {
        // Create test contact
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'email1' => 'delete.me@example.com'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('DELETE', "/contacts/{$contact->id}", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $contact->id];
        
        // Call controller method
        $this->controller->delete($request, $response);
        
        // Assert response
        $this->assertEquals(204, $response->getStatusCode());
        
        // Verify contact was soft deleted
        $deletedContact = \BeanFactory::getBean('Contacts', $contact->id);
        $this->assertEquals(1, $deletedContact->deleted);
    }
    
    /**
     * Test contact activities endpoint
     */
    public function testContactActivities()
    {
        // Create test contact
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Activity',
            'last_name' => 'Test',
            'email1' => 'activity.test@example.com'
        ]);
        
        // Create related task
        $task = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Test Task',
            'status' => 'Not Started',
            'parent_type' => 'Contacts',
            'parent_id' => $contact->id,
            'contact_id' => $contact->id
        ]);
        
        // Create related email
        $email = \TestDatabaseHelper::createTestRecord('Emails', [
            'name' => 'Test Email',
            'parent_type' => 'Contacts',
            'parent_id' => $contact->id
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', "/contacts/{$contact->id}/activities", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $contact->id];
        
        // Call controller method
        $this->controller->activities($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('tasks', $data);
        $this->assertArrayHasKey('emails', $data);
        $this->assertArrayHasKey('lastActivityDate', $data);
        
        // Verify activities are included
        $taskIds = array_column($data['tasks'], 'id');
        $emailIds = array_column($data['emails'], 'id');
        
        $this->assertContains($task->id, $taskIds);
        $this->assertContains($email->id, $emailIds);
    }
    
    /**
     * Test DTO validation
     */
    public function testContactDTOValidation()
    {
        $dto = new ContactDTO();
        
        // Test empty DTO fails validation
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('first_name', $errors);
        
        // Test valid DTO passes validation
        $dto->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john.doe@example.com');
            
        $this->assertTrue($dto->validate());
        
        // Test invalid email
        $dto->setEmail('invalid-email');
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('email', $errors);
    }
    
    /**
     * Test filtering contacts
     */
    public function testFilterContacts()
    {
        // Create test contacts with different sources
        $webContact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Web',
            'last_name' => 'Contact',
            'lead_source' => 'Web Site'
        ]);
        
        $emailContact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Email',
            'last_name' => 'Contact',
            'lead_source' => 'Email'
        ]);
        
        // Make API request with filter
        $result = $this->makeApiRequest('GET', '/contacts', [
            'lead_source' => 'Web Site'
        ], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->list($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify only web contact is returned
        $contactIds = array_column($data['data'], 'id');
        $this->assertContains($webContact->id, $contactIds);
        $this->assertNotContains($emailContact->id, $contactIds);
    }
}