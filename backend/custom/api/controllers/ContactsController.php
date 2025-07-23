<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ContactsController extends BaseController {
    
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $bean = \BeanFactory::newBean('Contacts');
        
        // Get filters
        $queryParams = $request->getQueryParams();
        $filters = $queryParams['filters'] ?? [];
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $queryParams['sort'] ?? 'last_name';
        $sortOrder = $queryParams['order'] ?? 'ASC';
        
        // Get pagination
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        
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
        
        return $response->json([
            'data' => $contacts,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return $this->notFoundResponse($response, 'Contact');
        }
        
        // Get additional data
        $data = $this->formatBean($contact);
        
        // Add calculated fields
        $data['lifetimeValue'] = $this->calculateLifetimeValue($contact);
        $data['lastActivityDate'] = $this->getLastActivityDate($contact);
        
        return $response->json($data);
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $contact = \BeanFactory::newBean('Contacts');
        
        // Set fields
        $data = $request->getParsedBody();
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'description'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $contact->$field = $data[$field];
            }
        }
        
        // Validate required fields
        if (empty($contact->last_name)) {
            return $this->validationErrorResponse($response, 'Last name is required', ['last_name' => 'Required field']);
        }
        
        // Save
        $contact->save();
        
        return $response->json($this->formatBean($contact), 201);
    }
    
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return $this->notFoundResponse($response, 'Contact');
        }
        
        // Update fields
        $data = $request->getParsedBody();
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'description'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $contact->$field = $data[$field];
            }
        }
        
        // Save
        $contact->save();
        
        return $response->json($this->formatBean($contact));
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return $this->notFoundResponse($response, 'Contact');
        }
        
        $contact->mark_deleted($id);
        
        return $response->json(['message' => 'Contact deleted successfully']);
    }
    
    public function activities(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $contact = \BeanFactory::getBean('Contacts', $id);
        
        if (empty($contact->id)) {
            return $this->notFoundResponse($response, 'Contact');
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
        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        $total = count($activities);
        $activities = array_slice($activities, $offset, $limit);
        
        return $response->json([
            'data' => $activities,
            'pagination' => [
                'page' => $page,
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