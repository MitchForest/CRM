<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ActivityTrackingVisitor;
use App\Models\ChatConversation;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\Case;
use App\Models\CustomerHealthScore;
use App\Models\Opportunity;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class ContactsController extends Controller
{
    public function index(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $query = Contact::with(['assignedUser'])
            ->where('deleted', 0);
        
        // Add search filter
        if (isset($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('account_name', 'like', "%$search%")
                  ->orWhere('email1', 'like', "%$search%");
            });
        }
        
        // Select display name
        $query->selectRaw('*, CASE WHEN is_company = 1 THEN account_name ELSE CONCAT(first_name, " ", last_name) END as display_name');
        
        // Sorting
        $query->orderBy('date_entered', 'DESC');
        
        // Pagination
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        $contacts = $query->paginate($limit, ['*'], 'page', $page);
        
        // Format response
        $data = $contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'full_name' => $contact->full_name,
                'email1' => $contact->email1,
                'phone_work' => $contact->phone_work,
                'phone_mobile' => $contact->phone_mobile,
                'title' => $contact->title,
                'department' => $contact->department,
                'account_id' => $contact->account_id,
                'account_name' => $contact->account?->name,
                'assigned_user_id' => $contact->assigned_user_id,
                'assigned_user_name' => $contact->assignedUser?->full_name,
                'date_entered' => $contact->date_entered?->toIso8601String(),
                'date_modified' => $contact->date_modified?->toIso8601String()
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'pagination' => [
                'page' => $contacts->currentPage(),
                'pageSize' => $contacts->perPage(),
                'total' => $contacts->total(),
                'totalPages' => $contacts->lastPage()
            ]
        ]);
    }
    
    public function unifiedView(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $contact = Contact::with(['assignedUser'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$contact) {
            return $this->error($response, 'Contact not found', 404);
        }
        
        $contactData = [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => $contact->full_name,
            'salutation' => $contact->salutation,
            'email1' => $contact->email1,
            'phone_work' => $contact->phone_work,
            'phone_mobile' => $contact->phone_mobile,
            'title' => $contact->title,
            'department' => $contact->department,
            'account_id' => $contact->account_id,
            'account_name' => $contact->account?->name,
            'primary_address_street' => $contact->primary_address_street,
            'primary_address_city' => $contact->primary_address_city,
            'primary_address_state' => $contact->primary_address_state,
            'primary_address_postalcode' => $contact->primary_address_postalcode,
            'primary_address_country' => $contact->primary_address_country,
            'lead_source' => $contact->lead_source,
            'lifetime_value' => $contact->lifetime_value,
            'engagement_score' => $contact->engagement_score,
            'last_activity_date' => $contact->last_activity_date?->toIso8601String(),
            'date_entered' => $contact->date_entered?->toIso8601String(),
            'date_modified' => $contact->date_modified?->toIso8601String(),
            'assigned_user_id' => $contact->assigned_user_id,
            'assigned_user_name' => $contact->assignedUser?->full_name,
            'description' => $contact->description
        ];
        
        // Get all activities
        $activities = [];
        
        // Website visits
        $visitors = ActivityTrackingVisitor::where('contact_id', $id)
            ->orderBy('last_visit', 'DESC')
            ->get();
        
        foreach ($visitors as $visitor) {
            $activities[] = [
                'type' => 'website_visit',
                'date' => $visitor->last_visit,
                'data' => [
                    'totalVisits' => $visitor->total_visits,
                    'totalPageViews' => $visitor->total_page_views,
                    'totalTimeSpent' => $visitor->total_time_spent,
                    'engagementScore' => $visitor->engagement_score,
                    'browser' => $visitor->browser,
                    'device' => $visitor->device_type
                ]
            ];
        }
        
        // AI chat conversations
        $chats = ChatConversation::where('contact_id', $id)
            ->withCount('messages')
            ->orderBy('started_at', 'DESC')
            ->get();
        
        foreach ($chats as $chat) {
            $activities[] = [
                'type' => 'ai_chat',
                'date' => $chat->started_at,
                'data' => [
                    'conversationId' => $chat->id,
                    'status' => $chat->status,
                    'messageCount' => $chat->messages_count,
                    'duration' => $chat->ended_at ? $chat->ended_at->diffInSeconds($chat->started_at) : null
                ]
            ];
        }
        
        // Calls
        $calls = Call::with('assignedUser')
            ->whereHas('contacts', function ($q) use ($id) {
                $q->where('contact_id', $id);
            })
            ->where('deleted', 0)
            ->orderBy('date_start', 'DESC')
            ->get();
        
        foreach ($calls as $call) {
            $activities[] = [
                'type' => 'call',
                'date' => $call->date_start,
                'data' => [
                    'id' => $call->id,
                    'name' => $call->name,
                    'status' => $call->status,
                    'direction' => $call->direction,
                    'duration' => ($call->duration_hours * 60) + $call->duration_minutes,
                    'assignedTo' => $call->assignedUser?->user_name,
                    'description' => $call->description
                ]
            ];
        }
        
        // Meetings
        $meetings = Meeting::with('assignedUser')
            ->whereHas('contacts', function ($q) use ($id) {
                $q->where('contact_id', $id);
            })
            ->where('deleted', 0)
            ->orderBy('date_start', 'DESC')
            ->get();
        
        foreach ($meetings as $meeting) {
            $activities[] = [
                'type' => 'meeting',
                'date' => $meeting->date_start,
                'data' => [
                    'id' => $meeting->id,
                    'name' => $meeting->name,
                    'status' => $meeting->status,
                    'location' => $meeting->location,
                    'duration' => ($meeting->duration_hours * 60) + $meeting->duration_minutes,
                    'assignedTo' => $meeting->assignedUser?->user_name,
                    'description' => $meeting->description
                ]
            ];
        }
        
        // Notes
        $notes = Note::with('createdBy')
            ->where('parent_type', 'Contacts')
            ->where('parent_id', $id)
            ->where('deleted', 0)
            ->orderBy('date_entered', 'DESC')
            ->get();
        
        foreach ($notes as $note) {
            $activities[] = [
                'type' => 'note',
                'date' => $note->date_entered,
                'data' => [
                    'id' => $note->id,
                    'name' => $note->name,
                    'createdBy' => $note->createdBy?->user_name,
                    'description' => $note->description
                ]
            ];
        }
        
        // Sort activities by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Get support tickets
        $tickets = Case::where('deleted', 0)
            ->where('account_id', $contact->account_id)
            ->orderBy('date_entered', 'DESC')
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'name' => $ticket->name,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'type' => $ticket->type,
                    'dateEntered' => $ticket->date_entered?->toIso8601String()
                ];
            });
        
        // Get health score
        $score = null;
        if ($contact->account_id) {
            $healthScore = CustomerHealthScore::where('contact_id', $id)
                ->orderBy('calculated_at', 'DESC')
                ->first();
            
            if ($healthScore) {
                $score = [
                    'type' => 'health',
                    'value' => $healthScore->score,
                    'riskLevel' => $healthScore->risk_level,
                    'factors' => $healthScore->factors,
                    'calculatedAt' => $healthScore->calculated_at?->toIso8601String()
                ];
            }
        }
        
        // Get related opportunities
        $opportunities = Opportunity::whereHas('contacts', function ($q) use ($id) {
                $q->where('contact_id', $id);
            })
            ->where('deleted', 0)
            ->orderBy('date_entered', 'DESC')
            ->get()
            ->map(function ($opp) {
                return [
                    'id' => $opp->id,
                    'name' => $opp->name,
                    'amount' => $opp->amount,
                    'salesStage' => $opp->sales_stage,
                    'probability' => $opp->probability,
                    'closeDate' => $opp->date_closed
                ];
            });
        
        return $this->json($response, [
            'data' => [
                'contact' => $contactData,
                'activities' => $activities,
                'tickets' => $tickets,
                'opportunities' => $opportunities,
                'score' => $score,
                'stats' => [
                    'totalActivities' => count($activities),
                    'openTickets' => $tickets->filter(function($t) { 
                        return !in_array($t['status'], ['Closed', 'Resolved']); 
                    })->count(),
                    'totalOpportunities' => $opportunities->count()
                ]
            ]
        ]);
    }
    
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $contact = Contact::with(['assignedUser'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$contact) {
            return $this->error($response, 'Contact not found', 404);
        }
        
        return $this->json($response, [
            'data' => $this->formatContact($contact)
        ]);
    }
    
    public function create(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'last_name' => 'required|string|max:100',
            'first_name' => 'sometimes|string|max:100',
            'salutation' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:100',
            'department' => 'sometimes|string|max:100',
            'email1' => 'sometimes|email|max:100',
            'phone_work' => 'sometimes|string|max:100',
            'phone_mobile' => 'sometimes|string|max:100',
            'phone_home' => 'sometimes|string|max:100',
            'phone_other' => 'sometimes|string|max:100',
            'phone_fax' => 'sometimes|string|max:100',
            'primary_address_street' => 'sometimes|string|max:150',
            'primary_address_city' => 'sometimes|string|max:100',
            'primary_address_state' => 'sometimes|string|max:100',
            'primary_address_postalcode' => 'sometimes|string|max:20',
            'primary_address_country' => 'sometimes|string|max:255',
            'account_id' => 'sometimes|string|size:36|exists:accounts,id',
            'account_name' => 'sometimes|string|max:255',
            'lead_source' => 'sometimes|string|max:100',
            'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
            'description' => 'sometimes|string|max:65535'
        ]);
        
        DB::beginTransaction();
        
        try {
            $contact = Contact::create($data);
            
            DB::commit();
            
            $contact->load(['assignedUser']);
            
            return $this->json($response, [
                'data' => $this->formatContact($contact),
                'message' => 'Contact created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create contact: ' . $e->getMessage(), 500);
        }
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $contact = Contact::where('deleted', 0)->find($id);
        
        if (!$contact) {
            return $this->error($response, 'Contact not found', 404);
        }
        
        $data = $this->validate($request, [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'salutation' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:100',
            'department' => 'sometimes|string|max:100',
            'email1' => 'sometimes|email|max:100',
            'phone_work' => 'sometimes|string|max:100',
            'phone_mobile' => 'sometimes|string|max:100',
            'phone_home' => 'sometimes|string|max:100',
            'phone_other' => 'sometimes|string|max:100',
            'phone_fax' => 'sometimes|string|max:100',
            'primary_address_street' => 'sometimes|string|max:150',
            'primary_address_city' => 'sometimes|string|max:100',
            'primary_address_state' => 'sometimes|string|max:100',
            'primary_address_postalcode' => 'sometimes|string|max:20',
            'primary_address_country' => 'sometimes|string|max:255',
            'account_id' => 'sometimes|string|size:36|exists:accounts,id',
            'account_name' => 'sometimes|string|max:255',
            'lead_source' => 'sometimes|string|max:100',
            'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
            'description' => 'sometimes|string|max:65535'
        ]);
        
        DB::beginTransaction();
        
        try {
            $contact->update($data);
            
            DB::commit();
            
            $contact->load(['assignedUser']);
            
            return $this->json($response, [
                'data' => $this->formatContact($contact),
                'message' => 'Contact updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update contact: ' . $e->getMessage(), 500);
        }
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $contact = Contact::where('deleted', 0)->find($id);
        
        if (!$contact) {
            return $this->error($response, 'Contact not found', 404);
        }
        
        $contact->deleted = 1;
        $contact->save();
        
        return $this->json($response, [
            'message' => 'Contact deleted successfully'
        ]);
    }
    
    public function getHealthScore(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $contact = Contact::where('deleted', 0)->find($id);
        
        if (!$contact) {
            return $this->error($response, 'Contact not found', 404);
        }
        
        $healthScore = CustomerHealthScore::where('contact_id', $id)
            ->orderBy('calculated_at', 'DESC')
            ->first();
        
        if (!$healthScore) {
            return $this->json($response, [
                'data' => [
                    'score' => null,
                    'message' => 'No health score available for this contact'
                ]
            ]);
        }
        
        return $this->json($response, [
            'data' => [
                'score' => $healthScore->score,
                'riskLevel' => $healthScore->risk_level,
                'factors' => $healthScore->factors,
                'calculatedAt' => $healthScore->calculated_at?->toIso8601String(),
                'nextReviewDate' => $healthScore->next_review_date?->toIso8601String()
            ]
        ]);
    }
    
    private function formatContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => $contact->full_name,
            'salutation' => $contact->salutation,
            'title' => $contact->title,
            'department' => $contact->department,
            'email1' => $contact->email1,
            'email2' => $contact->email2,
            'phone_work' => $contact->phone_work,
            'phone_mobile' => $contact->phone_mobile,
            'phone_home' => $contact->phone_home,
            'phone_other' => $contact->phone_other,
            'phone_fax' => $contact->phone_fax,
            'primary_address_street' => $contact->primary_address_street,
            'primary_address_city' => $contact->primary_address_city,
            'primary_address_state' => $contact->primary_address_state,
            'primary_address_postalcode' => $contact->primary_address_postalcode,
            'primary_address_country' => $contact->primary_address_country,
            'alt_address_street' => $contact->alt_address_street,
            'alt_address_city' => $contact->alt_address_city,
            'alt_address_state' => $contact->alt_address_state,
            'alt_address_postalcode' => $contact->alt_address_postalcode,
            'alt_address_country' => $contact->alt_address_country,
            'account_id' => $contact->account_id,
            'account_name' => $contact->account_name,
            'lead_source' => $contact->lead_source,
            'birthdate' => $contact->birthdate,
            'description' => $contact->description,
            'assigned_user_id' => $contact->assigned_user_id,
            'assigned_user_name' => $contact->assignedUser?->full_name,
            'date_entered' => $contact->date_entered?->toIso8601String(),
            'date_modified' => $contact->date_modified?->toIso8601String(),
            'modified_user_id' => $contact->modified_user_id,
            'created_by' => $contact->created_by,
            'do_not_call' => $contact->do_not_call,
            'deleted' => $contact->deleted
        ];
    }
}