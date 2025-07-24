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
        parent::__construct();
        
        // Load AI configuration
        require_once __DIR__ . '/../../suitecrm-custom/config/ai_config.php';
        
        // Initialize OpenAI service
        require_once __DIR__ . '/../../suitecrm-custom/services/OpenAIService.php';
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
            
            // Generate AI response
            $aiResponse = $this->openAIService->generateChatResponse($conversation, $message);
            
            // Save messages
            $this->saveChatMessage($conversationId, 'user', $message);
            $this->saveChatMessage($conversationId, 'assistant', $aiResponse['response'], [
                'confidence' => $aiResponse['confidence'],
                'handoff_required' => $aiResponse['handoff_required'],
            ]);
            
            // Handle handoff if needed
            if ($aiResponse['handoff_required']) {
                $this->initiateHandoff($conversationId);
            }
            
            // Check for lead capture opportunity
            $leadInfo = $this->extractLeadInfo($message, $conversation);
            if ($leadInfo) {
                $this->createLeadFromChat($conversationId, $leadInfo);
            }
            
            return $response->withJson([
                'data' => [
                    'conversation_id' => $conversationId,
                    'message' => $aiResponse['response'],
                    'handoff_required' => $aiResponse['handoff_required'],
                    'confidence' => $aiResponse['confidence'],
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
    
    private function extractLeadInfo($message, $conversation)
    {
        // Simple email extraction
        $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        if (preg_match($emailPattern, $message, $matches)) {
            return ['email' => $matches[0]];
        }
        
        // TODO: More sophisticated lead extraction using AI
        return null;
    }
    
    private function createLeadFromChat($conversationId, $leadInfo)
    {
        // Check if lead already exists
        $query = "SELECT id FROM leads WHERE email = ? AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$leadInfo['email']]);
        $existingLead = $stmt->fetchColumn();
        
        if (!$existingLead) {
            // Create new lead
            $leadId = $this->generateUUID();
            
            $query = "INSERT INTO leads 
                     (id, email, lead_source, status, date_entered, date_modified)
                     VALUES (?, ?, 'Chat', 'New', NOW(), NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId, $leadInfo['email']]);
            
            // Link to conversation
            $query = "UPDATE ai_chat_conversations SET lead_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId, $conversationId]);
        }
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
}