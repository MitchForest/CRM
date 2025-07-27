<?php

namespace App\Services\AI;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
use App\Models\KnowledgeBaseArticle;
use App\Services\CRM\LeadService;
use Illuminate\Support\Str;

class ChatbotService
{
    public function __construct(
        private OpenAIService $openAI,
        private LeadService $leadService
    ) {}
    
    /**
     * Start a new chat conversation
     */
    public function startConversation(array $data): ChatConversation
    {
        $conversationId = Str::uuid()->toString();
        
        $conversation = ChatConversation::create([
            'conversation_id' => $conversationId,
            'visitor_id' => $data['visitor_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'started_at' => new \DateTime(),
            'status' => 'active',
            'metadata' => [
                'source' => $data['source'] ?? 'website',
                'page_url' => $data['page_url'] ?? null,
                'referrer' => $data['referrer'] ?? null
            ]
        ]);
        
        // Add system message
        $this->addMessage($conversationId, 'system', 'Chat started', [
            'event' => 'conversation_started'
        ]);
        
        return $conversation;
    }
    
    /**
     * Handle incoming message
     */
    public function handleMessage(string $conversationId, string $message, array $context = []): ChatMessage
    {
        $conversation = ChatConversation::where('conversation_id', $conversationId)->firstOrFail();
        
        // Add user message
        $userMessage = $this->addMessage($conversationId, 'user', $message);
        
        // Get conversation history
        $messages = $conversation->messages()
            ->where('message_type', '!=', 'system')
            ->orderBy('created_at')
            ->get()
            ->toArray();
        
        // Find relevant knowledge base articles
        $relevantArticles = $this->findRelevantArticles($message);
        
        // Generate response
        $response = $this->openAI->generateChatResponse($messages, [
            'knowledge_base' => $this->formatArticlesForContext($relevantArticles)
        ]);
        
        // Add assistant message
        $assistantMessage = $this->addMessage($conversationId, 'assistant', $response);
        
        // Extract lead information if available
        $this->extractLeadInfo($conversation, $messages);
        
        // Check for qualification triggers
        $this->checkQualificationTriggers($conversation, $message);
        
        return $assistantMessage;
    }
    
    /**
     * Add a message to the conversation
     */
    private function addMessage(string $conversationId, string $type, string $content, array $metadata = []): ChatMessage
    {
        return ChatMessage::create([
            'conversation_id' => $conversationId,
            'message_type' => $type,
            'content' => $content,
            'metadata' => $metadata,
            'created_at' => new \DateTime()
        ]);
    }
    
    /**
     * Find relevant knowledge base articles
     */
    private function findRelevantArticles(string $query, int $limit = 3): array
    {
        // Simple keyword search for now
        // In production, use embeddings for semantic search
        return KnowledgeBaseArticle::published()
            ->search($query)
            ->limit($limit)
            ->get(['title', 'excerpt', 'slug'])
            ->toArray();
    }
    
    /**
     * Format articles for AI context
     */
    private function formatArticlesForContext(array $articles): string
    {
        if (empty($articles)) {
            return '';
        }
        
        $formatted = [];
        foreach ($articles as $article) {
            $formatted[] = "- {$article['title']}: {$article['excerpt']}";
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Extract lead information from conversation
     */
    private function extractLeadInfo(ChatConversation $conversation, array $messages): void
    {
        // Only process if we don't have a lead yet
        if ($conversation->lead_id) {
            return;
        }
        
        // Combine all user messages
        $allUserMessages = collect($messages)
            ->filter(fn($m) => $m['message_type'] === 'user')
            ->pluck('content')
            ->implode(' ');
        
        // Try to extract information
        $extracted = $this->openAI->extractInfo($allUserMessages, [
            'name', 'email', 'company', 'phone', 'title'
        ]);
        
        // Check if we have enough info to create a lead
        if (!empty($extracted['email']) || (!empty($extracted['name']) && !empty($extracted['company']))) {
            $nameParts = $this->parseFullName($extracted['name'] ?? '');
            
            $leadData = [
                'first_name' => $nameParts['first'] ?? 'Unknown',
                'last_name' => $nameParts['last'] ?? '',
                'email' => $extracted['email'] ?? null,
                'company' => $extracted['company'] ?? null,
                'phone' => $extracted['phone'] ?? null,
                'title' => $extracted['title'] ?? null,
                'source' => 'Chat',
                'status' => 'new',
                'visitor_id' => $conversation->visitor_id
            ];
            
            $lead = $this->leadService->create($leadData);
            
            // Update conversation
            $conversation->update(['lead_id' => $lead->id]);
            
            // Add system message
            $this->addMessage($conversation->conversation_id, 'system', 'Lead created', [
                'event' => 'lead_created',
                'lead_id' => $lead->id
            ]);
        }
    }
    
    /**
     * Check for qualification triggers
     */
    private function checkQualificationTriggers(ChatConversation $conversation, string $message): void
    {
        $triggers = [
            'high_intent' => [
                'pricing', 'cost', 'demo', 'trial', 'buy', 'purchase', 'contract'
            ],
            'feature_interest' => [
                'integration', 'api', 'features', 'security', 'compliance'
            ],
            'timeline' => [
                'urgent', 'asap', 'this week', 'this month', 'immediately'
            ]
        ];
        
        $metadata = $conversation->metadata ?? [];
        $qualificationSignals = $metadata['qualification_signals'] ?? [];
        
        foreach ($triggers as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($message, $keyword) !== false) {
                    $qualificationSignals[] = [
                        'category' => $category,
                        'keyword' => $keyword,
                        'timestamp' => new \DateTime()
                    ];
                }
            }
        }
        
        if (!empty($qualificationSignals)) {
            $metadata['qualification_signals'] = $qualificationSignals;
            $metadata['qualification_score'] = count($qualificationSignals) * 0.1; // Simple scoring
            
            $conversation->update(['metadata' => $metadata]);
        }
    }
    
    /**
     * End a conversation
     */
    public function endConversation(string $conversationId, array $feedback = []): ChatConversation
    {
        $conversation = ChatConversation::where('conversation_id', $conversationId)->firstOrFail();
        
        $updateData = [
            'ended_at' => new \DateTime(),
            'status' => 'ended'
        ];
        
        if (!empty($feedback)) {
            $updateData['rating'] = $feedback['rating'] ?? null;
            $updateData['feedback'] = $feedback['comment'] ?? null;
        }
        
        $conversation->update($updateData);
        
        // Add system message
        $this->addMessage($conversationId, 'system', 'Chat ended', [
            'event' => 'conversation_ended',
            'feedback' => $feedback
        ]);
        
        return $conversation;
    }
    
    /**
     * Get suggested responses
     */
    public function getSuggestedResponses(string $conversationId): array
    {
        $conversation = ChatConversation::where('conversation_id', $conversationId)->firstOrFail();
        
        // Get last few messages
        $recentMessages = $conversation->messages()
            ->where('message_type', '!=', 'system')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // Common suggestions based on conversation stage
        $suggestions = [];
        
        if ($recentMessages->count() < 3) {
            // Early stage
            $suggestions = [
                'Tell me about your CRM features',
                'How does AI lead scoring work?',
                'Can I see a demo?',
                'What integrations do you support?'
            ];
        } elseif ($conversation->lead_id) {
            // Lead identified
            $suggestions = [
                'What are your pricing plans?',
                'Can I start a free trial?',
                'Schedule a call with sales',
                'See customer testimonials'
            ];
        } else {
            // Need more info
            $suggestions = [
                'I\'d like to learn more',
                'Can you help me evaluate CRM options?',
                'What makes Sassy CRM different?',
                'How do I get started?'
            ];
        }
        
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Parse full name into parts
     */
    private function parseFullName(?string $fullName): array
    {
        if (empty($fullName)) {
            return ['first' => null, 'last' => null];
        }
        
        $parts = explode(' ', trim($fullName), 2);
        return [
            'first' => $parts[0] ?? null,
            'last' => $parts[1] ?? null
        ];
    }
}