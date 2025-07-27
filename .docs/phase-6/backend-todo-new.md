# Backend Migration TODO - Phase 6 (Condensed)

## Current Status Summary
- **Progress**: 8/17 controllers (47%) migrated to Slim ✅
- **Backend**: Running in Docker on port 8080
- **Working**: Auth, Leads, Contacts, Opportunities, Cases, Dashboard, AI, Schema
- **Fixed**: SchemaController bug with tableNameToInterface method ✅

## Critical Issue
The backend uses Slim 4 framework, but 9 controllers still have Laravel-style code that **WILL NOT WORK**.

## Remaining Controllers to Migrate (9 Total)

### 🔴 CRITICAL - Public/Admin Features (Day 1)
1. **FormBuilderController** - Lead capture forms BROKEN (used in public routes)
2. **ActivityTrackingController** - Session tracking BROKEN (used in public routes)
3. **KnowledgeBaseController** - KB articles BROKEN (used in public routes)

### 🔴 HIGH PRIORITY - Day 2 (Core Features)

#### 4. AnalyticsController (5 methods)
**Impact**: Dashboard analytics broken (used in CRM routes)
**Methods**: salesAnalytics, leadAnalytics, activityAnalytics, conversionAnalytics, teamPerformance

#### 5. ActivitiesController (8 methods)
**Impact**: Can't manage calls, meetings, tasks (used in CRM routes)
**Methods**: index, upcoming, overdue, createTask, createCall, createMeeting, update, delete

### 🟡 MEDIUM PRIORITY - Day 3 (Admin Features)

#### 6. EmailController (5 methods)
**Impact**: Email templates broken (used in admin routes)
**Methods**: getTemplates, createTemplate, updateTemplate, deleteTemplate, sendTestEmail

#### 7. CustomerHealthController (7 methods)
**Impact**: Health scoring features broken (used in admin routes)
**Methods**: getRules, createRule, updateRule, deleteRule, calculateScores, getScores, getHealthTrends

#### 8. DocumentController (4 methods)
**Impact**: Document management broken (used in admin routes)
**Methods**: upload, getDocument, deleteDocument, downloadDocument

### 🟢 LOW PRIORITY - Day 4

#### 9. HealthController (1 method)
**Impact**: System health check endpoint (not used in routes currently)
**Methods**: check

## Migration Pattern (MUST FOLLOW)

### 1. Update Imports
```php
// REMOVE these
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ADD these
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
```

### 2. Fix ALL Method Signatures
```php
// WRONG (Laravel)
public function index(Request $request): JsonResponse

// CORRECT (Slim)
public function index(Request $request, Response $response, array $args): Response
```

### 3. Fix Request Handling
```php
// GET parameters
$params = $request->getQueryParams();
$page = intval($params['page'] ?? 1);

// POST/PUT data
$data = $this->validate($request, [
    'name' => 'required|string',
    'email' => 'required|email'
]);

// Route parameters
$id = $args['id'];

// Headers
$token = $request->getHeaderLine('Authorization');
```

### 4. Fix Response Handling
```php
// JSON responses
return $this->json($response, ['data' => $result]);
return $this->json($response, ['data' => $result], 201);

// Error responses
return $this->error($response, 'Not found', 404);

// File downloads
$response->getBody()->write($fileContents);
return $response
    ->withHeader('Content-Type', 'application/pdf')
    ->withHeader('Content-Disposition', 'attachment; filename="file.pdf"');
```

### 5. Fix Common Helpers
```php
// Authentication
auth()->id()                    → $request->getAttribute('user_id')
auth()->user()                  → User::find($request->getAttribute('user_id'))

// Dates
now()                          → new \DateTime()
now()->toIso8601String()       → (new \DateTime())->format('c')
now()->subDays(30)             → (new \DateTime())->modify('-30 days')

// Validation
$request->validate()           → $this->validate($request, [...])
$request->validated()          → $data (from validate result)
$request->has('key')          → isset($data['key'])
$request->boolean('active')   → (bool)($data['active'] ?? false)

// Logging
Log::error($msg)              → error_log($msg)
\Log::info($msg)              → error_log($msg)

// Environment
env('KEY')                    → $_ENV['KEY'] ?? 'default'
```

### 6. Fix Service Instantiation
```php
// WRONG (Laravel auto-injection)
public function __construct(FormBuilderService $service)
{
    $this->service = $service;
}

// CORRECT (Slim manual)
public function __construct()
{
    parent::__construct();
    $this->service = new FormBuilderService();
    // If service has dependencies:
    $this->service = new FormBuilderService(new OtherService());
}
```

## Testing After Migration

### Test Each Controller Immediately
```bash
# Get auth token first
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "password"}'

# Test your migrated endpoint
curl -X GET http://localhost:8080/api/crm/forms \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Common Errors to Watch For
1. **500 Error**: Method signature wrong or Laravel code still present
2. **TypeError**: Wrong parameter types (check Request/Response imports)
3. **Call to undefined method**: Using Laravel helpers (response(), auth(), etc.)
4. **Service not found**: Manual instantiation needed

## Services Needing Updates

After controller migration, update these services:
- **FormBuilderService**: Remove facades, use exact DB fields
- **ActivityTrackingService**: Fix date handling, remove auth() helper
- **KnowledgeBaseService**: Update search to use DB instance
- **AnalyticsService**: Fix aggregation queries
- **EmailService**: Remove Mail facade, use PHPMailer

## Database Field Mapping

### Important: Database uses snake_case, API preserves snake_case
The backend correctly uses snake_case field names matching the database schema. This is intentional and correct:
- Database fields: `first_name`, `last_name`, `email1`, `phone_work`, `account_name`
- API requests/responses: Same snake_case fields
- TypeScript types: Generated with snake_case to match API exactly

### Critical field mappings to remember:
- `email` → `email1` (in DB)
- `phone` → `phone_work` (in DB)
- `company` → `account_name` (in DB)

### Why snake_case is correct:
1. Database uses snake_case (SQL standard)
2. API preserves exact field names (no transformation)
3. Frontend can handle snake_case or transform if needed
4. Type safety is maintained end-to-end

## Success Criteria

1. All 17 controllers use Slim patterns
2. No 500 errors on any endpoint
3. Frontend can call all APIs successfully
4. All tests pass (implement TESTING_STRATEGY.md after)

## Current Architecture Status

### Working Components
1. **Database**: MySQL 8.0 running in Docker
2. **Backend Framework**: Slim 4 with Eloquent ORM
3. **Authentication**: JWT-based auth working
4. **Models**: All Eloquent models created with proper relationships
5. **Base Controller**: Slim-compatible base controller with validation
6. **Routes**: All routes defined (public, auth, CRM, admin)

### Migrated Controllers (8/17)
1. ✅ AuthController - Login/logout/refresh working
2. ✅ LeadsController - Full CRUD + AI scoring
3. ✅ ContactsController - Full CRUD + unified view
4. ✅ OpportunitiesController - Full CRUD + pipeline
5. ✅ CasesController - Full CRUD + status updates
6. ✅ DashboardController - All metrics endpoints
7. ✅ AIController - Chat, scoring, insights
8. ✅ SchemaController - Type generation (bug fixed)

### Services Status
- ✅ All services created but using Laravel facades
- ⚠️ Need to update after controller migration

## Migration Steps Already Completed

From the implementation plan:
- ✅ Phase 1: Clean slate - Docker setup complete
- ✅ Phase 2: Database schema extracted
- ✅ Phase 3: Eloquent ORM installed and configured
- ✅ Phase 4: All models created
- ✅ Phase 5: Base controller created
- ✅ Phase 6: API routes set up
- ⚠️ Phase 7: NOT DONE - Old SuiteCRM files still present
- ⚠️ Phase 8: Partially done - need to complete controller migration

## Timeline Estimate

- **Day 1**: FormBuilder + ActivityTracking + KnowledgeBase (critical public features)
- **Day 2**: Analytics + Activities (core CRM features)
- **Day 3**: Email + CustomerHealth + Document (admin features)
- **Day 4**: Health + Service updates + testing
- **Day 5**: Implement TESTING_STRATEGY.md

**Total**: 5 days to completion

## Next Immediate Steps
1. Start with FormBuilderController migration (it's actively used in public routes)
2. Test each controller immediately after migration
3. Update related services to remove Laravel dependencies
4. Run full integration tests