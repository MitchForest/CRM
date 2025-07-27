# Phase 7: User Testing Tracker

## Overview
This document tracks the issues discovered during user testing and their resolutions during the Phase 6 to Phase 7 migration.

## Testing Environment
- Frontend: http://localhost:5173
- Backend: http://localhost:8080
- Test Credentials: `john.smith@techflow.com` / `password123`

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

**Status**: 🔄 Ready to implement

### 3. ⏳ Activity Tracking (PENDING)

#### Issue 3.1: Page View Tracking Error
- **Error**: `Failed to track page view: AxiosError 405`
- **Endpoint**: `/api/track/pageview`
- **Status**: ⏳ To be investigated after dashboard fixes

## Next Steps

1. **Immediate**: Fix all CRM API routes by adding `/crm` prefix
2. **Then**: Test activity tracking endpoints
3. **Finally**: Go through each feature systematically:
   - Leads management
   - Contacts management
   - Opportunities pipeline
   - Cases/tickets
   - Analytics dashboards
   - AI features
   - Activity tracking

## Testing Checklist

- [x] Login/Authentication
- [ ] Dashboard metrics loading
- [ ] Activity tracking
- [ ] Lead CRUD operations
- [ ] Contact CRUD operations
- [ ] Opportunity pipeline
- [ ] Case management
- [ ] Analytics views
- [ ] AI chat functionality
- [ ] Search functionality
- [ ] Data export features
- [ ] Real-time updates
- [ ] Error handling
- [ ] Loading states
- [ ] Empty states

## Notes for Other Agents

When continuing this work:
1. Always check if frontend route matches backend route structure
2. Backend has `/api/crm/*` prefix for all CRM routes, `/api/*` for auth/public routes
3. Use test credentials: `john.smith@techflow.com` / `password123`
4. Frontend dev server should run on port 5173 (not 5175)
5. Check browser console for detailed error messages
6. Test each fix immediately before moving to next issue