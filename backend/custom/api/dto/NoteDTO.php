<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Note Data Transfer Object
 */
class NoteDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $modifiedUserId = null;
    protected ?string $modifiedByName = null;
    protected ?string $createdBy = null;
    protected ?string $createdByName = null;
    protected ?string $description = null;
    protected ?bool $deleted = null;
    protected ?string $assignedUserId = null;
    protected ?string $assignedUserName = null;
    
    // Note-specific fields
    protected ?string $filename = null;
    protected ?string $fileUrl = null;
    protected ?string $fileMimeType = null;
    protected ?string $parentType = null;
    protected ?string $parentId = null;
    protected ?string $parentName = null;
    protected ?string $contactId = null;
    protected ?string $contactName = null;
    protected ?string $portalFlag = null;
    protected ?string $embedFlag = null;
    protected ?string $attachmentId = null;
    protected ?string $attachmentType = null;
    protected ?string $attachmentFlag = null;
    protected ?int $fileSize = null;
    protected ?string $uploadDate = null;
    
    // Additional metadata
    protected ?string $noteType = null; // general, attachment, email_attachment
    protected ?string $noteCategory = null;
    protected ?array $tags = null;
    protected ?bool $isPinned = null;
    protected ?bool $isPrivate = null;
    protected ?int $viewCount = null;
    protected ?string $lastViewedDate = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->name) && empty($this->filename)) {
            $this->addError('name', 'Note subject or filename is required');
        }
        
        // Parent type validation
        $validParentTypes = ['Contacts', 'Leads', 'Opportunities', 'Cases', 'Tasks', 'Quotes', 'Emails', 'Calls', 'Meetings'];
        if (!empty($this->parentType) && !in_array($this->parentType, $validParentTypes)) {
            $this->addError('parent_type', 'Invalid parent type');
        }
        
        // File size validation
        if ($this->fileSize !== null && $this->fileSize < 0) {
            $this->addError('file_size', 'File size must be non-negative');
        }
        
        // Note type validation
        if (!empty($this->noteType)) {
            $validNoteTypes = ['general', 'attachment', 'email_attachment'];
            if (!in_array($this->noteType, $validNoteTypes)) {
                $this->addError('note_type', 'Invalid note type');
            }
        }
        
        // File validation
        if (!empty($this->filename) && empty($this->fileMimeType)) {
            // Try to determine mime type from filename
            $extension = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                'csv' => 'text/csv'
            ];
            
            if (isset($mimeTypes[$extension])) {
                $this->fileMimeType = $mimeTypes[$extension];
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
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    
    public function getFilename(): ?string
    {
        return $this->filename;
    }
    
    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        
        // Auto-set note type if filename is present
        if (!empty($filename)) {
            $this->noteType = 'attachment';
        }
        
        return $this;
    }
    
    public function getParentType(): ?string
    {
        return $this->parentType;
    }
    
    public function setParentType(?string $parentType): self
    {
        $this->parentType = $parentType;
        return $this;
    }
    
    public function getParentId(): ?string
    {
        return $this->parentId;
    }
    
    public function setParentId(?string $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }
    
    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }
    
    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }
    
    public function getTags(): ?array
    {
        return $this->tags;
    }
    
    public function setTags(?array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }
    
    public function addTag(string $tag): self
    {
        if ($this->tags === null) {
            $this->tags = [];
        }
        
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
        
        return $this;
    }
    
    public function getIsPinned(): ?bool
    {
        return $this->isPinned;
    }
    
    public function setIsPinned(?bool $isPinned): self
    {
        $this->isPinned = $isPinned;
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
            ->setName($bean->name)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setModifiedUserId($bean->modified_user_id)
            ->setCreatedBy($bean->created_by)
            ->setDescription($bean->description)
            ->setDeleted((bool)$bean->deleted)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setFilename($bean->filename)
            ->setFileUrl($bean->file_url)
            ->setFileMimeType($bean->file_mime_type)
            ->setParentType($bean->parent_type)
            ->setParentId($bean->parent_id)
            ->setContactId($bean->contact_id)
            ->setPortalFlag($bean->portal_flag)
            ->setEmbedFlag($bean->embed_flag)
            ->setAttachmentId($bean->attachment_id);
            
        // Load related contact name if available
        if (!empty($bean->contact_id)) {
            $contact = \BeanFactory::getBean('Contacts', $bean->contact_id);
            if ($contact) {
                $dto->setContactName(trim($contact->first_name . ' ' . $contact->last_name));
            }
        }
        
        // Load parent name based on parent type
        if (!empty($bean->parent_type) && !empty($bean->parent_id)) {
            $parentBean = \BeanFactory::getBean($bean->parent_type, $bean->parent_id);
            if ($parentBean) {
                $dto->setParentName($parentBean->name);
            }
        }
        
        // Determine note type
        if (!empty($bean->filename)) {
            $dto->setNoteType('attachment');
        } elseif (!empty($bean->parent_type) && $bean->parent_type === 'Emails') {
            $dto->setNoteType('email_attachment');
        } else {
            $dto->setNoteType('general');
        }
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->name !== null) $bean->name = $this->name;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->filename !== null) $bean->filename = $this->filename;
        if ($this->fileUrl !== null) $bean->file_url = $this->fileUrl;
        if ($this->fileMimeType !== null) $bean->file_mime_type = $this->fileMimeType;
        if ($this->parentType !== null) $bean->parent_type = $this->parentType;
        if ($this->parentId !== null) $bean->parent_id = $this->parentId;
        if ($this->contactId !== null) $bean->contact_id = $this->contactId;
        if ($this->portalFlag !== null) $bean->portal_flag = $this->portalFlag;
        if ($this->embedFlag !== null) $bean->embed_flag = $this->embedFlag;
        if ($this->attachmentId !== null) $bean->attachment_id = $this->attachmentId;
    }
    
    /**
     * Get human-readable file size
     */
    public function getFormattedFileSize(): string
    {
        if ($this->fileSize === null) {
            return '';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Note {
  id?: string;
  name?: string;
  dateEntered?: string;
  dateModified?: string;
  modifiedUserId?: string;
  modifiedByName?: string;
  createdBy?: string;
  createdByName?: string;
  description?: string;
  deleted?: boolean;
  assignedUserId?: string;
  assignedUserName?: string;
  filename?: string;
  fileUrl?: string;
  fileMimeType?: string;
  parentType?: 'Contacts' | 'Leads' | 'Opportunities' | 'Cases' | 'Tasks' | 'Quotes' | 'Emails' | 'Calls' | 'Meetings';
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  portalFlag?: string;
  embedFlag?: string;
  attachmentId?: string;
  attachmentType?: string;
  attachmentFlag?: string;
  fileSize?: number;
  uploadDate?: string;
  noteType?: 'general' | 'attachment' | 'email_attachment';
  noteCategory?: string;
  tags?: string[];
  isPinned?: boolean;
  isPrivate?: boolean;
  viewCount?: number;
  lastViewedDate?: string;
  formattedFileSize?: string;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const NoteSchema = z.object({
  id: z.string().optional(),
  name: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  modifiedUserId: z.string().optional(),
  modifiedByName: z.string().optional(),
  createdBy: z.string().optional(),
  createdByName: z.string().optional(),
  description: z.string().optional(),
  deleted: z.boolean().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  filename: z.string().optional(),
  fileUrl: z.string().optional(),
  fileMimeType: z.string().optional(),
  parentType: z.enum(['Contacts', 'Leads', 'Opportunities', 'Cases', 'Tasks', 'Quotes', 'Emails', 'Calls', 'Meetings']).optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  portalFlag: z.string().optional(),
  embedFlag: z.string().optional(),
  attachmentId: z.string().optional(),
  attachmentType: z.string().optional(),
  attachmentFlag: z.string().optional(),
  fileSize: z.number().min(0).optional(),
  uploadDate: z.string().optional(),
  noteType: z.enum(['general', 'attachment', 'email_attachment']).optional(),
  noteCategory: z.string().optional(),
  tags: z.array(z.string()).optional(),
  isPinned: z.boolean().optional(),
  isPrivate: z.boolean().optional(),
  viewCount: z.number().min(0).optional(),
  lastViewedDate: z.string().optional(),
  formattedFileSize: z.string().optional()
}).refine((data) => {
  return data.name || data.filename;
}, {
  message: "Note subject or filename is required"
});
TS;
    }
}