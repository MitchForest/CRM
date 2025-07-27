<?php

namespace App\Services\CRM;

use App\Models\Contact;
use App\Models\CustomerHealthScore;
use Illuminate\Database\Eloquent\Collection;

class ContactService
{
    /**
     * Create a new contact
     */
    public function create(array $data): Contact
    {
        $contact = Contact::create($data);
        
        // Calculate initial health score
        $this->calculateHealthScore($contact);
        
        return $contact->fresh(['account', 'assignedUser']);
    }
    
    /**
     * Update a contact
     */
    public function update(string $id, array $data): Contact
    {
        $contact = Contact::findOrFail($id);
        $contact->update($data);
        
        // Recalculate health score if key fields changed
        $healthFields = ['account_id', 'email', 'phone_work'];
        if (array_intersect_key($data, array_flip($healthFields))) {
            $this->calculateHealthScore($contact);
        }
        
        return $contact->fresh(['account', 'assignedUser']);
    }
    
    /**
     * Get contact with full timeline
     */
    public function getWithTimeline(string $id): array
    {
        $contact = Contact::with([
            'activities.pageViews',
            'conversations.messages',
            'cases',
            'opportunities',
            'healthScores',
            'account'
        ])->findOrFail($id);
        
        return [
            'contact' => $contact,
            'timeline' => $this->buildTimeline($contact),
            'stats' => $this->calculateStats($contact),
            'health' => $this->getHealthSummary($contact)
        ];
    }
    
    /**
     * Build unified timeline
     */
    private function buildTimeline(Contact $contact): Collection
    {
        $timeline = collect();
        
        // Add activities
        foreach ($contact->activities as $session) {
            $timeline->push([
                'type' => 'session',
                'timestamp' => $session->started_at,
                'title' => 'Website Session',
                'description' => "{$session->page_views} pages viewed",
                'data' => $session
            ]);
        }
        
        // Add conversations
        foreach ($contact->conversations as $conversation) {
            $timeline->push([
                'type' => 'chat',
                'timestamp' => $conversation->started_at,
                'title' => 'Support Chat',
                'description' => $conversation->metadata['resolved'] ?? false ? 'Resolved' : 'Open',
                'data' => $conversation
            ]);
        }
        
        // Add cases
        foreach ($contact->cases as $case) {
            $timeline->push([
                'type' => 'case',
                'timestamp' => $case->date_entered,
                'title' => $case->name,
                'description' => "Support Case: {$case->status}",
                'data' => $case
            ]);
        }
        
        // Add opportunities
        foreach ($contact->opportunities as $opportunity) {
            $timeline->push([
                'type' => 'opportunity',
                'timestamp' => $opportunity->date_entered,
                'title' => $opportunity->name,
                'description' => "Opportunity: \${$opportunity->amount}",
                'data' => $opportunity
            ]);
        }
        
        // Add health score changes
        foreach ($contact->healthScores as $score) {
            $timeline->push([
                'type' => 'health_score',
                'timestamp' => $score->calculated_at,
                'title' => 'Health Score Update',
                'description' => "Score: " . $this->getHealthStatusFromScore($score->score) . " (" . ($score->score * 100) . "%)",
                'data' => $score
            ]);
        }
        
        return $timeline->sortByDesc('timestamp')->values();
    }
    
    /**
     * Calculate contact statistics
     */
    private function calculateStats(Contact $contact): array
    {
        return [
            'total_sessions' => $contact->activities->count(),
            'total_page_views' => $contact->activities->sum('page_views'),
            'support_cases' => $contact->cases->count(),
            'open_cases' => $contact->cases->where('status', '!=', 'closed')->count(),
            'total_opportunities' => $contact->opportunities->count(),
            'opportunity_value' => $contact->opportunities->sum('amount'),
            'days_as_customer' => \App\Helpers\DateHelper::diffInDays($contact->date_entered, new \DateTime()),
            'last_activity' => $contact->activities->max('started_at')
        ];
    }
    
    /**
     * Calculate health score
     */
    public function calculateHealthScore(Contact $contact): CustomerHealthScore
    {
        $factors = [];
        $score = 0;
        
        // Activity engagement (30%)
        $recentActivity = $contact->activities()
            ->where('started_at', '>', (new \DateTime())->modify('-30 days'))
            ->count();
        $activityScore = min($recentActivity / 10, 1) * 0.3;
        $factors['activity'] = [
            'score' => $activityScore,
            'count' => $recentActivity
        ];
        $score += $activityScore;
        
        // Support satisfaction (30%)
        $recentCases = $contact->cases()
            ->where('date_entered', '>', (new \DateTime())->modify('-3 months'))
            ->get();
        $resolvedCases = $recentCases->where('status', 'closed')->count();
        $totalCases = $recentCases->count();
        $supportScore = $totalCases > 0 ? ($resolvedCases / $totalCases) * 0.3 : 0.15;
        $factors['support'] = [
            'score' => $supportScore,
            'resolved' => $resolvedCases,
            'total' => $totalCases
        ];
        $score += $supportScore;
        
        // Product usage (20%)
        $lastLogin = $contact->activities()
            ->where('started_at', '>', (new \DateTime())->modify('-7 days'))
            ->exists();
        $usageScore = $lastLogin ? 0.2 : 0;
        $factors['usage'] = [
            'score' => $usageScore,
            'last_login' => $lastLogin
        ];
        $score += $usageScore;
        
        // Relationship strength (20%)
        $hasMultipleContacts = $contact->account?->contacts()->count() > 1;
        $relationshipScore = $hasMultipleContacts ? 0.2 : 0.1;
        $factors['relationship'] = [
            'score' => $relationshipScore,
            'multiple_contacts' => $hasMultipleContacts
        ];
        $score += $relationshipScore;
        
        // Determine trend
        $lastScore = $contact->healthScores()->latest()->first();
        $trend = 'stable';
        if ($lastScore) {
            if ($score > $lastScore->score + 10) $trend = 'improving';
            elseif ($score < $lastScore->score - 10) $trend = 'declining';
        }
        
        // Determine risk level
        $riskLevel = 'low';
        if ($score < 30) $riskLevel = 'high';
        elseif ($score < 60) $riskLevel = 'medium';
        
        return CustomerHealthScore::create([
            'contact_id' => $contact->id,
            'account_id' => $contact->account_id,
            'score' => round($score, 2),
            'factors' => $factors,
            'trend' => $trend,
            'risk_level' => $riskLevel,
            'calculated_at' => new \DateTime()
        ]);
    }
    
    /**
     * Get health summary
     */
    private function getHealthSummary(Contact $contact): array
    {
        $latestScore = $contact->healthScores()->latest()->first();
        
        if (!$latestScore) {
            return [
                'score' => null,
                'status' => 'Unknown',
                'trend' => 'stable',
                'risk_level' => 'unknown'
            ];
        }
        
        return [
            'score' => $latestScore->score,
            'status' => $this->getHealthStatusFromScore($latestScore->score),
            'color' => $this->getHealthColorFromScore($latestScore->score),
            'trend' => $latestScore->trend,
            'risk_level' => $latestScore->risk_level,
            'factors' => $latestScore->factors,
            'last_calculated' => $latestScore->calculated_at
        ];
    }
    
    /**
     * Get health status from score
     */
    private function getHealthStatusFromScore(float $score): string
    {
        $scorePercent = $score * 100;
        
        if ($scorePercent >= 80) return 'Excellent';
        if ($scorePercent >= 60) return 'Good';
        if ($scorePercent >= 40) return 'Fair';
        if ($scorePercent >= 20) return 'At Risk';
        return 'Critical';
    }
    
    /**
     * Get health color from score
     */
    private function getHealthColorFromScore(float $score): string
    {
        $scorePercent = $score * 100;
        
        if ($scorePercent >= 80) return 'green';
        if ($scorePercent >= 60) return 'blue';
        if ($scorePercent >= 40) return 'yellow';
        if ($scorePercent >= 20) return 'orange';
        return 'red';
    }
    
    /**
     * Get contacts at risk
     */
    public function getAtRiskContacts(string $userId = null): Collection
    {
        $query = Contact::with(['account', 'healthScores' => function ($q) {
            $q->latest()->limit(1);
        }]);
        
        if ($userId) {
            $query->where('assigned_user_id', $userId);
        }
        
        return $query->get()->filter(function ($contact) {
            $latestScore = $contact->healthScores->first();
            return $latestScore && $latestScore->risk_level === 'high';
        });
    }
}