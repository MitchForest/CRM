<?php
namespace Api\Controllers;

use Api\Controllers\BaseController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Calls Controller
 * 
 * Handles call-related API endpoints
 */
class CallsController extends BaseController
{
    protected $moduleName = 'Calls';
    
    /**
     * List calls with pagination and filtering
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filters = $request->getQueryParams();
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $pageSize = isset($filters['pageSize']) ? (int)$filters['pageSize'] : 20;
        $orderBy = isset($filters['orderBy']) ? $filters['orderBy'] : 'date_start DESC';
        
        // Build query
        $query = "SELECT * FROM calls WHERE deleted = 0";
        $countQuery = "SELECT COUNT(*) as total FROM calls WHERE deleted = 0";
        $params = [];
        
        // Add filters
        // Remove pagination params from filters before building where clause
        $filterParams = array_diff_key($filters, array_flip(['page', 'pageSize', 'orderBy']));
        $where = $this->buildWhereClause($filterParams);
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
        
        $calls = [];
        while ($row = $db->fetchByAssoc($result)) {
            $call = \BeanFactory::getBean('Calls', $row['id']);
            if ($call) {
                $callData = $this->formatCallResponse($call);
                $calls[] = $callData;
            }
        }
        
        return $response->json([
            'data' => $calls,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total,
                'totalPages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    /**
     * Get single call
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $request->getAttribute('id');
        
        $call = \BeanFactory::getBean('Calls', $id);
        if (!$call || empty($call->id)) {
            return $this->notFoundResponse($response, 'Call');
        }
        
        return $response->json($this->formatCallResponse($call, true));
    }
    
    /**
     * Create new call
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        $call = \BeanFactory::newBean('Calls');
        
        // Set basic fields
        $call->name = $data['name'] ?? '';
        $call->description = $data['description'] ?? '';
        $call->status = $data['status'] ?? 'Planned';
        $call->direction = $data['direction'] ?? 'Outbound';
        $call->date_start = $data['date_start'] ?? date('Y-m-d H:i:s');
        $call->duration_hours = $data['duration_hours'] ?? 0;
        $call->duration_minutes = $data['duration_minutes'] ?? 15;
        
        // Calculate end date
        if (!empty($data['date_start'])) {
            $startTime = strtotime($data['date_start']);
            $duration = ($call->duration_hours * 3600) + ($call->duration_minutes * 60);
            $call->date_end = date('Y-m-d H:i:s', $startTime + $duration);
        }
        
        // Set call-specific fields
        $call->call_purpose = $data['call_purpose'] ?? '';
        $call->call_result = $data['call_result'] ?? '';
        $call->phone_number = $data['phone_number'] ?? '';
        
        // Set parent relationship
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $call->parent_type = $data['parent_type'];
            $call->parent_id = $data['parent_id'];
        }
        
        // Set contact
        if (!empty($data['contact_id'])) {
            $call->contact_id = $data['contact_id'];
        }
        
        // Set reminder
        if (!empty($data['reminder_time'])) {
            $call->reminder_time = $data['reminder_time'];
        }
        
        // Set assigned user
        if (!empty($data['assigned_user_id'])) {
            $call->assigned_user_id = $data['assigned_user_id'];
        } else {
            global $current_user;
            $call->assigned_user_id = $current_user->id;
        }
        
        $call->save();
        
        // Add invitees
        if (!empty($data['invitee_ids'])) {
            $call->load_relationship('users');
            foreach ($data['invitee_ids'] as $userId) {
                $call->users->add($userId);
            }
        }
        
        return $response->json($this->formatCallResponse($call), 201);
    }
    
    /**
     * Update call
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $data = $request->getParsedBody();
        
        $call = \BeanFactory::getBean('Calls', $id);
        if (!$call || empty($call->id)) {
            return $this->notFoundResponse($response, 'Call');
        }
        
        // Update fields
        if (isset($data['name'])) $call->name = $data['name'];
        if (isset($data['description'])) $call->description = $data['description'];
        if (isset($data['status'])) $call->status = $data['status'];
        if (isset($data['direction'])) $call->direction = $data['direction'];
        if (isset($data['date_start'])) $call->date_start = $data['date_start'];
        if (isset($data['duration_hours'])) $call->duration_hours = $data['duration_hours'];
        if (isset($data['duration_minutes'])) $call->duration_minutes = $data['duration_minutes'];
        if (isset($data['call_purpose'])) $call->call_purpose = $data['call_purpose'];
        if (isset($data['call_result'])) $call->call_result = $data['call_result'];
        if (isset($data['phone_number'])) $call->phone_number = $data['phone_number'];
        
        // Recalculate end date if start or duration changed
        if (isset($data['date_start']) || isset($data['duration_hours']) || isset($data['duration_minutes'])) {
            $startTime = strtotime($call->date_start);
            $duration = ($call->duration_hours * 3600) + ($call->duration_minutes * 60);
            $call->date_end = date('Y-m-d H:i:s', $startTime + $duration);
        }
        
        $call->save();
        
        return $response->json($this->formatCallResponse($call));
    }
    
    /**
     * Delete call
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $request->getAttribute('id');
        
        $call = \BeanFactory::getBean('Calls', $id);
        if (!$call || empty($call->id)) {
            return $this->notFoundResponse($response, 'Call');
        }
        
        $call->mark_deleted($id);
        
        return $response->withStatus(204);
    }
    
    /**
     * Mark call as held
     */
    public function hold(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $data = $request->getParsedBody();
        
        $call = \BeanFactory::getBean('Calls', $id);
        if (!$call || empty($call->id)) {
            return $this->notFoundResponse($response, 'Call');
        }
        
        $call->status = 'Held';
        $call->call_result = $data['call_result'] ?? '';
        $call->description = $call->description . "\n\nCall Result: " . $call->call_result;
        
        // If no actual duration was provided, use the scheduled duration
        if (empty($call->date_end)) {
            $startTime = strtotime($call->date_start);
            $duration = ($call->duration_hours * 3600) + ($call->duration_minutes * 60);
            $call->date_end = date('Y-m-d H:i:s', $startTime + $duration);
        }
        
        $call->save();
        
        return $response->json([
            'message' => 'Call marked as held',
            'call' => $this->formatCallResponse($call)
        ]);
    }
    
    /**
     * Cancel call
     */
    public function cancel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $data = $request->getParsedBody();
        
        $call = \BeanFactory::getBean('Calls', $id);
        if (!$call || empty($call->id)) {
            return $this->notFoundResponse($response, 'Call');
        }
        
        $call->status = 'Not Held';
        if (!empty($data['reason'])) {
            $call->description = $call->description . "\n\nCancellation Reason: " . $data['reason'];
        }
        
        $call->save();
        
        return $response->json([
            'message' => 'Call cancelled',
            'call' => $this->formatCallResponse($call)
        ]);
    }
    
    /**
     * Get upcoming calls
     */
    public function upcoming(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $days = $queryParams['days'] ?? 7;
        
        global $db;
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        
        $safeNow = $db->quote($now);
        $safeFuture = $db->quote($future);
        
        $query = "SELECT * FROM calls 
                 WHERE deleted = 0 
                 AND status = 'Planned'
                 AND date_start >= $safeNow 
                 AND date_start <= $safeFuture
                 ORDER BY date_start ASC";
        
        $result = $db->query($query);
        $calls = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $call = \BeanFactory::getBean('Calls', $row['id']);
            if ($call) {
                $calls[] = $this->formatCallResponse($call);
            }
        }
        
        return $response->json($calls);
    }
    
    /**
     * Get today's calls
     */
    public function today(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        global $db;
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        $safeStart = $db->quote($todayStart);
        $safeEnd = $db->quote($todayEnd);
        
        $query = "SELECT * FROM calls 
                 WHERE deleted = 0 
                 AND date_start >= $safeStart 
                 AND date_start <= $safeEnd
                 ORDER BY date_start ASC";
        
        $result = $db->query($query);
        $calls = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $call = \BeanFactory::getBean('Calls', $row['id']);
            if ($call) {
                $calls[] = $this->formatCallResponse($call);
            }
        }
        
        return $response->json($calls);
    }
    
    /**
     * Create recurring calls
     */
    public function createRecurring(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // Validate recurrence data
        if (empty($data['repeat_type'])) {
            return $this->validationErrorResponse($response, 'Repeat type required', ['repeat_type' => 'Repeat type is required for recurring calls']);
        }
        
        if (empty($data['repeat_count']) && empty($data['repeat_until'])) {
            return $this->validationErrorResponse($response, 'Repeat count or until date required', ['repeat_count' => 'Either repeat count or until date is required']);
        }
        
        // Create parent call
        $parentCall = \BeanFactory::newBean('Calls');
        $this->populateCallFromData($parentCall, $data);
        $parentCall->repeat_type = $data['repeat_type'];
        $parentCall->repeat_interval = $data['repeat_interval'] ?? 1;
        $parentCall->repeat_dow = $data['repeat_dow'] ?? '';
        $parentCall->repeat_until = $data['repeat_until'] ?? '';
        $parentCall->repeat_count = $data['repeat_count'] ?? 0;
        $parentCall->save();
        
        // Generate recurring calls
        $createdCalls = [$this->formatCallResponse($parentCall)];
        $currentDate = strtotime($parentCall->date_start);
        $count = 1;
        $maxCount = $data['repeat_count'] ?? 52; // Default max 1 year weekly
        $untilDate = !empty($data['repeat_until']) ? strtotime($data['repeat_until']) : strtotime('+1 year');
        
        while ($count < $maxCount && $currentDate < $untilDate) {
            // Calculate next date based on repeat type
            switch ($data['repeat_type']) {
                case 'Daily':
                    $currentDate = strtotime("+{$parentCall->repeat_interval} days", $currentDate);
                    break;
                case 'Weekly':
                    $currentDate = strtotime("+{$parentCall->repeat_interval} weeks", $currentDate);
                    break;
                case 'Monthly':
                    $currentDate = strtotime("+{$parentCall->repeat_interval} months", $currentDate);
                    break;
                case 'Yearly':
                    $currentDate = strtotime("+{$parentCall->repeat_interval} years", $currentDate);
                    break;
            }
            
            // Create recurring instance
            $recurringCall = \BeanFactory::newBean('Calls');
            $this->populateCallFromData($recurringCall, $data);
            $recurringCall->date_start = date('Y-m-d H:i:s', $currentDate);
            $recurringCall->repeat_parent_id = $parentCall->id;
            $recurringCall->recurring_source = 'Sugar';
            $recurringCall->save();
            
            $createdCalls[] = $this->formatCallResponse($recurringCall);
            $count++;
        }
        
        return $response->json([
            'message' => count($createdCalls) . ' calls created',
            'calls' => $createdCalls
        ], 201);
    }
    
    /**
     * Format call response
     */
    protected function formatCallResponse($call, $includeDetails = false)
    {
        $data = [
            'id' => $call->id,
            'name' => $call->name,
            'status' => $call->status,
            'direction' => $call->direction,
            'date_start' => $call->date_start,
            'date_end' => $call->date_end,
            'duration_hours' => (int)$call->duration_hours,
            'duration_minutes' => (int)$call->duration_minutes,
            'parent_type' => $call->parent_type,
            'parent_id' => $call->parent_id,
            'contact_id' => $call->contact_id,
            'assigned_user_id' => $call->assigned_user_id,
            'date_entered' => $call->date_entered,
            'date_modified' => $call->date_modified,
            'call_purpose' => $call->call_purpose,
            'call_result' => $call->call_result,
            'phone_number' => $call->phone_number
        ];
        
        if ($includeDetails) {
            $data['description'] = $call->description;
            $data['reminder_time'] = $call->reminder_time;
            $data['email_reminder_time'] = $call->email_reminder_time;
            $data['repeat_type'] = $call->repeat_type;
            $data['repeat_interval'] = $call->repeat_interval;
            $data['repeat_dow'] = $call->repeat_dow;
            $data['repeat_until'] = $call->repeat_until;
            $data['repeat_count'] = $call->repeat_count;
            $data['repeat_parent_id'] = $call->repeat_parent_id;
            
            // Load invitees
            $call->load_relationship('users');
            $users = $call->users->getBeans();
            $invitees = [];
            foreach ($users as $user) {
                $invitees[] = [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'accept_status' => $call->users->get_relationship_data($user->id)['accept_status'] ?? ''
                ];
            }
            $data['invitees'] = $invitees;
        }
        
        // Get contact name
        if (!empty($call->contact_id)) {
            $contact = \BeanFactory::getBean('Contacts', $call->contact_id);
            if ($contact) {
                $data['contact_name'] = trim($contact->first_name . ' ' . $contact->last_name);
                $data['contact_phone'] = $contact->phone_work ?: $contact->phone_mobile;
            }
        }
        
        // Get parent name
        if (!empty($call->parent_type) && !empty($call->parent_id)) {
            $parent = \BeanFactory::getBean($call->parent_type, $call->parent_id);
            if ($parent) {
                $data['parent_name'] = $parent->name;
            }
        }
        
        // Get assigned user name
        if (!empty($call->assigned_user_id)) {
            $user = \BeanFactory::getBean('Users', $call->assigned_user_id);
            if ($user) {
                $data['assigned_user_name'] = trim($user->first_name . ' ' . $user->last_name);
            }
        }
        
        // Calculate duration string
        $hours = $call->duration_hours ?? 0;
        $minutes = $call->duration_minutes ?? 0;
        $data['duration'] = sprintf('%dh %dm', $hours, $minutes);
        
        return $data;
    }
    
    /**
     * Populate call bean from request data
     */
    protected function populateCallFromData($call, $data)
    {
        $call->name = $data['name'] ?? '';
        $call->description = $data['description'] ?? '';
        $call->status = $data['status'] ?? 'Planned';
        $call->direction = $data['direction'] ?? 'Outbound';
        $call->date_start = $data['date_start'] ?? date('Y-m-d H:i:s');
        $call->duration_hours = $data['duration_hours'] ?? 0;
        $call->duration_minutes = $data['duration_minutes'] ?? 15;
        $call->call_purpose = $data['call_purpose'] ?? '';
        $call->call_result = $data['call_result'] ?? '';
        $call->phone_number = $data['phone_number'] ?? '';
        
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $call->parent_type = $data['parent_type'];
            $call->parent_id = $data['parent_id'];
        }
        
        if (!empty($data['contact_id'])) {
            $call->contact_id = $data['contact_id'];
        }
        
        if (!empty($data['assigned_user_id'])) {
            $call->assigned_user_id = $data['assigned_user_id'];
        } else {
            global $current_user;
            $call->assigned_user_id = $current_user->id;
        }
        
        // Calculate end date
        $startTime = strtotime($call->date_start);
        $duration = ($call->duration_hours * 3600) + ($call->duration_minutes * 60);
        $call->date_end = date('Y-m-d H:i:s', $startTime + $duration);
    }
    
    /**
     * Sanitize order by clause
     */
    protected function sanitizeOrderBy($orderBy)
    {
        // Simple sanitization for order by clause
        $allowedFields = ['date_start', 'date_end', 'name', 'status', 'date_entered', 'date_modified'];
        $parts = explode(' ', $orderBy);
        
        if (count($parts) >= 1 && in_array($parts[0], $allowedFields)) {
            $field = $parts[0];
            $direction = (count($parts) > 1 && strtoupper($parts[1]) === 'ASC') ? 'ASC' : 'DESC';
            return "$field $direction";
        }
        
        return 'date_start DESC';
    }
}