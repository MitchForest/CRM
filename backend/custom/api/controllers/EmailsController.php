<?php
namespace Api\Controllers;

use Api\Controllers\BaseController;

/**
 * Emails Controller
 * 
 * Handles email-related API endpoints
 */
class EmailsController extends BaseController
{
    protected $moduleName = 'Emails';
    
    /**
     * List emails with pagination and filtering
     */
    public function list($request, $response)
    {
        $filters = $request->getQueryParams();
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $pageSize = isset($filters['pageSize']) ? (int)$filters['pageSize'] : 20;
        $orderBy = isset($filters['orderBy']) ? $filters['orderBy'] : 'date_sent DESC';
        
        // Build query
        $query = "SELECT * FROM emails WHERE deleted = 0";
        $countQuery = "SELECT COUNT(*) as total FROM emails WHERE deleted = 0";
        $params = [];
        
        // Add filters
        $where = $this->buildWhereClause($filters, ['page', 'pageSize', 'orderBy']);
        if ($where) {
            $query .= " AND " . $where;
            $countQuery .= " AND " . $where;
        }
        
        // Add ordering
        $query .= " ORDER BY " . $this->sanitizeOrderBy($orderBy);
        
        // Add pagination
        $offset = ($page - 1) * $pageSize;
        $query .= " LIMIT $pageSize OFFSET $offset";
        
        // Execute queries
        global $db;
        $result = $db->query($query);
        $countResult = $db->query($countQuery);
        $totalRow = $db->fetchByAssoc($countResult);
        $total = $totalRow['total'];
        
        $emails = [];
        while ($row = $db->fetchByAssoc($result)) {
            $email = \BeanFactory::getBean('Emails', $row['id']);
            if ($email) {
                $emailData = $this->formatEmailResponse($email);
                $emails[] = $emailData;
            }
        }
        
        return $response->json([
            'data' => $emails,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total,
                'totalPages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    /**
     * Get single email
     */
    public function get($request, $response)
    {
        $id = $request->getParam('id');
        
        $email = \BeanFactory::getBean('Emails', $id);
        if (!$email || empty($email->id)) {
            return $response->json(['error' => 'Email not found'], 404);
        }
        
        // Mark as read if viewing
        if ($email->status === 'unread') {
            $email->status = 'read';
            $email->is_read = '1';
            $email->save();
        }
        
        return $response->json($this->formatEmailResponse($email, true));
    }
    
    /**
     * Create new email
     */
    public function create($request, $response)
    {
        $data = json_decode($request->getBody(), true);
        
        $email = \BeanFactory::newBean('Emails');
        
        // Set basic fields
        $email->name = $data['subject'] ?? '';
        $email->description = $data['description'] ?? '';
        $email->description_html = $data['description_html'] ?? $data['description'] ?? '';
        $email->type = $data['type'] ?? 'out';
        $email->status = $data['status'] ?? 'draft';
        
        // Set addresses
        $email->from_addr = $data['from_addr'] ?? '';
        $email->from_name = $data['from_name'] ?? '';
        $email->to_addrs = $data['to_addrs'] ?? '';
        $email->cc_addrs = $data['cc_addrs'] ?? '';
        $email->bcc_addrs = $data['bcc_addrs'] ?? '';
        $email->reply_to_addr = $data['reply_to_addr'] ?? '';
        
        // Set parent relationship
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $email->parent_type = $data['parent_type'];
            $email->parent_id = $data['parent_id'];
        }
        
        // Set assigned user
        if (!empty($data['assigned_user_id'])) {
            $email->assigned_user_id = $data['assigned_user_id'];
        } else {
            global $current_user;
            $email->assigned_user_id = $current_user->id;
        }
        
        $email->save();
        
        // Add recipients to relationships
        if (!empty($data['contact_ids'])) {
            $email->load_relationship('contacts');
            foreach ($data['contact_ids'] as $contactId) {
                $email->contacts->add($contactId);
            }
        }
        
        // Handle attachments
        if (!empty($data['attachments'])) {
            $email->load_relationship('notes');
            foreach ($data['attachments'] as $attachment) {
                $note = \BeanFactory::newBean('Notes');
                $note->name = $attachment['name'];
                $note->filename = $attachment['filename'];
                $note->file_mime_type = $attachment['mime_type'] ?? '';
                $note->parent_type = 'Emails';
                $note->parent_id = $email->id;
                $note->save();
                
                $email->notes->add($note->id);
            }
        }
        
        return $response->json($this->formatEmailResponse($email), 201);
    }
    
    /**
     * Update email
     */
    public function update($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $email = \BeanFactory::getBean('Emails', $id);
        if (!$email || empty($email->id)) {
            return $response->json(['error' => 'Email not found'], 404);
        }
        
        // Only allow updates to draft emails
        if ($email->status !== 'draft' && $email->status !== 'archived') {
            return $response->json(['error' => 'Cannot update sent emails'], 400);
        }
        
        // Update fields
        if (isset($data['subject'])) $email->name = $data['subject'];
        if (isset($data['description'])) $email->description = $data['description'];
        if (isset($data['description_html'])) $email->description_html = $data['description_html'];
        if (isset($data['to_addrs'])) $email->to_addrs = $data['to_addrs'];
        if (isset($data['cc_addrs'])) $email->cc_addrs = $data['cc_addrs'];
        if (isset($data['bcc_addrs'])) $email->bcc_addrs = $data['bcc_addrs'];
        if (isset($data['status'])) $email->status = $data['status'];
        
        $email->save();
        
        return $response->json($this->formatEmailResponse($email));
    }
    
    /**
     * Send email
     */
    public function send($request, $response)
    {
        $id = $request->getParam('id');
        
        $email = \BeanFactory::getBean('Emails', $id);
        if (!$email || empty($email->id)) {
            return $response->json(['error' => 'Email not found'], 404);
        }
        
        if ($email->status === 'sent') {
            return $response->json(['error' => 'Email already sent'], 400);
        }
        
        // Validate required fields
        if (empty($email->to_addrs)) {
            return $response->json(['error' => 'Recipients required'], 400);
        }
        
        if (empty($email->name)) {
            return $response->json(['error' => 'Subject required'], 400);
        }
        
        // In real implementation, this would use SugarPHPMailer
        // For now, we'll just update the status
        $email->status = 'sent';
        $email->date_sent = date('Y-m-d H:i:s');
        $email->is_sent = '1';
        $email->type = 'out';
        $email->save();
        
        // Create email-to-contact relationships
        $this->createEmailRelationships($email);
        
        return $response->json([
            'message' => 'Email sent successfully',
            'email' => $this->formatEmailResponse($email)
        ]);
    }
    
    /**
     * Delete email
     */
    public function delete($request, $response)
    {
        $id = $request->getParam('id');
        
        $email = \BeanFactory::getBean('Emails', $id);
        if (!$email || empty($email->id)) {
            return $response->json(['error' => 'Email not found'], 404);
        }
        
        $email->mark_deleted($id);
        
        return $response->status(204);
    }
    
    /**
     * Reply to email
     */
    public function reply($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $originalEmail = \BeanFactory::getBean('Emails', $id);
        if (!$originalEmail || empty($originalEmail->id)) {
            return $response->json(['error' => 'Email not found'], 404);
        }
        
        // Create reply email
        $reply = \BeanFactory::newBean('Emails');
        $reply->name = 'Re: ' . $originalEmail->name;
        $reply->description = $data['description'] ?? '';
        $reply->description_html = $data['description_html'] ?? $data['description'] ?? '';
        $reply->type = 'out';
        $reply->status = 'draft';
        
        // Set reply addresses
        $reply->to_addrs = $originalEmail->from_addr;
        $reply->from_addr = $originalEmail->to_addrs;
        $reply->parent_type = $originalEmail->parent_type;
        $reply->parent_id = $originalEmail->parent_id;
        
        // Add reply reference
        $reply->reply_to_status = 'replied';
        
        global $current_user;
        $reply->assigned_user_id = $current_user->id;
        
        $reply->save();
        
        // Update original email status
        $originalEmail->reply_to_status = 'replied';
        $originalEmail->is_replied = '1';
        $originalEmail->save();
        
        return $response->json($this->formatEmailResponse($reply), 201);
    }
    
    /**
     * Forward email
     */
    public function forward($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $originalEmail = \BeanFactory::getBean('Emails', $id);
        if (!$originalEmail || empty($originalEmail->id)) {
            return $response->json(['error' => 'Email not found'], 404);
        }
        
        // Create forward email
        $forward = \BeanFactory::newBean('Emails');
        $forward->name = 'Fwd: ' . $originalEmail->name;
        $forward->description = $data['description'] . "\n\n--- Forwarded message ---\n" . $originalEmail->description;
        $forward->description_html = $data['description_html'] ?? $forward->description;
        $forward->type = 'out';
        $forward->status = 'draft';
        
        // Set forward addresses
        $forward->to_addrs = $data['to_addrs'] ?? '';
        $forward->cc_addrs = $data['cc_addrs'] ?? '';
        $forward->from_addr = $originalEmail->to_addrs;
        
        global $current_user;
        $forward->assigned_user_id = $current_user->id;
        
        $forward->save();
        
        // Copy attachments
        $originalEmail->load_relationship('notes');
        $notes = $originalEmail->notes->getBeans();
        if (!empty($notes)) {
            $forward->load_relationship('notes');
            foreach ($notes as $note) {
                $forward->notes->add($note->id);
            }
        }
        
        return $response->json($this->formatEmailResponse($forward), 201);
    }
    
    /**
     * Get email inbox
     */
    public function inbox($request, $response)
    {
        $filters = $request->getQueryParams();
        $filters['type'] = 'inbound';
        $filters['status'] = ['ne' => 'draft'];
        
        $request->queryParams = $filters;
        return $this->list($request, $response);
    }
    
    /**
     * Get sent emails
     */
    public function sent($request, $response)
    {
        $filters = $request->getQueryParams();
        $filters['status'] = 'sent';
        $filters['type'] = 'out';
        
        $request->queryParams = $filters;
        return $this->list($request, $response);
    }
    
    /**
     * Get draft emails
     */
    public function drafts($request, $response)
    {
        $filters = $request->getQueryParams();
        $filters['status'] = 'draft';
        
        $request->queryParams = $filters;
        return $this->list($request, $response);
    }
    
    /**
     * Format email response
     */
    protected function formatEmailResponse($email, $includeBody = false)
    {
        $data = [
            'id' => $email->id,
            'subject' => $email->name,
            'type' => $email->type,
            'status' => $email->status,
            'date_entered' => $email->date_entered,
            'date_modified' => $email->date_modified,
            'date_sent' => $email->date_sent,
            'from_addr' => $email->from_addr,
            'from_name' => $email->from_name,
            'to_addrs' => $email->to_addrs,
            'cc_addrs' => $email->cc_addrs,
            'bcc_addrs' => $email->bcc_addrs,
            'reply_to_addr' => $email->reply_to_addr,
            'parent_type' => $email->parent_type,
            'parent_id' => $email->parent_id,
            'assigned_user_id' => $email->assigned_user_id,
            'is_read' => $email->is_read,
            'is_replied' => $email->is_replied,
            'flagged' => $email->flagged
        ];
        
        if ($includeBody) {
            $data['description'] = $email->description;
            $data['description_html'] = $email->description_html;
            
            // Load attachments
            $email->load_relationship('notes');
            $notes = $email->notes->getBeans();
            $attachments = [];
            foreach ($notes as $note) {
                $attachments[] = [
                    'id' => $note->id,
                    'name' => $note->name,
                    'filename' => $note->filename,
                    'file_mime_type' => $note->file_mime_type
                ];
            }
            $data['attachments'] = $attachments;
            
            // Load related contacts
            $email->load_relationship('contacts');
            $contacts = $email->contacts->getBeans();
            $contactList = [];
            foreach ($contacts as $contact) {
                $contactList[] = [
                    'id' => $contact->id,
                    'name' => trim($contact->first_name . ' ' . $contact->last_name),
                    'email' => $contact->email1
                ];
            }
            $data['contacts'] = $contactList;
        }
        
        // Get parent name if exists
        if (!empty($email->parent_type) && !empty($email->parent_id)) {
            $parent = \BeanFactory::getBean($email->parent_type, $email->parent_id);
            if ($parent) {
                $data['parent_name'] = $parent->name;
            }
        }
        
        // Get assigned user name
        if (!empty($email->assigned_user_id)) {
            $user = \BeanFactory::getBean('Users', $email->assigned_user_id);
            if ($user) {
                $data['assigned_user_name'] = trim($user->first_name . ' ' . $user->last_name);
            }
        }
        
        return $data;
    }
    
    /**
     * Create email-to-contact relationships based on addresses
     */
    protected function createEmailRelationships($email)
    {
        global $db;
        
        // Get all email addresses
        $addresses = [];
        if (!empty($email->to_addrs)) {
            $addresses = array_merge($addresses, explode(',', $email->to_addrs));
        }
        if (!empty($email->cc_addrs)) {
            $addresses = array_merge($addresses, explode(',', $email->cc_addrs));
        }
        
        // Clean and unique addresses
        $addresses = array_unique(array_map('trim', $addresses));
        
        // Find contacts with these emails
        $email->load_relationship('contacts');
        foreach ($addresses as $address) {
            if (empty($address)) continue;
            
            $safeEmail = $db->quote($address);
            $query = "SELECT id FROM contacts WHERE email1 = $safeEmail AND deleted = 0";
            $result = $db->query($query);
            
            while ($row = $db->fetchByAssoc($result)) {
                $email->contacts->add($row['id']);
            }
        }
    }
}