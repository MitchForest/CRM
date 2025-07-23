<?php
namespace Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use Api\DTO\ContactDTO;

/**
 * Unit tests for ContactDTO
 */
class ContactDTOTest extends TestCase
{
    /**
     * Test required field validation
     */
    public function testRequiredFieldValidation()
    {
        $dto = new ContactDTO();
        
        // Should fail without required fields
        $this->assertFalse($dto->validate());
        $errors = $dto->getErrors();
        
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertEquals('First name is required', $errors['first_name']);
    }
    
    /**
     * Test email validation
     */
    public function testEmailValidation()
    {
        $dto = new ContactDTO();
        $dto->setFirstName('John')
            ->setLastName('Doe');
        
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
        $dto = new ContactDTO();
        $dto->setFirstName('John')
            ->setLastName('Doe');
        
        // Test invalid phone
        $dto->setPhoneMobile('123');
        $this->assertFalse($dto->validate());
        
        // Test valid phone formats
        $validPhones = [
            '+1234567890',
            '123-456-7890',
            '(123) 456-7890',
            '123.456.7890'
        ];
        
        foreach ($validPhones as $phone) {
            $dto->setPhoneMobile($phone);
            $this->assertTrue($dto->validate(), "Phone format should be valid: $phone");
        }
    }
    
    /**
     * Test lead source validation
     */
    public function testLeadSourceValidation()
    {
        $dto = new ContactDTO();
        $dto->setFirstName('John')
            ->setLastName('Doe');
        
        // Test invalid lead source
        $dto->setLeadSource('Invalid Source');
        $this->assertFalse($dto->validate());
        
        // Test valid lead sources
        $validSources = ['Cold Call', 'Existing Customer', 'Self Generated', 'Employee', 
                        'Partner', 'Public Relations', 'Direct Mail', 'Conference', 
                        'Trade Show', 'Web Site', 'Word of mouth', 'Email', 'Campaign', 'Other'];
        
        foreach ($validSources as $source) {
            $dto->setLeadSource($source);
            $this->assertTrue($dto->validate(), "Lead source should be valid: $source");
        }
    }
    
    /**
     * Test customer satisfaction validation
     */
    public function testCustomerSatisfactionValidation()
    {
        $dto = new ContactDTO();
        $dto->setFirstName('John')
            ->setLastName('Doe');
        
        // Test invalid values
        $dto->setCustomerSatisfaction(0);
        $this->assertFalse($dto->validate());
        
        $dto->setCustomerSatisfaction(6);
        $this->assertFalse($dto->validate());
        
        // Test valid values
        for ($i = 1; $i <= 5; $i++) {
            $dto->setCustomerSatisfaction($i);
            $this->assertTrue($dto->validate(), "Customer satisfaction $i should be valid");
        }
    }
    
    /**
     * Test date validation
     */
    public function testDateValidation()
    {
        $dto = new ContactDTO();
        $dto->setFirstName('John')
            ->setLastName('Doe');
        
        // Test invalid date
        $dto->setBirthdate('invalid-date');
        $this->assertFalse($dto->validate());
        
        // Test valid dates
        $validDates = [
            '2000-01-01',
            '1985-12-31',
            '2000-01-01 12:00:00'
        ];
        
        foreach ($validDates as $date) {
            $dto->setBirthdate($date);
            $this->assertTrue($dto->validate(), "Date should be valid: $date");
        }
    }
    
    /**
     * Test toArray method
     */
    public function testToArray()
    {
        $dto = new ContactDTO();
        $dto->setId('123')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john.doe@example.com')
            ->setPhoneMobile('+1234567890')
            ->setLeadSource('Web Site');
        
        $array = $dto->toArray();
        
        $this->assertEquals('123', $array['id']);
        $this->assertEquals('John', $array['firstName']);
        $this->assertEquals('Doe', $array['lastName']);
        $this->assertEquals('john.doe@example.com', $array['email']);
        $this->assertEquals('+1234567890', $array['phoneMobile']);
        $this->assertEquals('Web Site', $array['leadSource']);
    }
    
    /**
     * Test TypeScript interface generation
     */
    public function testTypeScriptGeneration()
    {
        $dto = new ContactDTO();
        $typescript = $dto->getTypeScriptInterface();
        
        // Verify interface contains expected fields
        $this->assertStringContainsString('export interface Contact {', $typescript);
        $this->assertStringContainsString('id?: string;', $typescript);
        $this->assertStringContainsString('firstName?: string;', $typescript);
        $this->assertStringContainsString('lastName?: string;', $typescript);
        $this->assertStringContainsString('email?: string;', $typescript);
        $this->assertStringContainsString('leadSource?: ', $typescript);
    }
    
    /**
     * Test Zod schema generation
     */
    public function testZodSchemaGeneration()
    {
        $dto = new ContactDTO();
        $zod = $dto->getZodSchema();
        
        // Verify schema contains expected validations
        $this->assertStringContainsString('export const ContactSchema = z.object({', $zod);
        $this->assertStringContainsString('firstName: z.string().min(1, "First name is required")', $zod);
        $this->assertStringContainsString('email: z.string().email().optional()', $zod);
        $this->assertStringContainsString('customerSatisfaction: z.number().min(1).max(5).optional()', $zod);
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
        $bean->lead_source = 'Web Site';
        $bean->date_entered = '2024-01-01 12:00:00';
        $bean->date_modified = '2024-01-02 12:00:00';
        $bean->deleted = 0;
        
        $dto = ContactDTO::fromBean($bean);
        
        $this->assertEquals('123', $dto->getId());
        $this->assertEquals('John', $dto->getFirstName());
        $this->assertEquals('Doe', $dto->getLastName());
        $this->assertEquals('john@example.com', $dto->getEmail());
        $this->assertEquals('+1234567890', $dto->getPhoneMobile());
        $this->assertEquals('Web Site', $dto->getLeadSource());
        $this->assertFalse($dto->getDeleted());
    }
    
    /**
     * Test toBean method
     */
    public function testToBean()
    {
        // Create DTO
        $dto = new ContactDTO();
        $dto->setFirstName('Jane')
            ->setLastName('Smith')
            ->setEmail('jane@example.com')
            ->setPhoneMobile('+9876543210')
            ->setLeadSource('Email');
        
        // Create mock bean
        $bean = new \stdClass();
        
        // Apply DTO to bean
        $dto->toBean($bean);
        
        $this->assertEquals('Jane', $bean->first_name);
        $this->assertEquals('Smith', $bean->last_name);
        $this->assertEquals('jane@example.com', $bean->email1);
        $this->assertEquals('+9876543210', $bean->phone_mobile);
        $this->assertEquals('Email', $bean->lead_source);
    }
}