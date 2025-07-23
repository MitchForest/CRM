<?php
namespace Tests\Integration\Controllers;

use Api\DTO\TaskDTO;

/**
 * Integration tests for TasksController
 */
class TasksControllerTest extends \SuiteCRMIntegrationTest
{
    private $controller;
    private $auth;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create authenticated user
        $this->auth = $this->createAuthenticatedUser();
        
        // Create controller instance
        $this->controller = new \Api\Controllers\TasksController();
    }
    
    /**
     * Test listing tasks
     */
    public function testListTasks()
    {
        // Create test contact for relationship
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Task',
            'last_name' => 'Owner'
        ]);
        
        // Create test tasks
        $task1 = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Call customer',
            'status' => 'Not Started',
            'priority' => 'High',
            'date_due' => date('Y-m-d', strtotime('+1 day')),
            'parent_type' => 'Contacts',
            'parent_id' => $contact->id,
            'contact_id' => $contact->id
        ]);
        
        $task2 = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Send proposal',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'date_due' => date('Y-m-d', strtotime('+3 days'))
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/tasks', [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->list($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        
        // Verify tasks are in response
        $taskIds = array_column($data['data'], 'id');
        $this->assertContains($task1->id, $taskIds);
        $this->assertContains($task2->id, $taskIds);
        
        // Verify contact relationship is loaded
        foreach ($data['data'] as $taskData) {
            if ($taskData['id'] === $task1->id) {
                $this->assertEquals($contact->id, $taskData['contact_id']);
            }
        }
    }
    
    /**
     * Test creating a task
     */
    public function testCreateTask()
    {
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        $taskData = [
            'name' => 'Follow up with customer',
            'status' => 'Not Started',
            'priority' => 'High',
            'date_due' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'description' => 'Discuss product features and pricing',
            'parent_type' => 'Contacts',
            'parent_id' => $contact->id,
            'contact_id' => $contact->id,
            'reminder_time' => 900, // 15 minutes
            'percent_complete' => 0
        ];
        
        // Make API request
        $result = $this->makeApiRequest('POST', '/tasks', $taskData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Mock request body
        $request->body = json_encode($taskData);
        
        // Call controller method
        $this->controller->create($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Follow up with customer', $data['name']);
        $this->assertEquals('Not Started', $data['status']);
        $this->assertEquals('High', $data['priority']);
        
        // Verify task was created in database
        $task = \BeanFactory::getBean('Tasks', $data['id']);
        $this->assertNotNull($task);
        $this->assertEquals('Follow up with customer', $task->name);
        $this->assertEquals($contact->id, $task->contact_id);
        $this->assertEquals(900, $task->reminder_time);
    }
    
    /**
     * Test completing a task
     */
    public function testCompleteTask()
    {
        // Create test task
        $task = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Complete me',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'percent_complete' => 50
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/tasks/{$task->id}/complete", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $task->id];
        
        // Call controller method
        $this->controller->complete($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Task completed successfully', $data['message']);
        $this->assertEquals('Completed', $data['task']['status']);
        $this->assertEquals(100, $data['task']['percent_complete']);
        
        // Verify task was updated in database
        $completedTask = \BeanFactory::getBean('Tasks', $task->id);
        $this->assertEquals('Completed', $completedTask->status);
        $this->assertEquals(100, $completedTask->percent_complete);
    }
    
    /**
     * Test getting upcoming tasks
     */
    public function testGetUpcomingTasks()
    {
        // Create tasks with different due dates
        $overdue = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Overdue task',
            'status' => 'Not Started',
            'date_due' => date('Y-m-d', strtotime('-2 days'))
        ]);
        
        $todayTask = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Due today',
            'status' => 'In Progress',
            'date_due' => date('Y-m-d')
        ]);
        
        $futureTask = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Future task',
            'status' => 'Not Started',
            'date_due' => date('Y-m-d', strtotime('+7 days'))
        ]);
        
        $completedTask = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Already done',
            'status' => 'Completed',
            'date_due' => date('Y-m-d', strtotime('+1 day'))
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/tasks/upcoming', ['days' => 7], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->upcoming($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $taskIds = array_column($data, 'id');
        
        // Should include today and future tasks, but not overdue or completed
        $this->assertContains($todayTask->id, $taskIds);
        $this->assertContains($futureTask->id, $taskIds);
        $this->assertNotContains($overdue->id, $taskIds);
        $this->assertNotContains($completedTask->id, $taskIds);
    }
    
    /**
     * Test getting overdue tasks
     */
    public function testGetOverdueTasks()
    {
        // Create tasks with different statuses and due dates
        $overdue1 = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Very overdue',
            'status' => 'Not Started',
            'date_due' => date('Y-m-d', strtotime('-5 days'))
        ]);
        
        $overdue2 = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Slightly overdue',
            'status' => 'In Progress',
            'date_due' => date('Y-m-d', strtotime('-1 day'))
        ]);
        
        $notDue = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Not due yet',
            'status' => 'Not Started',
            'date_due' => date('Y-m-d', strtotime('+1 day'))
        ]);
        
        $completed = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Completed overdue',
            'status' => 'Completed',
            'date_due' => date('Y-m-d', strtotime('-3 days'))
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('GET', '/tasks/overdue', [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Call controller method
        $this->controller->overdue($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $taskIds = array_column($data, 'id');
        
        // Should only include incomplete overdue tasks
        $this->assertContains($overdue1->id, $taskIds);
        $this->assertContains($overdue2->id, $taskIds);
        $this->assertNotContains($notDue->id, $taskIds);
        $this->assertNotContains($completed->id, $taskIds);
    }
    
    /**
     * Test updating a task
     */
    public function testUpdateTask()
    {
        // Create test task
        $task = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Original task',
            'status' => 'Not Started',
            'priority' => 'Low',
            'percent_complete' => 0
        ]);
        
        $updateData = [
            'name' => 'Updated task',
            'status' => 'In Progress',
            'priority' => 'High',
            'percent_complete' => 75,
            'description' => 'Updated description'
        ];
        
        // Make API request
        $result = $this->makeApiRequest('PUT', "/tasks/{$task->id}", $updateData, $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params and body
        $request->params = ['id' => $task->id];
        $request->body = json_encode($updateData);
        
        // Call controller method
        $this->controller->update($request, $response);
        
        // Assert response
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated task', $data['name']);
        $this->assertEquals('In Progress', $data['status']);
        $this->assertEquals('High', $data['priority']);
        $this->assertEquals(75, $data['percent_complete']);
        
        // Verify task was updated in database
        $updatedTask = \BeanFactory::getBean('Tasks', $task->id);
        $this->assertEquals('Updated task', $updatedTask->name);
        $this->assertEquals('In Progress', $updatedTask->status);
        $this->assertEquals(75, $updatedTask->percent_complete);
    }
    
    /**
     * Test deleting a task
     */
    public function testDeleteTask()
    {
        // Create test task
        $task = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Delete me',
            'status' => 'Not Started'
        ]);
        
        // Make API request
        $result = $this->makeApiRequest('DELETE', "/tasks/{$task->id}", [], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        // Set route params
        $request->params = ['id' => $task->id];
        
        // Call controller method
        $this->controller->delete($request, $response);
        
        // Assert response
        $this->assertEquals(204, $response->getStatusCode());
        
        // Verify task was soft deleted
        $deletedTask = \BeanFactory::getBean('Tasks', $task->id);
        $this->assertEquals(1, $deletedTask->deleted);
    }
    
    /**
     * Test task DTO validation
     */
    public function testTaskDTOValidation()
    {
        $dto = new TaskDTO();
        
        // Test empty DTO fails validation
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('name', $errors);
        
        // Test valid DTO passes validation
        $dto->setName('Test Task')
            ->setStatus('Not Started')
            ->setPriority('Medium')
            ->setDateDue(date('Y-m-d', strtotime('+1 week')));
            
        $this->assertTrue($dto->validate());
        
        // Test invalid status
        $dto->setStatus('Invalid Status');
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('status', $errors);
        
        // Test percent complete validation
        $dto->setStatus('In Progress');
        $dto->setPercentComplete(150); // Over 100
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('percent_complete', $errors);
    }
    
    /**
     * Test auto status update based on percent complete
     */
    public function testAutoStatusUpdate()
    {
        $dto = new TaskDTO();
        $dto->setName('Test Task')
            ->setPriority('Medium');
        
        // Test 0% sets status to Not Started
        $dto->setPercentComplete(0);
        $this->assertEquals('Not Started', $dto->getStatus());
        
        // Test 100% sets status to Completed
        $dto->setPercentComplete(100);
        $this->assertEquals('Completed', $dto->getStatus());
        
        // Test between 0 and 100 sets In Progress
        $dto->setPercentComplete(50);
        $this->assertEquals('In Progress', $dto->getStatus());
    }
    
    /**
     * Test filtering tasks by priority
     */
    public function testFilterTasksByPriority()
    {
        // Create tasks with different priorities
        $highPriority = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'High priority task',
            'priority' => 'High'
        ]);
        
        $mediumPriority = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Medium priority task',
            'priority' => 'Medium'
        ]);
        
        $lowPriority = \TestDatabaseHelper::createTestRecord('Tasks', [
            'name' => 'Low priority task',
            'priority' => 'Low'
        ]);
        
        // Filter for High priority
        $result = $this->makeApiRequest('GET', '/tasks', ['priority' => 'High'], $this->auth['token']);
        $request = $result['request'];
        $response = $result['response'];
        
        $this->controller->list($request, $response);
        
        $data = json_decode($response->getBody(), true);
        $taskIds = array_column($data['data'], 'id');
        
        $this->assertContains($highPriority->id, $taskIds);
        $this->assertNotContains($mediumPriority->id, $taskIds);
        $this->assertNotContains($lowPriority->id, $taskIds);
    }
}