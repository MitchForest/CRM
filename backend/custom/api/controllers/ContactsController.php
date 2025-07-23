<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class ContactsController extends BaseController {
    
    public function list(Request $request) {
        $bean = \BeanFactory::newBean('Contacts');
        
        // Get filters
        $filters = $request->get('filters', []);
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $request->get('sort', 'last_name');
        $sortOrder = $request->get('order', 'ASC');
        
        // Get pagination
        list($limit, $offset) = $this->getPaginationParams($request);
        
        // Build query
        $query = $bean->create_new_list_query(
            "$sortField $sortOrder",
            $where,
            [],
            [],
            0,
            '',
            true,
            $bean,
            true
        );
        
        // Get total count
        $countResult = $bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = $bean->db->fetchByAssoc($countResult)['total'];
        
        // Add limit and offset
        $query .= " LIMIT $limit OFFSET $offset";
        
        // Execute query
        $result = $bean->db->query($query);
        $contacts = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $contact = \BeanFactory::newBean('Contacts');
            $contact->populateFromRow($row);
            $contacts[] = $this->formatBean($contact);
        }
        
        return Response::success([
            'data' => $contacts,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function get(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        // Get additional data
        $data = $this->formatBean($contact);
        
        // Add calculated fields
        $data['lifetimeValue'] = $this->calculateLifetimeValue($contact);
        $data['lastActivityDate'] = $this->getLastActivityDate($contact);
        
        return Response::success($data);
    }
    
    public function create(Request $request) {
        $contact = \BeanFactory::newBean('Contacts');
        
        // Set fields
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'description'];
        foreach ($fields as $field) {
            if ($request->get($field)) {
                $contact->$field = $request->get($field);
            }
        }
        
        // Validate required fields
        if (empty($contact->last_name)) {
            return Response::error('Last name is required', 400);
        }
        
        // Save
        $contact->save();
        
        return Response::created($this->formatBean($contact));
    }
    
    public function update(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        // Update fields
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'description'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $contact->$field = $request->get($field);
            }
        }
        
        // Save
        $contact->save();
        
        return Response::success($this->formatBean($contact));
    }
    
    public function delete(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        $contact->mark_deleted($id);
        
        return Response::success(['message' => 'Contact deleted successfully']);
    }
    
    public function activities(Request $request) {
        $id = $request->getParam('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return Response::notFound('Contact not found');
        }
        
        $activities = [];
        
        // Get related activities from different modules
        $modules = [
            'Tasks' => ['name', 'status', 'date_due', 'priority'],
            'Emails' => ['name', 'status', 'date_sent'],
            'Calls' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes'],
            'Meetings' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes'],
            'Notes' => ['name', 'description', 'date_entered']
        ];
        
        foreach ($modules as $module => $fields) {
            $bean = \BeanFactory::newBean($module);
            
            // Build where clause with proper escaping
            global $db;
            $safeId = $db->quote($id);
            $safeParentType = $db->quote('Contacts');
            
            $where = "parent_type = $safeParentType AND parent_id = $safeId";
            if ($module === 'Tasks') {
                $where = "contact_id = $safeId";
            }
            
            $query = $bean->create_new_list_query('date_entered DESC', $where);
            $result = $bean->db->query($query);
            
            while ($row = $bean->db->fetchByAssoc($result)) {
                $activity = [
                    'id' => $row['id'],
                    'type' => strtolower($module),
                    'module' => $module,
                    'date' => $row['date_entered'] ?? $row['date_start'] ?? $row['date_due']
                ];
                
                foreach ($fields as $field) {
                    if (isset($row[$field])) {
                        $activity[$field] = $row[$field];
                    }
                }
                
                $activities[] = $activity;
            }
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Apply pagination
        list($limit, $offset) = $this->getPaginationParams($request);
        $total = count($activities);
        $activities = array_slice($activities, $offset, $limit);
        
        return Response::success([
            'data' => $activities,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    private function calculateLifetimeValue($contact) {
        // This would calculate based on opportunities, orders, etc.
        // For now, return a placeholder
        return 0;
    }
    
    private function getLastActivityDate($contact) {
        global $db;
        
        $safeContactId = $db->quote($contact->id);
        $safeParentType = $db->quote('Contacts');
        
        $query = "SELECT MAX(date_entered) as last_date FROM (
            SELECT date_entered FROM tasks WHERE contact_id = $safeContactId AND deleted = 0
            UNION
            SELECT date_entered FROM emails WHERE parent_type = $safeParentType AND parent_id = $safeContactId AND deleted = 0
            UNION
            SELECT date_entered FROM calls WHERE parent_type = $safeParentType AND parent_id = $safeContactId AND deleted = 0
            UNION
            SELECT date_entered FROM meetings WHERE parent_type = $safeParentType AND parent_id = $safeContactId AND deleted = 0
        ) as activities";
        
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        return $row['last_date'] ?? null;
    }
}