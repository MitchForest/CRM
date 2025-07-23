<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ActivitiesController extends BaseController {
    
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $activities = [];
        
        // Define modules and their fields
        $modules = [
            'Tasks' => [
                'fields' => ['name', 'status', 'priority', 'date_due', 'contact_id', 'parent_type', 'parent_id'],
                'date_field' => 'date_due',
                'type' => 'task'
            ],
            'Emails' => [
                'fields' => ['name', 'status', 'date_sent', 'parent_type', 'parent_id', 'from_addr', 'to_addrs'],
                'date_field' => 'date_sent',
                'type' => 'email'
            ],
            'Calls' => [
                'fields' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes', 'parent_type', 'parent_id'],
                'date_field' => 'date_start',
                'type' => 'call'
            ],
            'Meetings' => [
                'fields' => ['name', 'status', 'date_start', 'duration_hours', 'duration_minutes', 'parent_type', 'parent_id', 'location'],
                'date_field' => 'date_start',
                'type' => 'meeting'
            ],
            'Notes' => [
                'fields' => ['name', 'description', 'date_entered', 'parent_type', 'parent_id'],
                'date_field' => 'date_entered',
                'type' => 'note'
            ]
        ];
        
        // Get filters
        $queryParams = $request->getQueryParams();
        $filters = $queryParams['filters'] ?? [];
        $typeFilter = $filters['type'] ?? null;
        $contactFilter = $filters['contact_id'] ?? null;
        $statusFilter = $filters['status'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        
        // Fetch from each module
        foreach ($modules as $module => $config) {
            if ($typeFilter && $config['type'] !== $typeFilter) {
                continue;
            }
            
            $bean = \BeanFactory::newBean($module);
            $where = [];
            
            if ($contactFilter) {
                if ($module === 'Tasks') {
                    $where[] = "contact_id = '$contactFilter'";
                } else {
                    $where[] = "(parent_type = 'Contacts' AND parent_id = '$contactFilter')";
                }
            }
            
            if ($statusFilter && in_array('status', $config['fields'])) {
                $where[] = "status = '$statusFilter'";
            }
            
            if ($dateFrom) {
                $where[] = "{$config['date_field']} >= '$dateFrom'";
            }
            
            if ($dateTo) {
                $where[] = "{$config['date_field']} <= '$dateTo'";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = $bean->create_new_list_query(
                $config['date_field'] . ' DESC',
                $whereClause
            );
            
            // Limit to 100 per module to prevent memory issues
            $result = $bean->db->query($query . " LIMIT 100");
            
            while ($row = $bean->db->fetchByAssoc($result)) {
                $activity = [
                    'id' => $row['id'],
                    'type' => $config['type'],
                    'module' => $module,
                    'date' => $row[$config['date_field']] ?? $row['date_entered'],
                    'timestamp' => strtotime($row[$config['date_field']] ?? $row['date_entered'])
                ];
                
                foreach ($config['fields'] as $field) {
                    if (isset($row[$field])) {
                        $activity[$field] = $row[$field];
                    }
                }
                
                // Add related contact name
                if (!empty($row['contact_id'])) {
                    $contact = \BeanFactory::getBean('Contacts', $row['contact_id']);
                    if ($contact && !empty($contact->id)) {
                        $activity['contact_name'] = $contact->first_name . ' ' . $contact->last_name;
                    }
                } elseif ($row['parent_type'] === 'Contacts' && !empty($row['parent_id'])) {
                    $contact = \BeanFactory::getBean('Contacts', $row['parent_id']);
                    if ($contact && !empty($contact->id)) {
                        $activity['contact_id'] = $contact->id;
                        $activity['contact_name'] = $contact->first_name . ' ' . $contact->last_name;
                    }
                }
                
                $activities[] = $activity;
            }
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Pagination
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        $total = count($activities);
        $activities = array_slice($activities, $offset, $limit);
        
        // Remove timestamp field used for sorting
        foreach ($activities as &$activity) {
            unset($activity['timestamp']);
        }
        
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
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $type = $data['type'] ?? '';
        
        if (!$type) {
            return $this->validationErrorResponse($response, 'Activity type is required', ['type' => 'Required field']);
        }
        
        $moduleMap = [
            'task' => 'Tasks',
            'email' => 'Emails',
            'call' => 'Calls',
            'meeting' => 'Meetings',
            'note' => 'Notes'
        ];
        
        $module = $moduleMap[$type] ?? null;
        
        if (!$module) {
            return $this->validationErrorResponse($response, 'Invalid activity type', ['type' => 'Invalid value']);
        }
        
        $bean = \BeanFactory::newBean($module);
        
        // Set common fields
        $bean->name = $data['subject'] ?? $data['name'] ?? '';
        
        if (!empty($data['contact_id'])) {
            if ($module === 'Tasks') {
                $bean->contact_id = $data['contact_id'];
            } else {
                $bean->parent_type = 'Contacts';
                $bean->parent_id = $data['contact_id'];
            }
        }
        
        // Set parent if provided
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $bean->parent_type = $data['parent_type'];
            $bean->parent_id = $data['parent_id'];
        }
        
        // Set type-specific fields
        switch ($module) {
            case 'Tasks':
                $bean->status = $data['status'] ?? 'Not Started';
                $bean->priority = $data['priority'] ?? 'Medium';
                $bean->date_due = $data['date_due'] ?? '';
                $bean->description = $data['description'] ?? '';
                break;
                
            case 'Emails':
                $bean->status = $data['status'] ?? 'sent';
                $bean->date_sent = $data['date_sent'] ?? date('Y-m-d H:i:s');
                $bean->to_addrs = $data['to_addrs'] ?? '';
                $bean->from_addr = $data['from_addr'] ?? '';
                $bean->description_html = $data['body'] ?? '';
                break;
                
            case 'Calls':
            case 'Meetings':
                $bean->status = $data['status'] ?? 'Planned';
                $bean->date_start = $data['date_start'] ?? '';
                $bean->duration_hours = $data['duration_hours'] ?? 0;
                $bean->duration_minutes = $data['duration_minutes'] ?? 30;
                $bean->description = $data['description'] ?? '';
                if ($module === 'Meetings') {
                    $bean->location = $data['location'] ?? '';
                }
                break;
                
            case 'Notes':
                $bean->description = $data['description'] ?? '';
                break;
        }
        
        $bean->save();
        
        return $response->json([
            'id' => $bean->id,
            'type' => $type,
            'module' => $module
        ], 201);
    }
    
    public function upcoming(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        global $db, $current_user;
        
        $activities = [];
        $today = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+7 days'));
        
        // Get upcoming tasks
        $taskQuery = "SELECT * FROM tasks 
                     WHERE deleted = 0 
                     AND status != 'Completed' 
                     AND assigned_user_id = '{$current_user->id}'
                     AND date_due >= '$today' 
                     AND date_due <= '$endDate'";
        
        $result = $db->query($taskQuery);
        while ($row = $db->fetchByAssoc($result)) {
            $activities[] = [
                'id' => $row['id'],
                'type' => 'task',
                'module' => 'Tasks',
                'name' => $row['name'],
                'date' => $row['date_due'],
                'status' => $row['status'],
                'priority' => $row['priority']
            ];
        }
        
        // Get upcoming meetings
        $meetingQuery = "SELECT * FROM meetings 
                        WHERE deleted = 0 
                        AND status != 'Held' 
                        AND assigned_user_id = '{$current_user->id}'
                        AND date_start >= '$today' 
                        AND date_start <= '$endDate'";
        
        $result = $db->query($meetingQuery);
        while ($row = $db->fetchByAssoc($result)) {
            $activities[] = [
                'id' => $row['id'],
                'type' => 'meeting',
                'module' => 'Meetings',
                'name' => $row['name'],
                'date' => $row['date_start'],
                'status' => $row['status'],
                'location' => $row['location']
            ];
        }
        
        // Get upcoming calls
        $callQuery = "SELECT * FROM calls 
                     WHERE deleted = 0 
                     AND status != 'Held' 
                     AND assigned_user_id = '{$current_user->id}'
                     AND date_start >= '$today' 
                     AND date_start <= '$endDate'";
        
        $result = $db->query($callQuery);
        while ($row = $db->fetchByAssoc($result)) {
            $activities[] = [
                'id' => $row['id'],
                'type' => 'call',
                'module' => 'Calls',
                'name' => $row['name'],
                'date' => $row['date_start'],
                'status' => $row['status']
            ];
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $response->json([
            'data' => $activities,
            'total' => count($activities),
            'date_range' => [
                'from' => $today,
                'to' => $endDate
            ]
        ]);
    }
    
    public function recent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        global $db, $current_user;
        
        $activities = [];
        $queryParams = $request->getQueryParams();
        $limit = min((int)($queryParams['limit'] ?? 50), 100);
        
        // Define queries for recent activities
        $queries = [
            'Tasks' => "SELECT *, 'task' as activity_type FROM tasks WHERE deleted = 0 AND assigned_user_id = '{$current_user->id}' ORDER BY date_modified DESC LIMIT $limit",
            'Emails' => "SELECT *, 'email' as activity_type FROM emails WHERE deleted = 0 AND assigned_user_id = '{$current_user->id}' ORDER BY date_sent DESC LIMIT $limit",
            'Calls' => "SELECT *, 'call' as activity_type FROM calls WHERE deleted = 0 AND assigned_user_id = '{$current_user->id}' ORDER BY date_start DESC LIMIT $limit",
            'Meetings' => "SELECT *, 'meeting' as activity_type FROM meetings WHERE deleted = 0 AND assigned_user_id = '{$current_user->id}' ORDER BY date_start DESC LIMIT $limit",
            'Notes' => "SELECT *, 'note' as activity_type FROM notes WHERE deleted = 0 AND assigned_user_id = '{$current_user->id}' ORDER BY date_entered DESC LIMIT $limit"
        ];
        
        foreach ($queries as $module => $query) {
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $activity = [
                    'id' => $row['id'],
                    'type' => $row['activity_type'],
                    'module' => $module,
                    'name' => $row['name'],
                    'date' => $row['date_modified'] ?? $row['date_entered'],
                    'timestamp' => strtotime($row['date_modified'] ?? $row['date_entered'])
                ];
                
                // Add relevant fields based on type
                if (isset($row['status'])) {
                    $activity['status'] = $row['status'];
                }
                if (isset($row['priority'])) {
                    $activity['priority'] = $row['priority'];
                }
                
                $activities[] = $activity;
            }
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Limit to requested amount
        $activities = array_slice($activities, 0, $limit);
        
        // Remove timestamp
        foreach ($activities as &$activity) {
            unset($activity['timestamp']);
        }
        
        return $response->json([
            'data' => $activities,
            'total' => count($activities)
        ]);
    }
}