/**
 * SuiteCRM Activity Tracking Script
 * Tracks visitor behavior and links to CRM leads
 * 
 * Usage:
 * <script>
 *   window.SUITECRM_TRACKING = {
 *     api_url: 'https://your-crm.com',
 *     lead_id: 'optional-known-lead-id'
 *   };
 * </script>
 * <script src="https://your-crm.com/public/js/tracking.js" async></script>
 */

(function() {
    'use strict';
    
    // Configuration
    const config = window.SUITECRM_TRACKING || {};
    const API_BASE = config.api_url || 'http://localhost:8080';
    const API_ENDPOINT = '/api/v8/track';
    
    // Session data
    let sessionId = getOrCreateSessionId();
    let visitorId = getOrCreateVisitorId();
    let pageStartTime = Date.now();
    let lastActivityTime = Date.now();
    let scrollDepth = 0;
    let clickCount = 0;
    
    // Initialize tracking
    init();
    
    function init() {
        // Track page view
        trackPageView();
        
        // Set up event listeners
        setupEventListeners();
        
        // Send heartbeat every 30 seconds
        setInterval(sendHeartbeat, 30000);
        
        // Track when user leaves
        window.addEventListener('beforeunload', trackPageExit);
    }
    
    function getOrCreateSessionId() {
        let sessionId = sessionStorage.getItem('suitecrm_session_id');
        if (!sessionId) {
            sessionId = generateUUID();
            sessionStorage.setItem('suitecrm_session_id', sessionId);
        }
        return sessionId;
    }
    
    function getOrCreateVisitorId() {
        let visitorId = localStorage.getItem('suitecrm_visitor_id');
        if (!visitorId) {
            visitorId = generateUUID();
            localStorage.setItem('suitecrm_visitor_id', visitorId);
        }
        return visitorId;
    }
    
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    async function trackPageView() {
        const data = {
            session_id: sessionId,
            visitor_id: visitorId,
            lead_id: config.lead_id || null,
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            screen: {
                width: screen.width,
                height: screen.height
            }
        };
        
        try {
            await sendTrackingData('pageview', data);
        } catch (error) {
            console.error('Tracking error:', error);
        }
    }
    
    function setupEventListeners() {
        // Track scroll depth
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateScrollDepth();
                    ticking = false;
                });
                ticking = true;
            }
        });
        
        // Track clicks
        document.addEventListener('click', (e) => {
            clickCount++;
            
            // Track specific elements
            const target = e.target;
            if (target.matches('a, button, [data-track]')) {
                trackEngagement('click', {
                    element: target.tagName,
                    text: target.textContent?.substring(0, 50),
                    href: target.href,
                    data_track: target.dataset.track
                });
            }
        });
        
        // Track form interactions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('form')) {
                trackEngagement('form_submit', {
                    form_id: e.target.id,
                    form_name: e.target.name
                });
            }
        });
        
        // Track time on page
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                sendHeartbeat();
            } else {
                lastActivityTime = Date.now();
            }
        });
    }
    
    function updateScrollDepth() {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        const currentScrollDepth = Math.round((scrollTop + windowHeight) / documentHeight * 100);
        scrollDepth = Math.max(scrollDepth, currentScrollDepth);
    }
    
    async function trackEngagement(type, data) {
        const engagementData = {
            session_id: sessionId,
            visitor_id: visitorId,
            type: type,
            data: data,
            url: window.location.href,
            timestamp: new Date().toISOString()
        };
        
        try {
            await sendTrackingData('engagement', engagementData);
        } catch (error) {
            console.error('Engagement tracking error:', error);
        }
    }
    
    async function sendHeartbeat() {
        const timeOnPage = Math.round((Date.now() - pageStartTime) / 1000);
        
        const data = {
            session_id: sessionId,
            visitor_id: visitorId,
            url: window.location.href,
            time_on_page: timeOnPage,
            scroll_depth: scrollDepth,
            click_count: clickCount,
            is_active: document.hasFocus()
        };
        
        try {
            await sendTrackingData('heartbeat', data);
        } catch (error) {
            console.error('Heartbeat error:', error);
        }
    }
    
    function trackPageExit() {
        const timeOnPage = Math.round((Date.now() - pageStartTime) / 1000);
        
        // Use sendBeacon for reliable delivery
        const data = {
            session_id: sessionId,
            visitor_id: visitorId,
            url: window.location.href,
            time_on_page: timeOnPage,
            scroll_depth: scrollDepth,
            click_count: clickCount,
            exit_time: new Date().toISOString()
        };
        
        const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
        navigator.sendBeacon(`${API_BASE}${API_ENDPOINT}/session-end`, blob);
    }
    
    async function sendTrackingData(endpoint, data) {
        const response = await fetch(`${API_BASE}${API_ENDPOINT}/${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
            // Don't wait for response
            keepalive: true
        });
        
        if (!response.ok) {
            throw new Error(`Tracking failed: ${response.status}`);
        }
    }
    
    // Utility functions for external use
    window.SuiteCRMTracking = {
        // Track custom events
        trackEvent: function(eventName, eventData) {
            trackEngagement('custom', {
                event_name: eventName,
                event_data: eventData
            });
        },
        
        // Identify a lead
        identify: function(leadId, leadData) {
            config.lead_id = leadId;
            trackEngagement('identify', {
                lead_id: leadId,
                lead_data: leadData
            });
        },
        
        // Track conversion
        trackConversion: function(conversionType, conversionData) {
            sendTrackingData('conversion', {
                session_id: sessionId,
                visitor_id: visitorId,
                lead_id: config.lead_id,
                type: conversionType,
                data: conversionData,
                timestamp: new Date().toISOString()
            });
        }
    };
    
})();