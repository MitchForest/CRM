<?php
namespace Tests\Integration\Controllers;

use Tests\Integration\SuiteCRMIntegrationTest;

class MeetingsControllerTest extends SuiteCRMIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListMeetings()
    {
        // Create test meeting
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Test Meeting';
        $meeting->status = 'Planned';
        $meeting->date_start = date('Y-m-d H:i:s', strtotime('+1 day'));
        $meeting->duration_hours = 1;
        $meeting->duration_minutes = 0;
        $meeting->location = 'Conference Room A';
        $meeting->save();

        $response = $this->makeRequest('GET', '/meetings');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
        
        // Cleanup
        $meeting->mark_deleted($meeting->id);
    }

    public function testGetMeeting()
    {
        // Create test meeting
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Project Review Meeting';
        $meeting->status = 'Held';
        $meeting->date_start = date('Y-m-d H:i:s');
        $meeting->date_end = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $meeting->duration_hours = 2;
        $meeting->duration_minutes = 0;
        $meeting->location = 'Virtual - Zoom';
        $meeting->description = 'Quarterly project review';
        $meeting->save();

        $response = $this->makeRequest('GET', "/meetings/{$meeting->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals($meeting->id, $data['id']);
        $this->assertEquals('Project Review Meeting', $data['name']);
        $this->assertEquals('Held', $data['status']);
        $this->assertEquals('Virtual - Zoom', $data['location']);
        $this->assertEquals(120, $data['duration']); // 2 hours = 120 mins
        
        // Cleanup
        $meeting->mark_deleted($meeting->id);
    }

    public function testCreateMeeting()
    {
        $meetingData = [
            'name' => 'New Team Meeting',
            'status' => 'Planned',
            'type' => 'Virtual',
            'startDate' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'endDate' => date('Y-m-d H:i:s', strtotime('+2 days +1 hour')),
            'location' => 'Teams Meeting',
            'meetingUrl' => 'https://teams.microsoft.com/meet/123',
            'description' => 'Weekly team sync',
            'agenda' => '1. Updates\n2. Blockers\n3. Next steps'
        ];

        $response = $this->makeRequest('POST', '/meetings', $meetingData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Team Meeting', $data['name']);
        $this->assertEquals('Virtual', $data['type']);
        $this->assertEquals('Teams Meeting', $data['location']);
        $this->assertArrayHasKey('meetingUrl', $data);
        
        // Cleanup
        $meeting = \BeanFactory::getBean('Meetings', $data['id']);
        $meeting->mark_deleted($meeting->id);
    }

    public function testCreateMeetingWithInvitees()
    {
        // Create test contact to invite
        $contact = \BeanFactory::newBean('Contacts');
        $contact->first_name = 'Test';
        $contact->last_name = 'Invitee';
        $contact->email1 = 'invitee@example.com';
        $contact->save();

        $meetingData = [
            'name' => 'Meeting with Invitees',
            'status' => 'Planned',
            'type' => 'In Person',
            'startDate' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'endDate' => date('Y-m-d H:i:s', strtotime('+1 week +30 minutes')),
            'invitees' => [
                [
                    'id' => $contact->id,
                    'type' => 'contact',
                    'status' => 'invited'
                ]
            ]
        ];

        $response = $this->makeRequest('POST', '/meetings', $meetingData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('invitees', $data);
        $this->assertCount(1, $data['invitees']);
        $this->assertEquals($contact->id, $data['invitees'][0]['id']);
        $this->assertEquals('invited', $data['invitees'][0]['status']);
        
        // Cleanup
        $meeting = \BeanFactory::getBean('Meetings', $data['id']);
        $meeting->mark_deleted($meeting->id);
        $contact->mark_deleted($contact->id);
    }

    public function testUpdateInviteeStatus()
    {
        // Create meeting and contact
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Meeting to Update Invitee';
        $meeting->status = 'Planned';
        $meeting->save();

        $contact = \BeanFactory::newBean('Contacts');
        $contact->first_name = 'John';
        $contact->last_name = 'Doe';
        $contact->save();

        // Add invitee
        $meeting->load_relationship('contacts');
        $meeting->contacts->add($contact->id);

        $response = $this->makeRequest('PUT', "/meetings/{$meeting->id}/invitees/{$contact->id}", [
            'status' => 'accepted'
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('accepted', $data['status']);
        
        // Cleanup
        $meeting->mark_deleted($meeting->id);
        $contact->mark_deleted($contact->id);
    }

    public function testHoldMeeting()
    {
        // Create test meeting
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Meeting to Hold';
        $meeting->status = 'Planned';
        $meeting->save();

        $response = $this->makeRequest('POST', "/meetings/{$meeting->id}/hold", [
            'minutes' => 'Meeting minutes: Discussed project timeline and deliverables.'
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Held', $data['status']);
        $this->assertStringContainsString('Discussed project timeline', $data['minutes']);
        
        // Cleanup
        $meeting->mark_deleted($meeting->id);
    }

    public function testCancelMeeting()
    {
        // Create test meeting
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Meeting to Cancel';
        $meeting->status = 'Planned';
        $meeting->save();

        $response = $this->makeRequest('POST', "/meetings/{$meeting->id}/cancel");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Cancelled', $data['status']);
        
        // Cleanup
        $meeting->mark_deleted($meeting->id);
    }

    public function testGetMeetingTemplates()
    {
        // Create template meeting
        $template = \BeanFactory::newBean('Meetings');
        $template->name = 'Weekly Standup Template';
        $template->status = 'Template';
        $template->duration_hours = 0;
        $template->duration_minutes = 15;
        $template->description = 'Standard weekly standup meeting';
        $template->save();

        $response = $this->makeRequest('GET', '/meetings/templates');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        
        // Verify template is in results
        $templates = array_column($data['data'], 'name');
        $this->assertContains('Weekly Standup Template', $templates);
        
        // Cleanup
        $template->mark_deleted($template->id);
    }

    public function testDeleteMeeting()
    {
        // Create test meeting
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Meeting to Delete';
        $meeting->save();

        $response = $this->makeRequest('DELETE', "/meetings/{$meeting->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify meeting is marked as deleted
        $deleted = \BeanFactory::getBean('Meetings', $meeting->id);
        $this->assertEquals(1, $deleted->deleted);
    }

    public function testMeetingNotFound()
    {
        $response = $this->makeRequest('GET', '/meetings/invalid-id');
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Meeting not found', $data['error']);
        $this->assertEquals('NOT_FOUND', $data['code']);
    }

    public function testMeetingValidation()
    {
        // Missing required fields
        $response = $this->makeRequest('POST', '/meetings', [
            'description' => 'Missing required fields'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('required', strtolower($data['error']));
    }

    public function testInvalidInviteeStatus()
    {
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = 'Test Meeting';
        $meeting->save();

        $response = $this->makeRequest('PUT', "/meetings/{$meeting->id}/invitees/fake-id", [
            'status' => 'invalid-status'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        
        // Cleanup
        $meeting->mark_deleted($meeting->id);
    }
}