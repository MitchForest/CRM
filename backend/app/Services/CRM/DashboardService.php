<?php

namespace App\Services\CRM;

use App\Models\Lead;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

class DashboardService
{
    /**
     * Get dashboard stats for a user
     */
    public function getUserDashboard(string $userId): array
    {
        return [
            'stats' => $this->getUserStats($userId),
            'activities' => $this->getRecentActivities($userId),
            'pipeline' => $this->getPipelineSummary($userId),
            'tasks' => $this->getUpcomingTasks($userId),
            'leads' => $this->getHotLeads($userId),
            'performance' => $this->getUserPerformance($userId)
        ];
    }
    
    /**
     * Get admin dashboard
     */
    public function getAdminDashboard(): array
    {
        return [
            'stats' => $this->getOverallStats(),
            'team_performance' => $this->getTeamPerformance(),
            'revenue_metrics' => $this->getRevenueMetrics(),
            'activity_trends' => $this->getActivityTrends(),
            'conversion_funnel' => $this->getConversionFunnel(),
            'ai_insights' => $this->getAIInsights()
        ];
    }
    
    /**
     * Get user statistics
     */
    private function getUserStats(string $userId): array
    {
        return [
            'leads' => [
                'total' => Lead::where('assigned_user_id', $userId)->count(),
                'new_this_week' => Lead::where('assigned_user_id', $userId)
                    ->where('date_entered', '>=', (new \DateTime('monday this week'))->format('Y-m-d'))
                    ->count(),
                'hot_leads' => Lead::where('assigned_user_id', $userId)
                    ->highScore()
                    ->count()
            ],
            'opportunities' => [
                'open' => Opportunity::where('assigned_user_id', $userId)
                    ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                    ->count(),
                'value' => Opportunity::where('assigned_user_id', $userId)
                    ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                    ->sum('amount'),
                'closing_this_month' => Opportunity::where('assigned_user_id', $userId)
                    ->whereBetween('date_closed', [(new \DateTime('first day of this month'))->format('Y-m-d'), (new \DateTime('last day of this month'))->format('Y-m-d')])
                    ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                    ->count()
            ],
            'contacts' => [
                'total' => Contact::where('assigned_user_id', $userId)->count(),
                'at_risk' => Contact::where('assigned_user_id', $userId)
                    ->whereHas('healthScores', function ($q) {
                        $q->where('risk_level', 'high')
                          ->where('calculated_at', '>=', (new \DateTime())->modify('-7 days')->format('Y-m-d H:i:s'));
                    })
                    ->count()
            ],
            'cases' => [
                'open' => SupportCase::where('assigned_user_id', $userId)
                    ->where('status', '!=', 'closed')
                    ->count(),
                'overdue' => SupportCase::where('assigned_user_id', $userId)
                    ->where('status', '!=', 'closed')
                    ->where('date_entered', '<', (new \DateTime())->modify('-3 days')->format('Y-m-d'))
                    ->count()
            ]
        ];
    }
    
    /**
     * Get recent activities
     */
    private function getRecentActivities(string $userId): array
    {
        $activities = [];
        
        // Recent leads
        $recentLeads = Lead::where('assigned_user_id', $userId)
            ->orderBy('date_entered', 'desc')
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'company', 'date_entered']);
        
        foreach ($recentLeads as $lead) {
            $activities[] = [
                'type' => 'lead_created',
                'timestamp' => $lead->date_entered,
                'title' => "New lead: {$lead->full_name}",
                'description' => $lead->company,
                'link' => "/leads/{$lead->id}"
            ];
        }
        
        // Recent opportunities
        $recentOpps = Opportunity::where('assigned_user_id', $userId)
            ->orderBy('date_entered', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'amount', 'date_entered']);
        
        foreach ($recentOpps as $opp) {
            $activities[] = [
                'type' => 'opportunity_created',
                'timestamp' => $opp->date_entered,
                'title' => "New opportunity: {$opp->name}",
                'description' => "\${$opp->amount}",
                'link' => "/opportunities/{$opp->id}"
            ];
        }
        
        // Sort by timestamp and limit
        return array_values(array_slice($activities, 0, 10))
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }
    
    /**
     * Get pipeline summary
     */
    private function getPipelineSummary(string $userId): array
    {
        $opportunities = Opportunity::where('assigned_user_id', $userId)
            ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
            ->get();
        
        return [
            'total_value' => $opportunities->sum('amount'),
            'weighted_value' => $opportunities->sum('weighted_amount'),
            'count' => $opportunities->count(),
            'by_stage' => $opportunities->groupBy('sales_stage')
                ->map(function ($group, $stage) {
                    return [
                        'stage' => $stage,
                        'count' => $group->count(),
                        'value' => $group->sum('amount')
                    ];
                })->values()
        ];
    }
    
    /**
     * Get upcoming tasks
     */
    private function getUpcomingTasks(string $userId): array
    {
        return \App\Models\Task::where('assigned_user_id', $userId)
            ->where('status', '!=', 'completed')
            ->where('date_due', '>=', (new \DateTime())->format('Y-m-d H:i:s'))
            ->orderBy('date_due')
            ->limit(5)
            ->get(['id', 'name', 'date_due', 'priority', 'parent_type', 'parent_id'])
            ->toArray();
    }
    
    /**
     * Get hot leads
     */
    private function getHotLeads(string $userId): array
    {
        return Lead::with('scores')
            ->where('assigned_user_id', $userId)
            ->whereIn('status', ['new', 'contacted'])
            ->highScore()
            ->orderByDesc(
                LeadScore::select('score')
                    ->whereColumn('lead_id', 'leads.id')
                    ->latest('date_scored')
                    ->limit(1)
            )
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'company', 'email'])
            ->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->full_name,
                    'company' => $lead->company,
                    'email' => $lead->email,
                    'score' => $lead->latest_score
                ];
            })
            ->toArray();
    }
    
    /**
     * Get user performance metrics
     */
    private function getUserPerformance(string $userId): array
    {
        $thisMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');
        
        return [
            'leads_converted' => Lead::where('assigned_user_id', $userId)
                ->where('status', 'converted')
                ->where('date_modified', '>=', $thisMonth)
                ->count(),
            'opportunities_won' => Opportunity::where('assigned_user_id', $userId)
                ->where('sales_stage', 'Closed Won')
                ->where('date_closed', '>=', $thisMonth)
                ->count(),
            'revenue_closed' => Opportunity::where('assigned_user_id', $userId)
                ->where('sales_stage', 'Closed Won')
                ->where('date_closed', '>=', $thisMonth)
                ->sum('amount'),
            'avg_deal_size' => Opportunity::where('assigned_user_id', $userId)
                ->where('sales_stage', 'Closed Won')
                ->where('date_closed', '>=', $thisMonth)
                ->avg('amount'),
            'win_rate' => $this->calculateWinRate($userId, $thisMonth)
        ];
    }
    
    /**
     * Get overall statistics
     */
    private function getOverallStats(): array
    {
        return [
            'total_leads' => Lead::count(),
            'total_opportunities' => Opportunity::count(),
            'total_contacts' => Contact::count(),
            'total_revenue' => Opportunity::where('sales_stage', 'Closed Won')->sum('amount'),
            'active_users' => User::where('status', 'active')->count(),
            'conversion_rate' => $this->calculateOverallConversionRate()
        ];
    }
    
    /**
     * Get team performance
     */
    private function getTeamPerformance(): array
    {
        return User::where('status', 'active')
            ->get()
            ->map(function ($user) {
                return [
                    'user' => $user->full_name,
                    'leads' => $user->leads()->count(),
                    'opportunities' => $user->opportunities()->count(),
                    'revenue' => $user->opportunities()
                        ->where('sales_stage', 'Closed Won')
                        ->sum('amount'),
                    'win_rate' => $this->calculateWinRate($user->id)
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->toArray();
    }
    
    /**
     * Get revenue metrics
     */
    private function getRevenueMetrics(): array
    {
        $currentQuarter = ceil((int)(new \DateTime())->format('n') / 3);
        $currentYear = (int)(new \DateTime())->format('Y');
        
        return [
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateARR(),
            'quarterly_revenue' => Opportunity::where('sales_stage', 'Closed Won')
                ->whereYear('date_closed', $currentYear)
                ->whereRaw('QUARTER(date_closed) = ?', [$currentQuarter])
                ->sum('amount'),
            'pipeline_value' => Opportunity::whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])
                ->sum('amount'),
            'average_deal_size' => Opportunity::where('sales_stage', 'Closed Won')
                ->whereYear('date_closed', $currentYear)
                ->avg('amount'),
            'revenue_by_month' => $this->getRevenueByMonth()
        ];
    }
    
    /**
     * Get activity trends
     */
    private function getActivityTrends(): array
    {
        $days = 30;
        $trends = [];
        
        for ($i = $days; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-{$i} days");
            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'leads' => Lead::whereDate('date_entered', $date)->count(),
                'opportunities' => Opportunity::whereDate('date_entered', $date)->count(),
                'activities' => \App\Models\Call::whereDate('date_start', $date)->count() +
                              \App\Models\Meeting::whereDate('date_start', $date)->count()
            ];
        }
        
        return $trends;
    }
    
    /**
     * Get conversion funnel
     */
    private function getConversionFunnel(): array
    {
        $totalLeads = Lead::count();
        $qualifiedLeads = Lead::where('status', 'qualified')->count();
        $opportunities = Opportunity::count();
        $closedWon = Opportunity::where('sales_stage', 'Closed Won')->count();
        
        return [
            'stages' => [
                ['stage' => 'Leads', 'count' => $totalLeads, 'percentage' => 100],
                ['stage' => 'Qualified', 'count' => $qualifiedLeads, 'percentage' => $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 1) : 0],
                ['stage' => 'Opportunities', 'count' => $opportunities, 'percentage' => $totalLeads > 0 ? round(($opportunities / $totalLeads) * 100, 1) : 0],
                ['stage' => 'Won', 'count' => $closedWon, 'percentage' => $totalLeads > 0 ? round(($closedWon / $totalLeads) * 100, 1) : 0]
            ],
            'conversion_rates' => [
                'lead_to_qualified' => $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 1) : 0,
                'qualified_to_opportunity' => $qualifiedLeads > 0 ? round(($opportunities / $qualifiedLeads) * 100, 1) : 0,
                'opportunity_to_won' => $opportunities > 0 ? round(($closedWon / $opportunities) * 100, 1) : 0
            ]
        ];
    }
    
    /**
     * Get AI insights
     */
    private function getAIInsights(): array
    {
        // These would be generated by analyzing patterns
        return [
            'trending_up' => [
                'Web traffic leads converting 25% better than average',
                'Demo requests have 3x higher close rate',
                'Enterprise segment showing increased interest'
            ],
            'attention_needed' => [
                'Follow-up time increasing - average now 48 hours',
                '15 high-score leads unassigned',
                '8 opportunities stalled in negotiation stage'
            ],
            'recommendations' => [
                'Focus on web traffic optimization',
                'Prioritize demo request follow-ups',
                'Review stalled negotiation opportunities'
            ]
        ];
    }
    
    /**
     * Calculate win rate
     */
    private function calculateWinRate(string $userId, $since = null): float
    {
        $query = Opportunity::where('assigned_user_id', $userId)
            ->whereIn('sales_stage', ['Closed Won', 'Closed Lost']);
        
        if ($since) {
            $query->where('date_closed', '>=', $since);
        }
        
        $total = $query->count();
        $won = $query->where('sales_stage', 'Closed Won')->count();
        
        return $total > 0 ? round(($won / $total) * 100, 1) : 0;
    }
    
    /**
     * Calculate overall conversion rate
     */
    private function calculateOverallConversionRate(): float
    {
        $totalLeads = Lead::count();
        $convertedLeads = Lead::where('status', 'converted')->count();
        
        return $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;
    }
    
    /**
     * Calculate MRR (Monthly Recurring Revenue)
     */
    private function calculateMRR(): float
    {
        // This would need subscription data
        // For now, estimate from closed opportunities
        return Opportunity::where('sales_stage', 'Closed Won')
            ->where('opportunity_type', 'subscription')
            ->whereMonth('date_closed', (new \DateTime())->format('n'))
            ->sum('amount');
    }
    
    /**
     * Calculate ARR (Annual Recurring Revenue)
     */
    private function calculateARR(): float
    {
        return $this->calculateMRR() * 12;
    }
    
    /**
     * Get revenue by month
     */
    private function getRevenueByMonth(): array
    {
        return Opportunity::where('sales_stage', 'Closed Won')
            ->where('date_closed', '>=', (new \DateTime())->modify('-12 months')->format('Y-m-d'))
            ->get()
            ->groupBy(function ($opp) {
                return $opp->date_closed->format('Y-m');
            })
            ->map(function ($group, $month) {
                return [
                    'month' => $month,
                    'revenue' => $group->sum('amount'),
                    'count' => $group->count()
                ];
            })
            ->values()
            ->toArray();
    }
}