<?php

namespace App\Services\Tracking;

use App\Models\ActivityTrackingVisitor;
use App\Models\ActivityTrackingSession;
use App\Models\ActivityTrackingPageView;
use App\Models\Lead;
use App\Models\Contact;
use Illuminate\Support\Str;

class ActivityTrackingService
{
    /**
     * Track a page view
     */
    public function trackPageView(array $data): ActivityTrackingPageView
    {
        // Get or create visitor
        $visitor = $this->getOrCreateVisitor($data);
        
        // Get or create session
        $session = $this->getOrCreateSession($visitor, $data);
        
        // Create page view
        $pageView = ActivityTrackingPageView::create([
            'session_id' => $session->session_id,
            'visitor_id' => $visitor->visitor_id,
            'page_url' => $data['page_url'],
            'page_title' => $data['page_title'] ?? null,
            'time_on_page' => 0,
            'bounce' => false,
            'exit_page' => false,
            'created_at' => new \DateTime()
        ]);
        
        // Update session stats
        $session->increment('page_views');
        $session->update(['ended_at' => new \DateTime()]);
        
        return $pageView;
    }
    
    /**
     * Update time spent on page
     */
    public function updatePageTime(string $pageViewId, int $timeSpent): void
    {
        $pageView = ActivityTrackingPageView::find($pageViewId);
        if ($pageView) {
            $pageView->update(['time_on_page' => $timeSpent]);
            
            // Update session duration
            $session = ActivityTrackingSession::where('session_id', $pageView->session_id)->first();
            if ($session) {
                $totalDuration = $session->pageViews()->sum('time_on_page');
                $session->update([
                    'duration' => $totalDuration,
                    'ended_at' => new \DateTime()
                ]);
            }
        }
    }
    
    /**
     * Track an event
     */
    public function trackEvent(array $data): void
    {
        $session = ActivityTrackingSession::where('session_id', $data['session_id'])->first();
        if ($session) {
            $session->increment('events_count');
            
            // Track specific events
            if ($data['event_type'] === 'form_submission') {
                $session->increment('form_submissions');
            }
        }
    }
    
    /**
     * End a session
     */
    public function endSession(string $sessionId): void
    {
        $session = ActivityTrackingSession::where('session_id', $sessionId)->first();
        if ($session) {
            // Mark last page as exit
            $lastPageView = $session->pageViews()->latest()->first();
            if ($lastPageView) {
                $lastPageView->update(['exit_page' => true]);
                
                // Check if bounce (only one page view)
                if ($session->page_views === 1) {
                    $lastPageView->update(['bounce' => true]);
                }
            }
            
            // Update session
            $now = new \DateTime();
            $diff = $now->getTimestamp() - (new \DateTime($session->started_at))->getTimestamp();
            $session->update([
                'ended_at' => $now,
                'duration' => $diff
            ]);
        }
    }
    
    /**
     * Link visitor to lead
     */
    public function linkVisitorToLead(string $visitorId, string $leadId): void
    {
        // Update all sessions for this visitor
        ActivityTrackingSession::where('visitor_id', $visitorId)
            ->whereNull('lead_id')
            ->update(['lead_id' => $leadId]);
    }
    
    /**
     * Link visitor to contact
     */
    public function linkVisitorToContact(string $visitorId, string $contactId): void
    {
        // Update all sessions for this visitor
        ActivityTrackingSession::where('visitor_id', $visitorId)
            ->whereNull('contact_id')
            ->update(['contact_id' => $contactId]);
    }
    
    /**
     * Get visitor activity summary
     */
    public function getVisitorSummary(string $visitorId): array
    {
        $visitor = ActivityTrackingVisitor::where('visitor_id', $visitorId)->first();
        if (!$visitor) {
            return [];
        }
        
        $sessions = $visitor->sessions;
        $pageViews = $visitor->pageViews;
        
        return [
            'visitor' => $visitor,
            'total_sessions' => $sessions->count(),
            'total_page_views' => $pageViews->count(),
            'total_time_on_site' => $sessions->sum('duration'),
            'average_session_duration' => $sessions->avg('duration'),
            'first_visit' => $sessions->min('started_at'),
            'last_visit' => $sessions->max('started_at'),
            'top_pages' => $pageViews->groupBy('page_url')
                ->map->count()
                ->sortDesc()
                ->take(5),
            'conversion_events' => $sessions->sum('form_submissions')
        ];
    }
    
    /**
     * Get or create visitor
     */
    private function getOrCreateVisitor(array $data): ActivityTrackingVisitor
    {
        $visitorId = $data['visitor_id'] ?? Str::uuid()->toString();
        
        return ActivityTrackingVisitor::firstOrCreate(
            ['visitor_id' => $visitorId],
            [
                'user_agent' => $data['user_agent'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'country' => $data['country'] ?? null,
                'region' => $data['region'] ?? null,
                'city' => $data['city'] ?? null,
                'referrer_url' => $data['referrer'] ?? null,
                'first_page_url' => $data['page_url'] ?? null
            ]
        );
    }
    
    /**
     * Get or create session
     */
    private function getOrCreateSession(ActivityTrackingVisitor $visitor, array $data): ActivityTrackingSession
    {
        $sessionId = $data['session_id'] ?? Str::uuid()->toString();
        
        // Check for existing active session (within 30 minutes)
        $existingSession = ActivityTrackingSession::where('visitor_id', $visitor->visitor_id)
            ->where('ended_at', '>', (new \DateTime())->modify('-30 minutes'))
            ->orderBy('started_at', 'desc')
            ->first();
        
        if ($existingSession) {
            return $existingSession;
        }
        
        // Create new session
        return ActivityTrackingSession::create([
            'session_id' => $sessionId,
            'visitor_id' => $visitor->visitor_id,
            'lead_id' => $data['lead_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'started_at' => new \DateTime(),
            'page_views' => 0,
            'events_count' => 0,
            'form_submissions' => 0,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null
        ]);
    }
    
    /**
     * Get activity timeline for entity
     */
    public function getActivityTimeline(string $entityType, string $entityId): array
    {
        $sessions = ActivityTrackingSession::with('pageViews');
        
        if ($entityType === 'lead') {
            $sessions->where('lead_id', $entityId);
        } elseif ($entityType === 'contact') {
            $sessions->where('contact_id', $entityId);
        } else {
            return [];
        }
        
        $sessions = $sessions->orderBy('started_at', 'desc')->get();
        
        $timeline = [];
        foreach ($sessions as $session) {
            $timeline[] = [
                'type' => 'session',
                'timestamp' => $session->started_at,
                'duration' => $session->duration,
                'page_views' => $session->page_views,
                'pages' => $session->pageViews->map(function ($pv) {
                    return [
                        'url' => $pv->page_url,
                        'title' => $pv->page_title,
                        'time_on_page' => $pv->time_on_page
                    ];
                })
            ];
        }
        
        return $timeline;
    }
}