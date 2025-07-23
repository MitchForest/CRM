<?php
namespace Tests\Integration\Controllers;

use Tests\Integration\SuiteCRMIntegrationTest;

class QuotesControllerTest extends SuiteCRMIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function testListQuotes()
    {
        // Create test quote
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Test Quote';
        $quote->stage = 'Draft';
        $quote->total_amount = 1000.00;
        $quote->save();

        $response = $this->makeRequest('GET', '/quotes');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
    }

    public function testGetQuote()
    {
        // Create test quote
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Detailed Quote';
        $quote->stage = 'Sent';
        $quote->number = 'Q-2024-001';
        $quote->expiration = date('Y-m-d', strtotime('+30 days'));
        $quote->subtotal_amount = 900.00;
        $quote->tax_amount = 90.00;
        $quote->shipping_amount = 10.00;
        $quote->total_amount = 1000.00;
        $quote->currency_id = '-99';
        $quote->save();

        $response = $this->makeRequest('GET', "/quotes/{$quote->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals($quote->id, $data['id']);
        $this->assertEquals('Detailed Quote', $data['name']);
        $this->assertEquals('Sent', $data['stage']);
        $this->assertEquals('Q-2024-001', $data['quoteNumber']);
        $this->assertEquals(900.00, $data['subtotal']);
        $this->assertEquals(90.00, $data['tax']);
        $this->assertEquals(10.00, $data['shipping']);
        $this->assertEquals(1000.00, $data['total']);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
    }

    public function testCreateQuote()
    {
        // Create opportunity to link
        $opportunity = \BeanFactory::newBean('Opportunities');
        $opportunity->name = 'Test Opportunity';
        $opportunity->amount = 5000;
        $opportunity->save();

        $quoteData = [
            'name' => 'New Quote',
            'stage' => 'Draft',
            'validUntil' => date('Y-m-d', strtotime('+14 days')),
            'opportunityId' => $opportunity->id,
            'terms' => 'Net 30',
            'description' => 'Quote for services'
        ];

        $response = $this->makeRequest('POST', '/quotes', $quoteData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Quote', $data['name']);
        $this->assertEquals('Draft', $data['stage']);
        $this->assertNotEmpty($data['quoteNumber']); // Auto-generated
        $this->assertEquals($opportunity->id, $data['opportunityId']);
        $this->assertEquals('Test Opportunity', $data['opportunityName']);
        
        // Cleanup
        $quote = \BeanFactory::getBean('AOS_Quotes', $data['id']);
        $quote->mark_deleted($quote->id);
        $opportunity->mark_deleted($opportunity->id);
    }

    public function testCreateQuoteWithLineItems()
    {
        $quoteData = [
            'name' => 'Quote with Line Items',
            'stage' => 'Draft',
            'lineItems' => [
                [
                    'name' => 'Product A',
                    'description' => 'High quality product',
                    'quantity' => 2,
                    'unitPrice' => 100.00,
                    'discount' => 10.00,
                    'tax' => 18.00
                ],
                [
                    'name' => 'Service B',
                    'description' => 'Professional service',
                    'quantity' => 1,
                    'unitPrice' => 500.00,
                    'tax' => 50.00
                ]
            ]
        ];

        $response = $this->makeRequest('POST', '/quotes', $quoteData);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('lineItems', $data);
        $this->assertCount(2, $data['lineItems']);
        
        // Verify first line item
        $this->assertEquals('Product A', $data['lineItems'][0]['name']);
        $this->assertEquals(2, $data['lineItems'][0]['quantity']);
        $this->assertEquals(100.00, $data['lineItems'][0]['unitPrice']);
        
        // Verify totals are calculated correctly
        // Product A: (2 * 100) - 10 + 18 = 208
        // Service B: (1 * 500) + 50 = 550
        // Total: 758
        $this->assertEquals(758.00, $data['total']);
        
        // Cleanup
        $quote = \BeanFactory::getBean('AOS_Quotes', $data['id']);
        $quote->mark_deleted($quote->id);
    }

    public function testUpdateQuote()
    {
        // Create test quote
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Original Quote';
        $quote->stage = 'Draft';
        $quote->save();

        $updateData = [
            'name' => 'Updated Quote',
            'stage' => 'Sent',
            'discount' => 50.00,
            'discountType' => 'Amount'
        ];

        $response = $this->makeRequest('PUT', "/quotes/{$quote->id}", $updateData);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Updated Quote', $data['name']);
        $this->assertEquals('Sent', $data['stage']);
        $this->assertEquals(50.00, $data['discount']);
        $this->assertEquals('Amount', $data['discountType']);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
    }

    public function testSendQuote()
    {
        // Create test quote with contact
        $contact = \BeanFactory::newBean('Contacts');
        $contact->first_name = 'John';
        $contact->last_name = 'Doe';
        $contact->email1 = 'john.doe@example.com';
        $contact->save();

        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Quote to Send';
        $quote->stage = 'Draft';
        $quote->billing_contact_id = $contact->id;
        $quote->save();

        $response = $this->makeRequest('POST', "/quotes/{$quote->id}/send");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Sent', $data['stage']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('sent successfully', $data['message']);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
        $contact->mark_deleted($contact->id);
    }

    public function testConvertToInvoice()
    {
        // Create quote with line items
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Quote to Convert';
        $quote->stage = 'Accepted';
        $quote->total_amount = 1500.00;
        $quote->save();

        // Add line items
        $lineItem = \BeanFactory::newBean('AOS_Products_Quotes');
        $lineItem->parent_type = 'AOS_Quotes';
        $lineItem->parent_id = $quote->id;
        $lineItem->name = 'Product for Invoice';
        $lineItem->product_qty = 1;
        $lineItem->product_unit_price = 1500.00;
        $lineItem->save();

        $response = $this->makeRequest('POST', "/quotes/{$quote->id}/convert");
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('invoiceId', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('converted to invoice', $data['message']);
        
        // Verify invoice was created
        $invoice = \BeanFactory::getBean('AOS_Invoices', $data['invoiceId']);
        $this->assertNotEmpty($invoice->id);
        $this->assertEquals($quote->name, $invoice->name);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
        $lineItem->mark_deleted($lineItem->id);
        $invoice->mark_deleted($invoice->id);
    }

    public function testDeleteQuote()
    {
        // Create test quote
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Quote to Delete';
        $quote->save();

        $response = $this->makeRequest('DELETE', "/quotes/{$quote->id}");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify quote is marked as deleted
        $deleted = \BeanFactory::getBean('AOS_Quotes', $quote->id);
        $this->assertEquals(1, $deleted->deleted);
    }

    public function testQuoteNotFound()
    {
        $response = $this->makeRequest('GET', '/quotes/invalid-id');
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Quote not found', $data['error']);
        $this->assertEquals('NOT_FOUND', $data['code']);
    }

    public function testQuoteValidation()
    {
        // Missing required fields
        $response = $this->makeRequest('POST', '/quotes', [
            'description' => 'Quote without name'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('required', strtolower($data['error']));
    }

    public function testInvalidQuoteStage()
    {
        $response = $this->makeRequest('POST', '/quotes', [
            'name' => 'Invalid Stage Quote',
            'stage' => 'InvalidStage'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
    }

    public function testSendQuoteWithoutContact()
    {
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Quote without Contact';
        $quote->stage = 'Draft';
        $quote->save();

        $response = $this->makeRequest('POST', "/quotes/{$quote->id}/send");
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
        $this->assertStringContainsString('contact email', $data['error']);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
    }

    public function testConvertDraftQuote()
    {
        $quote = \BeanFactory::newBean('AOS_Quotes');
        $quote->name = 'Draft Quote';
        $quote->stage = 'Draft';
        $quote->save();

        $response = $this->makeRequest('POST', "/quotes/{$quote->id}/convert");
        
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('FORBIDDEN', $data['code']);
        $this->assertStringContainsString('Only accepted quotes', $data['error']);
        
        // Cleanup
        $quote->mark_deleted($quote->id);
    }
}