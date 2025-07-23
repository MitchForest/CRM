<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Task Data Transfer Object
 */
class TaskDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $status = null;
    protected ?string $priority = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $dateStart = null;
    protected ?string $dateDue = null;
    protected ?string $dateStartFlag = null;
    protected ?string $dateDueFlag = null;
    protected ?string $parentType = null;
    protected ?string $parentId = null;
    protected ?string $contactId = null;
    protected ?string $contactName = null;
    protected ?string $contactPhone = null;
    protected ?string $contactEmail = null;
    protected ?string $assignedUserId = null;
    protected ?string $assignedUserName = null;
    protected ?string $description = null;
    protected ?bool $deleted = null;
    protected ?int $reminderTime = null;
    protected ?int $emailReminderTime = null;
    protected ?string $emailReminderSent = null;
    protected ?string $reminderSent = null;
    protected ?int $percentComplete = null;
    protected ?string $completedDate = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->name)) {
            $this->addError('name', 'Task name is required');
        }
        
        // Status validation
        $validStatuses = ['Not Started', 'In Progress', 'Completed', 'Pending Input', 'Deferred'];
        if (!empty($this->status) && !in_array($this->status, $validStatuses)) {
            $this->addError('status', 'Invalid task status');
        }
        
        // Priority validation
        $validPriorities = ['High', 'Medium', 'Low'];
        if (!empty($this->priority) && !in_array($this->priority, $validPriorities)) {
            $this->addError('priority', 'Invalid task priority');
        }
        
        // Date validation
        if (!empty($this->dateDue) && !strtotime($this->dateDue)) {
            $this->addError('date_due', 'Invalid due date format');
        }
        
        if (!empty($this->dateStart) && !strtotime($this->dateStart)) {
            $this->addError('date_start', 'Invalid start date format');
        }
        
        // Date logic validation
        if (!empty($this->dateStart) && !empty($this->dateDue)) {
            if (strtotime($this->dateStart) > strtotime($this->dateDue)) {
                $this->addError('date_due', 'Due date must be after start date');
            }
        }
        
        // Percent complete validation
        if ($this->percentComplete !== null && ($this->percentComplete < 0 || $this->percentComplete > 100)) {
            $this->addError('percent_complete', 'Percent complete must be between 0 and 100');
        }
        
        // Parent type validation
        $validParentTypes = ['Contacts', 'Leads', 'Opportunities', 'Cases', 'Accounts'];
        if (!empty($this->parentType) && !in_array($this->parentType, $validParentTypes)) {
            $this->addError('parent_type', 'Invalid parent type');
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
    
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        
        // Auto-update percent complete based on status
        if ($status === 'Completed') {
            $this->percentComplete = 100;
            $this->completedDate = date('Y-m-d H:i:s');
        } elseif ($status === 'Not Started') {
            $this->percentComplete = 0;
            $this->completedDate = null;
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
    
    public function getDateDue(): ?string
    {
        return $this->dateDue;
    }
    
    public function setDateDue(?string $dateDue): self
    {
        $this->dateDue = $dateDue;
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
    
    public function getPercentComplete(): ?int
    {
        return $this->percentComplete;
    }
    
    public function setPercentComplete(?int $percentComplete): self
    {
        $this->percentComplete = $percentComplete;
        
        // Auto-update status based on percent
        if ($percentComplete === 100) {
            $this->status = 'Completed';
            $this->completedDate = date('Y-m-d H:i:s');
        } elseif ($percentComplete > 0) {
            $this->status = 'In Progress';
        }
        
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
            ->setStatus($bean->status)
            ->setPriority($bean->priority)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setDateStart($bean->date_start)
            ->setDateDue($bean->date_due)
            ->setDateStartFlag($bean->date_start_flag)
            ->setDateDueFlag($bean->date_due_flag)
            ->setParentType($bean->parent_type)
            ->setParentId($bean->parent_id)
            ->setContactId($bean->contact_id)
            ->setContactName($bean->contact_name)
            ->setContactPhone($bean->contact_phone)
            ->setContactEmail($bean->contact_email)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setDescription($bean->description)
            ->setDeleted((bool)$bean->deleted)
            ->setReminderTime((int)$bean->reminder_time)
            ->setEmailReminderTime((int)$bean->email_reminder_time)
            ->setEmailReminderSent($bean->email_reminder_sent)
            ->setReminderSent($bean->reminder_sent)
            ->setPercentComplete((int)$bean->percent_complete);
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->name !== null) $bean->name = $this->name;
        if ($this->status !== null) $bean->status = $this->status;
        if ($this->priority !== null) $bean->priority = $this->priority;
        if ($this->dateStart !== null) $bean->date_start = $this->dateStart;
        if ($this->dateDue !== null) $bean->date_due = $this->dateDue;
        if ($this->dateStartFlag !== null) $bean->date_start_flag = $this->dateStartFlag;
        if ($this->dateDueFlag !== null) $bean->date_due_flag = $this->dateDueFlag;
        if ($this->parentType !== null) $bean->parent_type = $this->parentType;
        if ($this->parentId !== null) $bean->parent_id = $this->parentId;
        if ($this->contactId !== null) $bean->contact_id = $this->contactId;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->reminderTime !== null) $bean->reminder_time = $this->reminderTime;
        if ($this->emailReminderTime !== null) $bean->email_reminder_time = $this->emailReminderTime;
        if ($this->percentComplete !== null) $bean->percent_complete = $this->percentComplete;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Task {
  id?: string;
  name?: string;
  status?: 'Not Started' | 'In Progress' | 'Completed' | 'Pending Input' | 'Deferred';
  priority?: 'High' | 'Medium' | 'Low';
  dateEntered?: string;
  dateModified?: string;
  dateStart?: string;
  dateDue?: string;
  dateStartFlag?: string;
  dateDueFlag?: string;
  parentType?: 'Contacts' | 'Leads' | 'Opportunities' | 'Cases' | 'Accounts';
  parentId?: string;
  contactId?: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  description?: string;
  deleted?: boolean;
  reminderTime?: number;
  emailReminderTime?: number;
  emailReminderSent?: string;
  reminderSent?: string;
  percentComplete?: number;
  completedDate?: string;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const TaskSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1, "Task name is required"),
  status: z.enum(['Not Started', 'In Progress', 'Completed', 'Pending Input', 'Deferred']).optional(),
  priority: z.enum(['High', 'Medium', 'Low']).optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  dateStart: z.string().optional(),
  dateDue: z.string().optional(),
  dateStartFlag: z.string().optional(),
  dateDueFlag: z.string().optional(),
  parentType: z.enum(['Contacts', 'Leads', 'Opportunities', 'Cases', 'Accounts']).optional(),
  parentId: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  contactPhone: z.string().optional(),
  contactEmail: z.string().email().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  description: z.string().optional(),
  deleted: z.boolean().optional(),
  reminderTime: z.number().optional(),
  emailReminderTime: z.number().optional(),
  emailReminderSent: z.string().optional(),
  reminderSent: z.string().optional(),
  percentComplete: z.number().min(0).max(100).optional(),
  completedDate: z.string().optional()
});
TS;
    }
}