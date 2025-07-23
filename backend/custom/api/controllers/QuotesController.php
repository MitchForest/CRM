<?php
namespace Api\Controllers;

class QuotesController extends BaseController
{
    
    public function list($request, $response)
    {
        $bean = \BeanFactory::newBean('AOS_Quotes');
        
        // Get filters
        $filters = $request->getQueryParam('filters', []);
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $request->getQueryParam('sort', 'date_entered');
        $sortOrder = $request->getQueryParam('order', 'DESC');
        
        // Get pagination
        list($limit, $offset) = $this->getPaginationParams($request);
        
        // Build query
        $query = $bean->create_new_list_query(
            "$sortField $sortOrder",
            $where,
            [],
            [],
            0,
            '',
            true,
            $bean,
            true
        );
        
        // Get total count
        $countResult = $bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = $bean->db->fetchByAssoc($countResult)['total'];
        
        // Add limit and offset
        $query .= " LIMIT $limit OFFSET $offset";
        
        // Execute query
        $result = $bean->db->query($query);
        $quotes = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $quote = \BeanFactory::newBean('AOS_Quotes');
            $quote->populateFromRow($row);
            $quoteData = $this->formatBean($quote);
            
            // Add related data
            $quoteData['opportunity_name'] = $this->getRelatedName('Opportunities', $quote->opportunity_id);
            $quoteData['contact_name'] = $this->getRelatedName('Contacts', $quote->billing_contact_id);
            
            $quotes[] = $quoteData;
        }
        
        return $response->json([
            'data' => $quotes,
            'pagination' => [
                'page' => (int)$request->getQueryParam('page', 1),
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function get($request, $response)
    {
        $id = $request->getParam('id');
        $quote = \BeanFactory::getBean('AOS_Quotes', $id);
        
        if (empty($quote->id)) {
            return $this->notFoundResponse($response, 'Quote');
        }
        
        $data = $this->formatBean($quote);
        
        // Add related data
        $data['opportunity_name'] = $this->getRelatedName('Opportunities', $quote->opportunity_id);
        $data['contact_name'] = $this->getRelatedName('Contacts', $quote->billing_contact_id);
        
        // Get line items
        $data['line_items'] = $this->getLineItems($quote);
        
        return $response->json($data);
    }
    
    public function create($request, $response)
    {
        $data = json_decode($request->getBody(), true);
        $quote = \BeanFactory::newBean('AOS_Quotes');
        
        // Set fields
        $fields = [
            'name', 'stage', 'validity', 'payment_terms', 'approval_status',
            'invoice_status', 'subtotal_amount', 'discount_amount', 'tax_amount',
            'shipping_amount', 'total_amount', 'currency_id', 'description',
            'opportunity_id', 'billing_contact_id', 'billing_account_id',
            'billing_address_street', 'billing_address_city', 'billing_address_state',
            'billing_address_postalcode', 'billing_address_country',
            'shipping_address_street', 'shipping_address_city', 'shipping_address_state',
            'shipping_address_postalcode', 'shipping_address_country'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $quote->$field = $data[$field];
            }
        }
        
        // Validate required fields
        if (empty($quote->name)) {
            return $this->validationErrorResponse($response, 'Quote name is required', ['name' => ['Quote name is required']]);
        }
        
        // Set defaults
        if (empty($quote->stage)) {
            $quote->stage = 'Draft';
        }
        
        if (empty($quote->quote_number)) {
            $quote->quote_number = $this->generateQuoteNumber();
        }
        
        // Calculate totals if not provided
        if ($quote->total_amount === null) {
            $quote->total_amount = 
                ($quote->subtotal_amount ?? 0) - 
                ($quote->discount_amount ?? 0) + 
                ($quote->tax_amount ?? 0) + 
                ($quote->shipping_amount ?? 0);
        }
        
        // Save
        $quote->save();
        
        // Handle line items
        $lineItems = $data['line_items'] ?? [];
        if (!empty($lineItems)) {
            $this->saveLineItems($quote, $lineItems);
        }
        
        return $response->json($this->formatBean($quote), 201);
    }
    
    public function update($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        $quote = \BeanFactory::getBean('AOS_Quotes', $id);
        
        if (empty($quote->id)) {
            return $this->notFoundResponse($response, 'Quote');
        }
        
        // Update fields
        $fields = [
            'name', 'stage', 'validity', 'payment_terms', 'approval_status',
            'invoice_status', 'subtotal_amount', 'discount_amount', 'tax_amount',
            'shipping_amount', 'total_amount', 'currency_id', 'description',
            'opportunity_id', 'billing_contact_id', 'billing_account_id',
            'billing_address_street', 'billing_address_city', 'billing_address_state',
            'billing_address_postalcode', 'billing_address_country',
            'shipping_address_street', 'shipping_address_city', 'shipping_address_state',
            'shipping_address_postalcode', 'shipping_address_country'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $quote->$field = $data[$field];
            }
        }
        
        // Recalculate totals if components changed
        if (!empty($data['recalculate_totals'])) {
            $quote->total_amount = 
                ($quote->subtotal_amount ?? 0) - 
                ($quote->discount_amount ?? 0) + 
                ($quote->tax_amount ?? 0) + 
                ($quote->shipping_amount ?? 0);
        }
        
        // Save
        $quote->save();
        
        // Handle line items update
        $lineItems = $data['line_items'] ?? null;
        if ($lineItems !== null) {
            $this->updateLineItems($quote, $lineItems);
        }
        
        return $response->json($this->formatBean($quote));
    }
    
    public function delete($request, $response)
    {
        $id = $request->getParam('id');
        $quote = \BeanFactory::getBean('AOS_Quotes', $id);
        
        if (empty($quote->id)) {
            return $this->notFoundResponse($response, 'Quote');
        }
        
        // Check if quote can be deleted
        if ($quote->stage === 'Delivered' || $quote->invoice_status === 'Paid') {
            return $this->forbiddenResponse($response, 'Cannot delete delivered or paid quotes');
        }
        
        // Delete line items first
        $this->deleteLineItems($quote);
        
        // Delete quote
        $quote->mark_deleted($id);
        
        return $response->json(['message' => 'Quote deleted successfully']);
    }
    
    /**
     * Send quote to customer
     */
    public function send($request, $response)
    {
        $id = $request->getParam('id');
        $quote = \BeanFactory::getBean('AOS_Quotes', $id);
        
        if (empty($quote->id)) {
            return $this->notFoundResponse($response, 'Quote');
        }
        
        // Validate quote is ready to send
        if ($quote->stage === 'Draft') {
            return $this->validationErrorResponse($response, 'Cannot send draft quotes');
        }
        
        if (empty($quote->billing_contact_id)) {
            return $this->validationErrorResponse($response, 'No billing contact specified', ['billing_contact_id' => ['Billing contact is required']]);
        }
        
        // Get contact email
        $contact = \BeanFactory::getBean('Contacts', $quote->billing_contact_id);
        if (empty($contact->email1)) {
            return $this->validationErrorResponse($response, 'Contact has no email address');
        }
        
        // TODO: Generate PDF and send email
        // For now, just update status
        $quote->stage = 'Delivered';
        $quote->save();
        
        return $response->json([
            'message' => 'Quote sent successfully',
            'recipient' => $contact->email1
        ]);
    }
    
    /**
     * Convert quote to invoice
     */
    public function convertToInvoice($request, $response)
    {
        $id = $request->getParam('id');
        $quote = \BeanFactory::getBean('AOS_Quotes', $id);
        
        if (empty($quote->id)) {
            return $this->notFoundResponse($response, 'Quote');
        }
        
        // Validate quote can be converted
        if ($quote->approval_status !== 'Approved') {
            return $this->validationErrorResponse($response, 'Quote must be approved before converting to invoice');
        }
        
        // Create invoice from quote
        $invoice = \BeanFactory::newBean('AOS_Invoices');
        
        // Copy fields from quote
        $invoice->name = 'Invoice for ' . $quote->name;
        $invoice->quote_id = $quote->id;
        $invoice->billing_account_id = $quote->billing_account_id;
        $invoice->billing_contact_id = $quote->billing_contact_id;
        $invoice->billing_address_street = $quote->billing_address_street;
        $invoice->billing_address_city = $quote->billing_address_city;
        $invoice->billing_address_state = $quote->billing_address_state;
        $invoice->billing_address_postalcode = $quote->billing_address_postalcode;
        $invoice->billing_address_country = $quote->billing_address_country;
        $invoice->shipping_address_street = $quote->shipping_address_street;
        $invoice->shipping_address_city = $quote->shipping_address_city;
        $invoice->shipping_address_state = $quote->shipping_address_state;
        $invoice->shipping_address_postalcode = $quote->shipping_address_postalcode;
        $invoice->shipping_address_country = $quote->shipping_address_country;
        $invoice->subtotal_amount = $quote->subtotal_amount;
        $invoice->discount_amount = $quote->discount_amount;
        $invoice->tax_amount = $quote->tax_amount;
        $invoice->shipping_amount = $quote->shipping_amount;
        $invoice->total_amount = $quote->total_amount;
        $invoice->currency_id = $quote->currency_id;
        $invoice->status = 'Unpaid';
        
        $invoice->save();
        
        // Copy line items
        $lineItems = $this->getLineItems($quote);
        foreach ($lineItems as $item) {
            $invoiceItem = \BeanFactory::newBean('AOS_Products_Quotes');
            $invoiceItem->parent_type = 'AOS_Invoices';
            $invoiceItem->parent_id = $invoice->id;
            $invoiceItem->product_id = $item['product_id'];
            $invoiceItem->name = $item['name'];
            $invoiceItem->product_qty = $item['quantity'];
            $invoiceItem->product_unit_price = $item['unit_price'];
            $invoiceItem->product_total_price = $item['total_price'];
            $invoiceItem->save();
        }
        
        // Update quote status
        $quote->invoice_status = 'Invoiced';
        $quote->save();
        
        return $response->json([
            'message' => 'Quote converted to invoice successfully',
            'invoice_id' => $invoice->id
        ]);
    }
    
    /**
     * Get default fields for quotes
     */
    protected function getDefaultFields($module) {
        if ($module === 'AOS_Quotes') {
            return [
                'id', 'name', 'quote_number', 'stage', 'validity', 'payment_terms',
                'approval_status', 'invoice_status', 'subtotal_amount', 'discount_amount',
                'tax_amount', 'shipping_amount', 'total_amount', 'currency_id',
                'opportunity_id', 'billing_contact_id', 'billing_account_id',
                'date_entered', 'date_modified', 'assigned_user_id'
            ];
        }
        return parent::getDefaultFields($module);
    }
    
    /**
     * Generate unique quote number
     */
    private function generateQuoteNumber() {
        global $db;
        
        $year = date('Y');
        $prefix = "Q{$year}-";
        
        // Get the last quote number for this year
        $query = "SELECT MAX(CAST(SUBSTRING(quote_number, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) as max_num 
                  FROM aos_quotes 
                  WHERE quote_number LIKE '{$prefix}%' 
                  AND deleted = 0";
        
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        $nextNumber = ($row['max_num'] ?? 0) + 1;
        
        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get related record name
     */
    private function getRelatedName($module, $id) {
        if (empty($id)) {
            return null;
        }
        
        $bean = \BeanFactory::getBean($module, $id);
        
        if ($module === 'Contacts') {
            return trim($bean->first_name . ' ' . $bean->last_name);
        }
        
        return $bean->name ?? null;
    }
    
    /**
     * Get quote line items
     */
    private function getLineItems($quote) {
        global $db;
        
        $items = [];
        
        $query = "SELECT * FROM aos_products_quotes 
                  WHERE parent_type = 'AOS_Quotes' 
                  AND parent_id = '{$quote->id}' 
                  AND deleted = 0 
                  ORDER BY number ASC";
        
        $result = $db->query($query);
        
        while ($row = $db->fetchByAssoc($result)) {
            $items[] = [
                'id' => $row['id'],
                'product_id' => $row['product_id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'quantity' => (float)$row['product_qty'],
                'unit_price' => (float)$row['product_unit_price'],
                'total_price' => (float)$row['product_total_price'],
                'discount' => (float)$row['product_discount'],
                'discount_amount' => (float)$row['product_discount_amount']
            ];
        }
        
        return $items;
    }
    
    /**
     * Save line items for a quote
     */
    private function saveLineItems($quote, $items) {
        $number = 1;
        
        foreach ($items as $item) {
            $lineItem = \BeanFactory::newBean('AOS_Products_Quotes');
            $lineItem->parent_type = 'AOS_Quotes';
            $lineItem->parent_id = $quote->id;
            $lineItem->number = $number++;
            $lineItem->product_id = $item['product_id'] ?? null;
            $lineItem->name = $item['name'] ?? '';
            $lineItem->description = $item['description'] ?? '';
            $lineItem->product_qty = $item['quantity'] ?? 1;
            $lineItem->product_unit_price = $item['unit_price'] ?? 0;
            $lineItem->product_total_price = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
            $lineItem->product_discount = $item['discount'] ?? 0;
            $lineItem->product_discount_amount = $item['discount_amount'] ?? 0;
            $lineItem->save();
        }
    }
    
    /**
     * Update line items for a quote
     */
    private function updateLineItems($quote, $items) {
        // Delete existing items
        $this->deleteLineItems($quote);
        
        // Save new items
        $this->saveLineItems($quote, $items);
    }
    
    /**
     * Delete all line items for a quote
     */
    private function deleteLineItems($quote) {
        global $db;
        
        $query = "UPDATE aos_products_quotes 
                  SET deleted = 1 
                  WHERE parent_type = 'AOS_Quotes' 
                  AND parent_id = '{$quote->id}'";
        
        $db->query($query);
    }
}