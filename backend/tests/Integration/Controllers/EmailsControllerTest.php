<?php
namespace Tests\Integration\Controllers;

use Tests\Integration\SuiteCRMIntegrationTest;

class EmailsControllerTest extends SuiteCRMIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListEmails()
    {
        // Create test email
        $email = \BeanFactory::newBean('Emails');
        $email->name = 'Test Email Subject';
        $email->type = 'inbound';
        $email->status = 'read';
        $email->from_addr = 'test@example.com';
        $email->to_addrs = 'user@example.com';
        $email->save();

        $response = $this->makeRequest('GET', '/emails');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
        
        // Cleanup
        $email->mark_deleted($email->id);
    }

    public function testGetEmail()
    {
        // Create test email
        $email = \BeanFactory::newBean('Emails');
        $email->name = 'Test Email';
        $email->type = 'outbound';
        $email->status = 'sent';
        $email->from_addr = 'sender@example.com';
        $email->to_addrs = 'recipient@example.com';
        $email->description = 'Test email body';
        $email->description_html = '<p>Test email body</p>';
        $email->save();

        $response = $this->makeRequest('GET', "/emails/{$email->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals($email->id, $data['id']);
        $this->assertEquals('Test Email', $data['subject']);
        $this->assertEquals('outbound', $data['type']);
        $this->assertEquals('sent', $data['status']);
        
        // Cleanup
        $email->mark_deleted($email->id);
    }

    public function testSendEmail()
    {
        $emailData = [
            'subject' => 'Test Send Email',
            'to' => [
                ['email' => 'recipient@example.com', 'name' => 'Test Recipient']
            ],
            'body' => 'This is a test email body',
            'bodyHtml' => '<p>This is a test email body</p>'
        ];

        $response = $this->makeRequest('POST', '/emails/send', $emailData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Test Send Email', $data['subject']);
        $this->assertEquals('sent', $data['status']);
        $this->assertEquals('outbound', $data['type']);
        
        // Verify email was created
        $email = \BeanFactory::getBean('Emails', $data['id']);
        $this->assertNotEmpty($email->id);
        
        // Cleanup
        $email->mark_deleted($email->id);
    }

    public function testReplyToEmail()
    {
        // Create original email
        $originalEmail = \BeanFactory::newBean('Emails');
        $originalEmail->name = 'Original Email';
        $originalEmail->message_id = '<original@example.com>';
        $originalEmail->from_addr = 'sender@example.com';
        $originalEmail->to_addrs = 'recipient@example.com';
        $originalEmail->save();

        $replyData = [
            'body' => 'This is a reply to the email',
            'bodyHtml' => '<p>This is a reply to the email</p>'
        ];

        $response = $this->makeRequest('POST', "/emails/{$originalEmail->id}/reply", $replyData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertStringStartsWith('Re: Original Email', $data['subject']);
        $this->assertEquals($originalEmail->message_id, $data['inReplyTo']);
        $this->assertStringContainsString('This is a reply', $data['body']);
        
        // Cleanup
        $originalEmail->mark_deleted($originalEmail->id);
        if (!empty($data['id'])) {
            $reply = \BeanFactory::getBean('Emails', $data['id']);
            $reply->mark_deleted($reply->id);
        }
    }

    public function testForwardEmail()
    {
        // Create original email
        $originalEmail = \BeanFactory::newBean('Emails');
        $originalEmail->name = 'Email to Forward';
        $originalEmail->from_addr = 'original@example.com';
        $originalEmail->to_addrs = 'recipient@example.com';
        $originalEmail->description = 'Original email content';
        $originalEmail->save();

        $forwardData = [
            'to' => [
                ['email' => 'forward@example.com', 'name' => 'Forward Recipient']
            ],
            'body' => 'Forwarding this email to you'
        ];

        $response = $this->makeRequest('POST', "/emails/{$originalEmail->id}/forward", $forwardData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertStringStartsWith('Fwd: Email to Forward', $data['subject']);
        $this->assertStringContainsString('Forwarding this email', $data['body']);
        $this->assertStringContainsString('Original email content', $data['body']);
        
        // Cleanup
        $originalEmail->mark_deleted($originalEmail->id);
        if (!empty($data['id'])) {
            $forward = \BeanFactory::getBean('Emails', $data['id']);
            $forward->mark_deleted($forward->id);
        }
    }

    public function testGetInboxEmails()
    {
        // Create test emails
        $inbox = \BeanFactory::newBean('Emails');
        $inbox->name = 'Inbox Email';
        $inbox->type = 'inbound';
        $inbox->status = 'unread';
        $inbox->save();

        $sent = \BeanFactory::newBean('Emails');
        $sent->name = 'Sent Email';
        $sent->type = 'outbound';
        $sent->status = 'sent';
        $sent->save();

        $response = $this->makeRequest('GET', '/emails/inbox');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        
        // Verify only inbox emails are returned
        $types = array_column($data['data'], 'type');
        $this->assertContains('inbound', $types);
        $this->assertNotContains('outbound', $types);
        
        // Cleanup
        $inbox->mark_deleted($inbox->id);
        $sent->mark_deleted($sent->id);
    }

    public function testDeleteEmail()
    {
        // Create test email
        $email = \BeanFactory::newBean('Emails');
        $email->name = 'Email to Delete';
        $email->save();

        $response = $this->makeRequest('DELETE', "/emails/{$email->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify email is marked as deleted
        $deleted = \BeanFactory::getBean('Emails', $email->id);
        $this->assertEquals(1, $deleted->deleted);
    }

    public function testEmailNotFound()
    {
        $response = $this->makeRequest('GET', '/emails/invalid-id');
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Email not found', $data['error']);
        $this->assertEquals('NOT_FOUND', $data['code']);
    }

    public function testSendEmailValidation()
    {
        // Missing required fields
        $response = $this->makeRequest('POST', '/emails/send', []);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('required', strtolower($data['error']));
    }
}