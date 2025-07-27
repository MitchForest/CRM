<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivitiesController extends Controller
{
    /**
     * Get all activities
     * GET /api/crm/activities
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|min:1|max:100',
                'type' => 'sometimes|string|in:all,call,meeting,task,note',
                'parent_type' => 'sometimes|string',
                'parent_id' => 'sometimes|string',
                'assigned_user_id' => 'sometimes|string',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $type = $request->input('type', 'all');
            $parentType = $request->input('parent_type');
            $parentId = $request->input('parent_id');
            $assignedUserId = $request->input('assigned_user_id');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            
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
            
            return response()->json([
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
            return response()->json([
                'error' => 'Failed to fetch activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single activity
     * GET /api/crm/activities/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // Try to find the activity in different models
            $models = [
                ['model' => Call::class, 'type' => 'call'],
                ['model' => Meeting::class, 'type' => 'meeting'],
                ['model' => Task::class, 'type' => 'task'],
                ['model' => Note::class, 'type' => 'note']
            ];
            
            foreach ($models as $item) {
                $activity = $item['model']::find($id);
                if ($activity) {
                    return response()->json([
                        'data' => $this->formatActivity($activity, $item['type'])
                    ]);
                }
            }
            
            return response()->json(['error' => 'Activity not found'], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new activity
     * POST /api/crm/activities
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:call,meeting,task,note',
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'assigned_user_id' => 'sometimes|string|exists:users,id',
            'parent_type' => 'sometimes|string',
            'parent_id' => 'sometimes|string',
            'contact_ids' => 'sometimes|array',
            'contact_ids.*' => 'string|exists:contacts,id'
        ]);
        
        DB::beginTransaction();
        
        try {
            $data = $request->all();
            $activity = null;
            
            switch ($data['type']) {
                case 'call':
                    $request->validate([
                        'status' => 'sometimes|string',
                        'direction' => 'sometimes|string|in:Inbound,Outbound',
                        'duration_hours' => 'sometimes|integer|min:0',
                        'duration_minutes' => 'sometimes|integer|min:0|max:59',
                        'date_start' => 'sometimes|date'
                    ]);
                    
                    $activity = Call::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? '',
                        'status' => $data['status'] ?? 'Planned',
                        'direction' => $data['direction'] ?? 'Outbound',
                        'duration_hours' => $data['duration_hours'] ?? 0,
                        'duration_minutes' => $data['duration_minutes'] ?? 15,
                        'date_start' => $data['date_start'] ?? now(),
                        'assigned_user_id' => $data['assigned_user_id'] ?? $request->user()->id,
                        'parent_type' => $data['parent_type'] ?? null,
                        'parent_id' => $data['parent_id'] ?? null
                    ]);
                    break;
                    
                case 'meeting':
                    $request->validate([
                        'status' => 'sometimes|string',
                        'location' => 'sometimes|string',
                        'duration_hours' => 'sometimes|integer|min:0',
                        'duration_minutes' => 'sometimes|integer|min:0|max:59',
                        'date_start' => 'sometimes|date'
                    ]);
                    
                    $activity = Meeting::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? '',
                        'status' => $data['status'] ?? 'Planned',
                        'location' => $data['location'] ?? '',
                        'duration_hours' => $data['duration_hours'] ?? 1,
                        'duration_minutes' => $data['duration_minutes'] ?? 0,
                        'date_start' => $data['date_start'] ?? now(),
                        'assigned_user_id' => $data['assigned_user_id'] ?? $request->user()->id,
                        'parent_type' => $data['parent_type'] ?? null,
                        'parent_id' => $data['parent_id'] ?? null
                    ]);
                    break;
                    
                case 'task':
                    $request->validate([
                        'status' => 'sometimes|string',
                        'priority' => 'sometimes|string|in:High,Medium,Low',
                        'date_due' => 'sometimes|date'
                    ]);
                    
                    $activity = Task::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? '',
                        'status' => $data['status'] ?? 'Not Started',
                        'priority' => $data['priority'] ?? 'Medium',
                        'date_due' => $data['date_due'] ?? now()->addDays(7),
                        'assigned_user_id' => $data['assigned_user_id'] ?? $request->user()->id,
                        'parent_type' => $data['parent_type'] ?? null,
                        'parent_id' => $data['parent_id'] ?? null
                    ]);
                    break;
                    
                case 'note':
                    $activity = Note::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? '',
                        'assigned_user_id' => $data['assigned_user_id'] ?? $request->user()->id,
                        'parent_type' => $data['parent_type'] ?? null,
                        'parent_id' => $data['parent_id'] ?? null
                    ]);
                    break;
            }
            
            // Add related contacts if provided
            if (!empty($data['contact_ids']) && in_array($data['type'], ['call', 'meeting'])) {
                $activity->contacts()->attach($data['contact_ids']);
            }
            
            DB::commit();
            
            return response()->json([
                'data' => [
                    'id' => $activity->id
                ],
                'message' => 'Activity created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update activity
     * PUT /api/crm/activities/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Find the activity
            $activity = null;
            $type = null;
            
            if ($activity = Call::find($id)) {
                $type = 'call';
            } elseif ($activity = Meeting::find($id)) {
                $type = 'meeting';
            } elseif ($activity = Task::find($id)) {
                $type = 'task';
            } elseif ($activity = Note::find($id)) {
                $type = 'note';
            }
            
            if (!$activity) {
                return response()->json(['error' => 'Activity not found'], 404);
            }
            
            // Update common fields
            $activity->fill($request->only(['name', 'description', 'assigned_user_id']));
            
            // Update type-specific fields
            switch ($type) {
                case 'call':
                    $activity->fill($request->only(['status', 'direction', 'duration_hours', 'duration_minutes', 'date_start']));
                    break;
                case 'meeting':
                    $activity->fill($request->only(['status', 'location', 'duration_hours', 'duration_minutes', 'date_start']));
                    break;
                case 'task':
                    $activity->fill($request->only(['status', 'priority', 'date_due']));
                    break;
            }
            
            $activity->save();
            
            DB::commit();
            
            return response()->json([
                'data' => ['id' => $activity->id],
                'message' => 'Activity updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete activity
     * DELETE /api/crm/activities/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            // Find and delete the activity
            $models = [Call::class, Meeting::class, Task::class, Note::class];
            
            foreach ($models as $model) {
                $activity = $model::find($id);
                if ($activity) {
                    $activity->delete();
                    return response()->json(['message' => 'Activity deleted successfully']);
                }
            }
            
            return response()->json(['error' => 'Activity not found'], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete activity',
                'message' => $e->getMessage()
            ], 500);
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
            'assignedUserName' => $activity->assignedUser->full_name ?? null,
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