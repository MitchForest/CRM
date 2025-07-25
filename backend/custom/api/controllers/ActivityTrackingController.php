<?php
/**
 * Simplified Activity Tracking Controller 
 * Uses SuiteCRM's DB methods
 */

namespace Api\Controllers;

use Api\Controllers\BaseController;
use Api\Request;
use Api\Response;
use Exception;

class ActivityTrackingController extends BaseController
{
    private $db;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Track page view (public endpoint)
     */
    public function trackPageView(Request $request)
    {
        try {
            $body = $request->getData();
            
            // Get fields
            $visitorId = $body['visitor_id'] ?? null;
            $sessionId = $body['session_id'] ?? null;
            $pageUrl = $body['page_url'] ?? null;
            
            // Validate required field
            if (!$pageUrl) {
                return Response::error('Missing required field: page_url', 400);
            }
            
            // Generate visitor ID if not provided or invalid
            if (empty($visitorId) || $visitorId === 'undefined' || $visitorId === 'null') {
                $visitorId = 'visitor_' . uniqid() . '_' . time();
                $isNewVisitor = true;
            } else {
                $isNewVisitor = false;
            }
            
            // Generate session ID if not provided or invalid
            if (empty($sessionId) || $sessionId === 'undefined' || $sessionId === 'null') {
                $sessionId = 'session_' . uniqid() . '_' . time();
            }
            
            // Check if visitor exists (only if not a new visitor)
            if (!$isNewVisitor) {
                $query = "SELECT id FROM activity_tracking_visitors WHERE visitor_id = '{$this->db->quote($visitorId)}' LIMIT 1";
                $result = $this->db->query($query);
                $visitor = $this->db->fetchByAssoc($result);
            } else {
                $visitor = null;
            }
            
            if (!$visitor) {
                // Create new visitor
                $visitorGuid = create_guid();
                $query = "INSERT INTO activity_tracking_visitors 
                         (id, visitor_id, first_visit, last_visit, total_visits, date_created)
                         VALUES ('$visitorGuid', '{$this->db->quote($visitorId)}', NOW(), NOW(), 1, NOW())";
                $this->db->query($query);
            } else {
                // Update visitor
                $query = "UPDATE activity_tracking_visitors 
                         SET last_visit = NOW(), total_visits = total_visits + 1 
                         WHERE id = '{$visitor['id']}'";
                $this->db->query($query);
            }
            
            // Record page view
            $pageViewId = create_guid();
            $pageTitle = $body['title'] ?? 'Untitled';
            
            $query = "INSERT INTO activity_tracking_page_views 
                     (id, visitor_id, session_id, page_url, page_title, timestamp)
                     VALUES ('$pageViewId', '{$this->db->quote($visitorId)}', 
                             '{$this->db->quote($sessionId)}', '{$this->db->quote($pageUrl)}', 
                             '{$this->db->quote($pageTitle)}', NOW())";
            $this->db->query($query);
            
            return Response::success([
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'page_view_id' => $pageViewId
            ]);
            
        } catch (Exception $e) {
            return Response::error('Failed to track page view: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Track engagement (public endpoint)
     */
    public function trackEngagement(Request $request)
    {
        try {
            $body = $request->getData();
            
            // Validate required fields
            $visitorId = $body['visitor_id'] ?? null;
            $eventType = $body['event_type'] ?? null;
            
            if (!$visitorId || !$eventType) {
                return Response::error('Missing required fields', 400);
            }
            
            // Record engagement
            $engagementId = create_guid();
            $eventData = json_encode($body['event_data'] ?? []);
            
            $query = "INSERT INTO activity_tracking_engagements 
                     (id, visitor_id, event_type, event_data, timestamp)
                     VALUES ('$engagementId', '{$this->db->quote($visitorId)}', 
                             '{$this->db->quote($eventType)}', '{$this->db->quote($eventData)}', NOW())";
            $this->db->query($query);
            
            return Response::success([
                'engagement_id' => $engagementId
            ]);
            
        } catch (Exception $e) {
            return Response::error('Failed to track engagement: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Track page exit with metrics (public endpoint) 
     */
    public function trackPageExit(Request $request)
    {
        try {
            $body = $request->getData();
            
            // Log page exit metrics
            error_log("Page exit: " . json_encode($body));
            
            return Response::success(['status' => 'recorded']);
            
        } catch (Exception $e) {
            return Response::error('Failed to track page exit: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Track generic events (public endpoint)
     * Used for chat interactions, form events, and custom tracking
     */
    public function trackEvent(Request $request)
    {
        try {
            $body = $request->getData();
            
            // Get event data
            $visitorId = $body['visitor_id'] ?? null;
            $sessionId = $body['session_id'] ?? null;
            $event = $body['event'] ?? null;
            $properties = $body['properties'] ?? [];
            $timestamp = $body['timestamp'] ?? date('Y-m-d H:i:s');
            
            // Validate required fields
            if (!$event) {
                return Response::error('Event name is required', 400);
            }
            
            // Ensure visitor ID
            if (empty($visitorId) || $visitorId === 'undefined' || $visitorId === 'null' || $visitorId === 'anonymous') {
                $visitorId = 'visitor_' . uniqid() . '_' . time();
            }
            
            // Record event in database
            $eventId = create_guid();
            $eventData = json_encode([
                'event' => $event,
                'properties' => $properties,
                'page_url' => $body['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $this->getClientIp()
            ]);
            
            // Insert into generic events table
            $query = "INSERT INTO activity_tracking_events 
                     (id, visitor_id, session_id, event_name, event_data, timestamp, date_created)
                     VALUES ('{$eventId}', '{$this->db->quote($visitorId)}', 
                             " . ($sessionId ? "'{$this->db->quote($sessionId)}'" : "NULL") . ",
                             '{$this->db->quote($event)}', '{$this->db->quote($eventData)}',
                             '{$this->db->quote($timestamp)}', NOW())";
            $this->db->query($query);
            
            // Handle specific event types
            $this->processSpecificEvent($event, $visitorId, $properties);
            
            // Update visitor engagement score
            $this->updateVisitorEngagement($visitorId, $event);
            
            return Response::success([
                'event_id' => $eventId,
                'visitor_id' => $visitorId,
                'status' => 'tracked'
            ]);
            
        } catch (Exception $e) {
            error_log('Event tracking error: ' . $e->getMessage());
            return Response::error('Failed to track event', 500);
        }
    }
    
    /**
     * Process specific event types
     */
    private function processSpecificEvent($event, $visitorId, $properties)
    {
        switch ($event) {
            case 'chat_opened':
            case 'chat_closed':
            case 'chat_message_sent':
                // Update chat engagement metrics
                $conversationId = $properties['conversation_id'] ?? null;
                if ($conversationId) {
                    $query = "UPDATE ai_chat_conversations 
                             SET last_activity = NOW() 
                             WHERE id = '{$this->db->quote($conversationId)}'";
                    $this->db->query($query);
                }
                break;
                
            case 'form_started':
            case 'form_abandoned':
            case 'form_submitted':
                // Track form interactions
                $formId = $properties['form_id'] ?? null;
                if ($formId) {
                    $this->trackFormInteraction($formId, $event, $visitorId);
                }
                break;
        }
    }
    
    /**
     * Update visitor engagement score based on events
     */
    private function updateVisitorEngagement($visitorId, $event)
    {
        // Define event weights
        $eventWeights = [
            'chat_opened' => 5,
            'chat_message_sent' => 10,
            'form_started' => 3,
            'form_submitted' => 20,
            'page_view' => 1,
            'document_download' => 15,
            'video_played' => 8
        ];
        
        $weight = $eventWeights[$event] ?? 1;
        
        // Update engagement score
        $query = "UPDATE activity_tracking_visitors 
                 SET engagement_score = COALESCE(engagement_score, 0) + {$weight},
                     last_activity = NOW()
                 WHERE visitor_id = '{$this->db->quote($visitorId)}'";
        $this->db->query($query);
    }
    
    /**
     * Track form interactions
     */
    private function trackFormInteraction($formId, $event, $visitorId)
    {
        $id = create_guid();
        $query = "INSERT INTO form_tracking_events 
                 (id, form_id, visitor_id, event_type, date_created)
                 VALUES ('{$id}', '{$this->db->quote($formId)}', 
                         '{$this->db->quote($visitorId)}', '{$this->db->quote($event)}', NOW())";
        $this->db->query($query);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        return '0.0.0.0';
    }
    
    /**
     * Get active visitors
     */
    public function getActiveVisitors(Request $request)
    {
        try {
            // Get visitors active in last 5 minutes
            $query = "SELECT v.*, 
                      (SELECT COUNT(*) FROM activity_tracking_page_views pv 
                       WHERE pv.visitor_id = v.visitor_id 
                       AND pv.timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as active_pages
                      FROM activity_tracking_visitors v
                      WHERE v.last_visit > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                      ORDER BY v.last_visit DESC";
            
            $result = $this->db->query($query);
            $visitors = [];
            
            while ($row = $this->db->fetchByAssoc($result)) {
                // Get current page for each visitor
                $pageQuery = "SELECT page_url, page_title, timestamp 
                             FROM activity_tracking_page_views 
                             WHERE visitor_id = '{$row['visitor_id']}'
                             ORDER BY timestamp DESC 
                             LIMIT 1";
                $pageResult = $this->db->query($pageQuery);
                $currentPage = $this->db->fetchByAssoc($pageResult);
                
                $visitors[] = [
                    'visitor_id' => $row['visitor_id'],
                    'first_visit' => $row['first_visit'],
                    'last_visit' => $row['last_visit'],
                    'total_visits' => (int)$row['total_visits'],
                    'active_pages' => (int)$row['active_pages'],
                    'current_page' => $currentPage ? [
                        'url' => $currentPage['page_url'],
                        'title' => $currentPage['page_title'],
                        'timestamp' => $currentPage['timestamp']
                    ] : null
                ];
            }
            
            return Response::success([
                'visitors' => $visitors,
                'total_active' => count($visitors)
            ]);
            
        } catch (Exception $e) {
            return Response::error('Failed to get active visitors: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get session details
     */
    public function getSessionDetails(Request $request)
    {
        try {
            $sessionId = $request->getParam('session_id');
            
            if (!$sessionId) {
                return Response::error('Session ID is required', 400);
            }
            
            // Get session info from page views
            $query = "SELECT pv.*, v.first_visit, v.total_visits
                     FROM activity_tracking_page_views pv
                     JOIN activity_tracking_visitors v ON v.visitor_id = pv.visitor_id
                     WHERE pv.session_id = '{$this->db->quote($sessionId)}'
                     ORDER BY pv.timestamp ASC";
            
            $result = $this->db->query($query);
            $pages = [];
            $sessionInfo = null;
            
            while ($row = $this->db->fetchByAssoc($result)) {
                if (!$sessionInfo) {
                    $sessionInfo = [
                        'session_id' => $sessionId,
                        'visitor_id' => $row['visitor_id'],
                        'first_visit' => $row['first_visit'],
                        'total_visits' => (int)$row['total_visits']
                    ];
                }
                
                $pages[] = [
                    'url' => $row['page_url'],
                    'title' => $row['page_title'],
                    'timestamp' => $row['timestamp']
                ];
            }
            
            if (!$sessionInfo) {
                return Response::error('Session not found', 404);
            }
            
            $sessionInfo['pages_viewed'] = $pages;
            $sessionInfo['page_count'] = count($pages);
            
            return Response::success($sessionInfo);
            
        } catch (Exception $e) {
            return Response::error('Failed to get session details: ' . $e->getMessage(), 500);
        }
    }
}