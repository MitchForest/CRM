<?php
namespace Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use Api\DTO\LeadDTO;

/**
 * Unit tests for LeadDTO
 */
class LeadDTOTest extends TestCase
{
    /**
     * Test required field validation
     */
    public function testRequiredFieldValidation()
    {
        $dto = new LeadDTO();
        
        // Should fail without required fields
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertEquals('Last name is required', $errors['last_name']);
    }
    
    /**
     * Test email validation
     */
    public function testEmailValidation()
    {
        $dto = new LeadDTO();
        $dto->setLastName('Doe');
        
        // Test invalid email
        $dto->setEmail('invalid-email');
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        $this->assertArrayHasKey('email', $errors);
        
        // Test valid email
        $dto->setEmail('john.doe@example.com');
        $this->assertTrue($dto->validate());
    }
    
    /**
     * Test phone number validation
     */
    public function testPhoneValidation()
    {
        $dto = new LeadDTO();
        $dto->setLastName('Doe');
        
        // Test invalid phone
        $dto->setPhoneMobile('abc123');
        $this->assertFalse($dto->validate());
        
        // Test valid phone formats
        $validPhones = [
            '+1234567890',
            '123-456-7890',
            '(123) 456-7890',
            '123 456 7890'
        ];
        
        foreach ($validPhones as $phone) {
            $dto->setPhoneMobile($phone);
            $this->assertTrue($dto->validate(), "Phone format should be valid: $phone");
        }
    }
    
    /**
     * Test lead status validation
     */
    public function testStatusValidation()
    {
        $dto = new LeadDTO();
        $dto->setLastName('Doe');
        
        // Test invalid status
        $dto->setStatus('Invalid Status');
        $this->assertFalse($dto->validate());
        
        // Test valid statuses
        $validStatuses = ['New', 'Assigned', 'In Process', 'Converted', 'Recycled', 'Dead'];
        
        foreach ($validStatuses as $status) {
            $dto->setStatus($status);
            $this->assertTrue($dto->validate(), "Status should be valid: $status");
        }
    }
    
    /**
     * Test lead score validation
     */
    public function testLeadScoreValidation()
    {
        $dto = new LeadDTO();
        $dto->setLastName('Doe');
        
        // Test invalid values
        $dto->setLeadScore(-1);
        $this->assertFalse($dto->validate());
        
        $dto->setLeadScore(101);
        $this->assertFalse($dto->validate());
        
        // Test valid values
        $dto->setLeadScore(0);
        $this->assertTrue($dto->validate());
        
        $dto->setLeadScore(50);
        $this->assertTrue($dto->validate());
        
        $dto->setLeadScore(100);
        $this->assertTrue($dto->validate());
    }
    
    /**
     * Test website URL validation
     */
    public function testWebsiteValidation()
    {
        $dto = new LeadDTO();
        $dto->setLastName('Doe');
        
        // Test invalid URL
        $dto->setWebsite('not-a-url');
        $this->assertFalse($dto->validate());
        
        // Test valid URLs
        $validUrls = [
            'http://example.com',
            'https://example.com',
            'https://www.example.com/page',
            'http://subdomain.example.com'
        ];
        
        foreach ($validUrls as $url) {
            $dto->setWebsite($url);
            $this->assertTrue($dto->validate(), "URL should be valid: $url");
        }
    }
    
    /**
     * Test toArray method
     */
    public function testToArray()
    {
        $dto = new LeadDTO();
        $dto->setId('123')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john.doe@example.com')
            ->setPhoneMobile('+1234567890')
            ->setStatus('New')
            ->setLeadSource('Web Site')
            ->setLeadScore(75);
        
        $array = $dto->toArray();
        
        $this->assertEquals('123', $array['id']);
        $this->assertEquals('John', $array['first_name']);
        $this->assertEquals('Doe', $array['last_name']);
        $this->assertEquals('john.doe@example.com', $array['email']);
        $this->assertEquals('+1234567890', $array['phone_mobile']);
        $this->assertEquals('New', $array['status']);
        $this->assertEquals('Web Site', $array['lead_source']);
        $this->assertEquals(75, $array['lead_score']);
    }
    
    /**
     * Test fromBean method
     */
    public function testFromBean()
    {
        // Create mock bean
        $bean = new \stdClass();
        $bean->id = '123';
        $bean->first_name = 'John';
        $bean->last_name = 'Doe';
        $bean->email1 = 'john@example.com';
        $bean->phone_mobile = '+1234567890';
        $bean->status = 'New';
        $bean->lead_source = 'Web Site';
        $bean->lead_score_c = 75;
        $bean->converted = '0';
        
        $dto = LeadDTO::fromBean($bean);
        
        $this->assertEquals('123', $dto->getId());
        $this->assertEquals('John', $dto->getFirstName());
        $this->assertEquals('Doe', $dto->getLastName());
        $this->assertEquals('john@example.com', $dto->getEmail());
        $this->assertEquals('+1234567890', $dto->getPhoneMobile());
        $this->assertEquals('New', $dto->getStatus());
        $this->assertEquals('Web Site', $dto->getLeadSource());
        $this->assertEquals(75, $dto->getLeadScore());
        $this->assertEquals('0', $dto->getConverted());
    }
    
    /**
     * Test toBean method
     */
    public function testToBean()
    {
        // Create DTO
        $dto = new LeadDTO();
        $dto->setFirstName('Jane')
            ->setLastName('Smith')
            ->setEmail('jane@example.com')
            ->setPhoneMobile('+9876543210')
            ->setStatus('Assigned')
            ->setLeadScore(80);
        
        // Create mock bean
        $bean = new \stdClass();
        
        // Apply DTO to bean
        $dto->toBean($bean);
        
        $this->assertEquals('Jane', $bean->first_name);
        $this->assertEquals('Smith', $bean->last_name);
        $this->assertEquals('jane@example.com', $bean->email1);
        $this->assertEquals('+9876543210', $bean->phone_mobile);
        $this->assertEquals('Assigned', $bean->status);
        $this->assertEquals(80, $bean->lead_score_c);
    }
}