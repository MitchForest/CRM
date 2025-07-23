<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Contact Data Transfer Object
 */
class ContactDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $firstName = null;
    protected ?string $lastName = null;
    protected ?string $email = null;
    protected ?string $phoneMobile = null;
    protected ?string $phoneWork = null;
    protected ?string $phoneHome = null;
    protected ?string $description = null;
    protected ?string $addressStreet = null;
    protected ?string $addressCity = null;
    protected ?string $addressState = null;
    protected ?string $addressPostalcode = null;
    protected ?string $addressCountry = null;
    protected ?string $leadSource = null;
    protected ?string $status = null;
    protected ?string $title = null;
    protected ?string $department = null;
    protected ?string $doNotCall = null;
    protected ?string $assignedUserId = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?float $lifetimeValue = null;
    protected ?string $lastActivityDate = null;
    protected ?string $preferredContactMethod = null;
    protected ?string $subscriptionStatus = null;
    protected ?int $engagementScore = null;
    protected ?string $churnRisk = null;
    protected ?array $productInterests = null;
    
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
        
        // Phone validation (basic)
        if (!empty($this->phoneMobile) && !preg_match('/^[\+\-\(\)\s\d]+$/', $this->phoneMobile)) {
            $this->addError('phone_mobile', 'Invalid phone number format');
        }
        
        // Enum validations
        if (!empty($this->subscriptionStatus) && !in_array($this->subscriptionStatus, ['trial', 'active', 'cancelled', 'expired'])) {
            $this->addError('subscription_status', 'Invalid subscription status');
        }
        
        if (!empty($this->churnRisk) && !in_array($this->churnRisk, ['low', 'medium', 'high'])) {
            $this->addError('churn_risk', 'Invalid churn risk level');
        }
        
        if (!empty($this->preferredContactMethod) && !in_array($this->preferredContactMethod, ['email', 'phone', 'chat'])) {
            $this->addError('preferred_contact_method', 'Invalid contact method');
        }
        
        // Numeric validations
        if ($this->lifetimeValue !== null && $this->lifetimeValue < 0) {
            $this->addError('lifetime_value', 'Lifetime value must be non-negative');
        }
        
        if ($this->engagementScore !== null && ($this->engagementScore < 0 || $this->engagementScore > 100)) {
            $this->addError('engagement_score', 'Engagement score must be between 0 and 100');
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
    
    public function getPhoneMobile(): ?string
    {
        return $this->phoneMobile;
    }
    
    public function setPhoneMobile(?string $phoneMobile): self
    {
        $this->phoneMobile = $phoneMobile;
        return $this;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    
    public function getLifetimeValue(): ?float
    {
        return $this->lifetimeValue;
    }
    
    public function setLifetimeValue(?float $lifetimeValue): self
    {
        $this->lifetimeValue = $lifetimeValue;
        return $this;
    }
    
    public function getSubscriptionStatus(): ?string
    {
        return $this->subscriptionStatus;
    }
    
    public function setSubscriptionStatus(?string $subscriptionStatus): self
    {
        $this->subscriptionStatus = $subscriptionStatus;
        return $this;
    }
    
    // Additional setters/getters omitted for brevity - would include all properties
    
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
            ->setPhoneHome($bean->phone_home)
            ->setDescription($bean->description)
            ->setAddressStreet($bean->primary_address_street)
            ->setAddressCity($bean->primary_address_city)
            ->setAddressState($bean->primary_address_state)
            ->setAddressPostalcode($bean->primary_address_postalcode)
            ->setAddressCountry($bean->primary_address_country)
            ->setLeadSource($bean->lead_source)
            ->setTitle($bean->title)
            ->setDepartment($bean->department)
            ->setDoNotCall($bean->do_not_call)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified);
            
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
        if ($this->phoneHome !== null) $bean->phone_home = $this->phoneHome;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->addressStreet !== null) $bean->primary_address_street = $this->addressStreet;
        if ($this->addressCity !== null) $bean->primary_address_city = $this->addressCity;
        if ($this->addressState !== null) $bean->primary_address_state = $this->addressState;
        if ($this->addressPostalcode !== null) $bean->primary_address_postalcode = $this->addressPostalcode;
        if ($this->addressCountry !== null) $bean->primary_address_country = $this->addressCountry;
        if ($this->leadSource !== null) $bean->lead_source = $this->leadSource;
        if ($this->title !== null) $bean->title = $this->title;
        if ($this->department !== null) $bean->department = $this->department;
        if ($this->doNotCall !== null) $bean->do_not_call = $this->doNotCall;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Contact {
  id?: string;
  firstName?: string;
  lastName?: string;
  email?: string;
  phoneMobile?: string;
  phoneWork?: string;
  phoneHome?: string;
  description?: string;
  addressStreet?: string;
  addressCity?: string;
  addressState?: string;
  addressPostalcode?: string;
  addressCountry?: string;
  leadSource?: string;
  status?: string;
  title?: string;
  department?: string;
  doNotCall?: string;
  assignedUserId?: string;
  dateEntered?: string;
  dateModified?: string;
  lifetimeValue?: number;
  lastActivityDate?: string;
  preferredContactMethod?: 'email' | 'phone' | 'chat';
  subscriptionStatus?: 'trial' | 'active' | 'cancelled' | 'expired';
  engagementScore?: number;
  churnRisk?: 'low' | 'medium' | 'high';
  productInterests?: string[];
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const ContactSchema = z.object({
  id: z.string().optional(),
  firstName: z.string().optional(),
  lastName: z.string().min(1, "Last name is required"),
  email: z.string().email().optional(),
  phoneMobile: z.string().regex(/^[\+\-\(\)\s\d]+$/, "Invalid phone format").optional(),
  phoneWork: z.string().optional(),
  phoneHome: z.string().optional(),
  description: z.string().optional(),
  addressStreet: z.string().optional(),
  addressCity: z.string().optional(),
  addressState: z.string().optional(),
  addressPostalcode: z.string().optional(),
  addressCountry: z.string().optional(),
  leadSource: z.string().optional(),
  status: z.string().optional(),
  title: z.string().optional(),
  department: z.string().optional(),
  doNotCall: z.string().optional(),
  assignedUserId: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  lifetimeValue: z.number().min(0).optional(),
  lastActivityDate: z.string().optional(),
  preferredContactMethod: z.enum(['email', 'phone', 'chat']).optional(),
  subscriptionStatus: z.enum(['trial', 'active', 'cancelled', 'expired']).optional(),
  engagementScore: z.number().min(0).max(100).optional(),
  churnRisk: z.enum(['low', 'medium', 'high']).optional(),
  productInterests: z.array(z.string()).optional()
});
TS;
    }
}