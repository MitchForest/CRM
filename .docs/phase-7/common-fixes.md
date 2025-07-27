# Common Fixes for CRM Application

## Activity Tracking Errors (Homepage)

### Issue 1: 405 Method Not Allowed
**Error**: `POST http://localhost:5176/api/track/pageview 405 (Method Not Allowed)`

**Root Cause**: Frontend calling wrong API path. Backend expects `/api/public/track/*` but frontend was calling `/api/track/*`

**Fix**:
1. Update `frontend/src/services/activityTracking.service.ts`:
   ```typescript
   // Change all tracking endpoints from:
   apiClient.publicPost('/track/pageview', ...)
   // To:
   apiClient.publicPost('/public/track/pageview', ...)
   ```

2. Apply same fix to all tracking endpoints:
   - `/track/pageview` → `/public/track/pageview`
   - `/track/page-exit` → `/public/track/page-exit`
   - `/track/conversion` → `/public/track/conversion`
   - `/track/event` → `/public/track/event`

### Issue 2: 500 Internal Server Error
**Error**: `POST http://localhost:5176/api/public/track/pageview 500 (Internal Server Error)`

**Root Causes**:
1. Validation expecting full URLs but receiving paths
2. Field name mismatch between frontend and backend
3. Missing database tables

**Fixes**:

1. **Remove URL validation** in `backend/app/Http/Controllers/ActivityTrackingController.php`:
   ```php
   // Change from:
   'page_url' => 'required|string|url',
   // To:
   'page_url' => 'required|string',
   ```

2. **Fix field name mismatch** in `frontend/src/services/activityTracking.service.ts`:
   ```typescript
   // Change from:
   title: data?.title || document.title,
   // To:
   page_title: data?.title || document.title,
   ```

3. **Add missing routes** in `backend/routes/public.php`:
   ```php
   // Add these routes in the /track group:
   $track->post('/page-exit', [ActivityTrackingController::class, 'trackPageExit'])
       ->setName('public.track.page-exit');
   
   $track->post('/conversion', [ActivityTrackingController::class, 'trackConversion'])
       ->setName('public.track.conversion');
   ```

4. **Add missing controller method** in `backend/app/Http/Controllers/ActivityTrackingController.php`:
   ```php
   public function trackConversion(Request $request, Response $response, array $args): Response
   {
       $data = $this->validate($request, [
           'visitor_id' => 'required|string',
           'session_id' => 'required|string',
           'conversion_type' => 'required|string',
           'conversion_value' => 'sometimes|numeric',
           'metadata' => 'sometimes|array'
       ]);
       
       try {
           // Track as special event
           return $this->trackEvent($request->withParsedBody(array_merge($data, [
               'event_type' => 'conversion',
               'event_name' => $data['conversion_type'],
               'event_data' => [
                   'value' => $data['conversion_value'] ?? null,
                   'metadata' => $data['metadata'] ?? []
               ]
           ])), $response, $args);
           
       } catch (\Exception $e) {
           return $this->error($response, 'Failed to track conversion: ' . $e->getMessage(), 500);
       }
   }
   ```

### Issue 3: Missing Database Tables
**Error**: Database operations failing because activity_tracking_* tables don't exist

**Temporary Fix** (to continue testing):
Replace database operations in `trackPageView` method with:
```php
// Temporarily return success without database operations
// TODO: Enable database tracking once tables are created
$result = [
    'visitor_id' => $visitorId,
    'session_id' => $sessionId,
    'page_view_id' => 'tracked_' . uniqid(),
    'status' => 'success'
];
```

**Permanent Fix**:
1. Create migration file: `backend/database/migrations/create_activity_tracking_tables.php`
2. Create migration runner: `backend/bin/migrate.php`
3. Run: `php backend/bin/migrate.php`

## Common Patterns to Check

### API Path Mismatches
- Frontend calls must match backend route definitions
- Check for `/public` prefix on public endpoints
- Verify route groups in `backend/routes/*.php`

### Field Name Mismatches
- Frontend request fields must match backend validation rules
- Common mismatches: `title` vs `page_title`, `url` vs `page_url`

### Validation Issues
- Backend may expect strict formats (URLs, emails) that frontend doesn't provide
- Solution: Relax validation or format data correctly in frontend

### Missing Database Tables
- Check if models reference tables that don't exist
- Create migrations for missing tables
- Run migrations before testing database operations

## Quick Debugging Steps

1. **Check browser console** for exact error and request details
2. **Check request payload** in Network tab to see what's being sent
3. **Check backend routes** to ensure endpoint exists
4. **Check validation rules** to ensure they match request data
5. **Temporarily bypass database** operations to isolate issues
6. **Add debug logging** to pinpoint exact failure point

## Files to Check When Debugging

- Frontend API calls: `frontend/src/services/*.service.ts`
- API client: `frontend/src/lib/api-client.ts`
- Backend routes: `backend/routes/*.php`
- Controllers: `backend/app/Http/Controllers/*.php`
- Models: `backend/app/Models/*.php`
- Database config: `backend/.env` and `backend/config/database.php`