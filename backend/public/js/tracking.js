// Activity Tracking Script
(function() {
    'use strict';
    
    var API_ENDPOINT = '{{API_ENDPOINT}}';
    var visitorId = localStorage.getItem('visitor_id') || generateId();
    var sessionId = sessionStorage.getItem('session_id') || generateId();
    
    function generateId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0,
                v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    function trackPageView() {
        var data = {
            visitor_id: visitorId,
            session_id: sessionId,
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            user_agent: navigator.userAgent
        };
        
        fetch(API_ENDPOINT + '/api/public/track/pageview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        }).then(function(response) {
            return response.json();
        }).then(function(result) {
            if (result.visitor_id) {
                localStorage.setItem('visitor_id', result.visitor_id);
            }
            if (result.session_id) {
                sessionStorage.setItem('session_id', result.session_id);
            }
        }).catch(function(error) {
            console.error('Tracking error:', error);
        });
    }
    
    // Track page view on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackPageView);
    } else {
        trackPageView();
    }
    
    // Public API
    window.ActivityTracking = {
        trackEvent: function(event, properties) {
            var data = {
                visitor_id: visitorId,
                session_id: sessionId,
                event: event,
                properties: properties || {}
            };
            
            fetch(API_ENDPOINT + '/api/public/track/event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
        }
    };
})();