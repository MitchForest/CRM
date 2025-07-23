<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base integration test to verify SuiteCRM bootstrap
 */
class SuiteCRMIntegrationTest extends \SuiteCRMIntegrationTest
{
    /**
     * Test that SuiteCRM is properly loaded
     */
    public function testSuiteCRMBootstrap()
    {
        global $sugar_config, $db;
        
        // Test configuration is loaded
        $this->assertNotEmpty($sugar_config);
        $this->assertArrayHasKey('dbconfig', $sugar_config);
        
        // Test database connection
        $this->assertNotNull($db);
        $this->assertTrue($db->checkConnection());
        
        // Test we can query the database
        $result = $db->query("SELECT 1 as test");
        $row = $db->fetchByAssoc($result);
        $this->assertEquals(1, $row['test']);
    }
    
    /**
     * Test that we can create and retrieve a bean
     */
    public function testBeanOperations()
    {
        // Create a test contact
        $contact = \TestDatabaseHelper::createTestRecord('Contacts', [
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email1' => 'test@example.com'
        ]);
        
        $this->assertNotEmpty($contact->id);
        
        // Retrieve the contact
        $retrieved = \BeanFactory::getBean('Contacts', $contact->id);
        
        $this->assertEquals('Test', $retrieved->first_name);
        $this->assertEquals('Contact', $retrieved->last_name);
        $this->assertEquals('test@example.com', $retrieved->email1);
    }
    
    /**
     * Test JWT token generation and validation
     */
    public function testJWTAuthentication()
    {
        $jwt = new \Api\Auth\JWT();
        
        // Test encoding
        $payload = [
            'user_id' => 'test-user-id',
            'username' => 'testuser',
            'exp' => time() + 3600
        ];
        
        $token = $jwt->encode($payload);
        $this->assertNotEmpty($token);
        
        // Test decoding
        $decoded = $jwt->decode($token);
        $this->assertEquals($payload['user_id'], $decoded['user_id']);
        $this->assertEquals($payload['username'], $decoded['username']);
    }
    
    /**
     * Test user authentication
     */
    public function testUserAuthentication()
    {
        $auth = $this->createAuthenticatedUser('phpunit_user', 'phpunit_pass');
        
        $this->assertNotEmpty($auth['user']->id);
        $this->assertEquals('phpunit_user', $auth['user']->user_name);
        $this->assertNotEmpty($auth['token']);
        
        // Verify token contains correct user info
        $jwt = new \Api\Auth\JWT();
        $decoded = $jwt->decode($auth['token']);
        
        $this->assertEquals($auth['user']->id, $decoded['user_id']);
        $this->assertEquals($auth['user']->user_name, $decoded['username']);
    }
}