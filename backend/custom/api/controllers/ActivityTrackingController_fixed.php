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