<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Case;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Note;
use App\Models\User;
use App\Services\CRM\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    private AnalyticsService $analyticsService;
    
    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }
    
    /**
     * Get analytics overview
     * GET /api/crm/analytics/overview
     */
    public function getOverview(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->input('date_to', now()->format('Y-m-d'));
            
            // Get leads metrics
            $leadsCount = Lead::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
            
            // Get opportunities metrics
            $opportunities = Opportunity::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(*) as count, SUM(amount) as total_value')
                ->first();
            
            // Get won opportunities
            $wonOpportunities = Opportunity::where('deleted', 0)
                ->where('sales_stage', 'Closed Won')
                ->whereBetween('date_closed', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(*) as count, SUM(amount) as total_value')
                ->first();
            
            // Get cases metrics
            $casesCount = Case::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
            
            $openCasesCount = Case::where('deleted', 0)
                ->whereIn('status', ['Open_New', 'Open_Assigned', 'Open_Pending'])
                ->count();
            
            // Get activities count
            $activitiesCount = 0;
            $activitiesCount += Call::where('deleted', 0)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
            $activitiesCount += Meeting::where('deleted', 0)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
            $activitiesCount += Task::where('deleted', 0)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
            $activitiesCount += Note::where('deleted', 0)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
            
            // Calculate metrics
            $conversionRate = $leadsCount > 0 ? round(($opportunities->count / $leadsCount) * 100, 2) : 0;
            $winRate = $opportunities->count > 0 ? round(($wonOpportunities->count / $opportunities->count) * 100, 2) : 0;
            
            // Get top performers
            $topPerformers = User::join('opportunities', 'opportunities.assigned_user_id', '=', 'users.id')
                ->where('opportunities.deleted', 0)
                ->where('opportunities.sales_stage', 'Closed Won')
                ->whereBetween('opportunities.date_closed', [$dateFrom, $dateTo])
                ->select('users.id', 'users.first_name', 'users.last_name')
                ->selectRaw('COUNT(opportunities.id) as won_deals, SUM(opportunities.amount) as total_value')
                ->groupBy('users.id', 'users.first_name', 'users.last_name')
                ->orderBy('total_value', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'wonDeals' => $user->won_deals,
                        'totalValue' => (float)$user->total_value
                    ];
                });
            
            return response()->json([
                'overview' => [
                    'leads' => [
                        'count' => $leadsCount,
                        'trend' => $this->calculateTrend('leads', $dateFrom, $dateTo)
                    ],
                    'opportunities' => [
                        'count' => $opportunities->count,
                        'totalValue' => (float)$opportunities->total_value,
                        'averageValue' => $opportunities->count > 0 ? round($opportunities->total_value / $opportunities->count, 2) : 0,
                        'trend' => $this->calculateTrend('opportunities', $dateFrom, $dateTo)
                    ],
                    'wonDeals' => [
                        'count' => $wonOpportunities->count,
                        'totalValue' => (float)$wonOpportunities->total_value,
                        'winRate' => $winRate
                    ],
                    'cases' => [
                        'total' => $casesCount,
                        'open' => $openCasesCount,
                        'resolved' => $casesCount - $openCasesCount
                    ],
                    'activities' => [
                        'count' => $activitiesCount,
                        'trend' => $this->calculateTrend('activities', $dateFrom, $dateTo)
                    ],
                    'metrics' => [
                        'conversionRate' => $conversionRate,
                        'winRate' => $winRate,
                        'averageDealSize' => $wonOpportunities->count > 0 ? round($wonOpportunities->total_value / $wonOpportunities->count, 2) : 0
                    ]
                ],
                'topPerformers' => $topPerformers,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch analytics overview',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get conversion funnel
     * GET /api/crm/analytics/funnel
     */
    public function getConversionFunnel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->input('date_to', now()->format('Y-m-d'));
            
            // Lead funnel
            $leadStages = ['New', 'Contacted', 'Qualified'];
            $leadFunnel = [];
            
            foreach ($leadStages as $stage) {
                $count = Lead::where('deleted', 0)
                    ->where('status', $stage)
                    ->whereBetween('date_entered', [$dateFrom, $dateTo])
                    ->count();
                
                $leadFunnel[] = [
                    'stage' => $stage,
                    'count' => $count
                ];
            }
            
            // Opportunity funnel
            $oppStages = ['Qualification', 'Proposal/Price Quote', 'Negotiation/Review', 'Closed Won', 'Closed Lost'];
            $oppFunnel = [];
            
            foreach ($oppStages as $stage) {
                $data = Opportunity::where('deleted', 0)
                    ->where('sales_stage', $stage)
                    ->whereBetween('date_entered', [$dateFrom, $dateTo])
                    ->selectRaw('COUNT(*) as count, SUM(amount) as value')
                    ->first();
                
                $oppFunnel[] = [
                    'stage' => $stage,
                    'count' => $data->count,
                    'value' => (float)$data->value
                ];
            }
            
            // Calculate conversion metrics
            $totalLeads = array_sum(array_column($leadFunnel, 'count'));
            $qualifiedLeads = $leadFunnel[2]['count'] ?? 0;
            $totalOpps = array_sum(array_column($oppFunnel, 'count'));
            $wonOpps = 0;
            $lostOpps = 0;
            
            foreach ($oppFunnel as $stage) {
                if ($stage['stage'] === 'Closed Won') $wonOpps = $stage['count'];
                if ($stage['stage'] === 'Closed Lost') $lostOpps = $stage['count'];
            }
            
            $leadToOppRate = $totalLeads > 0 ? round(($totalOpps / $totalLeads) * 100, 2) : 0;
            $oppToWonRate = $totalOpps > 0 ? round(($wonOpps / $totalOpps) * 100, 2) : 0;
            
            // Get conversion time metrics
            $avgLeadTime = $this->getAverageLeadTime($dateFrom, $dateTo);
            $avgSalesTime = $this->getAverageSalesTime($dateFrom, $dateTo);
            
            return response()->json([
                'leadFunnel' => $leadFunnel,
                'opportunityFunnel' => $oppFunnel,
                'conversionMetrics' => [
                    'leadToOpportunityRate' => $leadToOppRate,
                    'opportunityToWonRate' => $oppToWonRate,
                    'overallConversionRate' => $totalLeads > 0 ? round(($wonOpps / $totalLeads) * 100, 2) : 0,
                    'averageLeadTime' => $avgLeadTime,
                    'averageSalesTime' => $avgSalesTime
                ],
                'summary' => [
                    'totalLeads' => $totalLeads,
                    'qualifiedLeads' => $qualifiedLeads,
                    'totalOpportunities' => $totalOpps,
                    'wonDeals' => $wonOpps,
                    'lostDeals' => $lostOpps
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch conversion funnel',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get lead sources analytics
     * GET /api/crm/analytics/lead-sources
     */
    public function getLeadSources(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->input('date_to', now()->format('Y-m-d'));
            
            $sources = $this->analyticsService->getLeadSourcesPerformance($dateFrom, $dateTo);
            
            // Get trending sources (last 7 days)
            $trending = Lead::where('deleted', 0)
                ->where('date_entered', '>=', now()->subDays(7))
                ->select('lead_source', DB::raw('COUNT(*) as count'))
                ->groupBy('lead_source')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'source' => $item->lead_source ?: 'Unknown',
                        'recentLeads' => $item->count
                    ];
                });
            
            $totalLeads = array_sum(array_column($sources, 'leads'));
            $totalRevenue = array_sum(array_column($sources, 'revenue'));
            
            return response()->json([
                'sources' => $sources,
                'summary' => [
                    'totalSources' => count($sources),
                    'totalLeads' => $totalLeads,
                    'totalRevenue' => $totalRevenue,
                    'topSource' => !empty($sources) ? $sources[0]['source'] : null,
                    'averageLeadsPerSource' => count($sources) > 0 ? round($totalLeads / count($sources), 2) : 0
                ],
                'trending' => $trending,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch lead sources analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get custom report
     * GET /api/crm/analytics/report
     */
    public function getCustomReport(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:sales,marketing,support,activity',
            'group_by' => 'sometimes|string|in:user,team,source,stage,month,week',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from'
        ]);
        
        $type = $request->input('type');
        $groupBy = $request->input('group_by', 'month');
        $dateFrom = $request->input('date_from', now()->subMonths(3)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        
        try {
            $data = $this->analyticsService->generateCustomReport($type, $groupBy, $dateFrom, $dateTo);
            
            return response()->json([
                'data' => $data,
                'metadata' => [
                    'type' => $type,
                    'groupBy' => $groupBy,
                    'period' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate custom report',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calculate trend for a metric
     */
    private function calculateTrend(string $type, string $dateFrom, string $dateTo): array
    {
        $currentPeriod = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));
        $prevDateTo = Carbon::parse($dateFrom)->subDay();
        $prevDateFrom = $prevDateTo->copy()->subDays($currentPeriod);
        
        $currentCount = 0;
        $previousCount = 0;
        
        switch ($type) {
            case 'leads':
                $currentCount = Lead::where('deleted', 0)
                    ->whereBetween('date_entered', [$dateFrom, $dateTo])
                    ->count();
                $previousCount = Lead::where('deleted', 0)
                    ->whereBetween('date_entered', [$prevDateFrom, $prevDateTo])
                    ->count();
                break;
                
            case 'opportunities':
                $currentCount = Opportunity::where('deleted', 0)
                    ->whereBetween('date_entered', [$dateFrom, $dateTo])
                    ->count();
                $previousCount = Opportunity::where('deleted', 0)
                    ->whereBetween('date_entered', [$prevDateFrom, $prevDateTo])
                    ->count();
                break;
                
            case 'activities':
                $tables = [Call::class, Meeting::class, Task::class, Note::class];
                foreach ($tables as $model) {
                    $currentCount += $model::where('deleted', 0)
                        ->whereBetween('date_entered', [$dateFrom, $dateTo])
                        ->count();
                    $previousCount += $model::where('deleted', 0)
                        ->whereBetween('date_entered', [$prevDateFrom, $prevDateTo])
                        ->count();
                }
                break;
        }
        
        $trend = $previousCount > 0 ? round((($currentCount - $previousCount) / $previousCount) * 100, 2) : 0;
        
        return [
            'value' => $trend,
            'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat')
        ];
    }
    
    /**
     * Get average lead qualification time
     */
    private function getAverageLeadTime(string $dateFrom, string $dateTo): float
    {
        $avgDays = Lead::where('deleted', 0)
            ->where('status', 'Qualified')
            ->whereBetween('date_modified', [$dateFrom, $dateTo])
            ->selectRaw('AVG(DATEDIFF(date_modified, date_entered)) as avg_days')
            ->first()
            ->avg_days ?? 0;
        
        return round($avgDays, 1);
    }
    
    /**
     * Get average sales cycle time
     */
    private function getAverageSalesTime(string $dateFrom, string $dateTo): float
    {
        $avgDays = Opportunity::where('deleted', 0)
            ->where('sales_stage', 'Closed Won')
            ->whereBetween('date_closed', [$dateFrom, $dateTo])
            ->selectRaw('AVG(DATEDIFF(date_closed, date_entered)) as avg_days')
            ->first()
            ->avg_days ?? 0;
        
        return round($avgDays, 1);
    }
}