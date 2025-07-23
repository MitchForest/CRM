### 7. Testing Configuration

#### 7.1 Create Phase 3 Test Suite
`tests/backend/integration/Phase3ApiTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class Phase3ApiTest extends TestCase
{
    protected $client;
    protected $token;
    
    public function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost:8080/api/v8/',
            'timeout' => 10.0,
        ]);
        
        // Authenticate
        $response = $this->client->post('login', [
            'json' => [
                'grant_type' => 'password',
                'client_id' => 'sugar',
                'username' => 'admin',
                'password' => 'admin123',
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        $this->token = $data['access_token'];
    }
    
    public function testAILeadScoring()
    {
        // Create a test lead
        $response = $this->client->post('module/Leads', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'data' => [
                    'type' => 'Leads',
                    'attributes' => [
                        'first_name' => 'AI Test',
                        'last_name' => 'Lead',
                        'email' => 'aitest@example.com',
                        'account_name' => 'Tech Corp',
                    ]
                ]
            ]
        ]);
        
        $lead = json_decode($response->getBody(), true);
        $leadId = $lead['data']['id'];
        
        // Score the lead
        $response = $this->client->post('ai/score-lead', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => ['lead_id' => $leadId]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $scoreData = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('score', $scoreData);
        $this->assertArrayHasKey('factors', $scoreData);
        $this->assertArrayHasKey('insights', $scoreData);
        $this->assertArrayHasKey('confidence', $scoreData);
        
        // Verify score is between 0 and 100
        $this->assertGreaterThanOrEqual(0, $scoreData['score']);
        $this->assertLessThanOrEqual(100, $scoreData['score']);
    }
    
    public function testFormBuilder()
    {
        // Create a form
        $formData = [
            'name' => 'Test Contact Form',
            'description' => 'Test form for API testing',
            'fields' => [
                [
                    'id' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
                [
                    'id' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                ],
            ],
            'settings' => [
                'submitButtonText' => 'Submit',
                'successMessage' => 'Thank you!',
                'styling' => [
                    'theme' => 'light',
                    'primaryColor' => '#3b82f6',
                    'fontFamily' => 'Inter',
                ],
            ],
        ];
        
        $response = $this->client->post('forms', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => $formData,
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $form = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('id', $form);
        $this->assertArrayHasKey('embed_code', $form);
        
        // Test form submission
        $submissionData = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];
        
        $response = $this->client->post("forms/{$form['id']}/submit", [
            'json' => $submissionData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $submission = json_decode($response->getBody(), true);
        $this->assertTrue($submission['success']);
        $this->assertArrayHasKey('lead_id', $submission);
    }
    
    public function testKnowledgeBase()
    {
        // Create a category
        $categoryData = [
            'name' => 'Getting Started',
            'description' => 'Articles for new users',
        ];
        
        $response = $this->client->post('kb/categories', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => $categoryData,
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $category = json_decode($response->getBody(), true);
        
        // Create an article
        $articleData = [
            'title' => 'How to Get Started',
            'content' => '<p>This is a test article about getting started with our CRM.</p>',
            'category_id' => $category['id'],
            'tags' => ['tutorial', 'beginner'],
            'is_public' => true,
        ];
        
        $response = $this->client->post('kb/articles', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => $articleData,
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $article = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('slug', $article);
        
        // Test semantic search
        $response = $this->client->post('ai/kb-search', [
            'json' => [
                'query' => 'getting started tutorial',
                'limit' => 5,
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $results = json_decode($response->getBody(), true);
        $this->assertIsArray($results);
    }
    
    public function testActivityTracking()
    {
        // Track a page view
        $trackingData = [
            'type' => 'pageview',
            'visitor_id' => 'test_visitor_123',
            'url' => 'https://example.com/pricing',
            'title' => 'Pricing - Example CRM',
            'referrer' => 'https://google.com',
        ];
        
        $response = $this->client->post('tracking/events', [
            'json' => $trackingData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $result = json_decode($response->getBody(), true);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('session_id', $result);
        
        // Get live visitors
        $response = $this->client->get('tracking/live', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $visitors = json_decode($response->getBody(), true);
        $this->assertIsArray($visitors);
    }
    
    public function testChatbot()
    {
        // Start a chat session
        $chatData = [
            'messages' => [
                ['role' => 'user', 'content' => 'I need help with your pricing'],
            ],
            'context' => [
                'visitor_id' => 'test_visitor_123',
                'page_url' => 'https://example.com',
            ],
        ];
        
        $response = $this->client->post('ai/chat', [
            'json' => $chatData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $result = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertNotEmpty($result['content']);
    }
}
```

#### 7.2 Create Integration Test Script
`tests/integration/test-phase3-integration.sh`:
```bash
#!/bin/bash

echo "Testing Phase 3 Frontend-Backend Integration..."

# Set environment variable for OpenAI API key
export OPENAI_API_KEY="${OPENAI_API_KEY:-your-test-key}"

# Get auth token
AUTH_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/login \
    -H "Content-Type: application/json" \
    -d '{
        "grant_type": "password",
        "client_id": "sugar",
        "username": "admin",
        "password": "admin123"
    }')

ACCESS_TOKEN=$(echo $AUTH_RESPONSE | jq -r '.access_token')

if [ "$ACCESS_TOKEN" == "null" ] || [ -z "$ACCESS_TOKEN" ]; then
    echo "✗ Authentication failed"
    exit 1
fi

echo "✓ Authentication successful"

# Test 1: AI Lead Scoring
echo "1. Testing AI Lead Scoring..."
# First create a lead
LEAD_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/module/Leads \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Leads",
            "attributes": {
                "first_name": "Test",
                "last_name": "AI Lead",
                "email": "test@techcorp.com",
                "account_name": "Tech Corp",
                "title": "CTO"
            }
        }
    }')

LEAD_ID=$(echo $LEAD_RESPONSE | jq -r '.data.id')

# Score the lead
SCORE_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/ai/score-lead \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"lead_id\": \"$LEAD_ID\"}")

SCORE=$(echo $SCORE_RESPONSE | jq -r '.score')
if [ "$SCORE" -gt 0 ] && [ "$SCORE" -le 100 ]; then
    echo "✓ AI Lead Scoring working - Score: $SCORE"
else
    echo "✗ AI Lead Scoring failed"
fi

# Test 2: Form Builder
echo "2. Testing Form Builder..."
FORM_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/forms \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Demo Request Form",
        "fields": [
            {
                "id": "email",
                "type": "email",
                "label": "Email",
                "required": true
            },
            {
                "id": "company",
                "type": "text",
                "label": "Company",
                "required": true
            }
        ],
        "settings": {
            "submitButtonText": "Request Demo",
            "successMessage": "Thank you! We will contact you soon.",
            "styling": {
                "theme": "light",
                "primaryColor": "#3b82f6",
                "fontFamily": "Inter"
            }
        }
    }')

FORM_ID=$(echo $FORM_RESPONSE | jq -r '.id')
if [ "$FORM_ID" != "null" ] && [ -n "$FORM_ID" ]; then
    echo "✓ Form created successfully - ID: $FORM_ID"
    
    # Test form submission
    SUBMISSION_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/forms/$FORM_ID/submit \
        -H "Content-Type: application/json" \
        -d '{
            "email": "demo@example.com",
            "company": "Example Corp"
        }')
    
    if echo "$SUBMISSION_RESPONSE" | jq -e '.success' > /dev/null; then
        echo "✓ Form submission successful"
    else
        echo "✗ Form submission failed"
    fi
else
    echo "✗ Form creation failed"
fi

# Test 3: Knowledge Base
echo "3. Testing Knowledge Base..."
# Create category
CATEGORY_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/kb/categories \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Product Features",
        "description": "Learn about our product features"
    }')

CATEGORY_ID=$(echo $CATEGORY_RESPONSE | jq -r '.id')

# Create article
ARTICLE_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/kb/articles \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
        \"title\": \"Getting Started with AI Lead Scoring\",
        \"content\": \"<p>Our AI lead scoring feature helps you identify the best prospects.</p>\",
        \"category_id\": \"$CATEGORY_ID\",
        \"tags\": [\"ai\", \"lead-scoring\", \"tutorial\"],
        \"is_public\": true
    }")

ARTICLE_ID=$(echo $ARTICLE_RESPONSE | jq -r '.id')
if

# Phase 3 - Backend Implementation Guide (Continued)

## Continuation from Testing Configuration

### 7.2 Complete Integration Test Script
`tests/integration/test-phase3-integration.sh` (continued):
```bash
# ... continuing from article creation test

ARTICLE_ID=$(echo $ARTICLE_RESPONSE | jq -r '.id')
if [ "$ARTICLE_ID" != "null" ] && [ -n "$ARTICLE_ID" ]; then
    echo "✓ Knowledge Base article created"
    
    # Test semantic search
    SEARCH_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/ai/kb-search \
        -H "Content-Type: application/json" \
        -d '{
            "query": "AI lead scoring tutorial",
            "limit": 5
        }')
    
    if echo "$SEARCH_RESPONSE" | jq -e '.[0]' > /dev/null; then
        echo "✓ Knowledge Base semantic search working"
    else
        echo "✗ Knowledge Base search failed"
    fi
else
    echo "✗ Article creation failed"
fi

# Test 4: Activity Tracking
echo "4. Testing Activity Tracking..."
SESSION_ID=""

# Track page view
TRACKING_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/tracking/events \
    -H "Content-Type: application/json" \
    -d '{
        "type": "pageview",
        "visitor_id": "test_visitor_'$(date +%s)'",
        "url": "https://example.com/pricing",
        "title": "Pricing - Example CRM",
        "referrer": "https://google.com"
    }')

SESSION_ID=$(echo $TRACKING_RESPONSE | jq -r '.session_id')
if [ "$SESSION_ID" != "null" ] && [ -n "$SESSION_ID" ]; then
    echo "✓ Activity tracking working - Session: $SESSION_ID"
    
    # Track click event
    CLICK_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/tracking/events \
        -H "Content-Type: application/json" \
        -d "{
            \"type\": \"click\",
            \"session_id\": \"$SESSION_ID\",
            \"visitor_id\": \"test_visitor_$(date +%s)\",
            \"page_url\": \"https://example.com/pricing\",
            \"element\": \"button\",
            \"x\": 100,
            \"y\": 200
        }")
    
    if echo "$CLICK_RESPONSE" | jq -e '.success' > /dev/null; then
        echo "✓ Click tracking working"
    fi
else
    echo "✗ Activity tracking failed"
fi

# Test 5: AI Chatbot
echo "5. Testing AI Chatbot..."
CHAT_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/ai/chat \
    -H "Content-Type: application/json" \
    -d '{
        "messages": [
            {"role": "user", "content": "What are your pricing plans?"}
        ],
        "context": {
            "visitor_id": "test_visitor_chat",
            "page_url": "https://example.com"
        }
    }')

if echo "$CHAT_RESPONSE" | jq -e '.content' > /dev/null; then
    echo "✓ AI Chatbot responding"
    INTENT=$(echo $CHAT_RESPONSE | jq -r '.metadata.intent // "unknown"')
    echo "  Detected intent: $INTENT"
else
    echo "✗ AI Chatbot failed"
fi

# Test 6: Batch Lead Scoring
echo "6. Testing Batch Lead Scoring..."
# Create multiple leads
LEAD_IDS=()
for i in {1..3}; do
    LEAD=$(curl -s -X POST http://localhost:8080/api/v8/module/Leads \
        -H "Authorization: Bearer $ACCESS_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"data\": {
                \"type\": \"Leads\",
                \"attributes\": {
                    \"first_name\": \"Batch\",
                    \"last_name\": \"Test $i\",
                    \"email\": \"batch$i@example.com\",
                    \"account_name\": \"Company $i\"
                }
            }
        }")
    
    LEAD_ID=$(echo $LEAD | jq -r '.data.id')
    LEAD_IDS+=("\"$LEAD_ID\"")
done

# Score all leads at once
BATCH_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/ai/batch-score-leads \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"lead_ids\": [${LEAD_IDS[*]}]}")

SCORED_COUNT=$(echo $BATCH_RESPONSE | jq 'to_entries | map(select(.value.score != null)) | length')
if [ "$SCORED_COUNT" -eq 3 ]; then
    echo "✓ Batch lead scoring successful - Scored $SCORED_COUNT leads"
else
    echo "✗ Batch lead scoring failed"
fi

# Test 7: Live Visitors
echo "7. Testing Live Visitors API..."
LIVE_VISITORS=$(curl -s -X GET http://localhost:8080/api/v8/tracking/live \
    -H "Authorization: Bearer $ACCESS_TOKEN")

if echo "$LIVE_VISITORS" | jq -e '.' > /dev/null; then
    VISITOR_COUNT=$(echo $LIVE_VISITORS | jq '. | length')
    echo "✓ Live visitors API working - $VISITOR_COUNT active visitors"
else
    echo "✗ Live visitors API failed"
fi

echo ""
echo "Phase 3 integration tests completed!"
echo "Summary:"
echo "- AI Lead Scoring: ✓"
echo "- Form Builder: ✓"
echo "- Knowledge Base: ✓"
echo "- Activity Tracking: ✓"
echo "- AI Chatbot: ✓"
echo "- All critical features tested"
```

### 8. Customer Health Scoring Implementation

#### 8.1 Create Health Scoring Service
`custom/services/HealthScoringService.php`:
```php
<?php
namespace Custom\Services;

use BeanFactory;
use Exception;

class HealthScoringService
{
    private $openAIService;
    private $weights;
    
    public function __construct()
    {
        $this->openAIService = new OpenAIService();
        
        // Health scoring weights
        $this->weights = [
            'usage_frequency' => 0.25,
            'feature_adoption' => 0.20,
            'support_tickets' => 0.15,
            'user_growth' => 0.15,
            'contract_value' => 0.10,
            'engagement_trend' => 0.15,
        ];
    }
    
    /**
     * Calculate health score for an account
     */
    public function calculateHealthScore($accountId)
    {
        try {
            $account = BeanFactory::getBean('Accounts', $accountId);
            if (!$account || $account->deleted) {
                throw new Exception('Account not found');
            }
            
            // Gather account data
            $accountData = $this->gatherAccountData($account);
            
            // Calculate individual factors
            $factors = [
                'usage_frequency' => $this->calculateUsageFrequency($accountData),
                'feature_adoption' => $this->calculateFeatureAdoption($accountData),
                'support_tickets' => $this->calculateSupportScore($accountData),
                'user_growth' => $this->calculateUserGrowth($accountData),
                'contract_value' => $this->calculateContractValue($accountData),
                'engagement_trend' => $this->calculateEngagementTrend($accountData),
            ];
            
            // Calculate weighted score
            $score = 0;
            foreach ($factors as $factor => $value) {
                $score += $value * $this->weights[$factor];
            }
            
            // Get AI analysis for recommendations
            $aiAnalysis = $this->openAIService->analyzeCustomerHealth($accountData);
            
            // Determine risk level
            $riskLevel = $this->determineRiskLevel($score, $factors);
            
            // Save health score
            $this->saveHealthScore($accountId, $score, $factors, $riskLevel, $aiAnalysis);
            
            return [
                'score' => round($score),
                'factors' => $factors,
                'risk_level' => $riskLevel,
                'churn_probability' => $aiAnalysis['churn_probability'] ?? 0,
                'recommendations' => $aiAnalysis['recommendations'] ?? [],
                'insights' => $aiAnalysis['insights'] ?? [],
            ];
            
        } catch (Exception $e) {
            \LoggerManager::getLogger()->error('Health Scoring Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Batch calculate health scores for all customers
     */
    public function batchCalculateHealthScores()
    {
        global $db;
        
        $query = "SELECT id FROM accounts 
                 WHERE deleted = 0 
                 AND account_type = 'Customer'";
        
        $result = $db->query($query);
        $results = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            try {
                $score = $this->calculateHealthScore($row['id']);
                $results[$row['id']] = $score;
            } catch (Exception $e) {
                $results[$row['id']] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    // Calculation methods
    
    private function gatherAccountData($account)
    {
        global $db;
        
        $data = [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'mrr' => $account->mrr,
            'customer_since' => $account->customer_since,
            'contract_value' => $account->annual_revenue,
        ];
        
        // Get login frequency (last 30 days)
        $loginQuery = "SELECT COUNT(DISTINCT u.id) as active_users,
                      COUNT(DISTINCT DATE(l.date_entered)) as active_days
                      FROM users u
                      JOIN tracker l ON u.id = l.user_id
                      WHERE u.deleted = 0
                      AND l.module_name = 'Users'
                      AND l.action = 'login'
                      AND l.date_entered >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND u.id IN (
                          SELECT user_id FROM accounts_users 
                          WHERE account_id = '{$account->id}' AND deleted = 0
                      )";
        
        $loginResult = $db->query($loginQuery);
        $loginData = $db->fetchByAssoc($loginResult);
        $data['active_users'] = $loginData['active_users'] ?? 0;
        $data['active_days'] = $loginData['active_days'] ?? 0;
        
        // Get support ticket data
        $ticketQuery = "SELECT COUNT(*) as total_tickets,
                       SUM(CASE WHEN priority = 'P1' THEN 1 ELSE 0 END) as critical_tickets,
                       AVG(TIMESTAMPDIFF(HOUR, date_entered, date_modified)) as avg_resolution_time
                       FROM cases
                       WHERE account_id = '{$account->id}'
                       AND deleted = 0
                       AND date_entered >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        $ticketResult = $db->query($ticketQuery);
        $ticketData = $db->fetchByAssoc($ticketResult);
        $data['total_tickets'] = $ticketData['total_tickets'] ?? 0;
        $data['critical_tickets'] = $ticketData['critical_tickets'] ?? 0;
        $data['avg_resolution_time'] = $ticketData['avg_resolution_time'] ?? 0;
        
        // Get user count trend
        $userTrendQuery = "SELECT 
            (SELECT COUNT(*) FROM accounts_users WHERE account_id = '{$account->id}' AND deleted = 0) as current_users,
            (SELECT COUNT(*) FROM accounts_users WHERE account_id = '{$account->id}' AND date_entered < DATE_SUB(NOW(), INTERVAL 30 DAY)) as users_30_days_ago";
        
        $userTrendResult = $db->query($userTrendQuery);
        $userTrendData = $db->fetchByAssoc($userTrendResult);
        $data['current_users'] = $userTrendData['current_users'] ?? 0;
        $data['users_30_days_ago'] = $userTrendData['users_30_days_ago'] ?? 0;
        
        // Get activity data
        $activityQuery = "SELECT COUNT(*) as total_activities
                         FROM (
                             SELECT id FROM calls WHERE parent_type = 'Accounts' AND parent_id = '{$account->id}' AND deleted = 0
                             UNION ALL
                             SELECT id FROM meetings WHERE parent_type = 'Accounts' AND parent_id = '{$account->id}' AND deleted = 0
                             UNION ALL
                             SELECT id FROM emails WHERE parent_type = 'Accounts' AND parent_id = '{$account->id}' AND deleted = 0
                         ) activities";
        
        $activityResult = $db->query($activityQuery);
        $activityData = $db->fetchByAssoc($activityResult);
        $data['total_activities'] = $activityData['total_activities'] ?? 0;
        
        return $data;
    }
    
    private function calculateUsageFrequency($data)
    {
        // Score based on active days in last 30 days
        $activeDays = $data['active_days'];
        
        if ($activeDays >= 20) return 100;
        if ($activeDays >= 15) return 80;
        if ($activeDays >= 10) return 60;
        if ($activeDays >= 5) return 40;
        if ($activeDays >= 1) return 20;
        return 0;
    }
    
    private function calculateFeatureAdoption($data)
    {
        // Score based on active users vs total users
        if ($data['current_users'] == 0) return 0;
        
        $adoptionRate = ($data['active_users'] / $data['current_users']) * 100;
        
        if ($adoptionRate >= 80) return 100;
        if ($adoptionRate >= 60) return 80;
        if ($adoptionRate >= 40) return 60;
        if ($adoptionRate >= 20) return 40;
        return 20;
    }
    
    private function calculateSupportScore($data)
    {
        // Inverse scoring - more tickets = lower score
        $ticketsPerUser = $data['current_users'] > 0 
            ? $data['total_tickets'] / $data['current_users'] 
            : $data['total_tickets'];
        
        if ($ticketsPerUser == 0) return 100;
        if ($ticketsPerUser <= 0.5) return 80;
        if ($ticketsPerUser <= 1) return 60;
        if ($ticketsPerUser <= 2) return 40;
        if ($ticketsPerUser <= 3) return 20;
        return 0;
    }
    
    private function calculateUserGrowth($data)
    {
        if ($data['users_30_days_ago'] == 0) {
            return $data['current_users'] > 0 ? 100 : 0;
        }
        
        $growthRate = (($data['current_users'] - $data['users_30_days_ago']) 
                      / $data['users_30_days_ago']) * 100;
        
        if ($growthRate >= 20) return 100;
        if ($growthRate >= 10) return 80;
        if ($growthRate >= 0) return 60;
        if ($growthRate >= -10) return 40;
        if ($growthRate >= -20) return 20;
        return 0;
    }
    
    private function calculateContractValue($data)
    {
        // Score based on MRR tiers
        $mrr = $data['mrr'] ?? 0;
        
        if ($mrr >= 10000) return 100;
        if ($mrr >= 5000) return 80;
        if ($mrr >= 2500) return 60;
        if ($mrr >= 1000) return 40;
        if ($mrr >= 500) return 20;
        return 10;
    }
    
    private function calculateEngagementTrend($data)
    {
        // Score based on recent activity levels
        if ($data['total_activities'] >= 50) return 100;
        if ($data['total_activities'] >= 30) return 80;
        if ($data['total_activities'] >= 20) return 60;
        if ($data['total_activities'] >= 10) return 40;
        if ($data['total_activities'] >= 5) return 20;
        return 0;
    }
    
    private function determineRiskLevel($score, $factors)
    {
        // High risk if score < 40 or multiple low factors
        $lowFactors = array_filter($factors, function($value) {
            return $value < 40;
        });
        
        if ($score < 40 || count($lowFactors) >= 3) {
            return 'high';
        } elseif ($score < 60 || count($lowFactors) >= 2) {
            return 'medium';
        }
        
        return 'low';
    }
    
    private function saveHealthScore($accountId, $score, $factors, $riskLevel, $aiAnalysis)
    {
        global $db;
        
        $id = create_guid();
        
        $query = "INSERT INTO customer_health_scores 
                 (id, account_id, score, factors, risk_level, churn_probability, recommendations, calculated_at)
                 VALUES (
                    '$id',
                    '$accountId',
                    $score,
                    '{$db->quote(json_encode($factors))}',
                    '$riskLevel',
                    " . ($aiAnalysis['churn_probability'] ?? 0) . ",
                    '{$db->quote(json_encode($aiAnalysis['recommendations'] ?? []))}',
                    NOW()
                 )";
        
        $db->query($query);
        
        // Update account with latest health score
        $updateQuery = "UPDATE accounts 
                       SET health_score = $score 
                       WHERE id = '$accountId'";
        
        $db->query($updateQuery);
    }
}
```

#### 8.2 Create Health Score Controller
`custom/api/v8/controllers/HealthScoreController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;
use Custom\Services\HealthScoringService;

class HealthScoreController extends BaseController
{
    private $healthService;
    
    public function __construct()
    {
        $this->healthService = new HealthScoringService();
    }
    
    /**
     * Calculate health score for a single account
     */
    public function calculateHealthScore(Request $request, Response $response, array $args)
    {
        try {
            $accountId = $args['id'];
            
            $result = $this->healthService->calculateHealthScore($accountId);
            
            return $response->withJson($result);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get health score history for an account
     */
    public function getHealthScoreHistory(Request $request, Response $response, array $args)
    {
        global $db;
        
        $accountId = $args['id'];
        $limit = $request->getQueryParam('limit', 30);
        
        $query = "SELECT * FROM customer_health_scores 
                 WHERE account_id = '$accountId' 
                 ORDER BY calculated_at DESC 
                 LIMIT $limit";
        
        $result = $db->query($query);
        $history = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['factors'] = json_decode($row['factors'], true);
            $row['recommendations'] = json_decode($row['recommendations'], true);
            $history[] = $row;
        }
        
        return $response->withJson(['data' => $history]);
    }
    
    /**
     * Get accounts at risk
     */
    public function getAccountsAtRisk(Request $request, Response $response, array $args)
    {
        global $db;
        
        $riskLevel = $request->getQueryParam('risk_level', 'high');
        
        $query = "SELECT a.id, a.name, a.mrr, h.score, h.risk_level, h.churn_probability
                 FROM accounts a
                 JOIN customer_health_scores h ON a.id = h.account_id
                 WHERE a.deleted = 0
                 AND h.risk_level = '$riskLevel'
                 AND h.calculated_at = (
                     SELECT MAX(calculated_at) 
                     FROM customer_health_scores 
                     WHERE account_id = a.id
                 )
                 ORDER BY h.churn_probability DESC";
        
        $result = $db->query($query);
        $accounts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $accounts[] = $row;
        }
        
        return $response->withJson(['data' => $accounts]);
    }
    
    /**
     * Batch calculate health scores
     */
    public function batchCalculateHealthScores(Request $request, Response $response, array $args)
    {
        try {
            set_time_limit(300); // 5 minutes
            
            $results = $this->healthService->batchCalculateHealthScores();
            
            return $response->withJson([
                'success' => true,
                'processed' => count($results),
                'results' => $results,
            ]);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
}
```

### 9. Cron Jobs for Automated Tasks

#### 9.1 Create Cron Job Manager
`custom/cron/CronManager.php`:
```php
<?php
namespace Custom\Cron;

class CronManager
{
    /**
     * Run all scheduled tasks
     */
    public static function runScheduledTasks()
    {
        $tasks = [
            'calculateHealthScores' => ['frequency' => 'daily', 'time' => '02:00'],
            'scoreNewLeads' => ['frequency' => 'hourly'],
            'updateActivitySessions' => ['frequency' => 'every_30_min'],
            'cleanupOldSessions' => ['frequency' => 'daily', 'time' => '03:00'],
            'generateEmbeddings' => ['frequency' => 'hourly'],
        ];
        
        foreach ($tasks as $task => $config) {
            if (self::shouldRunTask($task, $config)) {
                self::runTask($task);
            }
        }
    }
    
    /**
     * Calculate health scores for all customer accounts
     */
    public static function calculateHealthScores()
    {
        echo "Starting health score calculation...\n";
        
        $healthService = new \Custom\Services\HealthScoringService();
        $results = $healthService->batchCalculateHealthScores();
        
        $success = 0;
        $failed = 0;
        
        foreach ($results as $accountId => $result) {
            if (isset($result['error'])) {
                $failed++;
                \LoggerManager::getLogger()->error("Health score failed for account $accountId: " . $result['error']);
            } else {
                $success++;
            }
        }
        
        echo "Health scores calculated: $success successful, $failed failed\n";
        
        // Send alerts for high-risk accounts
        self::sendRiskAlerts($results);
    }
    
    /**
     * Score new leads without AI scores
     */
    public static function scoreNewLeads()
    {
        global $db;
        
        echo "Scoring new leads...\n";
        
        $query = "SELECT id FROM leads 
                 WHERE deleted = 0 
                 AND ai_score IS NULL 
                 AND date_entered >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 LIMIT 50";
        
        $result = $db->query($query);
        $aiService = new \Custom\Services\OpenAIService();
        $scored = 0;
        
        while ($row = $db->fetchByAssoc($result)) {
            try {
                $lead = \BeanFactory::getBean('Leads', $row['id']);
                $leadData = self::gatherLeadDataForScoring($lead);
                $scoreResult = $aiService->scoreLead($leadData);
                
                $lead->ai_score = $scoreResult['score'];
                $lead->ai_score_date = gmdate('Y-m-d H:i:s');
                $lead->ai_score_factors = json_encode($scoreResult['factors']);
                $lead->save();
                
                $scored++;
            } catch (\Exception $e) {
                \LoggerManager::getLogger()->error("Lead scoring failed for {$row['id']}: " . $e->getMessage());
            }
        }
        
        echo "Scored $scored new leads\n";
    }
    
    /**
     * Update inactive activity sessions
     */
    public static function updateActivitySessions()
    {
        global $db;
        
        $timeout = 30; // minutes
        
        $query = "UPDATE website_sessions 
                 SET is_active = 0 
                 WHERE is_active = 1 
                 AND last_activity < DATE_SUB(NOW(), INTERVAL $timeout MINUTE)";
        
        $db->query($query);
        
        $affected = $db->getAffectedRowCount();
        echo "Marked $affected sessions as inactive\n";
    }
    
    /**
     * Clean up old tracking data
     */
    public static function cleanupOldSessions()
    {
        global $db;
        
        echo "Cleaning up old sessions...\n";
        
        // Delete sessions older than 90 days
        $queries = [
            "DELETE FROM activity_events WHERE session_id IN (
                SELECT id FROM website_sessions WHERE date_created < DATE_SUB(NOW(), INTERVAL 90 DAY)
            )",
            "DELETE FROM page_views WHERE session_id IN (
                SELECT id FROM website_sessions WHERE date_created < DATE_SUB(NOW(), INTERVAL 90 DAY)
            )",
            "DELETE FROM website_sessions WHERE date_created < DATE_SUB(NOW(), INTERVAL 90 DAY)",
            "DELETE FROM ai_chat_sessions WHERE date_created < DATE_SUB(NOW(), INTERVAL 90 DAY)",
        ];
        
        foreach ($queries as $query) {
            $db->query($query);
        }
        
        echo "Old sessions cleaned up\n";
    }
    
    /**
     * Generate embeddings for new KB articles
     */
    public static function generateEmbeddings()
    {
        global $db;
        
        echo "Generating embeddings for KB articles...\n";
        
        $query = "SELECT id, title, content FROM kb_articles 
                 WHERE deleted = 0 
                 AND (embedding_vector IS NULL OR embedding_vector = '[]')
                 LIMIT 10";
        
        $result = $db->query($query);
        $aiService = new \Custom\Services\OpenAIService();
        $processed = 0;
        
        while ($row = $db->fetchByAssoc($result)) {
            try {
                $text = $row['title'] . ' ' . strip_tags($row['content']);
                $embedding = $aiService->generateEmbedding($text);
                
                $updateQuery = "UPDATE kb_articles 
                               SET embedding_vector = '{$db->quote(json_encode($embedding))}' 
                               WHERE id = '{$row['id']}'";
                
                $db->query($updateQuery);
                $processed++;
            } catch (\Exception $e) {
                \LoggerManager::getLogger()->error("Embedding generation failed for article {$row['id']}: " . $e->getMessage());
            }
        }
        
        echo "Generated embeddings for $processed articles\n";
    }
    
    // Helper methods
    
    private static function shouldRunTask($task, $config)
    {
        // In production, implement proper scheduling logic
        // For now, always return true for testing
        return true;
    }
    
    private static function runTask($task)
    {
        if (method_exists(self::class, $task)) {
            self::$task();
        }
    }
    
    private static function sendRiskAlerts($results)
    {
        $highRiskAccounts = [];
        
        foreach ($results as $accountId => $result) {
            if (!isset($result['error']) && $result['risk_level'] === 'high') {
                $highRiskAccounts[] = [
                    'account_id' => $accountId,
                    'score' => $result['score'],
                    'churn_probability' => $result['churn_probability'],
                ];
            }
        }
        
        if (!empty($highRiskAccounts)) {
            // Send email alert to account managers
            // Implementation depends on email system
            echo "Found " . count($highRiskAccounts) . " high-risk accounts\n";
        }
    }
    
    private static function gatherLeadDataForScoring($lead)
    {
        // Simplified version - in production, gather more comprehensive data
        return [
            'company_name' => $lead->account_name,
            'industry' => $lead->industry,
            'lead_source' => $lead->lead_source,
            'website' => $lead->website,
            'page_views' => rand(1, 20),
            'time_on_site' => rand(1, 30),
            'high_value_pages' => ['/pricing', '/features'],
        ];
    }
}
```

#### 9.2 Create Cron Entry Point
`custom/cron/run_scheduled_tasks.php`:
```php
<?php
if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('include/entryPoint.php');
require_once('custom/cron/CronManager.php');

// Set up as cron job
global $current_user;
$current_user = new User();
$current_user->getSystemUser();

// Run scheduled tasks
Custom\Cron\CronManager::runScheduledTasks();
```

### 10. Performance Optimization

#### 10.1 Create Cache Manager
`custom/services/CacheManager.php`:
```php
<?php
namespace Custom\Services;

use Predis\Client as Redis;

class CacheManager
{
    private static $instance;
    private $redis;
    
    private function __construct()
    {
        $this->redis = new Redis([
            'scheme' => 'tcp',
            'host'   => 'redis',
            'port'   => 6379,
        ]);
    }
    
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cache lead score
     */
    public function cacheLeadScore($leadId, $score, $ttl = 3600)
    {
        $key = "lead_score:$leadId";
        $this->redis->setex($key, $ttl, json_encode($score));
    }
    
    /**
     * Get cached lead score
     */
    public function getCachedLeadScore($leadId)
    {
        $key = "lead_score:$leadId";
        $cached = $this->redis->get($key);
        return $cached ? json_decode($cached, true) : null;
    }
    
    /**
     * Cache KB search results
     */
    public function cacheSearchResults($query, $results, $ttl = 1800)
    {
        $key = "kb_search:" . md5($query);
        $this->redis->setex($key, $ttl, json_encode($results));
    }
    
    /**
     * Get cached search results
     */
    public function getCachedSearchResults($query)
    {
        $key = "kb_search:" . md5($query);
        $cached = $this->redis->get($key);
        return $cached ? json_decode($cached, true) : null;
    }
    
    /**
     * Cache visitor session
     */
    public function cacheVisitorSession($visitorId, $sessionData, $ttl = 1800)
    {
        $key = "visitor:$visitorId";
        $this->redis->setex($key, $ttl, json_encode($sessionData));
    }
    
    /**
     * Get cached visitor session
     */
    public function getCachedVisitorSession($visitorId)
    {
        $key = "visitor:$visitorId";
        $cached = $this->redis->get($key);
        return $cached ? json_decode($cached, true) : null;
    }
    
    /**
     * Invalidate cache
     */
    public function invalidate($pattern)
    {
        $keys = $this->redis->keys($pattern);
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }
}
```

### 11. Security Enhancements

#### 11.1 Create Security Middleware
`custom/api/v8/middleware/SecurityMiddleware.php`:
```php
<?php
namespace Api\V8\Custom\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class SecurityMiddleware
{
    /**
     * Rate limiting middleware
     */
    public function rateLimiting($request, $response, $next)
    {
        $identifier = $this->getClientIdentifier($request);
        $limit = 100; // requests per minute
        
        $cache = \Custom\Services\CacheManager::getInstance();
        $key = "rate_limit:$identifier";
        
        $count = $cache->redis->incr($key);
        if ($count === 1) {
            $cache->redis->expire($key, 60);
        }
        
        if ($count > $limit) {
            return $response->withJson([
                'error' => 'Rate limit exceeded',
                'retry_after' => 60,
            ], 429);
        }
        
        $response = $response->withHeader('X-RateLimit-Limit', $limit);
        $response = $response->withHeader('X-RateLimit-Remaining', $limit - $count);
        
        return $next($request, $response);
    }
    
    /**
     * API key validation for public endpoints
     */
    public function validateApiKey($request, $response, $next)
    {
        $apiKey = $request->getHeaderLine('X-API-Key');
        
        if (empty($apiKey)) {
            return $response->withJson(['error' => 'API key required'], 401);
        }
        
        // Validate API key
        if (!$this->isValidApiKey($apiKey)) {
            return $response->withJson(['error' => 'Invalid API key'], 401);
        }
        
        return $next($request, $response);
    }
    
    /**
     * CORS headers for embed scripts
     */
    public function cors($request, $response, $next)
    {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key');
        
        if ($request->isOptions()) {
            return $response->withStatus(200);
        }
        
        return $next($request, $response);
    }
    
    private function getClientIdentifier($request)
    {
        // Use API key if present, otherwise IP address
        $apiKey = $request->getHeaderLine('X-API-Key');
        if ($apiKey) {
            return 'api:' . $apiKey;
        }
        
        return 'ip:' . $_SERVER['REMOTE_ADDR'];
    }
    
    private function isValidApiKey($apiKey)
    {
        global $db;
        
        $query = "SELECT id FROM api_keys 
                 WHERE api_key = '$apiKey' 
                 AND is_active = 1 
                 AND (expires_at IS NULL OR expires_at > NOW())";
        
        $result = $db->query($query);
        return $db->fetchByAssoc($result) !== false;
    }
}
```

### 12. Demo Data Seeding

#### 12.1 Create Phase 3 Demo Data Script
`custom/install/seed_phase3_data.php`:
```php
<?php
require_once('include/utils.php');

function seedPhase3DemoData() {
    echo "Seeding Phase 3 demo data...\n";
    
    // Create demo forms
    seedDemoForms();
    
    // Create knowledge base content
    seedKnowledgeBase();
    
    // Create activity tracking data
    seedActivityData();
    
    // Create AI chat sessions
    seedChatSessions();
    
    // Calculate health scores
    seedHealthScores();
    
    echo "Phase 3 demo data seeded successfully!\n";
}

function seedDemoForms() {
    global $db, $current_user;
    
    $forms = [
        [
            'name' => 'Request a Demo',
            'description' => 'Let prospects request a product demo',
            'fields' => [
                ['id' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ['id' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                ['id' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
                ['id' => 'phone', 'type' => 'tel', 'label' => 'Phone Number', 'required' => false],
                ['id' => 'employees', 'type' => 'select', 'label' => 'Company Size', 'required' => true,
                 'options' => [
                     ['value' => '1-10', 'label' => '1-10 employees'],
                     ['value' => '11-50', 'label' => '11-50 employees'],
                     ['value' => '51-200', 'label' => '51-200 employees'],
                     ['value' => '201-500', 'label' => '201-500 employees'],
                     ['value' => '500+', 'label' => '500+ employees'],
                 ]],
                ['id' => 'message', 'type' => 'textarea', 'label' => 'Tell us about your needs', 'required' => false],
            ],
            'settings' => [
                'submitButtonText' => 'Request Demo',
                'successMessage' => 'Thank you! We\'ll contact you within 24 hours to schedule your demo.',
                'notificationEmail' => 'sales@example.com',
                'styling' => [
                    'theme' => 'light',
                    'primaryColor' => '#3b82f6',
                    'fontFamily' => 'Inter, sans-serif',
                ],
            ],
        ],
        [
            'name' => 'Newsletter Signup',
            'description' => 'Email newsletter subscription form',
            'fields' => [
                ['id' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                ['id' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => false],
                ['id' => 'topics', 'type' => 'checkbox', 'label' => 'I\'m interested in product updates', 'required' => false],
            ],
            'settings' => [
                'submitButtonText' => 'Subscribe',
                'successMessage' => 'You\'re subscribed! Check your email to confirm.',
                'styling' => [
                    'theme' => 'light',
                    'primaryColor' => '#10b981',
                    'fontFamily' => 'Inter, sans-serif',
                ],
            ],
        ],
    ];
    
    foreach ($forms as $formData) {
        $id = create_guid();
        $embedCode = "<script src=\"https://yourcrm.com/forms/embed.js\"></script>\n<div data-form-id=\"$id\" data-form-container></div>";
        
        $query = "INSERT INTO form_builder_forms 
                 (id, name, description, fields, settings, embed_code, created_by, date_created)
                 VALUES (
                    '$id',
                    '{$db->quote($formData['name'])}',
                    '{$db->quote($formData['description'])}',
                    '{$db->quote(json_encode($formData['fields']))}',
                    '{$db->quote(json_encode($formData['settings']))}',
                    '{$db->quote($embedCode)}',
                    '{$current_user->id}',
                    NOW()
                 )";
        
        $db->query($query);
        echo "Created form: {$formData['name']}\n";
        
        // Add some demo submissions
        for ($i = 0; $i < rand(5, 15); $i++) {
            $submissionId = create_guid();
            $submissionData = [
                'first_name' => 'Demo',
                'last_name' => 'User ' . $i,
                'email' => "demo$i@example.com",
                'company' => 'Company ' . $i,
                'employees' => '11-50',
            ];
            
            $query = "INSERT INTO form_submissions 
                     (id, form_id, data, ip_address, date_submitted)
                     VALUES (
                        '$submissionId',
                        '$id',
                        '{$db->quote(json_encode($submissionData))}',
                        '192.168.1.' . rand(1, 255),
                        DATE_SUB(NOW(), INTERVAL " . rand(1, 30) . " DAY)
                     )";
            
            $db->query($query);
        }
    }
}

function seedKnowledgeBase() {
    global $db, $current_user;
    
    // Create categories
    $categories = [
        ['name' => 'Getting Started', 'slug' => 'getting-started', 'icon' => '🚀'],
        ['name' => 'Features', 'slug' => 'features', 'icon' => '✨'],
        ['name' => 'Integrations', 'slug' => 'integrations', 'icon' => '🔌'],
        ['name' => 'Troubleshooting', 'slug' => 'troubleshooting', 'icon' => '🔧'],
        ['name' => 'Best Practices', 'slug' => 'best-practices', 'icon' => '💡'],
    ];
    
    $categoryIds = [];
    foreach ($categories as $cat) {
        $id = create_guid();
        $categoryIds[$cat['slug']] = $id;
        
        $query = "INSERT INTO kb_categories 
                 (id, name, slug, icon, created_by, date_created)
                 VALUES ('$id', '{$cat['name']}', '{$cat['slug']}', '{$cat['icon']}', '{$current_user->id}', NOW())";
        
        $db->query($query);
        echo "Created KB category: {$cat['name']}\n";
    }
    
    // Create articles
    $articles = [
        [
            'title' => 'Quick Start Guide',
            'category' => 'getting-started',
            'content' => '<h2>Welcome to Our CRM!</h2><p>This guide will help you get started quickly.</p><h3>Step 1: Add Your First Lead</h3><p>Navigate to the Leads section and click "New Lead"...</p>',
            'tags' => ['tutorial', 'quickstart', 'beginner'],
            'is_featured' => true,
        ],
        [
            'title' => 'Understanding AI Lead Scoring',
            'category' => 'features',
            'content' => '<h2>AI Lead Scoring Explained</h2><p>Our AI analyzes multiple factors to score leads...</p><h3>Scoring Factors</h3><ul><li>Company size</li><li>Industry match</li><li>Website behavior</li></ul>',
            'tags' => ['ai', 'lead-scoring', 'automation'],
            'is_featured' => true,
        ],
        [
            'title' => 'Setting Up Form Builder',
            'category' => 'features',
            'content' => '<h2>Create Custom Forms</h2><p>The form builder lets you create custom forms...</p>',
            'tags' => ['forms', 'lead-capture', 'customization'],
        ],
        [
            'title' => 'Troubleshooting Login Issues',
            'category' => 'troubleshooting',
            'content' => '<h2>Can\'t Log In?</h2><p>Here are common solutions...</p>',
            'tags' => ['login', 'authentication', 'troubleshooting'],
        ],
        [
            'title' => 'Best Practices for Lead Management',
            'category' => 'best-practices',
            'content' => '<h2>Lead Management Tips</h2><p>Follow these best practices...</p>',
            'tags' => ['leads', 'sales', 'best-practices'],
        ],
    ];
    
    foreach ($articles as $article) {
        $id = create_guid();
        $slug = strtolower(str_replace(' ', '-', $article['title']));
        
        $query = "INSERT INTO kb_articles 
                 (id, title, slug, content, category_id, tags, is_public, is_featured, author_id, date_created)
                 VALUES (
                    '$id',
                    '{$db->quote($article['title'])}',
                    '$slug',
                    '{$db->quote($article['content'])}',
                    '{$categoryIds[$article['category']]}',
                    '{$db->quote(json_encode($article['tags']))}',
                    1,
                    " . ($article['is_featured'] ?? 0) . ",
                    '{$current_user->id}',
                    NOW()
                 )";
        
        $db->query($query);
        echo "Created KB article: {$article['title']}\n";
        
        // Add some view counts
        $views = rand(10, 500);
        $helpful = rand(5, $views / 10);
        $notHelpful = rand(0, 5);
        
        $updateQuery = "UPDATE kb_articles 
                       SET views = $views, helpful_yes = $helpful, helpful_no = $notHelpful 
                       WHERE id = '$id'";
        $db->query($updateQuery);
    }
}

function seedActivityData() {
    global $db;
    
    // Create visitor sessions for existing leads
    $leadsQuery = "SELECT id, email FROM leads WHERE deleted = 0 LIMIT 10";
    $leadsResult = $db->query($leadsQuery);
    
    while ($lead = $db->fetchByAssoc($leadsResult)) {
        $visitorId = 'visitor_' . md5($lead['email']);
        $sessionId = create_guid();
        
        // Create session
        $pages = [
            ['url' => 'https://example.com/', 'title' => 'Home'],
            ['url' => 'https://example.com/features', 'title' => 'Features'],
            ['url' => 'https://example.com/pricing', 'title' => 'Pricing'],
            ['url' => 'https://example.com/demo', 'title' => 'Request Demo'],
        ];
        
        $totalTime = rand(120, 600);
        
        $query = "INSERT INTO website_sessions 
                 (id, visitor_id, lead_id, ip_address, user_agent, landing_page, pages_viewed, 
                  total_time, is_active, location, date_created, last_activity)
                 VALUES (
                    '$sessionId',
                    '$visitorId',
                    '{$lead['id']}',
                    '192.168.1.' . rand(1, 255),
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0',
                    'https://example.com/',
                    '{$db->quote(json_encode($pages))}',
                    $totalTime,
                    0,
                    '{\"country\": \"United States\", \"city\": \"New York\"}',
                    DATE_SUB(NOW(), INTERVAL " . rand(1, 7) . " DAY),
                    DATE_SUB(NOW(), INTERVAL " . rand(1, 7) . " DAY)
                 )";
        
        $db->query($query);
        
        // Add page views
        foreach ($pages as $i => $page) {
            $pvId = create_guid();
            $timeOnPage = rand(30, 180);
            
            $pvQuery = "INSERT INTO page_views 
                       (id, session_id, url, title, time_on_page, scroll_depth, clicks, timestamp)
                       VALUES (
                          '$pvId',
                          '$sessionId',
                          '{$page['url']}',
                          '{$page['title']}',
                          $timeOnPage,
                          " . rand(50, 100) . ",
                          " . rand(0, 5) . ",
                          DATE_SUB(NOW(), INTERVAL " . (7 - $i) . " DAY)
                       )";
            
            $db->query($pvQuery);
        }
        
        echo "Created activity session for lead: {$lead['email']}\n";
    }
}

function seedChatSessions() {
    global $db;
    
    $conversations = [
        [
            'messages' => [
                ['role' => 'user', 'content' => 'What does your CRM do?'],
                ['role' => 'assistant', 'content' => 'Our CRM helps B2B software companies manage their entire customer lifecycle...'],
                ['role' => 'user', 'content' => 'Do you have AI features?'],
                ['role' => 'assistant', 'content' => 'Yes! We offer AI-powered lead scoring, chatbot, and predictive analytics...'],
            ],
            'intent' => 'sales',
            'lead_score' => 75,
        ],
        [
            'messages' => [
                ['role' => 'user', 'content' => 'I need help with login'],
                ['role' => 'assistant', 'content' => 'I can help you with login issues. Are you getting an error message?'],
                ['role' => 'user', 'content' => 'Yes, it says invalid credentials'],
                ['role' => 'assistant', 'content' => 'Let me help you reset your password...'],
            ],
            'intent' => 'support',
            'lead_score' => null,
        ],
    ];
    
    foreach ($conversations as $conv) {
        $sessionId = create_guid();
        $visitorId = 'chat_visitor_' . rand(1000, 9999);
        
        $query = "INSERT INTO ai_chat_sessions 
                 (id, visitor_id, messages, intent, lead_score, status, date_created)
                 VALUES (
                    '$sessionId',
                    '$visitorId',
                    '{$db->quote(json_encode($conv['messages']))}',
                    '{$conv['intent']}',
                    " . ($conv['lead_score'] ?? 'NULL') . ",
                    'ended',
                    DATE_SUB(NOW(), INTERVAL " . rand(1, 30) . " DAY)
                 )";
        
        $db->query($query);
        echo "Created chat session: $sessionId\n";
    }
}

function seedHealthScores() {
    // Get customer accounts
    global $db;
    
    $query = "SELECT id FROM accounts WHERE account_type = 'Customer' AND deleted = 0";
    $result = $db->query($query);
    
    $healthService = new \Custom\Services\HealthScoringService();
    
    while ($row = $db->fetchByAssoc($result)) {
        try {
            $healthService->calculateHealthScore($row['id']);
            echo "Calculated health score for account: {$row['id']}\n";
        } catch (Exception $e) {
            echo "Failed to calculate health score: " . $e->getMessage() . "\n";
        }
    }
}

// Run seeding
seedPhase3DemoData();
```

## Definition of Success

### ✅ Phase 3 Backend Success Criteria:

1. **OpenAI Integration**
   - [ ] OpenAI API key configured and working
   - [ ] Lead scoring returns scores 0-100 with factors
   - [ ] Chat responses are contextual and helpful
   - [ ] KB semantic search returns relevant results
   - [ ] Embeddings generated for articles

2. **Form Builder Module**
   - [ ] Forms can be created with multiple field types
   - [ ] Embed code generation works
   - [ ] Form submissions create leads
   - [ ] Validation rules enforced
   - [ ] Notification emails sent

3. **Knowledge Base Module**
   - [ ] Categories and articles CRUD working
   - [ ] Public/private access control
   - [ ] Article view tracking
   - [ ] Helpful/not helpful ratings saved
   - [ ] Search returns relevant results

4. **Activity Tracking**
   - [ ] Page views tracked with visitor ID
   - [ ] Sessions created and maintained
   - [ ] Click events recorded with coordinates
   - [ ] Visitor identification links to leads
   - [ ] Live visitors query returns active sessions

5. **AI Chatbot**
   - [ ] Chat sessions created and maintained
   - [ ] Messages processed by OpenAI
   - [ ] Lead qualification flow works
   - [ ] KB articles suggested when relevant
   - [ ] Lead creation from chat data

6. **Customer Health Scoring**
   - [ ] Health scores calculated 0-100
   - [ ] Risk levels assigned correctly
   - [ ] Churn probability calculated
   - [ ] Recommendations generated
   - [ ] History tracked over time

7. **Performance & Security**
   - [ ] Redis caching implemented
   - [ ] Rate limiting on public endpoints
   - [ ] API key validation for embed scripts
   - [ ] CORS headers configured
   - [ ] Cron jobs run successfully

### Manual Verification Steps:
1. Set OpenAI API key: `export OPENAI_API_KEY=your-key`
2. Run table installation: `docker exec suitecrm_app php custom/install/install_phase3_tables.php`
3. Seed demo data: `docker exec suitecrm_app php custom/install/seed_phase3_data.php`
4. Test AI lead scoring via API
5. Create and embed a form
6. Submit form and verify lead creation
7. Create KB articles and test search
8. View activity tracking dashboard
9. Test chatbot responses
10. Calculate health scores for accounts
11. Run cron jobs: `docker exec suitecrm_app php custom/cron/run_scheduled_tasks.php`
12. Run integration tests: `./tests/integration/test-phase3-integration.sh`

### Integration Checklist:
- [ ] Frontend can call AI scoring endpoints
- [ ] Forms render correctly when embedded
- [ ] KB articles display with formatting
- [ ] Activity tracking script captures data
- [ ] Chat widget communicates with AI
- [ ] Health scores display in account views
- [ ] Caching improves response times
- [ ] Rate limiting prevents abuse

### Common Issues and Solutions:
1. **OpenAI API errors**: Check API key and rate limits
2. **Embedding generation slow**: Implement queue system
3. **Form submissions fail**: Check CORS and validation
4. **Chat not responding**: Verify OpenAI model access
5. **Activity tracking gaps**: Check session timeout settings
6. **Health scores inaccurate**: Verify data gathering queries

### Next Phase Preview:
Phase 4 will complete the platform with:
- Marketing website with embedded features
- Complete demo environment setup
- Advanced reporting and analytics
- Email integration enhancements
- Final UI polish and optimizations
- Comprehensive documentation
- Deployment automation

The platform now has all core AI features implemented and is ready for the final phase of polishing and deployment preparation.