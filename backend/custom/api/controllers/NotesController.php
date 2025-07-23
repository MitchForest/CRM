<?php
namespace Api\Controllers;

use Api\Controllers\BaseController;

/**
 * Notes Controller
 * 
 * Handles note-related API endpoints
 */
class NotesController extends BaseController
{
    protected $moduleName = 'Notes';
    
    /**
     * List notes with pagination and filtering
     */
    public function list($request, $response)
    {
        $filters = $request->getQueryParams();
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $pageSize = isset($filters['pageSize']) ? (int)$filters['pageSize'] : 20;
        $orderBy = isset($filters['orderBy']) ? $filters['orderBy'] : 'date_entered DESC';
        
        // Build query
        $query = "SELECT * FROM notes WHERE deleted = 0";
        $countQuery = "SELECT COUNT(*) as total FROM notes WHERE deleted = 0";
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
        
        $notes = [];
        while ($row = $db->fetchByAssoc($result)) {
            $note = \BeanFactory::getBean('Notes', $row['id']);
            if ($note) {
                $noteData = $this->formatNoteResponse($note);
                $notes[] = $noteData;
            }
        }
        
        return $response->json([
            'data' => $notes,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total,
                'totalPages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    /**
     * Get single note
     */
    public function get($request, $response)
    {
        $id = $request->getParam('id');
        
        $note = \BeanFactory::getBean('Notes', $id);
        if (!$note || empty($note->id)) {
            return $this->notFoundResponse($response, 'Note');
        }
        
        // Track view
        $this->trackNoteView($note);
        
        return $response->json($this->formatNoteResponse($note, true));
    }
    
    /**
     * Create new note
     */
    public function create($request, $response)
    {
        $data = json_decode($request->getBody(), true);
        
        $note = \BeanFactory::newBean('Notes');
        
        // Set basic fields
        $note->name = $data['name'] ?? '';
        $note->description = $data['description'] ?? '';
        $note->portal_flag = $data['portal_flag'] ?? '';
        $note->embed_flag = $data['embed_flag'] ?? '';
        
        // Set parent relationship
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $note->parent_type = $data['parent_type'];
            $note->parent_id = $data['parent_id'];
        }
        
        // Set contact
        if (!empty($data['contact_id'])) {
            $note->contact_id = $data['contact_id'];
        }
        
        // Set assigned user
        if (!empty($data['assigned_user_id'])) {
            $note->assigned_user_id = $data['assigned_user_id'];
        } else {
            global $current_user;
            $note->assigned_user_id = $current_user->id;
        }
        
        // Handle file attachment
        if (!empty($data['file'])) {
            $this->handleFileUpload($note, $data['file']);
        }
        
        $note->save();
        
        // Add tags if provided
        if (!empty($data['tags'])) {
            $this->saveTags($note, $data['tags']);
        }
        
        // Set privacy settings
        if (isset($data['is_private'])) {
            $this->setNotePrivacy($note, $data['is_private']);
        }
        
        return $response->json($this->formatNoteResponse($note), 201);
    }
    
    /**
     * Update note
     */
    public function update($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $note = \BeanFactory::getBean('Notes', $id);
        if (!$note || empty($note->id)) {
            return $this->notFoundResponse($response, 'Note');
        }
        
        // Update fields
        if (isset($data['name'])) $note->name = $data['name'];
        if (isset($data['description'])) $note->description = $data['description'];
        if (isset($data['portal_flag'])) $note->portal_flag = $data['portal_flag'];
        if (isset($data['embed_flag'])) $note->embed_flag = $data['embed_flag'];
        
        // Update file if provided
        if (!empty($data['file'])) {
            $this->handleFileUpload($note, $data['file']);
        }
        
        $note->save();
        
        // Update tags
        if (isset($data['tags'])) {
            $this->saveTags($note, $data['tags']);
        }
        
        // Update privacy
        if (isset($data['is_private'])) {
            $this->setNotePrivacy($note, $data['is_private']);
        }
        
        return $response->json($this->formatNoteResponse($note));
    }
    
    /**
     * Delete note
     */
    public function delete($request, $response)
    {
        $id = $request->getParam('id');
        
        $note = \BeanFactory::getBean('Notes', $id);
        if (!$note || empty($note->id)) {
            return $this->notFoundResponse($response, 'Note');
        }
        
        // Delete associated file
        if (!empty($note->filename)) {
            $this->deleteNoteFile($note);
        }
        
        $note->mark_deleted($id);
        
        return $response->status(204);
    }
    
    /**
     * Download note attachment
     */
    public function download($request, $response)
    {
        $id = $request->getParam('id');
        
        $note = \BeanFactory::getBean('Notes', $id);
        if (!$note || empty($note->id) || empty($note->filename)) {
            return $this->notFoundResponse($response, 'File');
        }
        
        // Get file path
        $filePath = $this->getNoteFilePath($note);
        if (!file_exists($filePath)) {
            return $this->notFoundResponse($response, 'File');
        }
        
        // Set headers for download
        header('Content-Type: ' . ($note->file_mime_type ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $note->filename . '"');
        header('Content-Length: ' . filesize($filePath));
        
        // Output file
        readfile($filePath);
        exit;
    }
    
    /**
     * Pin/unpin note
     */
    public function pin($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $note = \BeanFactory::getBean('Notes', $id);
        if (!$note || empty($note->id)) {
            return $this->notFoundResponse($response, 'Note');
        }
        
        $isPinned = $data['pinned'] ?? true;
        $this->setNotePinned($note, $isPinned);
        
        return $response->json([
            'message' => $isPinned ? 'Note pinned' : 'Note unpinned',
            'note' => $this->formatNoteResponse($note)
        ]);
    }
    
    /**
     * Search notes
     */
    public function search($request, $response)
    {
        $query = $request->getQueryParam('q', '');
        if (empty($query)) {
            return $this->validationErrorResponse($response, 'Search query required', ['q' => ['Search query is required']]);
        }
        
        global $db;
        $safeQuery = $db->quote('%' . $query . '%');
        
        $sql = "SELECT * FROM notes 
                WHERE deleted = 0 
                AND (name LIKE $safeQuery 
                     OR description LIKE $safeQuery 
                     OR filename LIKE $safeQuery)
                ORDER BY date_entered DESC
                LIMIT 50";
        
        $result = $db->query($sql);
        $notes = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $note = \BeanFactory::getBean('Notes', $row['id']);
            if ($note) {
                $notes[] = $this->formatNoteResponse($note);
            }
        }
        
        return $response->json($notes);
    }
    
    /**
     * Get notes by parent
     */
    public function byParent($request, $response)
    {
        $parentType = $request->getQueryParam('parent_type');
        $parentId = $request->getQueryParam('parent_id');
        
        if (empty($parentType) || empty($parentId)) {
            return $this->validationErrorResponse($response, 'Parent type and ID required', ['parent_type' => ['Parent type is required'], 'parent_id' => ['Parent ID is required']]);
        }
        
        global $db;
        $safeParentType = $db->quote($parentType);
        $safeParentId = $db->quote($parentId);
        
        $query = "SELECT * FROM notes 
                 WHERE deleted = 0 
                 AND parent_type = $safeParentType 
                 AND parent_id = $safeParentId
                 ORDER BY date_entered DESC";
        
        $result = $db->query($query);
        $notes = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $note = \BeanFactory::getBean('Notes', $row['id']);
            if ($note) {
                $notes[] = $this->formatNoteResponse($note);
            }
        }
        
        return $response->json($notes);
    }
    
    /**
     * Get recent notes
     */
    public function recent($request, $response)
    {
        $days = $request->getQueryParam('days', 7);
        
        global $db;
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $safeDate = $db->quote($date);
        
        $query = "SELECT * FROM notes 
                 WHERE deleted = 0 
                 AND date_entered >= $safeDate
                 ORDER BY date_entered DESC
                 LIMIT 50";
        
        $result = $db->query($query);
        $notes = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $note = \BeanFactory::getBean('Notes', $row['id']);
            if ($note) {
                $notes[] = $this->formatNoteResponse($note);
            }
        }
        
        return $response->json($notes);
    }
    
    /**
     * Get pinned notes
     */
    public function pinned($request, $response)
    {
        global $db;
        
        // Using custom field for pinned status
        $query = "SELECT n.* FROM notes n
                 LEFT JOIN notes_cstm nc ON n.id = nc.id_c
                 WHERE n.deleted = 0 
                 AND nc.is_pinned_c = 1
                 ORDER BY n.date_entered DESC";
        
        $result = $db->query($query);
        $notes = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $note = \BeanFactory::getBean('Notes', $row['id']);
            if ($note) {
                $notes[] = $this->formatNoteResponse($note);
            }
        }
        
        return $response->json($notes);
    }
    
    /**
     * Format note response
     */
    protected function formatNoteResponse($note, $includeContent = false)
    {
        $data = [
            'id' => $note->id,
            'name' => $note->name,
            'filename' => $note->filename,
            'file_mime_type' => $note->file_mime_type,
            'parent_type' => $note->parent_type,
            'parent_id' => $note->parent_id,
            'contact_id' => $note->contact_id,
            'assigned_user_id' => $note->assigned_user_id,
            'date_entered' => $note->date_entered,
            'date_modified' => $note->date_modified,
            'portal_flag' => $note->portal_flag,
            'embed_flag' => $note->embed_flag
        ];
        
        // Determine note type
        if (!empty($note->filename)) {
            $data['note_type'] = 'attachment';
        } elseif ($note->parent_type === 'Emails') {
            $data['note_type'] = 'email_attachment';
        } else {
            $data['note_type'] = 'general';
        }
        
        if ($includeContent) {
            $data['description'] = $note->description;
            $data['file_url'] = !empty($note->filename) ? "/api/notes/{$note->id}/download" : null;
            $data['file_size'] = $this->getNoteFileSize($note);
            $data['formatted_file_size'] = $this->formatFileSize($data['file_size']);
            
            // Get custom fields
            $data['is_pinned'] = $this->isNotePinned($note);
            $data['is_private'] = $this->isNotePrivate($note);
            $data['tags'] = $this->getNoteTags($note);
            $data['view_count'] = $this->getNoteViewCount($note);
            $data['last_viewed_date'] = $this->getNoteLastViewedDate($note);
        }
        
        // Get contact name
        if (!empty($note->contact_id)) {
            $contact = \BeanFactory::getBean('Contacts', $note->contact_id);
            if ($contact) {
                $data['contact_name'] = trim($contact->first_name . ' ' . $contact->last_name);
            }
        }
        
        // Get parent name
        if (!empty($note->parent_type) && !empty($note->parent_id)) {
            $parent = \BeanFactory::getBean($note->parent_type, $note->parent_id);
            if ($parent) {
                $data['parent_name'] = $parent->name;
            }
        }
        
        // Get assigned user name
        if (!empty($note->assigned_user_id)) {
            $user = \BeanFactory::getBean('Users', $note->assigned_user_id);
            if ($user) {
                $data['assigned_user_name'] = trim($user->first_name . ' ' . $user->last_name);
            }
        }
        
        // Get created by name
        if (!empty($note->created_by)) {
            $user = \BeanFactory::getBean('Users', $note->created_by);
            if ($user) {
                $data['created_by_name'] = trim($user->first_name . ' ' . $user->last_name);
            }
        }
        
        return $data;
    }
    
    /**
     * Handle file upload
     */
    protected function handleFileUpload($note, $fileData)
    {
        // In a real implementation, this would handle actual file upload
        // For now, we'll just set the metadata
        if (!empty($fileData['name'])) {
            $note->filename = $fileData['name'];
            $note->file_mime_type = $fileData['mime_type'] ?? $this->getMimeType($fileData['name']);
            $note->file_url = $fileData['url'] ?? '';
            
            // Generate attachment ID
            $note->attachment_id = create_guid();
        }
    }
    
    /**
     * Get file path for note
     */
    protected function getNoteFilePath($note)
    {
        global $sugar_config;
        $uploadDir = $sugar_config['upload_dir'] ?? 'upload/';
        return $uploadDir . $note->id;
    }
    
    /**
     * Delete note file
     */
    protected function deleteNoteFile($note)
    {
        $filePath = $this->getNoteFilePath($note);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    /**
     * Get note file size
     */
    protected function getNoteFileSize($note)
    {
        if (empty($note->filename)) {
            return 0;
        }
        
        $filePath = $this->getNoteFilePath($note);
        return file_exists($filePath) ? filesize($filePath) : 0;
    }
    
    /**
     * Format file size
     */
    protected function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * Get MIME type from filename
     */
    protected function getMimeType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
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
            'csv' => 'text/csv',
            'zip' => 'application/zip'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Custom field helpers (would use notes_cstm table in real implementation)
     */
    protected function isNotePinned($note)
    {
        // In real implementation, query notes_cstm.is_pinned_c
        return false;
    }
    
    protected function setNotePinned($note, $isPinned)
    {
        // In real implementation, update notes_cstm.is_pinned_c
        global $db;
        $safeId = $db->quote($note->id);
        $safePinned = $isPinned ? 1 : 0;
        
        $db->query("INSERT INTO notes_cstm (id_c, is_pinned_c) VALUES ($safeId, $safePinned) 
                   ON DUPLICATE KEY UPDATE is_pinned_c = $safePinned");
    }
    
    protected function isNotePrivate($note)
    {
        // In real implementation, check ACL or custom field
        return false;
    }
    
    protected function setNotePrivacy($note, $isPrivate)
    {
        // In real implementation, update ACL or custom field
    }
    
    protected function getNoteTags($note)
    {
        // In real implementation, query tags relationship table
        return [];
    }
    
    protected function saveTags($note, $tags)
    {
        // In real implementation, save to tags relationship table
    }
    
    protected function getNoteViewCount($note)
    {
        // In real implementation, query view tracking table
        return 0;
    }
    
    protected function getNoteLastViewedDate($note)
    {
        // In real implementation, query view tracking table
        return null;
    }
    
    protected function trackNoteView($note)
    {
        // In real implementation, insert into view tracking table
        global $db, $current_user;
        
        $safeNoteId = $db->quote($note->id);
        $safeUserId = $db->quote($current_user->id);
        $now = date('Y-m-d H:i:s');
        
        $db->query("INSERT INTO note_views (note_id, user_id, view_date) 
                   VALUES ($safeNoteId, $safeUserId, '$now')");
    }
}