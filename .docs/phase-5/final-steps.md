# Final Steps Before Production - Comprehensive Checklist

## Critical Issues to Fix

### 1. Authentication & Token Management
- [x] Fix JWT token not being sent in API requests
- [x] Ensure token is properly stored after login
- [x] Fix automatic token refresh mechanism
- [x] Verify all API endpoints check for valid JWT token
- [x] Test token expiration and refresh flow

### 2. API Endpoints - Full Verification
- [x] `/api/dashboard/metrics` - Fix 401 error
- [x] `/api/dashboard/cases` - Fix 401 error  
- [x] `/api/dashboard/activities` - Fix 401 error
- [x] `/api/dashboard/pipeline` - Fix 401 error
- [x] `/api/leads` - Verify GET/POST/PUT/DELETE
- [x] `/api/contacts` - Verify GET/POST/PUT/DELETE
- [x] `/api/opportunities` - Verify GET/POST/PUT/DELETE
- [x] `/api/cases` - Verify GET/POST/PUT/DELETE
- [x] `/api/activities` - Verify GET/POST/PUT/DELETE
- [ ] `/api/knowledge-base/*` - All KB endpoints
- [ ] `/api/forms/*` - Form builder endpoints
- [ ] `/api/ai/chat` - AI chat endpoints
- [ ] `/api/track/*` - Activity tracking endpoints

### 3. Database Seeding - Comprehensive Data
- [x] Create proper relationships between all entities
- [x] Seed leads with:
  - [x] Realistic contact information
  - [x] Activity history (calls, emails, meetings)
  - [x] Notes and attachments
  - [x] Lead scores and tracking data
  - [x] Assignment to different users
- [x] Seed opportunities with:
  - [x] Links to contacts
  - [x] Links to accounts
  - [x] Activity history
  - [x] Stage history
  - [x] Products/line items
- [x] Seed contacts with:
  - [x] Full contact details
  - [x] Company associations
  - [x] Activity timeline
  - [x] Email addresses in proper tables
- [x] Seed cases/support tickets with:
  - [x] Customer associations
  - [x] Resolution history
  - [x] Internal notes
  - [x] Status changes
- [x] Seed activities:
  - [x] Calls with duration and outcomes
  - [x] Meetings with attendees
  - [x] Tasks with due dates
  - [x] Notes with proper associations
- [x] Seed knowledge base:
  - [x] Multiple categories
  - [x] Articles with rich content
  - [x] Search keywords
  - [x] View counts
- [x] Seed AI chat conversations:
  - [x] Multiple conversations
  - [x] Lead capture scenarios
  - [x] Support ticket creation
- [x] Seed form submissions:
  - [x] Multiple forms
  - [x] Submissions with conversion to leads

### 4. Frontend Issues
- [ ] Fix redirect to login on authenticated pages
- [ ] Ensure auth token is included in all API requests
- [ ] Fix "New Lead" button functionality
- [ ] Verify all list pages show seeded data
- [ ] Fix dashboard widgets to display real data
- [ ] Test all CRUD operations from UI
- [ ] Verify activity timeline displays correctly
- [ ] Fix any navigation issues

### 5. Integration Points
- [ ] Lead to Opportunity conversion
- [ ] Lead to Contact conversion
- [ ] Opportunity to Customer conversion
- [ ] Activity associations with multiple entities
- [ ] Email address relationships
- [ ] File attachments
- [ ] Unified contact view aggregation

### 6. Final Quality Checks
- [x] Run TypeScript type checking - fix all errors
- [x] Run ESLint - fix all errors and warnings
- [ ] Test all user roles (admin, sales, support)
- [ ] Verify all date/time displays correctly
- [ ] Check pagination on all list views
- [ ] Test search functionality
- [ ] Verify filters work correctly
- [ ] Test bulk operations

### 7. API Response Consistency
- [ ] Ensure all APIs return consistent format
- [ ] Proper error messages and codes
- [ ] Pagination metadata
- [ ] Success/failure indicators
- [ ] Proper HTTP status codes

### 8. Security Verification
- [ ] All endpoints require authentication (except public ones)
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] CORS properly configured
- [ ] Rate limiting working

## Order of Execution

1. **Fix Authentication Flow** - Without this, nothing else will work
2. **Create Comprehensive Seed Data** - Need data to test with
3. **Fix All API Endpoints** - Backend must work perfectly
4. **Fix Frontend Integration** - UI must consume APIs correctly
5. **Run Type/Lint Checks** - Clean up code quality
6. **Final Testing** - Verify everything works end-to-end

## Definition of Done

- [ ] Can login and stay logged in
- [ ] Dashboard shows real metrics from database
- [ ] Can create, view, edit, delete all entities
- [ ] Activity timelines show all related activities
- [ ] Lead conversion works
- [ ] AI chat captures leads
- [ ] Forms create leads
- [ ] No console errors
- [ ] No TypeScript errors
- [ ] No linting errors
- [ ] All seeded data displays correctly
- [ ] Can navigate between all pages without issues

## DO NOT tell user to test until ALL items above are checked!