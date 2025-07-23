<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Lead Data Transfer Object
 */
class LeadDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $firstName = null;
    protected ?string $lastName = null;
    protected ?string $email = null;
    protected ?string $phoneMobile = null;
    protected ?string $phoneWork = null;
    protected ?string $title = null;
    protected ?string $department = null;
    protected ?string $status = null;
    protected ?string $statusDescription = null;
    protected ?string $leadSource = null;
    protected ?string $leadSourceDescription = null;
    protected ?string $description = null;
    protected ?string $accountName = null;
    protected ?string $website = null;
    protected ?string $addressStreet = null;
    protected ?string $addressCity = null;
    protected ?string $addressState = null;
    protected ?string $addressPostalcode = null;
    protected ?string $addressCountry = null;
    protected ?string $assignedUserId = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $converted = null;
    protected ?string $contactId = null;
    protected ?string $accountId = null;
    protected ?string $opportunityId = null;
    protected ?float $opportunityAmount = null;
    protected ?string $campaignId = null;
    protected ?int $leadScore = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->lastName)) {
            $this->addError('last_name', 'Last name is required');
        }
        
        // Email validation
        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Invalid email format');
        }
        
        // Phone validation
        if (!empty($this->phoneMobile) && !preg_match('/^[\+\-\(\)\s\d]+$/', $this->phoneMobile)) {
            $this->addError('phone_mobile', 'Invalid phone number format');
        }
        
        // Status validation
        $validStatuses = ['New', 'Assigned', 'In Process', 'Converted', 'Recycled', 'Dead'];
        if (!empty($this->status) && !in_array($this->status, $validStatuses)) {
            $this->addError('status', 'Invalid lead status');
        }
        
        // Lead score validation
        if ($this->leadScore !== null && ($this->leadScore < 0 || $this->leadScore > 100)) {
            $this->addError('lead_score', 'Lead score must be between 0 and 100');
        }
        
        // URL validation
        if (!empty($this->website) && !filter_var($this->website, FILTER_VALIDATE_URL)) {
            $this->addError('website', 'Invalid website URL');
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
    
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }
    
    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }
    
    public function getLastName(): ?string
    {
        return $this->lastName;
    }
    
    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }
    
    public function getEmail(): ?string
    {
        return $this->email;
    }
    
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }
    
    public function getLeadSource(): ?string
    {
        return $this->leadSource;
    }
    
    public function setLeadSource(?string $leadSource): self
    {
        $this->leadSource = $leadSource;
        return $this;
    }
    
    // Additional setters/getters for other properties...
    
    /**
     * Create from SugarBean
     */
    public static function fromBean($bean): self
    {
        $dto = new self();
        
        $dto->setId($bean->id)
            ->setFirstName($bean->first_name)
            ->setLastName($bean->last_name)
            ->setEmail($bean->email1)
            ->setPhoneMobile($bean->phone_mobile)
            ->setPhoneWork($bean->phone_work)
            ->setTitle($bean->title)
            ->setDepartment($bean->department)
            ->setStatus($bean->status)
            ->setStatusDescription($bean->status_description)
            ->setLeadSource($bean->lead_source)
            ->setLeadSourceDescription($bean->lead_source_description)
            ->setDescription($bean->description)
            ->setAccountName($bean->account_name)
            ->setWebsite($bean->website)
            ->setAddressStreet($bean->primary_address_street)
            ->setAddressCity($bean->primary_address_city)
            ->setAddressState($bean->primary_address_state)
            ->setAddressPostalcode($bean->primary_address_postalcode)
            ->setAddressCountry($bean->primary_address_country)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setConverted($bean->converted)
            ->setContactId($bean->contact_id)
            ->setAccountId($bean->account_id)
            ->setOpportunityId($bean->opportunity_id)
            ->setOpportunityAmount($bean->opportunity_amount)
            ->setCampaignId($bean->campaign_id);
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->firstName !== null) $bean->first_name = $this->firstName;
        if ($this->lastName !== null) $bean->last_name = $this->lastName;
        if ($this->email !== null) $bean->email1 = $this->email;
        if ($this->phoneMobile !== null) $bean->phone_mobile = $this->phoneMobile;
        if ($this->phoneWork !== null) $bean->phone_work = $this->phoneWork;
        if ($this->title !== null) $bean->title = $this->title;
        if ($this->department !== null) $bean->department = $this->department;
        if ($this->status !== null) $bean->status = $this->status;
        if ($this->statusDescription !== null) $bean->status_description = $this->statusDescription;
        if ($this->leadSource !== null) $bean->lead_source = $this->leadSource;
        if ($this->leadSourceDescription !== null) $bean->lead_source_description = $this->leadSourceDescription;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->accountName !== null) $bean->account_name = $this->accountName;
        if ($this->website !== null) $bean->website = $this->website;
        if ($this->addressStreet !== null) $bean->primary_address_street = $this->addressStreet;
        if ($this->addressCity !== null) $bean->primary_address_city = $this->addressCity;
        if ($this->addressState !== null) $bean->primary_address_state = $this->addressState;
        if ($this->addressPostalcode !== null) $bean->primary_address_postalcode = $this->addressPostalcode;
        if ($this->addressCountry !== null) $bean->primary_address_country = $this->addressCountry;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->campaignId !== null) $bean->campaign_id = $this->campaignId;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Lead {
  id?: string;
  firstName?: string;
  lastName?: string;
  email?: string;
  phoneMobile?: string;
  phoneWork?: string;
  title?: string;
  department?: string;
  status?: 'New' | 'Assigned' | 'In Process' | 'Converted' | 'Recycled' | 'Dead';
  statusDescription?: string;
  leadSource?: string;
  leadSourceDescription?: string;
  description?: string;
  accountName?: string;
  website?: string;
  addressStreet?: string;
  addressCity?: string;
  addressState?: string;
  addressPostalcode?: string;
  addressCountry?: string;
  assignedUserId?: string;
  dateEntered?: string;
  dateModified?: string;
  converted?: string;
  contactId?: string;
  accountId?: string;
  opportunityId?: string;
  opportunityAmount?: number;
  campaignId?: string;
  leadScore?: number;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const LeadSchema = z.object({
  id: z.string().optional(),
  firstName: z.string().optional(),
  lastName: z.string().min(1, "Last name is required"),
  email: z.string().email().optional(),
  phoneMobile: z.string().regex(/^[\+\-\(\)\s\d]+$/, "Invalid phone format").optional(),
  phoneWork: z.string().optional(),
  title: z.string().optional(),
  department: z.string().optional(),
  status: z.enum(['New', 'Assigned', 'In Process', 'Converted', 'Recycled', 'Dead']).optional(),
  statusDescription: z.string().optional(),
  leadSource: z.string().optional(),
  leadSourceDescription: z.string().optional(),
  description: z.string().optional(),
  accountName: z.string().optional(),
  website: z.string().url().optional(),
  addressStreet: z.string().optional(),
  addressCity: z.string().optional(),
  addressState: z.string().optional(),
  addressPostalcode: z.string().optional(),
  addressCountry: z.string().optional(),
  assignedUserId: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  converted: z.string().optional(),
  contactId: z.string().optional(),
  accountId: z.string().optional(),
  opportunityId: z.string().optional(),
  opportunityAmount: z.number().optional(),
  campaignId: z.string().optional(),
  leadScore: z.number().min(0).max(100).optional()
});
TS;
    }
}