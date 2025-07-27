<?php

namespace App\Services\CRM;

use App\Models\Lead;
use App\Models\User;
use App\Services\AI\LeadScoringService;
use App\Services\Tracking\ActivityTrackingService;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LeadService
{
    public function __construct(
        private LeadScoringService $scoringService,
        private ActivityTrackingService $trackingService
    ) {}
    
    /**
     * Create a new lead
     */
    public function create(array $data): Lead
    {
        // Create the lead
        $lead = Lead::create($data);
        
        // Queue AI scoring
        $this->scoringService->scoreLead($lead);
        
        // Link any existing sessions
        if (!empty($data['visitor_id'])) {
            $this->trackingService->linkVisitorToLead($data['visitor_id'], $lead->id);
        }
        
        return $lead->fresh(['assignedUser', 'scores']);
    }
    
    /**
     * Update a lead
     */
    public function update(string $id, array $data): Lead
    {
        $lead = Lead::findOrFail($id);
        $lead->update($data);
        
        // Re-score if important fields changed
        $scoringFields = ['email', 'company', 'title', 'status'];
        if (array_intersect_key($data, array_flip($scoringFields))) {
            $this->scoringService->scoreLead($lead);
        }
        
        return $lead->fresh(['assignedUser', 'scores']);
    }
    
    /**
     * Get lead with full timeline
     */
    public function getWithTimeline(string $id): array
    {
        $lead = Lead::with([
            'sessions.pageViews',
            'conversations.messages',
            'scores',
            'formSubmissions',
            'tasks',
            'calls',
            'meetings',
            'notes'
        ])->findOrFail($id);
        
        return [
            'lead' => $lead,
            'timeline' => $this->buildTimeline($lead),
            'stats' => $this->calculateStats($lead)
        ];
    }
    
    /**
     * Build unified timeline of all activities
     */
    private function buildTimeline(Lead $lead): Collection
    {
        $timeline = collect();
        
        // Add page views from sessions
        foreach ($lead->sessions as $session) {
            $timeline->push([
                'type' => 'session',
                'timestamp' => $session->started_at,
                'title' => 'Website Session',
                'description' => "{$session->page_views} pages viewed, {$session->duration} seconds",
                'data' => $session
            ]);
        }
        
        // Add form submissions
        foreach ($lead->formSubmissions as $submission) {
            $timeline->push([
                'type' => 'form_submission',
                'timestamp' => $submission->created_at,
                'title' => 'Form Submitted',
                'description' => $submission->form->name ?? 'Unknown Form',
                'data' => $submission
            ]);
        }
        
        // Add chat conversations
        foreach ($lead->conversations as $conversation) {
            $timeline->push([
                'type' => 'chat',
                'timestamp' => $conversation->started_at,
                'title' => 'Chat Conversation',
                'description' => "{$conversation->messages->count()} messages",
                'data' => $conversation
            ]);
        }
        
        // Add AI scores
        foreach ($lead->scores as $score) {
            $timeline->push([
                'type' => 'ai_score',
                'timestamp' => $score->scored_at,
                'title' => 'AI Score Updated',
                'description' => "Score: {$score->score_percentage}%",
                'data' => $score
            ]);
        }
        
        // Add CRM activities
        foreach ($lead->tasks as $task) {
            $timeline->push([
                'type' => 'task',
                'timestamp' => $task->date_entered,
                'title' => $task->name,
                'description' => "Task: {$task->status}",
                'data' => $task
            ]);
        }
        
        foreach ($lead->calls as $call) {
            $timeline->push([
                'type' => 'call',
                'timestamp' => $call->date_start,
                'title' => $call->name,
                'description' => "Call: {$call->duration_total_minutes} minutes",
                'data' => $call
            ]);
        }
        
        foreach ($lead->meetings as $meeting) {
            $timeline->push([
                'type' => 'meeting',
                'timestamp' => $meeting->date_start,
                'title' => $meeting->name,
                'description' => "Meeting: {$meeting->status}",
                'data' => $meeting
            ]);
        }
        
        foreach ($lead->notes as $note) {
            $timeline->push([
                'type' => 'note',
                'timestamp' => $note->date_entered,
                'title' => $note->name,
                'description' => substr($note->description, 0, 100) . '...',
                'data' => $note
            ]);
        }
        
        // Sort by timestamp descending
        return $timeline->sortByDesc('timestamp')->values();
    }
    
    /**
     * Calculate lead statistics
     */
    private function calculateStats(Lead $lead): array
    {
        return [
            'total_sessions' => $lead->sessions->count(),
            'total_page_views' => $lead->sessions->sum('page_views'),
            'total_time_on_site' => $lead->sessions->sum('duration'),
            'form_submissions' => $lead->formSubmissions->count(),
            'chat_conversations' => $lead->conversations->count(),
            'latest_score' => $lead->latest_score,
            'days_since_created' => $lead->date_entered->diffInDays(now()),
            'total_activities' => $lead->tasks->count() + 
                                $lead->calls->count() + 
                                $lead->meetings->count() + 
                                $lead->notes->count()
        ];
    }
    
    /**
     * Convert lead to contact
     */
    public function convert(string $id, array $data): array
    {
        $lead = Lead::findOrFail($id);
        
        // Create contact
        $contact = new \App\Models\Contact([
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone_work' => $lead->phone,
            'title' => $lead->title,
            'lead_source' => $lead->source,
            'assigned_user_id' => $lead->assigned_user_id,
            'description' => $lead->description
        ]);
        
        // Add account if provided
        if (!empty($data['account_id'])) {
            $contact->account_id = $data['account_id'];
        }
        
        $contact->save();
        
        // Create opportunity if requested
        $opportunity = null;
        if (!empty($data['create_opportunity'])) {
            $opportunity = new \App\Models\Opportunity([
                'name' => $data['opportunity_name'] ?? "{$lead->company} - Opportunity",
                'amount' => $data['opportunity_amount'] ?? 0,
                'sales_stage' => $data['opportunity_stage'] ?? 'Prospecting',
                'probability' => 10,
                'date_closed' => now()->addMonths(3),
                'assigned_user_id' => $lead->assigned_user_id,
                'lead_source' => $lead->source
            ]);
            
            if (!empty($data['account_id'])) {
                $opportunity->account_id = $data['account_id'];
            }
            
            $opportunity->save();
            $opportunity->contacts()->attach($contact);
        }
        
        // Transfer history
        $this->transferHistory($lead, $contact);
        
        // Mark lead as converted
        $lead->update([
            'status' => 'converted',
            'converted_contact_id' => $contact->id
        ]);
        
        return [
            'contact' => $contact,
            'opportunity' => $opportunity,
            'lead' => $lead
        ];
    }
    
    /**
     * Transfer lead history to contact
     */
    private function transferHistory(Lead $lead, \App\Models\Contact $contact): void
    {
        // Update activity tracking
        \App\Models\ActivityTrackingSession::where('lead_id', $lead->id)
            ->update(['contact_id' => $contact->id]);
        
        // Update chat conversations
        \App\Models\ChatConversation::where('lead_id', $lead->id)
            ->update(['contact_id' => $contact->id]);
        
        // Update form submissions
        \App\Models\FormSubmission::where('lead_id', $lead->id)
            ->update(['contact_id' => $contact->id]);
    }
    
    /**
     * Bulk assign leads
     */
    public function bulkAssign(array $leadIds, string $userId): int
    {
        // Verify user exists
        User::findOrFail($userId);
        
        return Lead::whereIn('id', $leadIds)->update([
            'assigned_user_id' => $userId,
            'date_modified' => now()
        ]);
    }
    
    /**
     * Get leads for user's dashboard
     */
    public function getDashboardLeads(string $userId, int $limit = 10): Collection
    {
        return Lead::with(['scores'])
            ->where('assigned_user_id', $userId)
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->orderByDesc(Lead::select('score')
                ->from('ai_lead_scoring_history')
                ->whereColumn('ai_lead_scoring_history.lead_id', 'leads.id')
                ->latest('scored_at')
                ->limit(1)
            )
            ->limit($limit)
            ->get();
    }
}