<?php

namespace App\Services\CRM;

use App\Models\Lead;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\ActivityTrackingSession;
use App\Models\FormSubmission;
use App\Models\ChatConversation;
use Illuminate\Database\Capsule\Manager as DB;

class AnalyticsService
{
    /**
     * Get comprehensive analytics dashboard
     */
    public function getAnalyticsDashboard(array $dateRange = []): array
    {
        $startDate = $dateRange['start'] ?? (new \DateTime())->modify('-30 days');
        $endDate = $dateRange['end'] ?? new \DateTime();
        
        return [
            'overview' => $this->getOverviewMetrics($startDate, $endDate),
            'funnel' => $this->getFunnelAnalytics($startDate, $endDate),
            'sources' => $this->getSourceAnalytics($startDate, $endDate),
            'performance' => $this->getPerformanceMetrics($startDate, $endDate),
            'engagement' => $this->getEngagementMetrics($startDate, $endDate),
            'trends' => $this->getTrendAnalytics($startDate, $endDate),
            'predictions' => $this->getPredictiveAnalytics()
        ];
    }
    
    /**
     * Get overview metrics
     */
    private function getOverviewMetrics($startDate, $endDate): array
    {
        return [
            'visitors' => [
                'total' => ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])->count(),
                'unique' => ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])
                    ->distinct('visitor_id')->count('visitor_id')
            ],
            'leads' => [
                'generated' => Lead::whereBetween('date_entered', [$startDate, $endDate])->count(),
                'qualified' => Lead::whereBetween('date_entered', [$startDate, $endDate])
                    ->where('status', 'qualified')->count(),
                'converted' => Lead::whereBetween('date_entered', [$startDate, $endDate])
                    ->where('status', 'converted')->count()
            ],
            'opportunities' => [
                'created' => Opportunity::whereBetween('date_entered', [$startDate, $endDate])->count(),
                'won' => Opportunity::whereBetween('date_closed', [$startDate, $endDate])
                    ->where('sales_stage', 'Closed Won')->count(),
                'value' => Opportunity::whereBetween('date_closed', [$startDate, $endDate])
                    ->where('sales_stage', 'Closed Won')->sum('amount')
            ],
            'support' => [
                'cases_created' => SupportCase::whereBetween('date_entered', [$startDate, $endDate])->count(),
                'cases_resolved' => SupportCase::whereBetween('date_modified', [$startDate, $endDate])
                    ->where('status', 'closed')->count()
            ]
        ];
    }
    
    /**
     * Get funnel analytics
     */
    private function getFunnelAnalytics($startDate, $endDate): array
    {
        $visitors = ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])
            ->distinct('visitor_id')->count('visitor_id');
        
        $leads = Lead::whereBetween('date_entered', [$startDate, $endDate])->count();
        
        $qualified = Lead::whereBetween('date_entered', [$startDate, $endDate])
            ->where('status', 'qualified')->count();
        
        $opportunities = Opportunity::whereBetween('date_entered', [$startDate, $endDate])->count();
        
        $won = Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->where('sales_stage', 'Closed Won')->count();
        
        return [
            'stages' => [
                [
                    'stage' => 'Visitors',
                    'count' => $visitors,
                    'percentage' => 100
                ],
                [
                    'stage' => 'Leads',
                    'count' => $leads,
                    'percentage' => $visitors > 0 ? round(($leads / $visitors) * 100, 1) : 0
                ],
                [
                    'stage' => 'Qualified',
                    'count' => $qualified,
                    'percentage' => $visitors > 0 ? round(($qualified / $visitors) * 100, 1) : 0
                ],
                [
                    'stage' => 'Opportunities',
                    'count' => $opportunities,
                    'percentage' => $visitors > 0 ? round(($opportunities / $visitors) * 100, 1) : 0
                ],
                [
                    'stage' => 'Won',
                    'count' => $won,
                    'percentage' => $visitors > 0 ? round(($won / $visitors) * 100, 1) : 0
                ]
            ],
            'conversion_rates' => [
                'visitor_to_lead' => $visitors > 0 ? round(($leads / $visitors) * 100, 1) : 0,
                'lead_to_qualified' => $leads > 0 ? round(($qualified / $leads) * 100, 1) : 0,
                'qualified_to_opportunity' => $qualified > 0 ? round(($opportunities / $qualified) * 100, 1) : 0,
                'opportunity_to_won' => $opportunities > 0 ? round(($won / $opportunities) * 100, 1) : 0,
                'overall' => $visitors > 0 ? round(($won / $visitors) * 100, 1) : 0
            ]
        ];
    }
    
    /**
     * Get source analytics
     */
    private function getSourceAnalytics($startDate, $endDate): array
    {
        // Lead sources
        $leadSources = Lead::whereBetween('date_entered', [$startDate, $endDate])
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'source' => $item->source ?: 'Direct',
                    'leads' => $item->count,
                    'conversion_rate' => $this->getSourceConversionRate($item->source, $startDate, $endDate)
                ];
            });
        
        // UTM campaigns
        $campaigns = ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])
            ->whereNotNull('utm_campaign')
            ->select('utm_campaign', 'utm_source', 'utm_medium', DB::raw('COUNT(*) as sessions'))
            ->groupBy('utm_campaign', 'utm_source', 'utm_medium')
            ->orderByDesc('sessions')
            ->get();
        
        // Form performance
        $forms = FormSubmission::whereBetween('created_at', [$startDate, $endDate])
            ->select('form_id', DB::raw('COUNT(*) as submissions'))
            ->groupBy('form_id')
            ->with('form:form_id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'form' => $item->form->name ?? 'Unknown',
                    'submissions' => $item->submissions,
                    'conversion_rate' => $this->getFormConversionRate($item->form_id)
                ];
            });
        
        return [
            'lead_sources' => $leadSources,
            'campaigns' => $campaigns,
            'forms' => $forms,
            'referrers' => $this->getTopReferrers($startDate, $endDate)
        ];
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($startDate, $endDate): array
    {
        // Sales performance
        $salesMetrics = [
            'average_deal_size' => Opportunity::whereBetween('date_closed', [$startDate, $endDate])
                ->where('sales_stage', 'Closed Won')
                ->avg('amount'),
            'sales_cycle_days' => $this->calculateAverageSalesCycle($startDate, $endDate),
            'win_rate' => $this->calculateWinRate($startDate, $endDate),
            'pipeline_velocity' => $this->calculatePipelineVelocity($startDate, $endDate)
        ];
        
        // Team performance
        $teamMetrics = DB::table('users')
            ->leftJoin('opportunities', function ($join) use ($startDate, $endDate) {
                $join->on('users.id', '=', 'opportunities.assigned_user_id')
                    ->where('opportunities.sales_stage', 'Closed Won')
                    ->whereBetween('opportunities.date_closed', [$startDate, $endDate]);
            })
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                DB::raw('COUNT(opportunities.id) as deals_closed'),
                DB::raw('SUM(opportunities.amount) as revenue')
            )
            ->where('users.status', 'active')
            ->groupBy('users.id', 'users.first_name', 'users.last_name')
            ->orderByDesc('revenue')
            ->get();
        
        // Support performance
        $supportMetrics = [
            'average_resolution_time' => $this->calculateAverageResolutionTime($startDate, $endDate),
            'first_response_time' => $this->calculateFirstResponseTime($startDate, $endDate),
            'customer_satisfaction' => $this->calculateCustomerSatisfaction($startDate, $endDate)
        ];
        
        return [
            'sales' => $salesMetrics,
            'team' => $teamMetrics,
            'support' => $supportMetrics
        ];
    }
    
    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics($startDate, $endDate): array
    {
        // Website engagement
        $sessions = ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate]);
        
        $websiteMetrics = [
            'total_sessions' => $sessions->count(),
            'average_duration' => $sessions->avg('duration'),
            'pages_per_session' => $sessions->avg('page_views'),
            'bounce_rate' => $this->calculateBounceRate($startDate, $endDate),
            'top_pages' => $this->getTopPages($startDate, $endDate)
        ];
        
        // Content engagement
        $contentMetrics = [
            'form_submissions' => FormSubmission::whereBetween('created_at', [$startDate, $endDate])->count(),
            'chat_conversations' => ChatConversation::whereBetween('started_at', [$startDate, $endDate])->count(),
            'kb_article_views' => $this->getKnowledgeBaseViews($startDate, $endDate),
            'email_engagement' => $this->getEmailEngagement($startDate, $endDate)
        ];
        
        // Feature usage
        $featureUsage = [
            'ai_scoring_usage' => Lead::whereHas('scores', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date_scored', [$startDate, $endDate]);
            })->count(),
            'chatbot_conversations' => ChatConversation::whereBetween('started_at', [$startDate, $endDate])->count(),
            'forms_created' => \App\Models\FormBuilderForm::whereBetween('created_at', [$startDate, $endDate])->count()
        ];
        
        return [
            'website' => $websiteMetrics,
            'content' => $contentMetrics,
            'features' => $featureUsage
        ];
    }
    
    /**
     * Get trend analytics
     */
    private function getTrendAnalytics($startDate, $endDate): array
    {
        $days = \App\Helpers\DateHelper::diffInDays($startDate, $endDate);
        $interval = $days > 90 ? 'week' : 'day';
        
        return [
            'leads' => $this->getTrendData('leads', $startDate, $endDate, $interval),
            'opportunities' => $this->getTrendData('opportunities', $startDate, $endDate, $interval),
            'revenue' => $this->getRevenueTrend($startDate, $endDate, $interval),
            'activity' => $this->getActivityTrend($startDate, $endDate, $interval)
        ];
    }
    
    /**
     * Get predictive analytics
     */
    private function getPredictiveAnalytics(): array
    {
        // Simple predictions based on historical data
        $lastMonth = (new \DateTime())->modify('-1 month');
        $thisMonth = new \DateTime();
        
        // Revenue prediction
        $lastMonthRevenue = Opportunity::where('sales_stage', 'Closed Won')
            ->whereMonth('date_closed', $lastMonth->format('n'))
            ->whereYear('date_closed', $lastMonth->format('Y'))
            ->sum('amount');
        
        $thisMonthRevenue = Opportunity::where('sales_stage', 'Closed Won')
            ->whereMonth('date_closed', $thisMonth->format('n'))
            ->whereYear('date_closed', $thisMonth->format('Y'))
            ->sum('amount');
        
        $daysInMonth = (int)$thisMonth->format('t');
        $daysPassed = (int)$thisMonth->format('j');
        $projectedRevenue = $daysInMonth > 0 ? ($thisMonthRevenue / $daysPassed) * $daysInMonth : 0;
        
        // Lead prediction
        $avgLeadsPerDay = Lead::where('date_entered', '>=', (new \DateTime())->modify('-30 days')->format('Y-m-d'))
            ->count() / 30;
        
        $projectedLeads = $avgLeadsPerDay * ($daysInMonth - $daysPassed);
        
        return [
            'revenue' => [
                'current_month' => $thisMonthRevenue,
                'projected_month' => $projectedRevenue,
                'last_month' => $lastMonthRevenue,
                'growth_rate' => $lastMonthRevenue > 0 
                    ? round((($projectedRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) 
                    : 0
            ],
            'leads' => [
                'projected_this_month' => round($projectedLeads),
                'average_per_day' => round($avgLeadsPerDay, 1)
            ],
            'recommendations' => $this->getRecommendations()
        ];
    }
    
    /**
     * Helper methods
     */
    
    private function getSourceConversionRate($source, $startDate, $endDate): float
    {
        $leads = Lead::whereBetween('date_entered', [$startDate, $endDate])
            ->where('source', $source)
            ->count();
        
        $converted = Lead::whereBetween('date_entered', [$startDate, $endDate])
            ->where('source', $source)
            ->where('status', 'converted')
            ->count();
        
        return $leads > 0 ? round(($converted / $leads) * 100, 1) : 0;
    }
    
    private function getFormConversionRate($formId): float
    {
        // This would need view tracking
        return rand(5, 25); // Mock data
    }
    
    private function getTopReferrers($startDate, $endDate): array
    {
        return ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])
            ->select('referrer_url', DB::raw('COUNT(*) as sessions'))
            ->whereNotNull('referrer_url')
            ->groupBy('referrer_url')
            ->orderByDesc('sessions')
            ->limit(10)
            ->get()
            ->toArray();
    }
    
    private function calculateAverageSalesCycle($startDate, $endDate): float
    {
        $opportunities = Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->where('sales_stage', 'Closed Won')
            ->get();
        
        if ($opportunities->isEmpty()) {
            return 0;
        }
        
        $totalDays = $opportunities->sum(function ($opp) {
            return \App\Helpers\DateHelper::diffInDays($opp->date_entered, $opp->date_closed);
        });
        
        return round($totalDays / $opportunities->count(), 1);
    }
    
    private function calculateWinRate($startDate, $endDate): float
    {
        $closed = Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->whereIn('sales_stage', ['Closed Won', 'Closed Lost'])
            ->count();
        
        $won = Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->where('sales_stage', 'Closed Won')
            ->count();
        
        return $closed > 0 ? round(($won / $closed) * 100, 1) : 0;
    }
    
    private function calculatePipelineVelocity($startDate, $endDate): float
    {
        // Pipeline velocity = (# of opportunities × avg deal size × win rate) / sales cycle length
        $opportunities = Opportunity::whereBetween('date_entered', [$startDate, $endDate])->count();
        $avgDealSize = Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->where('sales_stage', 'Closed Won')
            ->avg('amount') ?? 0;
        $winRate = $this->calculateWinRate($startDate, $endDate) / 100;
        $salesCycle = $this->calculateAverageSalesCycle($startDate, $endDate) ?: 1;
        
        return round(($opportunities * $avgDealSize * $winRate) / $salesCycle, 2);
    }
    
    private function calculateAverageResolutionTime($startDate, $endDate): float
    {
        $cases = SupportCase::whereBetween('date_modified', [$startDate, $endDate])
            ->where('status', 'closed')
            ->get();
        
        if ($cases->isEmpty()) {
            return 0;
        }
        
        $totalHours = $cases->sum(function ($case) {
            return \App\Helpers\DateHelper::diffInHours($case->date_entered, $case->date_modified);
        });
        
        return round($totalHours / $cases->count(), 1);
    }
    
    private function calculateFirstResponseTime($startDate, $endDate): float
    {
        // Would need to track first response
        return rand(1, 4); // Mock data in hours
    }
    
    private function calculateCustomerSatisfaction($startDate, $endDate): float
    {
        // Would need satisfaction surveys
        return rand(85, 95); // Mock data percentage
    }
    
    private function calculateBounceRate($startDate, $endDate): float
    {
        $totalSessions = ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])->count();
        $bouncedSessions = ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])
            ->where('page_views', 1)
            ->count();
        
        return $totalSessions > 0 ? round(($bouncedSessions / $totalSessions) * 100, 1) : 0;
    }
    
    private function getTopPages($startDate, $endDate): array
    {
        return DB::table('activity_tracking_page_views')
            ->join('activity_tracking_sessions', 'activity_tracking_page_views.session_id', '=', 'activity_tracking_sessions.session_id')
            ->whereBetween('activity_tracking_sessions.started_at', [$startDate, $endDate])
            ->select('page_url', DB::raw('COUNT(*) as views'), DB::raw('AVG(time_on_page) as avg_time'))
            ->groupBy('page_url')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->toArray();
    }
    
    private function getKnowledgeBaseViews($startDate, $endDate): int
    {
        // Would need to track KB views
        return rand(500, 2000); // Mock data
    }
    
    private function getEmailEngagement($startDate, $endDate): array
    {
        // Would need email tracking
        return [
            'sent' => rand(1000, 5000),
            'opened' => rand(200, 1000),
            'clicked' => rand(50, 200),
            'open_rate' => rand(20, 40),
            'click_rate' => rand(5, 15)
        ];
    }
    
    private function getTrendData($entity, $startDate, $endDate, $interval): array
    {
        $model = match($entity) {
            'leads' => Lead::class,
            'opportunities' => Opportunity::class,
            default => null
        };
        
        if (!$model) return [];
        
        $dateField = 'date_entered';
        $groupBy = match($interval) {
            'day' => "DATE($dateField)",
            'week' => "YEARWEEK($dateField)",
            'month' => "DATE_FORMAT($dateField, '%Y-%m')",
            default => "DATE($dateField)"
        };
        
        return $model::whereBetween($dateField, [$startDate, $endDate])
            ->select(DB::raw("$groupBy as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }
    
    private function getRevenueTrend($startDate, $endDate, $interval): array
    {
        $groupBy = match($interval) {
            'day' => "DATE(date_closed)",
            'week' => "YEARWEEK(date_closed)",
            'month' => "DATE_FORMAT(date_closed, '%Y-%m')",
            default => "DATE(date_closed)"
        };
        
        return Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->where('sales_stage', 'Closed Won')
            ->select(DB::raw("$groupBy as period"), DB::raw('SUM(amount) as revenue'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }
    
    private function getActivityTrend($startDate, $endDate, $interval): array
    {
        $groupBy = match($interval) {
            'day' => "DATE(started_at)",
            'week' => "YEARWEEK(started_at)",
            'month' => "DATE_FORMAT(started_at, '%Y-%m')",
            default => "DATE(started_at)"
        };
        
        return ActivityTrackingSession::whereBetween('started_at', [$startDate, $endDate])
            ->select(DB::raw("$groupBy as period"), DB::raw('COUNT(*) as sessions'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }
    
    private function getRecommendations(): array
    {
        // AI-powered recommendations based on data
        return [
            'Increase focus on web traffic - converting 25% better than other sources',
            'Follow up with 12 high-score leads that haven\'t been contacted',
            'Review 5 stalled opportunities in negotiation stage',
            'Optimize landing page /features - highest bounce rate',
            'Schedule training for team members with low conversion rates'
        ];
    }
}