<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class ActivitiesController extends BaseController {
    
    public function index(Request $request) {
        try {
            // Get query parameters
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $type = $_GET['type'] ?? ''; // call, meeting, task, note, email
            $parentType = $_GET['parent_type'] ?? '';
            $parentId = $_GET['parent_id'] ?? '';
            $assignedUserId = $_GET['assigned_user_id'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            
            $activities = [];
            $totalCount = 0;
            
            // Get activities based on type
            if (empty($type) || $type === 'all') {
                // Get all activity types
                $activities = array_merge(
                    $this->getCalls($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo),
                    $this->getMeetings($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo),
                    $this->getTasks($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo),
                    $this->getNotes($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo),
                    $this->getEmails($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo)
                );
            } else {
                switch ($type) {
                    case 'call':
                        $activities = $this->getCalls($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo);
                        break;
                    case 'meeting':
                        $activities = $this->getMeetings($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo);
                        break;
                    case 'task':
                        $activities = $this->getTasks($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo);
                        break;
                    case 'note':
                        $activities = $this->getNotes($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo);
                        break;
                    case 'email':
                        $activities = $this->getEmails($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo);
                        break;
                }
            }
            
            // Sort by date
            usort($activities, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            $totalCount = count($activities);
            
            // Apply pagination
            $offset = ($page - 1) * $limit;
            $activities = array_slice($activities, $offset, $limit);
            
            return Response::json([
                'data' => $activities,
                'pagination' => [
                    'page' => $page,
                    'pageSize' => $limit,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalCount' => $totalCount,
                    'hasNext' => $page < ceil($totalCount / $limit),
                    'hasPrevious' => $page > 1
                ]
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to fetch activities: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show(Request $request, $id) {
        try {
            // Try to find the activity in different modules
            $modules = ['Calls', 'Meetings', 'Tasks', 'Notes', 'Emails'];
            
            foreach ($modules as $module) {
                $bean = \BeanFactory::getBean($module, $id);
                if (!empty($bean->id)) {
                    return Response::json([
                        'data' => $this->formatActivity($bean, strtolower($module))
                    ]);
                }
            }
            
            return Response::json([
                'success' => false,
                'error' => 'Activity not found'
            ], 404);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to fetch activity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function create(Request $request) {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            if (empty($data['type'])) {
                return Response::json([
                    'success' => false,
                    'error' => 'Activity type is required'
                ], 400);
            }
            
            if (empty($data['name'])) {
                return Response::json([
                    'success' => false,
                    'error' => 'Name is required'
                ], 400);
            }
            
            $bean = null;
            
            switch ($data['type']) {
                case 'call':
                    $bean = \BeanFactory::newBean('Calls');
                    $bean->name = $data['name'];
                    $bean->status = $data['status'] ?? 'Planned';
                    $bean->direction = $data['direction'] ?? 'Outbound';
                    $bean->duration_hours = $data['durationHours'] ?? 0;
                    $bean->duration_minutes = $data['durationMinutes'] ?? 15;
                    $bean->date_start = $data['dateStart'] ?? date('Y-m-d H:i:s');
                    break;
                    
                case 'meeting':
                    $bean = \BeanFactory::newBean('Meetings');
                    $bean->name = $data['name'];
                    $bean->status = $data['status'] ?? 'Planned';
                    $bean->location = $data['location'] ?? '';
                    $bean->duration_hours = $data['durationHours'] ?? 1;
                    $bean->duration_minutes = $data['durationMinutes'] ?? 0;
                    $bean->date_start = $data['dateStart'] ?? date('Y-m-d H:i:s');
                    break;
                    
                case 'task':
                    $bean = \BeanFactory::newBean('Tasks');
                    $bean->name = $data['name'];
                    $bean->status = $data['status'] ?? 'Not Started';
                    $bean->priority = $data['priority'] ?? 'Medium';
                    $bean->date_due = $data['dateDue'] ?? date('Y-m-d');
                    break;
                    
                case 'note':
                    $bean = \BeanFactory::newBean('Notes');
                    $bean->name = $data['name'];
                    $bean->description = $data['description'] ?? '';
                    break;
                    
                default:
                    return Response::json([
                        'success' => false,
                        'error' => 'Invalid activity type'
                    ], 400);
            }
            
            // Set common fields
            $bean->description = $data['description'] ?? '';
            $bean->assigned_user_id = $data['assignedUserId'] ?? $this->getCurrentUserId();
            
            // Set parent if provided
            if (!empty($data['parentType']) && !empty($data['parentId'])) {
                $bean->parent_type = $data['parentType'];
                $bean->parent_id = $data['parentId'];
            }
            
            $bean->save();
            
            // Add related records if provided
            if (!empty($data['contactIds'])) {
                $bean->load_relationship('contacts');
                foreach ($data['contactIds'] as $contactId) {
                    $bean->contacts->add($contactId);
                }
            }
            
            return Response::json([
                'data' => [
                    'id' => $bean->id
                ],
                'message' => 'Activity created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to create activity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id) {
        try {
            $data = $this->getRequestData();
            
            // Find the activity
            $modules = ['Calls', 'Meetings', 'Tasks', 'Notes'];
            $bean = null;
            
            foreach ($modules as $module) {
                $tempBean = \BeanFactory::getBean($module, $id);
                if (!empty($tempBean->id)) {
                    $bean = $tempBean;
                    break;
                }
            }
            
            if (empty($bean)) {
                return Response::json([
                'success' => false,
                'error' => 'Activity not found'
            ], 404);
            }
            
            // Update common fields
            if (isset($data['name'])) $bean->name = $data['name'];
            if (isset($data['description'])) $bean->description = $data['description'];
            if (isset($data['assignedUserId'])) $bean->assigned_user_id = $data['assignedUserId'];
            
            // Update type-specific fields
            $module = $bean->module_name;
            
            switch ($module) {
                case 'Calls':
                    if (isset($data['status'])) $bean->status = $data['status'];
                    if (isset($data['direction'])) $bean->direction = $data['direction'];
                    if (isset($data['durationHours'])) $bean->duration_hours = $data['durationHours'];
                    if (isset($data['durationMinutes'])) $bean->duration_minutes = $data['durationMinutes'];
                    if (isset($data['dateStart'])) $bean->date_start = $data['dateStart'];
                    break;
                    
                case 'Meetings':
                    if (isset($data['status'])) $bean->status = $data['status'];
                    if (isset($data['location'])) $bean->location = $data['location'];
                    if (isset($data['durationHours'])) $bean->duration_hours = $data['durationHours'];
                    if (isset($data['durationMinutes'])) $bean->duration_minutes = $data['durationMinutes'];
                    if (isset($data['dateStart'])) $bean->date_start = $data['dateStart'];
                    break;
                    
                case 'Tasks':
                    if (isset($data['status'])) $bean->status = $data['status'];
                    if (isset($data['priority'])) $bean->priority = $data['priority'];
                    if (isset($data['dateDue'])) $bean->date_due = $data['dateDue'];
                    break;
            }
            
            $bean->save();
            
            return Response::json([
                'data' => [
                    'id' => $bean->id
                ],
                'message' => 'Activity updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to update activity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function delete(Request $request, $id) {
        try {
            // Find the activity
            $modules = ['Calls', 'Meetings', 'Tasks', 'Notes'];
            
            foreach ($modules as $module) {
                $bean = \BeanFactory::getBean($module, $id);
                if (!empty($bean->id)) {
                    $bean->mark_deleted($id);
                    return Response::json([
                        'message' => 'Activity deleted successfully'
                    ]);
                }
            }
            
            return Response::json([
                'success' => false,
                'error' => 'Activity not found'
            ], 404);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Failed to delete activity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function getCalls($parentType = '', $parentId = '', $assignedUserId = '', $dateFrom = '', $dateTo = '') {
        $query = new \SugarQuery();
        $query->from(\BeanFactory::newBean('Calls'));
        $query->select(['*']);
        
        $query->where()->equals('deleted', 0);
        
        if (!empty($parentType) && !empty($parentId)) {
            $query->where()->equals('parent_type', $parentType)->equals('parent_id', $parentId);
        }
        
        if (!empty($assignedUserId)) {
            $query->where()->equals('assigned_user_id', $assignedUserId);
        }
        
        if (!empty($dateFrom)) {
            $query->where()->gte('date_start', $dateFrom);
        }
        
        if (!empty($dateTo)) {
            $query->where()->lte('date_start', $dateTo);
        }
        
        $results = $query->execute();
        $activities = [];
        
        foreach ($results as $row) {
            $bean = \BeanFactory::getBean('Calls', $row['id']);
            $activities[] = $this->formatActivity($bean, 'call');
        }
        
        return $activities;
    }
    
    private function getMeetings($parentType = '', $parentId = '', $assignedUserId = '', $dateFrom = '', $dateTo = '') {
        $query = new \SugarQuery();
        $query->from(\BeanFactory::newBean('Meetings'));
        $query->select(['*']);
        
        $query->where()->equals('deleted', 0);
        
        if (!empty($parentType) && !empty($parentId)) {
            $query->where()->equals('parent_type', $parentType)->equals('parent_id', $parentId);
        }
        
        if (!empty($assignedUserId)) {
            $query->where()->equals('assigned_user_id', $assignedUserId);
        }
        
        if (!empty($dateFrom)) {
            $query->where()->gte('date_start', $dateFrom);
        }
        
        if (!empty($dateTo)) {
            $query->where()->lte('date_start', $dateTo);
        }
        
        $results = $query->execute();
        $activities = [];
        
        foreach ($results as $row) {
            $bean = \BeanFactory::getBean('Meetings', $row['id']);
            $activities[] = $this->formatActivity($bean, 'meeting');
        }
        
        return $activities;
    }
    
    private function getTasks($parentType = '', $parentId = '', $assignedUserId = '', $dateFrom = '', $dateTo = '') {
        $query = new \SugarQuery();
        $query->from(\BeanFactory::newBean('Tasks'));
        $query->select(['*']);
        
        $query->where()->equals('deleted', 0);
        
        if (!empty($parentType) && !empty($parentId)) {
            $query->where()->equals('parent_type', $parentType)->equals('parent_id', $parentId);
        }
        
        if (!empty($assignedUserId)) {
            $query->where()->equals('assigned_user_id', $assignedUserId);
        }
        
        if (!empty($dateFrom)) {
            $query->where()->gte('date_due', $dateFrom);
        }
        
        if (!empty($dateTo)) {
            $query->where()->lte('date_due', $dateTo);
        }
        
        $results = $query->execute();
        $activities = [];
        
        foreach ($results as $row) {
            $bean = \BeanFactory::getBean('Tasks', $row['id']);
            $activities[] = $this->formatActivity($bean, 'task');
        }
        
        return $activities;
    }
    
    private function getNotes($parentType = '', $parentId = '', $assignedUserId = '', $dateFrom = '', $dateTo = '') {
        $query = new \SugarQuery();
        $query->from(\BeanFactory::newBean('Notes'));
        $query->select(['*']);
        
        $query->where()->equals('deleted', 0);
        
        if (!empty($parentType) && !empty($parentId)) {
            $query->where()->equals('parent_type', $parentType)->equals('parent_id', $parentId);
        }
        
        if (!empty($assignedUserId)) {
            $query->where()->equals('assigned_user_id', $assignedUserId);
        }
        
        if (!empty($dateFrom)) {
            $query->where()->gte('date_entered', $dateFrom);
        }
        
        if (!empty($dateTo)) {
            $query->where()->lte('date_entered', $dateTo);
        }
        
        $results = $query->execute();
        $activities = [];
        
        foreach ($results as $row) {
            $bean = \BeanFactory::getBean('Notes', $row['id']);
            $activities[] = $this->formatActivity($bean, 'note');
        }
        
        return $activities;
    }
    
    private function getEmails($parentType = '', $parentId = '', $assignedUserId = '', $dateFrom = '', $dateTo = '') {
        // Placeholder for email activities
        return [];
    }
    
    private function formatActivity($bean, $type) {
        $data = [
            'id' => $bean->id,
            'type' => $type,
            'name' => $bean->name,
            'description' => $bean->description ?? '',
            'assignedUserId' => $bean->assigned_user_id,
            'assignedUserName' => $bean->assigned_user_name,
            'parentType' => $bean->parent_type ?? '',
            'parentId' => $bean->parent_id ?? '',
            'dateEntered' => $bean->date_entered,
            'dateModified' => $bean->date_modified
        ];
        
        // Add type-specific fields
        switch ($type) {
            case 'call':
                $data['status'] = $bean->status;
                $data['direction'] = $bean->direction;
                $data['duration'] = ($bean->duration_hours * 60) + $bean->duration_minutes;
                $data['date'] = $bean->date_start;
                break;
                
            case 'meeting':
                $data['status'] = $bean->status;
                $data['location'] = $bean->location;
                $data['duration'] = ($bean->duration_hours * 60) + $bean->duration_minutes;
                $data['date'] = $bean->date_start;
                break;
                
            case 'task':
                $data['status'] = $bean->status;
                $data['priority'] = $bean->priority;
                $data['date'] = $bean->date_due;
                break;
                
            case 'note':
                $data['date'] = $bean->date_entered;
                break;
                
            case 'email':
                $data['date'] = $bean->date_sent ?? $bean->date_entered;
                break;
        }
        
        return $data;
    }
}