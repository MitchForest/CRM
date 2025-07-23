<?php
namespace Tests\Integration\Controllers;

use Tests\Integration\SuiteCRMIntegrationTest;

class CallsControllerTest extends SuiteCRMIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListCalls()
    {
        // Create test call
        $call = \BeanFactory::newBean('Calls');
        $call->name = 'Test Call';
        $call->status = 'Planned';
        $call->direction = 'Outbound';
        $call->date_start = date('Y-m-d H:i:s');
        $call->duration_hours = 0;
        $call->duration_minutes = 30;
        $call->save();

        $response = $this->makeRequest('GET', '/calls');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
        
        // Cleanup
        $call->mark_deleted($call->id);
    }

    public function testGetCall()
    {
        // Create test call
        $call = \BeanFactory::newBean('Calls');
        $call->name = 'Important Call';
        $call->status = 'Held';
        $call->direction = 'Inbound';
        $call->date_start = date('Y-m-d H:i:s');
        $call->duration_hours = 1;
        $call->duration_minutes = 15;
        $call->description = 'Call notes';
        $call->save();

        $response = $this->makeRequest('GET', "/calls/{$call->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals($call->id, $data['id']);
        $this->assertEquals('Important Call', $data['name']);
        $this->assertEquals('Held', $data['status']);
        $this->assertEquals('Inbound', $data['direction']);
        $this->assertEquals(75, $data['duration']); // 1 hour 15 mins = 75 mins
        
        // Cleanup
        $call->mark_deleted($call->id);
    }

    public function testCreateCall()
    {
        $callData = [
            'name' => 'New Call',
            'direction' => 'Outbound',
            'status' => 'Planned',
            'startDate' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'duration' => 45,
            'phoneNumber' => '+1234567890',
            'description' => 'Call to discuss project'
        ];

        $response = $this->makeRequest('POST', '/calls', $callData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Call', $data['name']);
        $this->assertEquals('Planned', $data['status']);
        $this->assertEquals(45, $data['duration']);
        
        // Cleanup
        $call = \BeanFactory::getBean('Calls', $data['id']);
        $call->mark_deleted($call->id);
    }

    public function testCreateRecurringCall()
    {
        $callData = [
            'name' => 'Weekly Team Call',
            'direction' => 'Outbound',
            'status' => 'Planned',
            'startDate' => date('Y-m-d H:i:s'),
            'duration' => 60,
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'count' => 4,
                'byDay' => ['MO', 'WE', 'FR']
            ]
        ];

        $response = $this->makeRequest('POST', '/calls', $callData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('recurrence', $data);
        $this->assertEquals('weekly', $data['recurrence']['frequency']);
        $this->assertEquals(4, $data['recurrence']['count']);
        
        // Cleanup
        $call = \BeanFactory::getBean('Calls', $data['id']);
        $call->mark_deleted($call->id);
    }

    public function testUpdateCallStatus()
    {
        // Create test call
        $call = \BeanFactory::newBean('Calls');
        $call->name = 'Call to Update';
        $call->status = 'Planned';
        $call->save();

        $updateData = [
            'status' => 'Held',
            'result' => 'Successful call, follow-up scheduled'
        ];

        $response = $this->makeRequest('PUT', "/calls/{$call->id}", $updateData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Held', $data['status']);
        $this->assertEquals('Successful call, follow-up scheduled', $data['result']);
        
        // Cleanup
        $call->mark_deleted($call->id);
    }

    public function testHoldCall()
    {
        // Create test call
        $call = \BeanFactory::newBean('Calls');
        $call->name = 'Call to Hold';
        $call->status = 'Planned';
        $call->save();

        $response = $this->makeRequest('POST', "/calls/{$call->id}/hold");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Held', $data['status']);
        
        // Cleanup
        $call->mark_deleted($call->id);
    }

    public function testCancelCall()
    {
        // Create test call
        $call = \BeanFactory::newBean('Calls');
        $call->name = 'Call to Cancel';
        $call->status = 'Planned';
        $call->save();

        $response = $this->makeRequest('POST', "/calls/{$call->id}/cancel");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Cancelled', $data['status']);
        
        // Cleanup
        $call->mark_deleted($call->id);
    }

    public function testDeleteCall()
    {
        // Create test call
        $call = \BeanFactory::newBean('Calls');
        $call->name = 'Call to Delete';
        $call->save();

        $response = $this->makeRequest('DELETE', "/calls/{$call->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify call is marked as deleted
        $deleted = \BeanFactory::getBean('Calls', $call->id);
        $this->assertEquals(1, $deleted->deleted);
    }

    public function testCallNotFound()
    {
        $response = $this->makeRequest('GET', '/calls/invalid-id');
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Call not found', $data['error']);
        $this->assertEquals('NOT_FOUND', $data['code']);
    }

    public function testCallValidation()
    {
        // Missing required fields
        $response = $this->makeRequest('POST', '/calls', [
            'description' => 'Missing required fields'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('required', strtolower($data['error']));
    }

    public function testInvalidRecurrenceData()
    {
        $callData = [
            'name' => 'Invalid Recurring Call',
            'direction' => 'Outbound',
            'status' => 'Planned',
            'startDate' => date('Y-m-d H:i:s'),
            'recurrence' => [
                'frequency' => 'invalid'
            ]
        ];

        $response = $this->makeRequest('POST', '/calls', $callData);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('Invalid recurrence frequency', $data['error']);
    }
}