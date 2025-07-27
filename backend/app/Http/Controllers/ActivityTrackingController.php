<?php

namespace App\Http\Controllers;

use App\Models\ActivityTrackingVisitor;
use App\Models\ActivityTrackingSession;
use App\Models\ActivityTrackingPageView;
use App\Services\Tracking\ActivityTrackingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class ActivityTrackingController extends Controller
{
    private ?ActivityTrackingService $trackingService = null;
    
    public function __construct()
    {
        parent::__construct();
        // Initialize the tracking service
        $this->trackingService = new ActivityTrackingService();
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
            'page_url' => 'required|string',
            'page_title' => 'sometimes|string',
            'referrer' => 'sometimes|string',
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
            
            // Get or create visitor
            $visitor = ActivityTrackingVisitor::firstOrCreate(
                ['visitor_id' => $visitorId],
                [
                    'first_visit' => new \DateTime(),
                    'last_visit' => new \DateTime(),
                    'visit_count' => 1,
                    'page_view_count' => 0,
                    'referrer' => $data['referrer'] ?? null,
                    'user_agent' => $data['user_agent'] ?? $request->getHeaderLine('User-Agent')
                ]
            );
            
            // Update visitor activity
            $visitor->last_visit = new \DateTime();
            $visitor->page_view_count++;
            $visitor->save();
            
            // Get or create session
            $session = ActivityTrackingSession::firstOrCreate(
                ['session_id' => $sessionId],
                [
                    'visitor_id' => $visitorId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $data['user_agent'] ?? $request->getHeaderLine('User-Agent'),
                    'start_time' => new \DateTime(),
                    'page_count' => 0,
                    'date_entered' => new \DateTime()
                ]
            );
            
            // Update session
            $session->page_count++;
            $session->end_time = new \DateTime();
            $session->save();
            
            // Create page view
            $pageView = ActivityTrackingPageView::create([
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'page_url' => $data['page_url'],
                'page_title' => $data['page_title'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'date_entered' => new \DateTime()
            ]);
            
            return $this->json($response, [
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'page_view_id' => $pageView->id
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
            
            // Track the event using the service
            $result = $this->trackingService->trackEvent([
                'visitor_id' => $visitorId,
                'session_id' => $data['session_id'] ?? null,
                'event_type' => $data['event'],
                'event_data' => $data['properties'] ?? [],
                'timestamp' => $data['timestamp'] ?? null
            ]);
            
            return $this->json($response, $result);
            
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
    
    /**
     * Start session tracking (public endpoint)
     * POST /api/public/track/session/start
     */
    public function startSession(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'visitor_id' => 'sometimes|string',
            'referrer' => 'sometimes|string',
            'user_agent' => 'sometimes|string'
        ]);
        
        try {
            $visitorId = $data['visitor_id'] ?? null;
            if (empty($visitorId) || in_array($visitorId, ['undefined', 'null'])) {
                $visitorId = 'visitor_' . uniqid() . '_' . time();
            }
            
            $sessionId = 'session_' . uniqid() . '_' . time();
            
            $result = $this->trackingService->startSession(
                $visitorId,
                $sessionId,
                $data['referrer'] ?? null,
                $data['user_agent'] ?? $request->getHeaderLine('User-Agent')
            );
            
            return $this->json($response, [
                'visitor_id' => $result['visitor_id'],
                'session_id' => $result['session_id'],
                'status' => 'started'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to start session: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * End session tracking (public endpoint)
     * POST /api/public/track/session/end
     */
    public function endSession(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'session_id' => 'required|string',
            'page_views' => 'sometimes|integer',
            'duration' => 'sometimes|integer'
        ]);
        
        try {
            $result = $this->trackingService->endSession(
                $data['session_id'],
                $data['page_views'] ?? null,
                $data['duration'] ?? null
            );
            
            return $this->json($response, [
                'session_id' => $data['session_id'],
                'status' => 'ended'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to end session: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get tracking configuration (admin endpoint)
     * GET /api/admin/tracking/config
     */
    public function getConfig(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, [
            'config' => [
                'enabled' => true,
                'session_timeout' => 30 * 60, // 30 minutes
                'tracking_domains' => ['localhost', 'sassycrm.com'],
                'excluded_paths' => ['/admin', '/api'],
                'anonymize_ip' => false,
                'track_authenticated_users' => true
            ]
        ]);
    }
    
    /**
     * Update tracking configuration (admin endpoint)
     * PUT /api/admin/tracking/config
     */
    public function updateConfig(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'enabled' => 'sometimes|boolean',
            'session_timeout' => 'sometimes|integer|min:300|max:7200',
            'tracking_domains' => 'sometimes|array',
            'excluded_paths' => 'sometimes|array',
            'anonymize_ip' => 'sometimes|boolean',
            'track_authenticated_users' => 'sometimes|boolean'
        ]);
        
        // In a real implementation, save to config file or database
        return $this->json($response, [
            'message' => 'Configuration updated',
            'config' => $data
        ]);
    }
    
    /**
     * Get sessions list (admin endpoint)
     * GET /api/admin/tracking/sessions
     */
    public function getSessions(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = intval($params['page'] ?? 1);
        $limit = min(intval($params['limit'] ?? 20), 100);
        
        $query = ActivityTrackingSession::with(['visitor']);
        
        if (isset($params['start_date'])) {
            $query->where('started_at', '>=', $params['start_date']);
        }
        
        if (isset($params['end_date'])) {
            $query->where('started_at', '<=', $params['end_date']);
        }
        
        $sessions = $query->orderBy('started_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        
        $data = $sessions->map(function ($session) {
            return [
                'session_id' => $session->session_id,
                'visitor_id' => $session->visitor_id,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'duration' => $session->duration,
                'page_views' => $session->page_views,
                'visitor' => [
                    'first_visit' => $session->visitor->first_visit,
                    'total_visits' => $session->visitor->total_visits
                ]
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'total' => $sessions->total(),
                'page' => $sessions->currentPage(),
                'limit' => $sessions->perPage(),
                'pages' => $sessions->lastPage()
            ]
        ]);
    }
    
    /**
     * Get session details (admin endpoint)
     * GET /api/admin/tracking/sessions/{id}
     */
    public function getSession(Request $request, Response $response, array $args): Response
    {
        // This method already exists as getSessionDetails, just redirecting
        return $this->getSessionDetails($request, $response, $args);
    }
    
    /**
     * Get page views (admin endpoint)
     * GET /api/admin/tracking/page-views
     */
    public function getPageViews(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = intval($params['page'] ?? 1);
        $limit = min(intval($params['limit'] ?? 20), 100);
        
        $query = ActivityTrackingPageView::with(['session', 'visitor']);
        
        if (isset($params['url'])) {
            $query->where('page_url', 'like', '%' . $params['url'] . '%');
        }
        
        $pageViews = $query->orderBy('viewed_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        
        $data = $pageViews->map(function ($pv) {
            return [
                'id' => $pv->id,
                'page_url' => $pv->page_url,
                'page_title' => $pv->page_title,
                'viewed_at' => $pv->viewed_at,
                'session_id' => $pv->session_id,
                'visitor_id' => $pv->visitor_id
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'total' => $pageViews->total(),
                'page' => $pageViews->currentPage(),
                'limit' => $pageViews->perPage(),
                'pages' => $pageViews->lastPage()
            ]
        ]);
    }
    
    /**
     * Get visitors list (admin endpoint)
     * GET /api/admin/tracking/visitors
     */
    public function getVisitors(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = intval($params['page'] ?? 1);
        $limit = min(intval($params['limit'] ?? 20), 100);
        
        $visitors = ActivityTrackingVisitor::with(['lead', 'contact'])
            ->orderBy('last_activity', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        
        $data = $visitors->map(function ($visitor) {
            return [
                'visitor_id' => $visitor->visitor_id,
                'first_visit' => $visitor->first_visit,
                'last_activity' => $visitor->last_activity,
                'total_visits' => $visitor->total_visits,
                'total_page_views' => $visitor->total_page_views,
                'engagement_score' => $visitor->engagement_score,
                'lead' => $visitor->lead ? [
                    'id' => $visitor->lead->id,
                    'name' => $visitor->lead->full_name,
                    'email' => $visitor->lead->email1
                ] : null,
                'contact' => $visitor->contact ? [
                    'id' => $visitor->contact->id,
                    'name' => $visitor->contact->full_name,
                    'email' => $visitor->contact->email1
                ] : null
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'total' => $visitors->total(),
                'page' => $visitors->currentPage(),
                'limit' => $visitors->perPage(),
                'pages' => $visitors->lastPage()
            ]
        ]);
    }
    
    /**
     * Get tracking analytics (admin endpoint)
     * GET /api/admin/tracking/analytics
     */
    public function getTrackingAnalytics(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $startDate = $params['start_date'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
        $endDate = $params['end_date'] ?? (new \DateTime())->format('Y-m-d');
        
        // Get daily stats
        $dailyStats = DB::table('activity_tracking_page_views')
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->select(DB::raw('
                DATE(viewed_at) as date,
                COUNT(*) as page_views,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                COUNT(DISTINCT session_id) as sessions
            '))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Get top pages
        $topPages = DB::table('activity_tracking_page_views')
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->select('page_url', 'page_title', DB::raw('COUNT(*) as views'))
            ->groupBy('page_url', 'page_title')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();
        
        // Get engagement metrics
        $engagementStats = DB::table('activity_tracking_sessions')
            ->whereBetween('started_at', [$startDate, $endDate])
            ->select(DB::raw('
                AVG(duration) as avg_session_duration,
                AVG(page_views) as avg_pages_per_session,
                MAX(duration) as max_session_duration
            '))
            ->first();
        
        return $this->json($response, [
            'data' => [
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'summary' => [
                    'total_page_views' => $dailyStats->sum('page_views'),
                    'unique_visitors' => DB::table('activity_tracking_visitors')
                        ->whereBetween('first_visit', [$startDate, $endDate])
                        ->count(),
                    'total_sessions' => $dailyStats->sum('sessions'),
                    'avg_session_duration' => round($engagementStats->avg_session_duration ?? 0, 2),
                    'avg_pages_per_session' => round($engagementStats->avg_pages_per_session ?? 0, 2)
                ],
                'daily_stats' => $dailyStats,
                'top_pages' => $topPages
            ]
        ]);
    }
    
    /**
     * Get settings (admin endpoint)
     * GET /api/admin/settings/tracking
     */
    public function getSettings(Request $request, Response $response, array $args): Response
    {
        return $this->getConfig($request, $response, $args);
    }
    
    /**
     * Update settings (admin endpoint)
     * PUT /api/admin/settings/tracking
     */
    public function updateSettings(Request $request, Response $response, array $args): Response
    {
        return $this->updateConfig($request, $response, $args);
    }
    
    /**
     * Get tracking script (public endpoint)
     * GET /api/public/tracking-script.js
     */
    public function getTrackingScript(Request $request, Response $response, array $args): Response
    {
        $script = file_get_contents(__DIR__ . '/../../../public/js/tracking.js');
        
        // Replace API endpoint placeholder
        $apiUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        if ($request->getUri()->getPort() && !in_array($request->getUri()->getPort(), [80, 443])) {
            $apiUrl .= ':' . $request->getUri()->getPort();
        }
        $script = str_replace('{{API_URL}}', $apiUrl, $script);
        
        $response->getBody()->write($script);
        return $response
            ->withHeader('Content-Type', 'application/javascript')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
    
    /**
     * Get tracking data for a specific lead
     * GET /api/crm/leads/{id}/tracking
     */
    public function getLeadTracking(Request $request, Response $response, array $args): Response
    {
        $leadId = $args['id'];
        
        // Get all visitors associated with this lead
        $visitors = ActivityTrackingVisitor::where('lead_id', $leadId)
            ->get();
        
        if ($visitors->isEmpty()) {
            return $this->json($response, [
                'data' => [
                    'visitors' => [],
                    'sessions' => [],
                    'page_views' => [],
                    'summary' => [
                        'total_visits' => 0,
                        'total_page_views' => 0,
                        'total_time_spent' => 0,
                        'last_visit' => null
                    ]
                ]
            ]);
        }
        
        $visitorIds = $visitors->pluck('visitor_id')->toArray();
        
        // Get all sessions for these visitors
        $sessions = ActivityTrackingSession::whereIn('visitor_id', $visitorIds)
            ->orderBy('start_time', 'DESC')
            ->limit(50)
            ->get();
        
        // Get recent page views
        $pageViews = ActivityTrackingPageView::whereIn('visitor_id', $visitorIds)
            ->orderBy('date_entered', 'DESC')
            ->limit(100)
            ->get();
        
        // Calculate summary
        $summary = [
            'total_visits' => $visitors->sum('visit_count'),
            'total_page_views' => $visitors->sum('page_view_count'),
            'total_time_spent' => $visitors->sum('total_time_spent'),
            'last_visit' => $visitors->max('last_visit'),
            'first_visit' => $visitors->min('first_visit'),
            'sources' => $visitors->pluck('source')->filter()->unique()->values(),
            'campaigns' => $visitors->pluck('campaign')->filter()->unique()->values()
        ];
        
        return $this->json($response, [
            'data' => [
                'visitors' => $visitors,
                'sessions' => $sessions,
                'page_views' => $pageViews,
                'summary' => $summary
            ]
        ]);
    }
    
    /**
     * Track conversion event (public endpoint)
     * POST /api/public/track/conversion
     */
    public function trackConversion(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'visitor_id' => 'required|string',
            'session_id' => 'required|string',
            'conversion_type' => 'required|string',
            'conversion_value' => 'sometimes|numeric',
            'metadata' => 'sometimes|array'
        ]);
        
        try {
            // Track as special event
            return $this->trackEvent($request->withParsedBody([
                'visitor_id' => $data['visitor_id'],
                'session_id' => $data['session_id'],
                'event' => 'conversion:' . $data['conversion_type'],
                'properties' => [
                    'conversion_type' => $data['conversion_type'],
                    'value' => $data['conversion_value'] ?? null,
                    'metadata' => $data['metadata'] ?? []
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]), $response, $args);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to track conversion: ' . $e->getMessage(), 500);
        }
    }
}