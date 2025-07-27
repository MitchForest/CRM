<?php

namespace App\Services\CRM;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

class OpportunityService
{
    private array $stageOrder = [
        'Prospecting' => 1,
        'Qualification' => 2,
        'Needs Analysis' => 3,
        'Value Proposition' => 4,
        'Id. Decision Makers' => 5,
        'Perception Analysis' => 6,
        'Proposal/Price Quote' => 7,
        'Negotiation/Review' => 8,
        'Closed Won' => 9,
        'Closed Lost' => 10
    ];
    
    private array $stageProbabilities = [
        'Prospecting' => 10,
        'Qualification' => 20,
        'Needs Analysis' => 30,
        'Value Proposition' => 40,
        'Id. Decision Makers' => 50,
        'Perception Analysis' => 60,
        'Proposal/Price Quote' => 70,
        'Negotiation/Review' => 80,
        'Closed Won' => 100,
        'Closed Lost' => 0
    ];
    
    /**
     * Create a new opportunity
     */
    public function create(array $data): Opportunity
    {
        // Set default probability based on stage
        if (!isset($data['probability']) && isset($data['sales_stage'])) {
            $data['probability'] = $this->stageProbabilities[$data['sales_stage']] ?? 50;
        }
        
        $opportunity = Opportunity::create($data);
        
        // Attach contacts if provided
        if (!empty($data['contact_ids'])) {
            $opportunity->contacts()->attach($data['contact_ids']);
        }
        
        return $opportunity->fresh(['account', 'assignedUser', 'contacts']);
    }
    
    /**
     * Update an opportunity
     */
    public function update(string $id, array $data): Opportunity
    {
        $opportunity = Opportunity::findOrFail($id);
        $oldStage = $opportunity->sales_stage;
        
        // Update probability if stage changed
        if (isset($data['sales_stage']) && $data['sales_stage'] !== $oldStage) {
            $data['probability'] = $this->stageProbabilities[$data['sales_stage']] ?? $opportunity->probability;
            
            // Log stage change
            $this->logStageChange($opportunity, $oldStage, $data['sales_stage']);
        }
        
        $opportunity->update($data);
        
        // Update contacts if provided
        if (isset($data['contact_ids'])) {
            $opportunity->contacts()->sync($data['contact_ids']);
        }
        
        return $opportunity->fresh(['account', 'assignedUser', 'contacts']);
    }
    
    /**
     * Get pipeline view data
     */
    public function getPipeline(array $filters = []): array
    {
        $query = Opportunity::with(['assignedUser', 'account'])
            ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost']);
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('assigned_user_id', $filters['user_id']);
        }
        
        if (!empty($filters['date_range'])) {
            $query->whereBetween('date_closed', [
                $filters['date_range']['start'],
                $filters['date_range']['end']
            ]);
        }
        
        $opportunities = $query->get();
        
        // Group by stage
        $pipeline = [];
        foreach ($this->stageOrder as $stage => $order) {
            if (in_array($stage, ['Closed Won', 'Closed Lost'])) {
                continue;
            }
            
            $stageOpps = $opportunities->where('sales_stage', $stage);
            
            $pipeline[] = [
                'stage' => $stage,
                'order' => $order,
                'count' => $stageOpps->count(),
                'total_value' => $stageOpps->sum('amount'),
                'weighted_value' => $stageOpps->sum('weighted_amount'),
                'opportunities' => $stageOpps->values()
            ];
        }
        
        return [
            'stages' => $pipeline,
            'summary' => [
                'total_opportunities' => $opportunities->count(),
                'total_value' => $opportunities->sum('amount'),
                'weighted_value' => $opportunities->sum('weighted_amount'),
                'average_deal_size' => $opportunities->avg('amount'),
                'average_probability' => $opportunities->avg('probability')
            ]
        ];
    }
    
    /**
     * Get forecast data
     */
    public function getForecast(string $period = 'quarter'): array
    {
        $startDate = new \DateTime();
        $endDate = match($period) {
            'month' => new \DateTime('last day of this month'),
            'quarter' => new \DateTime('last day of this quarter'),
            'year' => new \DateTime('December 31'),
            default => new \DateTime('last day of this quarter')
        };
        
        $opportunities = Opportunity::whereBetween('date_closed', [$startDate, $endDate])
            ->whereNotIn('sales_stage', ['Closed Lost'])
            ->get();
        
        $forecast = [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'committed' => $opportunities->where('probability', '>=', 70)->sum('amount'),
            'best_case' => $opportunities->sum('amount'),
            'weighted' => $opportunities->sum('weighted_amount'),
            'closed_won' => $opportunities->where('sales_stage', 'Closed Won')->sum('amount'),
            'pipeline_count' => $opportunities->count(),
            'by_month' => $this->getForecastByMonth($opportunities),
            'by_user' => $this->getForecastByUser($opportunities)
        ];
        
        return $forecast;
    }
    
    /**
     * Move opportunity to next stage
     */
    public function moveToNextStage(string $id): Opportunity
    {
        $opportunity = Opportunity::findOrFail($id);
        $currentOrder = $this->stageOrder[$opportunity->sales_stage] ?? 1;
        
        // Find next stage
        $nextStage = null;
        foreach ($this->stageOrder as $stage => $order) {
            if ($order === $currentOrder + 1) {
                $nextStage = $stage;
                break;
            }
        }
        
        if ($nextStage) {
            return $this->update($id, ['sales_stage' => $nextStage]);
        }
        
        return $opportunity;
    }
    
    /**
     * Calculate win/loss analytics
     */
    public function getWinLossAnalytics(array $dateRange = []): array
    {
        $query = Opportunity::whereIn('sales_stage', ['Closed Won', 'Closed Lost']);
        
        if (!empty($dateRange)) {
            $query->whereBetween('date_closed', [$dateRange['start'], $dateRange['end']]);
        }
        
        $opportunities = $query->get();
        
        $won = $opportunities->where('sales_stage', 'Closed Won');
        $lost = $opportunities->where('sales_stage', 'Closed Lost');
        
        return [
            'total_closed' => $opportunities->count(),
            'won' => [
                'count' => $won->count(),
                'value' => $won->sum('amount'),
                'average_size' => $won->avg('amount'),
                'average_days_to_close' => $won->avg(function ($opp) {
                    return \App\Helpers\DateHelper::diffInDays($opp->date_entered, $opp->date_closed);
                })
            ],
            'lost' => [
                'count' => $lost->count(),
                'value' => $lost->sum('amount'),
                'reasons' => $this->analyzeLossReasons($lost)
            ],
            'win_rate' => $opportunities->count() > 0 
                ? round(($won->count() / $opportunities->count()) * 100, 1) 
                : 0,
            'by_source' => $this->analyzeBySource($opportunities),
            'by_product' => $this->analyzeByProduct($opportunities)
        ];
    }
    
    /**
     * Get opportunities requiring attention
     */
    public function getRequiringAttention(string $userId = null): Collection
    {
        $query = Opportunity::with(['account', 'assignedUser'])
            ->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost']);
        
        if ($userId) {
            $query->where('assigned_user_id', $userId);
        }
        
        $opportunities = $query->get();
        
        return $opportunities->filter(function ($opp) {
            // Overdue
            if ($opp->date_closed < new \DateTime()) {
                $opp->attention_reason = 'Overdue close date';
                return true;
            }
            
            // Stalled (no activity in 14 days)
            $lastActivity = $opp->tasks()->latest()->first()?->date_entered ??
                          $opp->calls()->latest()->first()?->date_entered ??
                          $opp->meetings()->latest()->first()?->date_entered;
            
            if (!$lastActivity || $lastActivity < (new \DateTime())->modify('-14 days')) {
                $opp->attention_reason = 'No activity in 14+ days';
                return true;
            }
            
            // High value at risk
            if ($opp->amount > 50000 && $opp->probability < 50) {
                $opp->attention_reason = 'High value at risk';
                return true;
            }
            
            return false;
        });
    }
    
    /**
     * Log stage change
     */
    private function logStageChange(Opportunity $opportunity, string $oldStage, string $newStage): void
    {
        $opportunity->notes()->create([
            'name' => 'Stage Change',
            'description' => "Stage changed from {$oldStage} to {$newStage}",
            'parent_type' => 'Opportunities',
            'parent_id' => $opportunity->id,
            'assigned_user_id' => $opportunity->assigned_user_id
        ]);
    }
    
    /**
     * Get forecast by month
     */
    private function getForecastByMonth(Collection $opportunities): array
    {
        return $opportunities->groupBy(function ($opp) {
            return $opp->date_closed->format('Y-m');
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
                'weighted' => $group->sum('weighted_amount')
            ];
        })->toArray();
    }
    
    /**
     * Get forecast by user
     */
    private function getForecastByUser(Collection $opportunities): array
    {
        return $opportunities->groupBy('assigned_user_id')
            ->map(function ($group) {
                $user = $group->first()->assignedUser;
                return [
                    'user' => $user ? $user->full_name : 'Unassigned',
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                    'weighted' => $group->sum('weighted_amount')
                ];
            })->values()->toArray();
    }
    
    /**
     * Analyze loss reasons
     */
    private function analyzeLossReasons(Collection $lost): array
    {
        // In production, you'd have a loss_reason field
        // For now, return mock data
        return [
            'Price' => 35,
            'Competition' => 25,
            'No Budget' => 20,
            'Timing' => 15,
            'Other' => 5
        ];
    }
    
    /**
     * Analyze by source
     */
    private function analyzeBySource(Collection $opportunities): array
    {
        return $opportunities->groupBy('lead_source')
            ->map(function ($group, $source) {
                $won = $group->where('sales_stage', 'Closed Won');
                return [
                    'source' => $source ?: 'Unknown',
                    'total' => $group->count(),
                    'won' => $won->count(),
                    'win_rate' => $group->count() > 0 
                        ? round(($won->count() / $group->count()) * 100, 1) 
                        : 0,
                    'revenue' => $won->sum('amount')
                ];
            })->values()->toArray();
    }
    
    /**
     * Analyze by product
     */
    private function analyzeByProduct(Collection $opportunities): array
    {
        return $opportunities->groupBy('opportunity_type')
            ->map(function ($group, $type) {
                $won = $group->where('sales_stage', 'Closed Won');
                return [
                    'product' => $type ?: 'Unknown',
                    'total' => $group->count(),
                    'won' => $won->count(),
                    'win_rate' => $group->count() > 0 
                        ? round(($won->count() / $group->count()) * 100, 1) 
                        : 0,
                    'revenue' => $won->sum('amount')
                ];
            })->values()->toArray();
    }
}