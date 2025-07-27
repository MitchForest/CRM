# CRM Application Test Plan

## Fixed Issues
- ✅ Fixed 405 error for `/api/track/pageview` - Updated frontend to use `/public/track/pageview`

## Routes to Test

### Public Routes (No Auth Required)
1. **Homepage** - `/`
2. **Pricing** - `/pricing`
3. **Get Started** - `/get-started`
4. **Support** - `/support`
5. **Demo Booking** - `/demo`
6. **Public Knowledge Base** - `/kb/public/*`
7. **Login** - `/login`

### Protected Routes (Auth Required) - `/app/*`

#### CRM Core Features
1. **Contacts**
   - List - `/app/contacts`
   - Detail View - `/app/contacts/:id`

2. **Leads**
   - List - `/app/leads`
   - New Lead - `/app/leads/new`
   - Detail - `/app/leads/:id`
   - Edit - `/app/leads/:id/edit`

3. **Opportunities**
   - Pipeline - `/app/opportunities`
   - New - `/app/opportunities/new`
   - Detail - `/app/opportunities/:id`
   - Edit - `/app/opportunities/:id/edit`

4. **Cases (Support)**
   - List - `/app/cases`
   - New - `/app/cases/new`
   - Detail - `/app/cases/:id`
   - Edit - `/app/cases/:id/edit`

#### Additional Features
5. **Forms** - `/app/forms`
   - New Form - `/app/forms/new`
   - Form Detail - `/app/forms/:id`

6. **Knowledge Base** - `/app/kb`
   - New Article - `/app/kb/new`
   - Edit Article - `/app/kb/edit/:id`

7. **Activity Tracking** - `/app/tracking`
   - Session Detail - `/app/tracking/sessions/:id`

8. **AI Chatbot** - `/app/chatbot`

9. **Settings** - `/app/settings`

## Testing Steps

### 1. Public Routes Testing
- Navigate to each public route
- Verify page loads without errors
- Check that tracking works (no 405 errors)
- Test form submissions where applicable

### 2. Authentication Flow
- Test login with demo credentials
- Verify JWT token is stored
- Test logout functionality
- Verify protected routes redirect to login when not authenticated

### 3. CRUD Operations
For each entity (Leads, Contacts, Opportunities, Cases):
- Test listing with pagination
- Test creating new records
- Test viewing detail pages
- Test editing existing records
- Test deleting records
- Verify form validation

### 4. Special Features
- Test AI chat functionality
- Test form builder
- Test knowledge base search
- Test activity tracking dashboard
- Test opportunity pipeline drag-and-drop

### 5. API Integration
- Verify all API calls use correct endpoints
- Check error handling for failed requests
- Test loading states
- Verify data updates reflect immediately

## Known Issues to Fix
1. Activity tracking endpoints need `/public` prefix
2. Test setup files are missing
3. Backend server configuration for development