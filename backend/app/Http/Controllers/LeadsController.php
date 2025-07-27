<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadScore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

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
                    'scored_at' => new \DateTime()
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
                    'scored_at' => new \DateTime()
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
        
        // TODO: Implement timeline functionality
        // For now, return empty timeline
        return $this->json($response, [
            'data' => [
                'lead_id' => $id,
                'timeline' => []
            ]
        ]);
    }
    
    public function convert(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::where('deleted', 0)->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        if ($lead->converted) {
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
            // Mark lead as converted
            $lead->converted = 1;
            $lead->save();
            
            // TODO: Implement actual conversion logic
            // For now, just return success
            
            DB::commit();
            
            return $this->json($response, [
                'message' => 'Lead converted successfully',
                'data' => [
                    'lead_id' => $id,
                    'converted' => true
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to convert lead: ' . $e->getMessage(), 500);
        }
    }
    
    private function formatLead(Lead $lead): array
    {
        $latestScore = $lead->scores()->latest('scored_at')->first();
        
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
            'ai_score_date' => $lead->ai_score_date?->toIso8601String() ?? $latestScore?->scored_at?->toIso8601String(),
            'ai_insights' => $lead->ai_insights ?? $latestScore?->factors,
            'ai_next_best_action' => $lead->ai_next_best_action,
            // Conversion fields
            'converted' => $lead->converted,
            'converted_contact_id' => $lead->converted_contact_id,
            'converted_account_id' => $lead->converted_account_id,
            'converted_opportunity_id' => $lead->converted_opportunity_id,
            // Computed fields
            'full_name' => $lead->full_name,
            'latest_score' => $lead->latest_score
        ];
    }
}