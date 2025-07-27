# Backend Migration TODO - Phase 6 (Condensed)

## Current Status Summary
- **Progress**: 17/17 controllers (100%) migrated to Slim ✅ 🎉
- **Backend**: Running in Docker on port 8080
- **Working**: ALL controllers now fully migrated to Slim!
- **Fixed**: 
  - SchemaController bug with tableNameToInterface method (now handles plurals correctly) ✅
  - User model Case class reference issue ✅
  - CustomerHealthController paginate method replaced with manual pagination ✅
  - DocumentController paginate method replaced with manual pagination ✅
  - All controllers confirmed using snake_case field names (no camelCase) ✅

## Critical Issue RESOLVED
All controllers have been migrated and are working with Slim!

## All Controllers Migrated ✅

### ✅ COMPLETED - Day 1 Controllers
1. **FormBuilderController** - All methods migrated ✅
2. **ActivityTrackingController** - All methods migrated ✅
3. **KnowledgeBaseController** - All methods migrated ✅

### ✅ COMPLETED - Day 2 Controllers (Core Features)
4. **AnalyticsController** - All 5 methods migrated ✅
5. **ActivitiesController** - All 8 methods migrated ✅

### ✅ COMPLETED - Day 3 Controllers (Admin Features)
6. **EmailController** - All 5 methods migrated ✅
7. **CustomerHealthController** - All 7 methods migrated + fixed paginate ✅
8. **DocumentController** - All 4 methods migrated + fixed paginate ✅

### ✅ COMPLETED - Day 4
9. **HealthController** - System health check migrated ✅

## CRITICAL MODEL & SERVICE FIX PATTERNS

### Model Fix Pattern
When fixing models, check for these Laravel-specific issues:
1. **Laravel Helpers**
   - `now()` → `new \DateTime()`
   - `now()->subDays(30)` → `(new \DateTime())->modify('-30 days')`
   - `now()->addMonths(3)` → `(new \DateTime())->modify('+3 months')`

2. **Virtual Attributes (Accessors)**
   - These create fields that don't exist in DB
   - Can break API responses if controllers return them
   - Examples found:
     - `getScorePercentageAttribute()` → returns non-DB field `score_percentage`
     - `getHealthStatusAttribute()` → returns non-DB field `health_status`
   - **FIX**: Either remove them OR ensure controllers never return these virtual fields

3. **Field Name Mismatches**
   - Model might reference wrong field names in methods
   - Example: `$this->status` when field is `is_published`
   - Example: `$this->views` when field is `view_count`

### Service Fix Pattern
When fixing services, check for:
1. **Wrong Collection Import**
   - `use Illuminate\Support\Collection;` → `use Illuminate\Database\Eloquent\Collection;`
   - Services affected: CaseService, KnowledgeBaseService, ContactService, UserService

2. **Field Mapping Issues**
   - `$lead->email` → `$lead->email1`
   - `$lead->phone` → `$lead->phone_work`
   - `$lead->source` → `$lead->lead_source`
   - `$lead->company` → `$lead->account_name`

3. **Virtual Attribute Usage**
   - `$score->score_percentage` → `$score->score * 100`
   - `$call->duration_total_minutes` → `$call->duration`
   - `$lead->latest_score` → `$lead->ai_score`

4. **Laravel Helpers**
   - Same as models: `now()` → `new \DateTime()`

### Impact Chain to Check
When fixing a model/service, verify:
1. **Model** → Check if any **Services** use the model's virtual attributes
2. **Service** → Check if any **Controllers** use the service methods
3. **Controller** → Check if API responses match **OpenAPI** documentation
4. **OpenAPI** → Check if generated **TypeScript** types are correct

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

### Migrated Controllers (17/17) ✅
1. ✅ AuthController - Login/logout/refresh working
2. ✅ LeadsController - Full CRUD + AI scoring
3. ✅ ContactsController - Full CRUD + unified view
4. ✅ OpportunitiesController - Full CRUD + pipeline
5. ✅ CasesController - Full CRUD + status updates
6. ✅ DashboardController - All metrics endpoints
7. ✅ AIController - Chat, scoring, insights
8. ✅ SchemaController - Type generation (pluralization bug fixed)
9. ✅ FormBuilderController - Forms CRUD + public submission + analytics
10. ✅ ActivityTrackingController - Session/page tracking + analytics
11. ✅ KnowledgeBaseController - Articles CRUD + public search + categories
12. ✅ AnalyticsController - All analytics endpoints
13. ✅ ActivitiesController - Full activity management
14. ✅ EmailController - Email templates + sending
15. ✅ CustomerHealthController - Health scoring + rules
16. ✅ DocumentController - Document management + revisions
17. ✅ HealthController - System health checks

### Services Status
- ✅ All services created but still using Laravel facades
- ⚠️ NEXT PRIORITY: Update services to remove Laravel dependencies

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

## Timeline Update

### Timeline Completed ✅
- **Day 1**: ✅ FormBuilder, ActivityTracking, KnowledgeBase controllers
- **Day 2**: ✅ Analytics + Activities controllers  
- **Day 3**: ✅ Email + CustomerHealth + Document controllers
- **Day 4**: ✅ Health controller

## 🚨 CRITICAL ISSUES TO FIX IMMEDIATELY

### 1. Model Migration Issues
- Models are not fully migrated from Laravel to Slim
- Models may still have Laravel-specific features (accessors, mutators, etc.)
- Need to ensure all models work with standalone Eloquent

### 2. Frontend TypeScript Integration Breaking
- Generated TypeScript types from backend may not match actual API responses
- Type safety is broken between frontend and backend
- Need to verify OpenAPI spec generation is working correctly

### 3. Services Still Using Laravel Facades
- All services need to be updated to remove Laravel dependencies
- This is blocking full migration completion

## Next Immediate Steps (HIGH PRIORITY)
1. **Check and fix model issues** - Ensure all models work with standalone Eloquent ✅ COMPLETED
   - ✅ Fixed Lead model (removed now() helper)
   - ✅ Fixed KnowledgeBaseArticle model (fixed field names and methods)
   - ✅ All models checked - only minor issues found and fixed
   - ✅ Virtual attributes (accessors) exist but controllers don't expose them
2. **Fix TypeScript generation** - Verify OpenAPI spec and type generation
3. **Update all services** - Remove Laravel facades and dependencies ✅ COMPLETED
   - ✅ Fixed ALL services:
     - ✅ Collection imports (Illuminate\Support → Illuminate\Database\Eloquent)
     - ✅ now() helpers replaced with new DateTime()
     - ✅ Log facade replaced with error_log()
     - ✅ Field mappings corrected (email → email1, phone → phone_work, etc.)
4. **Verify snake_case consistency** - ✅ COMPLETED
   - ✅ Checked all controllers - ALL are using snake_case correctly
   - ✅ No camelCase fields being returned by API
4. **Test API endpoints** - Ensure responses match expected types
5. **Implement comprehensive testing** - Follow TESTING_STRATEGY.md