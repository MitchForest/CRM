<?php

namespace App\Services\CRM;

use App\Models\SupportCase;
use App\Services\AI\OpenAIService;
use Illuminate\Database\Eloquent\Collection;

class CaseService
{
    public function __construct(
        private OpenAIService $openAI
    ) {}
    
    /**
     * Create a new case
     */
    public function create(array $data): SupportCase
    {
        // Generate case number if not provided
        if (empty($data['case_number'])) {
            $data['case_number'] = $this->generateCaseNumber();
        }
        
        $case = SupportCase::create($data);
        
        // Auto-assign if rules are configured
        if (empty($data['assigned_user_id'])) {
            $this->autoAssignCase($case);
        }
        
        // Analyze sentiment if description provided
        if (!empty($case->description)) {
            $this->analyzeCaseSentiment($case);
        }
        
        return $case->fresh(['contact', 'account', 'assignedUser']);
    }
    
    /**
     * Update a case
     */
    public function update(string $id, array $data): SupportCase
    {
        $case = SupportCase::findOrFail($id);
        $oldStatus = $case->status;
        
        $case->update($data);
        
        // Log status changes
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->logStatusChange($case, $oldStatus, $data['status']);
            
            // Send notifications for critical status changes
            if ($data['status'] === 'closed') {
                $this->notifyCaseClosed($case);
            }
        }
        
        return $case->fresh(['contact', 'account', 'assignedUser']);
    }
    
    /**
     * Generate unique case number
     */
    private function generateCaseNumber(): string
    {
        $prefix = 'CASE';
        $year = (new \DateTime())->format('Y');
        $lastCase = SupportCase::whereYear('date_entered', $year)
            ->orderBy('case_number', 'desc')
            ->first();
        
        if ($lastCase && preg_match('/CASE-' . $year . '-(\d+)/', $lastCase->case_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return sprintf('%s-%s-%06d', $prefix, $year, $nextNumber);
    }
    
    /**
     * Auto-assign case based on rules
     */
    private function autoAssignCase(SupportCase $case): void
    {
        // Simple round-robin assignment
        $lastAssigned = SupportCase::orderBy('date_entered', 'desc')
            ->whereNotNull('assigned_user_id')
            ->first();
        
        $users = \App\Models\User::where('status', 'active')
            ->where('department', 'support')
            ->orderBy('first_name')
            ->pluck('id')
            ->toArray();
        
        if (empty($users)) {
            return;
        }
        
        $lastIndex = $lastAssigned ? array_search($lastAssigned->assigned_user_id, $users) : -1;
        $nextIndex = ($lastIndex + 1) % count($users);
        
        $case->update(['assigned_user_id' => $users[$nextIndex]]);
    }
    
    /**
     * Analyze case sentiment
     */
    private function analyzeCaseSentiment(SupportCase $case): void
    {
        try {
            $sentiment = $this->openAI->analyzeSentiment($case->description);
            
            // Escalate if negative sentiment
            if ($sentiment['sentiment'] === 'negative' && $sentiment['confidence'] > 0.8) {
                $case->update(['priority' => 'high']);
                
                // Add note about sentiment
                $case->notes()->create([
                    'name' => 'Sentiment Analysis',
                    'description' => "Detected negative sentiment. Case automatically escalated to high priority.",
                    'parent_type' => 'Cases',
                    'parent_id' => $case->id
                ]);
            }
        } catch (\Exception $e) {
            // Log but don't fail
            error_log("WARNING: Case sentiment analysis failed - case_id: {$case->id}, error: " . $e->getMessage());
        }
    }
    
    /**
     * Log status change
     */
    private function logStatusChange(SupportCase $case, string $oldStatus, string $newStatus): void
    {
        $case->notes()->create([
            'name' => 'Status Change',
            'description' => "Status changed from {$oldStatus} to {$newStatus}",
            'parent_type' => 'Cases',
            'parent_id' => $case->id,
            'assigned_user_id' => $case->assigned_user_id
        ]);
    }
    
    /**
     * Send case closed notification
     */
    private function notifyCaseClosed(SupportCase $case): void
    {
        // In production, send email to contact
        error_log("Case closed notification - case_id: {$case->id}, contact_email: " . ($case->contact?->email ?? 'N/A'));
    }
    
    /**
     * Get cases requiring attention
     */
    public function getCasesRequiringAttention(string $userId = null): Collection
    {
        $query = SupportCase::with(['contact', 'account'])
            ->where('status', '!=', 'closed');
        
        if ($userId) {
            $query->where('assigned_user_id', $userId);
        }
        
        $cases = $query->get();
        
        return $cases->filter(function ($case) {
            // High priority open > 24 hours
            if ($case->priority === 'high' && $case->date_entered->lt((new \DateTime())->modify('-1 day'))) {
                $case->attention_reason = 'High priority case open > 24 hours';
                return true;
            }
            
            // Any case open > 3 days
            if ($case->date_entered->lt((new \DateTime())->modify('-3 days'))) {
                $case->attention_reason = 'Case open > 3 days';
                return true;
            }
            
            // No activity in 48 hours
            $lastActivity = $case->notes()->latest()->first()?->date_entered ??
                          $case->calls()->latest()->first()?->date_entered ??
                          $case->date_modified;
            
            if ($lastActivity->lt((new \DateTime())->modify('-2 days'))) {
                $case->attention_reason = 'No activity in 48 hours';
                return true;
            }
            
            return false;
        });
    }
    
    /**
     * Get case metrics
     */
    public function getCaseMetrics(array $dateRange = []): array
    {
        $query = SupportCase::query();
        
        if (!empty($dateRange)) {
            $query->whereBetween('date_entered', [$dateRange['start'], $dateRange['end']]);
        }
        
        $cases = $query->get();
        
        $resolved = $cases->where('status', 'closed');
        
        return [
            'total_cases' => $cases->count(),
            'open_cases' => $cases->where('status', '!=', 'closed')->count(),
            'resolved_cases' => $resolved->count(),
            'resolution_rate' => $cases->count() > 0 
                ? round(($resolved->count() / $cases->count()) * 100, 1) 
                : 0,
            'average_resolution_time' => $this->calculateAverageResolutionTime($resolved),
            'by_priority' => $cases->groupBy('priority')->map->count(),
            'by_type' => $cases->groupBy('type')->map->count(),
            'by_source' => $cases->groupBy('source')->map->count(),
            'sla_compliance' => $this->calculateSLACompliance($resolved)
        ];
    }
    
    /**
     * Calculate average resolution time
     */
    private function calculateAverageResolutionTime(Collection $resolvedCases): ?float
    {
        if ($resolvedCases->isEmpty()) {
            return null;
        }
        
        $totalHours = $resolvedCases->sum(function ($case) {
            return $case->date_entered->diffInHours($case->date_modified);
        });
        
        return round($totalHours / $resolvedCases->count(), 1);
    }
    
    /**
     * Calculate SLA compliance
     */
    private function calculateSLACompliance(Collection $resolvedCases): array
    {
        $slaTargets = [
            'high' => 24, // 24 hours
            'medium' => 48, // 48 hours
            'low' => 72 // 72 hours
        ];
        
        $compliance = [];
        
        foreach ($slaTargets as $priority => $targetHours) {
            $priorityCases = $resolvedCases->where('priority', $priority);
            
            if ($priorityCases->isEmpty()) {
                $compliance[$priority] = null;
                continue;
            }
            
            $withinSLA = $priorityCases->filter(function ($case) use ($targetHours) {
                return $case->date_entered->diffInHours($case->date_modified) <= $targetHours;
            })->count();
            
            $compliance[$priority] = round(($withinSLA / $priorityCases->count()) * 100, 1);
        }
        
        return $compliance;
    }
    
    /**
     * Get suggested solutions
     */
    public function getSuggestedSolutions(string $caseId): array
    {
        $case = SupportCase::findOrFail($caseId);
        
        // Find similar resolved cases
        $similarCases = SupportCase::where('status', 'closed')
            ->where('id', '!=', $caseId)
            ->where(function ($query) use ($case) {
                $query->where('type', $case->type)
                    ->orWhere('name', 'like', '%' . substr($case->name, 0, 20) . '%');
            })
            ->with('notes')
            ->limit(5)
            ->get();
        
        // Extract solutions from case notes
        $solutions = [];
        foreach ($similarCases as $similarCase) {
            $resolutionNote = $similarCase->notes()
                ->where('name', 'like', '%resolution%')
                ->orWhere('description', 'like', '%resolved%')
                ->first();
            
            if ($resolutionNote) {
                $solutions[] = [
                    'case_number' => $similarCase->case_number,
                    'case_name' => $similarCase->name,
                    'resolution' => $resolutionNote->description,
                    'resolved_date' => $similarCase->date_modified
                ];
            }
        }
        
        // Get AI suggestions if no similar cases found
        if (empty($solutions)) {
            try {
                $prompt = "Suggest 3 possible solutions for this support case:\n" .
                         "Type: {$case->type}\n" .
                         "Issue: {$case->name}\n" .
                         "Description: {$case->description}";
                
                $aiSuggestions = $this->openAI->complete($prompt, [
                    'max_tokens' => 300,
                    'temperature' => 0.7
                ]);
                
                $solutions[] = [
                    'source' => 'AI',
                    'suggestions' => $aiSuggestions
                ];
            } catch (\Exception $e) {
                // Log but don't fail
            }
        }
        
        return $solutions;
    }
    
    /**
     * Escalate case
     */
    public function escalateCase(string $id, array $data): SupportCase
    {
        $case = SupportCase::findOrFail($id);
        
        $updates = [
            'priority' => 'high',
            'status' => 'escalated'
        ];
        
        if (!empty($data['escalate_to'])) {
            $updates['assigned_user_id'] = $data['escalate_to'];
        }
        
        $case->update($updates);
        
        // Add escalation note
        $case->notes()->create([
            'name' => 'Case Escalated',
            'description' => $data['reason'] ?? 'Case has been escalated to high priority',
            'parent_type' => 'Cases',
            'parent_id' => $case->id
        ]);
        
        return $case->fresh(['contact', 'account', 'assignedUser']);
    }
}