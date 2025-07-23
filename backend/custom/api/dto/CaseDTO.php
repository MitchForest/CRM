<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Case (Support Ticket) Data Transfer Object
 */
class CaseDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $caseNumber = null;
    protected ?string $type = null;
    protected ?string $status = null;
    protected ?string $priority = null;
    protected ?string $resolution = null;
    protected ?string $state = null;
    protected ?string $description = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $accountId = null;
    protected ?string $accountName = null;
    protected ?string $contactId = null;
    protected ?string $contactName = null;
    protected ?string $assignedUserId = null;
    protected ?string $assignedUserName = null;
    protected ?bool $deleted = null;
    protected ?string $workLog = null;
    protected ?array $updates = null;
    protected ?int $updateCount = null;
    protected ?string $source = null;
    protected ?string $product = null;
    protected ?string $firstResponseDate = null;
    protected ?string $resolutionDate = null;
    protected ?int $resolutionTime = null;
    protected ?int $customerSatisfaction = null;
    protected ?array $attachments = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->name)) {
            $this->addError('name', 'Case subject is required');
        }
        
        // Status validation
        $validStatuses = ['New', 'Assigned', 'Closed', 'Pending Input', 'Rejected', 'Duplicate'];
        if (!empty($this->status) && !in_array($this->status, $validStatuses)) {
            $this->addError('status', 'Invalid case status');
        }
        
        // Priority validation
        $validPriorities = ['P1', 'P2', 'P3', 'High', 'Medium', 'Low'];
        if (!empty($this->priority) && !in_array($this->priority, $validPriorities)) {
            $this->addError('priority', 'Invalid case priority');
        }
        
        // Type validation
        $validTypes = ['Administration', 'Bug', 'Feature Request', 'Question', 'Other'];
        if (!empty($this->type) && !in_array($this->type, $validTypes)) {
            $this->addError('type', 'Invalid case type');
        }
        
        // State validation
        $validStates = ['Open', 'Closed'];
        if (!empty($this->state) && !in_array($this->state, $validStates)) {
            $this->addError('state', 'Invalid case state');
        }
        
        // Customer satisfaction validation
        if ($this->customerSatisfaction !== null && ($this->customerSatisfaction < 1 || $this->customerSatisfaction > 5)) {
            $this->addError('customer_satisfaction', 'Customer satisfaction must be between 1 and 5');
        }
        
        // Logic validation
        if ($this->state === 'Closed' && empty($this->resolution)) {
            $this->addError('resolution', 'Resolution is required when closing a case');
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
    
    public function getCaseNumber(): ?string
    {
        return $this->caseNumber;
    }
    
    public function setCaseNumber(?string $caseNumber): self
    {
        $this->caseNumber = $caseNumber;
        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        
        // Auto-update state based on status
        if (in_array($status, ['Closed', 'Rejected', 'Duplicate'])) {
            $this->state = 'Closed';
            if (empty($this->resolutionDate)) {
                $this->resolutionDate = date('Y-m-d H:i:s');
            }
        } else {
            $this->state = 'Open';
        }
        
        return $this;
    }
    
    public function getPriority(): ?string
    {
        return $this->priority;
    }
    
    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
    
    public function getResolution(): ?string
    {
        return $this->resolution;
    }
    
    public function setResolution(?string $resolution): self
    {
        $this->resolution = $resolution;
        return $this;
    }
    
    public function getContactId(): ?string
    {
        return $this->contactId;
    }
    
    public function setContactId(?string $contactId): self
    {
        $this->contactId = $contactId;
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
            ->setName($bean->name)
            ->setCaseNumber($bean->case_number)
            ->setType($bean->type)
            ->setStatus($bean->status)
            ->setPriority($bean->priority)
            ->setResolution($bean->resolution)
            ->setState($bean->state)
            ->setDescription($bean->description)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setAccountId($bean->account_id)
            ->setAccountName($bean->account_name)
            ->setContactId($bean->contact_id)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setDeleted((bool)$bean->deleted)
            ->setWorkLog($bean->work_log);
            
        // Load related contact name if available
        if (!empty($bean->contact_id)) {
            $contact = \BeanFactory::getBean('Contacts', $bean->contact_id);
            if ($contact) {
                $dto->setContactName(trim($contact->first_name . ' ' . $contact->last_name));
            }
        }
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->name !== null) $bean->name = $this->name;
        if ($this->type !== null) $bean->type = $this->type;
        if ($this->status !== null) $bean->status = $this->status;
        if ($this->priority !== null) $bean->priority = $this->priority;
        if ($this->resolution !== null) $bean->resolution = $this->resolution;
        if ($this->state !== null) $bean->state = $this->state;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->accountId !== null) $bean->account_id = $this->accountId;
        if ($this->accountName !== null) $bean->account_name = $this->accountName;
        if ($this->contactId !== null) $bean->contact_id = $this->contactId;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->workLog !== null) $bean->work_log = $this->workLog;
    }
    
    /**
     * Add case update
     */
    public function addUpdate(array $update): self
    {
        if ($this->updates === null) {
            $this->updates = [];
        }
        
        $this->updates[] = array_merge($update, [
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $this->updateCount = count($this->updates);
        
        return $this;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Case {
  id?: string;
  name?: string;
  caseNumber?: string;
  type?: 'Administration' | 'Bug' | 'Feature Request' | 'Question' | 'Other';
  status?: 'New' | 'Assigned' | 'Closed' | 'Pending Input' | 'Rejected' | 'Duplicate';
  priority?: 'P1' | 'P2' | 'P3' | 'High' | 'Medium' | 'Low';
  resolution?: string;
  state?: 'Open' | 'Closed';
  description?: string;
  dateEntered?: string;
  dateModified?: string;
  accountId?: string;
  accountName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  deleted?: boolean;
  workLog?: string;
  updates?: CaseUpdate[];
  updateCount?: number;
  source?: string;
  product?: string;
  firstResponseDate?: string;
  resolutionDate?: string;
  resolutionTime?: number;
  customerSatisfaction?: number;
  attachments?: Attachment[];
}

export interface CaseUpdate {
  id?: string;
  author?: string;
  authorId?: string;
  text: string;
  timestamp: string;
  internal?: boolean;
}

export interface Attachment {
  id: string;
  name: string;
  size: number;
  mimeType: string;
  uploadDate: string;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const CaseUpdateSchema = z.object({
  id: z.string().optional(),
  author: z.string().optional(),
  authorId: z.string().optional(),
  text: z.string(),
  timestamp: z.string(),
  internal: z.boolean().optional()
});

export const AttachmentSchema = z.object({
  id: z.string(),
  name: z.string(),
  size: z.number(),
  mimeType: z.string(),
  uploadDate: z.string()
});

export const CaseSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1, "Case subject is required"),
  caseNumber: z.string().optional(),
  type: z.enum(['Administration', 'Bug', 'Feature Request', 'Question', 'Other']).optional(),
  status: z.enum(['New', 'Assigned', 'Closed', 'Pending Input', 'Rejected', 'Duplicate']).optional(),
  priority: z.enum(['P1', 'P2', 'P3', 'High', 'Medium', 'Low']).optional(),
  resolution: z.string().optional(),
  state: z.enum(['Open', 'Closed']).optional(),
  description: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  accountId: z.string().optional(),
  accountName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  deleted: z.boolean().optional(),
  workLog: z.string().optional(),
  updates: z.array(CaseUpdateSchema).optional(),
  updateCount: z.number().optional(),
  source: z.string().optional(),
  product: z.string().optional(),
  firstResponseDate: z.string().optional(),
  resolutionDate: z.string().optional(),
  resolutionTime: z.number().optional(),
  customerSatisfaction: z.number().min(1).max(5).optional(),
  attachments: z.array(AttachmentSchema).optional()
});
TS;
    }
}