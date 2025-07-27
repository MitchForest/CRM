<?php

namespace App\Services\AI;

use App\Models\Lead;
use App\Models\LeadScore;
use App\Services\AI\OpenAIService;

class LeadScoringService
{
    private array $scoringWeights = [
        'company_size' => 0.20,
        'job_title' => 0.20,
        'engagement' => 0.25,
        'fit_score' => 20,
        'intent_signals' => 0.15
    ];
    
    public function __construct(
        private OpenAIService $openAI
    ) {}
    
    /**
     * Score a lead using AI and behavioral data
     */
    public function scoreLead(Lead $lead): LeadScore
    {
        $factors = $this->calculateFactors($lead);
        $score = $this->calculateScore($factors);
        
        // Save score history
        $leadScore = LeadScore::create([
            'lead_id' => $lead->id,
            'score' => $score,
            'factors' => $factors,
            'model_version' => '1.0',
            'date_scored' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
        
        return $leadScore;
    }
    
    /**
     * Calculate scoring factors
     */
    private function calculateFactors(Lead $lead): array
    {
        $factors = [];
        
        // Company size score (based on email domain)
        $factors['company_size'] = $this->scoreCompanySize($lead);
        
        // Job title score
        $factors['job_title'] = $this->scoreJobTitle($lead);
        
        // Engagement score (based on activity)
        $factors['engagement'] = $this->scoreEngagement($lead);
        
        // Fit score (ICP match)
        $factors['fit_score'] = $this->scoreFit($lead);
        
        // Intent signals
        $factors['intent_signals'] = $this->scoreIntent($lead);
        
        // AI insights
        $factors['ai_insights'] = $this->getAIInsights($lead);
        
        return $factors;
    }
    
    /**
     * Calculate final score
     */
    private function calculateScore(array $factors): float
    {
        $score = 0;
        
        foreach ($this->scoringWeights as $factor => $weight) {
            $score += ($factors[$factor]['score'] ?? 0) * $weight;
        }
        
        return round($score, 2);
    }
    
    /**
     * Score based on company size
     */
    private function scoreCompanySize(Lead $lead): array
    {
        if (!$lead->email) {
            return ['score' => 30, 'reason' => 'No email provided'];
        }
        
        $domain = substr(strrchr($lead->email, "@"), 1);
        
        // Check for enterprise domains
        $enterpriseDomains = ['microsoft.com', 'google.com', 'amazon.com', 'apple.com'];
        if (in_array($domain, $enterpriseDomains)) {
            return ['score' => 1.0, 'reason' => 'Enterprise company'];
        }
        
        // Check for free email
        $freeEmails = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        if (in_array($domain, $freeEmails)) {
            return ['score' => 20, 'reason' => 'Free email provider'];
        }
        
        // Corporate email
        return ['score' => 70, 'reason' => 'Corporate email domain'];
    }
    
    /**
     * Score based on job title
     */
    private function scoreJobTitle(Lead $lead): array
    {
        if (!$lead->title) {
            return ['score' => 30, 'reason' => 'No title provided'];
        }
        
        $title = strtolower($lead->title);
        
        // Decision makers
        if (preg_match('/(ceo|cto|cfo|president|owner|founder|vp|vice president|director)/i', $title)) {
            return ['score' => 1.0, 'reason' => 'Decision maker'];
        }
        
        // Influencers
        if (preg_match('/(manager|head|lead|senior|principal)/i', $title)) {
            return ['score' => 70, 'reason' => 'Influencer'];
        }
        
        // Technical roles
        if (preg_match('/(engineer|developer|architect|analyst)/i', $title)) {
            return ['score' => 50, 'reason' => 'Technical role'];
        }
        
        return ['score' => 30, 'reason' => 'Other role'];
    }
    
    /**
     * Score based on engagement
     */
    private function scoreEngagement(Lead $lead): array
    {
        $engagement = [
            'sessions' => $lead->sessions()->count(),
            'page_views' => $lead->sessions()->sum('page_views'),
            'forms' => $lead->formSubmissions()->count(),
            'chats' => $lead->conversations()->count(),
            'time_on_site' => $lead->sessions()->sum('duration')
        ];
        
        $score = 0;
        
        // Multiple sessions = high interest
        if ($engagement['sessions'] >= 3) $score += 30;
        elseif ($engagement['sessions'] >= 1) $score += 10;
        
        // Page views indicate research
        if ($engagement['page_views'] >= 10) $score += 20;
        elseif ($engagement['page_views'] >= 5) $score += 10;
        
        // Form submissions = strong intent
        if ($engagement['forms'] > 0) $score += 30;
        
        // Chat engagement = immediate need
        if ($engagement['chats'] > 0) $score += 20;
        
        return [
            'score' => min($score, 1.0),
            'reason' => 'Engagement metrics',
            'details' => $engagement
        ];
    }
    
    /**
     * Score based on ICP fit
     */
    private function scoreFit(Lead $lead): array
    {
        $fitScore = 0;
        $reasons = [];
        
        // Industry fit (software/tech companies score higher)
        if ($lead->company && preg_match('/(software|tech|saas|cloud|digital)/i', $lead->company)) {
            $fitScore += 0.4;
            $reasons[] = 'Tech industry';
        }
        
        // Company name provided
        if ($lead->company) {
            $fitScore += 0.2;
            $reasons[] = 'Company identified';
        }
        
        // Has website
        if ($lead->website) {
            $fitScore += 0.2;
            $reasons[] = 'Website provided';
        }
        
        // Quality source
        if (in_array($lead->source, ['Demo Request', 'Contact Form', 'Webinar'])) {
            $fitScore += 0.2;
            $reasons[] = 'High-intent source';
        }
        
        return [
            'score' => min($fitScore, 1.0),
            'reason' => implode(', ', $reasons) ?: 'Basic fit'
        ];
    }
    
    /**
     * Score based on intent signals
     */
    private function scoreIntent(Lead $lead): array
    {
        $intentScore = 0;
        $signals = [];
        
        // Check pages viewed
        $pageViews = $lead->sessions()
            ->join('activity_tracking_page_views', 'activity_tracking_sessions.session_id', '=', 'activity_tracking_page_views.session_id')
            ->pluck('page_url');
        
        foreach ($pageViews as $url) {
            if (strpos($url, '/pricing') !== false) {
                $intentScore += 0.3;
                $signals[] = 'Viewed pricing';
            }
            if (strpos($url, '/features') !== false) {
                $intentScore += 0.2;
                $signals[] = 'Viewed features';
            }
            if (strpos($url, '/demo') !== false) {
                $intentScore += 0.3;
                $signals[] = 'Viewed demo page';
            }
            if (strpos($url, '/docs') !== false) {
                $intentScore += 0.2;
                $signals[] = 'Viewed documentation';
            }
        }
        
        // Recent activity
        $lastActivity = $lead->sessions()->latest('started_at')->first();
        if ($lastActivity && strtotime($lastActivity->started_at) > strtotime('-7 days')) {
            $intentScore += 0.2;
            $signals[] = 'Recent activity';
        }
        
        return [
            'score' => min($intentScore, 1.0),
            'reason' => implode(', ', array_unique($signals)) ?: 'No strong signals'
        ];
    }
    
    /**
     * Get AI insights about the lead
     */
    private function getAIInsights(Lead $lead): array
    {
        try {
            $prompt = "Analyze this lead for sales potential:\n" .
                     "Name: {$lead->full_name}\n" .
                     "Company: {$lead->company}\n" .
                     "Title: {$lead->title}\n" .
                     "Email: {$lead->email}\n" .
                     "Source: {$lead->source}\n" .
                     "Sessions: {$lead->sessions()->count()}\n" .
                     "Page views: {$lead->sessions()->sum('page_views')}\n" .
                     "\nProvide: 1) Sales potential (high/medium/low), 2) Recommended next action, 3) Key talking points";
            
            $response = $this->openAI->complete($prompt, [
                'max_tokens' => 150,
                'temperature' => 0.3
            ]);
            
            return [
                'analysis' => $response,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'analysis' => 'AI analysis unavailable',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk score multiple leads
     */
    public function bulkScore(array $leadIds): array
    {
        $results = [];
        
        foreach ($leadIds as $leadId) {
            try {
                $lead = Lead::find($leadId);
                if ($lead) {
                    $results[$leadId] = $this->scoreLead($lead);
                }
            } catch (\Exception $e) {
                $results[$leadId] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
}