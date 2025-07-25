<?php
/**
 * OpenAI Service for AI-powered features
 * Handles all interactions with OpenAI API
 */

namespace SuiteCRM\Custom\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OpenAIService
{
    private $client;
    private $config;
    private $apiKey;
    private $cache;
    
    public function __construct()
    {
        // Load configuration
        $this->config = require(__DIR__ . '/../config/ai_config.php');
        $this->apiKey = $this->config['openai']['api_key'];
        
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is not configured. Please set OPENAI_API_KEY in your environment.');
        }
        
        // Initialize HTTP client
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => $this->config['openai']['timeout'] ?? 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ]
        ]);
        
        // Initialize cache (Redis)
        $this->initializeCache();
    }
    
    /**
     * Initialize Redis cache connection
     */
    private function initializeCache()
    {
        global $sugar_config;
        
        if (class_exists('\Predis\Client')) {
            try {
                $this->cache = new \Predis\Client([
                    'scheme' => 'tcp',
                    'host' => $sugar_config['external_cache']['redis']['host'] ?? 'redis',
                    'port' => $sugar_config['external_cache']['redis']['port'] ?? 6379,
                ]);
                $this->cache->ping();
            } catch (Exception $e) {
                error_log('Redis cache initialization failed: ' . $e->getMessage());
                $this->cache = null;
            }
        }
    }
    
    /**
     * Score a lead using AI
     * 
     * @param array $leadData Lead information
     * @param array $activityData Activity and engagement data
     * @return array Score result with insights
     */
    public function scoreLead(array $leadData, array $activityData = [])
    {
        $prompt = $this->buildLeadScoringPrompt($leadData, $activityData);
        
        try {
            $response = $this->chatCompletion([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert B2B sales analyst. Analyze leads and provide scoring from 0-100 based on their likelihood to convert. Provide detailed insights and actionable recommendations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3, // Lower temperature for more consistent scoring
                'response_format' => ['type' => 'json_object']
            ]);
            
            $result = json_decode($response['choices'][0]['message']['content'], true);
            
            // Calculate final score with weighted factors
            $finalScore = $this->calculateWeightedScore($result, $leadData, $activityData);
            
            return [
                'score' => $finalScore,
                'factors' => $result['factors'] ?? [],
                'insights' => $result['insights'] ?? [],
                'recommendations' => $result['recommendations'] ?? [],
                'confidence' => $result['confidence'] ?? 0.8,
            ];
            
        } catch (Exception $e) {
            error_log('Lead scoring failed: ' . $e->getMessage());
            
            // Fallback to rule-based scoring
            return $this->fallbackLeadScoring($leadData, $activityData);
        }
    }
    
    /**
     * Build lead scoring prompt
     */
    private function buildLeadScoringPrompt(array $leadData, array $activityData)
    {
        $prompt = "Analyze this B2B lead and provide a score from 0-100:\n\n";
        $prompt .= "Lead Information:\n";
        $prompt .= "- Name: {$leadData['first_name']} {$leadData['last_name']}\n";
        $prompt .= "- Company: {$leadData['account_name']}\n";
        $prompt .= "- Title: {$leadData['title']}\n";
        $prompt .= "- Email: {$leadData['email']}\n";
        $prompt .= "- Industry: " . ($leadData['industry'] ?? 'Unknown') . "\n";
        $prompt .= "- Company Size: " . ($leadData['employees'] ?? 'Unknown') . "\n";
        $prompt .= "- Lead Source: {$leadData['lead_source']}\n";
        
        if (!empty($activityData)) {
            $prompt .= "\nEngagement Data:\n";
            $prompt .= "- Total Visits: " . ($activityData['total_visits'] ?? 0) . "\n";
            $prompt .= "- Pages Viewed: " . ($activityData['total_page_views'] ?? 0) . "\n";
            $prompt .= "- Time on Site: " . $this->formatSeconds($activityData['total_time_spent'] ?? 0) . "\n";
            $prompt .= "- High-Value Pages: " . implode(', ', $activityData['high_value_pages'] ?? []) . "\n";
            $prompt .= "- Form Submissions: " . ($activityData['form_submissions'] ?? 0) . "\n";
        }
        
        $prompt .= "\nReturn a JSON object with:\n";
        $prompt .= "- factors: object with scores for company_size, industry_match, behavior_score, engagement, budget_signals (each 0-20)\n";
        $prompt .= "- insights: array of key observations\n";
        $prompt .= "- recommendations: array of next best actions\n";
        $prompt .= "- confidence: confidence level 0-1\n";
        
        return $prompt;
    }
    
    /**
     * Calculate weighted score based on configuration
     */
    private function calculateWeightedScore(array $aiResult, array $leadData, array $activityData)
    {
        $weights = $this->config['lead_scoring']['weights'];
        $factors = $aiResult['factors'] ?? [];
        
        $score = 0;
        foreach ($weights as $factor => $weight) {
            $factorScore = $factors[$factor] ?? 10; // Default to 10/20 if not provided
            $score += $factorScore * $weight;
        }
        
        // Ensure score is between 0 and 100
        return max(0, min(100, round($score)));
    }
    
    /**
     * Fallback rule-based lead scoring
     */
    private function fallbackLeadScoring(array $leadData, array $activityData)
    {
        $score = 50; // Base score
        $factors = [];
        $insights = [];
        
        // Company size scoring
        $employees = $leadData['employees'] ?? '';
        if (strpos($employees, '1000') !== false) {
            $score += 10;
            $factors['company_size'] = 20;
            $insights[] = 'Enterprise company size is ideal';
        } elseif (strpos($employees, '100') !== false) {
            $score += 5;
            $factors['company_size'] = 15;
        } else {
            $factors['company_size'] = 10;
        }
        
        // Title scoring
        $title = strtolower($leadData['title'] ?? '');
        if (strpos($title, 'ceo') !== false || strpos($title, 'cto') !== false || strpos($title, 'vp') !== false) {
            $score += 10;
            $insights[] = 'Decision maker title identified';
        }
        
        // Engagement scoring
        if (!empty($activityData)) {
            $engagement = 0;
            if (($activityData['total_visits'] ?? 0) > 3) $engagement += 5;
            if (($activityData['total_page_views'] ?? 0) > 10) $engagement += 5;
            if (($activityData['form_submissions'] ?? 0) > 0) $engagement += 10;
            
            $score += $engagement;
            $factors['engagement'] = min(20, $engagement);
            
            if ($engagement > 15) {
                $insights[] = 'High engagement level detected';
            }
        }
        
        return [
            'score' => min(100, $score),
            'factors' => $factors,
            'insights' => $insights,
            'recommendations' => ['Follow up within 24 hours', 'Schedule a demo'],
            'confidence' => 0.6,
        ];
    }
    
    /**
     * Chat completion for chatbot
     */
    public function chatCompletion(array $params)
    {
        $defaultParams = [
            'model' => $this->config['openai']['model']['chat'],
            'max_tokens' => $this->config['openai']['max_tokens']['chat'],
            'temperature' => $this->config['openai']['temperature'],
        ];
        
        $params = array_merge($defaultParams, $params);
        
        return $this->makeRequest('chat/completions', $params);
    }
    
    /**
     * Generate embeddings for semantic search
     */
    public function generateEmbedding(string $text)
    {
        // Check cache first
        $cacheKey = 'embedding:' . md5($text);
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        }
        
        $response = $this->makeRequest('embeddings', [
            'model' => $this->config['openai']['model']['embedding'],
            'input' => $text,
        ]);
        
        $embedding = $response['data'][0]['embedding'];
        
        // Cache the embedding
        if ($this->cache) {
            $ttl = $this->config['knowledge_base']['embedding_cache_ttl'];
            $this->cache->setex($cacheKey, $ttl, json_encode($embedding));
        }
        
        return $embedding;
    }
    
    /**
     * Search knowledge base using semantic similarity
     */
    public function semanticSearch(string $query, array $embeddings, float $threshold = 0.75)
    {
        $queryEmbedding = $this->generateEmbedding($query);
        $results = [];
        
        foreach ($embeddings as $id => $data) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $data['embedding']);
            if ($similarity >= $threshold) {
                $results[] = [
                    'id' => $id,
                    'similarity' => $similarity,
                    'data' => $data['metadata'] ?? [],
                ];
            }
        }
        
        // Sort by similarity descending
        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $this->config['knowledge_base']['search_limit']);
    }
    
    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $vec1, array $vec2)
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dotProduct / ($norm1 * $norm2);
    }
    
    /**
     * Make API request with retry logic
     */
    private function makeRequest(string $endpoint, array $data)
    {
        $maxAttempts = $this->config['openai']['retry']['max_attempts'] ?? 3;
        $delay = $this->config['openai']['retry']['delay'] ?? 1000;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->client->post($endpoint, [
                    'json' => $data,
                ]);
                
                $result = json_decode($response->getBody()->getContents(), true);
                
                if (isset($result['error'])) {
                    throw new Exception($result['error']['message'] ?? 'Unknown API error');
                }
                
                return $result;
                
            } catch (GuzzleException $e) {
                error_log("OpenAI API request failed (attempt $attempt): " . $e->getMessage());
                
                if ($attempt < $maxAttempts) {
                    usleep($delay * 1000); // Convert to microseconds
                    $delay *= 2; // Exponential backoff
                } else {
                    throw new Exception('OpenAI API request failed after ' . $maxAttempts . ' attempts: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Format seconds to human readable
     */
    private function formatSeconds(int $seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }
    
    /**
     * Generate chat response for customer support
     */
    public function generateChatResponse(array $conversation, string $userMessage)
    {
        // Build conversation context
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful customer support agent for a B2B SaaS CRM platform. Be professional, friendly, and concise. If asked about pricing or complex technical issues, suggest connecting with a human agent.'
            ]
        ];
        
        // Add conversation history (limited to last N messages)
        $contextLimit = $this->config['chatbot']['conversation']['context_messages'];
        $recentMessages = array_slice($conversation, -$contextLimit);
        
        foreach ($recentMessages as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
        
        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];
        
        // Check for handoff triggers
        $handoffKeywords = $this->config['chatbot']['conversation']['handoff_keywords'];
        foreach ($handoffKeywords as $keyword) {
            if (stripos($userMessage, $keyword) !== false) {
                return [
                    'response' => "I'd be happy to connect you with one of our support specialists who can better assist you. Please hold while I transfer you.",
                    'handoff_required' => true,
                    'confidence' => 1.0,
                ];
            }
        }
        
        try {
            $response = $this->chatCompletion([
                'messages' => $messages,
                'temperature' => 0.7,
            ]);
            
            return [
                'response' => $response['choices'][0]['message']['content'],
                'handoff_required' => false,
                'confidence' => 0.9,
            ];
            
        } catch (Exception $e) {
            error_log('Chat response generation failed: ' . $e->getMessage());
            
            return [
                'response' => "I apologize, but I'm having trouble processing your request. Would you like me to connect you with a human agent?",
                'handoff_required' => true,
                'confidence' => 0.0,
            ];
        }
    }
    
    /**
     * Analyze customer health data and provide recommendations
     * 
     * @param array $healthData Customer health data
     * @return array Analysis results with recommendations
     */
    public function analyzeCustomerHealth(array $healthData)
    {
        $prompt = $this->buildHealthAnalysisPrompt($healthData);
        
        try {
            $response = $this->chatCompletion([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a customer success expert analyzing B2B SaaS customer health data. Provide actionable recommendations to prevent churn and improve customer satisfaction. Focus on specific, measurable actions the customer success team can take.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object']
            ]);
            
            $result = json_decode($response['choices'][0]['message']['content'], true);
            
            return [
                'recommendations' => $result['recommendations'] ?? [],
                'churn_probability' => $result['churn_probability'] ?? null,
                'key_risks' => $result['key_risks'] ?? [],
                'opportunities' => $result['opportunities'] ?? [],
            ];
            
        } catch (Exception $e) {
            error_log('Customer health AI analysis failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Build prompt for health analysis
     */
    private function buildHealthAnalysisPrompt($healthData)
    {
        $account = $healthData['account'];
        $support = $healthData['support_tickets'];
        $activities = $healthData['activities'];
        $usage = $healthData['usage'];
        $financials = $healthData['financials'];
        
        return "Analyze this B2B customer's health data and provide recommendations:

Company: {$account->name}
Industry: {$account->industry}
Contract Value: \${$financials['contract_value']}/month
Relationship Length: {$healthData['relationship']['months_active']} months

Support Metrics (Last 30 days):
- Total Tickets: {$support['total_tickets']}
- Open Tickets: {$support['open_tickets']}
- High Priority: {$support['high_priority']}
- Avg Resolution: {$support['avg_resolution_hours']} hours

Engagement Metrics:
- Recent Meetings: {$activities['meetings']}
- Recent Calls: {$activities['calls']}
- Active Users: {$usage['unique_users']}
- Sessions: {$usage['total_sessions']}
- Features Adopted: {$usage['features_used']}/{$usage['total_features']}

Payment History:
- Late Payments: {$financials['late_payments']}/{$financials['total_payments']}

Please provide:
1. 3-5 specific recommendations with priority (critical/high/medium)
2. Estimated churn probability (0-1)
3. Top 3 risk factors
4. Growth opportunities

Return as JSON with structure:
{
  \"recommendations\": [{\"priority\": \"high\", \"action\": \"...\", \"reason\": \"...\"}],
  \"churn_probability\": 0.25,
  \"key_risks\": [\"risk1\", \"risk2\", \"risk3\"],
  \"opportunities\": [\"opportunity1\", \"opportunity2\"]
}";
    }
}