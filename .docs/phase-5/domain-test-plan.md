# Domain-by-Domain Test Plan with Frontend Integration

## Testing Strategy

Each domain MUST be tested with actual frontend API calls before moving to the next domain. This document outlines all files and test scenarios per domain.

## Domain 1: Knowledge Base

### Backend Files
1. `/backend/custom/api/controllers/KnowledgeBaseController.php` - Main controller
2. `/backend/custom/api/routes.php` - Routes definition (already has KB routes)
3. `/backend/custom/api/middleware/AuthMiddleware.php` - Skip auth for public endpoints
4. `/backend/custom/config/ai_config.php` - CREATE THIS or remove dependency

### Frontend Files
1. `/frontend/src/services/knowledgeBase.service.ts` - API client
2. `/frontend/src/pages/kb/KnowledgeBase.tsx` - Admin KB page
3. `/frontend/src/pages/kb/KnowledgeBasePublic.tsx` - Public KB page
4. `/frontend/src/types/phase3.types.ts` - KB type definitions

### Test Scripts
```bash
# Create test script: /backend/test-kb-api.sh
#!/bin/bash

echo "Testing Knowledge Base API Endpoints..."

# Test public endpoints (no auth needed)
echo -e "\n1. Testing GET /api/v8/kb/categories"
curl -X GET "http://localhost:8890/api/v8/kb/categories" \
  -H "Content-Type: application/json" \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n2. Testing GET /api/v8/kb/articles"
curl -X GET "http://localhost:8890/api/v8/kb/articles?limit=5" \
  -H "Content-Type: application/json" \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n3. Testing GET /api/v8/kb/search"
curl -X GET "http://localhost:8890/api/v8/kb/search?q=test" \
  -H "Content-Type: application/json" \
  -w "\nHTTP Status: %{http_code}\n"

# Test authenticated endpoints (need JWT token)
TOKEN="YOUR_JWT_TOKEN_HERE"

echo -e "\n4. Testing POST /api/v8/knowledge-base/articles (Create)"
curl -X POST "http://localhost:8890/api/v8/knowledge-base/articles" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title": "Test Article",
    "content": "Test content",
    "category": "general",
    "is_published": true
  }' \
  -w "\nHTTP Status: %{http_code}\n"
```

### Frontend Test Checklist
1. [ ] Public KB page loads without auth
2. [ ] Categories display correctly
3. [ ] Articles list shows
4. [ ] Article detail page works
5. [ ] Search returns results
6. [ ] Admin can create/edit articles
7. [ ] No console errors

## Domain 2: Leads

### Backend Files
1. `/backend/custom/api/controllers/LeadsController.php` - Main controller
2. `/backend/custom/api/controllers/ActivityTrackingController.php` - Lead tracking
3. `/backend/custom/api/controllers/AIController.php` - Lead scoring methods

### Frontend Files
1. `/frontend/src/services/lead.service.ts` - API client
2. `/frontend/src/pages/leads/LeadsList.tsx` - List view
3. `/frontend/src/pages/leads/LeadForm.tsx` - Create/Edit form
4. `/frontend/src/pages/leads/LeadDetail.tsx` - Detail view
5. `/frontend/src/components/features/leads/LeadTimeline.tsx` - Activity timeline

### Test Scripts
```bash
# Create test script: /backend/test-leads-api.sh
#!/bin/bash

TOKEN="YOUR_JWT_TOKEN_HERE"

echo "Testing Leads API Endpoints..."

echo -e "\n1. Testing GET /api/v8/leads (List)"
curl -X GET "http://localhost:8890/api/v8/leads?page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN" \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n2. Testing GET /api/v8/leads/{id} (Single)"
curl -X GET "http://localhost:8890/api/v8/leads/LEAD_ID_HERE" \
  -H "Authorization: Bearer $TOKEN" \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n3. Testing POST /api/v8/leads (Create)"
curl -X POST "http://localhost:8890/api/v8/leads" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "first_name": "Test",
    "last_name": "Lead",
    "email": "test@example.com",
    "status": "New"
  }' \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n4. Testing Lead Score"
curl -X POST "http://localhost:8890/api/v8/leads/LEAD_ID_HERE/ai-score" \
  -H "Authorization: Bearer $TOKEN" \
  -w "\nHTTP Status: %{http_code}\n"
```

### Frontend Test Checklist
1. [ ] Leads list page loads with data
2. [ ] Pagination works
3. [ ] Create new lead works
4. [ ] Edit lead saves correctly
5. [ ] Lead detail shows all fields
6. [ ] Activity timeline displays
7. [ ] Lead scoring updates
8. [ ] Delete lead works

## Domain 3: Contacts

### Backend Files
1. `/backend/custom/api/controllers/ContactsController.php` - Main controller
2. Database tables: `contacts`, `email_addresses`, `email_addr_bean_rel`

### Frontend Files
1. `/frontend/src/services/contact.service.ts` - API client
2. `/frontend/src/pages/contacts/ContactsList.tsx` - List view
3. `/frontend/src/pages/contacts/ContactForm.tsx` - Create/Edit form
4. `/frontend/src/pages/contacts/ContactDetail.tsx` - Detail view
5. `/frontend/src/components/features/contacts/ContactActivities.tsx`

### Test Scripts
```bash
# Create test script: /backend/test-contacts-api.sh
#!/bin/bash

TOKEN="YOUR_JWT_TOKEN_HERE"

echo "Testing Contacts API Endpoints..."

echo -e "\n1. Testing GET /api/v8/contacts (List)"
curl -X GET "http://localhost:8890/api/v8/contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n2. Testing GET /api/v8/contacts/unified (Unified View)"
curl -X GET "http://localhost:8890/api/v8/contacts/unified" \
  -H "Authorization: Bearer $TOKEN" \
  -w "\nHTTP Status: %{http_code}\n"

echo -e "\n3. Testing POST /api/v8/contacts (Create)"
curl -X POST "http://localhost:8890/api/v8/contacts" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "first_name": "Test",
    "last_name": "Contact",
    "email": "contact@example.com",
    "account_id": "ACCOUNT_ID_HERE"
  }' \
  -w "\nHTTP Status: %{http_code}\n"
```

## Domain 4: Opportunities

### Backend Files
1. `/backend/custom/api/controllers/OpportunitiesController.php` - Main controller
2. Database tables: `opportunities`, `accounts_opportunities`, `opportunities_contacts`

### Frontend Files
1. `/frontend/src/services/opportunity.service.ts` - API client
2. `/frontend/src/pages/opportunities/OpportunitiesList.tsx` - List view
3. `/frontend/src/pages/opportunities/OpportunityForm.tsx` - Create/Edit form
4. `/frontend/src/pages/opportunities/OpportunityDetail.tsx` - Detail view
5. `/frontend/src/components/features/opportunities/OpportunitiesKanban.tsx` - Kanban view

### Related Backend Methods
- Lead to Opportunity conversion in LeadsController
- Opportunity stages and pipeline calculations

## Domain 5: Cases (Support Tickets)

### Backend Files
1. `/backend/custom/api/controllers/CasesController.php` - Main controller
2. Database tables: `cases`, `cases_cstm`, `contacts_cases`

### Frontend Files
1. `/frontend/src/services/case.service.ts` - API client
2. `/frontend/src/pages/cases/CasesList.tsx` - List view
3. `/frontend/src/pages/cases/CaseForm.tsx` - Create/Edit form
4. `/frontend/src/pages/cases/CaseDetail.tsx` - Detail view

### Integration Points
- AI chat can create cases
- Knowledge base can link to cases

## Domain 6: Activities

### Backend Files
1. `/backend/custom/api/controllers/ActivitiesController.php` - Main controller
2. Database tables: `calls`, `meetings`, `tasks`, `notes`, `emails`

### Frontend Files
1. `/frontend/src/services/activity.service.ts` - API client
2. `/frontend/src/pages/activities/ActivitiesList.tsx` - List view
3. `/frontend/src/pages/activities/CallForm.tsx` - Call form
4. `/frontend/src/pages/activities/MeetingForm.tsx` - Meeting form
5. `/frontend/src/pages/activities/TaskForm.tsx` - Task form
6. `/frontend/src/components/features/activities/ActivityTimeline.tsx`

### Cross-Domain Usage
- Used in Lead timeline
- Used in Contact timeline
- Used in Opportunity timeline
- Used in Case timeline

## Domain 7: Forms

### Backend Files
1. `/backend/custom/api/controllers/FormBuilderController.php` - Main controller
2. Database tables: `form_builder_forms`, `form_builder_submissions`
3. `/backend/custom/public/js/forms-embed.js` - Embed script

### Frontend Files
1. `/frontend/src/services/formBuilder.service.ts` - API client
2. `/frontend/src/pages/forms/FormsList.tsx` - List view
3. `/frontend/src/pages/forms/FormBuilder.tsx` - Form builder
4. `/frontend/src/pages/forms/FormSubmissions.tsx` - Submissions view

### Public Endpoints
- GET `/api/v8/forms/{id}` - Get form for embedding
- POST `/api/v8/forms/{id}/submit` - Submit form (no auth)

## Domain 8: AI Features

### Backend Files
1. `/backend/custom/api/controllers/AIController.php` - Main controller
2. `/backend/custom/api/services/OpenAIService.php` - OpenAI integration
3. Database tables: `ai_chat_conversations`, `ai_chat_messages`, `lead_scores`

### Frontend Files
1. `/frontend/src/services/ai.service.ts` - API client
2. `/frontend/src/components/features/chatbot/ChatWidget.tsx` - Chat widget
3. `/frontend/src/components/features/chatbot/ChatInterface.tsx` - Chat UI
4. `/frontend/src/pages/leads/LeadScoringDashboard.tsx` - Scoring dashboard

## Testing Automation

### Create Master Test Script
```bash
#!/bin/bash
# /backend/test-all-domains.sh

echo "CRM API Test Suite"
echo "=================="

# Get JWT token first
echo "Logging in to get JWT token..."
TOKEN=$(curl -s -X POST "http://localhost:8890/api/v8/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin"}' \
  | jq -r '.data.token')

if [ -z "$TOKEN" ]; then
  echo "Failed to get JWT token!"
  exit 1
fi

echo "Token obtained: ${TOKEN:0:20}..."

# Run domain tests
./test-kb-api.sh
./test-leads-api.sh
./test-contacts-api.sh
./test-opportunities-api.sh
./test-cases-api.sh
./test-activities-api.sh
./test-forms-api.sh
./test-ai-api.sh

echo -e "\nTest suite completed!"
```

### Frontend E2E Test Template
```typescript
// /frontend/tests/e2e/kb.test.ts
describe('Knowledge Base E2E', () => {
  it('should load public KB without auth', () => {
    cy.visit('/kb/public');
    cy.contains('Knowledge Base');
    cy.get('[data-testid="kb-categories"]').should('exist');
    cy.get('[data-testid="kb-articles"]').should('have.length.greaterThan', 0);
  });

  it('should search articles', () => {
    cy.visit('/kb/public');
    cy.get('[data-testid="kb-search"]').type('test');
    cy.get('[data-testid="search-results"]').should('exist');
  });

  it('should view article detail', () => {
    cy.visit('/kb/public');
    cy.get('[data-testid="kb-article"]').first().click();
    cy.get('[data-testid="article-content"]').should('exist');
  });
});
```

## Success Criteria Per Domain

1. **All API endpoints return 200/201 status**
2. **Response format matches frontend expectations**
3. **No PHP errors in logs**
4. **Frontend displays data correctly**
5. **CRUD operations complete successfully**
6. **Relationships load properly**
7. **No console errors in browser**
8. **Performance is acceptable (<500ms per request)**

## Order of Implementation

1. **Knowledge Base** - Isolated, public endpoints
2. **Leads** - Core entity, many dependencies
3. **Contacts** - Similar to leads, shared patterns
4. **Opportunities** - Depends on leads/contacts
5. **Cases** - Support functionality
6. **Activities** - Cross-entity, complex relationships
7. **Forms** - Public submission critical
8. **AI** - Most complex, depends on all others

## Critical Validation Points

### After Each Domain:
1. Run API test script
2. Check PHP error logs
3. Test in frontend UI
4. Run frontend type checking
5. Verify relationships work
6. Test pagination/filtering
7. Check performance

### Before Moving to Next Domain:
- [ ] All tests pass
- [ ] No errors in logs
- [ ] Frontend works end-to-end
- [ ] Code reviewed for patterns
- [ ] Document any new issues found

This approach ensures we catch issues immediately rather than after converting everything.