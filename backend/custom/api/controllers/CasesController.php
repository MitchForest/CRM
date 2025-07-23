<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CasesController extends BaseController {
    
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $bean = \BeanFactory::newBean('Cases');
        
        // Get filters
        $queryParams = $request->getQueryParams();
        $filters = $queryParams['filters'] ?? [];
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $queryParams['sort'] ?? 'date_entered';
        $sortOrder = $queryParams['order'] ?? 'DESC';
        
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
        $cases = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $case = \BeanFactory::newBean('Cases');
            $case->populateFromRow($row);
            $cases[] = $this->formatBean($case);
        }
        
        return $response->json([
            'data' => $cases,
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
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return $this->notFoundResponse($response, 'Case');
        }
        
        $data = $this->formatBean($case);
        
        // Add contact information
        $case->load_relationship('contacts');
        $contacts = $case->contacts->getBeans();
        $data['contacts'] = [];
        foreach ($contacts as $contact) {
            $data['contacts'][] = [
                'id' => $contact->id,
                'name' => $contact->first_name . ' ' . $contact->last_name,
                'email' => $contact->email1
            ];
        }
        
        // Add case updates/history
        $data['updates'] = $this->getCaseUpdates($case);
        
        return $response->json($data);
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $case = \BeanFactory::newBean('Cases');
        
        // Set fields
        $data = $request->getParsedBody();
        $fields = ['name', 'description', 'status', 'priority', 'type', 'resolution', 'work_log'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $case->$field = $data[$field];
            }
        }
        
        // Validate required fields
        if (empty($case->name)) {
            return $this->validationErrorResponse($response, 'Case subject is required', ['name' => 'Required field']);
        }
        
        // Set defaults
        if (empty($case->status)) {
            $case->status = 'New';
        }
        if (empty($case->priority)) {
            $case->priority = 'P2';
        }
        if (empty($case->state)) {
            $case->state = 'Open';
        }
        
        // Generate case number
        $case->case_number = $this->generateCaseNumber();
        
        // Save
        $case->save();
        
        // Link to contact if provided
        if (!empty($data['contact_id'])) {
            $case->load_relationship('contacts');
            $case->contacts->add($data['contact_id']);
        }
        
        return $response->json($this->formatBean($case), 201);
    }
    
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return $this->notFoundResponse($response, 'Case');
        }
        
        // Track status changes for history
        $oldStatus = $case->status;
        
        // Update fields
        $data = $request->getParsedBody();
        $fields = ['name', 'description', 'status', 'priority', 'type', 'resolution', 'work_log'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $case->$field = $data[$field];
            }
        }
        
        // Update state based on status
        if ($case->status === 'Closed' || $case->status === 'Rejected') {
            $case->state = 'Closed';
        } else {
            $case->state = 'Open';
        }
        
        // Save
        $case->save();
        
        // Log status change if needed
        if ($oldStatus != $case->status && !empty($data['update_text'])) {
            $this->createCaseUpdate($case, $data['update_text'], $oldStatus, $case->status);
        }
        
        return $response->json($this->formatBean($case));
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return $this->notFoundResponse($response, 'Case');
        }
        
        $case->mark_deleted($id);
        
        return $response->json(['message' => 'Case deleted successfully']);
    }
    
    public function addUpdate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return $this->notFoundResponse($response, 'Case');
        }
        
        $data = $request->getParsedBody();
        $updateText = $data['update_text'] ?? '';
        if (empty($updateText)) {
            return $this->validationErrorResponse($response, 'Update text is required', ['update_text' => 'Required field']);
        }
        
        // Create case update
        $update = \BeanFactory::newBean('AOP_Case_Updates');
        $update->name = 'Update from ' . date('Y-m-d H:i:s');
        $update->description = $updateText;
        $update->case_id = $case->id;
        $update->internal = $data['internal'] ?? 0;
        $update->save();
        
        // Update case modified date
        $case->save();
        
        return $response->json([
            'message' => 'Update added successfully',
            'update' => [
                'id' => $update->id,
                'text' => $update->description,
                'date' => $update->date_entered,
                'internal' => $update->internal
            ]
        ]);
    }
    
    private function generateCaseNumber() {
        global $db;
        
        // Get the last case number
        $query = "SELECT MAX(CAST(case_number AS UNSIGNED)) as max_number FROM cases WHERE deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        $nextNumber = 1;
        if ($row && !empty($row['max_number'])) {
            $nextNumber = $row['max_number'] + 1;
        }
        
        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    
    private function getCaseUpdates($case) {
        $updates = [];
        
        // Check if AOP_Case_Updates module exists
        if (class_exists('AOP_Case_Updates')) {
            global $db;
            
            $query = "SELECT * FROM aop_case_updates 
                     WHERE case_id = '{$case->id}' 
                     AND deleted = 0 
                     ORDER BY date_entered DESC";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $updates[] = [
                    'id' => $row['id'],
                    'text' => $row['description'],
                    'date' => $row['date_entered'],
                    'internal' => $row['internal'],
                    'created_by' => $row['created_by']
                ];
            }
        }
        
        return $updates;
    }
    
    private function createCaseUpdate($case, $text, $oldStatus = null, $newStatus = null) {
        if (class_exists('AOP_Case_Updates')) {
            $update = \BeanFactory::newBean('AOP_Case_Updates');
            
            // Build update text
            $updateText = $text;
            if ($oldStatus && $newStatus && $oldStatus != $newStatus) {
                $updateText = "Status changed from $oldStatus to $newStatus\n\n" . $updateText;
            }
            
            $update->name = 'Status Update';
            $update->description = $updateText;
            $update->case_id = $case->id;
            $update->internal = 0;
            $update->save();
        }
    }
    
    protected function getDefaultFields($module) {
        if ($module === 'Cases') {
            return ['id', 'name', 'case_number', 'status', 'priority', 'type', 
                   'state', 'description', 'resolution', 'date_entered', 'date_modified'];
        }
        return parent::getDefaultFields($module);
    }
}