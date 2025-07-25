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
}