<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TasksController extends BaseController {
    
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $bean = \BeanFactory::newBean('Tasks');
        
        // Get filters
        $queryParams = $request->getQueryParams();
        $filters = $queryParams['filters'] ?? [];
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $queryParams['sort'] ?? 'date_due';
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
        
        return $response->json([
            'data' => $tasks,
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
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return $this->notFoundResponse($response, 'Task');
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
        
        return $response->json($data);
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $task = \BeanFactory::newBean('Tasks');
        
        // Set fields
        $data = $request->getParsedBody();
        $fields = ['name', 'status', 'priority', 'date_due', 'date_due_flag', 'time_due', 
                  'date_start', 'date_start_flag', 'time_start', 'description', 
                  'contact_id', 'parent_type', 'parent_id'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $task->$field = $data[$field];
            }
        }
        
        // Validate required fields
        if (empty($task->name)) {
            return $this->validationErrorResponse($response, 'Task name is required', ['name' => 'Required field']);
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
        
        return $response->json($this->formatBean($task), 201);
    }
    
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return $this->notFoundResponse($response, 'Task');
        }
        
        // Update fields
        $data = $request->getParsedBody();
        $fields = ['name', 'status', 'priority', 'date_due', 'date_due_flag', 'time_due', 
                  'date_start', 'date_start_flag', 'time_start', 'description', 
                  'contact_id', 'parent_type', 'parent_id'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $task->$field = $data[$field];
            }
        }
        
        // Save
        $task->save();
        
        return $response->json($this->formatBean($task));
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return $this->notFoundResponse($response, 'Task');
        }
        
        $task->mark_deleted($id);
        
        return $response->json(['message' => 'Task deleted successfully']);
    }
    
    public function complete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $task = \BeanFactory::getBean('Tasks', $id);
        
        if (empty($task->id)) {
            return $this->notFoundResponse($response, 'Task');
        }
        
        // Mark as completed
        $task->status = 'Completed';
        $task->date_finished = date('Y-m-d H:i:s');
        $task->save();
        
        return $response->json([
            'message' => 'Task marked as completed',
            'task' => $this->formatBean($task)
        ]);
    }
    
    public function upcoming(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
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
        
        return $response->json([
            'data' => $tasks,
            'total' => count($tasks)
        ]);
    }
    
    public function overdue(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
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
        
        return $response->json([
            'data' => $tasks,
            'total' => count($tasks)
        ]);
    }
}