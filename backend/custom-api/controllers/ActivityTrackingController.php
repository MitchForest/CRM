<?php
/**
 * Activity Tracking Controller - Website visitor tracking and analytics
 * Phase 3 Implementation
 */

namespace Api\Controllers;

use Api\Controllers\BaseController;
use Api\Request;
use Api\Response;
use Exception;

class ActivityTrackingController extends BaseController
{
    private $db;
    private $config;
    
    public function __construct()
    {
        parent::__construct();
        
        global $db;
        $this->db = $db;
        
        // Load configuration
        $this->config = require(__DIR__ . '/../../suitecrm-custom/config/ai_config.php');
    }
    
    /**
     * Track page view (public endpoint)
     * POST /api/v8/track/pageview
     */
    public function trackPageView(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            
            // Validate required fields
            $visitorId = $body['visitor_id'] ?? null;
            $sessionId = $body['session_id'] ?? null;
            $pageUrl = $body['page_url'] ?? null;
            
            if (!$visitorId || !$sessionId || !$pageUrl) {
                return $response->withJson([
                    'error' => 'Missing required tracking data'
                ], 400);
            }
            
            // Get or create visitor
            $visitor = $this->getOrCreateVisitor($visitorId, $body);
            
            // Get or create session
            $session = $this->getOrCreateSession($visitorId, $sessionId, $body);
            
            // Check if page is high-value
            $isHighValue = $this->isHighValuePage($pageUrl);
            
            // Record page view
            $pageViewId = $this->generateUUID();
            
            $query = "INSERT INTO activity_tracking_page_views 
                     (id, visitor_id, session_id, page_url, page_title, 
                      referrer_url, time_on_page, scroll_depth, clicks, 
                      is_high_value, date_created)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $pageViewId,
                $visitorId,
                $sessionId,
                $pageUrl,
                $body['page_title'] ?? '',
                $body['referrer'] ?? '',
                0, // Will be updated on next page view or session end
                0, // Will be updated via engagement endpoint
                0, // Will be updated via engagement endpoint
                (int)$isHighValue
            ]);
            
            // Update session
            $this->updateSession($sessionId, [
                'page_count' => $session['page_count'] + 1,
                'last_activity' => date('Y-m-d H:i:s')
            ]);
            
            // Update visitor
            $this->updateVisitor($visitorId, [
                'total_page_views' => $visitor['total_page_views'] + 1,
                'last_visit' => date('Y-m-d H:i:s')
            ]);
            
            // Check for lead association
            if (!empty($body['email'])) {
                $this->associateVisitorWithLead($visitorId, $body['email']);
            }
            
            // Calculate engagement score
            $engagementScore = $this->calculateEngagementScore($visitorId);
            
            return $response->withJson([
                'data' => [
                    'tracked' => true,
                    'visitor_id' => $visitorId,
                    'session_id' => $sessionId,
                    'is_high_value' => $isHighValue,
                    'engagement_score' => $engagementScore
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Track page view error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to track page view'
            ], 500);
        }
    }
    
    /**
     * Update engagement metrics
     * POST /api/v8/track/engagement
     */
    public function trackEngagement(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            
            $visitorId = $body['visitor_id'] ?? null;
            $sessionId = $body['session_id'] ?? null;
            $pageUrl = $body['page_url'] ?? null;
            
            if (!$visitorId || !$sessionId || !$pageUrl) {
                return $response->withJson([
                    'error' => 'Missing required tracking data'
                ], 400);
            }
            
            // Update page view metrics
            $query = "UPDATE activity_tracking_page_views 
                     SET time_on_page = ?, 
                         scroll_depth = ?, 
                         clicks = ?
                     WHERE visitor_id = ? 
                     AND session_id = ? 
                     AND page_url = ?
                     ORDER BY date_created DESC 
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                (int)($body['time_on_page'] ?? 0),
                (int)($body['scroll_depth'] ?? 0),
                (int)($body['clicks'] ?? 0),
                $visitorId,
                $sessionId,
                $pageUrl
            ]);
            
            // Update session duration
            if (!empty($body['session_duration'])) {
                $this->updateSession($sessionId, [
                    'duration' => (int)$body['session_duration']
                ]);
            }
            
            // Update visitor total time
            if (!empty($body['time_on_page'])) {
                $query = "UPDATE activity_tracking_visitors 
                         SET total_time_spent = total_time_spent + ? 
                         WHERE visitor_id = ?";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    (int)$body['time_on_page'],
                    $visitorId
                ]);
            }
            
            // Recalculate engagement score
            $engagementScore = $this->calculateEngagementScore($visitorId);
            
            return $response->withJson([
                'data' => [
                    'updated' => true,
                    'engagement_score' => $engagementScore
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Track engagement error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to track engagement'
            ], 500);
        }
    }
    
    /**
     * Track conversion event
     * POST /api/v8/track/conversion
     */
    public function trackConversion(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            
            $visitorId = $body['visitor_id'] ?? null;
            $sessionId = $body['session_id'] ?? null;
            $event = $body['event'] ?? null;
            
            if (!$visitorId || !$sessionId || !$event) {
                return $response->withJson([
                    'error' => 'Missing required conversion data'
                ], 400);
            }
            
            // Update session with conversion
            $query = "UPDATE activity_tracking_sessions 
                     SET conversion_event = ? 
                     WHERE session_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$event, $sessionId]);
            
            // If it's a form submission, link to lead
            if ($event === 'form_submit' && !empty($body['form_id'])) {
                $this->linkFormSubmissionToVisitor($visitorId, $body['form_id']);
            }
            
            // Boost engagement score for conversions
            $query = "UPDATE activity_tracking_visitors 
                     SET engagement_score = LEAST(engagement_score + 20, 100) 
                     WHERE visitor_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitorId]);
            
            return $response->withJson([
                'data' => [
                    'tracked' => true,
                    'conversion' => $event
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Track conversion error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to track conversion'
            ], 500);
        }
    }
    
    /**
     * End session
     * POST /api/v8/track/session-end
     */
    public function endSession(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            $sessionId = $body['session_id'] ?? null;
            
            if (!$sessionId) {
                return $response->withJson([
                    'error' => 'Session ID is required'
                ], 400);
            }
            
            // Update session end time
            $query = "UPDATE activity_tracking_sessions 
                     SET end_time = NOW(), 
                         duration = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                         bounce = CASE WHEN page_count = 1 THEN 1 ELSE 0 END
                     WHERE session_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$sessionId]);
            
            return $response->withJson([
                'data' => [
                    'session_ended' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('End session error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to end session'
            ], 500);
        }
    }
    
    /**
     * Get visitor analytics
     * GET /api/v8/analytics/visitors
     */
    public function getVisitorAnalytics(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $params['date_to'] ?? date('Y-m-d');
            $leadId = $params['lead_id'] ?? null;
            
            $where = "WHERE v.last_visit BETWEEN ? AND ?";
            $queryParams = [$dateFrom, $dateTo];
            
            if ($leadId) {
                $where .= " AND v.lead_id = ?";
                $queryParams[] = $leadId;
            }
            
            // Get visitor metrics
            $query = "SELECT 
                        COUNT(DISTINCT v.visitor_id) as unique_visitors,
                        SUM(v.total_visits) as total_visits,
                        SUM(v.total_page_views) as total_page_views,
                        AVG(v.total_time_spent) as avg_time_spent,
                        AVG(v.engagement_score) as avg_engagement_score
                     FROM activity_tracking_visitors v
                     $where";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($queryParams);
            $metrics = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get top pages
            $query = "SELECT 
                        pv.page_url,
                        pv.page_title,
                        COUNT(*) as views,
                        AVG(pv.time_on_page) as avg_time,
                        AVG(pv.scroll_depth) as avg_scroll_depth
                     FROM activity_tracking_page_views pv
                     JOIN activity_tracking_visitors v ON pv.visitor_id = v.visitor_id
                     $where
                     GROUP BY pv.page_url, pv.page_title
                     ORDER BY views DESC
                     LIMIT 10";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($queryParams);
            $topPages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get conversion funnel
            $query = "SELECT 
                        s.conversion_event,
                        COUNT(*) as count
                     FROM activity_tracking_sessions s
                     JOIN activity_tracking_visitors v ON s.visitor_id = v.visitor_id
                     $where
                     AND s.conversion_event IS NOT NULL
                     GROUP BY s.conversion_event";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($queryParams);
            $conversions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $response->withJson([
                'data' => [
                    'metrics' => $metrics,
                    'top_pages' => $topPages,
                    'conversions' => $conversions,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Get visitor analytics error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get lead activity timeline
     * GET /api/v8/analytics/leads/{id}/activity
     */
    public function getLeadActivity(Request $request, Response $response, array $args)
    {
        try {
            $leadId = $args['id'] ?? null;
            if (!$leadId) {
                return $response->withJson([
                    'error' => 'Lead ID is required'
                ], 400);
            }
            
            // Get all visitors associated with this lead
            $query = "SELECT visitor_id FROM activity_tracking_visitors WHERE lead_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId]);
            $visitorIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($visitorIds)) {
                return $response->withJson([
                    'data' => [
                        'lead_id' => $leadId,
                        'visitors' => [],
                        'sessions' => [],
                        'page_views' => []
                    ]
                ]);
            }
            
            // Get visitor details
            $placeholders = str_repeat('?,', count($visitorIds) - 1) . '?';
            
            $query = "SELECT * FROM activity_tracking_visitors 
                     WHERE visitor_id IN ($placeholders)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($visitorIds);
            $visitors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get sessions
            $query = "SELECT s.*, v.lead_id 
                     FROM activity_tracking_sessions s
                     JOIN activity_tracking_visitors v ON s.visitor_id = v.visitor_id
                     WHERE s.visitor_id IN ($placeholders)
                     ORDER BY s.start_time DESC
                     LIMIT 50";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($visitorIds);
            $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get recent page views
            $query = "SELECT pv.*, v.lead_id 
                     FROM activity_tracking_page_views pv
                     JOIN activity_tracking_visitors v ON pv.visitor_id = v.visitor_id
                     WHERE pv.visitor_id IN ($placeholders)
                     ORDER BY pv.date_created DESC
                     LIMIT 100";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($visitorIds);
            $pageViews = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Build timeline
            $timeline = $this->buildActivityTimeline($sessions, $pageViews);
            
            return $response->withJson([
                'data' => [
                    'lead_id' => $leadId,
                    'visitors' => $visitors,
                    'sessions' => $sessions,
                    'timeline' => $timeline,
                    'summary' => [
                        'total_visits' => array_sum(array_column($visitors, 'total_visits')),
                        'total_page_views' => array_sum(array_column($visitors, 'total_page_views')),
                        'total_time_spent' => array_sum(array_column($visitors, 'total_time_spent')),
                        'avg_engagement_score' => round(array_sum(array_column($visitors, 'engagement_score')) / count($visitors))
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Get lead activity error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get lead activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get tracking pixel (for emails)
     * GET /api/v8/track/pixel/{tracking_id}.gif
     */
    public function trackingPixel(Request $request, Response $response, array $args)
    {
        try {
            $trackingId = $args['tracking_id'] ?? null;
            
            if ($trackingId) {
                // Log email open
                // This would typically update email campaign statistics
                error_log("Email opened: $trackingId");
            }
            
            // Return 1x1 transparent GIF
            $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            
            return $response
                ->withHeader('Content-Type', 'image/gif')
                ->withHeader('Content-Length', strlen($gif))
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->withHeader('Pragma', 'no-cache')
                ->write($gif);
                
        } catch (Exception $e) {
            error_log('Tracking pixel error: ' . $e->getMessage());
            // Still return the pixel even on error
            $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            return $response->withHeader('Content-Type', 'image/gif')->write($gif);
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function getOrCreateVisitor($visitorId, $data)
    {
        $query = "SELECT * FROM activity_tracking_visitors WHERE visitor_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$visitorId]);
        $visitor = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$visitor) {
            // Parse user agent
            $userAgent = $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
            $browserInfo = $this->parseBrowserInfo($userAgent);
            
            $id = $this->generateUUID();
            
            $query = "INSERT INTO activity_tracking_visitors 
                     (id, visitor_id, first_visit, last_visit, total_visits, 
                      browser, device_type, referrer_source, utm_source, 
                      utm_medium, utm_campaign, date_modified)
                     VALUES (?, ?, NOW(), NOW(), 1, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $id,
                $visitorId,
                $browserInfo['browser'],
                $browserInfo['device'],
                $this->parseReferrerSource($data['referrer'] ?? ''),
                $data['utm_source'] ?? null,
                $data['utm_medium'] ?? null,
                $data['utm_campaign'] ?? null
            ]);
            
            return [
                'id' => $id,
                'visitor_id' => $visitorId,
                'total_visits' => 1,
                'total_page_views' => 0,
                'total_time_spent' => 0,
                'engagement_score' => 0
            ];
        }
        
        // Update visit count
        $query = "UPDATE activity_tracking_visitors 
                 SET total_visits = total_visits + 1, 
                     last_visit = NOW(),
                     date_modified = NOW()
                 WHERE visitor_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$visitorId]);
        
        return $visitor;
    }
    
    private function getOrCreateSession($visitorId, $sessionId, $data)
    {
        $query = "SELECT * FROM activity_tracking_sessions WHERE session_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$session) {
            $id = $this->generateUUID();
            
            $query = "INSERT INTO activity_tracking_sessions 
                     (id, visitor_id, session_id, ip_address, user_agent, 
                      start_time, page_count, date_created)
                     VALUES (?, ?, ?, ?, ?, NOW(), 0, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $id,
                $visitorId,
                $sessionId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            return [
                'id' => $id,
                'session_id' => $sessionId,
                'page_count' => 0
            ];
        }
        
        return $session;
    }
    
    private function updateSession($sessionId, $updates)
    {
        $sets = [];
        $params = [];
        
        foreach ($updates as $field => $value) {
            $sets[] = "$field = ?";
            $params[] = $value;
        }
        
        $params[] = $sessionId;
        
        $query = "UPDATE activity_tracking_sessions 
                 SET " . implode(', ', $sets) . " 
                 WHERE session_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
    }
    
    private function updateVisitor($visitorId, $updates)
    {
        $sets = [];
        $params = [];
        
        foreach ($updates as $field => $value) {
            $sets[] = "$field = ?";
            $params[] = $value;
        }
        
        $sets[] = "date_modified = NOW()";
        $params[] = $visitorId;
        
        $query = "UPDATE activity_tracking_visitors 
                 SET " . implode(', ', $sets) . " 
                 WHERE visitor_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
    }
    
    private function isHighValuePage($url)
    {
        $highValuePages = $this->config['activity_tracking']['high_value_pages'];
        
        foreach ($highValuePages as $page) {
            if (strpos($url, $page) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function calculateEngagementScore($visitorId)
    {
        // Get visitor stats
        $query = "SELECT total_visits, total_page_views, total_time_spent 
                 FROM activity_tracking_visitors 
                 WHERE visitor_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$visitorId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$stats) {
            return 0;
        }
        
        // Get high-value page views
        $query = "SELECT COUNT(*) as high_value_views 
                 FROM activity_tracking_page_views 
                 WHERE visitor_id = ? AND is_high_value = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$visitorId]);
        $highValueViews = $stmt->fetchColumn();
        
        // Calculate score (0-100)
        $score = 0;
        
        // Visit frequency (max 25 points)
        $score += min($stats['total_visits'] * 5, 25);
        
        // Page depth (max 25 points)
        $avgPagesPerVisit = $stats['total_page_views'] / max($stats['total_visits'], 1);
        $score += min($avgPagesPerVisit * 5, 25);
        
        // Time engagement (max 25 points)
        $avgTimePerVisit = $stats['total_time_spent'] / max($stats['total_visits'], 1);
        $score += min($avgTimePerVisit / 60, 25); // 1 point per minute, max 25
        
        // High-value actions (max 25 points)
        $score += min($highValueViews * 5, 25);
        
        // Update visitor
        $query = "UPDATE activity_tracking_visitors 
                 SET engagement_score = ? 
                 WHERE visitor_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([round($score), $visitorId]);
        
        return round($score);
    }
    
    private function associateVisitorWithLead($visitorId, $email)
    {
        // Find lead by email
        $query = "SELECT id FROM leads WHERE email = ? AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        $leadId = $stmt->fetchColumn();
        
        if ($leadId) {
            // Update visitor
            $query = "UPDATE activity_tracking_visitors 
                     SET lead_id = ? 
                     WHERE visitor_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId, $visitorId]);
        }
    }
    
    private function linkFormSubmissionToVisitor($visitorId, $formId)
    {
        // Find recent submission
        $query = "SELECT lead_id FROM form_builder_submissions 
                 WHERE form_id = ? 
                 AND date_submitted >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY date_submitted DESC 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$formId]);
        $leadId = $stmt->fetchColumn();
        
        if ($leadId) {
            $this->associateVisitorWithLead($visitorId, null);
        }
    }
    
    private function parseBrowserInfo($userAgent)
    {
        $browser = 'Unknown';
        $device = 'desktop';
        
        // Simple browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        }
        
        // Simple device detection
        if (strpos($userAgent, 'Mobile') !== false) {
            $device = 'mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false || strpos($userAgent, 'iPad') !== false) {
            $device = 'tablet';
        }
        
        return [
            'browser' => $browser,
            'device' => $device
        ];
    }
    
    private function parseReferrerSource($referrer)
    {
        if (empty($referrer)) {
            return 'direct';
        }
        
        $host = parse_url($referrer, PHP_URL_HOST);
        
        // Common referrer sources
        if (strpos($host, 'google') !== false) {
            return 'google';
        } elseif (strpos($host, 'facebook') !== false) {
            return 'facebook';
        } elseif (strpos($host, 'linkedin') !== false) {
            return 'linkedin';
        } elseif (strpos($host, 'twitter') !== false) {
            return 'twitter';
        }
        
        return 'other';
    }
    
    private function buildActivityTimeline($sessions, $pageViews)
    {
        $timeline = [];
        
        // Add sessions to timeline
        foreach ($sessions as $session) {
            $timeline[] = [
                'type' => 'session',
                'timestamp' => $session['start_time'],
                'data' => [
                    'duration' => $session['duration'],
                    'page_count' => $session['page_count'],
                    'conversion' => $session['conversion_event']
                ]
            ];
        }
        
        // Add significant page views
        foreach ($pageViews as $pv) {
            if ($pv['is_high_value'] || $pv['time_on_page'] > 60) {
                $timeline[] = [
                    'type' => 'page_view',
                    'timestamp' => $pv['date_created'],
                    'data' => [
                        'url' => $pv['page_url'],
                        'title' => $pv['page_title'],
                        'time_on_page' => $pv['time_on_page'],
                        'is_high_value' => $pv['is_high_value']
                    ]
                ];
            }
        }
        
        // Sort by timestamp
        usort($timeline, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($timeline, 0, 50); // Limit to 50 most recent
    }
    
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}