<?php
// Test script to verify DTOs and Controllers work with actual SuiteCRM modules

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once '/var/www/html/config.php';
require_once '/var/www/html/include/entryPoint.php';

// Load our custom API classes
require_once '/var/www/html/custom/api/dto/Base/BaseDTO.php';
require_once '/var/www/html/custom/api/dto/ContactDTO.php';
require_once '/var/www/html/custom/api/dto/LeadDTO.php';
require_once '/var/www/html/custom/api/dto/OpportunityDTO.php';

echo "=== Testing DTO Integration with SuiteCRM ===\n\n";

// Test 1: Create a Contact using SugarBean and convert to DTO
echo "Test 1: Create Contact and convert to DTO\n";
$contact = BeanFactory::newBean('Contacts');
$contact->first_name = 'Test';
$contact->last_name = 'Contact';
$contact->email1 = 'test@example.com';
$contact->phone_mobile = '+1234567890';
$contact->lead_source = 'Web Site';
$contact->save();

echo "Created contact with ID: {$contact->id}\n";

// Convert to DTO
$contactDTO = Api\DTO\ContactDTO::fromBean($contact);
echo "DTO First Name: " . $contactDTO->getFirstName() . "\n";
echo "DTO Last Name: " . $contactDTO->getLastName() . "\n";
echo "DTO Email: " . $contactDTO->getEmail() . "\n";
echo "DTO Phone: " . $contactDTO->getPhoneMobile() . "\n";

// Test 2: Update Contact from DTO
echo "\nTest 2: Update Contact from DTO\n";
$contactDTO->setFirstName('Updated');
$contactDTO->setEmail('updated@example.com');
$contactDTO->toBean($contact);
$contact->save();

// Reload to verify
$contact2 = BeanFactory::getBean('Contacts', $contact->id);
echo "Updated First Name: {$contact2->first_name}\n";
echo "Updated Email: {$contact2->email1}\n";

// Test 3: Create a Lead and test conversion fields
echo "\nTest 3: Create Lead with custom fields\n";
$lead = BeanFactory::newBean('Leads');
$lead->first_name = 'Test';
$lead->last_name = 'Lead';
$lead->email1 = 'lead@example.com';
$lead->status = 'New';
$lead->lead_source = 'Cold Call';

// Check if custom field exists
if (property_exists($lead, 'lead_score_c')) {
    $lead->lead_score_c = 75;
    echo "Custom field lead_score_c exists and set to 75\n";
} else {
    echo "WARNING: Custom field lead_score_c does not exist!\n";
}

$lead->save();

$leadDTO = Api\DTO\LeadDTO::fromBean($lead);
echo "Lead Status: " . $leadDTO->getStatus() . "\n";
echo "Lead Score: " . ($leadDTO->getLeadScore() ?? 'null') . "\n";

// Test 4: Create Opportunity with relationships
echo "\nTest 4: Create Opportunity with relationships\n";
$opp = BeanFactory::newBean('Opportunities');
$opp->name = 'Test Opportunity';
$opp->amount = 50000;
$opp->sales_stage = 'Proposal/Price Quote';
$opp->date_closed = date('Y-m-d', strtotime('+30 days'));
$opp->probability = 65;
$opp->account_name = 'Test Account';
$opp->save();

// Link to contact
if (method_exists($opp, 'load_relationship')) {
    $opp->load_relationship('contacts');
    $opp->contacts->add($contact->id);
    echo "Linked opportunity to contact\n";
}

$oppDTO = Api\DTO\OpportunityDTO::fromBean($opp);
echo "Opportunity Name: " . $oppDTO->getName() . "\n";
echo "Amount: " . $oppDTO->getAmount() . "\n";
echo "Sales Stage: " . $oppDTO->getSalesStage() . "\n";
echo "Probability: " . $oppDTO->getProbability() . "\n";

// Test 5: Validate DTOs
echo "\nTest 5: DTO Validation\n";
$invalidContact = new Api\DTO\ContactDTO();
$invalidContact->setEmail('invalid-email');
$isValid = $invalidContact->validate();
echo "Invalid email validation: " . ($isValid ? 'FAILED' : 'PASSED') . "\n";
if (!$isValid) {
    echo "Errors: " . json_encode($invalidContact->getErrors()) . "\n";
}

// Clean up test data
echo "\nCleaning up test data...\n";
$contact->mark_deleted($contact->id);
$lead->mark_deleted($lead->id);
$opp->mark_deleted($opp->id);

echo "\n=== Integration Test Complete ===\n";