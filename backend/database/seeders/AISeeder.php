<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class AISeeder extends BaseSeeder
{
    private $chatTopics = [
        'pricing' => [
            'questions' => [
                'What are your pricing plans?',
                'How much does it cost for 50 users?',
                'Do you offer discounts for annual billing?',
                'What\'s included in the Enterprise plan?',
                'Can we get a custom quote?',
            ],
            'responses' => [
                'We offer three main plans: Starter ($10/user/month), Pro ($25/user/month), and Enterprise (custom pricing). All plans include core features with varying levels of support and advanced features.',
                'For 50 users on our Pro plan, it would be $1,250/month. We offer a 20% discount for annual billing, bringing it to $12,000/year.',
                'Yes! We offer 20% off for annual billing on all plans. This means you get 2.4 months free compared to monthly billing.',
                'Enterprise includes unlimited users, advanced security features, dedicated support, custom integrations, and SLA guarantees. Contact our sales team for pricing.',
                'Absolutely! For teams over 100 users or with specific requirements, we can create a custom package. Let me connect you with our sales team.',
            ],
        ],
        'features' => [
            'questions' => [
                'Do you have Gantt charts?',
                'Can we integrate with GitHub?',
                'Is there a mobile app?',
                'Do you support custom fields?',
                'How does the reporting work?',
            ],
            'responses' => [
                'Gantt charts are available on our Pro and Enterprise plans. They offer full drag-and-drop functionality and dependency management.',
                'Yes! We have native GitHub integration. You can link commits to tasks, view PR status, and sync issues bidirectionally.',
                'We have mobile apps for iOS and Android. They support offline mode and sync when you\'re back online.',
                'Custom fields are supported on all plans. You can create text, number, date, dropdown, and multi-select fields.',
                'Our reporting includes burndown charts, velocity tracking, time reports, and custom report builders on Pro/Enterprise plans.',
            ],
        ],
        'support' => [
            'questions' => [
                'How do I reset my password?',
                'I can\'t log in to my account',
                'How do I add new users?',
                'Where can I find documentation?',
                'Do you offer training?',
            ],
            'responses' => [
                'To reset your password, click "Forgot Password" on the login page. You\'ll receive an email with reset instructions.',
                'If you can\'t log in, first check your caps lock. If that doesn\'t help, try resetting your password or contact support@techflow.com.',
                'To add users, go to Settings > Team > Invite Users. You can add them individually or bulk import via CSV.',
                'Our documentation is available at docs.techflow.com. You can also access it from the Help menu within the app.',
                'We offer free onboarding for all new teams, weekly webinars, and custom training sessions for Enterprise customers.',
            ],
        ],
        'technical' => [
            'questions' => [
                'What\'s your API rate limit?',
                'Do you have webhooks?',
                'Can we self-host?',
                'What about data security?',
                'Is there an API?',
            ],
            'responses' => [
                'Our API allows 1000 requests per hour per API key. Enterprise customers can request higher limits.',
                'Yes, we support webhooks for all major events. You can configure them in Settings > Integrations > Webhooks.',
                'Self-hosting is available for Enterprise customers only. We provide Docker images and full deployment documentation.',
                'We use 256-bit encryption, are SOC2 compliant, and offer SSO/SAML for Enterprise. All data is backed up daily.',
                'Yes! We have a comprehensive REST API. Documentation and API keys are available at api.techflow.com.',
            ],
        ],
    ];
    
    public function run(): void
    {
        echo "Seeding AI data (lead scores and chat conversations)...\n";
        
        $leadIds = json_decode(file_get_contents(__DIR__ . '/lead_ids.json'), true);
        $contactIds = json_decode(file_get_contents(__DIR__ . '/contact_ids.json'), true);
        
        // Create lead scores
        $this->createLeadScores($leadIds);
        
        // Create chat conversations
        $this->createChatConversations($leadIds, $contactIds);
    }
    
    private function createLeadScores(array $leadIds): void
    {
        $scoreCount = 0;
        
        foreach ($leadIds as $leadId) {
            $lead = DB::table('leads')->where('id', $leadId)->first();
            
            // Not all leads have scores
            if (!$this->randomProbability(60)) continue;
            
            // Number of scores based on lead age
            $leadAge = (new \DateTime())->diff(new \DateTime($lead->date_entered))->days;
            $scoreHistoryCount = min(5, max(1, (int)($leadAge / 30))); // One score per month, max 5
            
            $scoreDate = new \DateTime($lead->date_entered);
            $previousScore = null;
            
            for ($i = 0; $i < $scoreHistoryCount; $i++) {
                // Score trends based on lead status
                $baseScore = $this->calculateBaseScore($lead->status, $lead->lead_source);
                
                // Add some variation but trend in the right direction
                if ($previousScore !== null) {
                    if ($lead->status === 'Converted' || $lead->status === 'Qualified') {
                        // Scores should trend up for good leads
                        $score = min(100, $previousScore + mt_rand(5, 15));
                    } elseif ($lead->status === 'Unqualified') {
                        // Scores should trend down for bad leads
                        $score = max(0, $previousScore - mt_rand(5, 15));
                    } else {
                        // Random walk for others
                        $score = max(0, min(100, $previousScore + mt_rand(-10, 10)));
                    }
                } else {
                    $score = $baseScore + mt_rand(-10, 10);
                    $score = max(0, min(100, $score));
                }
                
                $factors = $this->generateScoringFactors($score, $lead);
                
                DB::table('ai_lead_scoring_history')->insert([
                    'id' => $this->generateUuid(),
                    'lead_id' => $leadId,
                    'score' => $score,
                    'factors' => json_encode($factors),
                    'date_scored' => $scoreDate->format('Y-m-d H:i:s'),
                ]);
                
                $previousScore = $score;
                $scoreDate->modify('+' . mt_rand(25, 35) . ' days');
                $scoreCount++;
                
                // Update lead's current score
                if ($i === $scoreHistoryCount - 1) {
                    DB::table('leads')
                        ->where('id', $leadId)
                        ->update([
                            'ai_score' => $score,
                            'ai_score_date' => $scoreDate->format('Y-m-d H:i:s'),
                            'ai_insights' => json_encode($this->generateInsights($score, $factors)),
                            'ai_next_best_action' => $this->generateNextBestAction($score, $lead->status),
                        ]);
                }
            }
        }
        
        echo "  Created {$scoreCount} AI lead scores\n";
    }
    
    private function createChatConversations(array $leadIds, array $contactIds): void
    {
        $conversationCount = 0;
        $messageCount = 0;
        
        // Create conversations for 30% of leads
        $chattingLeads = array_slice($leadIds, 0, (int)(count($leadIds) * 0.3));
        shuffle($chattingLeads);
        
        foreach ($chattingLeads as $leadId) {
            $lead = DB::table('leads')->where('id', $leadId)->first();
            $conversationId = $this->generateUuid();
            $startTime = new \DateTime($lead->date_entered);
            $startTime->modify('-' . mt_rand(1, 24) . ' hours'); // Chat before becoming lead
            
            // Determine chat topic based on lead source
            $topic = $this->getChatTopic($lead->lead_source);
            
            DB::table('ai_chat_conversations')->insert([
                'id' => $conversationId,
                'lead_id' => $leadId,
                'contact_id' => null,
                'visitor_id' => 'v_' . substr(md5($lead->email1), 0, 16),
                'status' => 'completed',
                'date_started' => $startTime->format('Y-m-d H:i:s'),
                'date_ended' => (clone $startTime)->modify('+' . mt_rand(5, 20) . ' minutes')->format('Y-m-d H:i:s'),
                'date_modified' => $startTime->format('Y-m-d H:i:s'),
            ]);
            $conversationCount++;
            
            // Create messages
            $messageCount += $this->createChatMessages($conversationId, $topic, $startTime, 'lead', $lead);
        }
        
        // Create conversations for some existing customers
        $chattingContacts = array_slice($contactIds, 0, 50);
        
        foreach ($chattingContacts as $contactId) {
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            $conversationId = $this->generateUuid();
            $startTime = $this->randomDate('-3 months', '-1 week');
            
            DB::table('ai_chat_conversations')->insert([
                'id' => $conversationId,
                'lead_id' => null,
                'contact_id' => $contactId,
                'visitor_id' => 'v_' . substr(md5($contact->email1), 0, 16),
                'status' => 'completed',
                'date_started' => $startTime->format('Y-m-d H:i:s'),
                'date_ended' => (clone $startTime)->modify('+' . mt_rand(5, 30) . ' minutes')->format('Y-m-d H:i:s'),
                'date_modified' => $startTime->format('Y-m-d H:i:s'),
            ]);
            $conversationCount++;
            
            // Support topics for existing customers
            $messageCount += $this->createChatMessages($conversationId, 'support', $startTime, 'contact', $contact);
        }
        
        echo "  Created {$conversationCount} chat conversations\n";
        echo "  Created {$messageCount} chat messages\n";
    }
    
    private function calculateBaseScore(string $status, string $leadSource): int
    {
        $statusScores = [
            'New' => 40,
            'Contacted' => 50,
            'Qualified' => 75,
            'Unqualified' => 20,
            'Converted' => 90,
        ];
        
        $sourceBonus = [
            'Website' => 10,
            'Webinar' => 15,
            'Referral' => 20,
            'Trade Show' => 5,
            'Cold Call' => -5,
        ];
        
        $baseScore = $statusScores[$status] ?? 30;
        $bonus = $sourceBonus[$leadSource] ?? 0;
        
        return $baseScore + $bonus;
    }
    
    private function generateScoringFactors(int $score, $lead): array
    {
        $factors = [];
        
        // Engagement factors
        if ($score > 70) {
            $factors['high_engagement'] = true;
            $factors['website_visits'] = mt_rand(5, 15);
            $factors['content_downloads'] = mt_rand(2, 5);
        } else {
            $factors['website_visits'] = mt_rand(1, 5);
            $factors['content_downloads'] = mt_rand(0, 2);
        }
        
        // Firmographic factors
        $factors['company_size_match'] = $score > 50;
        $factors['industry_match'] = $this->randomProbability(70);
        $factors['budget_qualified'] = $lead->status === 'Qualified';
        
        // Behavioral factors
        $factors['email_opened'] = $this->randomProbability(60);
        $factors['demo_requested'] = $lead->lead_source === 'Website' && $score > 60;
        $factors['pricing_page_viewed'] = $score > 50 && $this->randomProbability(40);
        
        // Timing factors
        $factors['buying_timeline'] = $score > 70 ? 'immediate' : ($score > 40 ? '3-6 months' : '6+ months');
        
        return $factors;
    }
    
    private function generateInsights(int $score, array $factors): array
    {
        $insights = [];
        
        if ($score > 80) {
            $insights[] = 'High-value prospect showing strong buying signals';
            if ($factors['pricing_page_viewed'] ?? false) {
                $insights[] = 'Viewed pricing page multiple times - budget conscious';
            }
        } elseif ($score > 60) {
            $insights[] = 'Good fit with moderate engagement';
            if ($factors['demo_requested'] ?? false) {
                $insights[] = 'Requested demo - ready for sales conversation';
            }
        } elseif ($score > 40) {
            $insights[] = 'Early stage prospect - needs nurturing';
        } else {
            $insights[] = 'Low engagement - may not be qualified';
        }
        
        if ($factors['company_size_match'] ?? false) {
            $insights[] = 'Company size aligns with ideal customer profile';
        }
        
        if (($factors['website_visits'] ?? 0) > 10) {
            $insights[] = 'High website engagement indicates strong interest';
        }
        
        return $insights;
    }
    
    private function generateNextBestAction(int $score, string $status): string
    {
        if ($status === 'Converted') {
            return 'Continue opportunity management';
        }
        
        if ($score > 80) {
            return 'Call immediately - hot lead';
        } elseif ($score > 60) {
            return 'Schedule demo within 24 hours';
        } elseif ($score > 40) {
            return 'Send personalized email with case studies';
        } else {
            return 'Add to nurture campaign';
        }
    }
    
    private function getChatTopic(string $leadSource): string
    {
        if ($leadSource === 'Website' || $leadSource === 'Webinar') {
            return $this->faker->randomElement(['pricing', 'features', 'technical']);
        }
        return 'features';
    }
    
    private function createChatMessages(string $conversationId, string $topic, \DateTime $startTime, string $entityType, $entity): int
    {
        $questions = $this->chatTopics[$topic]['questions'] ?? $this->chatTopics['features']['questions'];
        $responses = $this->chatTopics[$topic]['responses'] ?? $this->chatTopics['features']['responses'];
        
        $messageCount = mt_rand(2, 5); // 2-5 exchanges
        $currentTime = clone $startTime;
        $messagesCreated = 0;
        
        // Initial greeting
        DB::table('ai_chat_messages')->insert([
            'id' => $this->generateUuid(),
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => 'Hello! I\'m here to help. What can I assist you with today?',
            'date_entered' => $currentTime->format('Y-m-d H:i:s'),
        ]);
        $messagesCreated++;
        
        $currentTime->modify('+' . mt_rand(30, 90) . ' seconds');
        
        for ($i = 0; $i < $messageCount; $i++) {
            // User question
            $question = $this->faker->randomElement($questions);
            if ($entityType === 'lead') {
                $question = str_replace('I', 'we', $question); // Make it sound like a company
            }
            
            DB::table('ai_chat_messages')->insert([
                'id' => $this->generateUuid(),
                'conversation_id' => $conversationId,
                'role' => 'user',
                'content' => $question,
                'date_entered' => $currentTime->format('Y-m-d H:i:s'),
            ]);
            $messagesCreated++;
            
            $currentTime->modify('+' . mt_rand(15, 45) . ' seconds');
            
            // Assistant response
            DB::table('ai_chat_messages')->insert([
                'id' => $this->generateUuid(),
                'conversation_id' => $conversationId,
                'role' => 'assistant',
                'content' => $this->faker->randomElement($responses),
                'date_entered' => $currentTime->format('Y-m-d H:i:s'),
            ]);
            $messagesCreated++;
            
            $currentTime->modify('+' . mt_rand(60, 180) . ' seconds');
        }
        
        // Closing message
        if ($entityType === 'lead' && $topic !== 'support') {
            DB::table('ai_chat_messages')->insert([
                'id' => $this->generateUuid(),
                'conversation_id' => $conversationId,
                'role' => 'assistant',
                'content' => 'Is there anything else I can help you with? Would you like to schedule a demo with our team?',
                'date_entered' => $currentTime->format('Y-m-d H:i:s'),
            ]);
            $messagesCreated++;
            
            $currentTime->modify('+' . mt_rand(30, 60) . ' seconds');
            
            // User response
            $interested = $this->randomProbability(60);
            DB::table('ai_chat_messages')->insert([
                'id' => $this->generateUuid(),
                'conversation_id' => $conversationId,
                'role' => 'user',
                'content' => $interested ? 
                    'Yes, that would be great. How do we schedule a demo?' : 
                    'Not right now, but I\'ll keep you in mind. Thanks!',
                'date_entered' => $currentTime->format('Y-m-d H:i:s'),
            ]);
            $messagesCreated++;
            
            if ($interested) {
                $currentTime->modify('+' . mt_rand(15, 30) . ' seconds');
                DB::table('ai_chat_messages')->insert([
                    'id' => $this->generateUuid(),
                    'conversation_id' => $conversationId,
                    'role' => 'assistant',
                    'content' => 'Perfect! I\'ll have someone from our sales team reach out within the next 24 hours to schedule a demo. They\'ll email you at ' . $entity->email1 . '. Looking forward to showing you TechFlow!',
                    'date_entered' => $currentTime->format('Y-m-d H:i:s'),
                ]);
                $messagesCreated++;
            }
        }
        
        return $messagesCreated;
    }
}