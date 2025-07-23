# Phase 3 - Backend Implementation Guide

## Overview
Phase 3 implements the backend for AI-powered features and custom modules: OpenAI integration for lead scoring and chatbot, form builder module, knowledge base with semantic search, activity tracking system, and customer health scoring. This phase transforms SuiteCRM into an intelligent CRM platform.

## Prerequisites
- Phase 1 and Phase 2 backend completed
- OpenAI API key obtained
- SuiteCRM custom module framework understood
- Redis configured for caching
- Composer installed for PHP dependencies

## Step-by-Step Implementation

### 1. Install Required Dependencies

#### 1.1 Update Composer Configuration
`backend/composer.json` (add to existing):
```json
{
    "require": {
        "openai-php/client": "^0.7",
        "guzzlehttp/guzzle": "^7.5",
        "ramsey/uuid": "^4.7",
        "league/html-to-markdown": "^5.1",
        "algolia/algoliasearch-client-php": "^3.3",
        "predis/predis": "^2.1"
    }
}
```

Run composer update:
```bash
docker exec suitecrm_app composer update
```

#### 1.2 Create Environment Configuration
`custom/config/ai_config.php`:
```php
<?php
// AI Configuration
$ai_config = [
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key-here',
        'organization' => getenv('OPENAI_ORG_ID') ?: null,
        'model' => [
            'chat' => 'gpt-3.5-turbo',
            'completion' => 'gpt-4',
            'embedding' => 'text-embedding-ada-002',
        ],
        'max_tokens' => [
            'chat' => 1000,
            'completion' => 2000,
        ],
        'temperature' => 0.7,
    ],
    'lead_scoring' => [
        'weights' => [
            'company_size' => 0.20,
            'industry_match' => 0.15,
            'behavior_score' => 0.25,
            'engagement' => 0.20,
            'budget_signals' => 0.20,
        ],
        'thresholds' => [
            'hot' => 80,
            'warm' => 60,
            'cool' => 40,
        ],
    ],
    'activity_tracking' => [
        'session_timeout' => 1800, // 30 minutes
        'high_value_pages' => ['/pricing', '/demo', '/contact', '/trial'],
        'engagement_thresholds' => [
            'high' => ['pages' => 5, 'time' => 300],
            'medium' => ['pages' => 3, 'time' => 120],
        ],
    ],
];

// Load into global config
global $sugar_config;
$sugar_config['ai'] = $ai_config;
```

### 2. Create Custom Database Tables

#### 2.1 Create Installation SQL
`custom/install/sql/phase3_tables.sql`:
```sql
-- Form Builder Tables
CREATE TABLE IF NOT EXISTS form_builder_forms (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    fields JSON NOT NULL,
    settings JSON NOT NULL,
    embed_code TEXT,
    is_active TINYINT(1) DEFAULT 1,
    submissions_count INT DEFAULT 0,
    created_by CHAR(36),
    date_created DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_active (is_active, deleted),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_submissions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    form_id CHAR(36) NOT NULL,
    lead_id CHAR(36),
    data JSON NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(500),
    date_submitted DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_form (form_id),
    INDEX idx_lead (lead_id),
    FOREIGN KEY (form_id) REFERENCES form_builder_forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Knowledge Base Tables
CREATE TABLE IF NOT EXISTS kb_categories (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    parent_id CHAR(36),
    sort_order INT DEFAULT 0,
    icon VARCHAR(50),
    created_by CHAR(36),
    date_created DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_articles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    category_id CHAR(36),
    tags JSON,
    is_public TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    views INT DEFAULT 0,
    helpful_yes INT DEFAULT 0,
    helpful_no INT DEFAULT 0,
    search_vector TEXT,
    embedding_vector JSON,
    author_id CHAR(36),
    date_created DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_category (category_id),
    INDEX idx_public (is_public, deleted),
    INDEX idx_featured (is_featured, is_public, deleted),
    FULLTEXT idx_search (title, content, tags),
    FOREIGN KEY (category_id) REFERENCES kb_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_article_feedback (
    id CHAR(36) NOT NULL PRIMARY KEY,
    article_id CHAR(36) NOT NULL,
    session_id VARCHAR(255),
    helpful TINYINT(1),
    comment TEXT,
    date_created DATETIME,
    INDEX idx_article (article_id),
    FOREIGN KEY (article_id) REFERENCES kb_articles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Tracking Tables
CREATE TABLE IF NOT EXISTS website_sessions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id VARCHAR(255) NOT NULL,
    lead_id CHAR(36),
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(500),
    landing_page VARCHAR(500),
    pages_viewed JSON,
    total_time INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    last_activity DATETIME,
    location JSON,
    device_info JSON,
    date_created DATETIME,
    INDEX idx_visitor (visitor_id),
    INDEX idx_lead (lead_id),
    INDEX idx_active (is_active, last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS page_views (
    id CHAR(36) NOT NULL PRIMARY KEY,
    session_id CHAR(36) NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255),
    time_on_page INT DEFAULT 0,
    scroll_depth INT DEFAULT 0,
    clicks INT DEFAULT 0,
    timestamp DATETIME,
    INDEX idx_session (session_id),
    INDEX idx_url (url),
    FOREIGN KEY (session_id) REFERENCES website_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_events (
    id CHAR(36) NOT NULL PRIMARY KEY,
    session_id CHAR(36) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    timestamp DATETIME,
    INDEX idx_session_event (session_id, event_type),
    FOREIGN KEY (session_id) REFERENCES website_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Chat Tables
CREATE TABLE IF NOT EXISTS ai_chat_sessions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id VARCHAR(255) NOT NULL,
    lead_id CHAR(36),
    messages JSON NOT NULL,
    context JSON,
    intent VARCHAR(50),
    sentiment_score DECIMAL(3,2),
    lead_score INT,
    status ENUM('active', 'ended', 'transferred') DEFAULT 'active',
    ended_at DATETIME,
    date_created DATETIME,
    INDEX idx_visitor (visitor_id),
    INDEX idx_lead (lead_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Scoring History
CREATE TABLE IF NOT EXISTS ai_lead_scores (
    id CHAR(36) NOT NULL PRIMARY KEY,
    lead_id CHAR(36) NOT NULL,
    score INT NOT NULL,
    factors JSON NOT NULL,
    insights JSON,
    confidence DECIMAL(3,2),
    model_version VARCHAR(50),
    date_created DATETIME,
    INDEX idx_lead (lead_id),
    INDEX idx_date (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer Health Scores
CREATE TABLE IF NOT EXISTS customer_health_scores (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    score INT NOT NULL,
    factors JSON NOT NULL,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    churn_probability DECIMAL(3,2),
    recommendations JSON,
    calculated_at DATETIME,
    INDEX idx_account (account_id),
    INDEX idx_risk (risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 2.2 Create Table Installation Script
`custom/install/install_phase3_tables.php`:
```php
<?php
require_once('include/database/DBManagerFactory.php');

function installPhase3Tables() {
    global $db;
    
    $sqlFile = __DIR__ . '/sql/phase3_tables.sql';
    $sql = file_get_contents($sqlFile);
    
    // Split SQL statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        'strlen'
    );
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $db->query($statement);
        }
    }
    
    echo "Phase 3 tables created successfully!\n";
}

// Run installation
installPhase3Tables();
```

### 3. OpenAI Service Implementation

#### 3.1 Create OpenAI Service Class
`custom/services/OpenAIService.php`:
```php
<?php
namespace Custom\Services;

use OpenAI;
use Exception;

class OpenAIService
{
    private $client;
    private $config;
    
    public function __construct()
    {
        global $sugar_config;
        $this->config = $sugar_config['ai']['openai'] ?? [];
        
        if (empty($this->config['api_key'])) {
            throw new Exception('OpenAI API key not configured');
        }
        
        $this->client = OpenAI::client($this->config['api_key']);
    }
    
    /**
     * Score a lead using AI analysis
     */
    public function scoreLead($leadData)
    {
        $prompt = $this->buildLeadScoringPrompt($leadData);
        
        try {
            $response = $this->client->chat()->create([
                'model' => $this->config['model']['completion'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->getLeadScoringSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
                'response_format' => ['type' => 'json_object']
            ]);
            
            $result = json_decode($response->choices[0]->message->content, true);
            
            // Calculate final score
            $score = $this->calculateLeadScore($result['factors'], $leadData);
            
            return [
                'score' => $score,
                'factors' => $result['factors'],
                'insights' => $result['insights'] ?? [],
                'recommended_actions' => $result['recommended_actions'] ?? [],
                'confidence' => $result['confidence'] ?? 0.85,
            ];
            
        } catch (Exception $e) {
            \LoggerManager::getLogger()->error('OpenAI Lead Scoring Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle chatbot conversation
     */
    public function chat($messages, $context = [])
    {
        try {
            // Add system prompt
            $systemMessage = [
                'role' => 'system',
                'content' => $this->getChatbotSystemPrompt($context)
            ];
            
            $allMessages = array_merge([$systemMessage], $messages);
            
            $response = $this->client->chat()->create([
                'model' => $this->config['model']['chat'],
                'messages' => $allMessages,
                'temperature' => $this->config['temperature'],
                'max_tokens' => $this->config['max_tokens']['chat'],
            ]);
            
            $content = $response->choices[0]->message->content;
            
            // Analyze intent and extract lead info if applicable
            $analysis = $this->analyzeChatIntent($messages, $content);
            
            return [
                'content' => $content,
                'metadata' => [
                    'intent' => $analysis['intent'],
                    'confidence' => $analysis['confidence'],
                    'suggested_articles' => $analysis['suggested_articles'] ?? [],
                    'lead_score' => $analysis['lead_score'] ?? null,
                ],
                'lead_info' => $analysis['lead_info'] ?? null,
            ];
            
        } catch (Exception $e) {
            \LoggerManager::getLogger()->error('OpenAI Chat Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate embeddings for knowledge base search
     */
    public function generateEmbedding($text)
    {
        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->config['model']['embedding'],
                'input' => $text,
            ]);
            
            return $response->embeddings[0]->embedding;
            
        } catch (Exception $e) {
            \LoggerManager::getLogger()->error('OpenAI Embedding Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Search knowledge base using semantic search
     */
    public function searchKnowledgeBase($query, $limit = 5)
    {
        // Generate embedding for query
        $queryEmbedding = $this->generateEmbedding($query);
        
        // Search articles by similarity
        global $db;
        
        // This is a simplified version - in production, you'd use a vector database
        $sql = "SELECT id, title, excerpt, 
                JSON_EXTRACT(embedding_vector, '$') as embedding
                FROM kb_articles 
                WHERE is_public = 1 AND deleted = 0
                LIMIT 100";
        
        $result = $db->query($sql);
        $articles = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            if ($row['embedding']) {
                $embedding = json_decode($row['embedding'], true);
                $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
                
                $articles[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'similarity' => $similarity,
                ];
            }
        }
        
        // Sort by similarity and return top results
        usort($articles, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($articles, 0, $limit);
    }
    
    /**
     * Analyze customer health and predict churn
     */
    public function analyzeCustomerHealth($accountData)
    {
        $prompt = $this->buildHealthAnalysisPrompt($accountData);
        
        try {
            $response = $this->client->chat()->create([
                'model' => $this->config['model']['completion'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->getHealthAnalysisSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
                'response_format' => ['type' => 'json_object']
            ]);
            
            return json_decode($response->choices[0]->message->content, true);
            
        } catch (Exception $e) {
            \LoggerManager::getLogger()->error('OpenAI Health Analysis Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    // Helper methods
    
    private function buildLeadScoringPrompt($leadData)
    {
        return sprintf(
            "Analyze this lead and provide scoring factors:\n" .
            "Company: %s\n" .
            "Industry: %s\n" .
            "Employee Count: %s\n" .
            "Website Activity: %d page views, %d minutes on site\n" .
            "High-Value Pages Visited: %s\n" .
            "Form Submissions: %d\n" .
            "Email Opens: %d\n" .
            "Return JSON with: factors (company_size, industry_match, behavior_score, engagement, budget_signals), insights[], recommended_actions[], confidence",
            $leadData['company_name'] ?? 'Unknown',
            $leadData['industry'] ?? 'Unknown',
            $leadData['employees'] ?? 'Unknown',
            $leadData['page_views'] ?? 0,
            $leadData['time_on_site'] ?? 0,
            implode(', ', $leadData['high_value_pages'] ?? []),
            $leadData['form_submissions'] ?? 0,
            $leadData['email_opens'] ?? 0
        );
    }
    
    private function getLeadScoringSystemPrompt()
    {
        return "You are an expert B2B sales analyst. Analyze leads for a software company and provide scoring based on:
        - Company size and growth potential (0-20 points)
        - Industry match and use case fit (0-15 points)
        - Website behavior and engagement (0-25 points)
        - Content engagement and interest signals (0-20 points)
        - Budget and buying signals (0-20 points)
        
        Provide specific insights about why the lead scored as they did and actionable recommendations for the sales team.";
    }
    
    private function getChatbotSystemPrompt($context)
    {
        return "You are a helpful customer service representative for a B2B software company. 
        Your goals are to:
        1. Answer questions about the product
        2. Qualify leads by asking about their needs
        3. Offer to schedule demos for qualified prospects
        4. Provide support for existing customers
        5. Search the knowledge base when appropriate
        
        Current page: {$context['page_url']}
        Be friendly, professional, and helpful. If someone seems like a good lead, gather their contact information.";
    }
    
    private function calculateLeadScore($factors, $leadData)
    {
        global $sugar_config;
        $weights = $sugar_config['ai']['lead_scoring']['weights'];
        
        $score = 0;
        foreach ($factors as $factor => $value) {
            if (isset($weights[$factor])) {
                $score += $value * $weights[$factor];
            }
        }
        
        return round($score);
    }
    
    private function cosineSimilarity($vec1, $vec2)
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }
    
    private function analyzeChatIntent($messages, $response)
    {
        // Simplified intent analysis
        $lastMessage = end($messages)['content'];
        
        $intent = 'general';
        if (stripos($lastMessage, 'demo') !== false || stripos($lastMessage, 'trial') !== false) {
            $intent = 'sales';
        } elseif (stripos($lastMessage, 'support') !== false || stripos($lastMessage, 'help') !== false) {
            $intent = 'support';
        }
        
        return [
            'intent' => $intent,
            'confidence' => 0.85,
            'lead_score' => $intent === 'sales' ? 75 : null,
        ];
    }
}
```

### 4. Custom Module Controllers

#### 4.1 Create AI Controller
`custom/api/v8/controllers/AIController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;
use Custom\Services\OpenAIService;

class AIController extends BaseController
{
    private $aiService;
    
    public function __construct()
    {
        $this->aiService = new OpenAIService();
    }
    
    /**
     * Score a single lead
     */
    public function scoreLead(Request $request, Response $response, array $args)
    {
        try {
            $data = $request->getParsedBody();
            $leadId = $data['lead_id'] ?? null;
            
            if (!$leadId) {
                return $response->withJson(['error' => 'Lead ID required'], 400);
            }
            
            // Get lead data
            $lead = \BeanFactory::getBean('Leads', $leadId);
            if (!$lead || $lead->deleted) {
                return $response->withJson(['error' => 'Lead not found'], 404);
            }
            
            // Gather lead data for scoring
            $leadData = $this->gatherLeadData($lead);
            
            // Get AI score
            $scoreResult = $this->aiService->scoreLead($leadData);
            
            // Save score to lead
            $lead->ai_score = $scoreResult['score'];
            $lead->ai_score_date = gmdate('Y-m-d H:i:s');
            $lead->ai_score_factors = json_encode($scoreResult['factors']);
            $lead->save();
            
            // Save to history
            $this->saveScoreHistory($leadId, $scoreResult);
            
            return $response->withJson($scoreResult);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Batch score multiple leads
     */
    public function batchScoreLeads(Request $request, Response $response, array $args)
    {
        try {
            $data = $request->getParsedBody();
            $leadIds = $data['lead_ids'] ?? [];
            
            if (empty($leadIds)) {
                return $response->withJson(['error' => 'Lead IDs required'], 400);
            }
            
            $results = [];
            
            foreach ($leadIds as $leadId) {
                try {
                    $lead = \BeanFactory::getBean('Leads', $leadId);
                    if ($lead && !$lead->deleted) {
                        $leadData = $this->gatherLeadData($lead);
                        $scoreResult = $this->aiService->scoreLead($leadData);
                        
                        // Save score
                        $lead->ai_score = $scoreResult['score'];
                        $lead->ai_score_date = gmdate('Y-m-d H:i:s');
                        $lead->ai_score_factors = json_encode($scoreResult['factors']);
                        $lead->save();
                        
                        $results[$leadId] = $scoreResult;
                    }
                } catch (\Exception $e) {
                    $results[$leadId] = ['error' => $e->getMessage()];
                }
            }
            
            return $response->withJson($results);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Handle chat messages
     */
    public function chat(Request $request, Response $response, array $args)
    {
        try {
            $data = $request->getParsedBody();
            $messages = $data['messages'] ?? [];
            $context = $data['context'] ?? [];
            $sessionId = $data['session_id'] ?? null;
            
            if (empty($messages)) {
                return $response->withJson(['error' => 'Messages required'], 400);
            }
            
            // Get or create chat session
            $session = $this->getChatSession($sessionId, $context);
            
            // Process chat
            $result = $this->aiService->chat($messages, $context);
            
            // Update session
            $this->updateChatSession($session['id'], $messages, $result);
            
            // Check if we should create a lead
            if ($result['lead_info'] && $result['metadata']['lead_score'] > 60) {
                $this->createLeadFromChat($result['lead_info'], $session['id']);
            }
            
            return $response->withJson($result);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Search knowledge base
     */
    public function searchKnowledgeBase(Request $request, Response $response, array $args)
    {
        try {
            $data = $request->getParsedBody();
            $query = $data['query'] ?? '';
            $limit = $data['limit'] ?? 5;
            
            if (empty($query)) {
                return $response->withJson(['error' => 'Query required'], 400);
            }
            
            $results = $this->aiService->searchKnowledgeBase($query, $limit);
            
            return $response->withJson($results);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
    
    // Helper methods
    
    private function gatherLeadData($lead)
    {
        global $db;
        
        // Get website activity
        $activityQuery = "SELECT 
            COUNT(DISTINCT ws.id) as sessions,
            SUM(ws.total_time) as total_time,
            COUNT(DISTINCT pv.url) as unique_pages
            FROM website_sessions ws
            LEFT JOIN page_views pv ON ws.id = pv.session_id
            WHERE ws.lead_id = '{$lead->id}'";
        
        $activityResult = $db->query($activityQuery);
        $activity = $db->fetchByAssoc($activityResult);
        
        // Get high-value page visits
        global $sugar_config;
        $highValuePages = $sugar_config['ai']['activity_tracking']['high_value_pages'];
        $highValueVisits = [];
        
        $pagesQuery = "SELECT DISTINCT pv.url 
                      FROM page_views pv
                      JOIN website_sessions ws ON pv.session_id = ws.id
                      WHERE ws.lead_id = '{$lead->id}'";
        
        $pagesResult = $db->query($pagesQuery);
        while ($page = $db->fetchByAssoc($pagesResult)) {
            foreach ($highValuePages as $hvPage) {
                if (stripos($page['url'], $hvPage) !== false) {
                    $highValueVisits[] = $hvPage;
                }
            }
        }
        
        return [
            'company_name' => $lead->account_name,
            'industry' => $lead->industry,
            'employees' => $lead->employees,
            'website' => $lead->website,
            'lead_source' => $lead->lead_source,
            'page_views' => $activity['unique_pages'] ?? 0,
            'time_on_site' => round(($activity['total_time'] ?? 0) / 60),
            'sessions' => $activity['sessions'] ?? 0,
            'high_value_pages' => array_unique($highValueVisits),
            'form_submissions' => $this->getFormSubmissionCount($lead->id),
            'email_opens' => 0, // Would integrate with email tracking
        ];
    }
    
    private function getFormSubmissionCount($leadId)
    {
        global $db;
        $query = "SELECT COUNT(*) as count FROM form_submissions WHERE lead_id = '$leadId'";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        return $row['count'] ?? 0;
    }
    
    private function saveScoreHistory($leadId, $scoreResult)
    {
        global $db;
        
        $id = create_guid();
        $query = "INSERT INTO ai_lead_scores 
                 (id, lead_id, score, factors, insights, confidence, model_version, date_created)
                 VALUES (
                    '$id',
                    '$leadId',
                    {$scoreResult['score']},
                    '" . json_encode($scoreResult['factors']) . "',
                    '" . json_encode($scoreResult['insights']) . "',
                    {$scoreResult['confidence']},
                    'gpt-4',
                    NOW()
                 )";
        
        $db->query($query);
    }
    
    private function getChatSession($sessionId, $context)
    {
        global $db;
        
        if ($sessionId) {
            $query = "SELECT * FROM ai_chat_sessions WHERE id = '$sessionId'";
            $result = $db->query($query);
            $session = $db->fetchByAssoc($result);
            
            if ($session) {
                return $session;
            }
        }
        
        // Create new session
        $id = create_guid();
        $visitorId = $context['visitor_id'] ?? uniqid('visitor_');
        
        $query = "INSERT INTO ai_chat_sessions 
                 (id, visitor_id, messages, context, status, date_created)
                 VALUES (
                    '$id',
                    '$visitorId',
                    '[]',
                    '" . json_encode($context) . "',
                    'active',
                    NOW()
                 )";
        
        $db->query($query);
        
        return [
            'id' => $id,
            'visitor_id' => $visitorId,
            'messages' => [],
        ];
    }
    
    private function updateChatSession($sessionId, $messages, $result)
    {
        global $db;
        
        $query = "UPDATE ai_chat_sessions 
                 SET messages = '" . json_encode($messages) . "',
                     intent = '{$result['metadata']['intent']}',
                     lead_score = " . ($result['metadata']['lead_score'] ?? 'NULL') . "
                 WHERE id = '$sessionId'";
        
        $db->query($query);
    }
    
    private function createLeadFromChat($leadInfo, $sessionId)
    {
        $lead = \BeanFactory::newBean('Leads');
        
        $lead->first_name = $leadInfo['first_name'] ?? '';
        $lead->last_name = $leadInfo['last_name'] ?? '';
        $lead->email = $leadInfo['email'] ?? '';
        $lead->phone_mobile = $leadInfo['phone'] ?? '';
        $lead->account_name = $leadInfo['company'] ?? '';
        $lead->title = $leadInfo['title'] ?? '';
        $lead->lead_source = 'Chat';
        $lead->status = 'New';
        $lead->description = "Lead captured from AI chat session: $sessionId";
        
        $lead->save();
        
        // Update chat session with lead ID
        global $db;
        $query = "UPDATE ai_chat_sessions SET lead_id = '{$lead->id}' WHERE id = '$sessionId'";
        $db->query($query);
        
        return $lead->id;
    }
}
```

#### 4.2 Create Form Builder Controller
`custom/api/v8/controllers/FormBuilderController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class FormBuilderController extends BaseController
{
    /**
     * Get all forms
     */
    public function getForms(Request $request, Response $response, array $args)
    {
        global $db;
        
        $page = $request->getQueryParam('page', 1);
        $limit = $request->getQueryParam('limit', 20);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT * FROM form_builder_forms 
                 WHERE deleted = 0 
                 ORDER BY date_created DESC 
                 LIMIT $limit OFFSET $offset";
        
        $result = $db->query($query);
        $forms = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['fields'] = json_decode($row['fields'], true);
            $row['settings'] = json_decode($row['settings'], true);
            $forms[] = $row;
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM form_builder_forms WHERE deleted = 0";
        $countResult = $db->query($countQuery);
        $total = $db->fetchByAssoc($countResult)['total'];
        
        return $response->withJson([
            'data' => $forms,
            'meta' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$limit,
            ],
        ]);
    }
    
    /**
     * Get single form
     */
    public function getForm(Request $request, Response $response, array $args)
    {
        global $db;
        
        $formId = $args['id'];
        
        $query = "SELECT * FROM form_builder_forms WHERE id = '$formId' AND deleted = 0";
        $result = $db->query($query);
        $form = $db->fetchByAssoc($result);
        
        if (!$form) {
            return $response->withJson(['error' => 'Form not found'], 404);
        }
        
        $form['fields'] = json_decode($form['fields'], true);
        $form['settings'] = json_decode($form['settings'], true);
        
        return $response->withJson($form);
    }
    
    /**
     * Create form
     */
    public function createForm(Request $request, Response $response, array $args)
    {
        global $db, $current_user;
        
        $data = $request->getParsedBody();
        
        $id = create_guid();
        $embedCode = $this->generateEmbedCode($id);
        
        $query = "INSERT INTO form_builder_forms 
                 (id, name, description, fields, settings, embed_code, created_by, date_created, date_modified)
                 VALUES (
                    '$id',
                    '{$db->quote($data['name'])}',
                    '{$db->quote($data['description'] ?? '')}',
                    '{$db->quote(json_encode($data['fields']))}',
                    '{$db->quote(json_encode($data['settings']))}',
                    '{$db->quote($embedCode)}',
                    '{$current_user->id}',
                    NOW(),
                    NOW()
                 )";
        
        $db->query($query);
        
        $data['id'] = $id;
        $data['embed_code'] = $embedCode;
        
        return $response->withJson($data, 201);
    }
    
    /**
     * Update form
     */
    public function updateForm(Request $request, Response $response, array $args)
    {
        global $db;
        
        $formId = $args['id'];
        $data = $request->getParsedBody();
        
        $query = "UPDATE form_builder_forms 
                 SET name = '{$db->quote($data['name'])}',
                     description = '{$db->quote($data['description'] ?? '')}',
                     fields = '{$db->quote(json_encode($data['fields']))}',
                     settings = '{$db->quote(json_encode($data['settings']))}',
                     date_modified = NOW()
                 WHERE id = '$formId'";
        
        $db->query($query);
        
        return $response->withJson($data);
    }
    
    /**
     * Delete form
     */
    public function deleteForm(Request $request, Response $response, array $args)
    {
        global $db;
        
        $formId = $args['id'];
        
        $query = "UPDATE form_builder_forms SET deleted = 1 WHERE id = '$formId'";
        $db->query($query);
        
        return $response->withStatus(204);
    }
    
    /**
     * Handle form submission
     */
    public function submitForm(Request $request, Response $response, array $args)
    {
        global $db;
        
        $formId = $args['id'];
        $data = $request->getParsedBody();
        
        // Get form
        $formQuery = "SELECT * FROM form_builder_forms WHERE id = '$formId' AND deleted = 0";
        $formResult = $db->query($formQuery);
        $form = $db->fetchByAssoc($formResult);
        
        if (!$form) {
            return $response->withJson(['error' => 'Form not found'], 404);
        }
        
        // Validate submission
        $fields = json_decode($form['fields'], true);
        $errors = $this->validateSubmission($data, $fields);
        
        if (!empty($errors)) {
            return $response->withJson(['errors' => $errors], 400);
        }
        
        // Create lead if email provided
        $leadId = null;
        if (!empty($data['email'])) {
            $leadId = $this->createLeadFromSubmission($data);
        }
        
        // Save submission
        $submissionId = create_guid();
        $query = "INSERT INTO form_submissions 
                 (id, form_id, lead_id, data, ip_address, user_agent, referrer, date_submitted)
                 VALUES (
                    '$submissionId',
                    '$formId',
                    " . ($leadId ? "'$leadId'" : 'NULL') . ",
                    '{$db->quote(json_encode($data))}',
                    '{$_SERVER['REMOTE_ADDR']}',
                    '{$db->quote($_SERVER['HTTP_USER_AGENT'] ?? '')}',
                    '{$db->quote($_SERVER['HTTP_REFERER'] ?? '')}',
                    NOW()
                 )";
        
        $db->query($query);
        
        // Update submission count
        $db->query("UPDATE form_builder_forms SET submissions_count = submissions_count + 1 WHERE id = '$formId'");
        
        // Send notification email if configured
        $settings = json_decode($form['settings'], true);
        if (!empty($settings['notificationEmail'])) {
            $this->sendSubmissionNotification($settings['notificationEmail'], $form['name'], $data);
        }
        
        return $response->withJson([
            'success' => true,
            'submission_id' => $submissionId,
            'lead_id' => $leadId,
            'message' => $settings['successMessage'] ?? 'Thank you for your submission!',
        ]);
    }
    
    /**
     * Get form submissions
     */
    public function getSubmissions(Request $request, Response $response, array $args)
    {
        global $db;
        
        $formId = $args['id'];
        $page = $request->getQueryParam('page', 1);
        $limit = $request->getQueryParam('limit', 20);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT s.*, l.first_name, l.last_name, l.email as lead_email
                 FROM form_submissions s
                 LEFT JOIN leads l ON s.lead_id = l.id
                 WHERE s.form_id = '$formId' AND s.deleted = 0
                 ORDER BY s.date_submitted DESC
                 LIMIT $limit OFFSET $offset";
        
        $result = $db->query($query);
        $submissions = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['data'] = json_decode($row['data'], true);
            $submissions[] = $row;
        }
        
        // Get total
        $countQuery = "SELECT COUNT(*) as total FROM form_submissions WHERE form_id = '$formId' AND deleted = 0";
        $countResult = $db->query($countQuery);
        $total = $db->fetchByAssoc($countResult)['total'];
        
        return $response->withJson([
            'data' => $submissions,
            'meta' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$limit,
            ],
        ]);
    }
    
    // Helper methods
    
    private function generateEmbedCode($formId)
    {
        $siteUrl = $GLOBALS['sugar_config']['site_url'];
        
        return "<script src=\"{$siteUrl}/forms/embed.js\"></script>\n" .
               "<div data-form-id=\"{$formId}\" data-form-container></div>";
    }
    
    private function validateSubmission($data, $fields)
    {
        $errors = [];
        
        foreach ($fields as $field) {
            if ($field['required'] && empty($data[$field['id']])) {
                $errors[$field['id']] = "{$field['label']} is required";
            }
            
            // Email validation
            if ($field['type'] === 'email' && !empty($data[$field['id']])) {
                if (!filter_var($data[$field['id']], FILTER_VALIDATE_EMAIL)) {
                    $errors[$field['id']] = "Invalid email format";
                }
            }
            
            // Custom validation rules
            if (!empty($field['validation'])) {
                // Pattern validation
                if (!empty($field['validation']['pattern'])) {
                    $pattern = '/' . $field['validation']['pattern'] . '/';
                    if (!preg_match($pattern, $data[$field['id']] ?? '')) {
                        $errors[$field['id']] = "Invalid format";
                    }
                }
                
                // Length validation
                if (!empty($field['validation']['minLength'])) {
                    if (strlen($data[$field['id']] ?? '') < $field['validation']['minLength']) {
                        $errors[$field['id']] = "Minimum length is {$field['validation']['minLength']}";
                    }
                }
            }
        }
        
        return $errors;
    }
    
    private function createLeadFromSubmission($data)
    {
        $lead = \BeanFactory::newBean('Leads');
        
        // Map common fields
        $fieldMap = [
            'email' => 'email1',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'company' => 'account_name',
            'phone' => 'phone_mobile',
            'title' => 'title',
        ];
        
        foreach ($fieldMap as $formField => $leadField) {
            if (!empty($data[$formField])) {
                $lead->$leadField = $data[$formField];
            }
        }
        
        $lead->lead_source = 'Web Form';
        $lead->status = 'New';
        $lead->description = "Submitted form data: " . json_encode($data);
        
        $lead->save();
        
        // Trigger AI scoring
        try {
            $aiService = new \Custom\Services\OpenAIService();
            $leadData = [
                'company_name' => $lead->account_name,
                'email' => $lead->email1,
                'form_submissions' => 1,
            ];
            $scoreResult = $aiService->scoreLead($leadData);
            
            $lead->ai_score = $scoreResult['score'];
            $lead->ai_score_date = gmdate('Y-m-d H:i:s');
            $lead->save();
        } catch (\Exception $e) {
            // Log error but don't fail submission
            \LoggerManager::getLogger()->error('AI Scoring failed for lead: ' . $e->getMessage());
        }
        
        return $lead->id;
    }
    
    private function sendSubmissionNotification($email, $formName, $data)
    {
        $subject = "New Form Submission: $formName";
        $body = "A new form submission has been received:\n\n";
        
        foreach ($data as $field => $value) {
            $body .= ucfirst(str_replace('_', ' ', $field)) . ": $value\n";
        }
        
        // Use SuiteCRM's email functionality
        require_once('modules/Emails/Email.php');
        $emailObj = new \Email();
        $emailObj->to_addrs = $email;
        $emailObj->type = 'out';
        $emailObj->status = 'sent';
        $emailObj->name = $subject;
        $emailObj->description = $body;
        $emailObj->description_html = nl2br($body);
        $emailObj->from_addr = $GLOBALS['sugar_config']['notify_fromaddress'];
        $emailObj->from_name = $GLOBALS['sugar_config']['notify_fromname'];
        
        $emailObj->send();
    }
}
```

#### 4.3 Create Knowledge Base Controller
`custom/api/v8/controllers/KnowledgeBaseController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;
use Custom\Services\OpenAIService;

class KnowledgeBaseController extends BaseController
{
    /**
     * Get categories
     */
    public function getCategories(Request $request, Response $response, array $args)
    {
        global $db;
        
        $query = "SELECT c.*, 
                 (SELECT COUNT(*) FROM kb_articles a 
                  WHERE a.category_id = c.id AND a.deleted = 0) as article_count
                 FROM kb_categories c
                 WHERE c.deleted = 0
                 ORDER BY c.sort_order, c.name";
        
        $result = $db->query($query);
        $categories = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $categories[] = $row;
        }
        
        return $response->withJson($categories);
    }
    
    /**
     * Create category
     */
    public function createCategory(Request $request, Response $response, array $args)
    {
        global $db, $current_user;
        
        $data = $request->getParsedBody();
        
        $id = create_guid();
        $slug = $this->generateSlug($data['name']);
        
        $query = "INSERT INTO kb_categories 
                 (id, name, slug, description, parent_id, sort_order, icon, created_by, date_created, date_modified)
                 VALUES (
                    '$id',
                    '{$db->quote($data['name'])}',
                    '{$db->quote($slug)}',
                    '{$db->quote($data['description'] ?? '')}',
                    " . ($data['parent_id'] ? "'{$data['parent_id']}'" : 'NULL') . ",
                    " . ($data['sort_order'] ?? 0) . ",
                    '{$db->quote($data['icon'] ?? '')}',
                    '{$current_user->id}',
                    NOW(),
                    NOW()
                 )";
        
        $db->query($query);
        
        $data['id'] = $id;
        $data['slug'] = $slug;
        
        return $response->withJson($data, 201);
    }
    
    /**
     * Get articles
     */
    public function getArticles(Request $request, Response $response, array $args)
    {
        global $db;
        
        $page = $request->getQueryParam('page', 1);
        $limit = $request->getQueryParam('limit', 20);
        $categoryId = $request->getQueryParam('category_id');
        $isPublic = $request->getQueryParam('is_public');
        $search = $request->getQueryParam('search');
        $offset = ($page - 1) * $limit;
        
        $where = ["a.deleted = 0"];
        
        if ($categoryId) {
            $where[] = "a.category_id = '$categoryId'";
        }
        
        if ($isPublic !== null) {
            $where[] = "a.is_public = " . ($isPublic ? '1' : '0');
        }
        
        if ($search) {
            $searchEscaped = $db->quote($search);
            $where[] = "(a.title LIKE '%$searchEscaped%' OR a.content LIKE '%$searchEscaped%')";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "SELECT a.*, c.name as category_name, 
                 CONCAT(u.first_name, ' ', u.last_name) as author_name
                 FROM kb_articles a
                 LEFT JOIN kb_categories c ON a.category_id = c.id
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE $whereClause
                 ORDER BY a.is_featured DESC, a.date_created DESC
                 LIMIT $limit OFFSET $offset";
        
        $result = $db->query($query);
        $articles = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['tags'] = json_decode($row['tags'] ?? '[]', true);
            $articles[] = $row;
        }
        
        // Get total
        $countQuery = "SELECT COUNT(*) as total FROM kb_articles a WHERE $whereClause";
        $countResult = $db->query($countQuery);
        $total = $db->fetchByAssoc($countResult)['total'];
        
        return $response->withJson([
            'data' => $articles,
            'meta' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$limit,
            ],
        ]);
    }
    
    /**
     * Get article by slug
     */
    public function getArticleBySlug(Request $request, Response $response, array $args)
    {
        global $db;
        
        $slug = $args['slug'];
        
        $query = "SELECT a.*, c.name as category_name,
                 CONCAT(u.first_name, ' ', u.last_name) as author_name
                 FROM kb_articles a
                 LEFT JOIN kb_categories c ON a.category_id = c.id
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.slug = '$slug' AND a.deleted = 0";
        
        $result = $db->query($query);
        $article = $db->fetchByAssoc($result);
        
        if (!$article) {
            return $response->withJson(['error' => 'Article not found'], 404);
        }
        
        $article['tags'] = json_decode($article['tags'] ?? '[]', true);
        
        // Get related articles
        $article['related_articles'] = $this->getRelatedArticles($article['id'], $article['category_id']);
        
        // Track view
        $this->trackArticleView($article['id']);
        
        return $response->withJson($article);
    }
    
    /**
     * Create article
     */
    public function createArticle(Request $request, Response $response, array $args)
    {
        global $db, $current_user;
        
        $data = $request->getParsedBody();
        
        $id = create_guid();
        $slug = $this->generateSlug($data['title']);
        
        // Generate embedding for semantic search
        $aiService = new OpenAIService();
        $embedding = $aiService->generateEmbedding($data['title'] . ' ' . strip_tags($data['content']));
        
        $query = "INSERT INTO kb_articles 
                 (id, title, slug, content, excerpt, category_id, tags, is_public, is_featured, 
                  search_vector, embedding_vector, author_id, date_created, date_modified)
                 VALUES (
                    '$id',
                    '{$db->quote($data['title'])}',
                    '{$db->quote($slug)}',
                    '{$db->quote($data['content'])}',
                    '{$db->quote($data['excerpt'] ?? $this->generateExcerpt($data['content']))}',
                    '{$data['category_id']}',
                    '{$db->quote(json_encode($data['tags'] ?? []))}',
                    " . ($data['is_public'] ? 1 : 0) . ",
                    " . ($data['is_featured'] ? 1 : 0) . ",
                    '{$db->quote($this->generateSearchVector($data))}',
                    '{$db->quote(json_encode($embedding))}',
                    '{$current_user->id}',
                    NOW(),
                    NOW()
                 )";
        
        $db->query($query);
        
        $data['id'] = $id;
        $data['slug'] = $slug;
        
        return $response->withJson($data, 201);
    }
    
    /**
     * Update article
     */
    public function updateArticle(Request $request, Response $response, array $args)
    {
        global $db;
        
        $articleId = $args['id'];
        $data = $request->getParsedBody();
        
        // Regenerate embedding if content changed
        $aiService = new OpenAIService();
        $embedding = $aiService->generateEmbedding($data['title'] . ' ' . strip_tags($data['content']));
        
        $query = "UPDATE kb_articles 
                 SET title = '{$db->quote($data['title'])}',
                     content = '{$db->quote($data['content'])}',
                     excerpt = '{$db->quote($data['excerpt'] ?? $this->generateExcerpt($data['content']))}',
                     category_id = '{$data['category_id']}',
                     tags = '{$db->quote(json_encode($data['tags'] ?? []))}',
                     is_public = " . ($data['is_public'] ? 1 : 0) . ",
                     is_featured = " . ($data['is_featured'] ? 1 : 0) . ",
                     search_vector = '{$db->quote($this->generateSearchVector($data))}',
                     embedding_vector = '{$db->quote(json_encode($embedding))}',
                     date_modified = NOW()
                 WHERE id = '$articleId'";
        
        $db->query($query);
        
        return $response->withJson($data);
    }
    
    /**
     * Rate article
     */
    public function rateArticle(Request $request, Response $response, array $args)
    {
        global $db;
        
        $articleId = $args['id'];
        $data = $request->getParsedBody();
        $helpful = $data['helpful'] ?? true;
        
        // Update article rating
        $field = $helpful ? 'helpful_yes' : 'helpful_no';
        $query = "UPDATE kb_articles SET $field = $field + 1 WHERE id = '$articleId'";
        $db->query($query);
        
        // Save feedback
        $feedbackId = create_guid();
        $sessionId = session_id();
        
        $query = "INSERT INTO kb_article_feedback 
                 (id, article_id, session_id, helpful, comment, date_created)
                 VALUES (
                    '$feedbackId',
                    '$articleId',
                    '$sessionId',
                    " . ($helpful ? 1 : 0) . ",
                    '{$db->quote($data['comment'] ?? '')}',
                    NOW()
                 )";
        
        $db->query($query);
        
        return $response->withJson(['success' => true]);
    }
    
    /**
     * Search articles
     */
    public function searchArticles(Request $request, Response $response, array $args)
    {
        $query = $request->getQueryParam('q');
        $limit = $request->getQueryParam('limit', 10);
        
        if (empty($query)) {
            return $response->withJson([]);
        }
        
        // Use AI for semantic search
        $aiService = new OpenAIService();
        $results = $aiService->searchKnowledgeBase($query, $limit);
        
        // Enhance results with full article data
        global $db;
        $articles = [];
        
        foreach ($results as $result) {
            $articleQuery = "SELECT a.*, c.name as category_name
                           FROM kb_articles a
                           LEFT JOIN kb_categories c ON a.category_id = c.id
                           WHERE a.id = '{$result['id']}'";
            
            $articleResult = $db->query($articleQuery);
            $article = $db->fetchByAssoc($articleResult);
            
            if ($article) {
                $article['tags'] = json_decode($article['tags'] ?? '[]', true);
                $article['relevance_score'] = $result['similarity'];
                $articles[] = $article;
            }
        }
        
        return $response->withJson($articles);
    }
    
    // Helper methods
    
    private function generateSlug($title)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Ensure uniqueness
        global $db;
        $baseSlug = $slug;
        $counter = 1;
        
        while (true) {
            $query = "SELECT id FROM kb_articles WHERE slug = '$slug' AND deleted = 0";
            $result = $db->query($query);
            
            if ($db->fetchByAssoc($result) === false) {
                break;
            }
            
            $slug = $baseSlug . '-' . $counter++;
        }
        
        return $slug;
    }
    
    private function generateExcerpt($content)
    {
        $text = strip_tags($content);
        $text = substr($text, 0, 200);
        return $text . (strlen($text) >= 200 ? '...' : '');
    }
    
    private function generateSearchVector($data)
    {
        // Simple search vector - in production, use proper text processing
        $searchText = $data['title'] . ' ' . strip_tags($data['content']);
        $searchText = strtolower(preg_replace('/[^a-z0-9 ]/', ' ', $searchText));
        return $searchText;
    }
    
    private function getRelatedArticles($articleId, $categoryId)
    {
        global $db;
        
        $query = "SELECT id, title, slug, excerpt 
                 FROM kb_articles 
                 WHERE category_id = '$categoryId' 
                 AND id != '$articleId'
                 AND is_public = 1 
                 AND deleted = 0
                 ORDER BY views DESC
                 LIMIT 5";
        
        $result = $db->query($query);
        $related = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $related[] = $row;
        }
        
        return $related;
    }
    
    private function trackArticleView($articleId)
    {
        global $db;
        $query = "UPDATE kb_articles SET views = views + 1 WHERE id = '$articleId'";
        $db->query($query);
    }
}
```

#### 4.4 Create Activity Tracking Controller
`custom/api/v8/controllers/ActivityTrackingController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class ActivityTrackingController extends BaseController
{
    /**
     * Track page view or activity event
     */
    public function trackEvent(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $eventType = $data['type'] ?? 'pageview';
        
        // Get or create session
        $session = $this->getOrCreateSession($data);
        
        switch ($eventType) {
            case 'pageview':
                $this->trackPageView($session['id'], $data);
                break;
                
            case 'click':
                $this->trackClick($session['id'], $data);
                break;
                
            case 'form_interaction':
                $this->trackFormInteraction($session['id'], $data);
                break;
                
            case 'session_end':
                $this->endSession($session['id']);
                break;
                
            default:
                $this->trackCustomEvent($session['id'], $eventType, $data);
        }
        
        // Check for lead identification
        if (!empty($data['identify']) && !empty($session['visitor_id'])) {
            $this->identifyVisitor($session['visitor_id'], $data['identify']);
        }
        
        return $response->withJson([
            'success' => true,
            'session_id' => $session['id'],
            'visitor_id' => $session['visitor_id'],
        ]);
    }
    
    /**
     * Get active sessions
     */
    public function getSessions(Request $request, Response $response, array $args)
    {
        global $db;
        
        $page = $request->getQueryParam('page', 1);
        $limit = $request->getQueryParam('limit', 20);
        $leadId = $request->getQueryParam('lead_id');
        $activeOnly = $request->getQueryParam('active_only', false);
        $offset = ($page - 1) * $limit;
        
        $where = [];
        
        if ($leadId) {
            $where[] = "s.lead_id = '$leadId'";
        }
        
        if ($activeOnly) {
            $where[] = "s.is_active = 1";
            $where[] = "s.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT s.*, l.first_name, l.last_name, l.email, l.account_name
                 FROM website_sessions s
                 LEFT JOIN leads l ON s.lead_id = l.id
                 $whereClause
                 ORDER BY s.last_activity DESC
                 LIMIT $limit OFFSET $offset";
        
        $result = $db->query($query);
        $sessions = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['pages_viewed'] = json_decode($row['pages_viewed'] ?? '[]', true);
            $row['location'] = json_decode($row['location'] ?? '{}', true);
            $row['device_info'] = json_decode($row['device_info'] ?? '{}', true);
            
            // Add engagement level
            $row['engagement_level'] = $this->calculateEngagement($row);
            
            $sessions[] = $row;
        }
        
        // Get total
        $countQuery = "SELECT COUNT(*) as total FROM website_sessions s $whereClause";
        $countResult = $db->query($countQuery);
        $total = $db->fetchByAssoc($countResult)['total'];
        
        return $response->withJson([
            'data' => $sessions,
            'meta' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$limit,
            ],
        ]);
    }
    
    /**
     * Get live visitors
     */
    public function getLiveVisitors(Request $request, Response $response, array $args)
    {
        global $db;
        
        $query = "SELECT s.*, l.first_name, l.last_name, l.email, l.account_name,
                 (SELECT url FROM page_views WHERE session_id = s.id ORDER BY timestamp DESC LIMIT 1) as current_page
                 FROM website_sessions s
                 LEFT JOIN leads l ON s.lead_id = l.id
                 WHERE s.is_active = 1 
                 AND s.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 ORDER BY s.last_activity DESC";
        
        $result = $db->query($query);
        $visitors = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['pages_viewed'] = json_decode($row['pages_viewed'] ?? '[]', true);
            $row['location'] = json_decode($row['location'] ?? '{}', true);
            $row['engagement_level'] = $this->calculateEngagement($row);
            $visitors[] = $row;
        }
        
        return $response->withJson($visitors);
    }
    
    /**
     * Get session details
     */
    public function getSession(Request $request, Response $response, array $args)
    {
        global $db;
        
        $sessionId = $args['id'];
        
        // Get session
        $query = "SELECT s.*, l.first_name, l.last_name, l.email, l.account_name
                 FROM website_sessions s
                 LEFT JOIN leads l ON s.lead_id = l.id
                 WHERE s.id = '$sessionId'";
        
        $result = $db->query($query);
        $session = $db->fetchByAssoc($result);
        
        if (!$session) {
            return $response->withJson(['error' => 'Session not found'], 404);
        }
        
        $session['pages_viewed'] = json_decode($session['pages_viewed'] ?? '[]', true);
        $session['location'] = json_decode($session['location'] ?? '{}', true);
        $session['device_info'] = json_decode($session['device_info'] ?? '{}', true);
        
        // Get detailed page views
        $pagesQuery = "SELECT * FROM page_views 
                      WHERE session_id = '$sessionId' 
                      ORDER BY timestamp ASC";
        
        $pagesResult = $db->query($pagesQuery);
        $pageViews = [];
        
        while ($page = $db->fetchByAssoc($pagesResult)) {
            $pageViews[] = $page;
        }
        
        $session['detailed_page_views'] = $pageViews;
        
        // Get events
        $eventsQuery = "SELECT * FROM activity_events 
                       WHERE session_id = '$sessionId' 
                       ORDER BY timestamp ASC";
        
        $eventsResult = $db->query($eventsQuery);
        $events = [];
        
        while ($event = $db->fetchByAssoc($eventsResult)) {
            $event['event_data'] = json_decode($event['event_data'] ?? '{}', true);
            $events[] = $event;
        }
        
        $session['events'] = $events;
        
        return $response->withJson($session);
    }
    
    /**
     * Get heatmap data
     */
    public function getHeatmapData(Request $request, Response $response, array $args)
    {
        global $db;
        
        $pageUrl = $request->getQueryParam('page_url');
        $startDate = $request->getQueryParam('start_date', date('Y-m-d', strtotime('-7 days')));
        $endDate = $request->getQueryParam('end_date', date('Y-m-d'));
        
        if (!$pageUrl) {
            return $response->withJson(['error' => 'Page URL required'], 400);
        }
        
        // Get click data
        $clickQuery = "SELECT e.event_data
                      FROM activity_events e
                      JOIN website_sessions s ON e.session_id = s.id
                      WHERE e.event_type = 'click'
                      AND JSON_EXTRACT(e.event_data, '$.page_url') = '$pageUrl'
                      AND DATE(e.timestamp) BETWEEN '$startDate' AND '$endDate'";
        
        $clickResult = $db->query($clickQuery);
        $clickData = [];
        
        while ($row = $db->fetchByAssoc($clickResult)) {
            $data = json_decode($row['event_data'], true);
            if (isset($data['x']) && isset($data['y'])) {
                $key = $data['x'] . ',' . $data['y'];
                if (!isset($clickData[$key])) {
                    $clickData[$key] = [
                        'x' => $data['x'],
                        'y' => $data['y'],
                        'value' => 0,
                    ];
                }
                $clickData[$key]['value']++;
            }
        }
        
        // Get scroll depth data
        $scrollQuery = "SELECT AVG(pv.scroll_depth) as avg_depth, 
                       COUNT(*) as views
                       FROM page_views pv
                       WHERE pv.url = '$pageUrl'
                       AND DATE(pv.timestamp) BETWEEN '$startDate' AND '$endDate'
                       GROUP BY FLOOR(pv.scroll_depth / 10) * 10";
        
        $scrollResult = $db->query($scrollQuery);
        $scrollData = [];
        
        while ($row = $db->fetchByAssoc($scrollResult)) {
            $scrollData[] = [
                'depth' => $row['avg_depth'],
                'percentage' => $row['views'],
            ];
        }
        
        return $response->withJson([
            'page_url' => $pageUrl,
            'click_data' => array_values($clickData),
            'scroll_data' => $scrollData,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }
    
    /**
     * Identify visitor with lead
     */
    public function identifyVisitor(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $visitorId = $data['visitor_id'] ?? null;
        $leadId = $data['lead_id'] ?? null;
        
        if (!$visitorId || !$leadId) {
            return $response->withJson(['error' => 'Visitor ID and Lead ID required'], 400);
        }
        
        $this->linkVisitorToLead($visitorId, $leadId);
        
        return $response->withJson(['success' => true]);
    }
    
    // Helper methods
    
    private function getOrCreateSession($data)
    {
        global $db;
        
        $visitorId = $data['visitor_id'] ?? null;
        
        if ($visitorId) {
            // Check for existing active session
            $query = "SELECT * FROM website_sessions 
                     WHERE visitor_id = '$visitorId' 
                     AND is_active = 1 
                     AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                     ORDER BY last_activity DESC 
                     LIMIT 1";
            
            $result = $db->query($query);
            $session = $db->fetchByAssoc($result);
            
            if ($session) {
                // Update last activity
                $updateQuery = "UPDATE website_sessions 
                               SET last_activity = NOW() 
                               WHERE id = '{$session['id']}'";
                $db->query($updateQuery);
                
                return $session;
            }
        }
        
        // Create new session
        $sessionId = create_guid();
        $visitorId = $visitorId ?: uniqid('visitor_');
        
        // Get location from IP
        $location = $this->getLocationFromIP($_SERVER['REMOTE_ADDR']);
        
        // Parse user agent
        $deviceInfo = $this->parseUserAgent($data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $query = "INSERT INTO website_sessions 
                 (id, visitor_id, ip_address, user_agent, referrer, landing_page, 
                  pages_viewed, location, device_info, date_created, last_activity)
                 VALUES (
                    '$sessionId',
                    '$visitorId',
                    '{$_SERVER['REMOTE_ADDR']}',
                    '{$db->quote($data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '')}',
                    '{$db->quote($data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '')}',
                    '{$db->quote($data['url'] ?? '')}',
                    '[]',
                    '{$db->quote(json_encode($location))}',
                    '{$db->quote(json_encode($deviceInfo))}',
                    NOW(),
                    NOW()
                 )";
        
        $db->query($query);
        
        return [
            'id' => $sessionId,
            'visitor_id' => $visitorId,
        ];
    }
    
    private function trackPageView($sessionId, $data)
    {
        global $db;
        
        $pageViewId = create_guid();
        
        $query = "INSERT INTO page_views 
                 (id, session_id, url, title, timestamp)
                 VALUES (
                    '$pageViewId',
                    '$sessionId',
                    '{$db->quote($data['url'])}',
                    '{$db->quote($data['title'] ?? '')}',
                    NOW()
                 )";
        
        $db->query($query);
        
        // Update session pages_viewed
        $this->updateSessionPages($sessionId, $data);
        
        // Check for high-value page
        $this->checkHighValuePage($sessionId, $data['url']);
    }
    
    private function trackClick($sessionId, $data)
    {
        global $db;
        
        $eventId = create_guid();
        
        $eventData = [
            'page_url' => $data['page_url'] ?? '',
            'element' => $data['element'] ?? '',
            'x' => $data['x'] ?? 0,
            'y' => $data['y'] ?? 0,
            'text' => $data['text'] ?? '',
        ];
        
        $query = "INSERT INTO activity_events 
                 (id, session_id, event_type, event_data, timestamp)
                 VALUES (
                    '$eventId',
                    '$sessionId',
                    'click',
                    '{$db->quote(json_encode($eventData))}',
                    NOW()
                 )";
        
        $db->query($query);
        
        // Update page clicks
        if (!empty($data['page_url'])) {
            $updateQuery = "UPDATE page_views 
                           SET clicks = clicks + 1 
                           WHERE session_id = '$sessionId' 
                           AND url = '{$db->quote($data['page_url'])}'
                           ORDER BY timestamp DESC 
                           LIMIT 1";
            $db->query($updateQuery);
        }
    }
    
    private function updateSessionPages($sessionId, $pageData)
    {
        global $db;
        
        // Get current pages
        $query = "SELECT pages_viewed FROM website_sessions WHERE id = '$sessionId'";
        $result = $db->query($query);
        $session = $db->fetchByAssoc($result);
        
        $pages = json_decode($session['pages_viewed'] ?? '[]', true);
        
        // Add new page
        $pages[] = [
            'url' => $pageData['url'],
            'title' => $pageData['title'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        // Update session
        $updateQuery = "UPDATE website_sessions 
                       SET pages_viewed = '{$db->quote(json_encode($pages))}',
                           total_time = TIMESTAMPDIFF(SECOND, date_created, NOW())
                       WHERE id = '$sessionId'";
        
        $db->query($updateQuery);
    }
    
    private function checkHighValuePage($sessionId, $url)
    {
        global $sugar_config;
        
        $highValuePages = $sugar_config['ai']['activity_tracking']['high_value_pages'];
        
        foreach ($highValuePages as $hvPage) {
            if (stripos($url, $hvPage) !== false) {
                // Trigger lead scoring if visitor is identified
                global $db;
                $query = "SELECT lead_id FROM website_sessions WHERE id = '$sessionId' AND lead_id IS NOT NULL";
                $result = $db->query($query);
                $session = $db->fetchByAssoc($result);
                
                if ($session && $session['lead_id']) {
                    // Queue for AI scoring
                    $this->queueLeadScoring($session['lead_id']);
                }
                
                break;
            }
        }
    }
    
    private function calculateEngagement($session)
    {
        global $sugar_config;
        
        $thresholds = $sugar_config['ai']['activity_tracking']['engagement_thresholds'];
        $pageCount = count($session['pages_viewed']);
        $totalTime = $session['total_time'];
        
        if ($pageCount >= $thresholds['high']['pages'] || $totalTime >= $thresholds['high']['time']) {
            return 'high';
        } elseif ($pageCount >= $thresholds['medium']['pages'] || $totalTime >= $thresholds['medium']['time']) {
            return 'medium';
        }
        
        return 'low';
    }
    
    private function linkVisitorToLead($visitorId, $leadId)
    {
        global $db;
        
        $query = "UPDATE website_sessions 
                 SET lead_id = '$leadId' 
                 WHERE visitor_id = '$visitorId'";
        
        $db->query($query);
    }
    
    private function getLocationFromIP($ip)
    {
        // In production, use a proper IP geolocation service
        return [
            'country' => 'United States',
            'city' => 'New York',
            'region' => 'NY',
        ];
    }
    
    private function parseUserAgent($userAgent)
    {
        // Simple user agent parsing - use a proper library in production
        $device = 'desktop';
        if (stripos($userAgent, 'mobile') !== false) {
            $device = 'mobile';
        } elseif (stripos($userAgent, 'tablet') !== false) {
            $device = 'tablet';
        }
        
        $browser = 'unknown';
        if (stripos($userAgent, 'chrome') !== false) {
            $browser = 'chrome';
        } elseif (stripos($userAgent, 'firefox') !== false) {
            $browser = 'firefox';
        } elseif (stripos($userAgent, 'safari') !== false) {
            $browser = 'safari';
        }
        
        return [
            'device' => $device,
            'browser' => $browser,
            'os' => 'unknown',
        ];
    }
    
    private function queueLeadScoring($leadId)
    {
        // In production, use a job queue
        // For now, trigger scoring directly
        try {
            $aiController = new AIController();
            $request = \Slim\Http\Request::createFromGlobals($_SERVER);
            $response = new \Slim\Http\Response();
            
            $request = $request->withParsedBody(['lead_id' => $leadId]);
            $aiController->scoreLead($request, $response, []);
        } catch (\Exception $e) {
            \LoggerManager::getLogger()->error('Lead scoring queue error: ' . $e->getMessage());
        }
    }
}
```

### 5. Update API Routes

`custom/api/v8/routes/routes.php` (complete file):
```php
<?php
return [
    // Dashboard endpoints (Phase 2)
    'dashboard_metrics' => [
        'method' => 'GET',
        'route' => '/dashboard/metrics',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getMetrics',
        'secure' => true,
    ],
    'dashboard_pipeline' => [
        'method' => 'GET',
        'route' => '/dashboard/pipeline',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getPipelineData',
        'secure' => true,
    ],
    'dashboard_activities' => [
        'method' => 'GET',
        'route' => '/dashboard/activities',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getActivityMetrics',
        'secure' => true,
    ],
    'dashboard_cases' => [
        'method' => 'GET',
        'route' => '/dashboard/cases',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getCaseMetrics',
        'secure' => true,
    ],
    
    // AI endpoints (Phase 3)
    'ai_score_lead' => [
        'method' => 'POST',
        'route' => '/ai/score-lead',
        'class' => 'Api\V8\Custom\Controller\AIController',
        'function' => 'scoreLead',
        'secure' => true,
    ],
    'ai_batch_score' => [
        'method' => 'POST',
        'route' => '/ai/batch-score-leads',
        'class' => 'Api\V8\Custom\Controller\AIController',
        'function' => 'batchScoreLeads',
        'secure' => true,
    ],
    'ai_chat' => [
        'method' => 'POST',
        'route' => '/ai/chat',
        'class' => 'Api\V8\Custom\Controller\AIController',
        'function' => 'chat',
        'secure' => false, // Allow anonymous chat
    ],
    'ai_kb_search' => [
        'method' => 'POST',
        'route' => '/ai/kb-search',
        'class' => 'Api\V8\Custom\Controller\AIController',
        'function' => 'searchKnowledgeBase',
        'secure' => false,
    ],
    
    // Form Builder endpoints
    'forms_list' => [
        'method' => 'GET',
        'route' => '/forms',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'getForms',
        'secure' => true,
    ],
    'forms_get' => [
        'method' => 'GET',
        'route' => '/forms/{id}',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'getForm',
        'secure' => true,
    ],
    'forms_create' => [
        'method' => 'POST',
        'route' => '/forms',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'createForm',
        'secure' => true,
    ],
    'forms_update' => [
        'method' => 'PUT',
        'route' => '/forms/{id}',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'updateForm',
        'secure' => true,
    ],
    'forms_delete' => [
        'method' => 'DELETE',
        'route' => '/forms/{id}',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'deleteForm',
        'secure' => true,
    ],
    'forms_submit' => [
        'method' => 'POST',
        'route' => '/forms/{id}/submit',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'submitForm',
        'secure' => false, // Public form submissions
    ],
    'forms_submissions' => [
        'method' => 'GET',
        'route' => '/forms/{id}/submissions',
        'class' => 'Api\V8\Custom\Controller\FormBuilderController',
        'function' => 'getSubmissions',
        'secure' => true,
    ],
    
    // Knowledge Base endpoints
    'kb_categories' => [
        'method' => 'GET',
        'route' => '/kb/categories',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'getCategories',
        'secure' => false, // Public access
    ],
    'kb_create_category' => [
        'method' => 'POST',
        'route' => '/kb/categories',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'createCategory',
        'secure' => true,
    ],
    'kb_articles' => [
        'method' => 'GET',
        'route' => '/kb/articles',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'getArticles',
        'secure' => false,
    ],
    'kb_article_slug' => [
        'method' => 'GET',
        'route' => '/kb/articles/slug/{slug}',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'getArticleBySlug',
        'secure' => false,
    ],
    'kb_create_article' => [
        'method' => 'POST',
        'route' => '/kb/articles',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'createArticle',
        'secure' => true,
    ],
    'kb_update_article' => [
        'method' => 'PUT',
        'route' => '/kb/articles/{id}',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'updateArticle',
        'secure' => true,
    ],
    'kb_rate_article' => [
        'method' => 'POST',
        'route' => '/kb/articles/{id}/rate',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'rateArticle',
        'secure' => false,
    ],
    'kb_search' => [
        'method' => 'GET',
        'route' => '/kb/search',
        'class' => 'Api\V8\Custom\Controller\KnowledgeBaseController',
        'function' => 'searchArticles',
        'secure' => false,
    ],
    
    // Activity Tracking endpoints
    'tracking_event' => [
        'method' => 'POST',
        'route' => '/tracking/events',
        'class' => 'Api\V8\Custom\Controller\ActivityTrackingController',
        'function' => 'trackEvent',
        'secure' => false, // Allow anonymous tracking
    ],
    'tracking_sessions' => [
        'method' => 'GET',
        'route' => '/tracking/sessions',
        'class' => 'Api\V8\Custom\Controller\ActivityTrackingController',
        'function' => 'getSessions',
        'secure' => true,
    ],
    'tracking_session' => [
        'method' => 'GET',
        'route' => '/tracking/sessions/{id}',
        'class' => 'Api\V8\Custom\Controller\ActivityTrackingController',
        'function' => 'getSession',
        'secure' => true,
    ],
    'tracking_live' => [
        'method' => 'GET',
        'route' => '/tracking/live',
        'class' => 'Api\V8\Custom\Controller\ActivityTrackingController',
        'function' => 'getLiveVisitors',
        'secure' => true,
    ],
    'tracking_heatmap' => [
        'method' => 'GET',
        'route' => '/tracking/heatmap',
        'class' => 'Api\V8\Custom\Controller\ActivityTrackingController',
        'function' => 'getHeatmapData',
        'secure' => true,
    ],
    'tracking_identify' => [
        'method' => 'POST',
        'route' => '/tracking/identify',
        'class' => 'Api\V8\Custom\Controller\ActivityTrackingController',
        'function' => 'identifyVisitor',
        'secure' => true,
    ],
];
```

### 6. Create Embed Scripts

#### 6.1 Create Form Embed Script
`public/forms/embed.js`:
```javascript
(function() {
    // Form Embed Script
    const API_URL = 'http://localhost:8080/api/v8';
    
    // Find all form containers
    const containers = document.querySelectorAll('[data-form-container]');
    
    containers.forEach(container => {
        const formId = container.getAttribute('data-form-id');
        if (!formId) return;
        
        // Load form
        fetch(`${API_URL}/forms/${formId}`)
            .then(res => res.json())
            .then(form => {
                renderForm(container, form);
            })
            .catch(err => {
                console.error('Failed to load form:', err);
            });
    });
    
    function renderForm(container, form) {
        const formEl = document.createElement('form');
        formEl.className = 'crm-form';
        formEl.setAttribute('data-form-id', form.id);
        
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .crm-form {
                font-family: ${form.settings.styling.fontFamily};
                max-width: 600px;
                margin: 0 auto;
            }
            .crm-form-field {
                margin-bottom: 1rem;
            }
            .crm-form-label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }
            .crm-form-input {
                width: 100%;
                padding: 0.5rem;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .crm-form-submit {
                background: ${form.settings.styling.primaryColor};
                color: white;
                padding: 0.75rem 2rem;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .crm-form-submit:hover {
                opacity: 0.9;
            }
            .crm-form-success {
                color: green;
                margin-top: 1rem;
            }
            .crm-form-error {
                color: red;
                margin-top: 0.25rem;
                font-size: 0.875rem;
            }
        `;
        document.head.appendChild(style);
        
        // Render fields
        form.fields.forEach(field => {
            const fieldEl = createField(field);
            formEl.appendChild(fieldEl);
        });
        
        // Submit button
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'crm-form-submit';
        submitBtn.textContent = form.settings.submitButtonText;
        formEl.appendChild(submitBtn);
        
        // Success message container
        const messageEl = document.createElement('div');
        messageEl.className = 'crm-form-message';
        formEl.appendChild(messageEl);
        
        // Handle submission
        formEl.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(formEl);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch(`${API_URL}/forms/${form.id}/submit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    messageEl.className = 'crm-form-success';
                    messageEl.textContent = result.message || form.settings.successMessage;
                    formEl.reset();
                    
                    if (form.settings.redirectUrl) {
                        setTimeout(() => {
                            window.location.href = form.settings.redirectUrl;
                        }, 2000);
                    }
                } else {
                    messageEl.className = 'crm-form-error';
                    messageEl.textContent = 'Submission failed. Please try again.';
                }
            } catch (err) {
                messageEl.className = 'crm-form-error';
                messageEl.textContent = 'Network error. Please try again.';
            }
        });
        
        container.appendChild(formEl);
    }
    
    function createField(field) {
        const wrapper = document.createElement('div');
        wrapper.className = 'crm-form-field';
        
        // Label
        const label = document.createElement('label');
        label.className = 'crm-form-label';
        label.textContent = field.label;
        if (field.required) {
            label.innerHTML += ' <span style="color: red;">*</span>';
        }
        wrapper.appendChild(label);
        
        // Input
        let input;
        switch (field.type) {
            case 'textarea':
                input = document.createElement('textarea');
                input.rows = 4;
                break;
                
            case 'select':
                input = document.createElement('select');
                field.options?.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    input.appendChild(option);
                });
                break;
                
            default:
                input = document.createElement('input');
                input.type = field.type;
                break;
        }
        
        input.name = field.id;
        input.className = 'crm-form-input';
        input.placeholder = field.placeholder || '';
        input.required = field.required;
        
        wrapper.appendChild(input);
        
        return wrapper;
    }
})();
```

#### 6.2 Create Tracking Script
`public/tracking.js`:
```javascript
(function(w,d,s,l,i) {
    // Activity Tracking Script
    const API_URL = 'http://localhost:8080/api/v8';
    const SITE_ID = i;
    
    // Initialize tracking
    w[l] = w[l] || [];
    
    // Get or create visitor ID
    let visitorId = localStorage.getItem('crm_visitor_id');
    if (!visitorId) {
        visitorId = 'v_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
        localStorage.setItem('crm_visitor_id', visitorId);
    }
    
    let sessionId = sessionStorage.getItem('crm_session_id');
    
    // Track page view
    function trackPageView() {
        const data = {
            type: 'pageview',
            visitor_id: visitorId,
            session_id: sessionId,
            site_id: SITE_ID,
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_width: screen.width,
            screen_height: screen.height,
        };
        
        fetch(`${API_URL}/tracking/events`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(res => res.json())
        .then(result => {
            if (!sessionId && result.session_id) {
                sessionId = result.session_id;
                sessionStorage.setItem('crm_session_id', sessionId);
            }
        })
        .catch(err => console.error('Tracking error:', err));
    }
    
    // Track clicks
    function trackClick(e) {
        const target = e.target;
        const data = {
            type: 'click',
            visitor_id: visitorId,
            session_id: sessionId,
            page_url: window.location.href,
            element: target.tagName,
            text: target.textContent?.substring(0, 50),
            x: e.clientX,
            y: e.clientY,
        };
        
        fetch(`${API_URL}/tracking/events`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        }).catch(() => {}); // Fail silently
    }
    
    // Track time on page
    let startTime = Date.now();
    function trackTimeOnPage() {
        const timeSpent = Math.round((Date.now() - startTime) / 1000);
        
        fetch(`${API_URL}/tracking/events`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: 'time_on_page',
                visitor_id: visitorId,
                session_id: sessionId,
                page_url: window.location.href,
                time_spent: timeSpent,
            }),
        }).catch(() => {});
    }
    
    // Identify visitor
    w.crmIdentify = function(leadData) {
        fetch(`${API_URL}/tracking/identify`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                visitor_id: visitorId,
                lead_id: leadData.lead_id,
                email: leadData.email,
            }),
        }).catch(() => {});
    };
    
    // Initialize tracking
    if (document.readyState === 'complete') {
        trackPageView();
    } else {
        window.addEventListener('load', trackPageView);
    }
    
    // Click tracking
    document.addEventListener('click', trackClick);
    
    // Time tracking
    window.addEventListener('beforeunload', trackTimeOnPage);
    setInterval(trackTimeOnPage, 30000); // Every 30 seconds
    
})(window,document,'script','crmTrack',window.crmTrack?.site || 'default');
```

#### 6.3 Create Chat Widget Script
`public/chat-widget.js`:
```javascript
(function(w,d,s,l,i) {
    // Chat Widget Script
    const API_URL = 'http://localhost:8080/api/v8';
    const SITE_ID = i;
    const CONFIG = w[l]?.[0]?.config || {};
    
    // Create widget container
    const widgetContainer = document.createElement('div');
    widgetContainer.id = 'crm-chat-widget';
    widgetContainer.style.cssText = `
        position: fixed;
        ${CONFIG.position === 'bottom-left' ? 'left: 20px' : 'right: 20px'};
        bottom: 20px;
        z-index: 999999;
    `;
    
    // Create chat button
    const chatButton = document.createElement('button');
    chatButton.innerHTML = `
        <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
        </svg>
    `;
    chatButton.style.cssText = `
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: ${CONFIG.primaryColor || '#3b82f6'};
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create chat window
    const chatWindow = document.createElement('div');
    chatWindow.style.cssText = `
        position: absolute;
        bottom: 80px;
        ${CONFIG.position === 'bottom-left' ? 'left: 0' : 'right: 0'};
        width: 380px;
        height: 600px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        display: none;
        flex-direction: column;
    `;
    
    chatWindow.innerHTML = `
        <div style="background: ${CONFIG.primaryColor || '#3b82f6'}; color: white; padding: 20px; border-radius: 12px 12px 0 0;">
            <h3 style="margin: 0; font-size: 18px;">Chat with us</h3>
            <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">We typically reply in minutes</p>
        </div>
        <div id="crm-chat-messages" style="flex: 1; overflow-y: auto; padding: 20px;">
            <div class="crm-message crm-message-bot">
                ${CONFIG.greeting || "Hi! How can we help you today?"}
            </div>
        </div>
        <div style="padding: 20px; border-top: 1px solid #e5e7eb;">
            <form id="crm-chat-form" style="display: flex; gap: 10px;">
                <input 
                    type="text" 
                    id="crm-chat-input" 
                    placeholder="Type your message..." 
                    style="flex: 1; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px;"
                    required
                />
                <button type="submit" style="padding: 10px 20px; background: ${CONFIG.primaryColor || '#3b82f6'}; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Send
                </button>
            </form>
        </div>
    `;
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .crm-message {
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            max-width: 80%;
        }
        .crm-message-user {
            background: ${CONFIG.primaryColor || '#3b82f6'};
            color: white;
            margin-left: auto;
            text-align: right;
        }
        .crm-message-bot {
            background: #f3f4f6;
            color: #111827;
        }
    `;
    document.head.appendChild(style);
    
    // Chat state
    let isOpen = false;
    let messages = [];
    let sessionId = sessionStorage.getItem('crm_chat_session_id');
    let visitorId = localStorage.getItem('crm_visitor_id') || 'chat_' + Math.random().toString(36).substring(2);
    localStorage.setItem('crm_visitor_id', visitorId);
    
    // Toggle chat
    chatButton.addEventListener('click', () => {
        isOpen = !isOpen;
        chatWindow.style.display = isOpen ? 'flex' : 'none';
        
        if (isOpen && messages.length === 0) {
            // Start session
            messages.push({
                role: 'assistant',
                content: CONFIG.greeting || "Hi! How can we help you today?",
            });
        }
    });
    
    // Handle form submission
    const form = chatWindow.querySelector('#crm-chat-form');
    const input = chatWindow.querySelector('#crm-chat-input');
    const messagesContainer = chatWindow.querySelector('#crm-chat-messages');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const message = input.value.trim();
        if (!message) return;
        
        // Add user message
        messages.push({ role: 'user', content: message });
        addMessageToUI('user', message);
        input.value = '';
        
        // Show typing indicator
        const typingEl = document.createElement('div');
        typingEl.className = 'crm-message crm-message-bot';
        typingEl.textContent = '...';
        messagesContainer.appendChild(typingEl);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        try {
            // Send to API
            const response = await fetch(`${API_URL}/ai/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    messages: messages,
                    session_id: sessionId,
                    context: {
                        visitor_id: visitorId,
                        page_url: window.location.href,
                        site_id: SITE_ID,
                    },
                }),
            });
            
            const result = await response.json();
            
            // Remove typing indicator
            typingEl.remove();
            
            // Add bot response
            messages.push({ role: 'assistant', content: result.content });
            addMessageToUI('bot', result.content);
            
            // Update session ID
            if (!sessionId && result.session_id) {
                sessionId = result.session_id;
                sessionStorage.setItem('crm_chat_session_id', sessionId);
            }
            
        } catch (err) {
            typingEl.remove();
            addMessageToUI('bot', 'Sorry, I encountered an error. Please try again.');
        }
    });
    
    function addMessageToUI(type, content) {
        const messageEl = document.createElement('div');
        messageEl.className = `crm-message crm-message-${type}`;
        messageEl.textContent = content;
        messagesContainer.appendChild(messageEl);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Append to page
    widgetContainer.appendChild(chatButton);
    widgetContainer.appendChild(chatWindow);
    document.body.appendChild(widgetContainer);
    
})(window,document,'script','crmChat',window.crmChat?.[0]?.site || 'default');
```

