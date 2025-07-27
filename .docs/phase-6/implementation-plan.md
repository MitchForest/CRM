# Implementation Plan: Clean Architecture Migration

## Overview

Migrate from monolithic SuiteCRM to clean architecture while preserving all our custom features. Focus on 80/20 approach - get everything working simply and correctly.

## Current Features to Preserve
1. **Marketing Website** with embeddable tools
2. **Session Tracking** - Track visitor behavior
3. **Lead Forms** - Embeddable capture forms
4. **AI Chatbot** - Sales and support conversations
5. **CRM Core** - Leads, Opportunities, Contacts, Support Tickets
6. **Unified Timeline** - Complete customer history
7. **Admin Tools** - KB editor, form builder, chatbot settings
8. **AI Scoring** - Lead and opportunity scoring

## Phase 1: Clean Slate (Day 1)

### 1.1 Backup Everything First
```bash
# Backup current database
docker exec crm-mysql mysqldump -u root -proot suitecrm > backup_$(date +%Y%m%d).sql

# Backup custom code
tar -czf custom_code_backup.tar.gz backend/custom/
```

### 1.2 Complete Cleanup
```bash
# Stop all containers
docker-compose down

# Remove volumes
docker volume rm crm_mysql_data

# Delete backend but keep custom
mv backend/custom /tmp/custom_backup
rm -rf backend/

# Clean up test files
find . -name "test*.php" -delete
find . -name "debug*.php" -delete
find . -name "*.test.js" -delete
```

### 1.2 Create New Structure
```bash
# Create clean backend structure
mkdir -p backend/{models,controllers,api,services,config,database}
mkdir -p backend/api/{routes,middleware}
mkdir -p backend/database/{migrations,seeds}
mkdir -p backend/services/{ai,crm,embeddings}
```

### 1.3 Clone Fresh SuiteCRM
```bash
# Clone to temporary location
cd backend/
git clone https://github.com/salesagility/SuiteCRM-Core.git temp-suitecrm
cd ..
```

## Phase 2: Extract What We Need (Day 1-2)

### 2.1 Database Schema Extraction
```bash
# Start fresh MySQL
docker run -d \
  --name crm-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=crm \
  -e MYSQL_USER=crm \
  -e MYSQL_PASSWORD=crm \
  -p 3306:3306 \
  mysql:8.0

# Install minimal SuiteCRM just to get schema
cd backend/temp-suitecrm
# Run installer to create tables
# Then export schema
docker exec crm-mysql mysqldump -u root -proot crm --no-data > ../database/suitecrm-schema.sql
```

### 2.2 Tables We're Using
```sql
-- Core CRM tables from SuiteCRM
leads                    -- Lead records
contacts                 -- Customer records
accounts                 -- Company records  
opportunities           -- Sales opportunities
cases                   -- Support tickets
users                   -- Employees (sales, support, etc)
tasks, calls, meetings  -- Activities
notes                   -- Notes/attachments

-- Relationship tables
accounts_contacts
accounts_opportunities
contacts_cases
opportunities_contacts

-- Our custom tables (already created)
activity_tracking       -- Website sessions
ai_conversations       -- Chat conversations
ai_chat_messages      -- Individual messages
form_submissions      -- Form data
form_builder_forms    -- Form definitions
ai_lead_scores       -- Historical scores
kb_articles          -- Knowledge base
kb_categories        -- KB organization
api_refresh_tokens   -- JWT refresh tokens
```

### 2.3 Update Custom Tables
```sql
-- Our custom tables are already in the database
-- Just need to ensure they're properly indexed

-- Add missing indexes for performance
ALTER TABLE activity_tracking ADD INDEX idx_session (session_id, created_at);
ALTER TABLE activity_tracking ADD INDEX idx_contact (contact_id);
ALTER TABLE activity_tracking ADD INDEX idx_lead (lead_id);

ALTER TABLE ai_conversations ADD INDEX idx_contact_conv (contact_id);
ALTER TABLE ai_conversations ADD INDEX idx_lead_conv (lead_id);

ALTER TABLE form_submissions ADD INDEX idx_form (form_id);
ALTER TABLE form_submissions ADD INDEX idx_created (created_at);

-- Future: Add embeddings table when we implement vector search
CREATE TABLE ai_embeddings (
    id CHAR(36) PRIMARY KEY,
    entity_type VARCHAR(50),
    entity_id CHAR(36),
    embedding_vector JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id)
);
```

## Phase 3: Setup Eloquent ORM (Day 2)

### 3.1 Install Dependencies
```json
// backend/composer.json
{
    "require": {
        "slim/slim": "^4.0",
        "slim/psr7": "^1.0",
        "illuminate/database": "^10.0",
        "illuminate/events": "^10.0",
        "firebase/php-jwt": "^6.0",
        "guzzlehttp/guzzle": "^7.0",
        "openai-php/client": "^0.6",
        "vlucas/phpdotenv": "^5.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "App\\Models\\": "models/",
            "App\\Controllers\\": "controllers/",
            "App\\Services\\": "services/"
        }
    }
}
```

### 3.2 Configure Eloquent
```php
// backend/config/database.php
<?php
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE', 'crm'),
    'username' => env('DB_USERNAME', 'crm'),
    'password' => env('DB_PASSWORD', 'crm'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();
```

## Phase 4: Create Models (Day 2-3)

### 4.1 Base Model for SuiteCRM Tables
```php
// backend/models/SuiteCRMModel.php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class SuiteCRMModel extends Model
{
    public $incrementing = false;
    public $keyType = 'string';
    public $timestamps = false; // SuiteCRM uses date_entered/date_modified
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = \Ramsey\Uuid\Uuid::uuid4()->toString();
            }
            $model->date_entered = now();
            $model->date_modified = now();
        });
        
        static::updating(function ($model) {
            $model->date_modified = now();
        });
    }
}
```

### 4.2 Core Models
```php
// backend/models/Lead.php
<?php
namespace App\Models;

class Lead extends SuiteCRMModel
{
    protected $table = 'leads';
    
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'company', 'title', 'status', 'assigned_user_id'
    ];
    
    protected $appends = ['ai_score', 'full_name'];
    
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
    
    public function aiScores()
    {
        return $this->hasMany(LeadScore::class, 'lead_id');
    }
    
    public function getAiScoreAttribute()
    {
        return $this->aiScores()->latest('scored_at')->first()?->score ?? 0;
    }
    
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
```

## Phase 5: Create Controllers (Day 3-4)

### 5.1 Base Controller
```php
// backend/controllers/BaseController.php
<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    protected function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
    
    protected function error(Response $response, string $message, int $status = 400): Response
    {
        return $this->json($response, ['error' => $message], $status);
    }
}
```

### 5.2 Lead Controller
```php
// backend/controllers/LeadController.php
<?php
namespace App\Controllers;

use App\Models\Lead;
use App\Services\AI\ScoringService;

class LeadController extends BaseController
{
    private $scoringService;
    
    public function __construct(ScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }
    
    public function index(Request $request, Response $response): Response
    {
        $leads = Lead::with('assignedUser')
            ->where('deleted', 0)
            ->orderBy('date_entered', 'desc')
            ->paginate(20);
            
        return $this->json($response, [
            'data' => $leads->items(),
            'meta' => [
                'total' => $leads->total(),
                'page' => $leads->currentPage(),
                'per_page' => $leads->perPage()
            ]
        ]);
    }
    
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $lead = Lead::create($data);
        
        // Queue AI scoring
        $this->scoringService->scoreLead($lead);
        
        return $this->json($response, ['data' => $lead], 201);
    }
}
```

## Phase 6: Setup API Routes (Day 4)

### 6.1 API Entry Point
```php
// backend/api/index.php
<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';

use Slim\Factory\AppFactory;
use App\Middleware\JwtMiddleware;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS middleware
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Routes
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/crm.php';
require __DIR__ . '/routes/ai.php';

$app->run();
```

### 6.2 CRM Routes
```php
// backend/api/routes/crm.php
use App\Controllers\LeadController;
use App\Middleware\JwtMiddleware;

$app->group('/api/crm', function ($group) {
    // Leads
    $group->get('/leads', [LeadController::class, 'index']);
    $group->post('/leads', [LeadController::class, 'store']);
    $group->get('/leads/{id}', [LeadController::class, 'show']);
    $group->put('/leads/{id}', [LeadController::class, 'update']);
    $group->delete('/leads/{id}', [LeadController::class, 'delete']);
    
    // Similar for contacts, accounts, opportunities
})->add(new JwtMiddleware());
```

## Phase 7: Delete Unnecessary Files (Day 5)

### 7.1 From SuiteCRM Keep Only
```
backend/temp-suitecrm/
├── Keep temporarily for reference:
│   ├── include/SugarObjects/templates/
│   ├── include/database/
│   └── modules/Users/authentication/
└── Delete everything else
```

### 7.2 Final Cleanup
```bash
# After extracting what we need
rm -rf backend/temp-suitecrm

# Clean docker
docker system prune -a
```

## Phase 8: Migrate Existing Features (Day 5-7)

### 8.1 Activity Tracking Service
```php
// Port existing ActivityTrackingController functionality
// backend/services/tracking/ActivityService.php
namespace App\Services;

class ActivityService {
    public function trackPageView($sessionId, $leadId, $pageUrl) {
        // Port existing tracking logic
    }
    
    public function getSessionHistory($contactId) {
        // Get all sessions for unified timeline
    }
}
```

### 8.2 AI Services
```php
// Port existing AI functionality
// backend/services/ai/LeadScoringService.php
namespace App\Services\AI;

class LeadScoringService {
    public function scoreLead($lead) {
        // Port existing scoring logic from AIController
        // Based on: activity, engagement, company info
    }
}

// backend/services/ai/ChatbotService.php  
class ChatbotService {
    public function handleMessage($conversationId, $message) {
        // Port existing chat logic
    }
}
```

### 8.3 Form Builder Service
```php
// backend/services/forms/FormService.php
namespace App\Services;

class FormService {
    public function getEmbedCode($formId) {
        // Generate embeddable form HTML/JS
    }
    
    public function processSubmission($formId, $data) {
        // Handle form submission, create lead if needed
    }
}
```

## Phase 9: Testing & Deployment (Day 7-8)

### 9.1 Test All Features
1. **Authentication** - JWT login/refresh
2. **Marketing Site** - Tracking script, forms, chatbot
3. **CRM Core**:
   - Dashboard stats
   - Leads CRUD + AI scoring
   - Opportunities pipeline
   - Contacts with unified timeline
   - Support tickets
4. **Embeddable Tools**:
   - Session tracking works
   - Forms capture leads
   - Chatbot responds correctly
5. **Admin Section**:
   - KB editor saves articles
   - Form builder creates forms
   - Chatbot settings update

### 9.2 Seed Database
```php
// backend/database/seeds/DemoDataSeeder.php
// Create realistic demo data for software sales

// Knowledge Base Articles
$articles = [
    'Getting Started with Our CRM',
    'API Documentation', 
    'Webhook Integration Guide',
    'Security Best Practices',
    'Troubleshooting Common Issues'
];

// Demo Leads with realistic software buyer data
$leads = [
    ['name' => 'John Smith', 'company' => 'TechStartup Inc', 'title' => 'CTO'],
    ['name' => 'Sarah Johnson', 'company' => 'SaaS Corp', 'title' => 'VP Sales'],
    // etc...
];

// Activity history for each lead
// Chat conversations
// Form submissions
// Page visits on pricing, features, docs
```

## Success Criteria

1. ✅ All existing features working
2. ✅ Clean codebase with Eloquent ORM
3. ✅ JWT auth for API
4. ✅ Marketing site uses our tools
5. ✅ Unified timeline shows all interactions
6. ✅ AI scoring and chat working
7. ✅ Demo data showcases capabilities

## Timeline

- **Day 1**: Backup, clean slate, new structure
- **Day 2**: Install Eloquent, create models
- **Day 3**: Port controllers to new architecture
- **Day 4**: Migrate all API endpoints
- **Day 5**: Port AI and tracking services
- **Day 6**: Testing and bug fixes
- **Day 7**: Seed demo data
- **Day 8**: Final testing and documentation

## Future Enhancement (After Core Migration)
- Add vector embeddings for knowledge base
- Implement semantic search in chatbot
- Enhanced AI scoring with embeddings

Total: 8 working days for complete migration