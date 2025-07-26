<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class CasesController extends BaseController {
    
    public function index(Request $request) {
        try {
            // Get query parameters
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $priority = $_GET['priority'] ?? '';
            $type = $_GET['type'] ?? '';
            $assignedUserId = $_GET['assigned_user_id'] ?? '';
            $sortBy = $_GET['sort_by'] ?? 'date_entered';
            $sortOrder = strtoupper($_GET['sort_order'] ?? 'DESC');
            
            // Build query
            $bean = \BeanFactory::newBean('Cases');
            $query = new \SugarQuery();
            $query->from($bean);
            $query->select(['*']);
            
            // Add filters
            $query->where()->equals('deleted', 0);
            
            if (!empty($search)) {
                $query->where()->queryOr()
                    ->contains('name', $search)
                    ->contains('case_number', $search)
                    ->contains('description', $search);
            }
            
            if (!empty($status)) {
                $query->where()->equals('status', $status);
            }
            
            if (!empty($priority)) {
                $query->where()->equals('priority', $priority);
            }
            
            if (!empty($type)) {
                $query->where()->equals('type', $type);
            }
            
            if (!empty($assignedUserId)) {
                $query->where()->equals('assigned_user_id', $assignedUserId);
            }
            
            // Add sorting
            $query->orderBy($sortBy, $sortOrder);
            
            // Get total count
            $countQuery = clone $query;
            $totalCount = $countQuery->getCountQuery()->execute();
            
            // Add pagination
            $offset = ($page - 1) * $limit;
            $query->limit($limit)->offset($offset);
            
            // Execute query
            $results = $query->execute();
            
            // Format cases
            $cases = [];
            foreach ($results as $row) {
                $case = \BeanFactory::getBean('Cases', $row['id']);
                
                // Get contact info
                $case->load_relationship('contacts');
                $contactIds = $case->contacts->get();
                $contactName = '';
                if (!empty($contactIds[0])) {
                    $contact = \BeanFactory::getBean('Contacts', $contactIds[0]);
                    $contactName = $contact->full_name;
                }
                
                $cases[] = [
                    'id' => $case->id,
                    'caseNumber' => $case->case_number,
                    'name' => $case->name,
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'type' => $case->type,
                    'description' => $case->description,
                    'resolution' => $case->resolution,
                    'contactId' => $contactIds[0] ?? null,
                    'contactName' => $contactName,
                    'assignedUserId' => $case->assigned_user_id,
                    'assignedUserName' => $case->assigned_user_name,
                    'dateEntered' => $case->date_entered,
                    'dateModified' => $case->date_modified,
                    'createdBy' => $case->created_by,
                    'modifiedUserId' => $case->modified_user_id
                ];
            }
            
            return Response::json([
                'data' => $cases,
                'pagination' => [
                    'page' => $page,
                    'pageSize' => $limit,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalCount' => (int)$totalCount,
                    'hasNext' => $page < ceil($totalCount / $limit),
                    'hasPrevious' => $page > 1
                ]
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to fetch cases: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show(Request $request, $id) {
        try {
            $case = \BeanFactory::getBean('Cases', $id);
            
            if (empty($case->id)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Case not found'
                ], 404);
            }
            
            // Load relationships
            $case->load_relationship('contacts');
            $contactIds = $case->contacts->get();
            $contacts = [];
            
            foreach ($contactIds as $contactId) {
                $contact = \BeanFactory::getBean('Contacts', $contactId);
                $contacts[] = [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'email' => $contact->email1,
                    'phone' => $contact->phone_work
                ];
            }
            
            // Get activities
            $activities = $this->getCaseActivities($id);
            
            return Response::json([
                'data' => [
                    'id' => $case->id,
                    'caseNumber' => $case->case_number,
                    'name' => $case->name,
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'type' => $case->type,
                    'description' => $case->description,
                    'resolution' => $case->resolution,
                    'assignedUserId' => $case->assigned_user_id,
                    'assignedUserName' => $case->assigned_user_name,
                    'dateEntered' => $case->date_entered,
                    'dateModified' => $case->date_modified,
                    'createdBy' => $case->created_by,
                    'modifiedUserId' => $case->modified_user_id,
                    'contacts' => $contacts,
                    'activities' => $activities
                ]
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to fetch case: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function create(Request $request) {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            if (empty($data['name'])) {
                return Response::json([
                    'success' => false,
                    'error' => 'Name is required'
                ], 400);
            }
            
            $case = \BeanFactory::newBean('Cases');
            
            // Generate case number
            $case->case_number = $this->generateCaseNumber();
            
            // Set fields
            $case->name = $data['name'];
            $case->status = $data['status'] ?? 'Open';
            $case->priority = $data['priority'] ?? 'Medium';
            $case->type = $data['type'] ?? 'Technical';
            $case->description = $data['description'] ?? '';
            $case->resolution = $data['resolution'] ?? '';
            $case->assigned_user_id = $data['assignedUserId'] ?? $this->getCurrentUserId();
            
            $case->save();
            
            // Add contact if provided
            if (!empty($data['contactId'])) {
                $case->load_relationship('contacts');
                $case->contacts->add($data['contactId']);
            }
            
            return Response::json([
                'data' => [
                    'id' => $case->id,
                    'caseNumber' => $case->case_number
                ],
                'message' => 'Case created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to create case: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id) {
        try {
            $data = $this->getRequestData();
            
            $case = \BeanFactory::getBean('Cases', $id);
            
            if (empty($case->id)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Case not found'
                ], 404);
            }
            
            // Update fields
            if (isset($data['name'])) $case->name = $data['name'];
            if (isset($data['status'])) $case->status = $data['status'];
            if (isset($data['priority'])) $case->priority = $data['priority'];
            if (isset($data['type'])) $case->type = $data['type'];
            if (isset($data['description'])) $case->description = $data['description'];
            if (isset($data['resolution'])) $case->resolution = $data['resolution'];
            if (isset($data['assignedUserId'])) $case->assigned_user_id = $data['assignedUserId'];
            
            $case->save();
            
            // Update contact if provided
            if (isset($data['contactId'])) {
                $case->load_relationship('contacts');
                // Remove all existing contacts
                $case->contacts->removeAll();
                // Add new contact
                if (!empty($data['contactId'])) {
                    $case->contacts->add($data['contactId']);
                }
            }
            
            return Response::json([
                'data' => [
                    'id' => $case->id
                ],
                'message' => 'Case updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to update case: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function delete(Request $request, $id) {
        try {
            $case = \BeanFactory::getBean('Cases', $id);
            
            if (empty($case->id)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Case not found'
                ], 404);
            }
            
            $case->mark_deleted($id);
            
            return Response::json([
                'message' => 'Case deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to delete case: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function generateCaseNumber() {
        // Get the last case number
        $query = new \SugarQuery();
        $query->from(\BeanFactory::newBean('Cases'));
        $query->select(['case_number']);
        $query->orderBy('case_number', 'DESC');
        $query->limit(1);
        
        $result = $query->execute();
        
        if (!empty($result[0]['case_number'])) {
            // Extract number from CASE-XXX format
            $lastNumber = (int)str_replace('CASE-', '', $result[0]['case_number']);
            return sprintf('CASE-%03d', $lastNumber + 1);
        }
        
        return 'CASE-001';
    }
    
    private function getCaseActivities($caseId) {
        $activities = [];
        
        // Get related calls
        $case = \BeanFactory::getBean('Cases', $caseId);
        $case->load_relationship('calls');
        $callIds = $case->calls->get();
        
        foreach ($callIds as $callId) {
            $call = \BeanFactory::getBean('Calls', $callId);
            $activities[] = [
                'type' => 'call',
                'id' => $call->id,
                'name' => $call->name,
                'status' => $call->status,
                'date' => $call->date_start,
                'assignedTo' => $call->assigned_user_name
            ];
        }
        
        // Get related meetings
        $case->load_relationship('meetings');
        $meetingIds = $case->meetings->get();
        
        foreach ($meetingIds as $meetingId) {
            $meeting = \BeanFactory::getBean('Meetings', $meetingId);
            $activities[] = [
                'type' => 'meeting',
                'id' => $meeting->id,
                'name' => $meeting->name,
                'status' => $meeting->status,
                'date' => $meeting->date_start,
                'assignedTo' => $meeting->assigned_user_name
            ];
        }
        
        // Get related notes
        $case->load_relationship('notes');
        $noteIds = $case->notes->get();
        
        foreach ($noteIds as $noteId) {
            $note = \BeanFactory::getBean('Notes', $noteId);
            $activities[] = [
                'type' => 'note',
                'id' => $note->id,
                'name' => $note->name,
                'date' => $note->date_entered,
                'assignedTo' => $note->assigned_user_name
            ];
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $activities;
    }
}