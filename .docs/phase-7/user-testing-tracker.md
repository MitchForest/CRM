# Phase 7: User Testing Tracker

## Overview
This document tracks the issues discovered during user testing and their resolutions during the Phase 6 to Phase 7 migration.

## Testing Environment
- Frontend: http://localhost:5173
- Backend: http://localhost:8080
- Test Credentials: `john.smith@techflow.com` / `password123`

## Architecture Understanding

### API Client Structure
- **Main Client**: `/frontend/src/lib/api-client.ts` (1,175 lines)
  - Comprehensive implementation with all entity support
  - Uses Axios with interceptors for auth handling
  - Dual client setup: one for SuiteCRM V8, one for custom backend
  - All CRM routes must use `/crm` prefix (e.g., `/api/crm/leads`)
  - Auth routes use `/api` directly (e.g., `/api/auth/login`)

### Authentication Flow
1. Login sends `email` and `password` to `/api/auth/login`
2. Backend returns `access_token`, `refresh_token`, and `user` object
3. Frontend stores auth in localStorage via `auth-store.ts`
4. All subsequent requests include `Authorization: Bearer {token}` header
5. JWT tokens expire after 15 minutes (900 seconds)

### Common Backend Patterns
- **Slim Framework** (not Laravel!)
  - Query params: `$request->getQueryParams()['param']`
  - POST body: `$request->getParsedBody()['field']`
  - No `$request->has()` or `$request->input()` methods!
- **Authentication**: JWT middleware validates tokens and adds `user_id` to request
- **Response format**: JSON with proper status codes

## Issues Found and Fixed

### 1. ✅ Login Authentication (COMPLETED)

#### Issue 1.1: 405 Method Not Allowed on Login
- **Error**: `POST http://localhost:5175/api/crm/auth/login 405 (Method Not Allowed)`
- **Root Cause**: Two frontend servers running (ports 5173 and 5175), user accessing wrong port
- **Fix**: Killed extra server on port 5175
- **Status**: ✅ Resolved

#### Issue 1.2: API Base URL Mismatch
- **Error**: Frontend calling `/api/crm/auth/login` instead of `/api/auth/login`
- **Root Cause**: `customClient` baseURL was set to `/api/crm` instead of `/api`
- **Fix**: Changed baseURL from `/api/crm` to `/api` in `frontend/src/lib/api-client.ts:70`
- **Status**: ✅ Resolved

#### Issue 1.3: Login Field Name Mismatch
- **Error**: Backend expects `email` field but frontend sends `username`
- **Root Cause**: Field name inconsistency
- **Fix**: Updated login method to send `email` instead of `username` in `frontend/src/lib/api-client.ts:228`
- **Status**: ✅ Resolved

#### Issue 1.4: Token Field Name Mismatch
- **Error**: "Invalid response from login"
- **Root Cause**: Backend returns `access_token` but frontend expects `accessToken`
- **Fix**: Updated token field names in `frontend/src/lib/api-client.ts:234-240`
- **Status**: ✅ Resolved

### 2. 🔄 Dashboard API Routes (IN PROGRESS)

#### Issue 2.1: Dashboard Endpoints 405 Errors
- **Errors**: 
  - `GET http://localhost:5173/api/dashboard/metrics 405`
  - `GET http://localhost:5173/api/dashboard/activities 405`
  - `GET http://localhost:5173/api/dashboard/pipeline 405`
  - `GET http://localhost:5173/api/dashboard/cases 405`
- **Root Cause**: Frontend calling `/api/dashboard/*` but backend routes are at `/api/crm/dashboard/*`
- **Analysis**: After fixing auth by changing baseURL to `/api`, all CRM routes lost their `/crm` prefix

#### Affected Routes Structure:
```
Backend Structure:
/api
  /auth/*          (public routes - working ✅)
  /track/*         (public routes - not tested yet)
  /crm/*           (protected routes - all broken 🔴)
    /dashboard/*   
    /leads/*
    /contacts/*
    /opportunities/*
    /cases/*
    /activities/*
    /analytics/*
    /ai/*

Frontend is calling:
/api/dashboard/*    ❌ Should be: /api/crm/dashboard/*
/api/leads/*        ❌ Should be: /api/crm/leads/*
/api/contacts/*     ❌ Should be: /api/crm/contacts/*
... etc
```

#### Proposed Fix:
Update all CRM-related API methods in `frontend/src/lib/api-client.ts` to include the `/crm` prefix:
- Dashboard methods: Lines 1026-1104
- Lead methods: Lines 423-554
- Contact methods: Lines 353-421
- Opportunity methods: Lines 627-741
- Case methods: Lines 954-1023
- All other CRM endpoints

**Status**: ✅ Resolved - Fixed all CRM routes by adding `/crm` prefix in `/frontend/src/lib/api-client.ts`

#### Additional Cleanup:
- **Issue**: Two conflicting API client files causing confusion
- **Files**: 
  - `/frontend/src/lib/api-client.ts` - Comprehensive client used by all components (KEPT)
  - `/frontend/src/api/client.ts` - Unused manual implementation (REMOVED)
- **Resolution**: Deleted the unused client to eliminate confusion

### 3. ⏳ Activity Tracking (PENDING)

#### Issue 3.1: Page View Tracking Error
- **Error**: `Failed to track page view: AxiosError 405`
- **Endpoint**: `/api/track/pageview`
- **Status**: ⏳ To be investigated after dashboard fixes

### 4. 🔄 CRM Feature Testing (IN PROGRESS)

#### Issue 4.1: 401 Unauthorized on Contacts/Opportunities ✅ RESOLVED
- **Error**: Contacts and Opportunities endpoints return 401 while Dashboard and Leads work fine
- **Investigation Results**:
  - Login works and returns valid JWT token ✅
  - Dashboard endpoint works: `/api/crm/dashboard/metrics` ✅ 
  - Leads endpoint works: `/api/crm/leads` ✅
  - Contacts endpoint fails: `/api/crm/contacts` ❌ (401)
  - Opportunities endpoint now works: `/api/crm/opportunities` ✅ (FIXED)
  - Opportunities pipeline works: `/api/crm/opportunities/pipeline` ✅
  - Same token works for some endpoints but not others
- **Root Cause Analysis - CONFIRMED**:
  1. **Laravel syntax in Slim framework**: Controllers using `response()->json()`, `$request->validate()`, etc. cause PHP fatal errors
  2. **Missing route methods**: Routes referencing non-existent methods (e.g., `updateStage`) prevent route registration
  3. **Database schema mismatches**: Model relationships expecting columns that don't exist in pivot tables
  4. **Error masking**: Slim's error handling catches PHP errors but returns generic 401 instead of real error details
- **Complete Fix Applied to OpportunitiesController**:
  - Fixed all Laravel-style method calls (`response()->json()` → `$this->json()`) ✅
  - Fixed `$id` references to use `$args['id']` ✅
  - Added missing `updateStage` method ✅
  - Fixed database issue: Removed non-existent columns from pivot table relationship ✅
  - Removed `contacts` eager loading that was causing SQL errors ✅
- **Proof of Fix**:
  - Opportunities endpoint now returns 200 OK with data ✅
  - Error changed from 401 → 500 → 200 as we fixed each issue
  - This confirms PHP errors were being masked as authentication failures
- **Status**: ✅ Resolved for OpportunitiesController

#### Issue 4.2: 500 Error on Opportunities Controller
- **Error**: `Call to undefined method Slim\Psr7\Request::has()`
- **Root Cause**: OpportunitiesController using Laravel-style methods (`has()`, `input()`) instead of Slim methods
- **Fix**: Updated to use `$request->getQueryParams()` and `$request->getParsedBody()`
- **Status**: ✅ Code fixed, needs testing

## Next Steps

1. **Immediate**: Debug JWT authentication issue causing 401 errors
2. **Then**: Test activity tracking endpoints
3. **Finally**: Go through each feature systematically:
   - Leads management
   - Contacts management
   - Opportunities pipeline
   - Cases/tickets
   - Analytics dashboards
   - AI features
   - Activity tracking

## Controllers Needing Slim Framework Updates

Based on the OpportunitiesController issue, we need to check ALL controllers for Laravel-style methods that don't work in Slim:

### Files to Check and Fix:
1. **ContactsController.php** - Likely has same `$request->has()` and `$request->input()` issues
2. **LeadsController.php** - Check for query param handling
3. **CasesController.php** - Check for request handling
4. **ActivitiesController.php** - Check for request handling
5. **DashboardController.php** - Already working, might be correct
6. **AnalyticsController.php** - Check for request handling
7. **AIController.php** - Check for request handling

### Pattern to Replace:
```php
// OLD (Laravel style - WRONG)
if ($request->has('param')) {
    $value = $request->input('param');
}

// NEW (Slim style - CORRECT)
// For GET requests:
$queryParams = $request->getQueryParams();
if (isset($queryParams['param'])) {
    $value = $queryParams['param'];
}

// For POST/PUT requests:
$body = $request->getParsedBody();
if (isset($body['field'])) {
    $value = $body['field'];
}
```

## Testing Checklist

- [x] Login/Authentication
- [x] Dashboard metrics loading ✅
- [ ] Activity tracking
- [x] Lead CRUD operations ✅ (working)
- [ ] Contact CRUD operations ❌ (401 error)
- [ ] Opportunity pipeline ❌ (500 error - partially fixed)
- [ ] Case management
- [ ] Analytics views
- [ ] AI chat functionality
- [ ] Search functionality
- [ ] Data export features
- [ ] Real-time updates
- [ ] Error handling
- [ ] Loading states
- [ ] Empty states

## Critical Issues Summary

### 🔴 Blocking Issues:
1. **Contacts API**: Returns 401 Unauthorized (auth works for other endpoints)
2. **Opportunities API**: Returns 401 Unauthorized (also had 500 error - partially fixed)
3. **Activity Tracking**: Not tested yet, likely has issues

### 🟡 Fixed Issues:
1. **Login**: Fixed field name mismatches and token format
2. **Dashboard**: Fixed route prefix (`/api/crm/dashboard/*`)
3. **Leads**: Working correctly after route fixes
4. **Opportunities 500 Error**: Fixed Slim request method calls

### 🟢 Working Features:
- Authentication (login/logout)
- Dashboard metrics
- Leads listing

## Notes for Other Agents

When continuing this work:
1. Always check if frontend route matches backend route structure
2. Backend has `/api/crm/*` prefix for all CRM routes, `/api/*` for auth/public routes
3. Use test credentials: `john.smith@techflow.com` / `password123`
4. Frontend dev server should run on port 5173 (not 5175)
5. Check browser console for detailed error messages
6. Test each fix immediately before moving to next issue
7. **Important**: Some controllers mysteriously return 401 even with valid tokens - needs investigation
8. All Slim controllers must use `$request->getQueryParams()` not `$request->has()` or `$request->input()`