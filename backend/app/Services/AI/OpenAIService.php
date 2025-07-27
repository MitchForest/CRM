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
            $formattedMessages[] = [
                'role' => $message['message_type'] === 'user' ? 'user' : 'assistant',
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
}