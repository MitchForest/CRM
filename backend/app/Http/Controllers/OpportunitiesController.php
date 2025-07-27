<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Contact;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class OpportunitiesController extends Controller
{
    /**
     * List opportunities
     * 
     * @OA\Get(
     *     path="/api/crm/opportunities",
     *     tags={"Opportunities"},
     *     summary="List all opportunities",
     *     description="Returns a paginated list of opportunities with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering opportunities",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="stage",
     *         in="query",
     *         description="Filter by sales stage",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="assigned_user_id",
     *         in="query",
     *         description="Filter by assigned user",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Opportunities retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Opportunity")
     *             ),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            // Remove 'contacts' from eager loading for now
            $query = Opportunity::with(['assignedUser', 'account'])
                ->where('deleted', 0);
            
            // Apply filters
            $queryParams = $request->getQueryParams();
        
        if (isset($queryParams['search'])) {
            $search = $queryParams['search'];
            $query->where('name', 'like', "%$search%");
        }
        
        if (isset($queryParams['stage'])) {
            $query->where('sales_stage', $queryParams['stage']);
        }
        
        if (isset($queryParams['assigned_user_id'])) {
            $query->where('assigned_user_id', $queryParams['assigned_user_id']);
        }
        
        // Sorting
        $sortBy = $queryParams['sort_by'] ?? 'date_entered';
        $sortOrder = $queryParams['sort_order'] ?? 'DESC';
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $page = $queryParams['page'] ?? 1;
        $limit = $queryParams['limit'] ?? 20;
        $opportunities = $query->paginate($limit, ['*'], 'page', $page);
        
        // Format response
        $data = $opportunities->map(function ($opp) {
            return $this->formatOpportunity($opp);
        });
        
        return $this->json($response, [
            'data' => $data,
            'pagination' => [
                'page' => $opportunities->currentPage(),
                'limit' => $opportunities->perPage(),
                'total' => $opportunities->total(),
                'total_pages' => $opportunities->lastPage()
            ]
        ]);
        } catch (\Exception $e) {
            return $this->error($response, 'Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    public function show(Request $request, Response $response, array $args): Response
    {
        $opportunity = Opportunity::with(['assignedUser', 'account', 'contacts'])
            ->where('deleted', 0)
            ->find($args['id']);
        
        if (!$opportunity) {
            return $this->error($response, 'Opportunity not found', 404);
        }
        
        $data = $this->formatOpportunity($opportunity);
        
        // Add contacts detail
        $data['contacts'] = $opportunity->contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'email1' => $contact->email1,
                'phone_work' => $contact->phone_work
            ];
        });
        
        return $this->json($response, ['data' => $data]);
    }
    
    public function create(Request $request, Response $response, array $args): Response
    {
        $validatedData = $this->validate($request, [
            'name' => 'required|string|max:50',
            'date_closed' => 'required|date',
            'amount' => 'sometimes|numeric|min:0',
            'amount_usdollar' => 'sometimes|numeric|min:0',
            'sales_stage' => 'sometimes|string|max:255',
            'probability' => 'sometimes|integer|min:0|max:100',
            'opportunity_type' => 'sometimes|string|max:255',
            'lead_source' => 'sometimes|string|max:50',
            'account_id' => 'sometimes|string|size:36|exists:accounts,id',
            'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
            'contactIds' => 'sometimes|array',
            'contactIds.*' => 'string|exists:contacts,id'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Direct assignment with exact field names
            $opportunity = Opportunity::create($validatedData);
            
            // Add contacts if provided
            $body = $request->getParsedBody();
            if (isset($body['contactIds'])) {
                $opportunity->contacts()->attach($body['contactIds']);
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $opportunity->id],
                'message' => 'Opportunity created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create opportunity: ' . $e->getMessage(), 500);
        }
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $opportunity = Opportunity::where('deleted', 0)->find($args['id']);
        
        if (!$opportunity) {
            return $this->error($response, 'Opportunity not found', 404);
        }
        
        $validatedData = $this->validate($request, [
            'name' => 'sometimes|string|max:50',
            'date_closed' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'amount_usdollar' => 'sometimes|numeric|min:0',
            'sales_stage' => 'sometimes|string|max:255',
            'probability' => 'sometimes|integer|min:0|max:100',
            'opportunity_type' => 'sometimes|string|max:255',
            'lead_source' => 'sometimes|string|max:50',
            'next_step' => 'sometimes|string|max:100',
            'description' => 'sometimes|string|max:65535',
            'account_id' => 'sometimes|string|size:36|exists:accounts,id',
            'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
            'campaign_id' => 'sometimes|string|size:36|nullable',
            'contactIds' => 'sometimes|array',
            'contactIds.*' => 'string|exists:contacts,id'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Direct update with exact field names
            $opportunity->update($validatedData);
            
            // Update contacts if provided
            $body = $request->getParsedBody();
            if (isset($body['contactIds'])) {
                $opportunity->contacts()->sync($body['contactIds']);
            }
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $opportunity->id],
                'message' => 'Opportunity updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update opportunity: ' . $e->getMessage(), 500);
        }
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $opportunity = Opportunity::where('deleted', 0)->find($args['id']);
        
        if (!$opportunity) {
            return $this->error($response, 'Opportunity not found', 404);
        }
        
        $opportunity->deleted = 1;
        $opportunity->save();
        
        return $this->json($response, [
            'message' => 'Opportunity deleted successfully'
        ]);
    }
    
    public function updateStage(Request $request, Response $response, array $args): Response
    {
        $opportunity = Opportunity::where('deleted', 0)->find($args['id']);
        
        if (!$opportunity) {
            return $this->error($response, 'Opportunity not found', 404);
        }
        
        $validatedData = $this->validate($request, [
            'sales_stage' => 'required|string|max:255'
        ]);
        
        $opportunity->sales_stage = $validatedData['sales_stage'];
        $opportunity->save();
        
        return $this->json($response, [
            'message' => 'Stage updated successfully',
            'data' => ['id' => $opportunity->id, 'sales_stage' => $opportunity->sales_stage]
        ]);
    }
    
    public function pipeline(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        
        // Get opportunities grouped by sales stage
        $pipeline = Opportunity::where('deleted', 0)
            ->selectRaw('sales_stage, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('sales_stage')
            ->get();
        
        // Define pipeline stages in order
        $stageOrder = [
            'Prospecting',
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Id. Decision Makers',
            'Perception Analysis',
            'Proposal/Price Quote',
            'Negotiation/Review',
            'Closed Won',
            'Closed Lost'
        ];
        
        // Build pipeline data
        $pipelineData = [];
        foreach ($stageOrder as $stage) {
            $stageData = $pipeline->firstWhere('sales_stage', $stage);
            $pipelineData[] = [
                'stage' => $stage,
                'count' => $stageData ? (int)$stageData->count : 0,
                'totalAmount' => $stageData ? (float)$stageData->total_amount : 0
            ];
        }
        
        // Get opportunities for each stage
        $opportunities = [];
        if (isset($params['includeOpportunities']) && $params['includeOpportunities'] === 'true') {
            foreach ($stageOrder as $stage) {
                $opps = Opportunity::with(['assignedUser', 'account'])
                    ->where('deleted', 0)
                    ->where('sales_stage', $stage)
                    ->orderBy('amount', 'DESC')
                    ->limit(10)
                    ->get();
                
                $opportunities[$stage] = $opps->map(function ($opp) {
                    return $this->formatOpportunity($opp);
                });
            }
        }
        
        return $this->json($response, [
            'data' => [
                'pipeline' => $pipelineData,
                'opportunities' => $opportunities,
                'summary' => [
                    'totalOpportunities' => $pipeline->sum('count'),
                    'totalValue' => $pipeline->sum('total_amount'),
                    'averageDealSize' => $pipeline->sum('count') > 0 ? 
                        $pipeline->sum('total_amount') / $pipeline->sum('count') : 0
                ]
            ]
        ]);
    }
    
    private function formatOpportunity(Opportunity $opportunity): array
    {
        return [
            'id' => $opportunity->id,
            'name' => $opportunity->name,
            'amount' => (float)$opportunity->amount,
            'currency_id' => $opportunity->currency_id ?? 'USD',
            'sales_stage' => $opportunity->sales_stage,
            'probability' => (int)$opportunity->probability,
            'date_closed' => $opportunity->date_closed,
            'opportunity_type' => $opportunity->opportunity_type,
            'lead_source' => $opportunity->lead_source,
            'next_step' => $opportunity->next_step,
            'description' => $opportunity->description,
            'account_id' => $opportunity->account_id,
            'account_name' => $opportunity->account ? $opportunity->account->name : null,
            'assigned_user_id' => $opportunity->assigned_user_id,
            'date_entered' => $opportunity->date_entered?->toIso8601String(),
            'date_modified' => $opportunity->date_modified?->toIso8601String(),
            'created_by' => $opportunity->created_by,
            'modified_user_id' => $opportunity->modified_user_id
        ];
    }
}