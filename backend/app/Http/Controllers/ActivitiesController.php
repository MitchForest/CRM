<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Note;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class ActivitiesController extends Controller
{
    /**
     * Get all activities
     * GET /api/crm/activities
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $params = $request->getQueryParams();
            $data = $this->validate($request, [
                'page' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|min:1|max:100',
                'type' => 'sometimes|string|in:all,call,meeting,task,note',
                'parent_type' => 'sometimes|string',
                'parent_id' => 'sometimes|string',
                'assigned_user_id' => 'sometimes|string',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $page = intval($data['page'] ?? 1);
            $limit = intval($data['limit'] ?? 20);
            $type = $data['type'] ?? 'all';
            $parentType = $data['parent_type'] ?? null;
            $parentId = $data['parent_id'] ?? null;
            $assignedUserId = $data['assigned_user_id'] ?? null;
            $dateFrom = $data['date_from'] ?? null;
            $dateTo = $data['date_to'] ?? null;
            
            $activities = collect();
            
            // Get activities based on type
            if ($type === 'all' || $type === 'call') {
                $activities = $activities->merge($this->getCalls($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo));
            }
            if ($type === 'all' || $type === 'meeting') {
                $activities = $activities->merge($this->getMeetings($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo));
            }
            if ($type === 'all' || $type === 'task') {
                $activities = $activities->merge($this->getTasks($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo));
            }
            if ($type === 'all' || $type === 'note') {
                $activities = $activities->merge($this->getNotes($parentType, $parentId, $assignedUserId, $dateFrom, $dateTo));
            }
            
            // Sort by date descending
            $activities = $activities->sortByDesc('date');
            
            $totalCount = $activities->count();
            
            // Apply pagination
            $offset = ($page - 1) * $limit;
            $activities = $activities->slice($offset, $limit)->values();
            
            return $this->json($response, [
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
            return $this->error($response, 'Failed to fetch activities: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get upcoming activities
     * GET /api/crm/activities/upcoming
     */
    public function upcoming(Request $request, Response $response, array $args): Response
    {
        try {
            $params = $request->getQueryParams();
            $data = $this->validate($request, [
                'limit' => 'sometimes|integer|min:1|max:100',
                'assigned_user_id' => 'sometimes|string'
            ]);
            
            $limit = intval($data['limit'] ?? 10);
            $assignedUserId = $data['assigned_user_id'] ?? $request->getAttribute('user_id');
            
            $currentDate = (new \DateTime())->format('Y-m-d H:i:s');
            $activities = collect();
            
            // Get upcoming calls
            $calls = Call::where('deleted', 0)
                ->where('status', 'Planned')
                ->where('date_start', '>=', $currentDate)
                ->when($assignedUserId, function ($query) use ($assignedUserId) {
                    return $query->where('assigned_user_id', $assignedUserId);
                })
                ->orderBy('date_start')
                ->limit($limit)
                ->get()
                ->map(function ($call) {
                    return $this->formatActivity($call, 'call');
                });
            
            // Get upcoming meetings
            $meetings = Meeting::where('deleted', 0)
                ->where('status', 'Planned')
                ->where('date_start', '>=', $currentDate)
                ->when($assignedUserId, function ($query) use ($assignedUserId) {
                    return $query->where('assigned_user_id', $assignedUserId);
                })
                ->orderBy('date_start')
                ->limit($limit)
                ->get()
                ->map(function ($meeting) {
                    return $this->formatActivity($meeting, 'meeting');
                });
            
            // Get upcoming tasks
            $tasks = Task::where('deleted', 0)
                ->whereIn('status', ['Not Started', 'In Progress'])
                ->where('date_due', '>=', $currentDate)
                ->when($assignedUserId, function ($query) use ($assignedUserId) {
                    return $query->where('assigned_user_id', $assignedUserId);
                })
                ->orderBy('date_due')
                ->limit($limit)
                ->get()
                ->map(function ($task) {
                    return $this->formatActivity($task, 'task');
                });
            
            // Merge and sort by date
            $activities = $activities->merge($calls)->merge($meetings)->merge($tasks);
            $activities = $activities->sortBy('date')->take($limit)->values();
            
            return $this->json($response, [
                'data' => $activities
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch upcoming activities: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get overdue activities
     * GET /api/crm/activities/overdue
     */
    public function overdue(Request $request, Response $response, array $args): Response
    {
        try {
            $params = $request->getQueryParams();
            $data = $this->validate($request, [
                'limit' => 'sometimes|integer|min:1|max:100',
                'assigned_user_id' => 'sometimes|string'
            ]);
            
            $limit = intval($data['limit'] ?? 20);
            $assignedUserId = $data['assigned_user_id'] ?? $request->getAttribute('user_id');
            
            $currentDate = (new \DateTime())->format('Y-m-d H:i:s');
            $activities = collect();
            
            // Get overdue calls
            $calls = Call::where('deleted', 0)
                ->where('status', 'Planned')
                ->where('date_start', '<', $currentDate)
                ->when($assignedUserId, function ($query) use ($assignedUserId) {
                    return $query->where('assigned_user_id', $assignedUserId);
                })
                ->orderBy('date_start', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($call) {
                    return $this->formatActivity($call, 'call');
                });
            
            // Get overdue meetings
            $meetings = Meeting::where('deleted', 0)
                ->where('status', 'Planned')
                ->where('date_start', '<', $currentDate)
                ->when($assignedUserId, function ($query) use ($assignedUserId) {
                    return $query->where('assigned_user_id', $assignedUserId);
                })
                ->orderBy('date_start', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($meeting) {
                    return $this->formatActivity($meeting, 'meeting');
                });
            
            // Get overdue tasks
            $tasks = Task::where('deleted', 0)
                ->whereIn('status', ['Not Started', 'In Progress'])
                ->where('date_due', '<', $currentDate)
                ->when($assignedUserId, function ($query) use ($assignedUserId) {
                    return $query->where('assigned_user_id', $assignedUserId);
                })
                ->orderBy('date_due', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($task) {
                    return $this->formatActivity($task, 'task');
                });
            
            // Merge all overdue activities
            $activities = $activities->merge($calls)->merge($meetings)->merge($tasks);
            $activities = $activities->sortByDesc('date')->take($limit)->values();
            
            return $this->json($response, [
                'data' => $activities,
                'summary' => [
                    'totalOverdue' => $activities->count(),
                    'overdueCalls' => $calls->count(),
                    'overdueMeetings' => $meetings->count(),
                    'overdueTasks' => $tasks->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch overdue activities: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create task
     * POST /api/crm/activities/tasks
     */
    public function createTask(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        
        try {
            $data = $this->validate($request, [
                'name' => 'required|string|max:255',
                'description' => 'sometimes|string',
                'status' => 'sometimes|string|in:Not Started,In Progress,Completed,Pending Input,Deferred',
                'priority' => 'sometimes|string|in:High,Medium,Low',
                'date_due' => 'sometimes|date',
                'assigned_user_id' => 'sometimes|string|exists:users,id',
                'parent_type' => 'sometimes|string',
                'parent_id' => 'sometimes|string'
            ]);
            
            $task = Task::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'Not Started',
                'priority' => $data['priority'] ?? 'Medium',
                'date_due' => $data['date_due'] ?? (new \DateTime())->modify('+7 days')->format('Y-m-d'),
                'assigned_user_id' => $data['assigned_user_id'] ?? $request->getAttribute('user_id'),
                'parent_type' => $data['parent_type'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'created_by' => $request->getAttribute('user_id'),
                'modified_user_id' => $request->getAttribute('user_id')
            ]);
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $task->id],
                'message' => 'Task created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create task: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create call
     * POST /api/crm/activities/calls
     */
    public function createCall(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        
        try {
            $data = $this->validate($request, [
                'name' => 'required|string|max:255',
                'description' => 'sometimes|string',
                'status' => 'sometimes|string|in:Planned,Held,Not Held',
                'direction' => 'sometimes|string|in:Inbound,Outbound',
                'duration_hours' => 'sometimes|integer|min:0',
                'duration_minutes' => 'sometimes|integer|min:0|max:59',
                'date_start' => 'sometimes|date',
                'assigned_user_id' => 'sometimes|string|exists:users,id',
                'parent_type' => 'sometimes|string',
                'parent_id' => 'sometimes|string',
                'contact_ids' => 'sometimes|array',
                'contact_ids.*' => 'string|exists:contacts,id'
            ]);
            
            $call = Call::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'Planned',
                'direction' => $data['direction'] ?? 'Outbound',
                'duration_hours' => $data['duration_hours'] ?? 0,
                'duration_minutes' => $data['duration_minutes'] ?? 15,
                'date_start' => $data['date_start'] ?? (new \DateTime())->format('Y-m-d H:i:s'),
                'assigned_user_id' => $data['assigned_user_id'] ?? $request->getAttribute('user_id'),
                'parent_type' => $data['parent_type'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'created_by' => $request->getAttribute('user_id'),
                'modified_user_id' => $request->getAttribute('user_id')
            ]);
            
            // Add related contacts if provided
            if (!empty($data['contact_ids'])) {
                $call->contacts()->attach($data['contact_ids']);
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $call->id],
                'message' => 'Call created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create call: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create meeting
     * POST /api/crm/activities/meetings
     */
    public function createMeeting(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        
        try {
            $data = $this->validate($request, [
                'name' => 'required|string|max:255',
                'description' => 'sometimes|string',
                'status' => 'sometimes|string|in:Planned,Held,Not Held',
                'location' => 'sometimes|string',
                'duration_hours' => 'sometimes|integer|min:0',
                'duration_minutes' => 'sometimes|integer|min:0|max:59',
                'date_start' => 'sometimes|date',
                'assigned_user_id' => 'sometimes|string|exists:users,id',
                'parent_type' => 'sometimes|string',
                'parent_id' => 'sometimes|string',
                'contact_ids' => 'sometimes|array',
                'contact_ids.*' => 'string|exists:contacts,id'
            ]);
            
            $meeting = Meeting::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'Planned',
                'location' => $data['location'] ?? '',
                'duration_hours' => $data['duration_hours'] ?? 1,
                'duration_minutes' => $data['duration_minutes'] ?? 0,
                'date_start' => $data['date_start'] ?? (new \DateTime())->format('Y-m-d H:i:s'),
                'assigned_user_id' => $data['assigned_user_id'] ?? $request->getAttribute('user_id'),
                'parent_type' => $data['parent_type'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'created_by' => $request->getAttribute('user_id'),
                'modified_user_id' => $request->getAttribute('user_id')
            ]);
            
            // Add related contacts if provided
            if (!empty($data['contact_ids'])) {
                $meeting->contacts()->attach($data['contact_ids']);
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $meeting->id],
                'message' => 'Meeting created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create meeting: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update activity
     * PUT /api/crm/activities/{type}/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        
        try {
            $type = $args['type'];
            $id = $args['id'];
            
            // Find the activity based on type
            $activity = null;
            
            switch ($type) {
                case 'call':
                    $activity = Call::find($id);
                    break;
                case 'meeting':
                    $activity = Meeting::find($id);
                    break;
                case 'task':
                    $activity = Task::find($id);
                    break;
                case 'note':
                    $activity = Note::find($id);
                    break;
                default:
                    return $this->error($response, 'Invalid activity type', 400);
            }
            
            if (!$activity) {
                return $this->error($response, 'Activity not found', 404);
            }
            
            $data = $request->getParsedBody();
            
            // Update common fields
            if (isset($data['name'])) $activity->name = $data['name'];
            if (isset($data['description'])) $activity->description = $data['description'];
            if (isset($data['assigned_user_id'])) $activity->assigned_user_id = $data['assigned_user_id'];
            
            // Update type-specific fields
            switch ($type) {
                case 'call':
                    if (isset($data['status'])) $activity->status = $data['status'];
                    if (isset($data['direction'])) $activity->direction = $data['direction'];
                    if (isset($data['duration_hours'])) $activity->duration_hours = $data['duration_hours'];
                    if (isset($data['duration_minutes'])) $activity->duration_minutes = $data['duration_minutes'];
                    if (isset($data['date_start'])) $activity->date_start = $data['date_start'];
                    break;
                    
                case 'meeting':
                    if (isset($data['status'])) $activity->status = $data['status'];
                    if (isset($data['location'])) $activity->location = $data['location'];
                    if (isset($data['duration_hours'])) $activity->duration_hours = $data['duration_hours'];
                    if (isset($data['duration_minutes'])) $activity->duration_minutes = $data['duration_minutes'];
                    if (isset($data['date_start'])) $activity->date_start = $data['date_start'];
                    break;
                    
                case 'task':
                    if (isset($data['status'])) $activity->status = $data['status'];
                    if (isset($data['priority'])) $activity->priority = $data['priority'];
                    if (isset($data['date_due'])) $activity->date_due = $data['date_due'];
                    break;
            }
            
            $activity->modified_user_id = $request->getAttribute('user_id');
            $activity->save();
            
            // Update related contacts if provided (for calls and meetings)
            if (in_array($type, ['call', 'meeting']) && isset($data['contact_ids'])) {
                $activity->contacts()->sync($data['contact_ids']);
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $activity->id],
                'message' => ucfirst($type) . ' updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update activity: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete activity
     * DELETE /api/crm/activities/{type}/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $type = $args['type'];
            $id = $args['id'];
            
            // Find the activity based on type
            $activity = null;
            
            switch ($type) {
                case 'call':
                    $activity = Call::find($id);
                    break;
                case 'meeting':
                    $activity = Meeting::find($id);
                    break;
                case 'task':
                    $activity = Task::find($id);
                    break;
                case 'note':
                    $activity = Note::find($id);
                    break;
                default:
                    return $this->error($response, 'Invalid activity type', 400);
            }
            
            if (!$activity) {
                return $this->error($response, 'Activity not found', 404);
            }
            
            // Soft delete
            $activity->deleted = 1;
            $activity->save();
            
            return $this->json($response, ['message' => ucfirst($type) . ' deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to delete activity: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get calls
     */
    private function getCalls($parentType = null, $parentId = null, $assignedUserId = null, $dateFrom = null, $dateTo = null)
    {
        $query = Call::where('deleted', 0);
        
        if ($parentType && $parentId) {
            $query->where('parent_type', $parentType)->where('parent_id', $parentId);
        }
        
        if ($assignedUserId) {
            $query->where('assigned_user_id', $assignedUserId);
        }
        
        if ($dateFrom) {
            $query->where('date_start', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('date_start', '<=', $dateTo);
        }
        
        return $query->with('assignedUser')->get()->map(function ($call) {
            return $this->formatActivity($call, 'call');
        });
    }
    
    /**
     * Get meetings
     */
    private function getMeetings($parentType = null, $parentId = null, $assignedUserId = null, $dateFrom = null, $dateTo = null)
    {
        $query = Meeting::where('deleted', 0);
        
        if ($parentType && $parentId) {
            $query->where('parent_type', $parentType)->where('parent_id', $parentId);
        }
        
        if ($assignedUserId) {
            $query->where('assigned_user_id', $assignedUserId);
        }
        
        if ($dateFrom) {
            $query->where('date_start', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('date_start', '<=', $dateTo);
        }
        
        return $query->with('assignedUser')->get()->map(function ($meeting) {
            return $this->formatActivity($meeting, 'meeting');
        });
    }
    
    /**
     * Get tasks
     */
    private function getTasks($parentType = null, $parentId = null, $assignedUserId = null, $dateFrom = null, $dateTo = null)
    {
        $query = Task::where('deleted', 0);
        
        if ($parentType && $parentId) {
            $query->where('parent_type', $parentType)->where('parent_id', $parentId);
        }
        
        if ($assignedUserId) {
            $query->where('assigned_user_id', $assignedUserId);
        }
        
        if ($dateFrom) {
            $query->where('date_due', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('date_due', '<=', $dateTo);
        }
        
        return $query->with('assignedUser')->get()->map(function ($task) {
            return $this->formatActivity($task, 'task');
        });
    }
    
    /**
     * Get notes
     */
    private function getNotes($parentType = null, $parentId = null, $assignedUserId = null, $dateFrom = null, $dateTo = null)
    {
        $query = Note::where('deleted', 0);
        
        if ($parentType && $parentId) {
            $query->where('parent_type', $parentType)->where('parent_id', $parentId);
        }
        
        if ($assignedUserId) {
            $query->where('assigned_user_id', $assignedUserId);
        }
        
        if ($dateFrom) {
            $query->where('date_entered', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('date_entered', '<=', $dateTo);
        }
        
        return $query->with('assignedUser')->get()->map(function ($note) {
            return $this->formatActivity($note, 'note');
        });
    }
    
    /**
     * Format activity for response
     */
    private function formatActivity($activity, string $type): array
    {
        $data = [
            'id' => $activity->id,
            'type' => $type,
            'name' => $activity->name,
            'description' => $activity->description ?? '',
            'assignedUserId' => $activity->assigned_user_id,
            'assignedUserName' => $activity->assignedUser ? $activity->assignedUser->first_name . ' ' . $activity->assignedUser->last_name : null,
            'parentType' => $activity->parent_type ?? '',
            'parentId' => $activity->parent_id ?? '',
            'dateEntered' => $activity->date_entered,
            'dateModified' => $activity->date_modified
        ];
        
        // Add type-specific fields
        switch ($type) {
            case 'call':
                $data['status'] = $activity->status;
                $data['direction'] = $activity->direction;
                $data['duration'] = ($activity->duration_hours * 60) + $activity->duration_minutes;
                $data['date'] = $activity->date_start;
                break;
                
            case 'meeting':
                $data['status'] = $activity->status;
                $data['location'] = $activity->location;
                $data['duration'] = ($activity->duration_hours * 60) + $activity->duration_minutes;
                $data['date'] = $activity->date_start;
                break;
                
            case 'task':
                $data['status'] = $activity->status;
                $data['priority'] = $activity->priority;
                $data['date'] = $activity->date_due;
                break;
                
            case 'note':
                $data['date'] = $activity->date_entered;
                break;
        }
        
        return $data;
    }
}