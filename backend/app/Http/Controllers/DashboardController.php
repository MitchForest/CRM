<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\Task;
use App\Services\CRM\DashboardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class DashboardController extends Controller
{
    private DashboardService $dashboardService;
    
    public function __construct()
    {
        parent::__construct();
        // Note: In Slim, services should be injected via container or instantiated manually
        // For now, we'll instantiate in methods that need it
    }
    
    /**
     * Get dashboard metrics
     * 
     * @OA\Get(
     *     path="/api/crm/dashboard/metrics",
     *     tags={"Dashboard"},
     *     summary="Get dashboard metrics",
     *     description="Returns key metrics for the dashboard including leads, accounts, and pipeline value",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_leads", type="integer", example=150),
     *                 @OA\Property(property="total_accounts", type="integer", example=45),
     *                 @OA\Property(property="new_leads_today", type="integer", example=5),
     *                 @OA\Property(property="pipeline_value", type="number", format="float", example=250000.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getMetrics(Request $request, Response $response, array $args): Response
    {
        try {
            $today = date('Y-m-d');
            $metrics = [
                'total_leads' => Lead::where('deleted', 0)->count(),
                'total_accounts' => Account::where('deleted', 0)->count(),
                'new_leads_today' => Lead::where('deleted', 0)
                    ->whereDate('date_entered', $today)
                    ->count(),
                'pipeline_value' => Opportunity::where('deleted', 0)
                    ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                    ->sum('amount')
            ];
            
            return $this->json($response, ['data' => $metrics]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve dashboard metrics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get pipeline data
     * 
     * @OA\Get(
     *     path="/api/crm/dashboard/pipeline",
     *     tags={"Dashboard"},
     *     summary="Get sales pipeline data",
     *     description="Returns opportunities grouped by sales stage with counts and values",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pipeline data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="stage", type="string", example="Qualified"),
     *                     @OA\Property(property="count", type="integer", example=10),
     *                     @OA\Property(property="value", type="number", format="float", example=50000.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getPipelineData(Request $request, Response $response, array $args): Response
    {
        try {
            // Map actual stages to simplified stages
            $stageMapping = [
                'Qualification' => 'Qualified',
                'Needs Analysis' => 'Qualified',
                'Value Proposition' => 'Qualified',
                'Proposal/Price Quote' => 'Proposal',
                'Negotiation/Review' => 'Negotiation',
                'Closed Won' => 'Won',
                'Closed Lost' => 'Lost'
            ];
            
            // Get opportunities grouped by stage
            $opportunities = Opportunity::where('deleted', 0)
                ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                ->selectRaw('sales_stage, COUNT(*) as count, SUM(amount) as value')
                ->groupBy('sales_stage')
                ->get();
            
            // Group by simplified stages
            $simplifiedData = [
                'Qualified' => ['count' => 0, 'value' => 0],
                'Proposal' => ['count' => 0, 'value' => 0],
                'Negotiation' => ['count' => 0, 'value' => 0]
            ];
            
            foreach ($opportunities as $opp) {
                $mappedStage = $stageMapping[$opp->sales_stage] ?? null;
                if ($mappedStage && isset($simplifiedData[$mappedStage])) {
                    $simplifiedData[$mappedStage]['count'] += $opp->count;
                    $simplifiedData[$mappedStage]['value'] += $opp->value;
                }
            }
            
            // Convert to array format
            $pipelineData = [];
            foreach ($simplifiedData as $stage => $data) {
                $pipelineData[] = [
                    'stage' => $stage,
                    'count' => $data['count'],
                    'value' => $data['value']
                ];
            }
            
            return $this->json($response, ['data' => $pipelineData]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve pipeline data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get activity metrics
     * GET /api/crm/dashboard/activities
     */
    public function getActivityMetrics(Request $request, Response $response, array $args): Response
    {
        try {
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            
            $metrics = [
                'calls_today' => Call::where('deleted', 0)
                    ->whereDate('date_start', $today)
                    ->count(),
                'meetings_today' => Meeting::where('deleted', 0)
                    ->whereDate('date_start', $today)
                    ->count(),
                'tasks_overdue' => Task::where('deleted', 0)
                    ->where('status', '!=', 'Completed')
                    ->where('date_due', '<', $now)
                    ->count()
            ];
            
            // Get upcoming activities
            $upcomingCalls = Call::where('deleted', 0)
                ->where('date_start', '>=', $now)
                ->where('status', '!=', 'Held')
                ->with(['parentAccount', 'parentContact'])
                ->orderBy('date_start', 'asc')
                ->limit(5)
                ->get()
                ->map(function ($call) {
                    $relatedTo = 'Unknown';
                    if ($call->parent_type === 'Accounts' && $call->parentAccount) {
                        $relatedTo = $call->parentAccount->name;
                    } elseif ($call->parent_type === 'Contacts' && $call->parentContact) {
                        $relatedTo = $call->parentContact->full_name;
                    }
                    
                    return [
                        'id' => $call->id,
                        'name' => $call->name,
                        'type' => 'Call',
                        'date_start' => $call->date_start,
                        'related_to' => $relatedTo
                    ];
                });
            
            $metrics['upcoming_activities'] = $upcomingCalls;
            
            return $this->json($response, ['data' => $metrics]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve activity metrics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recent leads
     * GET /api/crm/dashboard/leads/recent
     */
    public function getRecentLeads(Request $request, Response $response, array $args): Response
    {
        try {
            $leads = Lead::where('deleted', 0)
                ->orderBy('date_entered', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($lead) {
                    return [
                        'id' => $lead->id,
                        'name' => trim($lead->first_name . ' ' . $lead->last_name),
                        'email1' => $lead->email1,
                        'phone_work' => $lead->phone_work,
                        'account_name' => $lead->account_name,
                        'lead_source' => $lead->lead_source,
                        'status' => $lead->status,
                        'date_entered' => $lead->date_entered
                    ];
                });
            
            return $this->json($response, ['data' => $leads]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve recent leads: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get case metrics
     * GET /api/crm/dashboard/cases
     */
    public function getCaseMetrics(Request $request, Response $response, array $args): Response
    {
        try {
            $startOfMonth = date('Y-m-01 00:00:00');
            
            $metrics = [
                'open_cases' => SupportCase::where('deleted', 0)
                    ->where('status', 'like', 'Open_%')
                    ->count(),
                'closed_this_month' => SupportCase::where('deleted', 0)
                    ->where('status', 'like', 'Closed_%')
                    ->where('date_modified', '>=', $startOfMonth)
                    ->count(),
                'high_priority' => SupportCase::where('deleted', 0)
                    ->where('priority', 'High')
                    ->where('status', 'like', 'Open_%')
                    ->count()
            ];
            
            // Get average resolution time
            $avgResolution = SupportCase::where('deleted', 0)
                ->where('status', 'like', 'Closed_%')
                ->selectRaw('AVG(DATEDIFF(date_modified, date_entered)) as avg_days')
                ->first();
            
            $metrics['avg_resolution_days'] = round($avgResolution->avg_days ?? 0, 1);
            
            return $this->json($response, ['data' => $metrics]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve case metrics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get full dashboard data
     * GET /api/crm/dashboard
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = $request->getAttribute('user_id');
            $params = $request->getQueryParams();
            $period = $params['period'] ?? 'week'; // week, month, quarter
            
            // Note: DashboardService needs to be instantiated or injected
            // For now, returning basic data
            $data = [
                'period' => $period,
                'userId' => $userId,
                'message' => 'Dashboard service integration pending'
            ];
            
            return $this->json($response, ['data' => $data]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
        }
    }
}