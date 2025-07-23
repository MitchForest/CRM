<?php
namespace Api\Controllers;

use Api\Controllers\BaseController;

/**
 * Meetings Controller
 * 
 * Handles meeting-related API endpoints
 */
class MeetingsController extends BaseController
{
    protected $moduleName = 'Meetings';
    
    /**
     * List meetings with pagination and filtering
     */
    public function list($request, $response)
    {
        $filters = $request->getQueryParams();
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $pageSize = isset($filters['pageSize']) ? (int)$filters['pageSize'] : 20;
        $orderBy = isset($filters['orderBy']) ? $filters['orderBy'] : 'date_start DESC';
        
        // Build query
        $query = "SELECT * FROM meetings WHERE deleted = 0";
        $countQuery = "SELECT COUNT(*) as total FROM meetings WHERE deleted = 0";
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
        
        $meetings = [];
        while ($row = $db->fetchByAssoc($result)) {
            $meeting = \BeanFactory::getBean('Meetings', $row['id']);
            if ($meeting) {
                $meetingData = $this->formatMeetingResponse($meeting);
                $meetings[] = $meetingData;
            }
        }
        
        return $response->json([
            'data' => $meetings,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total,
                'totalPages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    /**
     * Get single meeting
     */
    public function get($request, $response)
    {
        $id = $request->getParam('id');
        
        $meeting = \BeanFactory::getBean('Meetings', $id);
        if (!$meeting || empty($meeting->id)) {
            return $this->notFoundResponse($response, 'Meeting');
        }
        
        return $response->json($this->formatMeetingResponse($meeting, true));
    }
    
    /**
     * Create new meeting
     */
    public function create($request, $response)
    {
        $data = json_decode($request->getBody(), true);
        
        $meeting = \BeanFactory::newBean('Meetings');
        
        // Set basic fields
        $meeting->name = $data['name'] ?? '';
        $meeting->description = $data['description'] ?? '';
        $meeting->status = $data['status'] ?? 'Planned';
        $meeting->type = $data['type'] ?? 'Meeting';
        $meeting->location = $data['location'] ?? '';
        $meeting->date_start = $data['date_start'] ?? date('Y-m-d H:i:s');
        $meeting->duration_hours = $data['duration_hours'] ?? 1;
        $meeting->duration_minutes = $data['duration_minutes'] ?? 0;
        
        // Calculate end date
        if (!empty($data['date_start'])) {
            $startTime = strtotime($data['date_start']);
            $duration = ($meeting->duration_hours * 3600) + ($meeting->duration_minutes * 60);
            $meeting->date_end = date('Y-m-d H:i:s', $startTime + $duration);
        }
        
        // Set meeting-specific fields
        $meeting->password_protected = $data['password_protected'] ?? '';
        $meeting->meeting_password = $data['meeting_password'] ?? '';
        $meeting->external_url = $data['external_url'] ?? '';
        
        // Set parent relationship
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $meeting->parent_type = $data['parent_type'];
            $meeting->parent_id = $data['parent_id'];
        }
        
        // Set contact
        if (!empty($data['contact_id'])) {
            $meeting->contact_id = $data['contact_id'];
        }
        
        // Set reminder
        if (!empty($data['reminder_time'])) {
            $meeting->reminder_time = $data['reminder_time'];
        }
        if (!empty($data['email_reminder_time'])) {
            $meeting->email_reminder_time = $data['email_reminder_time'];
        }
        
        // Set assigned user
        if (!empty($data['assigned_user_id'])) {
            $meeting->assigned_user_id = $data['assigned_user_id'];
        } else {
            global $current_user;
            $meeting->assigned_user_id = $current_user->id;
        }
        
        $meeting->save();
        
        // Add invitees
        if (!empty($data['invitee_ids'])) {
            $meeting->load_relationship('users');
            foreach ($data['invitee_ids'] as $userId) {
                $meeting->users->add($userId, ['accept_status' => 'none']);
            }
        }
        
        // Add contacts as invitees
        if (!empty($data['contact_ids'])) {
            $meeting->load_relationship('contacts');
            foreach ($data['contact_ids'] as $contactId) {
                $meeting->contacts->add($contactId, ['accept_status' => 'none']);
            }
        }
        
        return $response->json($this->formatMeetingResponse($meeting), 201);
    }
    
    /**
     * Update meeting
     */
    public function update($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $meeting = \BeanFactory::getBean('Meetings', $id);
        if (!$meeting || empty($meeting->id)) {
            return $this->notFoundResponse($response, 'Meeting');
        }
        
        // Update fields
        if (isset($data['name'])) $meeting->name = $data['name'];
        if (isset($data['description'])) $meeting->description = $data['description'];
        if (isset($data['status'])) $meeting->status = $data['status'];
        if (isset($data['type'])) $meeting->type = $data['type'];
        if (isset($data['location'])) $meeting->location = $data['location'];
        if (isset($data['date_start'])) $meeting->date_start = $data['date_start'];
        if (isset($data['duration_hours'])) $meeting->duration_hours = $data['duration_hours'];
        if (isset($data['duration_minutes'])) $meeting->duration_minutes = $data['duration_minutes'];
        if (isset($data['external_url'])) $meeting->external_url = $data['external_url'];
        if (isset($data['password_protected'])) $meeting->password_protected = $data['password_protected'];
        if (isset($data['meeting_password'])) $meeting->meeting_password = $data['meeting_password'];
        
        // Recalculate end date if start or duration changed
        if (isset($data['date_start']) || isset($data['duration_hours']) || isset($data['duration_minutes'])) {
            $startTime = strtotime($meeting->date_start);
            $duration = ($meeting->duration_hours * 3600) + ($meeting->duration_minutes * 60);
            $meeting->date_end = date('Y-m-d H:i:s', $startTime + $duration);
        }
        
        $meeting->save();
        
        return $response->json($this->formatMeetingResponse($meeting));
    }
    
    /**
     * Delete meeting
     */
    public function delete($request, $response)
    {
        $id = $request->getParam('id');
        
        $meeting = \BeanFactory::getBean('Meetings', $id);
        if (!$meeting || empty($meeting->id)) {
            return $this->notFoundResponse($response, 'Meeting');
        }
        
        $meeting->mark_deleted($id);
        
        return $response->status(204);
    }
    
    /**
     * Mark meeting as held
     */
    public function hold($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $meeting = \BeanFactory::getBean('Meetings', $id);
        if (!$meeting || empty($meeting->id)) {
            return $this->notFoundResponse($response, 'Meeting');
        }
        
        $meeting->status = 'Held';
        
        // Add meeting notes if provided
        if (!empty($data['notes'])) {
            $meeting->description = $meeting->description . "\n\nMeeting Notes:\n" . $data['notes'];
        }
        
        $meeting->save();
        
        return $response->json([
            'message' => 'Meeting marked as held',
            'meeting' => $this->formatMeetingResponse($meeting)
        ]);
    }
    
    /**
     * Cancel meeting
     */
    public function cancel($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        $meeting = \BeanFactory::getBean('Meetings', $id);
        if (!$meeting || empty($meeting->id)) {
            return $this->notFoundResponse($response, 'Meeting');
        }
        
        $meeting->status = 'Not Held';
        if (!empty($data['reason'])) {
            $meeting->description = $meeting->description . "\n\nCancellation Reason: " . $data['reason'];
        }
        
        $meeting->save();
        
        // TODO: Send cancellation notifications to invitees
        
        return $response->json([
            'message' => 'Meeting cancelled',
            'meeting' => $this->formatMeetingResponse($meeting)
        ]);
    }
    
    /**
     * Update invitee status
     */
    public function updateInviteeStatus($request, $response)
    {
        $id = $request->getParam('id');
        $data = json_decode($request->getBody(), true);
        
        if (empty($data['invitee_id']) || empty($data['status'])) {
            return $this->validationErrorResponse($response, 'Invitee ID and status required', ['invitee_id' => ['Invitee ID is required'], 'status' => ['Status is required']]);
        }
        
        $meeting = \BeanFactory::getBean('Meetings', $id);
        if (!$meeting || empty($meeting->id)) {
            return $this->notFoundResponse($response, 'Meeting');
        }
        
        // Update user invitee status
        $meeting->load_relationship('users');
        $meeting->users->add($data['invitee_id'], ['accept_status' => $data['status']]);
        
        // Also check contacts
        $meeting->load_relationship('contacts');
        $meeting->contacts->add($data['invitee_id'], ['accept_status' => $data['status']]);
        
        return $response->json([
            'message' => 'Invitee status updated',
            'meeting' => $this->formatMeetingResponse($meeting, true)
        ]);
    }
    
    /**
     * Get upcoming meetings
     */
    public function upcoming($request, $response)
    {
        $days = $request->getQueryParam('days', 7);
        
        global $db;
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        
        $safeNow = $db->quote($now);
        $safeFuture = $db->quote($future);
        
        $query = "SELECT * FROM meetings 
                 WHERE deleted = 0 
                 AND status = 'Planned'
                 AND date_start >= $safeNow 
                 AND date_start <= $safeFuture
                 ORDER BY date_start ASC";
        
        $result = $db->query($query);
        $meetings = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $meeting = \BeanFactory::getBean('Meetings', $row['id']);
            if ($meeting) {
                $meetings[] = $this->formatMeetingResponse($meeting);
            }
        }
        
        return $response->json($meetings);
    }
    
    /**
     * Get today's meetings
     */
    public function today($request, $response)
    {
        global $db;
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        $safeStart = $db->quote($todayStart);
        $safeEnd = $db->quote($todayEnd);
        
        $query = "SELECT * FROM meetings 
                 WHERE deleted = 0 
                 AND date_start >= $safeStart 
                 AND date_start <= $safeEnd
                 ORDER BY date_start ASC";
        
        $result = $db->query($query);
        $meetings = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $meeting = \BeanFactory::getBean('Meetings', $row['id']);
            if ($meeting) {
                $meetings[] = $this->formatMeetingResponse($meeting);
            }
        }
        
        return $response->json($meetings);
    }
    
    /**
     * Create meeting from template
     */
    public function createFromTemplate($request, $response)
    {
        $templateId = $request->getParam('templateId');
        $data = json_decode($request->getBody(), true);
        
        // Get template meeting
        $template = \BeanFactory::getBean('Meetings', $templateId);
        if (!$template || empty($template->id)) {
            return $this->notFoundResponse($response, 'Meeting template');
        }
        
        // Create new meeting from template
        $meeting = \BeanFactory::newBean('Meetings');
        $meeting->name = $data['name'] ?? $template->name;
        $meeting->description = $template->description;
        $meeting->type = $template->type;
        $meeting->location = $data['location'] ?? $template->location;
        $meeting->duration_hours = $template->duration_hours;
        $meeting->duration_minutes = $template->duration_minutes;
        $meeting->date_start = $data['date_start'] ?? date('Y-m-d H:i:s');
        
        // Calculate end date
        $startTime = strtotime($meeting->date_start);
        $duration = ($meeting->duration_hours * 3600) + ($meeting->duration_minutes * 60);
        $meeting->date_end = date('Y-m-d H:i:s', $startTime + $duration);
        
        // Set other fields
        $meeting->status = 'Planned';
        $meeting->parent_type = $data['parent_type'] ?? $template->parent_type;
        $meeting->parent_id = $data['parent_id'] ?? $template->parent_id;
        $meeting->contact_id = $data['contact_id'] ?? $template->contact_id;
        
        global $current_user;
        $meeting->assigned_user_id = $data['assigned_user_id'] ?? $current_user->id;
        
        $meeting->save();
        
        // Copy invitees from template if requested
        if (!empty($data['copy_invitees'])) {
            // Copy user invitees
            $template->load_relationship('users');
            $users = $template->users->getBeans();
            $meeting->load_relationship('users');
            foreach ($users as $user) {
                $meeting->users->add($user->id, ['accept_status' => 'none']);
            }
            
            // Copy contact invitees
            $template->load_relationship('contacts');
            $contacts = $template->contacts->getBeans();
            $meeting->load_relationship('contacts');
            foreach ($contacts as $contact) {
                $meeting->contacts->add($contact->id, ['accept_status' => 'none']);
            }
        }
        
        return $response->json($this->formatMeetingResponse($meeting), 201);
    }
    
    /**
     * Format meeting response
     */
    protected function formatMeetingResponse($meeting, $includeDetails = false)
    {
        $data = [
            'id' => $meeting->id,
            'name' => $meeting->name,
            'status' => $meeting->status,
            'type' => $meeting->type,
            'location' => $meeting->location,
            'date_start' => $meeting->date_start,
            'date_end' => $meeting->date_end,
            'duration_hours' => (int)$meeting->duration_hours,
            'duration_minutes' => (int)$meeting->duration_minutes,
            'parent_type' => $meeting->parent_type,
            'parent_id' => $meeting->parent_id,
            'contact_id' => $meeting->contact_id,
            'assigned_user_id' => $meeting->assigned_user_id,
            'date_entered' => $meeting->date_entered,
            'date_modified' => $meeting->date_modified,
            'external_url' => $meeting->external_url
        ];
        
        // Determine if remote meeting
        $remoteTypes = ['Zoom', 'Teams', 'WebEx', 'Google Meet'];
        $data['is_remote'] = in_array($meeting->type, $remoteTypes) || !empty($meeting->external_url);
        
        if ($includeDetails) {
            $data['description'] = $meeting->description;
            $data['reminder_time'] = $meeting->reminder_time;
            $data['email_reminder_time'] = $meeting->email_reminder_time;
            $data['password_protected'] = $meeting->password_protected;
            $data['meeting_password'] = $meeting->meeting_password;
            $data['repeat_type'] = $meeting->repeat_type;
            $data['repeat_interval'] = $meeting->repeat_interval;
            $data['repeat_dow'] = $meeting->repeat_dow;
            $data['repeat_until'] = $meeting->repeat_until;
            $data['repeat_count'] = $meeting->repeat_count;
            $data['repeat_parent_id'] = $meeting->repeat_parent_id;
            
            // Load invitees
            $invitees = [];
            
            // Get user invitees
            $meeting->load_relationship('users');
            $users = $meeting->users->getBeans();
            foreach ($users as $user) {
                $relationship = $meeting->users->get_relationship_data($user->id);
                $invitees[] = [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email1,
                    'type' => 'user',
                    'accept_status' => $relationship['accept_status'] ?? 'none',
                    'required' => $relationship['required'] ?? false
                ];
            }
            
            // Get contact invitees
            $meeting->load_relationship('contacts');
            $contacts = $meeting->contacts->getBeans();
            foreach ($contacts as $contact) {
                $relationship = $meeting->contacts->get_relationship_data($contact->id);
                $invitees[] = [
                    'id' => $contact->id,
                    'name' => trim($contact->first_name . ' ' . $contact->last_name),
                    'email' => $contact->email1,
                    'type' => 'contact',
                    'accept_status' => $relationship['accept_status'] ?? 'none',
                    'required' => $relationship['required'] ?? false
                ];
            }
            
            $data['invitees'] = $invitees;
            $data['invitee_count'] = count($invitees);
            
            // Count responses
            $data['accepted_count'] = count(array_filter($invitees, fn($i) => $i['accept_status'] === 'accept'));
            $data['declined_count'] = count(array_filter($invitees, fn($i) => $i['accept_status'] === 'decline'));
            $data['tentative_count'] = count(array_filter($invitees, fn($i) => $i['accept_status'] === 'tentative'));
        }
        
        // Get contact name
        if (!empty($meeting->contact_id)) {
            $contact = \BeanFactory::getBean('Contacts', $meeting->contact_id);
            if ($contact) {
                $data['contact_name'] = trim($contact->first_name . ' ' . $contact->last_name);
            }
        }
        
        // Get parent name
        if (!empty($meeting->parent_type) && !empty($meeting->parent_id)) {
            $parent = \BeanFactory::getBean($meeting->parent_type, $meeting->parent_id);
            if ($parent) {
                $data['parent_name'] = $parent->name;
            }
        }
        
        // Get assigned user name
        if (!empty($meeting->assigned_user_id)) {
            $user = \BeanFactory::getBean('Users', $meeting->assigned_user_id);
            if ($user) {
                $data['assigned_user_name'] = trim($user->first_name . ' ' . $user->last_name);
            }
        }
        
        // Calculate duration string
        $hours = $meeting->duration_hours ?? 0;
        $minutes = $meeting->duration_minutes ?? 0;
        $data['duration'] = sprintf('%dh %dm', $hours, $minutes);
        
        return $data;
    }
}