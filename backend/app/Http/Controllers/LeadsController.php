<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadScore;
use App\Models\Contact;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Leads",
 *     description="Lead management endpoints"
 * )
 */
class LeadsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/crm/leads",
     *     tags={"Leads"},
     *     summary="List leads",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new", "contacted", "qualified", "unqualified", "converted"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[assigned_user_id]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Lead")
     *             ),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
     * )
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $query = Lead::with(['assignedUser', 'scores'])
            ->where('deleted', 0);
        
        // Apply filters
        if (isset($params['filter'])) {
            $filters = $params['filter'];
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['assigned_user_id'])) {
                $query->where('assigned_user_id', $filters['assigned_user_id']);
            }
            
            if (isset($filters['lead_source'])) {
                $query->where('lead_source', $filters['lead_source']);
            }
            
            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                      ->orWhere('last_name', 'like', "%$search%")
                      ->orWhere('email1', 'like', "%$search%")
                      ->orWhere('account_name', 'like', "%$search%");
                });
            }
        }
        
        // Apply sorting
        $orderBy = $params['orderBy'] ?? 'date_entered';
        $orderDir = $params['orderDir'] ?? 'DESC';
        $query->orderBy($orderBy, $orderDir);
        
        // Get pagination parameters
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        
        // Execute query with pagination
        $leads = $query->paginate($limit, ['*'], 'page', $page);
        
        // Format response
        $data = $leads->map(function ($lead) {
            return $this->formatLead($lead);
        });
        
        return $this->json($response, [
            'data' => $data,
            'pagination' => [
                'page' => $leads->currentPage(),
                'limit' => $leads->perPage(),
                'total' => $leads->total(),
                'total_pages' => $leads->lastPage()
            ]
        ]);
    }
    
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::with(['assignedUser', 'scores'])
            ->where('deleted', 0)
            ->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        return $this->json($response, [
            'data' => $this->formatLead($lead)
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
            'account_name' => 'sometimes|string|max:255',
            'website' => 'sometimes|url|max:255',
            'primary_address_street' => 'sometimes|string|max:150',
            'primary_address_city' => 'sometimes|string|max:100',
            'primary_address_state' => 'sometimes|string|max:100',
            'primary_address_postalcode' => 'sometimes|string|max:20',
            'primary_address_country' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:100',
            'status_description' => 'sometimes|string|max:65535',
            'lead_source' => 'sometimes|string|max:100',
            'lead_source_description' => 'sometimes|string|max:65535',
            'description' => 'sometimes|string|max:65535',
            'assigned_user_id' => 'sometimes|string|size:36|exists:users,id'
        ]);
        
        $parsedBody = $request->getParsedBody();
        
        DB::beginTransaction();
        
        try {
            // Direct assignment - no field mapping needed
            $lead = Lead::create($data);
            
            // Handle AI score if provided
            if (isset($parsedBody['ai_score'])) {
                LeadScore::create([
                    'lead_id' => $lead->id,
                    'score' => $parsedBody['ai_score'],
                    'factors' => $parsedBody['ai_insights'] ?? [],
                    'date_scored' => new \DateTime()
                ]);
            }
            
            DB::commit();
            
            $lead->load(['assignedUser', 'scores']);
            
            return $this->json($response, [
                'data' => $this->formatLead($lead),
                'message' => 'Lead created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create lead: ' . $e->getMessage(), 500);
        }
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::where('deleted', 0)->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
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
            'account_name' => 'sometimes|string|max:255',
            'website' => 'sometimes|url|max:255',
            'primary_address_street' => 'sometimes|string|max:150',
            'primary_address_city' => 'sometimes|string|max:100',
            'primary_address_state' => 'sometimes|string|max:100',
            'primary_address_postalcode' => 'sometimes|string|max:20',
            'primary_address_country' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:100',
            'status_description' => 'sometimes|string|max:65535',
            'lead_source' => 'sometimes|string|max:100',
            'lead_source_description' => 'sometimes|string|max:65535',
            'description' => 'sometimes|string|max:65535',
            'assigned_user_id' => 'sometimes|string|size:36|exists:users,id'
        ]);
        
        $parsedBody = $request->getParsedBody();
        
        DB::beginTransaction();
        
        try {
            // Direct update - no field mapping needed
            $lead->update($data);
            
            // Handle AI score if provided
            if (isset($parsedBody['ai_score'])) {
                LeadScore::create([
                    'lead_id' => $lead->id,
                    'score' => $parsedBody['ai_score'],
                    'factors' => $parsedBody['ai_insights'] ?? [],
                    'date_scored' => new \DateTime()
                ]);
            }
            
            DB::commit();
            
            $lead->load(['assignedUser', 'scores']);
            
            return $this->json($response, [
                'data' => $this->formatLead($lead),
                'message' => 'Lead updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update lead: ' . $e->getMessage(), 500);
        }
    }
    
    public function patch(Request $request, Response $response, array $args): Response
    {
        // PATCH uses same logic as update
        return $this->update($request, $response, $args);
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::where('deleted', 0)->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        $lead->deleted = 1;
        $lead->save();
        
        return $this->json($response, [
            'message' => 'Lead deleted successfully'
        ]);
    }
    
    public function getTimeline(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::where('deleted', 0)->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        $params = $request->getQueryParams();
        $limit = intval($params['limit'] ?? 50);
        $offset = intval($params['offset'] ?? 0);
        
        $timeline = [];
        
        // 1. Get web activity (page views, sessions)
        $webActivity = $this->getWebActivity($lead, $limit, $offset);
        $timeline = array_merge($timeline, $webActivity);
        
        // 2. Get form submissions
        $formSubmissions = $this->getFormSubmissions($lead, $limit, $offset);
        $timeline = array_merge($timeline, $formSubmissions);
        
        // 3. Get chat conversations
        $chatActivity = $this->getChatActivity($lead, $limit, $offset);
        $timeline = array_merge($timeline, $chatActivity);
        
        // 4. Get CRM activities (calls, meetings, notes, tasks)
        $crmActivity = $this->getCRMActivity($lead, $limit, $offset);
        $timeline = array_merge($timeline, $crmActivity);
        
        // 5. Get lead score changes
        $scoreChanges = $this->getScoreChanges($lead, $limit, $offset);
        $timeline = array_merge($timeline, $scoreChanges);
        
        // Sort by timestamp descending
        usort($timeline, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply pagination
        $timeline = array_slice($timeline, $offset, $limit);
        
        return $this->json($response, [
            'data' => [
                'lead_id' => $id,
                'timeline' => $timeline,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => count($timeline) === $limit
                ]
            ]
        ]);
    }
    
    private function getWebActivity(Lead $lead, int $limit, int $offset): array
    {
        $activities = [];
        
        // Get visitor IDs associated with this lead
        $visitors = \App\Models\ActivityTrackingVisitor::where('lead_id', $lead->id)->get();
        
        foreach ($visitors as $visitor) {
            // Get sessions
            $sessions = \App\Models\ActivityTrackingSession::where('visitor_id', $visitor->visitor_id)
                ->orderBy('start_time', 'desc')
                ->limit($limit)
                ->get();
                
            foreach ($sessions as $session) {
                $activities[] = [
                    'id' => $session->id,
                    'type' => 'web_session',
                    'title' => 'Website Visit',
                    'description' => "Visited website for {$session->duration} seconds, viewed {$session->page_count} pages",
                    'timestamp' => $session->start_time->toIso8601String(),
                    'icon' => 'globe',
                    'data' => [
                        'session_id' => $session->session_id,
                        'duration' => $session->duration,
                        'page_count' => $session->page_count,
                        'landing_page' => $session->landing_page,
                        'exit_page' => $session->exit_page
                    ]
                ];
            }
            
            // Get recent page views
            $pageViews = \App\Models\ActivityTrackingPageView::where('visitor_id', $visitor->visitor_id)
                ->orderBy('date_entered', 'desc')
                ->limit(20)
                ->get();
                
            foreach ($pageViews as $pageView) {
                $activities[] = [
                    'id' => $pageView->id,
                    'type' => 'page_view',
                    'title' => 'Page View',
                    'description' => $pageView->page_title ?: $pageView->page_url,
                    'timestamp' => $pageView->date_entered->toIso8601String(),
                    'icon' => 'file',
                    'data' => [
                        'page_url' => $pageView->page_url,
                        'page_title' => $pageView->page_title,
                        'time_on_page' => $pageView->time_on_page
                    ]
                ];
            }
        }
        
        return $activities;
    }
    
    private function getFormSubmissions(Lead $lead, int $limit, int $offset): array
    {
        $activities = [];
        
        $submissions = \App\Models\FormSubmission::where('lead_id', $lead->id)
            ->where('deleted', 0)
            ->orderBy('date_entered', 'desc')
            ->limit($limit)
            ->get();
            
        foreach ($submissions as $submission) {
            $activities[] = [
                'id' => $submission->id,
                'type' => 'form_submission',
                'title' => 'Form Submission',
                'description' => $submission->form ? "Submitted form: {$submission->form->name}" : 'Submitted a form',
                'timestamp' => $submission->date_entered->toIso8601String(),
                'icon' => 'form',
                'data' => [
                    'form_id' => $submission->form_id,
                    'form_name' => $submission->form->name ?? null,
                    'submission_data' => $submission->data
                ]
            ];
        }
        
        return $activities;
    }
    
    private function getChatActivity(Lead $lead, int $limit, int $offset): array
    {
        $activities = [];
        
        $conversations = \App\Models\ChatConversation::where('lead_id', $lead->id)
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();
            
        foreach ($conversations as $conversation) {
            $activities[] = [
                'id' => $conversation->id,
                'type' => 'chat_conversation',
                'title' => 'Chat Conversation',
                'description' => "Had a chat conversation with {$conversation->messages()->count()} messages",
                'timestamp' => $conversation->started_at->toIso8601String(),
                'icon' => 'message-circle',
                'data' => [
                    'conversation_id' => $conversation->id,
                    'message_count' => $conversation->messages()->count(),
                    'duration' => $conversation->ended_at ? 
                        $conversation->ended_at->diffInMinutes($conversation->started_at) : null
                ]
            ];
        }
        
        return $activities;
    }
    
    private function getCRMActivity(Lead $lead, int $limit, int $offset): array
    {
        $activities = [];
        
        // Calls
        $calls = $lead->calls()->orderBy('date_start', 'desc')->limit($limit)->get();
        foreach ($calls as $call) {
            $activities[] = [
                'id' => $call->id,
                'type' => 'call',
                'title' => 'Phone Call',
                'description' => $call->name,
                'timestamp' => $call->date_start->toIso8601String(),
                'icon' => 'phone',
                'data' => [
                    'duration' => ($call->duration_hours * 60) + $call->duration_minutes,
                    'status' => $call->status,
                    'direction' => $call->direction
                ]
            ];
        }
        
        // Meetings
        $meetings = $lead->meetings()->orderBy('date_start', 'desc')->limit($limit)->get();
        foreach ($meetings as $meeting) {
            $activities[] = [
                'id' => $meeting->id,
                'type' => 'meeting',
                'title' => 'Meeting',
                'description' => $meeting->name,
                'timestamp' => $meeting->date_start->toIso8601String(),
                'icon' => 'calendar',
                'data' => [
                    'duration' => ($meeting->duration_hours * 60) + $meeting->duration_minutes,
                    'status' => $meeting->status,
                    'location' => $meeting->location
                ]
            ];
        }
        
        // Notes
        $notes = $lead->notes()->orderBy('date_entered', 'desc')->limit($limit)->get();
        foreach ($notes as $note) {
            $activities[] = [
                'id' => $note->id,
                'type' => 'note',
                'title' => 'Note Added',
                'description' => substr($note->description, 0, 100) . '...',
                'timestamp' => $note->date_entered->toIso8601String(),
                'icon' => 'file-text',
                'data' => [
                    'name' => $note->name,
                    'created_by' => $note->created_by
                ]
            ];
        }
        
        // Tasks
        $tasks = $lead->tasks()->orderBy('date_due', 'desc')->limit($limit)->get();
        foreach ($tasks as $task) {
            $activities[] = [
                'id' => $task->id,
                'type' => 'task',
                'title' => 'Task',
                'description' => $task->name,
                'timestamp' => $task->date_due ? $task->date_due->toIso8601String() : $task->date_entered->toIso8601String(),
                'icon' => 'check-square',
                'data' => [
                    'status' => $task->status,
                    'priority' => $task->priority
                ]
            ];
        }
        
        return $activities;
    }
    
    private function getScoreChanges(Lead $lead, int $limit, int $offset): array
    {
        $activities = [];
        
        $scores = $lead->scores()->orderBy('date_scored', 'desc')->limit($limit)->get();
        
        foreach ($scores as $score) {
            $activities[] = [
                'id' => $score->id,
                'type' => 'score_change',
                'title' => 'Lead Score Updated',
                'description' => "Score changed to {$score->score}",
                'timestamp' => $score->date_scored->toIso8601String(),
                'icon' => 'trending-up',
                'data' => [
                    'score' => $score->score,
                    'factors' => $score->factors,
                    'scoring_model' => $score->scoring_model
                ]
            ];
        }
        
        return $activities;
    }
    
    public function convert(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::where('deleted', 0)->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        if ($lead->status === 'converted') {
            return $this->error($response, 'Lead is already converted', 400);
        }
        
        $data = $this->validate($request, [
            'create_contact' => 'sometimes|boolean',
            'create_account' => 'sometimes|boolean',
            'create_opportunity' => 'sometimes|boolean',
            'opportunity_name' => 'required_if:create_opportunity,true|string|max:255',
            'opportunity_amount' => 'sometimes|numeric|min:0'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Create contact from lead
            $contact = Contact::create([
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email1' => $lead->email1,
                'phone_work' => $lead->phone_work,
                'phone_mobile' => $lead->phone_mobile,
                'primary_address_street' => $lead->primary_address_street,
                'primary_address_city' => $lead->primary_address_city,
                'primary_address_state' => $lead->primary_address_state,
                'primary_address_postalcode' => $lead->primary_address_postalcode,
                'primary_address_country' => $lead->primary_address_country,
                'description' => $lead->description,
                'lead_source' => $lead->lead_source,
                'assigned_user_id' => $lead->assigned_user_id
            ]);
            
            // Mark lead as converted
            $lead->status = 'converted';
            $lead->save();
            
            // Log conversion activity
            $this->logActivity($lead->id, 'lead_converted', [
                'contact_id' => $contact->id,
                'converted_at' => now()->toIso8601String()
            ]);
            
            DB::commit();
            
            return $this->json($response, [
                'message' => 'Lead converted successfully',
                'data' => [
                    'lead_id' => $id,
                    'contact_id' => $contact->id
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to convert lead: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Capture lead from public form submission
     * POST /api/public/capture/lead
     * 
     * This endpoint is used by embeddable forms and marketing pages
     * No authentication required
     */
    public function capturePublicLead(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'sometimes|string|max:100',
            'company' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|max:65535',
            'form_source' => 'sometimes|string|max:100',
            'visitor_id' => 'sometimes|string|max:36',
            'session_id' => 'sometimes|string|max:36',
            'utm_source' => 'sometimes|string|max:100',
            'utm_medium' => 'sometimes|string|max:100',
            'utm_campaign' => 'sometimes|string|max:100'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Check if lead already exists by email
            $existingLead = Lead::where('email1', $data['email'])
                ->where('deleted', 0)
                ->first();
            
            if ($existingLead) {
                // Update existing lead with new information
                $updateData = [
                    'phone_work' => $data['phone'] ?? $existingLead->phone_work,
                    'account_name' => $data['company'] ?? $existingLead->account_name,
                    'lead_source' => $data['form_source'] ?? 'Contact Form',
                    'status' => $existingLead->status === 'converted' ? $existingLead->status : 'contacted'
                ];
                
                if (!empty($data['message'])) {
                    $updateData['description'] = $existingLead->description . "\n\n[" . date('Y-m-d H:i:s') . "] New submission:\n" . $data['message'];
                }
                
                $existingLead->update($updateData);
                $lead = $existingLead;
                
                // Connect visitor tracking to lead if visitor_id provided and not already connected
                if (!empty($data['visitor_id'])) {
                    $this->connectVisitorToLead($data['visitor_id'], $lead->id);
                }
                
                // Log activity
                $this->logActivity($lead->id, 'form_submission', [
                    'form_source' => $data['form_source'] ?? 'Contact Form',
                    'message' => 'Lead submitted another form'
                ]);
            } else {
                // Create new lead
                $leadData = [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email1' => $data['email'],
                    'phone_work' => $data['phone'] ?? null,
                    'account_name' => $data['company'] ?? null,
                    'description' => $data['message'] ?? null,
                    'lead_source' => $data['form_source'] ?? 'Contact Form',
                    'status' => 'new',
                    'assigned_user_id' => $this->getNextAvailableUser() // Round-robin assignment
                ];
                
                $lead = Lead::create($leadData);
                
                // Connect visitor tracking to lead if visitor_id provided
                if (!empty($data['visitor_id'])) {
                    $this->connectVisitorToLead($data['visitor_id'], $lead->id);
                }
                
                // Log activity
                $this->logActivity($lead->id, 'form_submission', [
                    'form_source' => $data['form_source'] ?? 'Contact Form',
                    'message' => 'New lead created from form submission'
                ]);
            }
            
            // Trigger AI scoring in background (non-blocking)
            $this->triggerAIScoring($lead->id);
            
            DB::commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Thank you for your submission. We\'ll be in touch soon!',
                'data' => [
                    'lead_id' => $lead->id,
                    'is_new' => !$existingLead
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Public lead capture error: ' . $e->getMessage());
            
            return $this->error($response, 'Failed to process your submission. Please try again.', 500);
        }
    }
    
    /**
     * Handle demo request from marketing site
     * POST /api/public/demo-request
     * 
     * Creates lead and schedules demo meeting
     */
    public function requestDemo(Request $request, Response $response, array $args): Response
    {
        // Debug: Log raw request data
        $rawData = $request->getParsedBody();
        error_log('Demo request raw data: ' . json_encode($rawData));
        error_log('Demo request data keys: ' . json_encode(array_keys($rawData ?? [])));
        
        try {
            $data = $this->validate($request, [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:100',
                'phone' => 'sometimes|string|max:100',
                'company' => 'required|string|max:255',
                'company_size' => 'sometimes|string|max:50',
                'demo_date' => 'required|date',
                'demo_time' => 'required|string',
                'timezone' => 'sometimes|string|max:50',
                'message' => 'sometimes|string|max:65535',
                'visitor_id' => 'sometimes|string|max:36',
                'session_id' => 'sometimes|string|max:36'
            ]);
        } catch (\Exception $e) {
            error_log('Validation exception: ' . $e->getMessage());
            error_log('Validation exception trace: ' . $e->getTraceAsString());
            throw $e;
        }
        
        DB::beginTransaction();
        
        try {
            // First create/update lead using the public lead capture logic
            $leadData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'],
                'message' => "Demo Request\nCompany Size: " . ($data['company_size'] ?? 'Not specified') . "\n" . ($data['message'] ?? ''),
                'form_source' => 'Demo Request'
            ];
            
            // Only add visitor_id and session_id if they exist
            if (isset($data['visitor_id'])) {
                $leadData['visitor_id'] = $data['visitor_id'];
            }
            if (isset($data['session_id'])) {
                $leadData['session_id'] = $data['session_id'];
            }
            
            $leadRequest = $request->withParsedBody($leadData);
            
            $leadResponse = $this->capturePublicLead($leadRequest, $response, $args);
            $leadResult = json_decode((string)$leadResponse->getBody(), true);
            
            if (!$leadResult['success']) {
                throw new \Exception('Failed to create lead');
            }
            
            $leadId = $leadResult['data']['lead_id'];
            
            // Create demo meeting
            $meetingDateTime = new \DateTime($data['demo_date'] . ' ' . $data['demo_time']);
            $meetingEndTime = clone $meetingDateTime;
            $meetingEndTime->add(new \DateInterval('PT30M')); // 30 minute demo
            
            $meeting = \App\Models\Meeting::create([
                'name' => 'Demo with ' . $data['first_name'] . ' ' . $data['last_name'] . ' - ' . $data['company'],
                'date_start' => $meetingDateTime,
                'date_end' => $meetingEndTime,
                'duration_hours' => 0,
                'duration_minutes' => 30,
                'status' => 'Planned',
                'location' => 'Virtual (Link to be sent)',
                'description' => "Demo meeting scheduled\nCompany: " . $data['company'] . "\nCompany Size: " . ($data['company_size'] ?? 'Not specified') . "\nTimezone: " . ($data['timezone'] ?? 'Not specified'),
                'parent_type' => 'Leads',
                'parent_id' => $leadId,
                'assigned_user_id' => $this->getNextAvailableUser()
            ]);
            
            // Update lead status
            Lead::where('id', $leadId)->update(['status' => 'qualified']);
            
            // Log activity
            $this->logActivity($leadId, 'demo_scheduled', [
                'meeting_id' => $meeting->id,
                'scheduled_date' => $meetingDateTime->format('Y-m-d H:i:s')
            ]);
            
            DB::commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Demo scheduled successfully! We\'ll send you a calendar invite shortly.',
                'data' => [
                    'lead_id' => $leadId,
                    'meeting_id' => $meeting->id,
                    'scheduled_time' => $meetingDateTime->format('c')
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Demo request error: ' . $e->getMessage());
            
            return $this->error($response, 'Failed to schedule demo. Please try again or contact support.', 500);
        }
    }
    
    /**
     * Get next available user for round-robin assignment
     */
    private function getNextAvailableUser(): string
    {
        // For now, return admin user
        // In production, implement round-robin logic
        return '1';
    }
    
    /**
     * Log activity for lead
     */
    private function logActivity(string $leadId, string $type, array $data): void
    {
        // In production, create activity record
        // For now, just log
        error_log("Lead activity: {$leadId} - {$type} - " . json_encode($data));
    }
    
    /**
     * Trigger AI scoring for lead (non-blocking)
     */
    private function triggerAIScoring(string $leadId): void
    {
        // In production, queue job for AI scoring
        // For now, just log
        error_log("AI scoring triggered for lead: {$leadId}");
    }

    /**
     * Connect anonymous visitor to lead
     */
    private function connectVisitorToLead(string $visitorId, string $leadId): void
    {
        try {
            // Update visitor record to link to lead
            \App\Models\ActivityTrackingVisitor::where('visitor_id', $visitorId)
                ->update(['lead_id' => $leadId]);
            
            // Update all sessions for this visitor
            \App\Models\ActivityTrackingSession::where('visitor_id', $visitorId)
                ->update(['lead_id' => $leadId]);
                
            error_log("Connected visitor {$visitorId} to lead {$leadId}");
        } catch (\Exception $e) {
            // Don't fail the lead creation if visitor connection fails
            error_log("Failed to connect visitor to lead: " . $e->getMessage());
        }
    }

    private function formatLead(Lead $lead): array
    {
        $latestScore = $lead->scores()->latest('date_scored')->first();
        
        return [
            'id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'salutation' => $lead->salutation,
            'title' => $lead->title,
            'department' => $lead->department,
            'email1' => $lead->email1,
            'phone_work' => $lead->phone_work,
            'phone_mobile' => $lead->phone_mobile,
            'account_name' => $lead->account_name,
            'website' => $lead->website,
            'primary_address_street' => $lead->primary_address_street,
            'primary_address_city' => $lead->primary_address_city,
            'primary_address_state' => $lead->primary_address_state,
            'primary_address_postalcode' => $lead->primary_address_postalcode,
            'primary_address_country' => $lead->primary_address_country,
            'status' => $lead->status,
            'status_description' => $lead->status_description,
            'lead_source' => $lead->lead_source,
            'lead_source_description' => $lead->lead_source_description,
            'description' => $lead->description,
            'assigned_user_id' => $lead->assigned_user_id,
            'assigned_user_name' => $lead->assignedUser?->full_name,
            'date_entered' => $lead->date_entered?->toIso8601String(),
            'date_modified' => $lead->date_modified?->toIso8601String(),
            'modified_user_id' => $lead->modified_user_id,
            'created_by' => $lead->created_by,
            // AI fields
            'ai_score' => $lead->ai_score ?? $latestScore?->score,
            'ai_score_date' => $lead->ai_score_date?->toIso8601String() ?? $latestScore?->date_scored?->toIso8601String(),
            'ai_insights' => $lead->ai_insights ?? $latestScore?->factors,
            'ai_next_best_action' => $lead->ai_next_best_action,
            // Conversion status is tracked via status field
            // Computed fields
            'full_name' => $lead->full_name,
            'latest_score' => $lead->latest_score
        ];
    }
}