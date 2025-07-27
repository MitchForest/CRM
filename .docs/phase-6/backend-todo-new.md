# Backend Migration TODO - Phase 6 (Condensed)

## 🚨 CRITICAL: FOLLOW THE FUCKING DATABASE - SNAKE_CASE ONLY 🚨

### NO LARAVEL. NO TRANSFORMATIONS. DATABASE IS GOD.

**RETURN EXACT DATABASE FIELD NAMES - NO EXCEPTIONS:**
- ✅ Database has `email1` → Return `email1` (NOT `email`)
- ✅ Database has `phone_work` → Return `phone_work` (NOT `phone`)
- ✅ Database has `assigned_user_id` → Return `assigned_user_id` (NOT `assignedUserId`)
- ✅ Database has `date_entered` → Return `date_entered` (NOT `dateEntered`)
- ❌ NEVER transform: No accessors, no camelCase, no "helpful" renaming

**How to ensure compliance:**
1. Open the Model file
2. Look at the `$fillable` array - those are the EXACT database fields
3. Return ONLY those field names, EXACTLY as they appear
4. NO Laravel getFullNameAttribute() bullshit
5. NO field renaming for "better names"

**See `.docs/phase-6/SNAKE_CASE_ENFORCEMENT.md` for complete field mappings**

## Current Status Summary
- **Progress**: 13/17 controllers (76%) migrated to Slim ✅
- **Backend**: Running in Docker on port 8080
- **Working**: Auth, Leads, Contacts, Opportunities, Cases, Dashboard, AI, Schema, FormBuilder, ActivityTracking, KnowledgeBase, Analytics, Activities

## ⚠️ CRITICAL BUG: Controllers Using camelCase ⚠️
**These controllers are incorrectly returning camelCase and MUST be fixed:**

### 🔴 HIGH PRIORITY - Breaking API consistency:
1. **AuthController** - Returns `firstName`, `lastName`, `phoneWork` (lines 81-82, 172-173, 177)
2. **OpportunitiesController** - Returns `leadSource`, `accountName`, `assignedUserId`, `dateEntered`, `dateModified` (lines 284-292)
3. **CasesController** - Returns `assignedUserId`, `dateEntered`, `dateModified` (lines 73-76, 129-132)
4. **ActivitiesController** - Returns `assignedUserId`, `parentType`, `parentId`, `dateEntered`, `dateModified` in formatActivity() (lines 656-661)
5. **DashboardController** - Returns `leadSource`, `dateEntered` (lines 186-188)
6. **ContactsController** - Returns `dateEntered` (line 250)

**ALL responses must use snake_case like the database!**
- **Fixed**: 
  - SchemaController bug with tableNameToInterface method (now handles plurals correctly) ✅
  - User model Case class reference issue ✅

## Critical Issue RESOLVED
All critical public-facing controllers have been migrated!

## COMPLETE BACKEND ALIGNMENT CHECKLIST

### 🔴 STEP 1: Fix ALL Controllers Using camelCase (Day 1)
**Must return EXACT database field names:**
1. **AuthController** - Fix lines 81-82, 172-173, 177, 191-192, 195, 207-209
   - `firstName` → `first_name`
   - `lastName` → `last_name`
   - `phoneWork` → `phone_work`
2. **OpportunitiesController** - Fix lines 284-292
   - `leadSource` → `lead_source`
   - `accountName` → `account_name`
   - `assignedUserId` → `assigned_user_id`
   - `dateEntered` → `date_entered`
   - `dateModified` → `date_modified`
3. **CasesController** - Fix lines 73-76, 129-132, 150, 165, 206, 220
   - `assignedUserId` → `assigned_user_id`
   - `dateEntered` → `date_entered`
   - `dateModified` → `date_modified`
4. **ActivitiesController** - Fix formatActivity() method lines 656-661
   - `assignedUserId` → `assigned_user_id`
   - `assignedUserName` → Remove or use exact DB field
   - `parentType` → `parent_type`
   - `parentId` → `parent_id`
   - `dateEntered` → `date_entered`
   - `dateModified` → `date_modified`
5. **DashboardController** - Fix lines 186-188
   - `leadSource` → `lead_source`
   - `dateEntered` → `date_entered`
6. **ContactsController** - Fix line 250
   - `dateEntered` → `date_entered`
7. **AnalyticsController** - Review all methods for camelCase
8. **EmailController** - Fix all camelCase fields

### 🟠 STEP 2: Remove Model Accessors (Day 1)
**Remove these from models - they create fields that don't exist in DB:**
1. **Lead.php** - Remove lines 55-65
   - Remove `protected $appends = ['full_name', 'latest_score'];`
   - Remove `getFullNameAttribute()` method
   - Remove `getLatestScoreAttribute()` method
2. **Contact.php** - Remove similar accessors
   - Remove `getFullNameAttribute()` method
3. **User.php** - Remove similar accessors
   - Remove `getFullNameAttribute()` method

### 🟡 STEP 3: Complete Controller Migration (Day 2)

## Remaining Controllers to Migrate (4 Total)

### ✅ COMPLETED - Critical Controllers (Day 1)
1. **FormBuilderController** - All methods migrated, includes all public/admin endpoints ✅
2. **ActivityTrackingController** - All methods migrated, tracking functionality ready ✅
3. **KnowledgeBaseController** - All methods migrated, KB search and articles work ✅

### ✅ COMPLETED - Day 2 (Core Features)

#### 4. AnalyticsController (5 methods) ✅ COMPLETED
**Impact**: Dashboard analytics broken (used in CRM routes)
**Methods**: salesAnalytics, leadAnalytics, activityAnalytics, conversionAnalytics, teamPerformance
**Status**: COMPLETED - All 5 methods migrated to Slim (NEEDS snake_case review)

#### 5. ActivitiesController (8 methods) ✅ COMPLETED BUT BROKEN
**Impact**: Can't manage calls, meetings, tasks (used in CRM routes)
**Methods**: index, upcoming, overdue, createTask, createCall, createMeeting, update, delete
**Status**: COMPLETED - All 8 methods migrated BUT uses camelCase in formatActivity()

### 🔴 STEP 4: Migrate Remaining Controllers (Day 2)

#### 6. EmailController (5 methods)
**Impact**: Email templates broken (used in admin routes)
**Methods**: getTemplates, createTemplate, updateTemplate, deleteTemplate, sendTestEmail

#### 7. CustomerHealthController (7 methods)
**Impact**: Health scoring features broken (used in admin routes)
**Methods**: getRules, createRule, updateRule, deleteRule, calculateScores, getScores, getHealthTrends

#### 8. DocumentController (4 methods)
**Impact**: Document management broken (used in admin routes)
**Methods**: upload, getDocument, deleteDocument, downloadDocument

### 🟢 STEP 5: Final Controller (Day 2)

#### 9. HealthController (1 method)
**Impact**: System health check endpoint (not used in routes currently)
**Methods**: check

### 🔵 STEP 6: Update ALL Services (Day 3)
**Remove Laravel facades and ensure snake_case returns:**
1. **AI Services** - Remove Log/Cache facades, return snake_case
2. **CRM Services** - Remove DB facades, return exact DB fields
3. **Email Service** - Remove Mail facade, use PHPMailer
4. **Form Services** - Ensure snake_case in all responses
5. **Activity Services** - Return exact DB field names

### 🟣 STEP 7: Remove Laravel/SuiteCRM (Day 3)
1. Delete `/backend/suitecrm` directory
2. Delete `/backend/scripts` directory
3. Remove all Laravel-specific files
4. Clean up composer.json dependencies
5. Remove any remaining Laravel configs

### ✅ STEP 8: Implement OpenAPI/Swagger (Day 4)
1. **Install OpenAPI tools**
   ```bash
   composer require zircote/swagger-php
   composer require cebe/openapi
   ```

2. **Document ALL endpoints with OpenAPI annotations**
   ```php
   /**
    * @OA\Get(
    *     path="/api/crm/leads",
    *     tags={"Leads"},
    *     summary="Get all leads",
    *     @OA\Response(
    *         response=200,
    *         description="List of leads",
    *         @OA\JsonContent(
    *             @OA\Property(property="data", type="array",
    *                 @OA\Items(
    *                     @OA\Property(property="id", type="string"),
    *                     @OA\Property(property="first_name", type="string"),
    *                     @OA\Property(property="last_name", type="string"),
    *                     @OA\Property(property="email1", type="string"),
    *                     @OA\Property(property="phone_work", type="string")
    *                 )
    *             )
    *         )
    *     )
    * )
    */
   ```

3. **Generate OpenAPI spec**
   ```bash
   ./vendor/bin/openapi --output openapi.json backend/app
   ```

4. **Setup Swagger UI**
   - Serve at `/api/docs`
   - Point to generated openapi.json

### 🎯 STEP 9: Generate TypeScript Client (Day 4)
1. **Use OpenAPI Generator**
   ```bash
   npm install @openapitools/openapi-generator-cli -g
   openapi-generator-cli generate \
     -i openapi.json \
     -g typescript-axios \
     -o frontend/src/api/generated \
     --additional-properties=useSingleRequestParameter=true,withSeparateModelsAndApi=true,modelPropertyNaming=snake_case
   ```

2. **Ensure ALL types use snake_case**
   - Review generated interfaces
   - All properties must match exact DB fields
   - No camelCase anywhere

3. **Update frontend to use generated client**
   - Replace manual API calls
   - Use generated types everywhere
   - Type safety with exact DB fields

### 🟢 STEP 10: Final Testing & Validation (Day 4)
1. Test every single endpoint
2. Verify all responses use snake_case
3. Check TypeScript types match exactly
4. No field transformation anywhere
5. Database fields = API fields = TypeScript types

## Migration Pattern (MUST FOLLOW)

### 🔴 CRITICAL: Return snake_case fields ONLY
```php
// ❌ WRONG - Never use camelCase
return [
    'assignedUserId' => $lead->assigned_user_id,
    'dateEntered' => $lead->date_entered,
    'firstName' => $lead->first_name
];

// ✅ CORRECT - Always use snake_case
return [
    'assigned_user_id' => $lead->assigned_user_id,
    'date_entered' => $lead->date_entered,
    'first_name' => $lead->first_name
];
```

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

### REMINDER: Everything is snake_case - NO EXCEPTIONS
**The backend MUST use snake_case field names everywhere:**
- Database fields: `first_name`, `last_name`, `email1`, `phone_work`, `account_name`
- API requests/responses: Exact same snake_case fields (no transformation)
- Controller returns: `assigned_user_id` NOT `assignedUserId`
- Service properties: `date_entered` NOT `dateEntered`
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

### Migrated Controllers (13/17)
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
12. ✅ AnalyticsController - Sales, lead, activity, conversion, team performance analytics
13. ✅ ActivitiesController - All activity types (calls, meetings, tasks, notes) with upcoming/overdue ⚠️ USES CAMELCASE

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

## Timeline Update

### Day 1 COMPLETED ✅
- ✅ FormBuilderController - All 8 methods + 5 additional public/admin methods
- ✅ ActivityTrackingController - All 6 methods + 12 additional admin methods  
- ✅ KnowledgeBaseController - All 7 methods + 14 additional public/admin methods
- ✅ Fixed critical bugs (SchemaController pluralization, User model Case reference)

### Remaining Timeline
- **Day 2**: Analytics + Activities (core CRM features)
- **Day 3**: Email + CustomerHealth + Document (admin features)
- **Day 4**: Health + Service updates + remove Laravel dependencies
- **Day 5**: Implement TESTING_STRATEGY.md

**Total Timeline**: 4 days to complete backend alignment
- Day 1: Fix all camelCase + remove accessors
- Day 2: Complete remaining controller migrations
- Day 3: Update services + remove Laravel
- Day 4: OpenAPI + TypeScript generation + testing

**End Result**: 
- Backend returns EXACT database fields
- OpenAPI documentation for all endpoints
- Auto-generated TypeScript client
- Complete type safety with snake_case everywhere
- No Laravel, no field transformations, just pure data

## 🚀 HANDOFF CHECKLIST

### Backend Ready When:
☐ All controllers return snake_case (exact DB fields)
☐ No model accessors creating fake fields
☐ All 17 controllers migrated to Slim
☐ All services free of Laravel facades
☐ Laravel/SuiteCRM directories deleted
☐ OpenAPI spec generated and accurate
☐ Swagger UI available at /api/docs
☐ TypeScript client generated with snake_case
☐ All endpoints tested and working
☐ No 500 errors anywhere

### Documentation Provided:
☐ OpenAPI spec (openapi.json)
☐ Database schema with exact field names
☐ API endpoint documentation
☐ TypeScript interfaces matching DB
☐ Migration guide for frontend

### Frontend Integration:
☐ Generated TypeScript client in `frontend/src/api/generated`
☐ All types use snake_case properties
☐ Example usage for each endpoint
☐ No manual API calls needed
☐ Complete type safety

## Next Immediate Steps
1. Continue with AnalyticsController and ActivitiesController (Day 2)
2. Update services to remove Laravel facades after all controllers migrated
3. Test each endpoint to ensure proper functionality
4. Implement comprehensive testing strategy