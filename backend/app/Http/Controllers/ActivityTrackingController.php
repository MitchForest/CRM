<?php

namespace App\Http\Controllers;

use App\Models\ActivityTrackingVisitor;
use App\Models\ActivityTrackingSession;
use App\Models\ActivityTrackingPageView;
use App\Services\Tracking\ActivityTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityTrackingController extends Controller
{
    private ActivityTrackingService $trackingService;
    
    public function __construct(ActivityTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }
    
    /**
     * Track page view (public endpoint)
     * POST /api/public/track/pageview
     */
    public function trackPageView(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'visitor_id' => 'sometimes|string',
            'session_id' => 'sometimes|string',
            'page_url' => 'required|string|url',
            'page_title' => 'sometimes|string',
            'referrer' => 'sometimes|string|url',
            'user_agent' => 'sometimes|string'
        ]);
        
        try {
            // Generate IDs if not provided
            $visitorId = $data['visitor_id'] ?? null;
            $sessionId = $data['session_id'] ?? null;
            
            if (empty($visitorId) || in_array($visitorId, ['undefined', 'null'])) {
                $visitorId = 'visitor_' . uniqid() . '_' . time();
            }
            
            if (empty($sessionId) || in_array($sessionId, ['undefined', 'null'])) {
                $sessionId = 'session_' . uniqid() . '_' . time();
            }
            
            // Track the page view
            $result = $this->trackingService->trackPageView(
                $visitorId,
                $sessionId,
                $data['page_url'],
                $data['page_title'] ?? 'Untitled',
                $data['referrer'] ?? null,
                $data['user_agent'] ?? $request->getHeaderLine('User-Agent')
            );
            
            return $this->json($response, [
                'visitor_id' => $result['visitor_id'],
                'session_id' => $result['session_id'],
                'page_view_id' => $result['page_view_id']
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to track page view: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Track generic events (public endpoint)
     * POST /api/public/track/event
     */
    public function trackEvent(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'visitor_id' => 'sometimes|string',
            'session_id' => 'sometimes|string',
            'event' => 'required|string',
            'properties' => 'sometimes|array',
            'timestamp' => 'sometimes|date'
        ]);
        
        try {
            // Ensure visitor ID
            $visitorId = $data['visitor_id'] ?? null;
            if (empty($visitorId) || in_array($visitorId, ['undefined', 'null', 'anonymous'])) {
                $visitorId = 'visitor_' . uniqid() . '_' . time();
            }
            
            // Track the event
            $result = $this->trackingService->trackEvent(
                $visitorId,
                $data['event'],
                $data['properties'] ?? [],
                $data['session_id'] ?? null,
                $data['timestamp'] ?? null
            );
            
            return $this->json($response, [
                'event_id' => $result['event_id'],
                'visitor_id' => $visitorId,
                'status' => 'tracked'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to track event: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Track engagement (public endpoint)
     * POST /api/public/track/engagement
     */
    public function trackEngagement(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'visitor_id' => 'required|string',
            'event_type' => 'required|string',
            'event_data' => 'sometimes|array'
        ]);
        
        try {
            $result = $this->trackingService->trackEngagement(
                $data['visitor_id'],
                $data['event_type'],
                $data['event_data'] ?? []
            );
            
            return $this->json($response, [
                'engagement_id' => $result['engagement_id']
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to track engagement: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Track page exit with metrics (public endpoint)
     * POST /api/public/track/exit
     */
    public function trackPageExit(Request $request, Response $response, array $args): Response
    {
        // Log page exit metrics for analytics
        $metrics = $request->getParsedBody() ?? [];
        error_log('Page exit metrics: ' . json_encode($metrics));
        
        return $this->json($response, ['status' => 'recorded']);
    }
    
    /**
     * Get active visitors (authenticated)
     * GET /api/crm/tracking/visitors/active
     */
    public function getActiveVisitors(Request $request, Response $response, array $args): Response
    {
        try {
            $params = $request->getQueryParams();
            $activeMinutes = intval($params['minutes'] ?? 5);
            
            $visitors = ActivityTrackingVisitor::with(['latestPageView'])
                ->where('last_activity', '>', (new \DateTime())->modify("-{$activeMinutes} minutes")->format('Y-m-d H:i:s'))
                ->orderBy('last_activity', 'desc')
                ->get()
                ->map(function ($visitor) use ($activeMinutes) {
                    $activePages = $visitor->pageViews()
                        ->where('viewed_at', '>', (new \DateTime())->modify("-{$activeMinutes} minutes")->format('Y-m-d H:i:s'))
                        ->count();
                    
                    return [
                        'visitor_id' => $visitor->visitor_id,
                        'first_visit' => $visitor->first_visit,
                        'last_visit' => $visitor->last_activity,
                        'total_visits' => $visitor->total_visits,
                        'active_pages' => $activePages,
                        'current_page' => $visitor->latestPageView ? [
                            'url' => $visitor->latestPageView->page_url,
                            'title' => $visitor->latestPageView->page_title,
                            'timestamp' => $visitor->latestPageView->viewed_at
                        ] : null
                    ];
                });
            
            return $this->json($response, [
                'visitors' => $visitors,
                'total_active' => $visitors->count()
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to get active visitors: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get session details (authenticated)
     * GET /api/crm/tracking/sessions/{session_id}
     */
    public function getSessionDetails(Request $request, Response $response, array $args): Response
    {
        $sessionId = $args['session_id'];
        try {
            $session = ActivityTrackingSession::with(['visitor', 'pageViews'])
                ->where('session_id', $sessionId)
                ->first();
            
            if (!$session) {
                return $this->error($response, 'Session not found', 404);
            }
            
            $pages = $session->pageViews->map(function ($pageView) {
                return [
                    'url' => $pageView->page_url,
                    'title' => $pageView->page_title,
                    'timestamp' => $pageView->viewed_at
                ];
            });
            
            $sessionInfo = [
                'session_id' => $session->session_id,
                'visitor_id' => $session->visitor_id,
                'first_visit' => $session->visitor->first_visit,
                'total_visits' => $session->visitor->total_visits,
                'pages_viewed' => $pages,
                'page_count' => $pages->count(),
                'duration' => $session->duration,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at
            ];
            
            return $this->json($response, $sessionInfo);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to get session details: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get visitor history (authenticated)
     * GET /api/crm/tracking/visitors/{visitor_id}/history
     */
    public function getVisitorHistory(Request $request, Response $response, array $args): Response
    {
        $visitorId = $args['visitor_id'];
        try {
            $visitor = ActivityTrackingVisitor::with(['sessions.pageViews'])
                ->where('visitor_id', $visitorId)
                ->first();
            
            if (!$visitor) {
                return $this->error($response, 'Visitor not found', 404);
            }
            
            $history = $visitor->sessions->map(function ($session) {
                return [
                    'session_id' => $session->session_id,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at,
                    'duration' => $session->duration,
                    'page_count' => $session->page_views,
                    'pages' => $session->pageViews->map(function ($pv) {
                        return [
                            'url' => $pv->page_url,
                            'title' => $pv->page_title,
                            'viewed_at' => $pv->viewed_at
                        ];
                    })
                ];
            });
            
            return $this->json($response, [
                'visitor' => [
                    'visitor_id' => $visitor->visitor_id,
                    'first_visit' => $visitor->first_visit,
                    'last_visit' => $visitor->last_activity,
                    'total_visits' => $visitor->total_visits,
                    'total_page_views' => $visitor->total_page_views,
                    'engagement_score' => $visitor->engagement_score
                ],
                'sessions' => $history
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to get visitor history: ' . $e->getMessage(), 500);
        }
    }
}