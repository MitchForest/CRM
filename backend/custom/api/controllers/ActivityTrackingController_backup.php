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
            
            // Validate required fields
            $visitorId = $body['visitor_id'] ?? null;
            $pageUrl = $body['page_url'] ?? null;
            
            if (!$visitorId || !$pageUrl) {
                return Response::error('Missing required tracking data: visitor_id and page_url are required', 400);
            }
            
            // Check if visitor exists
            $query = "SELECT id FROM activity_tracking_visitors WHERE visitor_id = '{$this->db->quote($visitorId)}' LIMIT 1";
            $result = $this->db->query($query);
            $visitor = $this->db->fetchByAssoc($result);
            
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
            $sessionId = $body['session_id'] ?? 'default_session';
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
            
            return Response::success([
                'message' => 'Engagement tracked'
            ]);
            
        } catch (Exception $e) {
            return new Response([
                'error' => 'Failed to track engagement'
            ], 500);
        }
    }
    
    /**
     * Track conversion (public endpoint)
     */
    public function trackConversion(Request $request)
    {
        try {
            $body = $request->getData();
            
            $visitorId = $body['visitor_id'] ?? null;
            $event = $body['event'] ?? null;
            $value = $body['value'] ?? null;
            
            if (!$visitorId || !$event) {
                return Response::error('Missing required fields: visitor_id and event are required', 400);
            }
            
            // Record conversion
            $conversionId = create_guid();
            $query = "INSERT INTO activity_tracking_conversions 
                     (id, visitor_id, event_type, event_value, timestamp, date_created)
                     VALUES ('$conversionId', '{$this->db->quote($visitorId)}', 
                             '{$this->db->quote($event)}', '{$this->db->quote($value)}', 
                             NOW(), NOW())";
            $this->db->query($query);
            
            return Response::success([
                'conversion_id' => $conversionId
            ]);
            
        } catch (Exception $e) {
            return Response::error('Failed to track conversion: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * End session (public endpoint)
     */
    public function endSession(Request $request)
    {
        return Response::success([
            'message' => 'Session ended'
        ]);
    }
    
    /**
     * Get visitor analytics
     */
    public function getVisitorAnalytics(Request $request)
    {
        try {
            // Simple analytics
            $query = "SELECT COUNT(DISTINCT visitor_id) as unique_visitors, 
                            COUNT(*) as total_page_views
                     FROM activity_tracking_page_views 
                     WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $result = $this->db->query($query);
            $analytics = $this->db->fetchByAssoc($result);
            
            return Response::success([
                'analytics' => $analytics
            ]);
            
        } catch (Exception $e) {
            return Response::error('Failed to get analytics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Tracking pixel
     */
    public function trackingPixel(Request $request, array $args)
    {
        
        // Return 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        
        // Return GIF with proper headers
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $gif;
        exit;
    }
}