<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Call Data Transfer Object
 */
class CallDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $status = null;
    protected ?string $direction = null;
    protected ?string $dateStart = null;
    protected ?string $dateEnd = null;
    protected ?int $duration = null;
    protected ?int $durationHours = null;
    protected ?int $durationMinutes = null;
    protected ?string $description = null;
    protected ?string $parentType = null;
    protected ?string $parentId = null;
    protected ?string $parentName = null;
    protected ?string $contactId = null;
    protected ?string $contactName = null;
    protected ?string $assignedUserId = null;
    protected ?string $assignedUserName = null;
    protected ?string $createdBy = null;
    protected ?string $modifiedUserId = null;
    protected ?bool $deleted = null;
    
    // Call-specific fields
    protected ?string $callPurpose = null;
    protected ?string $callResult = null;
    protected ?string $phoneNumber = null;
    protected ?string $phoneHome = null;
    protected ?string $phoneMobile = null;
    protected ?string $phoneWork = null;
    protected ?string $phoneOther = null;
    protected ?string $phoneFax = null;
    protected ?string $acceptStatus = null;
    protected ?string $reminderTime = null;
    protected ?string $emailReminderTime = null;
    protected ?string $emailReminderSent = null;
    protected ?string $reminderSent = null;
    protected ?string $outlookId = null;
    protected ?string $repeatType = null;
    protected ?string $repeatInterval = null;
    protected ?string $repeatDow = null;
    protected ?string $repeatUntil = null;
    protected ?string $repeatCount = null;
    protected ?string $repeatParentId = null;
    protected ?string $recurringSource = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->name)) {
            $this->addError('name', 'Call subject is required');
        }
        
        // Status validation
        $validStatuses = ['Planned', 'Held', 'Not Held'];
        if (!empty($this->status) && !in_array($this->status, $validStatuses)) {
            $this->addError('status', 'Invalid call status');
        }
        
        // Direction validation
        $validDirections = ['Inbound', 'Outbound'];
        if (!empty($this->direction) && !in_array($this->direction, $validDirections)) {
            $this->addError('direction', 'Invalid call direction');
        }
        
        // Date validation
        if (!empty($this->dateStart) && !strtotime($this->dateStart)) {
            $this->addError('date_start', 'Invalid start date format');
        }
        
        if (!empty($this->dateEnd) && !strtotime($this->dateEnd)) {
            $this->addError('date_end', 'Invalid end date format');
        }
        
        // Date logic validation
        if (!empty($this->dateStart) && !empty($this->dateEnd)) {
            if (strtotime($this->dateStart) > strtotime($this->dateEnd)) {
                $this->addError('date_end', 'End date must be after start date');
            }
        }
        
        // Duration validation
        if ($this->durationHours !== null && $this->durationHours < 0) {
            $this->addError('duration_hours', 'Duration hours must be non-negative');
        }
        
        if ($this->durationMinutes !== null && ($this->durationMinutes < 0 || $this->durationMinutes >= 60)) {
            $this->addError('duration_minutes', 'Duration minutes must be between 0 and 59');
        }
        
        // Parent type validation
        $validParentTypes = ['Contacts', 'Leads', 'Opportunities', 'Cases', 'Tasks'];
        if (!empty($this->parentType) && !in_array($this->parentType, $validParentTypes)) {
            $this->addError('parent_type', 'Invalid parent type');
        }
        
        // Recurrence validation
        if (!empty($this->repeatType)) {
            $validRepeatTypes = ['Daily', 'Weekly', 'Monthly', 'Yearly'];
            if (!in_array($this->repeatType, $validRepeatTypes)) {
                $this->addError('repeat_type', 'Invalid repeat type');
            }
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
        return $this;
    }
    
    public function getDirection(): ?string
    {
        return $this->direction;
    }
    
    public function setDirection(?string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }
    
    public function getDateStart(): ?string
    {
        return $this->dateStart;
    }
    
    public function setDateStart(?string $dateStart): self
    {
        $this->dateStart = $dateStart;
        return $this;
    }
    
    public function getDurationHours(): ?int
    {
        return $this->durationHours;
    }
    
    public function setDurationHours(?int $durationHours): self
    {
        $this->durationHours = $durationHours;
        $this->updateDuration();
        return $this;
    }
    
    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }
    
    public function setDurationMinutes(?int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;
        $this->updateDuration();
        return $this;
    }
    
    /**
     * Update total duration in seconds
     */
    protected function updateDuration(): void
    {
        $hours = $this->durationHours ?? 0;
        $minutes = $this->durationMinutes ?? 0;
        $this->duration = ($hours * 3600) + ($minutes * 60);
    }
    
    // Additional setters/getters...
    
    /**
     * Create from SugarBean
     */
    public static function fromBean($bean): self
    {
        $dto = new self();
        
        $dto->setId($bean->id)
            ->setName($bean->name)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setStatus($bean->status)
            ->setDirection($bean->direction)
            ->setDateStart($bean->date_start)
            ->setDateEnd($bean->date_end)
            ->setDurationHours((int)$bean->duration_hours)
            ->setDurationMinutes((int)$bean->duration_minutes)
            ->setDescription($bean->description)
            ->setParentType($bean->parent_type)
            ->setParentId($bean->parent_id)
            ->setContactId($bean->contact_id)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setCreatedBy($bean->created_by)
            ->setModifiedUserId($bean->modified_user_id)
            ->setDeleted((bool)$bean->deleted)
            ->setCallPurpose($bean->call_purpose)
            ->setCallResult($bean->call_result)
            ->setPhoneNumber($bean->phone_number)
            ->setPhoneHome($bean->phone_home)
            ->setPhoneMobile($bean->phone_mobile)
            ->setPhoneWork($bean->phone_work)
            ->setPhoneOther($bean->phone_other)
            ->setPhoneFax($bean->phone_fax)
            ->setAcceptStatus($bean->accept_status)
            ->setReminderTime($bean->reminder_time)
            ->setEmailReminderTime($bean->email_reminder_time)
            ->setEmailReminderSent($bean->email_reminder_sent)
            ->setReminderSent($bean->reminder_sent)
            ->setOutlookId($bean->outlook_id)
            ->setRepeatType($bean->repeat_type)
            ->setRepeatInterval($bean->repeat_interval)
            ->setRepeatDow($bean->repeat_dow)
            ->setRepeatUntil($bean->repeat_until)
            ->setRepeatCount($bean->repeat_count)
            ->setRepeatParentId($bean->repeat_parent_id)
            ->setRecurringSource($bean->recurring_source);
            
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
        if ($this->status !== null) $bean->status = $this->status;
        if ($this->direction !== null) $bean->direction = $this->direction;
        if ($this->dateStart !== null) $bean->date_start = $this->dateStart;
        if ($this->dateEnd !== null) $bean->date_end = $this->dateEnd;
        if ($this->durationHours !== null) $bean->duration_hours = $this->durationHours;
        if ($this->durationMinutes !== null) $bean->duration_minutes = $this->durationMinutes;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->parentType !== null) $bean->parent_type = $this->parentType;
        if ($this->parentId !== null) $bean->parent_id = $this->parentId;
        if ($this->contactId !== null) $bean->contact_id = $this->contactId;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->callPurpose !== null) $bean->call_purpose = $this->callPurpose;
        if ($this->callResult !== null) $bean->call_result = $this->callResult;
        if ($this->phoneNumber !== null) $bean->phone_number = $this->phoneNumber;
        if ($this->phoneHome !== null) $bean->phone_home = $this->phoneHome;
        if ($this->phoneMobile !== null) $bean->phone_mobile = $this->phoneMobile;
        if ($this->phoneWork !== null) $bean->phone_work = $this->phoneWork;
        if ($this->phoneOther !== null) $bean->phone_other = $this->phoneOther;
        if ($this->phoneFax !== null) $bean->phone_fax = $this->phoneFax;
        if ($this->acceptStatus !== null) $bean->accept_status = $this->acceptStatus;
        if ($this->reminderTime !== null) $bean->reminder_time = $this->reminderTime;
        if ($this->emailReminderTime !== null) $bean->email_reminder_time = $this->emailReminderTime;
        if ($this->repeatType !== null) $bean->repeat_type = $this->repeatType;
        if ($this->repeatInterval !== null) $bean->repeat_interval = $this->repeatInterval;
        if ($this->repeatDow !== null) $bean->repeat_dow = $this->repeatDow;
        if ($this->repeatUntil !== null) $bean->repeat_until = $this->repeatUntil;
        if ($this->repeatCount !== null) $bean->repeat_count = $this->repeatCount;
        if ($this->repeatParentId !== null) $bean->repeat_parent_id = $this->repeatParentId;
        if ($this->recurringSource !== null) $bean->recurring_source = $this->recurringSource;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Call {
  id?: string;
  name?: string;
  dateEntered?: string;
  dateModified?: string;
  status?: 'Planned' | 'Held' | 'Not Held';
  direction?: 'Inbound' | 'Outbound';
  dateStart?: string;
  dateEnd?: string;
  duration?: number;
  durationHours?: number;
  durationMinutes?: number;
  description?: string;
  parentType?: 'Contacts' | 'Leads' | 'Opportunities' | 'Cases' | 'Tasks';
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  createdBy?: string;
  modifiedUserId?: string;
  deleted?: boolean;
  callPurpose?: string;
  callResult?: string;
  phoneNumber?: string;
  phoneHome?: string;
  phoneMobile?: string;
  phoneWork?: string;
  phoneOther?: string;
  phoneFax?: string;
  acceptStatus?: string;
  reminderTime?: string;
  emailReminderTime?: string;
  emailReminderSent?: string;
  reminderSent?: string;
  outlookId?: string;
  repeatType?: 'Daily' | 'Weekly' | 'Monthly' | 'Yearly';
  repeatInterval?: string;
  repeatDow?: string;
  repeatUntil?: string;
  repeatCount?: string;
  repeatParentId?: string;
  recurringSource?: string;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const CallSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1, "Call subject is required"),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  status: z.enum(['Planned', 'Held', 'Not Held']).optional(),
  direction: z.enum(['Inbound', 'Outbound']).optional(),
  dateStart: z.string().optional(),
  dateEnd: z.string().optional(),
  duration: z.number().optional(),
  durationHours: z.number().min(0).optional(),
  durationMinutes: z.number().min(0).max(59).optional(),
  description: z.string().optional(),
  parentType: z.enum(['Contacts', 'Leads', 'Opportunities', 'Cases', 'Tasks']).optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  createdBy: z.string().optional(),
  modifiedUserId: z.string().optional(),
  deleted: z.boolean().optional(),
  callPurpose: z.string().optional(),
  callResult: z.string().optional(),
  phoneNumber: z.string().optional(),
  phoneHome: z.string().optional(),
  phoneMobile: z.string().optional(),
  phoneWork: z.string().optional(),
  phoneOther: z.string().optional(),
  phoneFax: z.string().optional(),
  acceptStatus: z.string().optional(),
  reminderTime: z.string().optional(),
  emailReminderTime: z.string().optional(),
  emailReminderSent: z.string().optional(),
  reminderSent: z.string().optional(),
  outlookId: z.string().optional(),
  repeatType: z.enum(['Daily', 'Weekly', 'Monthly', 'Yearly']).optional(),
  repeatInterval: z.string().optional(),
  repeatDow: z.string().optional(),
  repeatUntil: z.string().optional(),
  repeatCount: z.string().optional(),
  repeatParentId: z.string().optional(),
  recurringSource: z.string().optional()
});
TS;
    }
}