<?php
/**
 * AI Controller - Handles AI-powered features
 * Phase 3 Implementation
 */

namespace Api\Controllers;

use Api\Controllers\BaseController;
use Api\Request;
use Api\Response;
use Exception;
use SuiteCRM\Custom\Services\OpenAIService;

class AIController extends BaseController
{
    private $openAIService;
    private $db;
    
    public function __construct()
    {
        // parent::__construct(); // BaseController has no constructor
        
        // Load AI configuration
        require_once __DIR__ . '/../../config/ai_config.php';
        
        // Initialize OpenAI service
        require_once __DIR__ . '/../../services/OpenAIService.php';
        $this->openAIService = new OpenAIService();
        
        global $db;
        $this->db = $db;
    }
    
    /**
     * Score a single lead using AI
     * POST /api/v8/leads/{id}/ai-score
     */
    public function scoreLead(Request $request, Response $response, array $args)
    {
        try {
            $leadId = $args['id'] ?? null;
            if (!$leadId) {
                return $response->withJson([
                    'error' => 'Lead ID is required'
                ], 400);
            }
            
            // Get lead data
            $leadData = $this->getLeadData($leadId);
            if (!$leadData) {
                return $response->withJson([
                    'error' => 'Lead not found'
                ], 404);
            }
            
            // Get activity data
            $activityData = $this->getLeadActivityData($leadId);
            
            // Score the lead using AI
            $scoreResult = $this->openAIService->scoreLead($leadData, $activityData);
            
            // Save the score
            $this->saveLeadScore($leadId, $scoreResult);
            
            // Trigger webhook if configured
            $this->triggerWebhook('lead_scored', [
                'lead_id' => $leadId,
                'score' => $scoreResult['score'],
                'previous_score' => $leadData['ai_score'] ?? 0,
            ]);
            
            return $response->withJson([
                'data' => [
                    'id' => $leadId,
                    'score' => $scoreResult['score'],
                    'previous_score' => $leadData['ai_score'] ?? 0,
                    'score_change' => $scoreResult['score'] - ($leadData['ai_score'] ?? 0),
                    'factors' => $scoreResult['factors'],
                    'insights' => $scoreResult['insights'],
                    'recommendations' => $scoreResult['recommendations'],
                    'confidence' => $scoreResult['confidence'],
                    'scored_at' => date('Y-m-d H:i:s'),
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Lead scoring error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to score lead',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Batch score multiple leads
     * POST /api/v8/leads/ai-score-batch
     */
    public function scoreLeadsBatch(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            $leadIds = $body['lead_ids'] ?? [];
            $limit = min(count($leadIds), 50); // Max 50 at a time
            
            if (empty($leadIds)) {
                return $response->withJson([
                    'error' => 'No lead IDs provided'
                ], 400);
            }
            
            $results = [];
            $errors = [];
            
            for ($i = 0; $i < $limit; $i++) {
                try {
                    $leadId = $leadIds[$i];
                    $leadData = $this->getLeadData($leadId);
                    
                    if (!$leadData) {
                        $errors[] = ['id' => $leadId, 'error' => 'Lead not found'];
                        continue;
                    }
                    
                    $activityData = $this->getLeadActivityData($leadId);
                    $scoreResult = $this->openAIService->scoreLead($leadData, $activityData);
                    $this->saveLeadScore($leadId, $scoreResult);
                    
                    $results[] = [
                        'id' => $leadId,
                        'score' => $scoreResult['score'],
                        'status' => 'success'
                    ];
                    
                } catch (Exception $e) {
                    $errors[] = [
                        'id' => $leadId,
                        'error' => $e->getMessage()
                    ];
                }
                
                // Add small delay to avoid rate limiting
                usleep(100000); // 0.1 seconds
            }
            
            return $response->withJson([
                'data' => [
                    'processed' => count($results),
                    'failed' => count($errors),
                    'results' => $results,
                    'errors' => $errors,
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Batch scoring error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Batch scoring failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get lead scoring history
     * GET /api/v8/leads/{id}/score-history
     */
    public function getScoreHistory(Request $request, Response $response, array $args)
    {
        try {
            $leadId = $args['id'] ?? null;
            if (!$leadId) {
                return $response->withJson([
                    'error' => 'Lead ID is required'
                ], 400);
            }
            
            $query = "SELECT * FROM ai_lead_scoring_history 
                     WHERE lead_id = ? 
                     ORDER BY date_scored DESC 
                     LIMIT 20";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId]);
            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($history as &$record) {
                $record['factors'] = json_decode($record['factors'], true);
                $record['insights'] = json_decode($record['insights'], true);
                $record['recommendations'] = json_decode($record['recommendations'], true);
            }
            
            return $response->withJson([
                'data' => $history
            ]);
            
        } catch (Exception $e) {
            error_log('Score history error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get score history',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Chat endpoint for AI chatbot
     * POST /api/v8/ai/chat
     */
    public function chat(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            $message = $body['message'] ?? '';
            $conversationId = $body['conversation_id'] ?? null;
            $visitorId = $body['visitor_id'] ?? null;
            
            if (empty($message)) {
                return $response->withJson([
                    'error' => 'Message is required'
                ], 400);
            }
            
            // Get or create conversation
            if (!$conversationId) {
                $conversationId = $this->createConversation($visitorId);
            }
            
            // Get conversation history
            $conversation = $this->getConversationHistory($conversationId);
            
            // Get visitor context for personalization
            $visitorContext = $this->getVisitorContext($visitorId);
            
            // Generate AI response with knowledge base integration
            $aiResponse = $this->openAIService->generateChatResponse($conversation, $message, $visitorContext);
            
            // Save messages with enhanced metadata
            $this->saveChatMessage($conversationId, 'user', $message);
            $this->saveChatMessage($conversationId, 'assistant', $aiResponse['response'], [
                'confidence' => $aiResponse['confidence'],
                'handoff_required' => $aiResponse['handoff_required'],
                'intent' => $aiResponse['intent'] ?? null,
                'sentiment' => $aiResponse['sentiment'] ?? null,
                'kb_articles_used' => $aiResponse['kb_articles_used'] ?? [],
            ]);
            
            // Handle handoff if needed
            if ($aiResponse['handoff_required']) {
                $this->initiateHandoff($conversationId);
            }
            
            // Check for lead capture opportunity with AI
            $leadScore = $this->assessLeadQuality($conversation, $aiResponse);
            if ($leadScore >= 60) {
                $leadInfo = $this->extractLeadInfoWithAI($conversationId, $conversation, $visitorContext);
                if ($leadInfo && $this->validateLeadInfo($leadInfo)) {
                    $leadId = $this->createLeadFromChat($conversationId, $leadInfo);
                    
                    // Update metadata with lead capture info
                    $aiResponse['metadata']['lead_captured'] = true;
                    $aiResponse['metadata']['lead_id'] = $leadId;
                    $aiResponse['metadata']['lead_score'] = $leadScore;
                    $aiResponse['metadata']['lead_info'] = $leadInfo;
                }
            }
            
            return $response->withJson([
                'data' => [
                    'conversation_id' => $conversationId,
                    'message' => $aiResponse['response'],
                    'handoff_required' => $aiResponse['handoff_required'],
                    'confidence' => $aiResponse['confidence'],
                    'intent' => $aiResponse['intent'] ?? null,
                    'sentiment' => $aiResponse['sentiment'] ?? null,
                    'suggested_actions' => $aiResponse['suggested_actions'] ?? [],
                    'metadata' => $aiResponse['metadata'] ?? null,
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Chat error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Chat service temporarily unavailable',
                'message' => 'Please try again later'
            ], 500);
        }
    }
    
    /**
     * Get chat conversation
     * GET /api/v8/ai/chat/{conversation_id}
     */
    public function getConversation(Request $request, Response $response, array $args)
    {
        try {
            $conversationId = $args['conversation_id'] ?? null;
            if (!$conversationId) {
                return $response->withJson([
                    'error' => 'Conversation ID is required'
                ], 400);
            }
            
            $conversation = $this->getConversationDetails($conversationId);
            if (!$conversation) {
                return $response->withJson([
                    'error' => 'Conversation not found'
                ], 404);
            }
            
            return $response->withJson([
                'data' => $conversation
            ]);
            
        } catch (Exception $e) {
            error_log('Get conversation error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get conversation',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function getLeadData($leadId)
    {
        $query = "SELECT l.*, a.name as account_name, a.industry, a.employees 
                 FROM leads l
                 LEFT JOIN accounts a ON l.account_id = a.id
                 WHERE l.id = ? AND l.deleted = 0";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$leadId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function getLeadActivityData($leadId)
    {
        // Get visitor activity
        $query = "SELECT * FROM activity_tracking_visitors WHERE lead_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$leadId]);
        $visitor = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$visitor) {
            return [];
        }
        
        // Get high-value page views
        $query = "SELECT page_url FROM activity_tracking_page_views 
                 WHERE visitor_id = ? AND is_high_value = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$visitor['visitor_id']]);
        $highValuePages = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Get form submissions
        $query = "SELECT COUNT(*) as count FROM form_builder_submissions 
                 WHERE lead_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$leadId]);
        $formSubmissions = $stmt->fetchColumn();
        
        return [
            'total_visits' => $visitor['total_visits'] ?? 0,
            'total_page_views' => $visitor['total_page_views'] ?? 0,
            'total_time_spent' => $visitor['total_time_spent'] ?? 0,
            'engagement_score' => $visitor['engagement_score'] ?? 0,
            'high_value_pages' => $highValuePages,
            'form_submissions' => $formSubmissions,
        ];
    }
    
    private function saveLeadScore($leadId, $scoreResult)
    {
        // Get previous score
        $query = "SELECT ai_score FROM leads WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$leadId]);
        $previousScore = $stmt->fetchColumn() ?: 0;
        
        // Update lead record
        $query = "UPDATE leads SET 
                 ai_score = ?, 
                 ai_score_date = NOW(),
                 ai_score_factors = ?
                 WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $scoreResult['score'],
            json_encode($scoreResult['factors']),
            $leadId
        ]);
        
        // Save to history
        $query = "INSERT INTO ai_lead_scoring_history 
                 (id, lead_id, score, previous_score, score_change, factors, insights, recommendations, model_version, date_scored)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $this->generateUUID(),
            $leadId,
            $scoreResult['score'],
            $previousScore,
            $scoreResult['score'] - $previousScore,
            json_encode($scoreResult['factors']),
            json_encode($scoreResult['insights']),
            json_encode($scoreResult['recommendations']),
            'gpt-4-turbo-preview'
        ]);
    }
    
    private function createConversation($visitorId)
    {
        $conversationId = $this->generateUUID();
        
        $query = "INSERT INTO ai_chat_conversations 
                 (id, visitor_id, status, start_time, message_count, date_created)
                 VALUES (?, ?, 'active', NOW(), 0, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$conversationId, $visitorId]);
        
        return $conversationId;
    }
    
    private function getConversationHistory($conversationId)
    {
        $query = "SELECT role, content FROM ai_chat_messages 
                 WHERE conversation_id = ? 
                 ORDER BY date_created ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function saveChatMessage($conversationId, $role, $content, $metadata = [])
    {
        $query = "INSERT INTO ai_chat_messages 
                 (id, conversation_id, role, content, metadata, date_created)
                 VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $this->generateUUID(),
            $conversationId,
            $role,
            $content,
            json_encode($metadata)
        ]);
        
        // Update message count
        $query = "UPDATE ai_chat_conversations 
                 SET message_count = message_count + 1,
                     date_modified = NOW()
                 WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$conversationId]);
    }
    
    private function getConversationDetails($conversationId)
    {
        // Get conversation
        $query = "SELECT * FROM ai_chat_conversations WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            return null;
        }
        
        // Get messages
        $query = "SELECT * FROM ai_chat_messages 
                 WHERE conversation_id = ? 
                 ORDER BY date_created ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Parse metadata
        foreach ($messages as &$message) {
            $message['metadata'] = json_decode($message['metadata'], true);
        }
        
        $conversation['messages'] = $messages;
        return $conversation;
    }
    
    private function initiateHandoff($conversationId)
    {
        // Update conversation status
        $query = "UPDATE ai_chat_conversations 
                 SET status = 'handoff'
                 WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$conversationId]);
        
        // TODO: Notify available agents
        // This would integrate with your notification system
    }
    
    private function extractLeadInfoWithAI($conversationId, $conversation, $visitorContext)
    {
        try {
            // Build conversation text for analysis
            $conversationText = $this->buildConversationText($conversation);
            
            // Use AI to extract lead information
            $prompt = "Extract lead information from this chat conversation. Return a JSON object with these fields (if found):
- email: email address
- first_name: first name
- last_name: last name  
- company: company name
- phone: phone number
- title: job title
- industry: industry
- company_size: number of employees or company size category
- budget: budget range mentioned
- timeline: implementation timeline
- pain_points: array of main challenges/pain points mentioned
- requirements: array of specific requirements mentioned

If information is not explicitly stated, leave the field empty. Only extract what was actually mentioned.

Conversation:
{$conversationText}";

            $response = $this->openAIService->chatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a lead extraction expert. Extract only explicitly mentioned information.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);
            
            $extractedInfo = json_decode($response['choices'][0]['message']['content'], true);
            
            // Merge with visitor context
            if (!empty($visitorContext['lead_name']) && empty($extractedInfo['first_name'])) {
                $nameParts = explode(' ', $visitorContext['lead_name'], 2);
                $extractedInfo['first_name'] = $nameParts[0];
                if (isset($nameParts[1])) {
                    $extractedInfo['last_name'] = $nameParts[1];
                }
            }
            
            if (!empty($visitorContext['company']) && empty($extractedInfo['company'])) {
                $extractedInfo['company'] = $visitorContext['company'];
            }
            
            // Add metadata
            $extractedInfo['lead_source'] = 'AI Chat';
            $extractedInfo['conversation_id'] = $conversationId;
            $extractedInfo['extraction_confidence'] = $this->calculateExtractionConfidence($extractedInfo);
            
            return $extractedInfo;
            
        } catch (Exception $e) {
            error_log('Lead extraction failed: ' . $e->getMessage());
            
            // Fallback to simple extraction
            return $this->simpleLeadExtraction($conversation);
        }
    }
    
    private function simpleLeadExtraction($conversation)
    {
        $leadInfo = [];
        
        // Combine all messages
        $allText = '';
        foreach ($conversation as $msg) {
            $allText .= $msg['content'] . ' ';
        }
        
        // Email extraction
        $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        if (preg_match($emailPattern, $allText, $matches)) {
            $leadInfo['email'] = $matches[0];
        }
        
        // Phone extraction
        $phonePattern = '/(?:\+?1[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})/';
        if (preg_match($phonePattern, $allText, $matches)) {
            $leadInfo['phone'] = $matches[0];
        }
        
        // Company extraction (look for common patterns)
        if (preg_match('/(?:company|work at|from|representing)\s+(?:is\s+)?([A-Z][A-Za-z\s&]+)(?:\.|,|\s|$)/i', $allText, $matches)) {
            $leadInfo['company'] = trim($matches[1]);
        }
        
        return empty($leadInfo) ? null : $leadInfo;
    }
    
    private function buildConversationText($conversation)
    {
        $text = '';
        foreach ($conversation as $msg) {
            $role = $msg['role'] === 'assistant' ? 'Agent' : 'Visitor';
            $text .= "{$role}: {$msg['content']}\n";
        }
        return $text;
    }
    
    private function calculateExtractionConfidence($leadInfo)
    {
        $score = 0;
        $weights = [
            'email' => 30,
            'first_name' => 15,
            'last_name' => 15,
            'company' => 20,
            'phone' => 10,
            'requirements' => 10
        ];
        
        foreach ($weights as $field => $weight) {
            if (!empty($leadInfo[$field])) {
                $score += $weight;
            }
        }
        
        return min(100, $score);
    }
    
    private function validateLeadInfo($leadInfo)
    {
        // Must have at least email or phone
        return !empty($leadInfo['email']) || !empty($leadInfo['phone']);
    }
    
    private function assessLeadQuality($conversation, $aiResponse)
    {
        // Quick assessment based on conversation signals
        $score = 50; // Base score
        
        // Intent-based scoring
        if ($aiResponse['intent'] === 'sales' || $aiResponse['intent'] === 'qualification') {
            $score += 20;
        }
        
        // Conversation depth
        $messageCount = count($conversation);
        if ($messageCount >= 4) $score += 10;
        if ($messageCount >= 8) $score += 10;
        
        // Check for buying signals
        $buyingSignals = ['pricing', 'cost', 'demo', 'trial', 'implementation', 'timeline', 'budget', 'purchase', 'requirements'];
        $conversationText = strtolower($this->buildConversationText($conversation));
        
        foreach ($buyingSignals as $signal) {
            if (strpos($conversationText, $signal) !== false) {
                $score += 5;
            }
        }
        
        // Contact info provided
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $conversationText)) {
            $score += 15;
        }
        
        return min(100, $score);
    }
    
    private function createLeadFromChat($conversationId, $leadInfo)
    {
        // Check if lead already exists
        $existingLeadId = null;
        if (!empty($leadInfo['email'])) {
            $query = "SELECT id FROM leads WHERE email = ? AND deleted = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadInfo['email']]);
            $existingLeadId = $stmt->fetchColumn();
        }
        
        if ($existingLeadId) {
            // Update existing lead with new information
            $this->updateLeadFromChat($existingLeadId, $leadInfo);
            $leadId = $existingLeadId;
        } else {
            // Create new lead with all extracted information
            $leadId = $this->generateUUID();
            
            // Build insert query with all available fields
            $fields = ['id', 'lead_source', 'status', 'date_entered', 'date_modified', 'assigned_user_id'];
            $values = [$leadId, 'AI Chat', 'New', 'NOW()', 'NOW()', '1'];
            $placeholders = ['?', '?', '?', 'NOW()', 'NOW()', '?'];
            
            // Add optional fields
            $fieldMapping = [
                'email' => 'email',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'company' => 'account_name',
                'phone' => 'phone_work',
                'title' => 'title',
                'industry' => 'industry',
                'company_size' => 'employees',
                'description' => 'description'
            ];
            
            foreach ($fieldMapping as $leadInfoKey => $dbField) {
                if (!empty($leadInfo[$leadInfoKey])) {
                    $fields[] = $dbField;
                    $placeholders[] = '?';
                    $values[] = $leadInfo[$leadInfoKey];
                }
            }
            
            // Build description with extracted info
            $description = "Lead captured from AI Chat\n";
            $description .= "Conversation ID: {$conversationId}\n";
            $description .= "Capture Date: " . date('Y-m-d H:i:s') . "\n";
            $description .= "Extraction Confidence: " . ($leadInfo['extraction_confidence'] ?? 'N/A') . "%\n\n";
            
            if (!empty($leadInfo['pain_points'])) {
                $description .= "Pain Points:\n";
                foreach ((array)$leadInfo['pain_points'] as $point) {
                    $description .= "- {$point}\n";
                }
                $description .= "\n";
            }
            
            if (!empty($leadInfo['requirements'])) {
                $description .= "Requirements:\n";
                foreach ((array)$leadInfo['requirements'] as $req) {
                    $description .= "- {$req}\n";
                }
                $description .= "\n";
            }
            
            if (!empty($leadInfo['budget'])) {
                $description .= "Budget: {$leadInfo['budget']}\n";
            }
            
            if (!empty($leadInfo['timeline'])) {
                $description .= "Timeline: {$leadInfo['timeline']}\n";
            }
            
            // Add description if not already set
            if (empty($leadInfo['description'])) {
                $fields[] = 'description';
                $placeholders[] = '?';
                $values[] = $description;
            }
            
            // Execute insert
            $query = "INSERT INTO leads (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($query);
            
            // Replace NOW() placeholders
            $finalValues = [];
            foreach ($values as $value) {
                if ($value !== 'NOW()') {
                    $finalValues[] = $value;
                }
            }
            
            $stmt->execute($finalValues);
            
            // Add to campaign if configured
            $this->addLeadToCampaign($leadId, 'AI Chat Leads');
            
            // Create initial activity
            $this->createLeadActivity($leadId, 'Lead captured from AI Chat conversation');
        }
        
        // Link conversation to lead
        $query = "UPDATE ai_chat_conversations SET lead_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$leadId, $conversationId]);
        
        // Update visitor with lead ID
        if (!empty($leadInfo['visitor_id'])) {
            $query = "UPDATE activity_tracking_visitors SET lead_id = ? WHERE visitor_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId, $leadInfo['visitor_id']]);
        }
        
        return $leadId;
    }
    
    private function updateLeadFromChat($leadId, $leadInfo)
    {
        // Update only fields that are empty in the existing lead
        $updates = [];
        $values = [];
        
        $fieldMapping = [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'company' => 'account_name',
            'phone' => 'phone_work',
            'title' => 'title',
            'industry' => 'industry',
            'company_size' => 'employees'
        ];
        
        foreach ($fieldMapping as $leadInfoKey => $dbField) {
            if (!empty($leadInfo[$leadInfoKey])) {
                // Check if field is empty in existing lead
                $query = "SELECT {$dbField} FROM leads WHERE id = ? AND ({$dbField} IS NULL OR {$dbField} = '')";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$leadId]);
                
                if ($stmt->fetchColumn() !== false) {
                    $updates[] = "{$dbField} = ?";
                    $values[] = $leadInfo[$leadInfoKey];
                }
            }
        }
        
        if (!empty($updates)) {
            $values[] = $leadId;
            $query = "UPDATE leads SET " . implode(', ', $updates) . ", date_modified = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute($values);
        }
        
        // Always add conversation notes
        $this->createLeadActivity($leadId, 'Additional information captured from AI Chat conversation');
    }
    
    private function addLeadToCampaign($leadId, $campaignName)
    {
        // Check if campaign exists
        $query = "SELECT id FROM campaigns WHERE name = ? AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$campaignName]);
        $campaignId = $stmt->fetchColumn();
        
        if (!$campaignId) {
            // Create campaign if it doesn't exist
            $campaignId = $this->generateUUID();
            $query = "INSERT INTO campaigns (id, name, campaign_type, status, date_entered, date_modified)
                     VALUES (?, ?, 'Email', 'Active', NOW(), NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$campaignId, $campaignName]);
        }
        
        // Add lead to campaign
        $query = "INSERT IGNORE INTO campaign_leads (campaign_id, lead_id, date_created)
                 VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$campaignId, $leadId]);
    }
    
    private function createLeadActivity($leadId, $description)
    {
        $activityId = $this->generateUUID();
        $query = "INSERT INTO activities 
                 (id, lead_id, activity_type, description, date_created, created_by)
                 VALUES (?, ?, 'Note', ?, NOW(), '1')";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$activityId, $leadId, $description]);
    }
    
    private function triggerWebhook($event, $data)
    {
        global $sugar_config;
        $webhookUrl = $sugar_config['ai']['webhooks'][$event] ?? '';
        
        if (empty($webhookUrl)) {
            return;
        }
        
        // Queue webhook for async processing
        $query = "INSERT INTO webhook_events 
                 (id, event_type, payload, webhook_url, status, date_created)
                 VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $this->generateUUID(),
            $event,
            json_encode($data),
            $webhookUrl
        ]);
    }
    
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function getVisitorContext($visitorId)
    {
        if (!$visitorId) {
            return [];
        }
        
        $context = [];
        
        // Get visitor info
        $query = "SELECT v.*, l.first_name, l.last_name, l.account_name as company
                 FROM activity_tracking_visitors v
                 LEFT JOIN leads l ON v.lead_id = l.id
                 WHERE v.visitor_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$visitorId]);
        $visitor = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($visitor) {
            // Add lead info if available
            if ($visitor['first_name'] || $visitor['last_name']) {
                $context['lead_name'] = trim($visitor['first_name'] . ' ' . $visitor['last_name']);
            }
            if ($visitor['company']) {
                $context['company'] = $visitor['company'];
            }
            
            // Get recent page views
            $query = "SELECT DISTINCT page_url, page_title 
                     FROM activity_tracking_page_views 
                     WHERE visitor_id = ? 
                     ORDER BY timestamp DESC 
                     LIMIT 5";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitorId]);
            $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if ($pages) {
                $context['pages_viewed'] = array_map(function($page) {
                    return $page['page_title'] ?: $page['page_url'];
                }, $pages);
            }
            
            // Add engagement metrics
            $context['total_visits'] = $visitor['total_visits'] ?? 0;
            $context['engagement_score'] = $visitor['engagement_score'] ?? 0;
        }
        
        return $context;
    }
    
    /**
     * Start a new AI chat conversation
     * POST /api/ai/chat/start
     */
    public function startConversation(Request $request) {
        try {
            $data = $this->getRequestData();
            $contactId = $data['contactId'] ?? null;
            
            // Create new conversation
            $conversationId = $this->generateUUID();
            
            $query = "INSERT INTO ai_chat_conversations 
                     (id, contact_id, status, started_at, created_by, date_entered, date_modified, deleted)
                     VALUES (?, ?, 'active', NOW(), ?, NOW(), NOW(), 0)";
            
            global $db;
            $stmt = $db->prepare($query);
            $stmt->execute([
                $conversationId,
                $contactId,
                $this->getCurrentUserId() ?: 'system'
            ]);
            
            return $this->success([
                'conversationId' => $conversationId,
                'status' => 'active'
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to start conversation: ' . $e->getMessage());
        }
    }
    
    /**
     * Send a message in an AI chat conversation
     * POST /api/ai/chat/message
     */
    public function sendMessage(Request $request) {
        try {
            $data = $this->getRequestData();
            $conversationId = $data['conversationId'] ?? null;
            $message = $data['message'] ?? '';
            
            if (!$conversationId || !$message) {
                return $this->error('Conversation ID and message are required');
            }
            
            // Store user message
            $userMessageId = $this->generateUUID();
            $query = "INSERT INTO ai_chat_messages 
                     (id, conversation_id, role, content, created_at, deleted)
                     VALUES (?, ?, 'user', ?, NOW(), 0)";
            
            global $db;
            $stmt = $db->prepare($query);
            $stmt->execute([$userMessageId, $conversationId, $message]);
            
            // Get conversation context
            $context = $this->getChatContext($conversationId);
            
            // Generate AI response
            $aiResponse = $this->generateAIResponse($message, $context);
            
            // Store AI response
            $aiMessageId = $this->generateUUID();
            $query = "INSERT INTO ai_chat_messages 
                     (id, conversation_id, role, content, metadata, created_at, deleted)
                     VALUES (?, ?, 'assistant', ?, ?, NOW(), 0)";
            
            $metadata = json_encode([
                'sentiment' => $aiResponse['sentiment'] ?? 'neutral',
                'confidence' => $aiResponse['confidence'] ?? 0.95,
                'intent' => $aiResponse['intent'] ?? 'general'
            ]);
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $aiMessageId,
                $conversationId,
                $aiResponse['message'],
                $metadata
            ]);
            
            // Handle special intents
            if (isset($aiResponse['intent'])) {
                switch ($aiResponse['intent']) {
                    case 'create_ticket':
                        $this->handleTicketCreation($conversationId, $aiResponse);
                        break;
                    case 'schedule_demo':
                        $this->handleDemoScheduling($conversationId, $aiResponse);
                        break;
                    case 'knowledge_query':
                        $this->handleKnowledgeQuery($conversationId, $aiResponse);
                        break;
                }
            }
            
            return $this->success([
                'response' => $aiResponse['message'],
                'metadata' => [
                    'sentiment' => $aiResponse['sentiment'] ?? 'neutral',
                    'confidence' => $aiResponse['confidence'] ?? 0.95,
                    'intent' => $aiResponse['intent'] ?? 'general'
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to send message: ' . $e->getMessage());
        }
    }
    
    /**
     * Get chat history for a contact
     * GET /api/ai/chat/history/{contact_id}
     */
    public function getChatHistory(Request $request) {
        try {
            $contactId = $request->getParam('contact_id');
            
            if (!$contactId) {
                return $this->error('Contact ID is required');
            }
            
            global $db;
            $query = "SELECT c.*, 
                      (SELECT COUNT(*) FROM ai_chat_messages WHERE conversation_id = c.id) as message_count
                      FROM ai_chat_conversations c
                      WHERE c.contact_id = ? AND c.deleted = 0
                      ORDER BY c.started_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$contactId]);
            $conversations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $history = [];
            foreach ($conversations as $conv) {
                // Get messages for this conversation
                $msgQuery = "SELECT * FROM ai_chat_messages 
                            WHERE conversation_id = ? AND deleted = 0
                            ORDER BY created_at ASC";
                
                $msgStmt = $db->prepare($msgQuery);
                $msgStmt->execute([$conv['id']]);
                $messages = $msgStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $history[] = [
                    'conversationId' => $conv['id'],
                    'status' => $conv['status'],
                    'startedAt' => $conv['started_at'],
                    'endedAt' => $conv['ended_at'],
                    'messageCount' => $conv['message_count'],
                    'messages' => array_map(function($msg) {
                        return [
                            'id' => $msg['id'],
                            'role' => $msg['role'],
                            'content' => $msg['content'],
                            'metadata' => json_decode($msg['metadata'], true),
                            'createdAt' => $msg['created_at']
                        ];
                    }, $messages)
                ];
            }
            
            return $this->success([
                'contactId' => $contactId,
                'conversations' => $history
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to get chat history: ' . $e->getMessage());
        }
    }
    
    /**
     * Create a support ticket from chat
     * POST /api/ai/chat/create-ticket
     */
    public function createTicketFromChat(Request $request) {
        try {
            $data = $this->getRequestData();
            $conversationId = $data['conversationId'] ?? null;
            $summary = $data['summary'] ?? '';
            $priority = $data['priority'] ?? 'Medium';
            
            if (!$conversationId) {
                return $this->error('Conversation ID is required');
            }
            
            // Get conversation details
            global $db;
            $query = "SELECT * FROM ai_chat_conversations WHERE id = ? AND deleted = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                return $this->error('Conversation not found');
            }
            
            // Create case (support ticket)
            $caseId = $this->generateUUID();
            $caseNumber = $this->generateCaseNumber();
            
            $query = "INSERT INTO cases 
                     (id, case_number, name, status, priority, type, description, assigned_user_id, 
                      date_entered, date_modified, created_by, modified_user_id, deleted)
                     VALUES (?, ?, ?, 'Open', ?, 'Technical', ?, ?, NOW(), NOW(), ?, ?, 0)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $caseId,
                $caseNumber,
                $summary ?: 'Support request from AI chat',
                $priority,
                $this->getConversationTranscript($conversationId),
                $this->getCurrentUserId() ?: '1',
                $this->getCurrentUserId() ?: '1',
                $this->getCurrentUserId() ?: '1'
            ]);
            
            // Link to contact if available
            if ($conversation['contact_id']) {
                $query = "INSERT INTO contacts_cases (id, contact_id, case_id, date_modified, deleted)
                         VALUES (?, ?, ?, NOW(), 0)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $this->generateUUID(),
                    $conversation['contact_id'],
                    $caseId
                ]);
            }
            
            // Update conversation metadata
            $query = "UPDATE ai_chat_conversations 
                     SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.ticket_id', ?)
                     WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$caseId, $conversationId]);
            
            return $this->success([
                'ticketId' => $caseId,
                'ticketNumber' => $caseNumber,
                'message' => 'Support ticket created successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to create ticket: ' . $e->getMessage());
        }
    }
    
    /**
     * Schedule a demo from chat
     * POST /api/ai/chat/schedule-demo
     */
    public function scheduleDemoFromChat(Request $request) {
        try {
            $data = $this->getRequestData();
            $conversationId = $data['conversationId'] ?? null;
            $requestedDate = $data['requestedDate'] ?? null;
            $requestedTime = $data['requestedTime'] ?? null;
            $notes = $data['notes'] ?? '';
            
            if (!$conversationId) {
                return $this->error('Conversation ID is required');
            }
            
            // Get conversation details
            global $db;
            $query = "SELECT * FROM ai_chat_conversations WHERE id = ? AND deleted = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                return $this->error('Conversation not found');
            }
            
            // Create meeting
            $meetingId = $this->generateUUID();
            $dateStart = $requestedDate && $requestedTime 
                ? $requestedDate . ' ' . $requestedTime 
                : date('Y-m-d H:i:s', strtotime('+2 days 10:00:00'));
            
            $query = "INSERT INTO meetings 
                     (id, name, status, location, duration_hours, duration_minutes, 
                      date_start, description, assigned_user_id, 
                      date_entered, date_modified, created_by, modified_user_id, deleted)
                     VALUES (?, ?, 'Planned', 'Online Demo', 1, 0, ?, ?, ?, 
                             NOW(), NOW(), ?, ?, 0)";
            
            $description = "Demo requested via AI chat\n\n" . 
                          "Notes: " . $notes . "\n\n" .
                          "Chat transcript:\n" . $this->getConversationTranscript($conversationId);
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $meetingId,
                'Product Demo - AI Chat Request',
                $dateStart,
                $description,
                $this->getCurrentUserId() ?: '1',
                $this->getCurrentUserId() ?: '1',
                $this->getCurrentUserId() ?: '1'
            ]);
            
            // Link to contact if available
            if ($conversation['contact_id']) {
                $query = "INSERT INTO meetings_contacts (id, meeting_id, contact_id, date_modified, deleted)
                         VALUES (?, ?, ?, NOW(), 0)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $this->generateUUID(),
                    $meetingId,
                    $conversation['contact_id']
                ]);
            }
            
            // Update conversation metadata
            $query = "UPDATE ai_chat_conversations 
                     SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.demo_id', ?)
                     WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$meetingId, $conversationId]);
            
            // TODO: Send calendar invite
            
            return $this->success([
                'demoId' => $meetingId,
                'scheduledDate' => $dateStart,
                'message' => 'Demo scheduled successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to schedule demo: ' . $e->getMessage());
        }
    }
    
    private function getChatContext($conversationId) {
        global $db;
        
        // Get recent messages
        $query = "SELECT role, content FROM ai_chat_messages 
                  WHERE conversation_id = ? AND deleted = 0
                  ORDER BY created_at DESC LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$conversationId]);
        $messages = array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
        
        // Get conversation metadata
        $query = "SELECT c.*, 
                  cont.first_name, cont.last_name, cont.account_name
                  FROM ai_chat_conversations c
                  LEFT JOIN contacts cont ON c.contact_id = cont.id
                  WHERE c.id = ? AND c.deleted = 0";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'messages' => $messages,
            'contact' => [
                'name' => trim(($conversation['first_name'] ?? '') . ' ' . ($conversation['last_name'] ?? '')),
                'company' => $conversation['account_name'] ?? ''
            ]
        ];
    }
    
    private function generateAIResponse($message, $context) {
        // Simple intent detection
        $intent = 'general';
        $sentiment = 'neutral';
        $confidence = 0.95;
        
        $messageLower = strtolower($message);
        
        // Detect intent
        if (strpos($messageLower, 'help') !== false || strpos($messageLower, 'support') !== false || 
            strpos($messageLower, 'issue') !== false || strpos($messageLower, 'problem') !== false) {
            $intent = 'support';
        } elseif (strpos($messageLower, 'demo') !== false || strpos($messageLower, 'trial') !== false ||
                  strpos($messageLower, 'meeting') !== false || strpos($messageLower, 'schedule') !== false) {
            $intent = 'schedule_demo';
        } elseif (strpos($messageLower, 'price') !== false || strpos($messageLower, 'pricing') !== false ||
                  strpos($messageLower, 'cost') !== false) {
            $intent = 'pricing';
        } elseif (strpos($messageLower, 'feature') !== false || strpos($messageLower, 'how') !== false ||
                  strpos($messageLower, 'what') !== false) {
            $intent = 'knowledge_query';
        }
        
        // Generate response based on intent
        switch ($intent) {
            case 'support':
                $response = "I understand you're experiencing an issue. I can help you create a support ticket. " .
                           "Could you please describe the problem you're facing in more detail?";
                break;
                
            case 'schedule_demo':
                $response = "I'd be happy to help you schedule a demo! Our team can show you all the features " .
                           "and answer any questions. When would be a good time for you?";
                break;
                
            case 'pricing':
                $response = "Our pricing is based on the number of users and features you need. " .
                           "For detailed pricing information, I can have our sales team contact you. " .
                           "Would you like me to schedule a call?";
                break;
                
            case 'knowledge_query':
                // Search knowledge base
                $kbResults = $this->searchKnowledgeBase($message);
                if (!empty($kbResults)) {
                    $response = "I found some helpful information in our knowledge base:\n\n" .
                               $kbResults[0]['title'] . "\n" . 
                               substr($kbResults[0]['content'], 0, 200) . "...\n\n" .
                               "Would you like me to send you the full article?";
                } else {
                    $response = "I'd be happy to help you learn more about our features. " .
                               "Could you be more specific about what you'd like to know?";
                }
                break;
                
            default:
                $response = "Thank you for your message. How can I assist you today? " .
                           "I can help with product information, scheduling demos, or technical support.";
        }
        
        return [
            'message' => $response,
            'intent' => $intent,
            'sentiment' => $sentiment,
            'confidence' => $confidence
        ];
    }
    
    private function handleTicketCreation($conversationId, $aiResponse) {
        // Auto-create ticket if high confidence support intent
        if ($aiResponse['intent'] === 'support' && $aiResponse['confidence'] > 0.8) {
            // This would be called via the createTicketFromChat endpoint
        }
    }
    
    private function handleDemoScheduling($conversationId, $aiResponse) {
        // Auto-schedule demo if high confidence demo intent
        if ($aiResponse['intent'] === 'schedule_demo' && $aiResponse['confidence'] > 0.8) {
            // This would be called via the scheduleDemoFromChat endpoint
        }
    }
    
    private function handleKnowledgeQuery($conversationId, $aiResponse) {
        // Already handled in generateAIResponse
    }
    
    private function searchKnowledgeBase($query) {
        global $db;
        
        $searchTerm = '%' . $query . '%';
        $query = "SELECT id, name as title, description as content 
                  FROM aok_knowledgebase 
                  WHERE (name LIKE ? OR description LIKE ?) 
                  AND status = 'published' AND deleted = 0
                  LIMIT 5";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$searchTerm, $searchTerm]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function generateCaseNumber() {
        global $db;
        
        // Get the last case number
        $query = "SELECT case_number FROM cases 
                  WHERE case_number LIKE 'CASE-%' 
                  ORDER BY case_number DESC LIMIT 1";
        
        $result = $db->query($query);
        $lastCase = $db->fetchByAssoc($result);
        
        if ($lastCase && preg_match('/CASE-(\d+)/', $lastCase['case_number'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return sprintf('CASE-%03d', $nextNumber);
    }
    
    private function getConversationTranscript($conversationId) {
        global $db;
        
        $query = "SELECT role, content, created_at 
                  FROM ai_chat_messages 
                  WHERE conversation_id = ? AND deleted = 0
                  ORDER BY created_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $transcript = "";
        foreach ($messages as $msg) {
            $timestamp = date('Y-m-d H:i:s', strtotime($msg['created_at']));
            $role = ucfirst($msg['role']);
            $transcript .= "[$timestamp] $role: {$msg['content']}\n";
        }
        
        return $transcript;
    }
}