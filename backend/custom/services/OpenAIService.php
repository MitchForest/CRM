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
     * Check if request is cacheable
     */
    private function isCacheableRequest($params)
    {
        // Don't cache if temperature is high (creative responses)
        if (($params['temperature'] ?? 0) > 0.5) {
            return false;
        }
        
        // Check for system prompts that indicate cacheable content
        $systemMessage = '';
        foreach ($params['messages'] ?? [] as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
                break;
            }
        }
        
        // Cache if it's a factual/extraction request
        $cacheablePatterns = [
            'extract',
            'analyze',
            'score',
            'categorize',
            'summarize',
            'json',
            'lead extraction expert'
        ];
        
        foreach ($cacheablePatterns as $pattern) {
            if (stripos($systemMessage, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate cache key for chat request
     */
    private function generateChatCacheKey($params)
    {
        // Create a normalized version of the request for caching
        $normalized = [
            'model' => $params['model'] ?? '',
            'temperature' => $params['temperature'] ?? 0,
            'messages' => array_map(function($msg) {
                return [
                    'role' => $msg['role'],
                    'content' => $this->normalizeContent($msg['content'])
                ];
            }, $params['messages'] ?? [])
        ];
        
        return 'chat:' . md5(json_encode($normalized));
    }
    
    /**
     * Normalize content for caching
     */
    private function normalizeContent($content)
    {
        // Remove timestamps, IDs, and other dynamic content
        $content = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', 'UUID', $content);
        $content = preg_replace('/\b\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}\b/', 'TIMESTAMP', $content);
        $content = preg_replace('/\bconversation_id:\s*\S+/i', 'conversation_id: ID', $content);
        
        return trim($content);
    }
    
    /**
     * Check if response should be cached
     */
    private function shouldCacheResponse($params, $response)
    {
        // Don't cache errors
        if (isset($response['error'])) {
            return false;
        }
        
        // Don't cache if response indicates dynamic content
        $content = $response['choices'][0]['message']['content'] ?? '';
        $dynamicPatterns = ['current time', 'right now', 'at this moment'];
        
        foreach ($dynamicPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get cached response
     */
    private function getCachedResponse($key)
    {
        if ($this->cache) {
            try {
                $cached = $this->cache->get($key);
                if ($cached) {
                    return json_decode($cached, true);
                }
            } catch (Exception $e) {
                error_log('Cache retrieval failed: ' . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Cache response
     */
    private function cacheResponse($key, $response, $ttl = 3600)
    {
        if ($this->cache) {
            try {
                $this->cache->setex($key, $ttl, json_encode($response));
            } catch (Exception $e) {
                error_log('Cache storage failed: ' . $e->getMessage());
            }
        }
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
     * Generate chat response for customer support with knowledge base integration
     */
    public function generateChatResponse(array $conversation, string $userMessage, array $visitorContext = [])
    {
        // Search knowledge base first
        $kbContext = $this->searchKnowledgeBaseForChat($userMessage);
        
        // Build enhanced system prompt with KB context
        $systemPrompt = $this->buildEnhancedSystemPrompt($kbContext, $visitorContext);
        
        // Build conversation context
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ]
        ];
        
        // Add conversation history (limited to last N messages)
        $contextLimit = $this->config['chatbot']['conversation']['context_messages'] ?? 10;
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
        $handoffKeywords = $this->config['chatbot']['conversation']['handoff_keywords'] ?? ['human', 'agent', 'representative', 'speak to someone'];
        foreach ($handoffKeywords as $keyword) {
            if (stripos($userMessage, $keyword) !== false) {
                return [
                    'response' => "I'd be happy to connect you with one of our support specialists who can better assist you. Please hold while I transfer you.",
                    'handoff_required' => true,
                    'confidence' => 1.0,
                    'intent' => 'handoff_request',
                    'kb_articles_used' => []
                ];
            }
        }
        
        try {
            // Detect intent and sentiment
            $messageAnalysis = $this->analyzeMessage($userMessage, $conversation);
            
            $response = $this->chatCompletion([
                'messages' => $messages,
                'temperature' => 0.7,
            ]);
            
            $responseContent = $response['choices'][0]['message']['content'];
            
            // Add suggested actions based on intent
            $suggestedActions = $this->generateSuggestedActions($messageAnalysis['intent'], $kbContext);
            
            return [
                'response' => $responseContent,
                'handoff_required' => false,
                'confidence' => $messageAnalysis['confidence'],
                'intent' => $messageAnalysis['intent'],
                'sentiment' => $messageAnalysis['sentiment'],
                'suggested_actions' => $suggestedActions,
                'kb_articles_used' => array_map(function($article) {
                    return [
                        'id' => $article['id'],
                        'title' => $article['title'],
                        'relevance' => $article['relevance']
                    ];
                }, array_slice($kbContext, 0, 3)),
                'metadata' => [
                    'response_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3),
                    'kb_search_performed' => !empty($kbContext),
                    'visitor_context_used' => !empty($visitorContext)
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Chat response generation failed: ' . $e->getMessage());
            
            return [
                'response' => "I apologize, but I'm having trouble processing your request. Would you like me to connect you with a human agent?",
                'handoff_required' => true,
                'confidence' => 0.0,
                'intent' => 'error',
                'kb_articles_used' => []
            ];
        }
    }
    
    /**
     * Search knowledge base for relevant articles
     */
    private function searchKnowledgeBaseForChat(string $query)
    {
        global $db;
        
        try {
            // First try keyword search
            $keywords = $this->extractKeywords($query);
            $articles = [];
            
            if (!empty($keywords)) {
                $keywordConditions = array_map(function($keyword) use ($db) {
                    $keyword = $db->quote('%' . $keyword . '%');
                    return "(title LIKE {$keyword} OR content LIKE {$keyword} OR tags LIKE {$keyword})";
                }, $keywords);
                
                $where = implode(' OR ', $keywordConditions);
                
                $query = "SELECT id, title, summary, content, tags, category 
                         FROM knowledge_base_articles 
                         WHERE is_published = 1 AND deleted = 0 AND ({$where})
                         ORDER BY view_count DESC, helpful_count DESC
                         LIMIT 5";
                
                $result = $db->query($query);
                while ($row = $db->fetchByAssoc($result)) {
                    $articles[] = $row;
                }
            }
            
            // If we have embeddings enabled, also do semantic search
            if ($this->config['knowledge_base']['use_embeddings'] ?? false) {
                $semanticResults = $this->semanticKnowledgeBaseSearch($query);
                
                // Merge results, avoiding duplicates
                $existingIds = array_column($articles, 'id');
                foreach ($semanticResults as $result) {
                    if (!in_array($result['id'], $existingIds)) {
                        $articles[] = $result;
                    }
                }
            }
            
            // Calculate relevance scores
            foreach ($articles as &$article) {
                $article['relevance'] = $this->calculateRelevance($query, $article);
            }
            
            // Sort by relevance
            usort($articles, function($a, $b) {
                return $b['relevance'] <=> $a['relevance'];
            });
            
            return array_slice($articles, 0, 3);
            
        } catch (Exception $e) {
            error_log('Knowledge base search failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Build enhanced system prompt with context
     */
    private function buildEnhancedSystemPrompt(array $kbArticles, array $visitorContext)
    {
        $prompt = "You are a helpful customer support agent for a B2B SaaS CRM platform. Be professional, friendly, and concise.\n\n";
        
        // Add knowledge base context
        if (!empty($kbArticles)) {
            $prompt .= "Here are relevant knowledge base articles to help answer the question:\n\n";
            foreach ($kbArticles as $article) {
                $prompt .= "Article: {$article['title']}\n";
                $prompt .= "Summary: {$article['summary']}\n";
                $prompt .= "Key Points: " . $this->extractKeyPoints($article['content']) . "\n\n";
            }
            $prompt .= "Use this information to provide accurate answers, but don't mention the articles directly unless asked.\n\n";
        }
        
        // Add visitor context
        if (!empty($visitorContext)) {
            $prompt .= "Visitor Context:\n";
            if (isset($visitorContext['lead_name'])) {
                $prompt .= "- Name: {$visitorContext['lead_name']}\n";
            }
            if (isset($visitorContext['company'])) {
                $prompt .= "- Company: {$visitorContext['company']}\n";
            }
            if (isset($visitorContext['pages_viewed'])) {
                $prompt .= "- Recently viewed: " . implode(', ', $visitorContext['pages_viewed']) . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Guidelines:\n";
        $prompt .= "- If asked about pricing or complex technical issues, suggest connecting with a human agent\n";
        $prompt .= "- Keep responses concise and actionable\n";
        $prompt .= "- Use the knowledge base information when relevant\n";
        $prompt .= "- Be empathetic and solution-focused";
        
        return $prompt;
    }
    
    /**
     * Extract keywords from query
     */
    private function extractKeywords(string $query)
    {
        // Remove common words
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'how', 'what', 'when', 'where', 'who', 'why'];
        
        $words = preg_split('/\s+/', strtolower($query));
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
        
        return array_values($keywords);
    }
    
    /**
     * Calculate relevance score
     */
    private function calculateRelevance(string $query, array $article)
    {
        $score = 0;
        $queryLower = strtolower($query);
        $titleLower = strtolower($article['title']);
        $contentLower = strtolower($article['content'] ?? '');
        
        // Title match (highest weight)
        if (strpos($titleLower, $queryLower) !== false) {
            $score += 50;
        }
        
        // Keyword matches
        $keywords = $this->extractKeywords($query);
        foreach ($keywords as $keyword) {
            if (strpos($titleLower, $keyword) !== false) {
                $score += 20;
            }
            if (strpos($contentLower, $keyword) !== false) {
                $score += 5;
            }
        }
        
        // Category relevance
        if (isset($article['category'])) {
            $categoryKeywords = ['integration', 'api', 'setup', 'billing', 'security'];
            foreach ($categoryKeywords as $catKey) {
                if (strpos($queryLower, $catKey) !== false && strpos(strtolower($article['category']), $catKey) !== false) {
                    $score += 15;
                }
            }
        }
        
        return $score;
    }
    
    /**
     * Extract key points from content
     */
    private function extractKeyPoints(string $content, int $maxLength = 200)
    {
        // Extract first paragraph or bullet points
        $lines = explode("\n", $content);
        $keyPoints = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for bullet points or numbered lists
            if (preg_match('/^[-*•]\s+(.+)/', $line, $matches) || preg_match('/^\d+\.\s+(.+)/', $line, $matches)) {
                $keyPoints[] = $matches[1];
                if (count($keyPoints) >= 3) break;
            }
        }
        
        if (empty($keyPoints)) {
            // If no bullet points, take first sentence
            $firstParagraph = $lines[0] ?? '';
            return substr($firstParagraph, 0, $maxLength) . (strlen($firstParagraph) > $maxLength ? '...' : '');
        }
        
        return implode('; ', array_slice($keyPoints, 0, 3));
    }
    
    /**
     * Analyze message for intent and sentiment
     */
    private function analyzeMessage(string $message, array $conversation)
    {
        $messageLower = strtolower($message);
        
        // Intent detection
        $intents = [
            'support' => ['help', 'issue', 'problem', 'error', 'not working', 'broken', 'fix'],
            'sales' => ['pricing', 'cost', 'plan', 'upgrade', 'features', 'demo', 'trial'],
            'general' => ['how to', 'what is', 'can i', 'where', 'when'],
            'qualification' => ['company', 'business', 'team', 'users', 'requirements'],
            'feedback' => ['feedback', 'suggestion', 'improve', 'feature request']
        ];
        
        $detectedIntent = 'general';
        $maxScore = 0;
        
        foreach ($intents as $intent => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($messageLower, $keyword) !== false) {
                    $score++;
                }
            }
            if ($score > $maxScore) {
                $maxScore = $score;
                $detectedIntent = $intent;
            }
        }
        
        // Sentiment analysis (simple version)
        $positiveWords = ['thank', 'great', 'excellent', 'perfect', 'wonderful', 'love', 'amazing'];
        $negativeWords = ['terrible', 'awful', 'hate', 'frustrated', 'angry', 'disappointed', 'worst'];
        
        $sentiment = 'neutral';
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                $negativeCount++;
            }
        }
        
        if ($positiveCount > $negativeCount) {
            $sentiment = 'positive';
        } elseif ($negativeCount > $positiveCount) {
            $sentiment = 'negative';
        }
        
        // Confidence based on keyword matches
        $confidence = min(0.9, 0.5 + ($maxScore * 0.1));
        
        return [
            'intent' => $detectedIntent,
            'sentiment' => $sentiment,
            'confidence' => $confidence
        ];
    }
    
    /**
     * Generate suggested actions based on intent
     */
    private function generateSuggestedActions(string $intent, array $kbArticles)
    {
        $actions = [];
        
        switch ($intent) {
            case 'support':
                $actions = [
                    'View troubleshooting guide',
                    'Contact support team',
                    'Check system status'
                ];
                break;
            case 'sales':
                $actions = [
                    'View pricing plans',
                    'Schedule a demo',
                    'Start free trial'
                ];
                break;
            case 'qualification':
                $actions = [
                    'Tell us about your company',
                    'What are your main requirements?',
                    'How many users do you have?'
                ];
                break;
        }
        
        // Add KB article suggestions
        foreach (array_slice($kbArticles, 0, 2) as $article) {
            $actions[] = "Read: " . substr($article['title'], 0, 40) . "...";
        }
        
        return array_slice($actions, 0, 4);
    }
    
    /**
     * Semantic knowledge base search using embeddings
     */
    private function semanticKnowledgeBaseSearch(string $query)
    {
        global $db;
        
        try {
            // Generate embedding for query
            $queryEmbedding = $this->generateEmbedding($query);
            
            // Get all article embeddings from cache or database
            $articles = [];
            $query = "SELECT id, title, summary, content, embedding_vector 
                     FROM knowledge_base_articles 
                     WHERE is_published = 1 AND deleted = 0 AND embedding_vector IS NOT NULL
                     LIMIT 100";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                if (!empty($row['embedding_vector'])) {
                    $embedding = json_decode($row['embedding_vector'], true);
                    if ($embedding) {
                        $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
                        if ($similarity >= 0.7) {
                            $row['relevance'] = $similarity * 100;
                            $articles[] = $row;
                        }
                    }
                }
            }
            
            // Sort by similarity
            usort($articles, function($a, $b) {
                return $b['relevance'] <=> $a['relevance'];
            });
            
            return array_slice($articles, 0, 5);
            
        } catch (Exception $e) {
            error_log('Semantic search failed: ' . $e->getMessage());
            return [];
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