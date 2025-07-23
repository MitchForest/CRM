<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class TasksController extends BaseController {
    
    public function list(Request $request) {
        $bean = \BeanFactory::newBean('Tasks');
        
        // Get filters
        $filters = $request->get('filters', []);
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $request->get('sort', 'date_due');
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
        $tasks = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $task = \BeanFactory::newBean('Tasks');
            $task->populateFromRow($row);
            $taskData = $this->formatBean($task);
            
            // Add contact name if available
            if (!empty($task->contact_id)) {
                $contact = \BeanFactory::getBean('Contacts', $task->contact_id);
                if ($contact && !empty($contact->id)) {
                    $taskData['contact_name'] = $contact->first_name . ' ' . $contact->last_name;
                }
            }
            
            $tasks[] = $taskData;
        }
        
        return Response::success([
            'data' => $tasks,
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
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return Response::notFound('Task not found');
        }
        
        $data = $this->formatBean($task);
        
        // Add contact information
        if (!empty($task->contact_id)) {
            $contact = \BeanFactory::getBean('Contacts', $task->contact_id);
            if ($contact && !empty($contact->id)) {
                $data['contact'] = [
                    'id' => $contact->id,
                    'name' => $contact->first_name . ' ' . $contact->last_name,
                    'email' => $contact->email1
                ];
            }
        }
        
        // Add parent information
        if (!empty($task->parent_type) && !empty($task->parent_id)) {
            $parent = \BeanFactory::getBean($task->parent_type, $task->parent_id);
            if ($parent && !empty($parent->id)) {
                $data['parent'] = [
                    'id' => $parent->id,
                    'type' => $task->parent_type,
                    'name' => $parent->name ?? ($parent->first_name . ' ' . $parent->last_name)
                ];
            }
        }
        
        return Response::success($data);
    }
    
    public function create(Request $request) {
        $task = \BeanFactory::newBean('Tasks');
        
        // Set fields
        $fields = ['name', 'status', 'priority', 'date_due', 'date_due_flag', 'time_due', 
                  'date_start', 'date_start_flag', 'time_start', 'description', 
                  'contact_id', 'parent_type', 'parent_id'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $task->$field = $request->get($field);
            }
        }
        
        // Validate required fields
        if (empty($task->name)) {
            return Response::error('Task name is required', 400);
        }
        
        // Set defaults
        if (empty($task->status)) {
            $task->status = 'Not Started';
        }
        if (empty($task->priority)) {
            $task->priority = 'Medium';
        }
        
        // Save
        $task->save();
        
        return Response::created($this->formatBean($task));
    }
    
    public function update(Request $request) {
        $id = $request->getParam('id');
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return Response::notFound('Task not found');
        }
        
        // Update fields
        $fields = ['name', 'status', 'priority', 'date_due', 'date_due_flag', 'time_due', 
                  'date_start', 'date_start_flag', 'time_start', 'description', 
                  'contact_id', 'parent_type', 'parent_id'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $task->$field = $request->get($field);
            }
        }
        
        // Save
        $task->save();
        
        return Response::success($this->formatBean($task));
    }
    
    public function delete(Request $request) {
        $id = $request->getParam('id');
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return Response::notFound('Task not found');
        }
        
        $task->mark_deleted($id);
        
        return Response::success(['message' => 'Task deleted successfully']);
    }
    
    public function complete(Request $request) {
        $id = $request->getParam('id');
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return Response::notFound('Task not found');
        }
        
        // Mark as completed
        $task->status = 'Completed';
        $task->date_finished = date('Y-m-d H:i:s');
        $task->save();
        
        return Response::success([
            'message' => 'Task marked as completed',
            'task' => $this->formatBean($task)
        ]);
    }
    
    public function upcoming(Request $request) {
        global $db, $current_user;
        
        // Get tasks due in the next 7 days
        $today = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));
        
        $query = "SELECT * FROM tasks 
                 WHERE deleted = 0 
                 AND status != 'Completed' 
                 AND assigned_user_id = '{$current_user->id}'
                 AND date_due >= '$today' 
                 AND date_due <= '$nextWeek'
                 ORDER BY date_due ASC, priority DESC";
        
        $result = $db->query($query);
        $tasks = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $task = \BeanFactory::newBean('Tasks');
            $task->populateFromRow($row);
            $taskData = $this->formatBean($task);
            
            // Add days until due
            $dueDate = strtotime($task->date_due);
            $now = strtotime($today);
            $taskData['days_until_due'] = floor(($dueDate - $now) / (60 * 60 * 24));
            
            // Add contact name
            if (!empty($task->contact_id)) {
                $contact = \BeanFactory::getBean('Contacts', $task->contact_id);
                if ($contact && !empty($contact->id)) {
                    $taskData['contact_name'] = $contact->first_name . ' ' . $contact->last_name;
                }
            }
            
            $tasks[] = $taskData;
        }
        
        return Response::success([
            'data' => $tasks,
            'total' => count($tasks)
        ]);
    }
    
    public function overdue(Request $request) {
        global $db, $current_user;
        
        // Get overdue tasks
        $today = date('Y-m-d');
        
        $query = "SELECT * FROM tasks 
                 WHERE deleted = 0 
                 AND status != 'Completed' 
                 AND assigned_user_id = '{$current_user->id}'
                 AND date_due < '$today'
                 ORDER BY date_due ASC, priority DESC";
        
        $result = $db->query($query);
        $tasks = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $task = \BeanFactory::newBean('Tasks');
            $task->populateFromRow($row);
            $taskData = $this->formatBean($task);
            
            // Add days overdue
            $dueDate = strtotime($task->date_due);
            $now = strtotime($today);
            $taskData['days_overdue'] = floor(($now - $dueDate) / (60 * 60 * 24));
            
            // Add contact name
            if (!empty($task->contact_id)) {
                $contact = \BeanFactory::getBean('Contacts', $task->contact_id);
                if ($contact && !empty($contact->id)) {
                    $taskData['contact_name'] = $contact->first_name . ' ' . $contact->last_name;
                }
            }
            
            $tasks[] = $taskData;
        }
        
        return Response::success([
            'data' => $tasks,
            'total' => count($tasks)
        ]);
    }
}