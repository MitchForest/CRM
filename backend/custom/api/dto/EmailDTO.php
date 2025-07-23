<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Email Data Transfer Object
 */
class EmailDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $subject = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $dateSent = null;
    protected ?string $messageId = null;
    protected ?string $type = null; // campaign, archived, etc.
    protected ?string $status = null;
    protected ?string $flagged = null;
    protected ?string $replyToStatus = null;
    protected ?string $intent = null;
    protected ?string $mailboxId = null;
    protected ?string $parentType = null;
    protected ?string $parentId = null;
    protected ?string $assignedUserId = null;
    
    // Email addresses
    protected ?string $fromAddr = null;
    protected ?string $fromName = null;
    protected ?string $toAddrs = null;
    protected ?string $ccAddrs = null;
    protected ?string $bccAddrs = null;
    protected ?string $replyToAddr = null;
    
    // Content
    protected ?string $description = null;
    protected ?string $descriptionHtml = null;
    protected ?string $rawSource = null;
    
    // Metadata
    protected ?bool $deleted = null;
    protected ?string $uid = null;
    protected ?string $msgno = null;
    protected ?string $folder = null;
    protected ?string $folderType = null;
    protected ?string $isRead = null;
    protected ?string $isReplied = null;
    protected ?string $isImported = null;
    protected ?string $isDraft = null;
    protected ?string $isSent = null;
    
    // Recipients
    protected ?array $recipients = null;
    protected ?int $recipientCount = null;
    
    // Attachments
    protected ?array $attachments = null;
    protected ?int $attachmentCount = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->subject) && empty($this->name)) {
            $this->addError('subject', 'Email subject is required');
        }
        
        // Type validation
        $validTypes = ['out', 'archived', 'draft', 'inbound', 'campaign'];
        if (!empty($this->type) && !in_array($this->type, $validTypes)) {
            $this->addError('type', 'Invalid email type');
        }
        
        // Status validation
        $validStatuses = ['archived', 'closed', 'draft', 'read', 'replied', 'sent', 'unread', 'bounced'];
        if (!empty($this->status) && !in_array($this->status, $validStatuses)) {
            $this->addError('status', 'Invalid email status');
        }
        
        // Email validation
        if (!empty($this->fromAddr) && !filter_var($this->fromAddr, FILTER_VALIDATE_EMAIL)) {
            $this->addError('from_addr', 'Invalid from email address');
        }
        
        if (!empty($this->replyToAddr) && !filter_var($this->replyToAddr, FILTER_VALIDATE_EMAIL)) {
            $this->addError('reply_to_addr', 'Invalid reply-to email address');
        }
        
        // Recipients validation
        if (!empty($this->toAddrs)) {
            $toEmails = explode(',', $this->toAddrs);
            foreach ($toEmails as $email) {
                $email = trim($email);
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('to_addrs', "Invalid email address in recipients: $email");
                    break;
                }
            }
        }
        
        // Date validation
        if (!empty($this->dateSent) && !strtotime($this->dateSent)) {
            $this->addError('date_sent', 'Invalid sent date format');
        }
        
        // Parent type validation
        $validParentTypes = ['Contacts', 'Leads', 'Opportunities', 'Cases', 'Tasks'];
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
    
    public function getSubject(): ?string
    {
        return $this->subject;
    }
    
    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        $this->name = $subject; // Keep name in sync
        return $this;
    }
    
    public function getType(): ?string
    {
        return $this->type;
    }
    
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        
        // Update read status
        if ($status === 'read' || $status === 'replied') {
            $this->isRead = '1';
        }
        
        return $this;
    }
    
    public function getFromAddr(): ?string
    {
        return $this->fromAddr;
    }
    
    public function setFromAddr(?string $fromAddr): self
    {
        $this->fromAddr = $fromAddr;
        return $this;
    }
    
    public function getToAddrs(): ?string
    {
        return $this->toAddrs;
    }
    
    public function setToAddrs(?string $toAddrs): self
    {
        $this->toAddrs = $toAddrs;
        
        // Update recipient count
        if (!empty($toAddrs)) {
            $recipients = explode(',', $toAddrs);
            $this->recipientCount = count($recipients);
        }
        
        return $this;
    }
    
    public function getDateSent(): ?string
    {
        return $this->dateSent;
    }
    
    public function setDateSent(?string $dateSent): self
    {
        $this->dateSent = $dateSent;
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
    
    public function getDescriptionHtml(): ?string
    {
        return $this->descriptionHtml;
    }
    
    public function setDescriptionHtml(?string $descriptionHtml): self
    {
        $this->descriptionHtml = $descriptionHtml;
        return $this;
    }
    
    public function getAttachments(): ?array
    {
        return $this->attachments;
    }
    
    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments;
        $this->attachmentCount = $attachments ? count($attachments) : 0;
        return $this;
    }
    
    // Additional setters/getters...
    
    /**
     * Create from SugarBean
     */
    public static function fromBean($bean): self
    {
        $dto = new self();
        
        $dto->setId($bean->id)
            ->setSubject($bean->name)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setDateSent($bean->date_sent)
            ->setMessageId($bean->message_id)
            ->setType($bean->type)
            ->setStatus($bean->status)
            ->setFlagged($bean->flagged)
            ->setReplyToStatus($bean->reply_to_status)
            ->setIntent($bean->intent)
            ->setMailboxId($bean->mailbox_id)
            ->setParentType($bean->parent_type)
            ->setParentId($bean->parent_id)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setFromAddr($bean->from_addr)
            ->setFromName($bean->from_name)
            ->setToAddrs($bean->to_addrs)
            ->setCcAddrs($bean->cc_addrs)
            ->setBccAddrs($bean->bcc_addrs)
            ->setReplyToAddr($bean->reply_to_addr)
            ->setDescription($bean->description)
            ->setDescriptionHtml($bean->description_html)
            ->setRawSource($bean->raw_source)
            ->setDeleted((bool)$bean->deleted)
            ->setUid($bean->uid)
            ->setMsgno($bean->msgno)
            ->setFolder($bean->folder)
            ->setFolderType($bean->folder_type)
            ->setIsRead($bean->is_read)
            ->setIsReplied($bean->is_replied)
            ->setIsImported($bean->is_imported)
            ->setIsDraft($bean->is_draft)
            ->setIsSent($bean->is_sent);
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->subject !== null) $bean->name = $this->subject;
        if ($this->dateSent !== null) $bean->date_sent = $this->dateSent;
        if ($this->messageId !== null) $bean->message_id = $this->messageId;
        if ($this->type !== null) $bean->type = $this->type;
        if ($this->status !== null) $bean->status = $this->status;
        if ($this->flagged !== null) $bean->flagged = $this->flagged;
        if ($this->replyToStatus !== null) $bean->reply_to_status = $this->replyToStatus;
        if ($this->intent !== null) $bean->intent = $this->intent;
        if ($this->mailboxId !== null) $bean->mailbox_id = $this->mailboxId;
        if ($this->parentType !== null) $bean->parent_type = $this->parentType;
        if ($this->parentId !== null) $bean->parent_id = $this->parentId;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->fromAddr !== null) $bean->from_addr = $this->fromAddr;
        if ($this->fromName !== null) $bean->from_name = $this->fromName;
        if ($this->toAddrs !== null) $bean->to_addrs = $this->toAddrs;
        if ($this->ccAddrs !== null) $bean->cc_addrs = $this->ccAddrs;
        if ($this->bccAddrs !== null) $bean->bcc_addrs = $this->bccAddrs;
        if ($this->replyToAddr !== null) $bean->reply_to_addr = $this->replyToAddr;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->descriptionHtml !== null) $bean->description_html = $this->descriptionHtml;
        if ($this->rawSource !== null) $bean->raw_source = $this->rawSource;
        if ($this->uid !== null) $bean->uid = $this->uid;
        if ($this->msgno !== null) $bean->msgno = $this->msgno;
        if ($this->folder !== null) $bean->folder = $this->folder;
        if ($this->folderType !== null) $bean->folder_type = $this->folderType;
        if ($this->isRead !== null) $bean->is_read = $this->isRead;
        if ($this->isReplied !== null) $bean->is_replied = $this->isReplied;
        if ($this->isImported !== null) $bean->is_imported = $this->isImported;
        if ($this->isDraft !== null) $bean->is_draft = $this->isDraft;
        if ($this->isSent !== null) $bean->is_sent = $this->isSent;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Email {
  id?: string;
  name?: string;
  subject?: string;
  dateEntered?: string;
  dateModified?: string;
  dateSent?: string;
  messageId?: string;
  type?: 'out' | 'archived' | 'draft' | 'inbound' | 'campaign';
  status?: 'archived' | 'closed' | 'draft' | 'read' | 'replied' | 'sent' | 'unread' | 'bounced';
  flagged?: string;
  replyToStatus?: string;
  intent?: string;
  mailboxId?: string;
  parentType?: 'Contacts' | 'Leads' | 'Opportunities' | 'Cases' | 'Tasks';
  parentId?: string;
  assignedUserId?: string;
  fromAddr?: string;
  fromName?: string;
  toAddrs?: string;
  ccAddrs?: string;
  bccAddrs?: string;
  replyToAddr?: string;
  description?: string;
  descriptionHtml?: string;
  rawSource?: string;
  deleted?: boolean;
  uid?: string;
  msgno?: string;
  folder?: string;
  folderType?: string;
  isRead?: string;
  isReplied?: string;
  isImported?: string;
  isDraft?: string;
  isSent?: string;
  recipients?: EmailRecipient[];
  recipientCount?: number;
  attachments?: EmailAttachment[];
  attachmentCount?: number;
}

export interface EmailRecipient {
  email: string;
  name?: string;
  type: 'to' | 'cc' | 'bcc';
}

export interface EmailAttachment {
  id: string;
  name: string;
  filename: string;
  size: number;
  mimeType: string;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const EmailRecipientSchema = z.object({
  email: z.string().email(),
  name: z.string().optional(),
  type: z.enum(['to', 'cc', 'bcc'])
});

export const EmailAttachmentSchema = z.object({
  id: z.string(),
  name: z.string(),
  filename: z.string(),
  size: z.number(),
  mimeType: z.string()
});

export const EmailSchema = z.object({
  id: z.string().optional(),
  name: z.string().optional(),
  subject: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  dateSent: z.string().optional(),
  messageId: z.string().optional(),
  type: z.enum(['out', 'archived', 'draft', 'inbound', 'campaign']).optional(),
  status: z.enum(['archived', 'closed', 'draft', 'read', 'replied', 'sent', 'unread', 'bounced']).optional(),
  flagged: z.string().optional(),
  replyToStatus: z.string().optional(),
  intent: z.string().optional(),
  mailboxId: z.string().optional(),
  parentType: z.enum(['Contacts', 'Leads', 'Opportunities', 'Cases', 'Tasks']).optional(),
  parentId: z.string().optional(),
  assignedUserId: z.string().optional(),
  fromAddr: z.string().email().optional(),
  fromName: z.string().optional(),
  toAddrs: z.string().optional(),
  ccAddrs: z.string().optional(),
  bccAddrs: z.string().optional(),
  replyToAddr: z.string().email().optional(),
  description: z.string().optional(),
  descriptionHtml: z.string().optional(),
  rawSource: z.string().optional(),
  deleted: z.boolean().optional(),
  uid: z.string().optional(),
  msgno: z.string().optional(),
  folder: z.string().optional(),
  folderType: z.string().optional(),
  isRead: z.string().optional(),
  isReplied: z.string().optional(),
  isImported: z.string().optional(),
  isDraft: z.string().optional(),
  isSent: z.string().optional(),
  recipients: z.array(EmailRecipientSchema).optional(),
  recipientCount: z.number().optional(),
  attachments: z.array(EmailAttachmentSchema).optional(),
  attachmentCount: z.number().optional()
}).refine((data) => {
  return data.subject || data.name;
}, {
  message: "Email subject is required"
});
TS;
    }
}