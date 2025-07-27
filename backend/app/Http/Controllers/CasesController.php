<?php

namespace App\Http\Controllers;

use App\Models\Case;
use App\Models\Contact;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\Note;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class CasesController extends Controller
{
    public function index(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $query = Case::with(['assignedUser', 'contacts'])
            ->where('deleted', 0);
        
        // Apply filters
        if (isset($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('case_number', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }
        
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        
        if (isset($params['priority'])) {
            $query->where('priority', $params['priority']);
        }
        
        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }
        
        if (isset($params['assigned_user_id'])) {
            $query->where('assigned_user_id', $params['assigned_user_id']);
        }
        
        // Sorting
        $sortBy = $params['sort_by'] ?? 'date_entered';
        $sortOrder = $params['sort_order'] ?? 'DESC';
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        $cases = $query->paginate($limit, ['*'], 'page', $page);
        
        // Format response
        $data = $cases->map(function ($case) {
            $contact = $case->contacts->first();
            
            return [
                'id' => $case->id,
                'caseNumber' => $case->case_number,
                'name' => $case->name,
                'status' => $case->status,
                'priority' => $case->priority,
                'type' => $case->type,
                'description' => $case->description,
                'resolution' => $case->resolution,
                'contactId' => $contact?->id,
                'contactName' => $contact?->full_name,
                'assignedUserId' => $case->assigned_user_id,
                'assignedUserName' => $case->assignedUser?->full_name,
                'dateEntered' => $case->date_entered?->toIso8601String(),
                'dateModified' => $case->date_modified?->toIso8601String(),
                'createdBy' => $case->created_by,
                'modifiedUserId' => $case->modified_user_id
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'pagination' => [
                'page' => $cases->currentPage(),
                'pageSize' => $cases->perPage(),
                'totalPages' => $cases->lastPage(),
                'totalCount' => $cases->total(),
                'hasNext' => $cases->hasMorePages(),
                'hasPrevious' => $cases->currentPage() > 1
            ]
        ]);
    }
    
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $case = Case::with(['assignedUser', 'contacts', 'calls', 'meetings', 'notes'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$case) {
            return $this->error($response, 'Case not found', 404);
        }
        
        // Format contacts
        $contacts = $case->contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'email1' => $contact->email1,
                'phone_work' => $contact->phone_work
            ];
        });
        
        // Get activities
        $activities = $this->getCaseActivities($case);
        
        return $this->json($response, [
            'data' => [
                'id' => $case->id,
                'caseNumber' => $case->case_number,
                'name' => $case->name,
                'status' => $case->status,
                'priority' => $case->priority,
                'type' => $case->type,
                'description' => $case->description,
                'resolution' => $case->resolution,
                'assignedUserId' => $case->assigned_user_id,
                'assignedUserName' => $case->assignedUser?->full_name,
                'dateEntered' => $case->date_entered?->toIso8601String(),
                'dateModified' => $case->date_modified?->toIso8601String(),
                'createdBy' => $case->created_by,
                'modifiedUserId' => $case->modified_user_id,
                'contacts' => $contacts,
                'activities' => $activities
            ]
        ]);
    }
    
    public function create(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:255',
            'status' => 'sometimes|string|max:100',
            'priority' => 'sometimes|string|max:100',
            'type' => 'sometimes|string|max:100',
            'description' => 'sometimes|string',
            'resolution' => 'sometimes|string',
            'assignedUserId' => 'sometimes|string|exists:users,id',
            'contactId' => 'sometimes|string|exists:contacts,id'
        ]);
        
        DB::beginTransaction();
        
        try {
            $case = new Case();
            $case->case_number = $this->generateCaseNumber();
            $case->name = $data['name'];
            $case->status = $data['status'] ?? 'Open';
            $case->priority = $data['priority'] ?? 'Medium';
            $case->type = $data['type'] ?? 'Technical';
            $case->description = $data['description'] ?? '';
            $case->resolution = $data['resolution'] ?? '';
            $case->assigned_user_id = $data['assignedUserId'] ?? $request->getAttribute('user_id');
            
            $case->save();
            
            // Add contact if provided
            if (isset($data['contactId'])) {
                $case->contacts()->attach($data['contactId']);
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => [
                    'id' => $case->id,
                    'caseNumber' => $case->case_number
                ],
                'message' => 'Case created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create case: ' . $e->getMessage(), 500);
        }
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $case = Case::where('deleted', 0)->find($id);
        
        if (!$case) {
            return $this->error($response, 'Case not found', 404);
        }
        
        $data = $this->validate($request, [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:100',
            'priority' => 'sometimes|string|max:100',
            'type' => 'sometimes|string|max:100',
            'description' => 'sometimes|string',
            'resolution' => 'sometimes|string',
            'assignedUserId' => 'sometimes|string|exists:users,id',
            'contactId' => 'sometimes|nullable|string|exists:contacts,id'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Update fields
            if (isset($data['name'])) $case->name = $data['name'];
            if (isset($data['status'])) $case->status = $data['status'];
            if (isset($data['priority'])) $case->priority = $data['priority'];
            if (isset($data['type'])) $case->type = $data['type'];
            if (isset($data['description'])) $case->description = $data['description'];
            if (isset($data['resolution'])) $case->resolution = $data['resolution'];
            if (isset($data['assignedUserId'])) $case->assigned_user_id = $data['assignedUserId'];
            
            $case->save();
            
            // Update contact if provided
            if (isset($data['contactId'])) {
                if ($data['contactId']) {
                    $case->contacts()->sync([$data['contactId']]);
                } else {
                    $case->contacts()->detach();
                }
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $case->id],
                'message' => 'Case updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update case: ' . $e->getMessage(), 500);
        }
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $case = Case::where('deleted', 0)->find($id);
        
        if (!$case) {
            return $this->error($response, 'Case not found', 404);
        }
        
        $case->deleted = 1;
        $case->save();
        
        return $this->json($response, [
            'message' => 'Case deleted successfully'
        ]);
    }
    
    private function generateCaseNumber(): string
    {
        $lastCase = Case::orderBy('case_number', 'DESC')->first();
        
        if ($lastCase && preg_match('/CASE-(\d+)/', $lastCase->case_number, $matches)) {
            $lastNumber = (int)$matches[1];
            return sprintf('CASE-%03d', $lastNumber + 1);
        }
        
        return 'CASE-001';
    }
    
    private function getCaseActivities(Case $case): array
    {
        $activities = [];
        
        // Calls
        foreach ($case->calls as $call) {
            $activities[] = [
                'type' => 'call',
                'id' => $call->id,
                'name' => $call->name,
                'status' => $call->status,
                'date' => $call->date_start,
                'assignedTo' => $call->assignedUser?->user_name
            ];
        }
        
        // Meetings
        foreach ($case->meetings as $meeting) {
            $activities[] = [
                'type' => 'meeting',
                'id' => $meeting->id,
                'name' => $meeting->name,
                'status' => $meeting->status,
                'date' => $meeting->date_start,
                'assignedTo' => $meeting->assignedUser?->user_name
            ];
        }
        
        // Notes
        foreach ($case->notes as $note) {
            $activities[] = [
                'type' => 'note',
                'id' => $note->id,
                'name' => $note->name,
                'date' => $note->date_entered,
                'assignedTo' => $note->assignedUser?->user_name
            ];
        }
        
        // Sort by date descending
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $activities;
    }
}