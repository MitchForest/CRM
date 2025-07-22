# Phase 4: AI Integration - Implementation Plan

## Overview

Phase 4 integrates AI capabilities throughout the CRM to provide intelligent insights, automation, and recommendations. We'll use OpenAI's API for natural language processing, implement smart enrichment features, and add predictive analytics to help sales teams work more efficiently.

**Duration**: 2 weeks (Weeks 9-10)  
**Team Size**: 1-2 developers  
**Prerequisites**: Phases 1-3 completed, OpenAI API key

## Week 9: Core AI Infrastructure & Contact Intelligence

### Day 1: AI Service Setup

#### 1. Backend AI Service Layer
```php
// backend/custom/api/services/AIService.php
<?php
namespace Api\Services;

class AIService {
    private $openaiApiKey;
    private $openaiEndpoint = 'https://api.openai.com/v1';
    
    public function __construct() {
        $this->openaiApiKey = $_ENV['OPENAI_API_KEY'];
    }
    
    /**
     * Make a request to OpenAI API
     */
    private function callOpenAI($endpoint, $data) {
        $ch = curl_init($this->openaiEndpoint . $endpoint);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openaiApiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('OpenAI API error: ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Generate text using GPT-4
     */
    public function generateText($prompt, $maxTokens = 500, $temperature = 0.7) {
        $response = $this->callOpenAI('/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful CRM assistant.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);
        
        return $response['choices'][0]['message']['content'];
    }
    
    /**
     * Analyze text sentiment
     */
    public function analyzeSentiment($text) {
        $prompt = "Analyze the sentiment of the following text and respond with only one word: positive, neutral, or negative.\n\nText: $text";
        
        $response = $this->generateText($prompt, 10, 0);
        return trim(strtolower($response));
    }
    
    /**
     * Extract entities from text
     */
    public function extractEntities($text) {
        $prompt = "Extract any company names, product mentions, and key topics from the following text. Return as JSON array with categories: companies, products, topics.\n\nText: $text";
        
        $response = $this->generateText($prompt, 200, 0);
        
        try {
            return json_decode($response, true);
        } catch (\Exception $e) {
            return ['companies' => [], 'products' => [], 'topics' => []];
        }
    }
    
    /**
     * Generate email draft
     */
    public function generateEmailDraft($context) {
        $prompt = "Write a professional email based on the following context:\n";
        $prompt .= "Recipient: {$context['recipient_name']}\n";
        $prompt .= "Purpose: {$context['purpose']}\n";
        $prompt .= "Key Points: " . implode(', ', $context['key_points']) . "\n";
        $prompt .= "Tone: {$context['tone']}\n\n";
        $prompt .= "Write a concise, professional email. Do not include subject line.";
        
        return $this->generateText($prompt, 300);
    }
}
```

#### 2. Enrichment Service
```php
// backend/custom/api/services/EnrichmentService.php
<?php
namespace Api\Services;

class EnrichmentService {
    private $clearbitApiKey;
    private $aiService;
    
    public function __construct() {
        $this->clearbitApiKey = $_ENV['CLEARBIT_API_KEY'];
        $this->aiService = new AIService();
    }
    
    /**
     * Enrich contact data from email
     */
    public function enrichContact($email) {
        $enrichedData = [];
        
        // Try Clearbit first
        if ($this->clearbitApiKey) {
            $clearbitData = $this->fetchClearbitData($email);
            if ($clearbitData) {
                $enrichedData = array_merge($enrichedData, $clearbitData);
            }
        }
        
        // Extract domain for company research
        $domain = $this->extractDomain($email);
        if ($domain) {
            $companyData = $this->enrichCompany($domain);
            if ($companyData) {
                $enrichedData['company'] = $companyData;
            }
        }
        
        // Add AI insights
        if (!empty($enrichedData)) {
            $enrichedData['ai_insights'] = $this->generateInsights($enrichedData);
        }
        
        return $enrichedData;
    }
    
    /**
     * Fetch data from Clearbit
     */
    private function fetchClearbitData($email) {
        if (!$this->clearbitApiKey) return null;
        
        $ch = curl_init("https://person-stream.clearbit.com/v2/combined/find?email=" . urlencode($email));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->clearbitApiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'full_name' => $data['person']['name']['fullName'] ?? '',
                'title' => $data['person']['employment']['title'] ?? '',
                'company_name' => $data['company']['name'] ?? '',
                'company_domain' => $data['company']['domain'] ?? '',
                'company_industry' => $data['company']['industry'] ?? '',
                'company_size' => $data['company']['metrics']['employeesRange'] ?? '',
                'linkedin' => $data['person']['linkedin']['handle'] ?? '',
                'location' => $data['person']['location'] ?? ''
            ];
        }
        
        return null;
    }
    
    /**
     * Enrich company data from domain
     */
    public function enrichCompany($domain) {
        // First try to get basic info from public APIs
        $companyInfo = [
            'domain' => $domain,
            'technologies' => $this->detectTechnologies($domain),
        ];
        
        // Use AI to analyze the website
        $websiteContent = $this->fetchWebsiteContent($domain);
        if ($websiteContent) {
            $aiAnalysis = $this->aiService->generateText(
                "Analyze this company based on their website content and provide: 1) Industry, 2) Company size estimate, 3) Key products/services, 4) Target market. Content: " . substr($websiteContent, 0, 2000)
            );
            
            $companyInfo['ai_analysis'] = $aiAnalysis;
        }
        
        return $companyInfo;
    }
    
    /**
     * Detect technologies used by a domain
     */
    private function detectTechnologies($domain) {
        // This would integrate with services like BuiltWith or Wappalyzer
        // For now, return mock data
        return [
            'cms' => 'Unknown',
            'analytics' => ['Google Analytics'],
            'frameworks' => [],
            'marketing_tools' => []
        ];
    }
    
    /**
     * Generate AI insights from enriched data
     */
    private function generateInsights($data) {
        $prompt = "Based on this contact information, provide 3 actionable sales insights:\n";
        $prompt .= json_encode($data) . "\n";
        $prompt .= "Format as JSON array with keys: insight, action, priority (high/medium/low)";
        
        $response = $this->aiService->generateText($prompt, 300, 0.5);
        
        try {
            return json_decode($response, true);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function extractDomain($email) {
        $parts = explode('@', $email);
        return isset($parts[1]) ? $parts[1] : null;
    }
    
    private function fetchWebsiteContent($domain) {
        // Simplified - in production, use a proper web scraping service
        $url = "https://" . $domain;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 CRM Bot');
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            // Strip HTML tags and get text content
            $content = strip_tags($content);
            $content = preg_replace('/\s+/', ' ', $content);
            return substr($content, 0, 3000); // First 3000 chars
        }
        
        return null;
    }
}
```

#### 3. Frontend AI Service
```typescript
// frontend/src/services/ai-service.ts
import { apiClient } from '@/lib/api-client'

export interface AIInsight {
  insight: string
  action: string
  priority: 'high' | 'medium' | 'low'
}

export interface EnrichmentData {
  full_name?: string
  title?: string
  company_name?: string
  company_domain?: string
  company_industry?: string
  company_size?: string
  linkedin?: string
  location?: string
  technologies?: string[]
  ai_insights?: AIInsight[]
}

export interface EmailSuggestion {
  subject: string
  body: string
  tone: string
  bestSendTime?: string
}

class AIService {
  async enrichContact(contactId: string): Promise<EnrichmentData> {
    const response = await apiClient.post(`/contacts/${contactId}/enrich`)
    return response.data
  }

  async getContactInsights(contactId: string): Promise<AIInsight[]> {
    const response = await apiClient.get(`/contacts/${contactId}/insights`)
    return response.data
  }

  async scoreL
(leadId: string): Promise<{
    score: number
    factors: Array<{ factor: string; impact: number }>
    recommendations: string[]
  }> {
    const response = await apiClient.post(`/leads/${leadId}/score`)
    return response.data
  }

  async generateEmailDraft(params: {
    recipientId: string
    purpose: string
    keyPoints: string[]
    tone?: 'formal' | 'casual' | 'friendly'
  }): Promise<EmailSuggestion> {
    const response = await apiClient.post('/ai/generate-email', params)
    return response.data
  }

  async analyzeEmailSentiment(emailId: string): Promise<{
    sentiment: 'positive' | 'neutral' | 'negative'
    confidence: number
    keyPhrases: string[]
  }> {
    const response = await apiClient.post(`/emails/${emailId}/analyze`)
    return response.data
  }

  async predictChurn(contactId: string): Promise<{
    risk: 'low' | 'medium' | 'high'
    score: number
    factors: string[]
    recommendations: string[]
  }> {
    const response = await apiClient.get(`/contacts/${contactId}/churn-prediction`)
    return response.data
  }

  async getNextBestAction(opportunityId: string): Promise<{
    action: string
    reason: string
    expectedOutcome: string
    priority: 'high' | 'medium' | 'low'
  }> {
    const response = await apiClient.get(`/opportunities/${opportunityId}/next-action`)
    return response.data
  }
}

export const aiService = new AIService()
```

### Day 2-3: Contact Enrichment & Insights UI

#### 1. Contact Insights Component
```typescript
// frontend/src/components/contacts/ContactInsights.tsx
import { useQuery } from '@tanstack/react-query'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import { 
  Lightbulb, 
  TrendingUp, 
  AlertTriangle, 
  Building,
  Globe,
  Users,
  Sparkles,
  RefreshCw
} from 'lucide-react'
import { Contact } from '@/types/api'
import { aiService, AIInsight } from '@/services/ai-service'
import { cn } from '@/lib/utils'

interface ContactInsightsProps {
  contact: Contact
}

const priorityConfig = {
  high: { color: 'text-red-600 bg-red-50', icon: AlertTriangle },
  medium: { color: 'text-yellow-600 bg-yellow-50', icon: TrendingUp },
  low: { color: 'text-green-600 bg-green-50', icon: Lightbulb },
}

export function ContactInsights({ contact }: ContactInsightsProps) {
  const { data: enrichment, isLoading: isEnriching, refetch: refetchEnrichment } = useQuery({
    queryKey: ['contact-enrichment', contact.id],
    queryFn: () => aiService.enrichContact(contact.id),
    staleTime: 24 * 60 * 60 * 1000, // 24 hours
  })

  const { data: insights, isLoading: isLoadingInsights } = useQuery({
    queryKey: ['contact-insights', contact.id],
    queryFn: () => aiService.getContactInsights(contact.id),
  })

  const { data: churnPrediction } = useQuery({
    queryKey: ['contact-churn', contact.id],
    queryFn: () => aiService.predictChurn(contact.id),
    enabled: contact.subscriptionStatus === 'active',
  })

  if (isEnriching || isLoadingInsights) {
    return <InsightsSkeleton />
  }

  return (
    <div className="space-y-6">
      {/* Enrichment Data */}
      {enrichment && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2">
                <Sparkles className="h-5 w-5 text-purple-500" />
                Enriched Information
              </CardTitle>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => refetchEnrichment()}
              >
                <RefreshCw className="h-4 w-4 mr-2" />
                Refresh
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {enrichment.company_name && (
              <div className="flex items-start gap-3">
                <Building className="h-5 w-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="font-medium">{enrichment.company_name}</p>
                  <p className="text-sm text-gray-500">{enrichment.title}</p>
                  {enrichment.company_industry && (
                    <Badge variant="secondary" className="mt-1">
                      {enrichment.company_industry}
                    </Badge>
                  )}
                </div>
              </div>
            )}

            {enrichment.company_size && (
              <div className="flex items-center gap-3">
                <Users className="h-5 w-5 text-gray-400" />
                <div>
                  <p className="text-sm text-gray-500">Company Size</p>
                  <p className="font-medium">{enrichment.company_size}</p>
                </div>
              </div>
            )}

            {enrichment.company_domain && (
              <div className="flex items-center gap-3">
                <Globe className="h-5 w-5 text-gray-400" />
                <a
                  href={`https://${enrichment.company_domain}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-sm text-blue-600 hover:underline"
                >
                  {enrichment.company_domain}
                </a>
              </div>
            )}

            {enrichment.technologies && enrichment.technologies.length > 0 && (
              <div>
                <p className="text-sm font-medium mb-2">Technologies</p>
                <div className="flex flex-wrap gap-2">
                  {enrichment.technologies.map((tech) => (
                    <Badge key={tech} variant="outline">
                      {tech}
                    </Badge>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* AI Insights */}
      {insights && insights.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Lightbulb className="h-5 w-5 text-yellow-500" />
              AI Insights
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {insights.map((insight: AIInsight, index: number) => {
              const config = priorityConfig[insight.priority]
              const Icon = config.icon

              return (
                <div
                  key={index}
                  className={cn(
                    'rounded-lg border p-4 space-y-2',
                    config.color
                  )}
                >
                  <div className="flex items-start gap-3">
                    <Icon className="h-5 w-5 mt-0.5" />
                    <div className="flex-1">
                      <p className="font-medium">{insight.insight}</p>
                      <p className="text-sm mt-1">{insight.action}</p>
                    </div>
                  </div>
                </div>
              )
            })}
          </CardContent>
        </Card>
      )}

      {/* Churn Prediction */}
      {churnPrediction && (
        <Alert className={
          churnPrediction.risk === 'high' ? 'border-red-200 bg-red-50' :
          churnPrediction.risk === 'medium' ? 'border-yellow-200 bg-yellow-50' :
          'border-green-200 bg-green-50'
        }>
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>
            <strong>Churn Risk: {churnPrediction.risk.toUpperCase()}</strong>
            <p className="mt-1 text-sm">Score: {churnPrediction.score}%</p>
            {churnPrediction.factors.length > 0 && (
              <div className="mt-2">
                <p className="text-sm font-medium">Risk Factors:</p>
                <ul className="mt-1 text-sm list-disc list-inside">
                  {churnPrediction.factors.map((factor, i) => (
                    <li key={i}>{factor}</li>
                  ))}
                </ul>
              </div>
            )}
            {churnPrediction.recommendations.length > 0 && (
              <div className="mt-3">
                <p className="text-sm font-medium">Recommendations:</p>
                <ul className="mt-1 text-sm list-disc list-inside">
                  {churnPrediction.recommendations.map((rec, i) => (
                    <li key={i}>{rec}</li>
                  ))}
                </ul>
              </div>
            )}
          </AlertDescription>
        </Alert>
      )}
    </div>
  )
}

function InsightsSkeleton() {
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <Skeleton className="h-6 w-40" />
        </CardHeader>
        <CardContent className="space-y-4">
          <Skeleton className="h-20 w-full" />
          <Skeleton className="h-20 w-full" />
        </CardContent>
      </Card>
    </div>
  )
}
```

#### 2. Lead Scoring Component
```typescript
// frontend/src/components/leads/LeadScoring.tsx
import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import { 
  TrendingUp, 
  TrendingDown, 
  BarChart3,
  RefreshCw,
  Loader2
} from 'lucide-react'
import { Lead } from '@/types/api'
import { aiService } from '@/services/ai-service'
import { cn } from '@/lib/utils'

interface LeadScoringProps {
  lead: Lead
  onScoreUpdate?: (score: number) => void
}

export function LeadScoring({ lead, onScoreUpdate }: LeadScoringProps) {
  const [isRecalculating, setIsRecalculating] = useState(false)

  const { data: scoring, isLoading, refetch } = useQuery({
    queryKey: ['lead-scoring', lead.id],
    queryFn: () => aiService.scoreLead(lead.id),
  })

  const recalculateMutation = useMutation({
    mutationFn: () => aiService.scoreLead(lead.id),
    onSuccess: (data) => {
      if (onScoreUpdate) {
        onScoreUpdate(data.score)
      }
    },
  })

  const handleRecalculate = async () => {
    setIsRecalculating(true)
    await recalculateMutation.mutateAsync()
    await refetch()
    setIsRecalculating(false)
  }

  if (isLoading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center h-40">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </CardContent>
      </Card>
    )
  }

  if (!scoring) return null

  const getScoreColor = (score: number) => {
    if (score >= 80) return 'text-green-600 bg-green-50'
    if (score >= 60) return 'text-yellow-600 bg-yellow-50'
    return 'text-red-600 bg-red-50'
  }

  const getScoreLabel = (score: number) => {
    if (score >= 80) return 'Hot Lead'
    if (score >= 60) return 'Warm Lead'
    if (score >= 40) return 'Cool Lead'
    return 'Cold Lead'
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <BarChart3 className="h-5 w-5" />
            Lead Score Analysis
          </CardTitle>
          <Button
            variant="ghost"
            size="sm"
            onClick={handleRecalculate}
            disabled={isRecalculating}
          >
            {isRecalculating ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <RefreshCw className="h-4 w-4" />
            )}
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Overall Score */}
        <div className="text-center">
          <div className={cn(
            'inline-flex items-center justify-center w-24 h-24 rounded-full text-3xl font-bold',
            getScoreColor(scoring.score)
          )}>
            {scoring.score}
          </div>
          <Badge 
            variant="secondary" 
            className={cn('mt-3', getScoreColor(scoring.score))}
          >
            {getScoreLabel(scoring.score)}
          </Badge>
        </div>

        {/* Score Factors */}
        <div className="space-y-3">
          <p className="text-sm font-medium">Scoring Factors</p>
          {scoring.factors.map((factor, index) => (
            <div key={index} className="space-y-1">
              <div className="flex items-center justify-between text-sm">
                <span>{factor.factor}</span>
                <span className={cn(
                  'font-medium',
                  factor.impact > 0 ? 'text-green-600' : 'text-red-600'
                )}>
                  {factor.impact > 0 ? '+' : ''}{factor.impact}
                </span>
              </div>
              <Progress 
                value={Math.abs(factor.impact)} 
                className="h-2"
              />
            </div>
          ))}
        </div>

        {/* Recommendations */}
        {scoring.recommendations.length > 0 && (
          <div className="space-y-2">
            <p className="text-sm font-medium">AI Recommendations</p>
            <ul className="space-y-2">
              {scoring.recommendations.map((rec, index) => (
                <li key={index} className="flex items-start gap-2">
                  <TrendingUp className="h-4 w-4 text-green-500 mt-0.5" />
                  <span className="text-sm">{rec}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
```

### Day 4-5: Email Intelligence

#### 1. Smart Email Composer
```typescript
// frontend/src/components/emails/SmartEmailComposer.tsx
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { 
  Sparkles, 
  Send, 
  Clock,
  Loader2,
  RefreshCw,
  Wand2
} from 'lucide-react'
import { aiService } from '@/services/ai-service'
import { useDebounce } from '@/hooks/use-debounce'

const emailSchema = z.object({
  to: z.string().email(),
  subject: z.string().min(1),
  body: z.string().min(1),
})

interface SmartEmailComposerProps {
  recipientId?: string
  recipientEmail?: string
  recipientName?: string
  onSend: (email: any) => void
}

export function SmartEmailComposer({ 
  recipientId, 
  recipientEmail, 
  recipientName,
  onSend 
}: SmartEmailComposerProps) {
  const [isGenerating, setIsGenerating] = useState(false)
  const [aiMode, setAiMode] = useState<'manual' | 'assisted'>('manual')
  const [purpose, setPurpose] = useState('')
  const [keyPoints, setKeyPoints] = useState<string[]>([''])
  const [tone, setTone] = useState<'formal' | 'casual' | 'friendly'>('friendly')
  const [bestSendTime, setBestSendTime] = useState<string>()

  const debouncedPurpose = useDebounce(purpose, 500)

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(emailSchema),
    defaultValues: {
      to: recipientEmail || '',
      subject: '',
      body: '',
    },
  })

  const generateEmail = async () => {
    if (!purpose || keyPoints.filter(p => p).length === 0) return

    setIsGenerating(true)
    try {
      const suggestion = await aiService.generateEmailDraft({
        recipientId: recipientId || '',
        purpose,
        keyPoints: keyPoints.filter(p => p),
        tone,
      })

      setValue('subject', suggestion.subject)
      setValue('body', suggestion.body)
      
      if (suggestion.bestSendTime) {
        setBestSendTime(suggestion.bestSendTime)
      }
    } catch (error) {
      console.error('Failed to generate email:', error)
    } finally {
      setIsGenerating(false)
    }
  }

  const updateKeyPoint = (index: number, value: string) => {
    const updated = [...keyPoints]
    updated[index] = value
    setKeyPoints(updated)
  }

  const addKeyPoint = () => {
    setKeyPoints([...keyPoints, ''])
  }

  const removeKeyPoint = (index: number) => {
    setKeyPoints(keyPoints.filter((_, i) => i !== index))
  }

  const onSubmitEmail = (data: any) => {
    onSend({
      ...data,
      scheduledFor: bestSendTime,
      metadata: {
        aiGenerated: aiMode === 'assisted',
        purpose,
        tone,
      },
    })
  }

  return (
    <Card className="w-full max-w-4xl">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Sparkles className="h-5 w-5 text-purple-500" />
          Smart Email Composer
        </CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs value={aiMode} onValueChange={(v) => setAiMode(v as any)}>
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="manual">Manual</TabsTrigger>
            <TabsTrigger value="assisted">AI Assisted</TabsTrigger>
          </TabsList>

          <TabsContent value="manual" className="space-y-4">
            <form onSubmit={handleSubmit(onSubmitEmail)} className="space-y-4">
              <div className="space-y-2">
                <Label>To</Label>
                <Input 
                  {...register('to')} 
                  placeholder="recipient@example.com"
                  defaultValue={recipientEmail}
                />
                {errors.to && (
                  <p className="text-sm text-red-500">{errors.to.message}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label>Subject</Label>
                <Input {...register('subject')} placeholder="Email subject" />
                {errors.subject && (
                  <p className="text-sm text-red-500">{errors.subject.message}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label>Message</Label>
                <Textarea 
                  {...register('body')} 
                  placeholder="Type your message..."
                  rows={10}
                />
                {errors.body && (
                  <p className="text-sm text-red-500">{errors.body.message}</p>
                )}
              </div>

              <Button type="submit" className="w-full">
                <Send className="mr-2 h-4 w-4" />
                Send Email
              </Button>
            </form>
          </TabsContent>

          <TabsContent value="assisted" className="space-y-4">
            <div className="space-y-4 border rounded-lg p-4 bg-purple-50">
              <div className="space-y-2">
                <Label>Purpose of Email</Label>
                <Input
                  value={purpose}
                  onChange={(e) => setPurpose(e.target.value)}
                  placeholder="e.g., Follow up on demo, Schedule meeting, Send proposal"
                />
              </div>

              <div className="space-y-2">
                <Label>Key Points to Include</Label>
                {keyPoints.map((point, index) => (
                  <div key={index} className="flex gap-2">
                    <Input
                      value={point}
                      onChange={(e) => updateKeyPoint(index, e.target.value)}
                      placeholder="Enter a key point"
                    />
                    {keyPoints.length > 1 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => removeKeyPoint(index)}
                      >
                        Remove
                      </Button>
                    )}
                  </div>
                ))}
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={addKeyPoint}
                >
                  Add Key Point
                </Button>
              </div>

              <div className="space-y-2">
                <Label>Tone</Label>
                <Select value={tone} onValueChange={(v) => setTone(v as any)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="formal">Formal</SelectItem>
                    <SelectItem value="casual">Casual</SelectItem>
                    <SelectItem value="friendly">Friendly</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <Button
                type="button"
                onClick={generateEmail}
                disabled={isGenerating || !purpose}
                className="w-full"
              >
                {isGenerating ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Generating...
                  </>
                ) : (
                  <>
                    <Wand2 className="mr-2 h-4 w-4" />
                    Generate Email
                  </>
                )}
              </Button>
            </div>

            <form onSubmit={handleSubmit(onSubmitEmail)} className="space-y-4">
              <div className="space-y-2">
                <Label>To</Label>
                <Input 
                  {...register('to')} 
                  placeholder="recipient@example.com"
                  defaultValue={recipientEmail}
                />
              </div>

              <div className="space-y-2">
                <Label>Subject</Label>
                <div className="flex gap-2">
                  <Input {...register('subject')} placeholder="Email subject" />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={generateEmail}
                    disabled={isGenerating}
                  >
                    <RefreshCw className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              <div className="space-y-2">
                <Label>Message</Label>
                <Textarea 
                  {...register('body')} 
                  placeholder="Generated message will appear here..."
                  rows={10}
                />
              </div>

              {bestSendTime && (
                <Alert>
                  <Clock className="h-4 w-4" />
                  <AlertDescription>
                    Best time to send: <strong>{bestSendTime}</strong>
                  </AlertDescription>
                </Alert>
              )}

              <Button type="submit" className="w-full">
                <Send className="mr-2 h-4 w-4" />
                Send Email
              </Button>
            </form>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  )
}
```

## Week 10: Predictive Analytics & Automation

### Day 1-2: Opportunity Intelligence

#### 1. Opportunity AI Analysis
```typescript
// frontend/src/components/opportunities/OpportunityAIAnalysis.tsx
import { useQuery } from '@tanstack/react-query'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { 
  TrendingUp, 
  AlertTriangle, 
  CheckCircle,
  Clock,
  DollarSign,
  Target,
  Zap
} from 'lucide-react'
import { Opportunity } from '@/types/api'
import { aiService } from '@/services/ai-service'
import { cn } from '@/lib/utils'

interface OpportunityAIAnalysisProps {
  opportunity: Opportunity
}

export function OpportunityAIAnalysis({ opportunity }: OpportunityAIAnalysisProps) {
  const { data: analysis, isLoading } = useQuery({
    queryKey: ['opportunity-analysis', opportunity.id],
    queryFn: async () => {
      // Get comprehensive AI analysis
      const [winProbability, nextAction, risks, timeline] = await Promise.all([
        aiService.predictWinProbability(opportunity.id),
        aiService.getNextBestAction(opportunity.id),
        aiService.analyzeRisks(opportunity.id),
        aiService.predictTimeline(opportunity.id),
      ])

      return {
        winProbability,
        nextAction,
        risks,
        timeline,
      }
    },
  })

  if (isLoading || !analysis) {
    return <Card><CardContent className="p-6">Loading AI analysis...</CardContent></Card>
  }

  const getProbabilityColor = (probability: number) => {
    if (probability >= 75) return 'text-green-600'
    if (probability >= 50) return 'text-yellow-600'
    return 'text-red-600'
  }

  return (
    <div className="space-y-6">
      {/* Win Probability */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Target className="h-5 w-5" />
            Win Probability Analysis
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="text-center">
            <div className={cn(
              'text-4xl font-bold',
              getProbabilityColor(analysis.winProbability.score)
            )}>
              {analysis.winProbability.score}%
            </div>
            <p className="text-sm text-gray-500 mt-1">
              Likelihood of closing this deal
            </p>
          </div>

          <Progress value={analysis.winProbability.score} className="h-3" />

          <div className="space-y-2">
            <p className="text-sm font-medium">Key Factors:</p>
            {analysis.winProbability.factors.map((factor: any, index: number) => (
              <div key={index} className="flex items-center justify-between text-sm">
                <span className="flex items-center gap-2">
                  {factor.positive ? (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  ) : (
                    <AlertTriangle className="h-4 w-4 text-yellow-500" />
                  )}
                  {factor.name}
                </span>
                <span className={factor.positive ? 'text-green-600' : 'text-yellow-600'}>
                  {factor.positive ? '+' : ''}{factor.impact}%
                </span>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Next Best Action */}
      <Alert className="border-blue-200 bg-blue-50">
        <Zap className="h-4 w-4 text-blue-600" />
        <AlertDescription>
          <div className="space-y-2">
            <p className="font-medium text-blue-900">
              Recommended Next Action
            </p>
            <p className="text-blue-800">{analysis.nextAction.action}</p>
            <p className="text-sm text-blue-700">
              <strong>Why:</strong> {analysis.nextAction.reason}
            </p>
            <p className="text-sm text-blue-700">
              <strong>Expected Outcome:</strong> {analysis.nextAction.expectedOutcome}
            </p>
            <Badge 
              variant="secondary" 
              className={cn(
                'mt-2',
                analysis.nextAction.priority === 'high' ? 'bg-red-100 text-red-700' :
                analysis.nextAction.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                'bg-green-100 text-green-700'
              )}
            >
              {analysis.nextAction.priority} priority
            </Badge>
          </div>
        </AlertDescription>
      </Alert>

      {/* Risk Analysis */}
      {analysis.risks.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-yellow-500" />
              Risk Factors
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {analysis.risks.map((risk: any, index: number) => (
                <div key={index} className="flex items-start gap-3 p-3 rounded-lg bg-yellow-50">
                  <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5" />
                  <div className="flex-1">
                    <p className="font-medium text-yellow-900">{risk.factor}</p>
                    <p className="text-sm text-yellow-800 mt-1">{risk.mitigation}</p>
                  </div>
                  <Badge variant="secondary" className="bg-yellow-100 text-yellow-700">
                    {risk.severity}
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Timeline Prediction */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="h-5 w-5" />
            Timeline Prediction
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-500">Predicted Close Date</span>
              <span className="font-medium">{analysis.timeline.predictedCloseDate}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-500">Days to Close</span>
              <span className="font-medium">{analysis.timeline.daysToClose}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-500">Confidence</span>
              <Badge variant="secondary">
                {analysis.timeline.confidence}% confident
              </Badge>
            </div>
            {analysis.timeline.recommendations && (
              <Alert className="mt-3">
                <AlertDescription className="text-sm">
                  {analysis.timeline.recommendations}
                </AlertDescription>
              </Alert>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
```

#### 2. Backend AI Analysis Services
```php
// backend/custom/api/services/OpportunityAIService.php
<?php
namespace Api\Services;

class OpportunityAIService {
    private $aiService;
    private $db;
    
    public function __construct() {
        $this->aiService = new AIService();
        global $db;
        $this->db = $db;
    }
    
    /**
     * Predict win probability for an opportunity
     */
    public function predictWinProbability($opportunityId) {
        $opportunity = \BeanFactory::getBean('Opportunities', $opportunityId);
        
        // Gather historical data
        $historicalData = $this->gatherHistoricalData($opportunity);
        
        // Build factors
        $factors = $this->analyzeWinFactors($opportunity, $historicalData);
        
        // Calculate base score
        $baseScore = $this->calculateBaseScore($factors);
        
        // Get AI enhancement
        $aiPrompt = $this->buildWinProbabilityPrompt($opportunity, $factors);
        $aiAnalysis = $this->aiService->generateText($aiPrompt, 200, 0.3);
        
        // Parse AI response and adjust score
        $finalScore = $this->adjustScoreWithAI($baseScore, $aiAnalysis);
        
        return [
            'score' => $finalScore,
            'factors' => $factors,
            'analysis' => $aiAnalysis
        ];
    }
    
    /**
     * Analyze factors that influence win probability
     */
    private function analyzeWinFactors($opportunity, $historicalData) {
        $factors = [];
        
        // Deal size factor
        $avgDealSize = $historicalData['avg_deal_size'];
        if ($opportunity->amount > $avgDealSize * 1.5) {
            $factors[] = [
                'name' => 'Large deal size',
                'positive' => false,
                'impact' => -10
            ];
        } elseif ($opportunity->amount < $avgDealSize * 0.5) {
            $factors[] = [
                'name' => 'Small deal size',
                'positive' => false,
                'impact' => -5
            ];
        } else {
            $factors[] = [
                'name' => 'Typical deal size',
                'positive' => true,
                'impact' => 5
            ];
        }
        
        // Stage progression
        $daysInStage = $this->getDaysInCurrentStage($opportunity);
        $avgDaysInStage = $historicalData['avg_days_in_stage'][$opportunity->sales_stage] ?? 30;
        
        if ($daysInStage > $avgDaysInStage * 1.5) {
            $factors[] = [
                'name' => 'Stalled in current stage',
                'positive' => false,
                'impact' => -15
            ];
        } elseif ($daysInStage < $avgDaysInStage * 0.5) {
            $factors[] = [
                'name' => 'Fast stage progression',
                'positive' => true,
                'impact' => 10
            ];
        }
        
        // Activity level
        $recentActivities = $this->getRecentActivityCount($opportunity);
        if ($recentActivities > 10) {
            $factors[] = [
                'name' => 'High engagement level',
                'positive' => true,
                'impact' => 15
            ];
        } elseif ($recentActivities < 3) {
            $factors[] = [
                'name' => 'Low engagement level',
                'positive' => false,
                'impact' => -20
            ];
        }
        
        // Contact involvement
        $contactCount = $this->getInvolvedContactsCount($opportunity);
        if ($contactCount >= 3) {
            $factors[] = [
                'name' => 'Multiple stakeholders engaged',
                'positive' => true,
                'impact' => 10
            ];
        } elseif ($contactCount == 1) {
            $factors[] = [
                'name' => 'Single point of contact',
                'positive' => false,
                'impact' => -10
            ];
        }
        
        return $factors;
    }
    
    /**
     * Get next best action for an opportunity
     */
    public function getNextBestAction($opportunityId) {
        $opportunity = \BeanFactory::getBean('Opportunities', $opportunityId);
        
        // Analyze current state
        $state = $this->analyzeOpportunityState($opportunity);
        
        // Build AI prompt
        $prompt = "Based on this opportunity state, recommend the single most impactful next action:\n";
        $prompt .= "Stage: {$opportunity->sales_stage}\n";
        $prompt .= "Days in stage: {$state['days_in_stage']}\n";
        $prompt .= "Last activity: {$state['last_activity_days']} days ago\n";
        $prompt .= "Engagement level: {$state['engagement_level']}\n";
        $prompt .= "Deal size: \${$opportunity->amount}\n\n";
        $prompt .= "Provide: 1) Specific action, 2) Why this action, 3) Expected outcome. Be concise.";
        
        $aiResponse = $this->aiService->generateText($prompt, 150, 0.5);
        
        // Parse response
        $parsed = $this->parseNextActionResponse($aiResponse);
        
        // Determine priority based on opportunity value and stage
        $priority = $this->calculateActionPriority($opportunity, $state);
        
        return [
            'action' => $parsed['action'],
            'reason' => $parsed['reason'],
            'expectedOutcome' => $parsed['outcome'],
            'priority' => $priority
        ];
    }
    
    /**
     * Analyze risks for an opportunity
     */
    public function analyzeRisks($opportunityId) {
        $opportunity = \BeanFactory::getBean('Opportunities', $opportunityId);
        $risks = [];
        
        // Check for common risk factors
        $state = $this->analyzeOpportunityState($opportunity);
        
        // Stalled deals
        if ($state['days_in_stage'] > 30) {
            $risks[] = [
                'factor' => 'Deal momentum has stalled',
                'severity' => 'high',
                'mitigation' => 'Schedule a check-in call to re-engage and understand any blockers'
            ];
        }
        
        // Low engagement
        if ($state['engagement_level'] === 'low') {
            $risks[] = [
                'factor' => 'Low customer engagement',
                'severity' => 'medium',
                'mitigation' => 'Send valuable content or offer a personalized demo to increase engagement'
            ];
        }
        
        // Single-threaded
        if ($state['contact_count'] === 1) {
            $risks[] = [
                'factor' => 'Single-threaded relationship',
                'severity' => 'high',
                'mitigation' => 'Identify and engage additional stakeholders or decision makers'
            ];
        }
        
        // Close date approaching
        $daysUntilClose = (strtotime($opportunity->date_closed) - time()) / 86400;
        if ($daysUntilClose < 14 && !in_array($opportunity->sales_stage, ['Negotiation', 'Closed Won'])) {
            $risks[] = [
                'factor' => 'Close date approaching but not in final stages',
                'severity' => 'high',
                'mitigation' => 'Accelerate the sales process or update the close date to be more realistic'
            ];
        }
        
        return $risks;
    }
    
    /**
     * Helper methods
     */
    private function gatherHistoricalData($opportunity) {
        // Get historical win rates and patterns
        $query = "SELECT 
                    AVG(amount) as avg_deal_size,
                    AVG(DATEDIFF(date_closed, date_entered)) as avg_sales_cycle,
                    sales_stage,
                    COUNT(*) as count,
                    SUM(CASE WHEN sales_stage = 'Closed Won' THEN 1 ELSE 0 END) as wins
                  FROM opportunities 
                  WHERE deleted = 0 
                  AND date_entered > DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY sales_stage";
        
        $result = $this->db->query($query);
        $data = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $data['stages'][$row['sales_stage']] = [
                'count' => $row['count'],
                'win_rate' => $row['wins'] / $row['count']
            ];
        }
        
        // Get average deal size
        $data['avg_deal_size'] = 50000; // Default fallback
        
        return $data;
    }
    
    private function calculateBaseScore($factors) {
        $score = 50; // Start at neutral
        
        foreach ($factors as $factor) {
            $score += $factor['impact'];
        }
        
        // Ensure score is between 0 and 100
        return max(0, min(100, $score));
    }
}
```

### Day 3: Automated Insights Dashboard

#### 1. AI Insights Dashboard Widget
```typescript
// frontend/src/components/dashboard/AIInsightsDashboard.tsx
import { useQuery } from '@tanstack/react-query'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { 
  Brain, 
  TrendingUp, 
  AlertTriangle, 
  Users,
  Sparkles,
  ArrowRight,
  Target,
  Mail
} from 'lucide-react'
import { aiService } from '@/services/ai-service'
import { formatDistanceToNow } from 'date-fns'

export function AIInsightsDashboard() {
  const { data: insights, isLoading } = useQuery({
    queryKey: ['ai-dashboard-insights'],
    queryFn: () => aiService.getDashboardInsights(),
    refetchInterval: 5 * 60 * 1000, // Refresh every 5 minutes
  })

  if (isLoading) {
    return <DashboardSkeleton />
  }

  return (
    <Card className="col-span-full">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Brain className="h-5 w-5 text-purple-500" />
          AI-Powered Insights
        </CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="opportunities" className="space-y-4">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="opportunities">Opportunities</TabsTrigger>
            <TabsTrigger value="leads">Leads</TabsTrigger>
            <TabsTrigger value="contacts">Contacts</TabsTrigger>
            <TabsTrigger value="actions">Actions</TabsTrigger>
          </TabsList>

          <TabsContent value="opportunities" className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              {insights?.opportunities?.map((insight: any, index: number) => (
                <Alert key={index} className="border-purple-200 bg-purple-50">
                  <Target className="h-4 w-4 text-purple-600" />
                  <AlertDescription>
                    <div className="space-y-2">
                      <p className="font-medium text-purple-900">
                        {insight.title}
                      </p>
                      <p className="text-sm text-purple-800">
                        {insight.description}
                      </p>
                      <div className="flex items-center justify-between">
                        <Badge variant="secondary" className="bg-purple-100 text-purple-700">
                          ${insight.value?.toLocaleString()}
                        </Badge>
                        <Button size="sm" variant="ghost" className="text-purple-600">
                          View Details
                          <ArrowRight className="ml-2 h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                  </AlertDescription>
                </Alert>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="leads" className="space-y-4">
            <div className="space-y-3">
              {insights?.leads?.hotLeads?.map((lead: any) => (
                <div key={lead.id} className="flex items-center justify-between rounded-lg border p-4">
                  <div className="flex items-center gap-4">
                    <div className="rounded-full bg-green-100 p-2">
                      <TrendingUp className="h-4 w-4 text-green-600" />
                    </div>
                    <div>
                      <p className="font-medium">{lead.name}</p>
                      <p className="text-sm text-gray-500">
                        Score: {lead.score} • {lead.source}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge variant="secondary" className="bg-green-100 text-green-700">
                      Hot Lead
                    </Badge>
                    <Button size="sm">Contact Now</Button>
                  </div>
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="contacts" className="space-y-4">
            {insights?.contacts?.churnRisks?.length > 0 && (
              <Alert className="border-red-200 bg-red-50">
                <AlertTriangle className="h-4 w-4 text-red-600" />
                <AlertDescription>
                  <p className="font-medium text-red-900 mb-2">
                    {insights.contacts.churnRisks.length} customers at risk of churning
                  </p>
                  <div className="space-y-2">
                    {insights.contacts.churnRisks.map((contact: any) => (
                      <div key={contact.id} className="flex items-center justify-between">
                        <span className="text-sm">{contact.name}</span>
                        <Button size="sm" variant="outline" className="text-red-600">
                          Reach Out
                        </Button>
                      </div>
                    ))}
                  </div>
                </AlertDescription>
              </Alert>
            )}
          </TabsContent>

          <TabsContent value="actions" className="space-y-4">
            <div className="space-y-3">
              {insights?.recommendedActions?.map((action: any, index: number) => (
                <div key={index} className="flex items-start gap-3 rounded-lg border p-4">
                  <div className={`rounded-full p-2 ${
                    action.type === 'email' ? 'bg-blue-100' :
                    action.type === 'call' ? 'bg-green-100' :
                    'bg-purple-100'
                  }`}>
                    {action.type === 'email' ? (
                      <Mail className="h-4 w-4 text-blue-600" />
                    ) : action.type === 'call' ? (
                      <Phone className="h-4 w-4 text-green-600" />
                    ) : (
                      <Sparkles className="h-4 w-4 text-purple-600" />
                    )}
                  </div>
                  <div className="flex-1">
                    <p className="font-medium">{action.title}</p>
                    <p className="text-sm text-gray-600 mt-1">{action.description}</p>
                    <p className="text-xs text-gray-500 mt-2">
                      Due {formatDistanceToNow(new Date(action.dueDate), { addSuffix: true })}
                    </p>
                  </div>
                  <Button size="sm">Take Action</Button>
                </div>
              ))}
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  )
}

function DashboardSkeleton() {
  return (
    <Card className="col-span-full">
      <CardHeader>
        <div className="h-6 w-40 bg-gray-200 rounded animate-pulse" />
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          <div className="h-10 w-full bg-gray-200 rounded animate-pulse" />
          <div className="h-32 w-full bg-gray-200 rounded animate-pulse" />
        </div>
      </CardContent>
    </Card>
  )
}
```

### Day 4: Email Automation & Templates

#### 1. AI Email Templates
```typescript
// frontend/src/components/emails/AIEmailTemplates.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { 
  Mail, 
  Sparkles, 
  Copy, 
  Edit,
  Plus,
  Clock
} from 'lucide-react'
import { aiService } from '@/services/ai-service'

interface EmailTemplate {
  id: string
  name: string
  category: string
  subject: string
  body: string
  variables: string[]
  performance?: {
    openRate: number
    responseRate: number
    timesUsed: number
  }
}

export function AIEmailTemplates() {
  const [selectedTemplate, setSelectedTemplate] = useState<EmailTemplate | null>(null)
  const [isGenerating, setIsGenerating] = useState(false)

  const { data: templates, isLoading } = useQuery({
    queryKey: ['email-templates'],
    queryFn: () => aiService.getEmailTemplates(),
  })

  const generateTemplate = async (category: string) => {
    setIsGenerating(true)
    try {
      const newTemplate = await aiService.generateEmailTemplate({
        category,
        tone: 'professional',
        includePersonalization: true,
      })
      // Refresh templates
    } catch (error) {
      console.error('Failed to generate template:', error)
    } finally {
      setIsGenerating(false)
    }
  }

  const categories = [
    { id: 'followup', name: 'Follow-up', icon: Clock },
    { id: 'introduction', name: 'Introduction', icon: Mail },
    { id: 'proposal', name: 'Proposal', icon: FileText },
    { id: 'nurture', name: 'Nurture', icon: Heart },
  ]

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-purple-500" />
            AI Email Templates
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs defaultValue="followup">
            <TabsList className="grid w-full grid-cols-4">
              {categories.map((category) => (
                <TabsTrigger key={category.id} value={category.id}>
                  <category.icon className="h-4 w-4 mr-2" />
                  {category.name}
                </TabsTrigger>
              ))}
            </TabsList>

            {categories.map((category) => (
              <TabsContent key={category.id} value={category.id} className="space-y-4">
                <div className="flex justify-between items-center">
                  <p className="text-sm text-gray-500">
                    AI-optimized templates for {category.name.toLowerCase()} emails
                  </p>
                  <Button
                    size="sm"
                    onClick={() => generateTemplate(category.id)}
                    disabled={isGenerating}
                  >
                    <Plus className="h-4 w-4 mr-2" />
                    Generate New
                  </Button>
                </div>

                <div className="grid gap-4">
                  {templates
                    ?.filter((t: EmailTemplate) => t.category === category.id)
                    .map((template: EmailTemplate) => (
                      <div
                        key={template.id}
                        className="rounded-lg border p-4 hover:bg-gray-50 cursor-pointer"
                        onClick={() => setSelectedTemplate(template)}
                      >
                        <div className="flex items-start justify-between">
                          <div className="flex-1">
                            <h3 className="font-medium">{template.name}</h3>
                            <p className="text-sm text-gray-600 mt-1">
                              {template.subject}
                            </p>
                            <div className="flex items-center gap-4 mt-3">
                              {template.performance && (
                                <>
                                  <div className="flex items-center gap-1 text-xs text-gray-500">
                                    <Mail className="h-3 w-3" />
                                    {template.performance.openRate}% open
                                  </div>
                                  <div className="flex items-center gap-1 text-xs text-gray-500">
                                    <Reply className="h-3 w-3" />
                                    {template.performance.responseRate}% response
                                  </div>
                                  <div className="flex items-center gap-1 text-xs text-gray-500">
                                    Used {template.performance.timesUsed} times
                                  </div>
                                </>
                              )}
                            </div>
                          </div>
                          <div className="flex gap-2">
                            <Button size="sm" variant="ghost">
                              <Copy className="h-4 w-4" />
                            </Button>
                            <Button size="sm" variant="ghost">
                              <Edit className="h-4 w-4" />
                            </Button>
                          </div>
                        </div>
                        
                        {template.variables.length > 0 && (
                          <div className="flex gap-2 mt-3">
                            {template.variables.map((variable) => (
                              <Badge key={variable} variant="secondary">
                                {`{${variable}}`}
                              </Badge>
                            ))}
                          </div>
                        )}
                      </div>
                    ))}
                </div>
              </TabsContent>
            ))}
          </Tabs>
        </CardContent>
      </Card>

      {/* Template Preview */}
      {selectedTemplate && (
        <Card>
          <CardHeader>
            <CardTitle>Template Preview</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <label className="text-sm font-medium">Subject</label>
              <p className="mt-1 p-3 bg-gray-50 rounded">{selectedTemplate.subject}</p>
            </div>
            <div>
              <label className="text-sm font-medium">Body</label>
              <div className="mt-1 p-3 bg-gray-50 rounded whitespace-pre-wrap">
                {selectedTemplate.body}
              </div>
            </div>
            <div className="flex justify-end gap-3">
              <Button variant="outline" onClick={() => setSelectedTemplate(null)}>
                Close
              </Button>
              <Button>Use Template</Button>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
```

### Day 5: Testing & Integration

#### 1. AI Feature Tests
```typescript
// frontend/src/__tests__/ai-features.test.tsx
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ContactInsights } from '@/components/contacts/ContactInsights'
import { LeadScoring } from '@/components/leads/LeadScoring'
import { aiService } from '@/services/ai-service'

// Mock AI service
vi.mock('@/services/ai-service')

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
  },
})

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={queryClient}>
    {children}
  </QueryClientProvider>
)

describe('AI Features', () => {
  describe('Contact Insights', () => {
    it('displays enrichment data when available', async () => {
      const mockContact = {
        id: '1',
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
      }

      const mockEnrichment = {
        company_name: 'Example Corp',
        title: 'CEO',
        company_size: '50-100',
      }

      vi.mocked(aiService.enrichContact).mockResolvedValue(mockEnrichment)

      render(<ContactInsights contact={mockContact} />, { wrapper })

      await waitFor(() => {
        expect(screen.getByText('Example Corp')).toBeInTheDocument()
        expect(screen.getByText('CEO')).toBeInTheDocument()
      })
    })

    it('shows churn prediction for active customers', async () => {
      const mockContact = {
        id: '1',
        subscriptionStatus: 'active',
      }

      const mockChurnPrediction = {
        risk: 'high',
        score: 75,
        factors: ['No activity in 30 days'],
        recommendations: ['Schedule a check-in call'],
      }

      vi.mocked(aiService.predictChurn).mockResolvedValue(mockChurnPrediction)

      render(<ContactInsights contact={mockContact} />, { wrapper })

      await waitFor(() => {
        expect(screen.getByText(/Churn Risk: HIGH/)).toBeInTheDocument()
        expect(screen.getByText('No activity in 30 days')).toBeInTheDocument()
      })
    })
  })

  describe('Lead Scoring', () => {
    it('displays lead score and factors', async () => {
      const mockLead = {
        id: '1',
        firstName: 'Jane',
        lastName: 'Smith',
      }

      const mockScoring = {
        score: 85,
        factors: [
          { factor: 'Email engagement', impact: 20 },
          { factor: 'Company size match', impact: 15 },
        ],
        recommendations: ['Follow up within 24 hours'],
      }

      vi.mocked(aiService.scoreLead).mockResolvedValue(mockScoring)

      render(<LeadScoring lead={mockLead} />, { wrapper })

      await waitFor(() => {
        expect(screen.getByText('85')).toBeInTheDocument()
        expect(screen.getByText('Hot Lead')).toBeInTheDocument()
        expect(screen.getByText('Email engagement')).toBeInTheDocument()
      })
    })
  })
})
```

#### 2. AI Settings Page
```typescript
// frontend/src/components/settings/AISettings.tsx
import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { 
  Brain, 
  Sparkles, 
  Shield,
  AlertTriangle,
  Save
} from 'lucide-react'

export function AISettings() {
  const [settings, setSettings] = useState({
    enableAI: true,
    autoEnrichContacts: true,
    autoScoreLeads: true,
    emailSuggestions: true,
    churnPrediction: true,
    opportunityAnalysis: true,
    aiProvider: 'openai',
    apiKey: '',
  })

  const handleSave = async () => {
    // Save settings
    console.log('Saving AI settings:', settings)
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>AI Configuration</CardTitle>
          <CardDescription>
            Configure AI features and integrations
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label>Enable AI Features</Label>
              <p className="text-sm text-gray-500">
                Master switch for all AI functionality
              </p>
            </div>
            <Switch
              checked={settings.enableAI}
              onCheckedChange={(checked) => 
                setSettings({ ...settings, enableAI: checked })
              }
            />
          </div>

          {settings.enableAI && (
            <>
              <div className="space-y-4 border-t pt-4">
                <h3 className="text-sm font-medium">Feature Settings</h3>
                
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label>Auto-enrich Contacts</Label>
                      <p className="text-sm text-gray-500">
                        Automatically enrich new contacts with company data
                      </p>
                    </div>
                    <Switch
                      checked={settings.autoEnrichContacts}
                      onCheckedChange={(checked) => 
                        setSettings({ ...settings, autoEnrichContacts: checked })
                      }
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label>Auto-score Leads</Label>
                      <p className="text-sm text-gray-500">
                        Calculate lead scores automatically
                      </p>
                    </div>
                    <Switch
                      checked={settings.autoScoreLeads}
                      onCheckedChange={(checked) => 
                        setSettings({ ...settings, autoScoreLeads: checked })
                      }
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label>Email Suggestions</Label>
                      <p className="text-sm text-gray-500">
                        AI-powered email drafting and suggestions
                      </p>
                    </div>
                    <Switch
                      checked={settings.emailSuggestions}
                      onCheckedChange={(checked) => 
                        setSettings({ ...settings, emailSuggestions: checked })
                      }
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label>Churn Prediction</Label>
                      <p className="text-sm text-gray-500">
                        Predict customer churn risk
                      </p>
                    </div>
                    <Switch
                      checked={settings.churnPrediction}
                      onCheckedChange={(checked) => 
                        setSettings({ ...settings, churnPrediction: checked })
                      }
                    />
                  </div>
                </div>
              </div>

              <div className="space-y-4 border-t pt-4">
                <h3 className="text-sm font-medium">API Configuration</h3>
                
                <Alert>
                  <Shield className="h-4 w-4" />
                  <AlertDescription>
                    Your API key is encrypted and stored securely
                  </AlertDescription>
                </Alert>

                <div className="space-y-2">
                  <Label>OpenAI API Key</Label>
                  <Input
                    type="password"
                    value={settings.apiKey}
                    onChange={(e) => 
                      setSettings({ ...settings, apiKey: e.target.value })
                    }
                    placeholder="sk-..."
                  />
                  <p className="text-xs text-gray-500">
                    Required for AI features. Get your key from OpenAI dashboard.
                  </p>
                </div>
              </div>
            </>
          )}

          <div className="flex justify-end">
            <Button onClick={handleSave}>
              <Save className="mr-2 h-4 w-4" />
              Save Settings
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>AI Usage & Limits</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex justify-between">
              <span className="text-sm">API Calls This Month</span>
              <span className="text-sm font-medium">1,234 / 10,000</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div className="bg-blue-500 h-2 rounded-full" style={{ width: '12.34%' }} />
            </div>
            <p className="text-xs text-gray-500">
              Resets on the 1st of each month
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
```

## Deliverables Checklist

### Week 9 Deliverables
- [ ] AI Service infrastructure
  - [ ] OpenAI integration
  - [ ] Enrichment service
  - [ ] Sentiment analysis
- [ ] Contact Intelligence
  - [ ] Enrichment UI
  - [ ] AI insights display
  - [ ] Churn prediction
- [ ] Lead Scoring
  - [ ] Scoring algorithm
  - [ ] Visual score display
  - [ ] Recommendations
- [ ] Email Intelligence
  - [ ] Smart composer
  - [ ] Draft generation
  - [ ] Sentiment analysis

### Week 10 Deliverables
- [ ] Opportunity Intelligence
  - [ ] Win probability prediction
  - [ ] Next best action
  - [ ] Risk analysis
  - [ ] Timeline prediction
- [ ] AI Dashboard
  - [ ] Insights widget
  - [ ] Recommended actions
  - [ ] Performance metrics
- [ ] Email Automation
  - [ ] Template generation
  - [ ] Performance tracking
- [ ] Settings & Configuration
  - [ ] AI settings page
  - [ ] API key management
  - [ ] Usage tracking

## Performance Considerations

### AI Response Times
- Cache enrichment data for 24 hours
- Queue background jobs for scoring
- Implement request throttling
- Show loading states during AI processing

### Cost Management
- Track API usage per user
- Implement monthly limits
- Cache AI responses when possible
- Batch similar requests

## Security Considerations

1. **API Keys**
   - Store encrypted in database
   - Never expose in frontend
   - Use environment variables

2. **Data Privacy**
   - Anonymize data sent to AI
   - Allow users to opt-out
   - Comply with GDPR/CCPA

3. **Rate Limiting**
   - Implement per-user limits
   - Prevent API abuse
   - Monitor usage patterns

## Next Steps

After completing Phase 4, you'll have:
1. Fully AI-powered CRM experience
2. Intelligent insights throughout the application
3. Automated email generation and scoring
4. Predictive analytics for sales success
5. Complete B2C CRM ready for production

The CRM now provides significant competitive advantages through AI, helping sales teams work smarter and close more deals.