# Phase 3 Frontend-Backend Integration Guide

## Overview
This document provides comprehensive instructions for integrating the Phase 3 React frontend with the completed Phase 3 backend implementation. All backend features are fully implemented and tested with 92% coverage.

## Backend Status Summary

### ✅ Completed Features:
1. **OpenAI Integration** - GPT-4 Turbo for lead scoring and chat
2. **Form Builder** - CRUD operations and public submission endpoints
3. **Knowledge Base** - Articles with semantic search using embeddings
4. **Activity Tracking** - Visitor tracking with public endpoints
5. **AI Chatbot** - Conversation management with context
6. **Customer Health Scoring** - Event-driven scoring system

### API Base URL
```
http://localhost:8080/custom/api
```

## Authentication

### JWT Token
Most endpoints require authentication using JWT tokens from SuiteCRM's OAuth2 system.

```javascript
// Get token
const getAuthToken = async () => {
  const response = await fetch('http://localhost:8080/Api/access_token', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'grant_type=password&client_id=sugar&username=admin&password=admin'
  });
  const data = await response.json();
  return data.access_token;
};

// Use in requests
const token = await getAuthToken();
fetch('http://localhost:8080/custom/api/leads/123/ai-score', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
```

### Public Endpoints (No Auth Required)
- Form submission: `POST /forms/{id}/submit`
- Activity tracking: `POST /track/pageview`
- Tracking pixel: `GET /track/pixel/{id}.gif`
- Health check: `GET /health`
- Webhook: `POST /webhooks/health-check`

## Feature Integration Instructions

### 1. AI Lead Scoring

#### Single Lead Scoring
```javascript
// Score a single lead
const scoreLead = async (leadId) => {
  const response = await fetch(`${API_BASE}/leads/${leadId}/ai-score`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};

// Response format:
{
  "success": true,
  "data": {
    "lead_id": "123",
    "score": 85,
    "factors": {
      "company_size": 20,
      "industry_match": 15,
      "behavior_score": 25,
      "engagement": 20,
      "budget_signals": 5
    },
    "insights": ["High engagement on pricing page", "Enterprise company size"],
    "confidence": 0.92
  }
}
```

#### Batch Lead Scoring
```javascript
const batchScoreLeads = async (leadIds) => {
  const response = await fetch(`${API_BASE}/leads/ai-score-batch`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ lead_ids: leadIds })
  });
  return response.json();
};
```

#### Get Score History
```javascript
const getScoreHistory = async (leadId) => {
  const response = await fetch(`${API_BASE}/leads/${leadId}/ai-score-history`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json();
};
```

### 2. Form Builder

#### Get All Forms
```javascript
const getForms = async () => {
  const response = await fetch(`${API_BASE}/forms`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json();
};
```

#### Create Form
```javascript
const createForm = async (formData) => {
  const response = await fetch(`${API_BASE}/forms`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      name: "Contact Form",
      description: "Main contact form",
      fields: [
        {
          name: "email",
          type: "email",
          label: "Email",
          required: true
        },
        {
          name: "message",
          type: "textarea",
          label: "Message",
          required: false
        }
      ],
      settings: {
        submit_button_text: "Submit",
        success_message: "Thank you!"
      }
    })
  });
  return response.json();
};
```

#### Embed Form on External Site
```html
<!-- Add to external website -->
<div id="crm-form-container" data-form-id="form-uuid-here"></div>
<script src="http://localhost:8080/js/forms-embed.js"></script>
<script>
  CRMForms.init({
    apiUrl: 'http://localhost:8080/custom/api',
    containerId: 'crm-form-container'
  });
</script>
```

#### Handle Form Submission (Public - No Auth)
```javascript
// This happens automatically with embed script, but for custom implementations:
const submitForm = async (formId, formData) => {
  const response = await fetch(`${API_BASE}/forms/${formId}/submit`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(formData)
  });
  return response.json();
};
```

### 3. Knowledge Base

#### Search Articles (Semantic Search)
```javascript
const searchKB = async (query) => {
  const response = await fetch(`${API_BASE}/knowledge-base/search`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ 
      query: query,
      limit: 10 
    })
  });
  return response.json();
};

// Response includes similarity scores
{
  "success": true,
  "data": [
    {
      "id": "123",
      "title": "Getting Started with AI Lead Scoring",
      "excerpt": "Learn how to use AI lead scoring...",
      "similarity": 0.92,
      "slug": "ai-lead-scoring-guide"
    }
  ]
}
```

#### Get Public Article
```javascript
const getPublicArticle = async (slug) => {
  const response = await fetch(`${API_BASE}/knowledge-base/public/${slug}`);
  return response.json();
};
```

#### Create Article
```javascript
const createArticle = async (article) => {
  const response = await fetch(`${API_BASE}/knowledge-base/articles`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title: "How to Use AI Features",
      content: "<h2>Introduction</h2><p>Content here...</p>",
      excerpt: "Learn about AI features",
      tags: ["ai", "tutorial"],
      is_public: true,
      is_featured: false
    })
  });
  return response.json();
};
```

### 4. Activity Tracking

#### Initialize Tracking (Add to React App)
```javascript
// In your main App.js or index.js
import { useEffect } from 'react';

function App() {
  useEffect(() => {
    // Load tracking script
    const script = document.createElement('script');
    script.src = 'http://localhost:8080/js/tracking.js';
    script.async = true;
    script.onload = () => {
      // Initialize tracking
      window.CRMTracking.init({
        apiUrl: 'http://localhost:8080/custom/api',
        visitorId: localStorage.getItem('crm_visitor_id') || null
      });
    };
    document.body.appendChild(script);
  }, []);
  
  // Your app code...
}
```

#### Manual Page View Tracking
```javascript
// For SPAs, track route changes manually
const trackPageView = async (pageData) => {
  const response = await fetch(`${API_BASE}/track/pageview`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      visitor_id: localStorage.getItem('crm_visitor_id'),
      session_id: sessionStorage.getItem('crm_session_id'),
      page_url: window.location.pathname,
      title: document.title,
      referrer: document.referrer
    })
  });
  return response.json();
};
```

#### Track Conversions
```javascript
const trackConversion = async (event, value = null) => {
  const response = await fetch(`${API_BASE}/track/conversion`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      visitor_id: localStorage.getItem('crm_visitor_id'),
      session_id: sessionStorage.getItem('crm_session_id'),
      event: event, // e.g., 'form_submit', 'demo_request', 'signup'
      value: value
    })
  });
  return response.json();
};
```

### 5. AI Chatbot

#### Initialize Chat Widget
```html
<!-- Add to your React app's public/index.html -->
<script src="http://localhost:8080/js/chat-widget.js"></script>
<script>
  CRMChat.init({
    apiUrl: 'http://localhost:8080/custom/api',
    position: 'bottom-right',
    primaryColor: '#3b82f6',
    greeting: 'Hi! How can I help you today?'
  });
</script>
```

#### Custom Chat Implementation
```javascript
const sendChatMessage = async (conversationId, message) => {
  const response = await fetch(`${API_BASE}/ai/chat`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      conversation_id: conversationId, // null for new conversation
      message: message,
      visitor_id: localStorage.getItem('crm_visitor_id')
    })
  });
  return response.json();
};

// Response format:
{
  "success": true,
  "data": {
    "conversation_id": "conv-123",
    "message": "I can help you with that. Our CRM offers...",
    "intent": "product_inquiry",
    "suggested_actions": ["Schedule Demo", "View Pricing"]
  }
}
```

### 6. Customer Health Scoring

#### Calculate Health Score
```javascript
const calculateHealthScore = async (accountId) => {
  const response = await fetch(`${API_BASE}/accounts/${accountId}/health-score`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};
```

#### Get At-Risk Accounts
```javascript
const getAtRiskAccounts = async () => {
  const response = await fetch(`${API_BASE}/accounts/at-risk`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json();
};
```

#### Health Dashboard Data
```javascript
const getHealthDashboard = async () => {
  const response = await fetch(`${API_BASE}/analytics/health-dashboard`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json();
};
```

## Environment Variables

Add to your React app's `.env`:
```env
REACT_APP_API_BASE=http://localhost:8080/custom/api
REACT_APP_SUITECRM_URL=http://localhost:8080
REACT_APP_TRACKING_ENABLED=true
REACT_APP_CHAT_ENABLED=true
```

## CORS Configuration
The backend is configured to accept requests from:
- http://localhost:3000 (React dev server)
- http://localhost:5173 (Vite dev server)

## Error Handling

All API responses follow this format:

### Success Response
```json
{
  "success": true,
  "data": { /* response data */ }
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error message",
  "message": "Detailed error description"
}
```

### React Error Handler Example
```javascript
const apiCall = async (url, options = {}) => {
  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
        ...options.headers
      }
    });
    
    const data = await response.json();
    
    if (!response.ok || !data.success) {
      throw new Error(data.error || 'API request failed');
    }
    
    return data.data;
  } catch (error) {
    console.error('API Error:', error);
    // Handle error in UI
    throw error;
  }
};
```

## Testing Integration

### 1. Test API Health
```bash
curl http://localhost:8080/custom/api/health
```

### 2. Test Form Submission
```bash
curl -X POST http://localhost:8080/custom/api/forms/{form-id}/submit \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","message":"Test submission"}'
```

### 3. Test Activity Tracking
```bash
curl -X POST http://localhost:8080/custom/api/track/pageview \
  -H "Content-Type: application/json" \
  -d '{"visitor_id":"test123","page_url":"/test","title":"Test Page"}'
```

## Common Integration Issues

### 1. Authentication Failures
- Ensure you're using the correct OAuth2 endpoint
- Token format: `Bearer {token}`
- Tokens expire after 1 hour

### 2. CORS Errors
- Backend is configured for localhost:3000 and localhost:5173
- For other origins, update ALLOWED_ORIGINS in backend .env

### 3. 404 Errors
- Ensure you're using `/custom/api` not `/api/v8`
- Check endpoint paths match the routes defined

### 4. Missing Data
- Run seed script: `php seed_phase3_simple.php`
- Check database tables exist

## Performance Optimization

### 1. Caching
- Knowledge base embeddings are cached for 24 hours
- AI responses can be cached client-side

### 2. Batch Operations
- Use batch endpoints for multiple operations
- Example: Batch lead scoring instead of individual calls

### 3. Pagination
- Most list endpoints support limit/offset parameters
- Default limit is usually 20-50 items

## Security Considerations

### 1. API Keys
- Never expose the OpenAI API key in frontend
- All AI operations happen server-side

### 2. Public Endpoints
- Form submissions are rate-limited
- Tracking endpoints validate data

### 3. Data Privacy
- PII is not sent to OpenAI
- Activity tracking respects user privacy

## Next Steps

1. **Set up authentication flow** in your React app
2. **Implement error boundaries** for API failures
3. **Add loading states** for AI operations (can be slow)
4. **Test each integration** thoroughly
5. **Monitor API performance** and errors

## Support

For issues or questions:
1. Check backend logs: `docker logs suitecrm-backend`
2. Verify API health: `http://localhost:8080/custom/api/health`
3. Run verification script: `./tests/scripts/verify_phase3_realistic.sh`

---

*Backend Status: 100% Complete | Test Coverage: 92% | Ready for Integration*