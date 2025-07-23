<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Activity Data Transfer Object
 * Unified representation of all activity types (Task, Email, Call, Meeting, Note)
 */
class ActivityDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $type = null; // task, email, call, meeting, note
    protected ?string $module = null; // Tasks, Emails, Calls, Meetings, Notes
    protected ?string $subject = null;
    protected ?string $description = null;
    protected ?string $status = null;
    protected ?string $priority = null;
    protected ?string $date = null; // Unified date field
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $dateStart = null;
    protected ?string $dateDue = null;
    protected ?string $dateSent = null;
    protected ?string $duration = null;
    protected ?int $durationHours = null;
    protected ?int $durationMinutes = null;
    protected ?string $parentType = null;
    protected ?string $parentId = null;
    protected ?string $parentName = null;
    protected ?string $contactId = null;
    protected ?string $contactName = null;
    protected ?string $assignedUserId = null;
    protected ?string $assignedUserName = null;
    protected ?string $createdBy = null;
    protected ?string $createdByName = null;
    protected ?bool $deleted = null;
    
    // Type-specific fields
    protected ?string $emailDirection = null; // inbound/outbound
    protected ?string $emailFrom = null;
    protected ?string $emailTo = null;
    protected ?string $emailSentiment = null; // positive/neutral/negative
    protected ?string $callDirection = null; // inbound/outbound
    protected ?string $callOutcome = null;
    protected ?string $meetingLocation = null;
    protected ?array $attendees = null;
    protected ?bool $isCompleted = null;
    protected ?bool $isOverdue = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->type)) {
            $this->addError('type', 'Activity type is required');
        }
        
        if (empty($this->subject) && empty($this->description)) {
            $this->addError('subject', 'Either subject or description is required');
        }
        
        // Type validation
        $validTypes = ['task', 'email', 'call', 'meeting', 'note'];
        if (!empty($this->type) && !in_array($this->type, $validTypes)) {
            $this->addError('type', 'Invalid activity type');
        }
        
        // Module validation
        $validModules = ['Tasks', 'Emails', 'Calls', 'Meetings', 'Notes'];
        if (!empty($this->module) && !in_array($this->module, $validModules)) {
            $this->addError('module', 'Invalid module');
        }
        
        // Email direction validation
        if ($this->type === 'email' && !empty($this->emailDirection)) {
            if (!in_array($this->emailDirection, ['inbound', 'outbound'])) {
                $this->addError('email_direction', 'Invalid email direction');
            }
        }
        
        // Call direction validation
        if ($this->type === 'call' && !empty($this->callDirection)) {
            if (!in_array($this->callDirection, ['inbound', 'outbound'])) {
                $this->addError('call_direction', 'Invalid call direction');
            }
        }
        
        // Sentiment validation
        if (!empty($this->emailSentiment)) {
            if (!in_array($this->emailSentiment, ['positive', 'neutral', 'negative'])) {
                $this->addError('email_sentiment', 'Invalid email sentiment');
            }
        }
        
        // Duration validation
        if ($this->durationHours !== null && $this->durationHours < 0) {
            $this->addError('duration_hours', 'Duration hours must be non-negative');
        }
        
        if ($this->durationMinutes !== null && ($this->durationMinutes < 0 || $this->durationMinutes >= 60)) {
            $this->addError('duration_minutes', 'Duration minutes must be between 0 and 59');
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
    
    public function getType(): ?string
    {
        return $this->type;
    }
    
    public function setType(?string $type): self
    {
        $this->type = $type;
        
        // Auto-set module based on type
        $typeModuleMap = [
            'task' => 'Tasks',
            'email' => 'Emails',
            'call' => 'Calls',
            'meeting' => 'Meetings',
            'note' => 'Notes'
        ];
        
        if (isset($typeModuleMap[$type])) {
            $this->module = $typeModuleMap[$type];
        }
        
        return $this;
    }
    
    public function getSubject(): ?string
    {
        return $this->subject;
    }
    
    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }
    
    public function getDate(): ?string
    {
        return $this->date;
    }
    
    public function setDate(?string $date): self
    {
        $this->date = $date;
        
        // Update overdue status for tasks
        if ($this->type === 'task' && $this->status !== 'Completed' && !empty($date)) {
            $this->isOverdue = strtotime($date) < time();
        }
        
        return $this;
    }
    
    public function getIsCompleted(): ?bool
    {
        return $this->isCompleted;
    }
    
    public function setIsCompleted(?bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;
        return $this;
    }
    
    public function getIsOverdue(): ?bool
    {
        return $this->isOverdue;
    }
    
    public function setIsOverdue(?bool $isOverdue): self
    {
        $this->isOverdue = $isOverdue;
        return $this;
    }
    
    // Additional setters/getters...
    
    /**
     * Create from various bean types
     */
    public static function fromBean($bean, string $type): self
    {
        $dto = new self();
        $dto->setType($type);
        $dto->setId($bean->id);
        $dto->setDateEntered($bean->date_entered);
        $dto->setDateModified($bean->date_modified);
        $dto->setAssignedUserId($bean->assigned_user_id);
        $dto->setDeleted((bool)$bean->deleted);
        
        switch ($type) {
            case 'task':
                $dto->setSubject($bean->name)
                    ->setDescription($bean->description)
                    ->setStatus($bean->status)
                    ->setPriority($bean->priority)
                    ->setDateDue($bean->date_due)
                    ->setDate($bean->date_due)
                    ->setParentType($bean->parent_type)
                    ->setParentId($bean->parent_id)
                    ->setContactId($bean->contact_id)
                    ->setIsCompleted($bean->status === 'Completed')
                    ->setIsOverdue($bean->status !== 'Completed' && 
                                  !empty($bean->date_due) && 
                                  strtotime($bean->date_due) < time());
                break;
                
            case 'email':
                $dto->setSubject($bean->name)
                    ->setDescription($bean->description ?? $bean->description_html)
                    ->setStatus($bean->status)
                    ->setDateSent($bean->date_sent)
                    ->setDate($bean->date_sent ?? $bean->date_entered)
                    ->setParentType($bean->parent_type)
                    ->setParentId($bean->parent_id)
                    ->setEmailFrom($bean->from_addr)
                    ->setEmailTo($bean->to_addrs)
                    ->setEmailDirection($bean->type ?? 'outbound')
                    ->setIsCompleted(true);
                break;
                
            case 'call':
                $dto->setSubject($bean->name)
                    ->setDescription($bean->description)
                    ->setStatus($bean->status)
                    ->setDateStart($bean->date_start)
                    ->setDate($bean->date_start)
                    ->setDurationHours((int)$bean->duration_hours)
                    ->setDurationMinutes((int)$bean->duration_minutes)
                    ->setParentType($bean->parent_type)
                    ->setParentId($bean->parent_id)
                    ->setCallDirection($bean->direction ?? 'outbound')
                    ->setCallOutcome($bean->status)
                    ->setIsCompleted($bean->status === 'Held');
                break;
                
            case 'meeting':
                $dto->setSubject($bean->name)
                    ->setDescription($bean->description)
                    ->setStatus($bean->status)
                    ->setDateStart($bean->date_start)
                    ->setDate($bean->date_start)
                    ->setDurationHours((int)$bean->duration_hours)
                    ->setDurationMinutes((int)$bean->duration_minutes)
                    ->setParentType($bean->parent_type)
                    ->setParentId($bean->parent_id)
                    ->setMeetingLocation($bean->location)
                    ->setIsCompleted($bean->status === 'Held');
                break;
                
            case 'note':
                $dto->setSubject($bean->name)
                    ->setDescription($bean->description)
                    ->setDate($bean->date_entered)
                    ->setParentType($bean->parent_type)
                    ->setParentId($bean->parent_id)
                    ->setIsCompleted(true);
                break;
        }
        
        // Calculate duration string
        if ($dto->durationHours !== null || $dto->durationMinutes !== null) {
            $hours = $dto->durationHours ?? 0;
            $minutes = $dto->durationMinutes ?? 0;
            $dto->duration = sprintf('%dh %dm', $hours, $minutes);
        }
        
        return $dto;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Activity {
  id?: string;
  type?: 'task' | 'email' | 'call' | 'meeting' | 'note';
  module?: 'Tasks' | 'Emails' | 'Calls' | 'Meetings' | 'Notes';
  subject?: string;
  description?: string;
  status?: string;
  priority?: string;
  date?: string;
  dateEntered?: string;
  dateModified?: string;
  dateStart?: string;
  dateDue?: string;
  dateSent?: string;
  duration?: string;
  durationHours?: number;
  durationMinutes?: number;
  parentType?: string;
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  createdBy?: string;
  createdByName?: string;
  deleted?: boolean;
  emailDirection?: 'inbound' | 'outbound';
  emailFrom?: string;
  emailTo?: string;
  emailSentiment?: 'positive' | 'neutral' | 'negative';
  callDirection?: 'inbound' | 'outbound';
  callOutcome?: string;
  meetingLocation?: string;
  attendees?: Attendee[];
  isCompleted?: boolean;
  isOverdue?: boolean;
}

export interface Attendee {
  id: string;
  name: string;
  email?: string;
  status?: 'Accepted' | 'Declined' | 'Tentative' | 'None';
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const AttendeeSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string().email().optional(),
  status: z.enum(['Accepted', 'Declined', 'Tentative', 'None']).optional()
});

export const ActivitySchema = z.object({
  id: z.string().optional(),
  type: z.enum(['task', 'email', 'call', 'meeting', 'note']).optional(),
  module: z.enum(['Tasks', 'Emails', 'Calls', 'Meetings', 'Notes']).optional(),
  subject: z.string().optional(),
  description: z.string().optional(),
  status: z.string().optional(),
  priority: z.string().optional(),
  date: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  dateStart: z.string().optional(),
  dateDue: z.string().optional(),
  dateSent: z.string().optional(),
  duration: z.string().optional(),
  durationHours: z.number().min(0).optional(),
  durationMinutes: z.number().min(0).max(59).optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  createdBy: z.string().optional(),
  createdByName: z.string().optional(),
  deleted: z.boolean().optional(),
  emailDirection: z.enum(['inbound', 'outbound']).optional(),
  emailFrom: z.string().optional(),
  emailTo: z.string().optional(),
  emailSentiment: z.enum(['positive', 'neutral', 'negative']).optional(),
  callDirection: z.enum(['inbound', 'outbound']).optional(),
  callOutcome: z.string().optional(),
  meetingLocation: z.string().optional(),
  attendees: z.array(AttendeeSchema).optional(),
  isCompleted: z.boolean().optional(),
  isOverdue: z.boolean().optional()
}).refine((data) => {
  return data.subject || data.description;
}, {
  message: "Either subject or description is required"
});
TS;
    }
}