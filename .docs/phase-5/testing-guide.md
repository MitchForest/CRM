# Phase 5 Testing Guide

## Overview
This guide provides step-by-step instructions for testing all Phase 5 implementations to ensure everything works correctly with real data.

## Prerequisites

1. **Environment Setup**
   ```bash
   cd backend
   cp .env.example .env
   # Edit .env and set:
   # - JWT_SECRET (generate a secure random string)
   # - OPENAI_API_KEY (your OpenAI API key)
   ```

2. **Database Reset & Seed**
   ```bash
   # Reset database (removes all data)
   php custom/install/reset_database.php
   
   # Seed with test data
   php custom/install/seed_phase5_data.php
   ```

3. **Start Services**
   ```bash
   # Backend (from backend directory)
   docker-compose up -d
   
   # Frontend (from frontend directory)
   npm install
   npm run dev
   ```

## Core Feature Tests

### 1. Authentication & Authorization

**Test JWT Authentication:**
1. Navigate to http://localhost:5173/login
2. Login with seeded user:
   - Email: john.doe@example.com
   - Password: admin123
3. Verify:
   - ✓ Redirected to dashboard
   - ✓ JWT token stored in localStorage
   - ✓ Can access protected routes
   - ✓ Token refreshes automatically

**Test Logout:**
1. Click logout in sidebar
2. Verify:
   - ✓ Redirected to login page
   - ✓ Cannot access protected routes
   - ✓ Tokens cleared from localStorage

### 2. Simplified Lead Management

**Test Lead Creation:**
1. Navigate to Leads page
2. Click "New Lead"
3. Fill in form with test data
4. Verify:
   - ✓ Lead created with status "New"
   - ✓ Only simplified statuses available (New, Contacted, Qualified)
   - ✓ Lead appears in list

**Test Lead Qualification:**
1. Open a lead detail page
2. Change status to "Qualified"
3. Verify:
   - ✓ Status updates correctly
   - ✓ Can create opportunity from qualified lead

### 3. Unified Contact View

**Test Contact View:**
1. Navigate to Contacts page
2. Click on any contact
3. Verify unified view shows:
   - ✓ Contact information
   - ✓ Activity timeline (all activities)
   - ✓ Related opportunities
   - ✓ Support tickets
   - ✓ AI chat conversations
   - ✓ Visitor tracking data

### 4. Form Builder & Embedding

**Test Form Creation:**
1. Navigate to Forms page (Admin section)
2. Create new form with:
   - Name field (required)
   - Email field (required)
   - Company field
   - Message field (textarea)
3. Save form
4. Verify:
   - ✓ Form saved successfully
   - ✓ Embed code generated

**Test Form Embedding:**
1. Copy embed code
2. Create test HTML file:
   ```html
   <!DOCTYPE html>
   <html>
   <head>
       <title>Form Test</title>
   </head>
   <body>
       <!-- Paste embed code here -->
   </body>
   </html>
   ```
3. Open HTML file in browser
4. Submit form
5. Verify:
   - ✓ Form renders correctly
   - ✓ Validation works
   - ✓ Submission creates lead in CRM

### 5. AI Chatbot

**Test Chat Widget:**
1. Open any page with chat widget
2. Click chat bubble
3. Send test messages:
   - "What do you do?"
   - "I need help with pricing"
   - "I want to speak to a human"
4. Verify:
   - ✓ AI responds appropriately
   - ✓ Knowledge base articles referenced
   - ✓ Handoff request recognized
   - ✓ Conversation saved in database

**Test Lead Capture:**
1. In chat, provide:
   - Name: Test User
   - Email: test@example.com
   - Company: Test Corp
2. Verify:
   - ✓ Lead automatically created
   - ✓ Chat linked to lead
   - ✓ Shows in unified contact view

### 6. Knowledge Base

**Test Article Creation:**
1. Navigate to Knowledge Base (Admin)
2. Create article:
   - Title: "Getting Started Guide"
   - Category: "Documentation"
   - Content: Test content with formatting
3. Verify:
   - ✓ Article saved
   - ✓ Shows in KB list
   - ✓ Searchable

**Test KB Search:**
1. Use search in KB
2. Search for keywords from articles
3. Verify:
   - ✓ Relevant results returned
   - ✓ Search highlights work

### 7. Support Tickets

**Test Ticket Creation:**
1. Navigate to Support Tickets
2. Create new ticket
3. Verify:
   - ✓ Uses simplified statuses (Open, In Progress, Resolved, Closed)
   - ✓ Simple types available (Technical, Billing, Feature Request, Other)

### 8. Opportunity Pipeline

**Test Opportunity Flow:**
1. Create opportunity from qualified lead
2. Move through stages:
   - Qualified → Proposal
   - Proposal → Negotiation
   - Negotiation → Won
3. Verify:
   - ✓ Only simplified stages available
   - ✓ Pipeline view updates correctly
   - ✓ Won opportunities create customers

### 9. Activity Tracking

**Test Visitor Tracking:**
1. Open tracking script page (Admin)
2. Copy tracking script
3. Add to test HTML page
4. Visit page multiple times
5. Verify in CRM:
   - ✓ Visitor sessions recorded
   - ✓ Page views tracked
   - ✓ Time on site calculated

### 10. Dashboard

**Test Dashboard Metrics:**
1. Navigate to Dashboard
2. Verify all widgets show real data:
   - ✓ Lead metrics
   - ✓ Opportunity pipeline
   - ✓ Recent activities
   - ✓ Support ticket status

## Integration Tests

### Lead Lifecycle Test
1. **Visitor Phase:**
   - Visit website with tracking script
   - View multiple pages
   - Read KB articles

2. **Engagement Phase:**
   - Use AI chat
   - Ask questions
   - Submit contact form

3. **Lead Phase:**
   - Verify lead created with all tracking data
   - Qualify lead
   - Create opportunity

4. **Customer Phase:**
   - Win opportunity
   - Verify customer created
   - Create support ticket

### API Consistency Test
Test each API endpoint:
```bash
# Login
curl -X POST http://localhost:8080/api/v8/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john.doe@example.com","password":"admin123"}'

# Get leads (with token)
curl http://localhost:8080/api/v8/leads \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Verify:
- ✓ Consistent response format
- ✓ Proper error messages
- ✓ Real data returned

## Performance Tests

1. **Page Load Times:**
   - Dashboard: < 2 seconds
   - List pages: < 1 second
   - Form submissions: < 1 second

2. **Concurrent Users:**
   - Test with multiple browser tabs
   - Verify no session conflicts

## Mobile Responsiveness

Test on mobile devices or browser dev tools:
1. ✓ Navigation works
2. ✓ Forms usable
3. ✓ Tables scroll properly
4. ✓ Chat widget positions correctly

## Error Handling

1. **Test Invalid Data:**
   - Submit forms with missing required fields
   - Use invalid email formats
   - Exceed character limits

2. **Test Network Errors:**
   - Disable network and try actions
   - Verify graceful error messages

## Security Tests

1. **Authentication:**
   - Try accessing API without token
   - Try using expired token
   - Verify proper 401 responses

2. **Authorization:**
   - Test different user roles
   - Verify permissions enforced

## Checklist Summary

- [ ] All authentication flows work
- [ ] Lead management simplified and functional
- [ ] Unified contact view shows all data
- [ ] Forms can be created and embedded
- [ ] AI chatbot works and captures leads
- [ ] Knowledge base searchable
- [ ] Support tickets use new structure
- [ ] Opportunities use simplified stages
- [ ] Activity tracking captures data
- [ ] Dashboard shows real metrics
- [ ] APIs consistent and secure
- [ ] Mobile responsive
- [ ] Proper error handling
- [ ] No console errors
- [ ] No mock data used

## Troubleshooting

**Common Issues:**

1. **"JWT Secret not set" error:**
   - Ensure .env file exists with JWT_SECRET

2. **Chat not responding:**
   - Check OPENAI_API_KEY is set correctly
   - Verify API key has credits

3. **Forms not loading:**
   - Check CORS settings
   - Verify embed script URL is correct

4. **No data showing:**
   - Run seed script
   - Check database connection

## Final Verification

Before marking Phase 5 complete:
1. Clear browser cache
2. Reset database and seed fresh data
3. Run through all tests above
4. Have another person test independently
5. Document any issues found

---

**Remember:** The goal is a SIMPLE, FUNCTIONAL CRM with no technical debt. If something doesn't work correctly, fix it before proceeding.