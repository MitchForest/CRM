<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Quote Data Transfer Object
 */
class QuoteDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $quoteNumber = null;
    protected ?string $stage = null;
    protected ?string $validity = null;
    protected ?string $paymentTerms = null;
    protected ?string $approvalStatus = null;
    protected ?string $invoiceStatus = null;
    protected ?float $subtotalAmount = null;
    protected ?float $discountAmount = null;
    protected ?float $taxAmount = null;
    protected ?float $shippingAmount = null;
    protected ?float $totalAmount = null;
    protected ?string $currencyId = null;
    protected ?string $description = null;
    protected ?string $opportunityId = null;
    protected ?string $opportunityName = null;
    protected ?string $billingContactId = null;
    protected ?string $billingContactName = null;
    protected ?string $billingAccountId = null;
    protected ?string $billingAccountName = null;
    protected ?string $billingAddressStreet = null;
    protected ?string $billingAddressCity = null;
    protected ?string $billingAddressState = null;
    protected ?string $billingAddressPostalcode = null;
    protected ?string $billingAddressCountry = null;
    protected ?string $shippingAddressStreet = null;
    protected ?string $shippingAddressCity = null;
    protected ?string $shippingAddressState = null;
    protected ?string $shippingAddressPostalcode = null;
    protected ?string $shippingAddressCountry = null;
    protected ?string $assignedUserId = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?array $lineItems = null;
    protected ?string $termsConditions = null;
    protected ?string $approvalIssue = null;
    protected ?string $expiryDate = null;
    protected ?bool $deleted = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->name)) {
            $this->addError('name', 'Quote name is required');
        }
        
        // Stage validation
        $validStages = ['Draft', 'Negotiation', 'Delivered', 'On Hold', 'Confirmed', 'Closed Accepted', 'Closed Lost', 'Closed Dead'];
        if (!empty($this->stage) && !in_array($this->stage, $validStages)) {
            $this->addError('stage', 'Invalid quote stage');
        }
        
        // Approval status validation
        $validApprovalStatuses = ['Not Approved', 'Approved', 'Rejected'];
        if (!empty($this->approvalStatus) && !in_array($this->approvalStatus, $validApprovalStatuses)) {
            $this->addError('approval_status', 'Invalid approval status');
        }
        
        // Invoice status validation
        $validInvoiceStatuses = ['Not Invoiced', 'Invoiced', 'Paid'];
        if (!empty($this->invoiceStatus) && !in_array($this->invoiceStatus, $validInvoiceStatuses)) {
            $this->addError('invoice_status', 'Invalid invoice status');
        }
        
        // Amount validations
        if ($this->subtotalAmount !== null && $this->subtotalAmount < 0) {
            $this->addError('subtotal_amount', 'Subtotal amount must be non-negative');
        }
        
        if ($this->totalAmount !== null && $this->totalAmount < 0) {
            $this->addError('total_amount', 'Total amount must be non-negative');
        }
        
        if ($this->discountAmount !== null && $this->discountAmount < 0) {
            $this->addError('discount_amount', 'Discount amount must be non-negative');
        }
        
        if ($this->taxAmount !== null && $this->taxAmount < 0) {
            $this->addError('tax_amount', 'Tax amount must be non-negative');
        }
        
        if ($this->shippingAmount !== null && $this->shippingAmount < 0) {
            $this->addError('shipping_amount', 'Shipping amount must be non-negative');
        }
        
        // Line items validation
        if (!empty($this->lineItems)) {
            foreach ($this->lineItems as $index => $item) {
                if (empty($item['name'])) {
                    $this->addError("line_items.$index.name", 'Line item name is required');
                }
                if (isset($item['quantity']) && $item['quantity'] <= 0) {
                    $this->addError("line_items.$index.quantity", 'Line item quantity must be positive');
                }
                if (isset($item['unit_price']) && $item['unit_price'] < 0) {
                    $this->addError("line_items.$index.unit_price", 'Line item unit price must be non-negative');
                }
            }
        }
        
        // Date validation
        if (!empty($this->expiryDate) && !strtotime($this->expiryDate)) {
            $this->addError('expiry_date', 'Invalid expiry date format');
        }
    }
    
    // Getters and Setters
    public function getId(): ?string
    {
        return $this->id;
    }
    
    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function getQuoteNumber(): ?string
    {
        return $this->quoteNumber;
    }
    
    public function setQuoteNumber(?string $quoteNumber): self
    {
        $this->quoteNumber = $quoteNumber;
        return $this;
    }
    
    public function getStage(): ?string
    {
        return $this->stage;
    }
    
    public function setStage(?string $stage): self
    {
        $this->stage = $stage;
        return $this;
    }
    
    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }
    
    public function setTotalAmount(?float $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }
    
    public function getLineItems(): ?array
    {
        return $this->lineItems;
    }
    
    public function setLineItems(?array $lineItems): self
    {
        $this->lineItems = $lineItems;
        
        // Recalculate totals if line items are set
        if ($lineItems !== null) {
            $this->recalculateTotals();
        }
        
        return $this;
    }
    
    /**
     * Recalculate totals based on line items
     */
    protected function recalculateTotals(): void
    {
        if (empty($this->lineItems)) {
            return;
        }
        
        $subtotal = 0;
        foreach ($this->lineItems as $item) {
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? 0;
            $discount = $item['discount'] ?? 0;
            
            $lineTotal = ($quantity * $unitPrice) - $discount;
            $subtotal += $lineTotal;
        }
        
        $this->subtotalAmount = $subtotal;
        
        // Calculate total
        $this->totalAmount = $this->subtotalAmount 
            - ($this->discountAmount ?? 0) 
            + ($this->taxAmount ?? 0) 
            + ($this->shippingAmount ?? 0);
    }
    
    // Additional setters/getters for other properties...
    
    /**
     * Create from SugarBean
     */
    public static function fromBean($bean): self
    {
        $dto = new self();
        
        $dto->setId($bean->id)
            ->setName($bean->name)
            ->setQuoteNumber($bean->quote_number)
            ->setStage($bean->stage)
            ->setValidity($bean->validity)
            ->setPaymentTerms($bean->payment_terms)
            ->setApprovalStatus($bean->approval_status)
            ->setInvoiceStatus($bean->invoice_status)
            ->setSubtotalAmount((float)$bean->subtotal_amount)
            ->setDiscountAmount((float)$bean->discount_amount)
            ->setTaxAmount((float)$bean->tax_amount)
            ->setShippingAmount((float)$bean->shipping_amount)
            ->setTotalAmount((float)$bean->total_amount)
            ->setCurrencyId($bean->currency_id)
            ->setDescription($bean->description)
            ->setOpportunityId($bean->opportunity_id)
            ->setBillingContactId($bean->billing_contact_id)
            ->setBillingAccountId($bean->billing_account_id)
            ->setBillingAddressStreet($bean->billing_address_street)
            ->setBillingAddressCity($bean->billing_address_city)
            ->setBillingAddressState($bean->billing_address_state)
            ->setBillingAddressPostalcode($bean->billing_address_postalcode)
            ->setBillingAddressCountry($bean->billing_address_country)
            ->setShippingAddressStreet($bean->shipping_address_street)
            ->setShippingAddressCity($bean->shipping_address_city)
            ->setShippingAddressState($bean->shipping_address_state)
            ->setShippingAddressPostalcode($bean->shipping_address_postalcode)
            ->setShippingAddressCountry($bean->shipping_address_country)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setTermsConditions($bean->terms_conditions)
            ->setApprovalIssue($bean->approval_issue)
            ->setDeleted((bool)$bean->deleted);
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->name !== null) $bean->name = $this->name;
        if ($this->stage !== null) $bean->stage = $this->stage;
        if ($this->validity !== null) $bean->validity = $this->validity;
        if ($this->paymentTerms !== null) $bean->payment_terms = $this->paymentTerms;
        if ($this->approvalStatus !== null) $bean->approval_status = $this->approvalStatus;
        if ($this->invoiceStatus !== null) $bean->invoice_status = $this->invoiceStatus;
        if ($this->subtotalAmount !== null) $bean->subtotal_amount = $this->subtotalAmount;
        if ($this->discountAmount !== null) $bean->discount_amount = $this->discountAmount;
        if ($this->taxAmount !== null) $bean->tax_amount = $this->taxAmount;
        if ($this->shippingAmount !== null) $bean->shipping_amount = $this->shippingAmount;
        if ($this->totalAmount !== null) $bean->total_amount = $this->totalAmount;
        if ($this->currencyId !== null) $bean->currency_id = $this->currencyId;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->opportunityId !== null) $bean->opportunity_id = $this->opportunityId;
        if ($this->billingContactId !== null) $bean->billing_contact_id = $this->billingContactId;
        if ($this->billingAccountId !== null) $bean->billing_account_id = $this->billingAccountId;
        if ($this->billingAddressStreet !== null) $bean->billing_address_street = $this->billingAddressStreet;
        if ($this->billingAddressCity !== null) $bean->billing_address_city = $this->billingAddressCity;
        if ($this->billingAddressState !== null) $bean->billing_address_state = $this->billingAddressState;
        if ($this->billingAddressPostalcode !== null) $bean->billing_address_postalcode = $this->billingAddressPostalcode;
        if ($this->billingAddressCountry !== null) $bean->billing_address_country = $this->billingAddressCountry;
        if ($this->shippingAddressStreet !== null) $bean->shipping_address_street = $this->shippingAddressStreet;
        if ($this->shippingAddressCity !== null) $bean->shipping_address_city = $this->shippingAddressCity;
        if ($this->shippingAddressState !== null) $bean->shipping_address_state = $this->shippingAddressState;
        if ($this->shippingAddressPostalcode !== null) $bean->shipping_address_postalcode = $this->shippingAddressPostalcode;
        if ($this->shippingAddressCountry !== null) $bean->shipping_address_country = $this->shippingAddressCountry;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->termsConditions !== null) $bean->terms_conditions = $this->termsConditions;
        if ($this->approvalIssue !== null) $bean->approval_issue = $this->approvalIssue;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Quote {
  id?: string;
  name?: string;
  quoteNumber?: string;
  stage?: 'Draft' | 'Negotiation' | 'Delivered' | 'On Hold' | 'Confirmed' | 'Closed Accepted' | 'Closed Lost' | 'Closed Dead';
  validity?: string;
  paymentTerms?: string;
  approvalStatus?: 'Not Approved' | 'Approved' | 'Rejected';
  invoiceStatus?: 'Not Invoiced' | 'Invoiced' | 'Paid';
  subtotalAmount?: number;
  discountAmount?: number;
  taxAmount?: number;
  shippingAmount?: number;
  totalAmount?: number;
  currencyId?: string;
  description?: string;
  opportunityId?: string;
  opportunityName?: string;
  billingContactId?: string;
  billingContactName?: string;
  billingAccountId?: string;
  billingAccountName?: string;
  billingAddressStreet?: string;
  billingAddressCity?: string;
  billingAddressState?: string;
  billingAddressPostalcode?: string;
  billingAddressCountry?: string;
  shippingAddressStreet?: string;
  shippingAddressCity?: string;
  shippingAddressState?: string;
  shippingAddressPostalcode?: string;
  shippingAddressCountry?: string;
  assignedUserId?: string;
  dateEntered?: string;
  dateModified?: string;
  lineItems?: QuoteLineItem[];
  termsConditions?: string;
  approvalIssue?: string;
  expiryDate?: string;
  deleted?: boolean;
}

export interface QuoteLineItem {
  id?: string;
  productId?: string;
  name: string;
  description?: string;
  quantity: number;
  unitPrice: number;
  totalPrice?: number;
  discount?: number;
  discountAmount?: number;
  tax?: number;
  taxAmount?: number;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const QuoteLineItemSchema = z.object({
  id: z.string().optional(),
  productId: z.string().optional(),
  name: z.string().min(1, "Line item name is required"),
  description: z.string().optional(),
  quantity: z.number().positive("Quantity must be positive"),
  unitPrice: z.number().min(0, "Unit price must be non-negative"),
  totalPrice: z.number().optional(),
  discount: z.number().min(0).optional(),
  discountAmount: z.number().min(0).optional(),
  tax: z.number().min(0).optional(),
  taxAmount: z.number().min(0).optional()
});

export const QuoteSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1, "Quote name is required"),
  quoteNumber: z.string().optional(),
  stage: z.enum(['Draft', 'Negotiation', 'Delivered', 'On Hold', 'Confirmed', 'Closed Accepted', 'Closed Lost', 'Closed Dead']).optional(),
  validity: z.string().optional(),
  paymentTerms: z.string().optional(),
  approvalStatus: z.enum(['Not Approved', 'Approved', 'Rejected']).optional(),
  invoiceStatus: z.enum(['Not Invoiced', 'Invoiced', 'Paid']).optional(),
  subtotalAmount: z.number().min(0).optional(),
  discountAmount: z.number().min(0).optional(),
  taxAmount: z.number().min(0).optional(),
  shippingAmount: z.number().min(0).optional(),
  totalAmount: z.number().min(0).optional(),
  currencyId: z.string().optional(),
  description: z.string().optional(),
  opportunityId: z.string().optional(),
  opportunityName: z.string().optional(),
  billingContactId: z.string().optional(),
  billingContactName: z.string().optional(),
  billingAccountId: z.string().optional(),
  billingAccountName: z.string().optional(),
  billingAddressStreet: z.string().optional(),
  billingAddressCity: z.string().optional(),
  billingAddressState: z.string().optional(),
  billingAddressPostalcode: z.string().optional(),
  billingAddressCountry: z.string().optional(),
  shippingAddressStreet: z.string().optional(),
  shippingAddressCity: z.string().optional(),
  shippingAddressState: z.string().optional(),
  shippingAddressPostalcode: z.string().optional(),
  shippingAddressCountry: z.string().optional(),
  assignedUserId: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  lineItems: z.array(QuoteLineItemSchema).optional(),
  termsConditions: z.string().optional(),
  approvalIssue: z.string().optional(),
  expiryDate: z.string().optional(),
  deleted: z.boolean().optional()
});
TS;
    }
}