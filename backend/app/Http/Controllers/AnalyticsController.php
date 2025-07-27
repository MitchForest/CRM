<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Cases;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Note;
use App\Models\User;
use App\Services\CRM\AnalyticsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class AnalyticsController extends Controller
{
    private AnalyticsService $analyticsService;
    
    public function __construct()
    {
        parent::__construct();
        $this->analyticsService = new AnalyticsService();
    }
    
    /**
     * Get sales analytics
     * GET /api/crm/analytics/sales
     */
    public function salesAnalytics(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->validate($request, [
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $data['date_from'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
            $dateTo = $data['date_to'] ?? (new \DateTime())->format('Y-m-d');
            
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
                
            // Get lost opportunities
            $lostOpportunities = Opportunity::where('deleted', 0)
                ->where('sales_stage', 'Closed Lost')
                ->whereBetween('date_closed', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(*) as count, SUM(amount) as total_value')
                ->first();
            
            // Get pipeline value by stage
            $pipelineByStage = Opportunity::where('deleted', 0)
                ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                ->select('sales_stage', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as value'))
                ->groupBy('sales_stage')
                ->get()
                ->map(function ($item) {
                    return [
                        'stage' => $item->sales_stage,
                        'count' => $item->count,
                        'value' => (float)$item->value
                    ];
                });
            
            // Calculate metrics
            $winRate = $opportunities->count > 0 ? round(($wonOpportunities->count / $opportunities->count) * 100, 2) : 0;
            $avgDealSize = $wonOpportunities->count > 0 ? round($wonOpportunities->total_value / $wonOpportunities->count, 2) : 0;
            
            // Get sales velocity (deals closed per day)
            $daysInPeriod = (new \DateTime($dateTo))->diff(new \DateTime($dateFrom))->days + 1;
            $salesVelocity = round($wonOpportunities->count / $daysInPeriod, 2);
            
            return $this->json($response, [
                'summary' => [
                    'total_opportunities' => $opportunities->count,
                    'total_value' => (float)$opportunities->total_value,
                    'won_deals' => $wonOpportunities->count,
                    'won_value' => (float)$wonOpportunities->total_value,
                    'lost_deals' => $lostOpportunities->count,
                    'lost_value' => (float)$lostOpportunities->total_value,
                    'win_rate' => $winRate,
                    'average_deal_size' => $avgDealSize,
                    'sales_velocity' => $salesVelocity
                ],
                'pipeline' => $pipelineByStage,
                'trends' => $this->getSalesTrends($dateFrom, $dateTo),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch sales analytics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get lead analytics
     * GET /api/crm/analytics/leads
     */
    public function leadAnalytics(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->validate($request, [
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $data['date_from'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
            $dateTo = $data['date_to'] ?? (new \DateTime())->format('Y-m-d');
            
            // Get leads by status
            $leadsByStatus = Lead::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => $item->status ?: 'Unknown',
                        'count' => $item->count
                    ];
                });
            
            // Get leads by source
            $leadsBySource = Lead::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->select('lead_source', DB::raw('COUNT(*) as count'))
                ->groupBy('lead_source')
                ->orderBy('count', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'source' => $item->lead_source ?: 'Unknown',
                        'count' => $item->count
                    ];
                });
            
            // Get conversion metrics
            $totalLeads = Lead::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $convertedLeads = Lead::where('deleted', 0)
                ->where('status', 'converted')
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $qualifiedLeads = Lead::where('deleted', 0)
                ->where('status', 'Qualified')
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
            
            $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0;
            $qualificationRate = $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 2) : 0;
            
            // Get lead response time
            $avgResponseTime = $this->getAverageLeadResponseTime($dateFrom, $dateTo);
            
            return $this->json($response, [
                'summary' => [
                    'total_leads' => $totalLeads,
                    'converted_leads' => $convertedLeads,
                    'qualified_leads' => $qualifiedLeads,
                    'conversion_rate' => $conversionRate,
                    'qualification_rate' => $qualificationRate,
                    'average_response_time' => $avgResponseTime
                ],
                'by_status' => $leadsByStatus,
                'by_source' => $leadsBySource,
                'trends' => $this->getLeadTrends($dateFrom, $dateTo),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch lead analytics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get activity analytics
     * GET /api/crm/analytics/activities
     */
    public function activityAnalytics(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->validate($request, [
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $data['date_from'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
            $dateTo = $data['date_to'] ?? (new \DateTime())->format('Y-m-d');
            
            // Get activities by type
            $callsCount = Call::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $meetingsCount = Meeting::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $tasksCount = Task::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $notesCount = Note::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $totalActivities = $callsCount + $meetingsCount + $tasksCount + $notesCount;
            
            // Get completed vs pending tasks
            $completedTasks = Task::where('deleted', 0)
                ->where('status', 'Completed')
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $pendingTasks = Task::where('deleted', 0)
                ->whereIn('status', ['Not Started', 'In Progress', 'Pending Input'])
                ->count(); // Current pending, not date-filtered
            
            // Get overdue activities
            $overdueTasks = Task::where('deleted', 0)
                ->whereIn('status', ['Not Started', 'In Progress'])
                ->where('date_due', '<', (new \DateTime())->format('Y-m-d'))
                ->count();
                
            $overdueCalls = Call::where('deleted', 0)
                ->where('status', 'Planned')
                ->where('date_start', '<', (new \DateTime())->format('Y-m-d'))
                ->count();
                
            $overdueMeetings = Meeting::where('deleted', 0)
                ->where('status', 'Planned')
                ->where('date_start', '<', (new \DateTime())->format('Y-m-d'))
                ->count();
            
            // Get activities by user
            $activitiesByUser = User::join('tasks', 'tasks.assigned_user_id', '=', 'users.id')
                ->where('tasks.deleted', 0)
                ->whereBetween('tasks.date_entered', [$dateFrom, $dateTo])
                ->select('users.id', 'users.first_name', 'users.last_name')
                ->selectRaw('COUNT(tasks.id) as activity_count')
                ->groupBy('users.id', 'users.first_name', 'users.last_name')
                ->orderBy('activity_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'activity_count' => $user->activity_count
                    ];
                });
            
            return $this->json($response, [
                'summary' => [
                    'total_activities' => $totalActivities,
                    'calls' => $callsCount,
                    'meetings' => $meetingsCount,
                    'tasks' => $tasksCount,
                    'notes' => $notesCount
                ],
                'task_metrics' => [
                    'completed' => $completedTasks,
                    'pending' => $pendingTasks,
                    'completion_rate' => $tasksCount > 0 ? round(($completedTasks / $tasksCount) * 100, 2) : 0
                ],
                'overdue' => [
                    'tasks' => $overdueTasks,
                    'calls' => $overdueCalls,
                    'meetings' => $overdueMeetings,
                    'total' => $overdueTasks + $overdueCalls + $overdueMeetings
                ],
                'by_user' => $activitiesByUser,
                'trends' => $this->getActivityTrends($dateFrom, $dateTo),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch activity analytics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get conversion analytics
     * GET /api/crm/analytics/conversion
     */
    public function conversionAnalytics(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->validate($request, [
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $data['date_from'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
            $dateTo = $data['date_to'] ?? (new \DateTime())->format('Y-m-d');
            
            // Lead to opportunity conversion
            $totalLeads = Lead::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $convertedToOpp = Lead::where('deleted', 0)
                ->where('status', 'converted')
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
            
            // Opportunity to won conversion
            $totalOpps = Opportunity::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->count();
                
            $wonOpps = Opportunity::where('deleted', 0)
                ->where('sales_stage', 'Closed Won')
                ->whereBetween('date_closed', [$dateFrom, $dateTo])
                ->count();
            
            // Build conversion funnel
            $funnel = [
                ['stage' => 'Leads', 'count' => $totalLeads, 'percentage' => 100],
                ['stage' => 'Qualified Leads', 'count' => Lead::where('deleted', 0)->where('status', 'Qualified')->whereBetween('date_entered', [$dateFrom, $dateTo])->count(), 'percentage' => 0],
                ['stage' => 'Opportunities', 'count' => $convertedToOpp, 'percentage' => 0],
                ['stage' => 'Proposals', 'count' => Opportunity::where('deleted', 0)->where('sales_stage', 'Proposal/Price Quote')->whereBetween('date_entered', [$dateFrom, $dateTo])->count(), 'percentage' => 0],
                ['stage' => 'Won Deals', 'count' => $wonOpps, 'percentage' => 0]
            ];
            
            // Calculate percentages
            for ($i = 1; $i < count($funnel); $i++) {
                $funnel[$i]['percentage'] = $totalLeads > 0 ? round(($funnel[$i]['count'] / $totalLeads) * 100, 2) : 0;
            }
            
            // Calculate conversion times
            $avgLeadToOppTime = $this->getAverageConversionTime('lead_to_opp', $dateFrom, $dateTo);
            $avgOppToWonTime = $this->getAverageConversionTime('opp_to_won', $dateFrom, $dateTo);
            
            // Conversion rates by source
            $conversionBySource = $this->getConversionRatesBySource($dateFrom, $dateTo);
            
            return $this->json($response, [
                'funnel' => $funnel,
                'rates' => [
                    'lead_to_opportunity' => $totalLeads > 0 ? round(($convertedToOpp / $totalLeads) * 100, 2) : 0,
                    'opportunity_to_won' => $totalOpps > 0 ? round(($wonOpps / $totalOpps) * 100, 2) : 0,
                    'overall_conversion' => $totalLeads > 0 ? round(($wonOpps / $totalLeads) * 100, 2) : 0
                ],
                'timing' => [
                    'average_lead_to_opportunity' => $avgLeadToOppTime,
                    'average_opportunity_to_won' => $avgOppToWonTime,
                    'total_sales_cycle' => $avgLeadToOppTime + $avgOppToWonTime
                ],
                'by_source' => $conversionBySource,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch conversion analytics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get team performance
     * GET /api/crm/analytics/team-performance
     */
    public function teamPerformance(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->validate($request, [
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            $dateFrom = $data['date_from'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
            $dateTo = $data['date_to'] ?? (new \DateTime())->format('Y-m-d');
            
            // Get team members with their metrics
            $teamMetrics = User::where('deleted', 0)
                ->where('status', 'Active')
                ->get()
                ->map(function ($user) use ($dateFrom, $dateTo) {
                    // Leads assigned
                    $leadsAssigned = Lead::where('deleted', 0)
                        ->where('assigned_user_id', $user->id)
                        ->whereBetween('date_entered', [$dateFrom, $dateTo])
                        ->count();
                    
                    // Opportunities
                    $oppsData = Opportunity::where('deleted', 0)
                        ->where('assigned_user_id', $user->id)
                        ->whereBetween('date_entered', [$dateFrom, $dateTo])
                        ->selectRaw('COUNT(*) as count, SUM(amount) as total_value')
                        ->first();
                    
                    // Won deals
                    $wonData = Opportunity::where('deleted', 0)
                        ->where('assigned_user_id', $user->id)
                        ->where('sales_stage', 'Closed Won')
                        ->whereBetween('date_closed', [$dateFrom, $dateTo])
                        ->selectRaw('COUNT(*) as count, SUM(amount) as total_value')
                        ->first();
                    
                    // Activities
                    $activitiesCount = 0;
                    $activitiesCount += Call::where('deleted', 0)->where('assigned_user_id', $user->id)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
                    $activitiesCount += Meeting::where('deleted', 0)->where('assigned_user_id', $user->id)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
                    $activitiesCount += Task::where('deleted', 0)->where('assigned_user_id', $user->id)->whereBetween('date_entered', [$dateFrom, $dateTo])->count();
                    
                    // Cases resolved
                    $casesResolved = Cases::where('deleted', 0)
                        ->where('assigned_user_id', $user->id)
                        ->where('status', 'Closed')
                        ->whereBetween('date_modified', [$dateFrom, $dateTo])
                        ->count();
                    
                    return [
                        'id' => $user->id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'metrics' => [
                            'leads_assigned' => $leadsAssigned,
                            'opportunities' => $oppsData->count,
                            'opportunity_value' => (float)$oppsData->total_value,
                            'won_deals' => $wonData->count,
                            'won_value' => (float)$wonData->total_value,
                            'win_rate' => $oppsData->count > 0 ? round(($wonData->count / $oppsData->count) * 100, 2) : 0,
                            'activities' => $activitiesCount,
                            'cases_resolved' => $casesResolved
                        ]
                    ];
                })
                ->filter(function ($user) {
                    // Only include users with activity
                    return array_sum(array_values($user['metrics'])) > 0;
                })
                ->values();
            
            // Calculate team totals
            $teamTotals = [
                'total_leads' => $teamMetrics->sum('metrics.leadsAssigned'),
                'total_opportunities' => $teamMetrics->sum('metrics.opportunities'),
                'total_opportunity_value' => $teamMetrics->sum('metrics.opportunityValue'),
                'total_won_deals' => $teamMetrics->sum('metrics.wonDeals'),
                'total_won_value' => $teamMetrics->sum('metrics.wonValue'),
                'total_activities' => $teamMetrics->sum('metrics.activities'),
                'total_cases_resolved' => $teamMetrics->sum('metrics.casesResolved')
            ];
            
            // Get top performers
            $topByRevenue = $teamMetrics->sortByDesc('metrics.wonValue')->take(5)->values();
            $topByDeals = $teamMetrics->sortByDesc('metrics.wonDeals')->take(5)->values();
            $topByActivities = $teamMetrics->sortByDesc('metrics.activities')->take(5)->values();
            
            return $this->json($response, [
                'team' => $teamMetrics,
                'totals' => $teamTotals,
                'top_performers' => [
                    'by_revenue' => $topByRevenue,
                    'by_deals' => $topByDeals,
                    'by_activities' => $topByActivities
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch team performance: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Helper: Get sales trends
     */
    private function getSalesTrends(string $dateFrom, string $dateTo): array
    {
        $interval = $this->getDateInterval($dateFrom, $dateTo);
        
        return Opportunity::where('deleted', 0)
            ->where('sales_stage', 'Closed Won')
            ->whereBetween('date_closed', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(date_closed, '{$interval['format']}') as period")
            ->selectRaw('COUNT(*) as deals')
            ->selectRaw('SUM(amount) as revenue')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'deals' => $item->deals,
                    'revenue' => (float)$item->revenue
                ];
            })
            ->toArray();
    }
    
    /**
     * Helper: Get lead trends
     */
    private function getLeadTrends(string $dateFrom, string $dateTo): array
    {
        $interval = $this->getDateInterval($dateFrom, $dateTo);
        
        return Lead::where('deleted', 0)
            ->whereBetween('date_entered', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(date_entered, '{$interval['format']}') as period")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'count' => $item->count
                ];
            })
            ->toArray();
    }
    
    /**
     * Helper: Get activity trends
     */
    private function getActivityTrends(string $dateFrom, string $dateTo): array
    {
        $interval = $this->getDateInterval($dateFrom, $dateTo);
        $trends = [];
        
        // Aggregate from all activity tables
        $tables = [
            'calls' => Call::class,
            'meetings' => Meeting::class,
            'tasks' => Task::class,
            'notes' => Note::class
        ];
        
        foreach ($tables as $type => $model) {
            $data = $model::where('deleted', 0)
                ->whereBetween('date_entered', [$dateFrom, $dateTo])
                ->selectRaw("DATE_FORMAT(date_entered, '{$interval['format']}') as period")
                ->selectRaw('COUNT(*) as count')
                ->groupBy('period')
                ->orderBy('period')
                ->get();
                
            foreach ($data as $item) {
                if (!isset($trends[$item->period])) {
                    $trends[$item->period] = [
                        'period' => $item->period,
                        'calls' => 0,
                        'meetings' => 0,
                        'tasks' => 0,
                        'notes' => 0,
                        'total' => 0
                    ];
                }
                $trends[$item->period][$type] = $item->count;
                $trends[$item->period]['total'] += $item->count;
            }
        }
        
        return array_values($trends);
    }
    
    /**
     * Helper: Get average lead response time
     */
    private function getAverageLeadResponseTime(string $dateFrom, string $dateTo): float
    {
        // This would typically calculate the time between lead creation and first activity
        // For now, return a placeholder
        return 2.5; // hours
    }
    
    /**
     * Helper: Get average conversion time
     */
    private function getAverageConversionTime(string $type, string $dateFrom, string $dateTo): float
    {
        if ($type === 'lead_to_opp') {
            $avgDays = Lead::where('deleted', 0)
                ->where('status', 'converted')
                ->whereBetween('date_modified', [$dateFrom, $dateTo])
                ->selectRaw('AVG(DATEDIFF(date_modified, date_entered)) as avg_days')
                ->first()
                ->avg_days ?? 0;
        } else {
            $avgDays = Opportunity::where('deleted', 0)
                ->where('sales_stage', 'Closed Won')
                ->whereBetween('date_closed', [$dateFrom, $dateTo])
                ->selectRaw('AVG(DATEDIFF(date_closed, date_entered)) as avg_days')
                ->first()
                ->avg_days ?? 0;
        }
        
        return round($avgDays, 1);
    }
    
    /**
     * Helper: Get conversion rates by source
     */
    private function getConversionRatesBySource(string $dateFrom, string $dateTo): array
    {
        $sources = Lead::where('deleted', 0)
            ->whereBetween('date_entered', [$dateFrom, $dateTo])
            ->select('lead_source')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('lead_source')
            ->having('total', '>', 5) // Only sources with meaningful data
            ->get()
            ->map(function ($item) {
                return [
                    'source' => $item->lead_source ?: 'Unknown',
                    'leads' => $item->total,
                    'converted' => $item->converted,
                    'conversionRate' => round(($item->converted / $item->total) * 100, 2)
                ];
            })
            ->sortByDesc('conversionRate')
            ->values()
            ->toArray();
            
        return $sources;
    }
    
    /**
     * Helper: Determine appropriate date interval for grouping
     */
    private function getDateInterval(string $dateFrom, string $dateTo): array
    {
        $days = (new \DateTime($dateTo))->diff(new \DateTime($dateFrom))->days;
        
        if ($days <= 7) {
            return ['interval' => 'day', 'format' => '%Y-%m-%d'];
        } elseif ($days <= 31) {
            return ['interval' => 'day', 'format' => '%Y-%m-%d'];
        } elseif ($days <= 90) {
            return ['interval' => 'week', 'format' => '%Y-%u'];
        } else {
            return ['interval' => 'month', 'format' => '%Y-%m'];
        }
    }
}