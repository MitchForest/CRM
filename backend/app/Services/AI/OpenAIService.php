<?php

namespace App\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class OpenAIService
{
    private Client $client;
    private string $apiKey;
    private string $model;
    
    public function __construct()
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        $this->model = 'gpt-3.5-turbo';
        
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
    }
    
    /**
     * Send a completion request to OpenAI
     */
    public function complete(string $prompt, array $options = []): string
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful sales assistant for a CRM system.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $options['max_tokens'] ?? 500,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'top_p' => $options['top_p'] ?? 1,
                    'frequency_penalty' => $options['frequency_penalty'] ?? 0,
                    'presence_penalty' => $options['presence_penalty'] ?? 0
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? '';
            
        } catch (RequestException $e) {
            throw new \Exception('OpenAI API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate embeddings for text
     */
    public function embed(string $text): array
    {
        try {
            $response = $this->client->post('embeddings', [
                'json' => [
                    'model' => 'text-embedding-ada-002',
                    'input' => $text
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'][0]['embedding'] ?? [];
            
        } catch (RequestException $e) {
            throw new \Exception('OpenAI Embedding error: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze sentiment of text
     */
    public function analyzeSentiment(string $text): array
    {
        $prompt = "Analyze the sentiment of this text and respond with only one word: positive, negative, or neutral.\n\nText: {$text}";
        
        $sentiment = strtolower(trim($this->complete($prompt, [
            'max_tokens' => 10,
            'temperature' => 0
        ])));
        
        return [
            'sentiment' => $sentiment,
            'confidence' => in_array($sentiment, ['positive', 'negative', 'neutral']) ? 0.9 : 0.5
        ];
    }
    
    /**
     * Generate a response for chatbot
     */
    public function generateChatResponse(array $messages, array $context = []): string
    {
        $systemPrompt = "You are Sassy, a helpful sales assistant for Sassy CRM. " .
                       "You help visitors learn about our CRM features, pricing, and can qualify leads. " .
                       "Be friendly, professional, and concise. " .
                       "Our key features include: AI lead scoring, activity tracking, embeddable forms, and unified timeline.";
        
        if (!empty($context['knowledge_base'])) {
            $systemPrompt .= "\n\nRelevant knowledge base articles:\n" . $context['knowledge_base'];
        }
        
        $formattedMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        
        foreach ($messages as $message) {
            // Handle both formats: ['role' => 'user', 'content' => '...'] and ['message_type' => 'user', 'content' => '...']
            $role = $message['role'] ?? ($message['message_type'] ?? 'user');
            $formattedMessages[] = [
                'role' => in_array($role, ['user', 'assistant', 'system']) ? $role : 'user',
                'content' => $message['content']
            ];
        }
        
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => $formattedMessages,
                    'max_tokens' => 300,
                    'temperature' => 0.7,
                    'presence_penalty' => 0.1
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? 'I apologize, I\'m having trouble responding right now.';
            
        } catch (\Exception $e) {
            return 'I apologize, I\'m experiencing technical difficulties. Please try again later or contact support.';
        }
    }
    
    /**
     * Extract key information from text
     */
    public function extractInfo(string $text, array $fields): array
    {
        $fieldsList = implode(', ', $fields);
        $prompt = "Extract the following information from the text: {$fieldsList}\n\n" .
                 "Text: {$text}\n\n" .
                 "Return as JSON with the field names as keys. If a field is not found, use null.";
        
        try {
            $response = $this->complete($prompt, [
                'max_tokens' => 200,
                'temperature' => 0
            ]);
            
            // Try to parse JSON response
            $extracted = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $extracted;
            }
            
            return array_fill_keys($fields, null);
            
        } catch (\Exception $e) {
            return array_fill_keys($fields, null);
        }
    }
    
    /**
     * Summarize long text
     */
    public function summarize(string $text, int $maxLength = 100): string
    {
        $prompt = "Summarize the following text in {$maxLength} words or less:\n\n{$text}";
        
        return $this->complete($prompt, [
            'max_tokens' => $maxLength * 2, // Rough estimate
            'temperature' => 0.3
        ]);
    }
    
    /**
     * Generate a knowledge base article from a prompt
     */
    public function generateArticle(string $topic, array $options = []): array
    {
        $tone = $options['tone'] ?? 'professional';
        $style = $options['style'] ?? 'informative';
        $wordCount = $options['word_count'] ?? 800;
        
        $systemPrompt = "You are an expert content writer creating knowledge base articles. 
        Write in a {$tone} tone with a {$style} style. 
        Create well-structured content with clear headings, subheadings, and paragraphs.
        Include practical examples and actionable information.
        Target approximately {$wordCount} words.";
        
        $userPrompt = "Write a comprehensive knowledge base article about: {$topic}
        
        Structure the article with:
        1. An engaging introduction
        2. Clear main sections with subheadings
        3. Practical examples or use cases
        4. A helpful conclusion
        5. Key takeaways or summary points
        
        Format the content in Markdown.";
        
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.7,
                    'top_p' => 0.9
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            // Extract title from content (usually first # heading)
            $title = $topic;
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = $matches[1];
                // Remove the title from content as we store it separately
                $content = preg_replace('/^#\s+.+\n/', '', $content, 1);
            }
            
            // Generate a summary (first paragraph or custom summary)
            $summary = $this->generateSummary($content);
            
            return [
                'success' => true,
                'title' => $title,
                'content' => trim($content),
                'summary' => $summary,
                'word_count' => str_word_count($content)
            ];
            
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to generate article: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Rewrite an existing article with specific instructions
     */
    public function rewriteArticle(string $currentContent, string $instructions, array $options = []): array
    {
        $tone = $options['tone'] ?? 'maintain current';
        $style = $options['style'] ?? 'maintain current';
        
        $systemPrompt = "You are an expert content editor improving knowledge base articles.
        Maintain the core information while improving clarity, structure, and readability.
        Tone: {$tone}. Style: {$style}.";
        
        $userPrompt = "Rewrite the following article based on these instructions: {$instructions}
        
        Current article:
        {$currentContent}
        
        Maintain accuracy while improving the content. Format in Markdown.";
        
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.6,
                    'top_p' => 0.9
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            return [
                'success' => true,
                'content' => trim($content),
                'word_count' => str_word_count($content)
            ];
            
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Failed to rewrite article: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate a summary from article content
     */
    public function generateSummary(string $content): string
    {
        // Try to extract first paragraph
        $paragraphs = explode("\n\n", $content);
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (strlen($para) > 50 && !str_starts_with($para, '#')) {
                return substr($para, 0, 200) . '...';
            }
        }
        
        // Fallback: generate summary using AI
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Summarize this article in 1-2 sentences.'],
                        ['role' => 'user', 'content' => $content]
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0.5
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? '';
            
        } catch (\Exception $e) {
            return 'Article about ' . substr($content, 0, 100) . '...';
        }
    }
}