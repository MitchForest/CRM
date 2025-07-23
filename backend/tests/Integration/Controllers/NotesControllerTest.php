<?php
namespace Tests\Integration\Controllers;

use Tests\Integration\SuiteCRMIntegrationTest;

class NotesControllerTest extends SuiteCRMIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListNotes()
    {
        // Create test note
        $note = \BeanFactory::newBean('Notes');
        $note->name = 'Test Note';
        $note->description = 'This is a test note';
        $note->save();

        $response = $this->makeRequest('GET', '/notes');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
        
        // Cleanup
        $note->mark_deleted($note->id);
    }

    public function testGetNote()
    {
        // Create test note
        $note = \BeanFactory::newBean('Notes');
        $note->name = 'Important Note';
        $note->description = 'Details about the important topic';
        $note->save();

        $response = $this->makeRequest('GET', "/notes/{$note->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals($note->id, $data['id']);
        $this->assertEquals('Important Note', $data['name']);
        $this->assertEquals('Details about the important topic', $data['description']);
        
        // Cleanup
        $note->mark_deleted($note->id);
    }

    public function testCreateNote()
    {
        $noteData = [
            'name' => 'New Note',
            'description' => 'This is a new note with important information',
            'tags' => ['important', 'follow-up']
        ];

        $response = $this->makeRequest('POST', '/notes', $noteData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Note', $data['name']);
        $this->assertEquals('This is a new note with important information', $data['description']);
        $this->assertContains('important', $data['tags']);
        $this->assertContains('follow-up', $data['tags']);
        
        // Cleanup
        $note = \BeanFactory::getBean('Notes', $data['id']);
        $note->mark_deleted($note->id);
    }

    public function testCreateNoteWithParent()
    {
        // Create a contact to attach note to
        $contact = \BeanFactory::newBean('Contacts');
        $contact->first_name = 'Test';
        $contact->last_name = 'Contact';
        $contact->save();

        $noteData = [
            'name' => 'Contact Note',
            'description' => 'Note about the contact',
            'parentType' => 'Contacts',
            'parentId' => $contact->id
        ];

        $response = $this->makeRequest('POST', '/notes', $noteData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Contacts', $data['parentType']);
        $this->assertEquals($contact->id, $data['parentId']);
        $this->assertEquals('Test Contact', $data['parentName']);
        
        // Cleanup
        $note = \BeanFactory::getBean('Notes', $data['id']);
        $note->mark_deleted($note->id);
        $contact->mark_deleted($contact->id);
    }

    public function testUploadAttachment()
    {
        // Create test note
        $note = \BeanFactory::newBean('Notes');
        $note->name = 'Note with Attachment';
        $note->save();

        // Simulate file upload
        $fileData = [
            'file' => [
                'name' => 'test-document.pdf',
                'type' => 'application/pdf',
                'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
                'size' => 1024
            ]
        ];

        // Create test file
        file_put_contents($fileData['file']['tmp_name'], 'Test file content');

        $response = $this->makeRequestWithFiles('POST', "/notes/{$note->id}/upload", [], $fileData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('attachment', $data);
        $this->assertEquals('test-document.pdf', $data['attachment']['name']);
        $this->assertEquals('application/pdf', $data['attachment']['mimeType']);
        
        // Cleanup
        $note->mark_deleted($note->id);
        if (file_exists($fileData['file']['tmp_name'])) {
            unlink($fileData['file']['tmp_name']);
        }
    }

    public function testSearchNotes()
    {
        // Create test notes with tags
        $note1 = \BeanFactory::newBean('Notes');
        $note1->name = 'Meeting Notes';
        $note1->description = 'Notes from client meeting';
        $note1->save();

        $note2 = \BeanFactory::newBean('Notes');
        $note2->name = 'Project Ideas';
        $note2->description = 'Brainstorming session notes';
        $note2->save();

        $response = $this->makeRequest('GET', '/notes/search?q=meeting');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertGreaterThan(0, count($data['data']));
        
        // Verify search results contain the search term
        $found = false;
        foreach ($data['data'] as $note) {
            if (stripos($note['name'], 'meeting') !== false || 
                stripos($note['description'], 'meeting') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        
        // Cleanup
        $note1->mark_deleted($note1->id);
        $note2->mark_deleted($note2->id);
    }

    public function testUpdateNote()
    {
        // Create test note
        $note = \BeanFactory::newBean('Notes');
        $note->name = 'Original Note';
        $note->description = 'Original description';
        $note->save();

        $updateData = [
            'name' => 'Updated Note',
            'description' => 'Updated description with more details',
            'tags' => ['updated', 'important']
        ];

        $response = $this->makeRequest('PUT', "/notes/{$note->id}", $updateData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Updated Note', $data['name']);
        $this->assertEquals('Updated description with more details', $data['description']);
        $this->assertContains('updated', $data['tags']);
        
        // Cleanup
        $note->mark_deleted($note->id);
    }

    public function testDeleteNote()
    {
        // Create test note
        $note = \BeanFactory::newBean('Notes');
        $note->name = 'Note to Delete';
        $note->save();

        $response = $this->makeRequest('DELETE', "/notes/{$note->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify note is marked as deleted
        $deleted = \BeanFactory::getBean('Notes', $note->id);
        $this->assertEquals(1, $deleted->deleted);
    }

    public function testNoteNotFound()
    {
        $response = $this->makeRequest('GET', '/notes/invalid-id');
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Note not found', $data['error']);
        $this->assertEquals('NOT_FOUND', $data['code']);
    }

    public function testNoteValidation()
    {
        // Missing required fields
        $response = $this->makeRequest('POST', '/notes', [
            'description' => 'Note without a name'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('required', strtolower($data['error']));
    }

    public function testSearchValidation()
    {
        // Empty search query
        $response = $this->makeRequest('GET', '/notes/search');
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('Search query required', $data['error']);
    }

    public function testInvalidFileUpload()
    {
        $note = \BeanFactory::newBean('Notes');
        $note->name = 'Test Note';
        $note->save();

        // No file provided
        $response = $this->makeRequest('POST', "/notes/{$note->id}/upload");
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('No file uploaded', $data['error']);
        
        // Cleanup
        $note->mark_deleted($note->id);
    }
}