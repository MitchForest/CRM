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
    protected ?string $birthdate = null;
    protected ?int $customerSatisfaction = null;
    protected bool $deleted = false;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->firstName)) {
            $this->addError('first_name', 'First name is required');
        }
        
        // Email validation
        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Invalid email format');
        }
        
        // Phone validation - allow various formats
        if (!empty($this->phoneMobile)) {
            // Allow digits, spaces, dots, dashes, parentheses, and plus sign
            // More than 3 digits required for a valid phone number
            $digitCount = preg_match_all('/\d/', $this->phoneMobile, $matches);
            if (!preg_match('/^[\+\-\(\)\s\.\d]+$/', $this->phoneMobile) || 
                $digitCount <= 3) {
                $this->addError('phone_mobile', 'Invalid phone number format');
            }
        }
        
        // Lead source validation
        $validLeadSources = ['Cold Call', 'Existing Customer', 'Self Generated', 'Employee', 
                           'Partner', 'Public Relations', 'Direct Mail', 'Conference', 
                           'Trade Show', 'Web Site', 'Word of mouth', 'Email', 'Campaign', 'Other'];
        if (!empty($this->leadSource) && !in_array($this->leadSource, $validLeadSources)) {
            $this->addError('lead_source', 'Invalid lead source');
        }
        
        // Customer satisfaction validation (1-5)
        if ($this->customerSatisfaction !== null && 
            ($this->customerSatisfaction < 1 || $this->customerSatisfaction > 5)) {
            $this->addError('customer_satisfaction', 'Customer satisfaction must be between 1 and 5');
        }
        
        // Date validation
        if (!empty($this->birthdate)) {
            $date = \DateTime::createFromFormat('Y-m-d', $this->birthdate);
            $date2 = \DateTime::createFromFormat('Y-m-d H:i:s', $this->birthdate);
            if (!$date && !$date2) {
                $this->addError('birthdate', 'Invalid date format');
            }
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
    
    public function getLeadSource(): ?string
    {
        return $this->leadSource;
    }
    
    public function setLeadSource(?string $leadSource): self
    {
        $this->leadSource = $leadSource;
        return $this;
    }
    
    public function getBirthdate(): ?string
    {
        return $this->birthdate;
    }
    
    public function setBirthdate(?string $birthdate): self
    {
        $this->birthdate = $birthdate;
        return $this;
    }
    
    public function getCustomerSatisfaction(): ?int
    {
        return $this->customerSatisfaction;
    }
    
    public function setCustomerSatisfaction(?int $customerSatisfaction): self
    {
        $this->customerSatisfaction = $customerSatisfaction;
        return $this;
    }
    
    public function getDeleted(): bool
    {
        return $this->deleted;
    }
    
    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;
        return $this;
    }
    
    public function getPhoneWork(): ?string
    {
        return $this->phoneWork;
    }
    
    public function setPhoneWork(?string $phoneWork): self
    {
        $this->phoneWork = $phoneWork;
        return $this;
    }
    
    public function getPhoneHome(): ?string
    {
        return $this->phoneHome;
    }
    
    public function setPhoneHome(?string $phoneHome): self
    {
        $this->phoneHome = $phoneHome;
        return $this;
    }
    
    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }
    
    public function setAddressStreet(?string $addressStreet): self
    {
        $this->addressStreet = $addressStreet;
        return $this;
    }
    
    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }
    
    public function setAddressCity(?string $addressCity): self
    {
        $this->addressCity = $addressCity;
        return $this;
    }
    
    public function getAddressState(): ?string
    {
        return $this->addressState;
    }
    
    public function setAddressState(?string $addressState): self
    {
        $this->addressState = $addressState;
        return $this;
    }
    
    public function getAddressPostalcode(): ?string
    {
        return $this->addressPostalcode;
    }
    
    public function setAddressPostalcode(?string $addressPostalcode): self
    {
        $this->addressPostalcode = $addressPostalcode;
        return $this;
    }
    
    public function getAddressCountry(): ?string
    {
        return $this->addressCountry;
    }
    
    public function setAddressCountry(?string $addressCountry): self
    {
        $this->addressCountry = $addressCountry;
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
    
    public function getTitle(): ?string
    {
        return $this->title;
    }
    
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    public function getDepartment(): ?string
    {
        return $this->department;
    }
    
    public function setDepartment(?string $department): self
    {
        $this->department = $department;
        return $this;
    }
    
    public function getDoNotCall(): ?string
    {
        return $this->doNotCall;
    }
    
    public function setDoNotCall(?string $doNotCall): self
    {
        $this->doNotCall = $doNotCall;
        return $this;
    }
    
    public function getAssignedUserId(): ?string
    {
        return $this->assignedUserId;
    }
    
    public function setAssignedUserId(?string $assignedUserId): self
    {
        $this->assignedUserId = $assignedUserId;
        return $this;
    }
    
    public function getDateEntered(): ?string
    {
        return $this->dateEntered;
    }
    
    public function setDateEntered(?string $dateEntered): self
    {
        $this->dateEntered = $dateEntered;
        return $this;
    }
    
    public function getDateModified(): ?string
    {
        return $this->dateModified;
    }
    
    public function setDateModified(?string $dateModified): self
    {
        $this->dateModified = $dateModified;
        return $this;
    }
    
    // Additional setters/getters for remaining properties
    
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
            ->setDateModified($bean->date_modified)
            ->setDeleted($bean->deleted == 1);
            
        // Add custom fields if they exist
        if (isset($bean->birthdate_c)) {
            $dto->setBirthdate($bean->birthdate_c);
        }
        if (isset($bean->customer_satisfaction_c)) {
            $dto->setCustomerSatisfaction((int)$bean->customer_satisfaction_c);
        }
            
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
        if ($this->birthdate !== null && isset($bean->birthdate_c)) $bean->birthdate_c = $this->birthdate;
        if ($this->customerSatisfaction !== null && isset($bean->customer_satisfaction_c)) {
            $bean->customer_satisfaction_c = $this->customerSatisfaction;
        }
    }
    
    /**
     * Override toArray to use camelCase keys
     */
    public function toArray(): array
    {
        $result = [];
        
        // Map properties to camelCase keys
        if ($this->id !== null) $result['id'] = $this->id;
        if ($this->firstName !== null) $result['firstName'] = $this->firstName;
        if ($this->lastName !== null) $result['lastName'] = $this->lastName;
        if ($this->email !== null) $result['email'] = $this->email;
        if ($this->phoneMobile !== null) $result['phoneMobile'] = $this->phoneMobile;
        if ($this->phoneWork !== null) $result['phoneWork'] = $this->phoneWork;
        if ($this->phoneHome !== null) $result['phoneHome'] = $this->phoneHome;
        if ($this->description !== null) $result['description'] = $this->description;
        if ($this->addressStreet !== null) $result['addressStreet'] = $this->addressStreet;
        if ($this->addressCity !== null) $result['addressCity'] = $this->addressCity;
        if ($this->addressState !== null) $result['addressState'] = $this->addressState;
        if ($this->addressPostalcode !== null) $result['addressPostalcode'] = $this->addressPostalcode;
        if ($this->addressCountry !== null) $result['addressCountry'] = $this->addressCountry;
        if ($this->leadSource !== null) $result['leadSource'] = $this->leadSource;
        if ($this->status !== null) $result['status'] = $this->status;
        if ($this->title !== null) $result['title'] = $this->title;
        if ($this->department !== null) $result['department'] = $this->department;
        if ($this->doNotCall !== null) $result['doNotCall'] = $this->doNotCall;
        if ($this->assignedUserId !== null) $result['assignedUserId'] = $this->assignedUserId;
        if ($this->dateEntered !== null) $result['dateEntered'] = $this->dateEntered;
        if ($this->dateModified !== null) $result['dateModified'] = $this->dateModified;
        if ($this->lifetimeValue !== null) $result['lifetimeValue'] = $this->lifetimeValue;
        if ($this->lastActivityDate !== null) $result['lastActivityDate'] = $this->lastActivityDate;
        if ($this->preferredContactMethod !== null) $result['preferredContactMethod'] = $this->preferredContactMethod;
        if ($this->subscriptionStatus !== null) $result['subscriptionStatus'] = $this->subscriptionStatus;
        if ($this->engagementScore !== null) $result['engagementScore'] = $this->engagementScore;
        if ($this->churnRisk !== null) $result['churnRisk'] = $this->churnRisk;
        if ($this->productInterests !== null) $result['productInterests'] = $this->productInterests;
        if ($this->birthdate !== null) $result['birthdate'] = $this->birthdate;
        if ($this->customerSatisfaction !== null) $result['customerSatisfaction'] = $this->customerSatisfaction;
        $result['deleted'] = $this->deleted;
        
        return $result;
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
  birthdate?: string;
  customerSatisfaction?: number;
  deleted?: boolean;
  leadSource?: 'Cold Call' | 'Existing Customer' | 'Self Generated' | 'Employee' | 'Partner' | 'Public Relations' | 'Direct Mail' | 'Conference' | 'Trade Show' | 'Web Site' | 'Word of mouth' | 'Email' | 'Campaign' | 'Other';
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const ContactSchema = z.object({
  id: z.string().optional(),
  firstName: z.string().min(1, "First name is required"),
  lastName: z.string().optional(),
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
  productInterests: z.array(z.string()).optional(),
  birthdate: z.string().optional(),
  customerSatisfaction: z.number().min(1).max(5).optional(),
  deleted: z.boolean().optional(),
  leadSource: z.enum(['Cold Call', 'Existing Customer', 'Self Generated', 'Employee', 'Partner', 'Public Relations', 'Direct Mail', 'Conference', 'Trade Show', 'Web Site', 'Word of mouth', 'Email', 'Campaign', 'Other']).optional()
});
TS;
    }
}