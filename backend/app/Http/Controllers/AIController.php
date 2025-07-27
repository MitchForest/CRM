<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadScore;
use App\Models\ActivityTrackingVisitor;
use App\Models\FormSubmission;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\Case;
use App\Models\Meeting;
use App\Services\AI\OpenAIService;
use App\Services\AI\LeadScoringService;
use App\Services\AI\ChatbotService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Str;

class AIController extends Controller
{
    private OpenAIService $openAIService;
    private LeadScoringService $leadScoringService;
    private ChatbotService $chatbotService;
    
    public function __construct()
    {
        parent::__construct();
        // Manual instantiation for Slim (no automatic DI)
        $this->openAIService = new OpenAIService();
        $this->leadScoringService = new LeadScoringService($this->openAIService);
        $this->chatbotService = new ChatbotService($this->openAIService);
    }
    
    /**
     * Score a single lead using AI
     * POST /api/v8/leads/{id}/ai-score
     */
    public function scoreLead(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::with(['sessions', 'formSubmissions'])->find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        try {
            // Get lead data and activity
            $leadData = $lead->toArray();
            $activityData = $this->getLeadActivityData($lead);
            
            // Score the lead
            $scoreResult = $this->leadScoringService->scoreLead($leadData, $activityData);
            
            // Save the score
            $leadScore = LeadScore::create([
                'lead_id' => $lead->id,
                'score' => $scoreResult['score'],
                'previous_score' => $lead->latest_score ?? 0,
                'score_change' => $scoreResult['score'] - ($lead->latest_score ?? 0),
                'factors' => $scoreResult['factors'],
                'insights' => $scoreResult['insights'],
                'recommendations' => $scoreResult['recommendations'],
                'confidence' => $scoreResult['confidence'],
                'model_version' => 'gpt-4-turbo-preview',
                'scored_at' => new \DateTime()
            ]);
            
            // Trigger webhook if configured
            $this->triggerWebhook('lead_scored', [
                'lead_id' => $lead->id,
                'score' => $scoreResult['score'],
                'previous_score' => $lead->latest_score ?? 0,
            ]);
            
            return $this->json($response, [
                'data' => [
                    'id' => $lead->id,
                    'score' => $scoreResult['score'],
                    'previous_score' => $lead->latest_score ?? 0,
                    'score_change' => $scoreResult['score'] - ($lead->latest_score ?? 0),
                    'factors' => $scoreResult['factors'],
                    'insights' => $scoreResult['insights'],
                    'recommendations' => $scoreResult['recommendations'],
                    'confidence' => $scoreResult['confidence'],
                    'scored_at' => (new \DateTime())->format('c'),
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log('Lead scoring error: ' . $e->getMessage());
            return $this->error($response, 'Failed to score lead: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Batch score multiple leads
     * POST /api/v8/leads/ai-score-batch
     */
    public function scoreLeadsBatch(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'lead_ids' => 'required|array|max:50',
            'lead_ids.*' => 'string|exists:leads,id'
        ]);
        
        $leadIds = $data['lead_ids'];
        $results = [];
        $errors = [];
        
        foreach ($leadIds as $leadId) {
            try {
                $lead = Lead::with(['sessions', 'formSubmissions'])->find($leadId);
                
                if (!$lead) {
                    $errors[] = ['id' => $leadId, 'error' => 'Lead not found'];
                    continue;
                }
                
                $leadData = $lead->toArray();
                $activityData = $this->getLeadActivityData($lead);
                $scoreResult = $this->leadScoringService->scoreLead($leadData, $activityData);
                
                LeadScore::create([
                    'lead_id' => $lead->id,
                    'score' => $scoreResult['score'],
                    'previous_score' => $lead->latest_score ?? 0,
                    'score_change' => $scoreResult['score'] - ($lead->latest_score ?? 0),
                    'factors' => $scoreResult['factors'],
                    'insights' => $scoreResult['insights'],
                    'recommendations' => $scoreResult['recommendations'],
                    'confidence' => $scoreResult['confidence'],
                    'model_version' => 'gpt-4-turbo-preview',
                    'scored_at' => new \DateTime()
                ]);
                
                $results[] = [
                    'id' => $leadId,
                    'score' => $scoreResult['score'],
                    'status' => 'success'
                ];
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $leadId,
                    'error' => $e->getMessage()
                ];
            }
            
            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }
        
        return $this->json($response, [
            'data' => [
                'processed' => count($results),
                'failed' => count($errors),
                'results' => $results,
                'errors' => $errors,
            ]
        ]);
    }
    
    /**
     * Get lead scoring history
     * GET /api/v8/leads/{id}/score-history
     */
    public function getScoreHistory(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $lead = Lead::find($id);
        
        if (!$lead) {
            return $this->error($response, 'Lead not found', 404);
        }
        
        $history = LeadScore::where('lead_id', $id)
            ->orderBy('scored_at', 'DESC')
            ->limit(20)
            ->get()
            ->map(function ($score) {
                return [
                    'id' => $score->id,
                    'score' => $score->score,
                    'previous_score' => $score->previous_score,
                    'score_change' => $score->score_change,
                    'factors' => $score->factors,
                    'insights' => $score->insights,
                    'recommendations' => $score->recommendations,
                    'confidence' => $score->confidence,
                    'model_version' => $score->model_version,
                    'scored_at' => $score->scored_at->toIso8601String()
                ];
            });
        
        return $this->json($response, ['data' => $history]);
    }
    
    /**
     * Chat endpoint for AI chatbot
     * POST /api/v8/ai/chat
     */
    public function chat(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'message' => 'required|string',
            'conversation_id' => 'sometimes|string|exists:ai_chat_conversations,id',
            'visitor_id' => 'sometimes|string'
        ]);
        
        $message = $data['message'];
        $conversationId = $data['conversation_id'] ?? null;
        $visitorId = $data['visitor_id'] ?? null;
        
        try {
            // Get or create conversation
            if (!$conversationId) {
                $conversation = ChatConversation::create([
                    'visitor_id' => $visitorId,
                    'status' => 'active',
                    'started_at' => new \DateTime()
                ]);
                $conversationId = $conversation->id;
            } else {
                $conversation = ChatConversation::find($conversationId);
            }
            
            // Get conversation history
            $conversationHistory = $conversation->messages()
                ->orderBy('created_at')
                ->get()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->role,
                        'content' => $msg->content
                    ];
                })
                ->toArray();
            
            // Get visitor context
            $visitorContext = $this->getVisitorContext($visitorId);
            
            // Search knowledge base
            $kbArticles = $this->chatbotService->searchKnowledgeBase($message);
            if (!empty($kbArticles)) {
                $visitorContext['kb_context'] = $this->formatKBContext($kbArticles);
            }
            
            // Generate AI response
            $aiResponse = $this->chatbotService->generateChatResponse(
                $conversationHistory,
                $message,
                $visitorContext
            );
            
            // Save messages
            ChatMessage::create([
                'conversation_id' => $conversationId,
                'role' => 'user',
                'content' => $message
            ]);
            
            ChatMessage::create([
                'conversation_id' => $conversationId,
                'role' => 'assistant',
                'content' => $aiResponse['response'],
                'metadata' => [
                    'confidence' => $aiResponse['confidence'],
                    'handoff_required' => $aiResponse['handoff_required'],
                    'intent' => $aiResponse['intent'] ?? null,
                    'sentiment' => $aiResponse['sentiment'] ?? null,
                    'kb_articles_used' => $aiResponse['kb_articles_used'] ?? [],
                ]
            ]);
            
            // Handle handoff if needed
            if ($aiResponse['handoff_required']) {
                $conversation->update(['status' => 'handoff']);
            }
            
            // Check for lead capture opportunity
            $leadScore = $this->assessLeadQuality($conversationHistory, $aiResponse);
            if ($leadScore >= 60) {
                $leadInfo = $this->extractLeadInfoWithAI($conversation, $visitorContext);
                if ($leadInfo && $this->validateLeadInfo($leadInfo)) {
                    $leadId = $this->createLeadFromChat($conversation, $leadInfo);
                    
                    $aiResponse['metadata']['lead_captured'] = true;
                    $aiResponse['metadata']['lead_id'] = $leadId;
                    $aiResponse['metadata']['lead_score'] = $leadScore;
                    $aiResponse['metadata']['lead_info'] = $leadInfo;
                }
            }
            
            return $this->json($response, [
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
            
        } catch (\Exception $e) {
            error_log('Chat error: ' . $e->getMessage());
            return $this->error($response, 'Chat service temporarily unavailable. Please try again later', 500);
        }
    }
    
    /**
     * Get chat conversation
     * GET /api/v8/ai/chat/{conversation_id}
     */
    public function getConversation(Request $request, Response $response, array $args): Response
    {
        $conversationId = $args['conversation_id'];
        $conversation = ChatConversation::with('messages')
            ->where('deleted', 0)
            ->find($conversationId);
        
        if (!$conversation) {
            return $this->error($response, 'Conversation not found', 404);
        }
        
        $messages = $conversation->messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'metadata' => $msg->metadata,
                'created_at' => $msg->created_at->toIso8601String()
            ];
        });
        
        return $this->json($response, [
            'data' => [
                'id' => $conversation->id,
                'visitor_id' => $conversation->visitor_id,
                'lead_id' => $conversation->lead_id,
                'contact_id' => $conversation->contact_id,
                'status' => $conversation->status,
                'started_at' => $conversation->started_at?->toIso8601String(),
                'ended_at' => $conversation->ended_at?->toIso8601String(),
                'messages' => $messages
            ]
        ]);
    }
    
    /**
     * Start a new AI chat conversation
     * POST /api/ai/chat/start
     */
    public function startConversation(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'contactId' => 'sometimes|string|exists:contacts,id'
        ]);
        
        $conversation = ChatConversation::create([
            'contact_id' => $data['contactId'] ?? null,
            'status' => 'active',
            'started_at' => new \DateTime(),
            'created_by' => $request->getAttribute('user_id') ?? 'system'
        ]);
        
        return $this->json($response, [
            'conversationId' => $conversation->id,
            'status' => 'active'
        ]);
    }
    
    /**
     * Send a message in an AI chat conversation
     * POST /api/ai/chat/message
     */
    public function sendMessage(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'conversationId' => 'required|exists:ai_chat_conversations,id',
            'message' => 'required|string'
        ]);
        
        $conversation = ChatConversation::find($data['conversationId']);
        
        // Store user message
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $data['message']
        ]);
        
        // Get conversation context
        $context = $this->getChatContext($conversation);
        
        // Generate AI response
        $aiResponse = $this->generateAIResponse($data['message'], $context);
        
        // Store AI response
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $aiResponse['message'],
            'metadata' => [
                'sentiment' => $aiResponse['sentiment'] ?? 'neutral',
                'confidence' => $aiResponse['confidence'] ?? 0.95,
                'intent' => $aiResponse['intent'] ?? 'general'
            ]
        ]);
        
        // Handle special intents
        $this->handleSpecialIntents($conversation, $aiResponse);
        
        return $this->json($response, [
            'response' => $aiResponse['message'],
            'metadata' => [
                'sentiment' => $aiResponse['sentiment'] ?? 'neutral',
                'confidence' => $aiResponse['confidence'] ?? 0.95,
                'intent' => $aiResponse['intent'] ?? 'general'
            ]
        ]);
    }
    
    /**
     * Get chat history for a contact
     * GET /api/ai/chat/history/{contact_id}
     */
    public function getChatHistory(Request $request, Response $response, array $args): Response
    {
        $contactId = $args['contact_id'];
        $conversations = ChatConversation::with('messages')
            ->where('contact_id', $contactId)
            ->where('deleted', 0)
            ->orderBy('started_at', 'DESC')
            ->get();
        
        $history = $conversations->map(function ($conv) {
            return [
                'conversationId' => $conv->id,
                'status' => $conv->status,
                'startedAt' => $conv->started_at?->toIso8601String(),
                'endedAt' => $conv->ended_at?->toIso8601String(),
                'messageCount' => $conv->messages->count(),
                'messages' => $conv->messages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'role' => $msg->role,
                        'content' => $msg->content,
                        'metadata' => $msg->metadata,
                        'createdAt' => $msg->created_at->toIso8601String()
                    ];
                })
            ];
        });
        
        return $this->json($response, [
            'contactId' => $contactId,
            'conversations' => $history
        ]);
    }
    
    /**
     * Create a support ticket from chat
     * POST /api/ai/chat/create-ticket
     */
    public function createTicketFromChat(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'conversationId' => 'required|exists:ai_chat_conversations,id',
            'summary' => 'sometimes|string',
            'priority' => 'sometimes|string|in:Low,Medium,High,Urgent'
        ]);
        
        $conversation = ChatConversation::find($data['conversationId']);
        
        DB::beginTransaction();
        
        try {
            $case = new Case();
            $case->case_number = $this->generateCaseNumber();
            $case->name = $data['summary'] ?? 'Support request from AI chat';
            $case->status = 'Open';
            $case->priority = $data['priority'] ?? 'Medium';
            $case->type = 'Technical';
            $case->description = $this->getConversationTranscript($conversation);
            $case->assigned_user_id = $request->getAttribute('user_id') ?? '1';
            $case->save();
            
            // Link to contact if available
            if ($conversation->contact_id) {
                $case->contacts()->attach($conversation->contact_id);
            }
            
            // Update conversation metadata
            $conversation->update([
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'ticket_id' => $case->id
                ])
            ]);
            
            DB::commit();
            
            return $this->json($response, [
                'ticketId' => $case->id,
                'ticketNumber' => $case->case_number,
                'message' => 'Support ticket created successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create ticket: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Schedule a demo from chat
     * POST /api/ai/chat/schedule-demo
     */
    public function scheduleDemoFromChat(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'conversationId' => 'required|exists:ai_chat_conversations,id',
            'requestedDate' => 'sometimes|date',
            'requestedTime' => 'sometimes|date_format:H:i',
            'notes' => 'sometimes|string'
        ]);
        
        $conversation = ChatConversation::find($data['conversationId']);
        
        DB::beginTransaction();
        
        try {
            $dateStart = isset($data['requestedDate']) && isset($data['requestedTime'])
                ? $data['requestedDate'] . ' ' . $data['requestedTime']
                : (new \DateTime())->modify('+2 days')->setTime(10, 0);
            
            $meeting = new Meeting();
            $meeting->name = 'Product Demo - AI Chat Request';
            $meeting->status = 'Planned';
            $meeting->location = 'Online Demo';
            $meeting->duration_hours = 1;
            $meeting->duration_minutes = 0;
            $meeting->date_start = $dateStart;
            $meeting->description = "Demo requested via AI chat\n\n" . 
                                  "Notes: " . ($data['notes'] ?? '') . "\n\n" .
                                  "Chat transcript:\n" . $this->getConversationTranscript($conversation);
            $meeting->assigned_user_id = $request->getAttribute('user_id') ?? '1';
            $meeting->save();
            
            // Link to contact if available
            if ($conversation->contact_id) {
                $meeting->contacts()->attach($conversation->contact_id);
            }
            
            // Update conversation metadata
            $conversation->update([
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'demo_id' => $meeting->id
                ])
            ]);
            
            DB::commit();
            
            return $this->json($response, [
                'demoId' => $meeting->id,
                'scheduledDate' => is_string($dateStart) ? $dateStart : $dateStart->format('c'),
                'message' => 'Demo scheduled successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to schedule demo: ' . $e->getMessage(), 500);
        }
    }
    
    // Private helper methods
    
    private function getLeadActivityData(Lead $lead): array
    {
        $visitor = ActivityTrackingVisitor::where('lead_id', $lead->id)->first();
        
        if (!$visitor) {
            return [];
        }
        
        $highValuePages = $visitor->pageViews()
            ->where('is_high_value', 1)
            ->pluck('page_url')
            ->toArray();
        
        $formSubmissions = FormSubmission::where('lead_id', $lead->id)->count();
        
        return [
            'total_visits' => $visitor->total_visits ?? 0,
            'total_page_views' => $visitor->total_page_views ?? 0,
            'total_time_spent' => $visitor->total_time_spent ?? 0,
            'engagement_score' => $visitor->engagement_score ?? 0,
            'high_value_pages' => $highValuePages,
            'form_submissions' => $formSubmissions,
        ];
    }
    
    private function getVisitorContext(?string $visitorId): array
    {
        if (!$visitorId) {
            return [];
        }
        
        $visitor = ActivityTrackingVisitor::with(['lead', 'pageViews' => function ($query) {
            $query->orderBy('timestamp', 'DESC')->limit(5);
        }])->where('visitor_id', $visitorId)->first();
        
        if (!$visitor) {
            return [];
        }
        
        $context = [];
        
        if ($visitor->lead) {
            $context['lead_name'] = $visitor->lead->full_name;
            $context['account_name'] = $visitor->lead->account_name;
        }
        
        $context['pages_viewed'] = $visitor->pageViews->map(function ($page) {
            return $page->page_title ?: $page->page_url;
        })->toArray();
        
        $context['total_visits'] = $visitor->total_visits ?? 0;
        $context['engagement_score'] = $visitor->engagement_score ?? 0;
        
        return $context;
    }
    
    private function formatKBContext(array $articles): string
    {
        $context = "Here are relevant knowledge base articles that may help answer the question:\n\n";
        
        foreach ($articles as $article) {
            $context .= "Article: " . $article['title'] . "\n";
            $context .= "Content: " . Str::limit(strip_tags($article['content']), 500) . "\n\n";
        }
        
        $context .= "Please use this information to provide accurate answers based on our knowledge base.";
        
        return $context;
    }
    
    private function assessLeadQuality(array $conversation, array $aiResponse): int
    {
        $score = 50; // Base score
        
        // Intent-based scoring
        if (in_array($aiResponse['intent'], ['sales', 'qualification'])) {
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
            if (str_contains($conversationText, $signal)) {
                $score += 5;
            }
        }
        
        // Contact info provided
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $conversationText)) {
            $score += 15;
        }
        
        return min(100, $score);
    }
    
    private function buildConversationText(array $conversation): string
    {
        $text = '';
        foreach ($conversation as $msg) {
            $role = $msg['role'] === 'assistant' ? 'Agent' : 'Visitor';
            $text .= "{$role}: {$msg['content']}\n";
        }
        return $text;
    }
    
    private function validateLeadInfo(array $leadInfo): bool
    {
        return !empty($leadInfo['email1']) || !empty($leadInfo['phone_work']);
    }
    
    private function extractLeadInfoWithAI(ChatConversation $conversation, array $visitorContext): ?array
    {
        try {
            $conversationText = $conversation->messages()
                ->orderBy('created_at')
                ->get()
                ->map(function ($msg) {
                    $role = $msg->role === 'assistant' ? 'Agent' : 'Visitor';
                    return "{$role}: {$msg->content}";
                })
                ->implode("\n");
            
            $extractedInfo = $this->openAIService->extractLeadInfo($conversationText);
            
            // Merge with visitor context
            if (!empty($visitorContext['lead_name']) && empty($extractedInfo['first_name'])) {
                $nameParts = explode(' ', $visitorContext['lead_name'], 2);
                $extractedInfo['first_name'] = $nameParts[0];
                if (isset($nameParts[1])) {
                    $extractedInfo['last_name'] = $nameParts[1];
                }
            }
            
            if (!empty($visitorContext['account_name']) && empty($extractedInfo['account_name'])) {
                $extractedInfo['account_name'] = $visitorContext['account_name'];
            }
            
            $extractedInfo['lead_source'] = 'AI Chat';
            $extractedInfo['conversation_id'] = $conversation->id;
            $extractedInfo['visitor_id'] = $conversation->visitor_id;
            
            return $extractedInfo;
            
        } catch (\Exception $e) {
            \Log::error('Lead extraction failed: ' . $e->getMessage());
            return $this->simpleLeadExtraction($conversation);
        }
    }
    
    private function simpleLeadExtraction(ChatConversation $conversation): ?array
    {
        $allText = $conversation->messages->pluck('content')->implode(' ');
        $leadInfo = [];
        
        // Email extraction
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $allText, $matches)) {
            $leadInfo['email1'] = $matches[0];
        }
        
        // Phone extraction
        if (preg_match('/(?:\+?1[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})/', $allText, $matches)) {
            $leadInfo['phone_work'] = $matches[0];
        }
        
        return empty($leadInfo) ? null : $leadInfo;
    }
    
    private function createLeadFromChat(ChatConversation $conversation, array $leadInfo): string
    {
        // Check if lead exists
        $existingLead = null;
        if (!empty($leadInfo['email1'])) {
            $existingLead = Lead::where('email1', $leadInfo['email1'])
                ->where('deleted', 0)
                ->first();
        }
        
        if ($existingLead) {
            $this->updateLeadFromChat($existingLead, $leadInfo);
            $leadId = $existingLead->id;
        } else {
            // Create new lead
            $lead = new Lead();
            $lead->lead_source = 'AI Chat';
            $lead->status = 'New';
            $lead->assigned_user_id = '1';
            
            // Direct assignment with exact DB fields
            foreach ($leadInfo as $field => $value) {
                if (!empty($value) && $lead->hasAttribute($field)) {
                    $lead->$field = $value;
                }
            }
            
            // Build description
            $description = "Lead captured from AI Chat\n";
            $description .= "Conversation ID: {$conversation->id}\n";
            $description .= "Capture Date: " . (new \DateTime())->format('Y-m-d H:i:s') . "\n\n";
            
            if (!empty($leadInfo['pain_points'])) {
                $description .= "Pain Points:\n";
                foreach ((array)$leadInfo['pain_points'] as $point) {
                    $description .= "- {$point}\n";
                }
            }
            
            if (!empty($leadInfo['requirements'])) {
                $description .= "\nRequirements:\n";
                foreach ((array)$leadInfo['requirements'] as $req) {
                    $description .= "- {$req}\n";
                }
            }
            
            $lead->description = $description;
            $lead->save();
            
            $leadId = $lead->id;
        }
        
        // Link conversation to lead
        $conversation->update(['lead_id' => $leadId]);
        
        // Update visitor with lead ID
        if ($conversation->visitor_id) {
            ActivityTrackingVisitor::where('visitor_id', $conversation->visitor_id)
                ->update(['lead_id' => $leadId]);
        }
        
        return $leadId;
    }
    
    private function updateLeadFromChat(Lead $lead, array $leadInfo): void
    {
        // Direct update with exact DB fields
        foreach ($leadInfo as $field => $value) {
            if (!empty($value) && empty($lead->$field) && $lead->hasAttribute($field)) {
                $lead->$field = $value;
            }
        }
        
        $lead->save();
    }
    
    private function getChatContext(ChatConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content
                ];
            })
            ->toArray();
        
        $contact = $conversation->contact;
        
        return [
            'messages' => $messages,
            'contact' => $contact ? [
                'name' => $contact->full_name,
                'account_name' => $contact->account_name
            ] : null
        ];
    }
    
    private function generateAIResponse(string $message, array $context): array
    {
        // This would normally call the AI service
        // For now, returning a simple response based on intent detection
        
        $intent = 'general';
        $messageLower = strtolower($message);
        
        if (str_contains($messageLower, 'help') || str_contains($messageLower, 'support')) {
            $intent = 'support';
        } elseif (str_contains($messageLower, 'demo') || str_contains($messageLower, 'trial')) {
            $intent = 'schedule_demo';
        } elseif (str_contains($messageLower, 'price') || str_contains($messageLower, 'pricing')) {
            $intent = 'pricing';
        }
        
        $responses = [
            'support' => "I understand you're experiencing an issue. I can help you create a support ticket. Could you please describe the problem you're facing in more detail?",
            'schedule_demo' => "I'd be happy to help you schedule a demo! Our team can show you all the features and answer any questions. When would be a good time for you?",
            'pricing' => "Our pricing is based on the number of users and features you need. For detailed pricing information, I can have our sales team contact you. Would you like me to schedule a call?",
            'general' => "Thank you for your message. How can I assist you today? I can help with product information, scheduling demos, or technical support."
        ];
        
        return [
            'message' => $responses[$intent],
            'intent' => $intent,
            'sentiment' => 'neutral',
            'confidence' => 0.95
        ];
    }
    
    private function handleSpecialIntents(ChatConversation $conversation, array $aiResponse): void
    {
        // Handle special intents if needed
        // This is where we'd trigger automatic actions based on intent
    }
    
    private function generateCaseNumber(): string
    {
        $lastCase = Case::orderBy('case_number', 'DESC')->first();
        
        if ($lastCase && preg_match('/CASE-(\d+)/', $lastCase->case_number, $matches)) {
            $lastNumber = (int)$matches[1];
            return sprintf('CASE-%03d', $lastNumber + 1);
        }
        
        return 'CASE-001';
    }
    
    private function getConversationTranscript(ChatConversation $conversation): string
    {
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get();
        
        $transcript = "";
        foreach ($messages as $msg) {
            $timestamp = $msg->created_at->format('Y-m-d H:i:s');
            $role = ucfirst($msg->role);
            $transcript .= "[$timestamp] $role: {$msg->content}\n";
        }
        
        return $transcript;
    }
    
    private function triggerWebhook(string $event, array $data): void
    {
        // TODO: Implement webhook triggering
        // This would queue a job to send the webhook
    }
}