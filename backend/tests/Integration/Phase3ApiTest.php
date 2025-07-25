<?php
/**
 * Phase 3 API Integration Tests
 * Tests all Phase 3 features including AI, forms, KB, tracking, and health scoring
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Phase3ApiTest extends TestCase
{
    protected static $client;
    protected static $token;
    protected static $baseUrl = 'http://localhost:8080/api/v8/';
    
    public static function setUpBeforeClass(): void
    {
        self::$client = new Client([
            'base_uri' => self::$baseUrl,
            'timeout' => 30.0,
            'http_errors' => false,
        ]);
        
        // Authenticate once for all tests
        self::authenticate();
    }
    
    protected static function authenticate()
    {
        try {
            $response = self::$client->post('login', [
                'json' => [
                    'grant_type' => 'password',
                    'client_id' => 'sugar',
                    'username' => 'admin',
                    'password' => 'admin123',
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            self::$token = $data['access_token'] ?? null;
            
            if (!self::$token) {
                throw new \Exception('Authentication failed');
            }
        } catch (\Exception $e) {
            self::fail('Authentication failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test AI Lead Scoring
     */
    public function testAILeadScoring()
    {
        // Create a test lead
        $leadData = [
            'data' => [
                'type' => 'Leads',
                'attributes' => [
                    'first_name' => 'AI Test',
                    'last_name' => 'Lead ' . time(),
                    'email' => 'aitest' . time() . '@techcorp.com',
                    'account_name' => 'Tech Corp Enterprise',
                    'title' => 'CTO',
                    'phone_work' => '+1-555-0123',
                    'website' => 'https://techcorp.com',
                    'lead_source' => 'Website',
                    'industry' => 'Technology',
                ]
            ]
        ];
        
        $response = self::$client->post('module/Leads', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'json' => $leadData
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $lead = json_decode($response->getBody(), true);
        $leadId = $lead['data']['id'];
        
        // Score the lead
        $response = self::$client->post('leads/' . $leadId . '/ai-score', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $scoreData = json_decode($response->getBody(), true);
        
        // Verify score structure
        $this->assertArrayHasKey('score', $scoreData);
        $this->assertArrayHasKey('factors', $scoreData);
        $this->assertArrayHasKey('insights', $scoreData);
        $this->assertArrayHasKey('recommended_actions', $scoreData);
        $this->assertArrayHasKey('confidence', $scoreData);
        
        // Verify score is valid
        $this->assertGreaterThanOrEqual(0, $scoreData['score']);
        $this->assertLessThanOrEqual(100, $scoreData['score']);
        
        // Verify factors exist
        $expectedFactors = ['company_size', 'industry_match', 'behavior_score', 'engagement', 'budget_signals'];
        foreach ($expectedFactors as $factor) {
            $this->assertArrayHasKey($factor, $scoreData['factors']);
        }
        
        // Test score history endpoint
        $response = self::$client->get('leads/' . $leadId . '/score-history', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $history = json_decode($response->getBody(), true);
        $this->assertIsArray($history);
    }
    
    /**
     * Test Batch Lead Scoring
     */
    public function testBatchLeadScoring()
    {
        $leadIds = [];
        
        // Create multiple test leads
        for ($i = 0; $i < 3; $i++) {
            $leadData = [
                'data' => [
                    'type' => 'Leads',
                    'attributes' => [
                        'first_name' => 'Batch',
                        'last_name' => 'Test ' . $i,
                        'email' => 'batch' . $i . time() . '@example.com',
                        'account_name' => 'Company ' . $i,
                    ]
                ]
            ];
            
            $response = self::$client->post('module/Leads', [
                'headers' => ['Authorization' => 'Bearer ' . self::$token],
                'json' => $leadData
            ]);
            
            $lead = json_decode($response->getBody(), true);
            $leadIds[] = $lead['data']['id'];
        }
        
        // Batch score leads
        $response = self::$client->post('leads/ai-score-batch', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'json' => ['lead_ids' => $leadIds]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $results = json_decode($response->getBody(), true);
        
        // Verify all leads were scored
        foreach ($leadIds as $leadId) {
            $this->assertArrayHasKey($leadId, $results);
            $this->assertArrayHasKey('score', $results[$leadId]);
        }
    }
    
    /**
     * Test Form Builder
     */
    public function testFormBuilder()
    {
        // Create a form
        $formData = [
            'name' => 'Test Contact Form ' . time(),
            'description' => 'Test form for API testing',
            'fields' => [
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email Address',
                    'required' => true,
                    'placeholder' => 'your@email.com',
                ],
                [
                    'name' => 'first_name',
                    'type' => 'text',
                    'label' => 'First Name',
                    'required' => true,
                ],
                [
                    'name' => 'company',
                    'type' => 'text',
                    'label' => 'Company',
                    'required' => false,
                ],
                [
                    'name' => 'message',
                    'type' => 'textarea',
                    'label' => 'Message',
                    'required' => false,
                    'rows' => 4,
                ],
            ],
            'settings' => [
                'submit_button_text' => 'Submit',
                'success_message' => 'Thank you for your submission!',
                'redirect_url' => '',
                'notification_email' => 'admin@example.com',
            ],
        ];
        
        $response = self::$client->post('forms', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'json' => $formData,
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $form = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('id', $form);
        $this->assertArrayHasKey('embed_code', $form);
        $this->assertEquals($formData['name'], $form['name']);
        
        $formId = $form['id'];
        
        // Test form retrieval
        $response = self::$client->get('forms/' . $formId, [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Test form submission (public endpoint)
        $submissionData = [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'company' => 'Test Corp',
            'message' => 'This is a test submission',
            '_metadata' => [
                'page_url' => 'https://example.com/contact',
                'referrer' => 'https://google.com',
            ]
        ];
        
        $response = self::$client->post('forms/' . $formId . '/submit', [
            'json' => $submissionData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $submission = json_decode($response->getBody(), true);
        $this->assertTrue($submission['success']);
        $this->assertArrayHasKey('submission_id', $submission);
        
        // Verify lead was created
        if (isset($submission['lead_id'])) {
            $response = self::$client->get('module/Leads/' . $submission['lead_id'], [
                'headers' => ['Authorization' => 'Bearer ' . self::$token],
            ]);
            
            $this->assertEquals(200, $response->getStatusCode());
        }
        
        // Test form submissions retrieval
        $response = self::$client->get('forms/' . $formId . '/submissions', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $submissions = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $submissions);
        $this->assertGreaterThan(0, count($submissions['data']));
    }
    
    /**
     * Test Knowledge Base
     */
    public function testKnowledgeBase()
    {
        // Create a category
        $categoryData = [
            'name' => 'Test Category ' . time(),
            'description' => 'Test category for API testing',
            'icon' => '📚',
        ];
        
        $response = self::$client->post('knowledge-base/categories', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'json' => $categoryData,
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $category = json_decode($response->getBody(), true);
        $categoryId = $category['id'];
        
        // Create an article
        $articleData = [
            'title' => 'Test Article: Getting Started with AI',
            'content' => '<h2>Introduction</h2><p>This is a comprehensive guide about AI features in our CRM.</p><h3>Lead Scoring</h3><p>Our AI analyzes multiple factors to score leads automatically.</p>',
            'excerpt' => 'Learn how to use AI features in our CRM',
            'category_id' => $categoryId,
            'tags' => ['ai', 'tutorial', 'getting-started'],
            'is_public' => true,
            'is_featured' => true,
        ];
        
        $response = self::$client->post('knowledge-base/articles', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'json' => $articleData,
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $article = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('id', $article);
        $this->assertArrayHasKey('slug', $article);
        $articleId = $article['id'];
        
        // Test article retrieval
        $response = self::$client->get('knowledge-base/articles/' . $articleId, [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Test semantic search
        $response = self::$client->get('knowledge-base/search', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'query' => [
                'q' => 'AI lead scoring tutorial',
                'limit' => 5,
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $results = json_decode($response->getBody(), true);
        $this->assertIsArray($results);
        
        // Test feedback submission (public endpoint)
        $response = self::$client->post('knowledge-base/articles/' . $articleId . '/feedback', [
            'json' => [
                'helpful' => true,
                'comment' => 'Very helpful article!',
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * Test Activity Tracking
     */
    public function testActivityTracking()
    {
        $visitorId = 'test_visitor_' . time();
        $sessionId = null;
        
        // Track page view (public endpoint)
        $trackingData = [
            'visitor_id' => $visitorId,
            'url' => 'https://example.com/pricing',
            'title' => 'Pricing - Example CRM',
            'referrer' => 'https://google.com',
            'timestamp' => date('c'),
        ];
        
        $response = self::$client->post('track/pageview', [
            'json' => $trackingData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $result = json_decode($response->getBody(), true);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('session_id', $result);
        $sessionId = $result['session_id'];
        
        // Track engagement event
        $engagementData = [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'type' => 'click',
            'data' => [
                'element' => 'button',
                'text' => 'Request Demo',
                'x' => 450,
                'y' => 320,
            ],
            'timestamp' => date('c'),
        ];
        
        $response = self::$client->post('track/engagement', [
            'json' => $engagementData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Track conversion
        $conversionData = [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'type' => 'form_submission',
            'value' => 'demo_request',
            'timestamp' => date('c'),
        ];
        
        $response = self::$client->post('track/conversion', [
            'json' => $conversionData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // End session
        $response = self::$client->post('track/session-end', [
            'json' => [
                'session_id' => $sessionId,
                'visitor_id' => $visitorId,
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Get visitor analytics (requires auth)
        $response = self::$client->get('analytics/visitors', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'query' => [
                'limit' => 10,
                'active_only' => false,
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $visitors = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $visitors);
    }
    
    /**
     * Test AI Chatbot
     */
    public function testAIChatbot()
    {
        // Start chat session
        $chatData = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, I need information about your pricing plans'],
            ],
            'context' => [
                'visitor_id' => 'test_chat_visitor_' . time(),
                'page_url' => 'https://example.com/pricing',
            ],
        ];
        
        $response = self::$client->post('ai/chat', [
            'json' => $chatData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $result = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('conversation_id', $result);
        $this->assertArrayHasKey('handoff_required', $result);
        $this->assertArrayHasKey('confidence', $result);
        
        $conversationId = $result['conversation_id'];
        
        // Continue conversation
        $chatData['messages'][] = ['role' => 'assistant', 'content' => $result['response']];
        $chatData['messages'][] = ['role' => 'user', 'content' => 'What is included in the Enterprise plan?'];
        $chatData['conversation_id'] = $conversationId;
        
        $response = self::$client->post('ai/chat', [
            'json' => $chatData,
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Get conversation history
        $response = self::$client->get('ai/chat/' . $conversationId, [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $conversation = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('messages', $conversation);
        $this->assertGreaterThan(2, count($conversation['messages']));
    }
    
    /**
     * Test Customer Health Scoring
     */
    public function testCustomerHealthScoring()
    {
        // First, get a customer account or create one
        $accountData = [
            'data' => [
                'type' => 'Accounts',
                'attributes' => [
                    'name' => 'Test Customer ' . time(),
                    'account_type' => 'Customer',
                    'industry' => 'Technology',
                    'annual_revenue' => '5000000',
                    'employees' => '51-200',
                    'website' => 'https://testcustomer.com',
                ]
            ]
        ];
        
        $response = self::$client->post('module/Accounts', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'json' => $accountData
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $account = json_decode($response->getBody(), true);
        $accountId = $account['data']['id'];
        
        // Calculate health score
        $response = self::$client->post('accounts/' . $accountId . '/health-score', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $healthData = json_decode($response->getBody(), true);
        
        // Verify health score structure
        $this->assertArrayHasKey('score', $healthData);
        $this->assertArrayHasKey('factors', $healthData);
        $this->assertArrayHasKey('risk_level', $healthData);
        $this->assertArrayHasKey('churn_probability', $healthData);
        $this->assertArrayHasKey('recommendations', $healthData);
        
        // Verify score is valid
        $this->assertGreaterThanOrEqual(0, $healthData['score']);
        $this->assertLessThanOrEqual(100, $healthData['score']);
        
        // Verify risk level
        $this->assertContains($healthData['risk_level'], ['healthy', 'at_risk', 'critical']);
        
        // Get health history
        $response = self::$client->get('accounts/' . $accountId . '/health-history', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $history = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('history', $history);
        $this->assertGreaterThan(0, count($history['history']));
        
        // Get at-risk accounts
        $response = self::$client->get('accounts/at-risk', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'query' => [
                'risk_level' => 'at_risk',
                'limit' => 10,
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $atRiskAccounts = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('accounts', $atRiskAccounts);
    }
    
    /**
     * Test Health Dashboard
     */
    public function testHealthDashboard()
    {
        $response = self::$client->get('analytics/health-dashboard', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $dashboard = json_decode($response->getBody(), true);
        
        // Verify dashboard structure
        $this->assertArrayHasKey('summary', $dashboard);
        $this->assertArrayHasKey('average_scores', $dashboard);
        $this->assertArrayHasKey('recent_alerts', $dashboard);
        $this->assertArrayHasKey('top_at_risk', $dashboard);
        $this->assertArrayHasKey('critical_accounts', $dashboard);
        
        // Verify summary data
        $summary = $dashboard['summary'];
        $this->assertArrayHasKey('total_accounts', $summary);
        $this->assertArrayHasKey('healthy', $summary);
        $this->assertArrayHasKey('at_risk', $summary);
        $this->assertArrayHasKey('critical', $summary);
        $this->assertArrayHasKey('healthy_percentage', $summary);
    }
    
    /**
     * Test Webhook Health Check
     */
    public function testWebhookHealthCheck()
    {
        $response = self::$client->post('webhooks/health-check', [
            'headers' => [
                'X-Webhook-Token' => 'your-secret-token-here',
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $result = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('inactive_accounts_checked', $result);
        $this->assertArrayHasKey('at_risk_accounts_processed', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }
    
    /**
     * Test Tracking Pixel
     */
    public function testTrackingPixel()
    {
        $trackingId = 'test_pixel_' . time();
        
        $response = self::$client->get('track/pixel/' . $trackingId . '.gif');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/gif', $response->getHeaderLine('Content-Type'));
    }
    
    /**
     * Test Error Handling
     */
    public function testErrorHandling()
    {
        // Test invalid lead ID for scoring
        $response = self::$client->post('leads/invalid-id/ai-score', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
        ]);
        
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        
        // Test invalid form submission
        $response = self::$client->post('forms/invalid-id/submit', [
            'json' => ['test' => 'data'],
        ]);
        
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        
        // Test unauthorized access
        $response = self::$client->get('analytics/health-dashboard', [
            'headers' => ['Authorization' => 'Bearer invalid-token'],
        ]);
        
        $this->assertEquals(401, $response->getStatusCode());
    }
}