<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class CasesController extends BaseController {
    
    public function list(Request $request) {
        $bean = \BeanFactory::newBean('Cases');
        
        // Get filters
        $filters = $request->get('filters', []);
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $request->get('sort', 'date_entered');
        $sortOrder = $request->get('order', 'DESC');
        
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
        $cases = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $case = \BeanFactory::newBean('Cases');
            $case->populateFromRow($row);
            $cases[] = $this->formatBean($case);
        }
        
        return Response::success([
            'data' => $cases,
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
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return Response::notFound('Case not found');
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
        
        return Response::success($data);
    }
    
    public function create(Request $request) {
        $case = \BeanFactory::newBean('Cases');
        
        // Set fields
        $fields = ['name', 'description', 'status', 'priority', 'type', 'resolution', 'work_log'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $case->$field = $request->get($field);
            }
        }
        
        // Validate required fields
        if (empty($case->name)) {
            return Response::error('Case subject is required', 400);
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
        if ($request->get('contact_id')) {
            $case->load_relationship('contacts');
            $case->contacts->add($request->get('contact_id'));
        }
        
        return Response::created($this->formatBean($case));
    }
    
    public function update(Request $request) {
        $id = $request->getParam('id');
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return Response::notFound('Case not found');
        }
        
        // Track status changes for history
        $oldStatus = $case->status;
        
        // Update fields
        $fields = ['name', 'description', 'status', 'priority', 'type', 'resolution', 'work_log'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $case->$field = $request->get($field);
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
        if ($oldStatus != $case->status && $request->get('update_text')) {
            $this->createCaseUpdate($case, $request->get('update_text'), $oldStatus, $case->status);
        }
        
        return Response::success($this->formatBean($case));
    }
    
    public function delete(Request $request) {
        $id = $request->getParam('id');
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return Response::notFound('Case not found');
        }
        
        $case->mark_deleted($id);
        
        return Response::success(['message' => 'Case deleted successfully']);
    }
    
    public function addUpdate(Request $request) {
        $id = $request->getParam('id');
        $case = \BeanFactory::getBean('Cases', $id);
        
        if (empty($case->id)) {
            return Response::notFound('Case not found');
        }
        
        $updateText = $request->get('update_text');
        if (empty($updateText)) {
            return Response::error('Update text is required', 400);
        }
        
        // Create case update
        $update = \BeanFactory::newBean('AOP_Case_Updates');
        $update->name = 'Update from ' . date('Y-m-d H:i:s');
        $update->description = $updateText;
        $update->case_id = $case->id;
        $update->internal = $request->get('internal', 0);
        $update->save();
        
        // Update case modified date
        $case->save();
        
        return Response::success([
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