# Phase 3 - Frontend Implementation Guide

## Overview
Phase 3 implements the AI-powered features and custom modules that differentiate this CRM: AI lead scoring with OpenAI integration, drag-drop form builder, knowledge base for self-service, basic AI chatbot, and website activity tracking. These features work together to automate lead qualification and provide self-service capabilities.

## Prerequisites
- Phase 1 and Phase 2 completed and working
- OpenAI API key available
- Backend custom modules configured
- Docker environment running

## Step-by-Step Implementation

### 1. Additional Dependencies

#### 1.1 Install Required Packages
```bash
cd frontend
# Form builder drag-drop
npm install @dnd-kit/modifiers

# Rich text editor for knowledge base
npm install @tiptap/react @tiptap/starter-kit @tiptap/extension-link @tiptap/extension-image

# Code syntax highlighting
npm install react-syntax-highlighter @types/react-syntax-highlighter

# Markdown processing
npm install react-markdown remark-gfm

# Chat UI components
npm install react-intersection-observer

# Heatmap visualization
npm install heatmap.js @types/heatmap.js

# Copy to clipboard
npm install react-copy-to-clipboard @types/react-copy-to-clipboard
```

### 2. Type Definitions for Custom Features

#### 2.1 Create Custom Feature Types
`src/types/custom.ts`:
```typescript
// AI Lead Scoring
export interface AIScoreResult {
  score: number;
  factors: {
    company_size: number;
    industry_match: number;
    behavior_score: number;
    engagement: number;
    budget_signals: number;
  };
  insights: string[];
  recommended_actions: string[];
  confidence: number;
}

// Form Builder
export interface FormField {
  id: string;
  type: 'text' | 'email' | 'tel' | 'select' | 'checkbox' | 'radio' | 'textarea';
  label: string;
  placeholder?: string;
  required: boolean;
  options?: { label: string; value: string }[];
  validation?: {
    pattern?: string;
    minLength?: number;
    maxLength?: number;
  };
  conditionalLogic?: {
    field: string;
    operator: 'equals' | 'not_equals' | 'contains';
    value: string;
  };
}

export interface Form {
  id: string;
  name: string;
  description?: string;
  fields: FormField[];
  settings: {
    submitButtonText: string;
    successMessage: string;
    redirectUrl?: string;
    notificationEmail?: string;
    styling: {
      theme: 'light' | 'dark';
      primaryColor: string;
      fontFamily: string;
    };
  };
  embed_code: string;
  created_by: string;
  date_created: string;
  submissions_count: number;
}

export interface FormSubmission {
  id: string;
  form_id: string;
  data: Record<string, any>;
  ip_address: string;
  user_agent: string;
  date_submitted: string;
  lead_id?: string;
}

// Knowledge Base
export interface KBCategory {
  id: string;
  name: string;
  slug: string;
  description?: string;
  parent_id?: string;
  article_count: number;
  icon?: string;
}

export interface KBArticle {
  id: string;
  title: string;
  slug: string;
  content: string;
  excerpt?: string;
  category_id: string;
  category_name: string;
  tags: string[];
  is_public: boolean;
  is_featured: boolean;
  views: number;
  helpful_yes: number;
  helpful_no: number;
  author_id: string;
  author_name: string;
  date_created: string;
  date_modified: string;
  related_articles?: KBArticle[];
}

// AI Chatbot
export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: string;
  metadata?: {
    intent?: 'support' | 'sales' | 'general';
    confidence?: number;
    suggested_articles?: string[];
    lead_score?: number;
  };
}

export interface ChatSession {
  id: string;
  visitor_id: string;
  lead_id?: string;
  messages: ChatMessage[];
  context: {
    page_url?: string;
    referrer?: string;
    user_info?: Partial<Lead>;
  };
  status: 'active' | 'ended';
  date_created: string;
}

// Activity Tracking
export interface PageView {
  url: string;
  title: string;
  timestamp: string;
  duration: number;
  scroll_depth: number;
  clicks: number;
}

export interface WebsiteSession {
  id: string;
  visitor_id: string;
  lead_id?: string;
  ip_address: string;
  user_agent: string;
  referrer?: string;
  pages_viewed: PageView[];
  total_time: number;
  is_active: boolean;
  date_created: string;
  location?: {
    country: string;
    city: string;
  };
}

export interface ActivityHeatmap {
  page_url: string;
  click_data: Array<{
    x: number;
    y: number;
    value: number;
  }>;
  scroll_data: Array<{
    depth: number;
    percentage: number;
  }>;
  date_range: {
    start: string;
    end: string;
  };
}
```

### 3. Service Layer for Custom Features

#### 3.1 Create AI Service
`src/services/ai.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Lead, AIScoreResult } from '@/types';

export const aiService = {
  async scoreLead(leadId: string): Promise<AIScoreResult> {
    const response = await api.post(`/ai/score-lead`, { lead_id: leadId });
    return response.data;
  },

  async batchScoreLeads(leadIds: string[]): Promise<Record<string, AIScoreResult>> {
    const response = await api.post('/ai/batch-score-leads', { lead_ids: leadIds });
    return response.data;
  },

  async getScoreHistory(leadId: string) {
    const response = await api.get(`/ai/score-history/${leadId}`);
    return response.data;
  },

  async chatCompletion(messages: ChatMessage[], context?: any) {
    const response = await api.post('/ai/chat', {
      messages,
      context,
    });
    return response.data;
  },

  async searchKnowledgeBase(query: string, limit = 5) {
    const response = await api.post('/ai/kb-search', {
      query,
      limit,
    });
    return response.data;
  },
};
```

#### 3.2 Create Form Builder Service
`src/services/formBuilder.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Form, FormSubmission, ApiResponse } from '@/types';

export const formBuilderService = {
  async getAllForms(params?: { page?: number; limit?: number }) {
    const response = await api.get<ApiResponse<Form[]>>('/forms', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
      },
    });
    return response.data;
  },

  async getForm(id: string) {
    const response = await api.get<Form>(`/forms/${id}`);
    return response.data;
  },

  async createForm(data: Partial<Form>) {
    const response = await api.post<Form>('/forms', data);
    return response.data;
  },

  async updateForm(id: string, data: Partial<Form>) {
    const response = await api.put<Form>(`/forms/${id}`, data);
    return response.data;
  },

  async deleteForm(id: string) {
    await api.delete(`/forms/${id}`);
  },

  async getFormSubmissions(formId: string, params?: { page?: number; limit?: number }) {
    const response = await api.get<ApiResponse<FormSubmission[]>>(`/forms/${formId}/submissions`, {
      params,
    });
    return response.data;
  },

  async submitForm(formId: string, data: Record<string, any>) {
    const response = await api.post(`/forms/${formId}/submit`, data);
    return response.data;
  },

  async generateEmbedCode(formId: string, options?: { theme?: string; container?: string }) {
    const response = await api.post(`/forms/${formId}/embed-code`, options);
    return response.data;
  },
};
```

#### 3.3 Create Knowledge Base Service
`src/services/knowledgeBase.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { KBArticle, KBCategory, ApiResponse } from '@/types';

export const knowledgeBaseService = {
  // Categories
  async getCategories() {
    const response = await api.get<KBCategory[]>('/kb/categories');
    return response.data;
  },

  async createCategory(data: Partial<KBCategory>) {
    const response = await api.post<KBCategory>('/kb/categories', data);
    return response.data;
  },

  // Articles
  async getArticles(params?: {
    page?: number;
    limit?: number;
    category_id?: string;
    is_public?: boolean;
    search?: string;
  }) {
    const response = await api.get<ApiResponse<KBArticle[]>>('/kb/articles', { params });
    return response.data;
  },

  async getArticle(id: string) {
    const response = await api.get<KBArticle>(`/kb/articles/${id}`);
    return response.data;
  },

  async getArticleBySlug(slug: string) {
    const response = await api.get<KBArticle>(`/kb/articles/slug/${slug}`);
    return response.data;
  },

  async createArticle(data: Partial<KBArticle>) {
    const response = await api.post<KBArticle>('/kb/articles', data);
    return response.data;
  },

  async updateArticle(id: string, data: Partial<KBArticle>) {
    const response = await api.put<KBArticle>(`/kb/articles/${id}`, data);
    return response.data;
  },

  async deleteArticle(id: string) {
    await api.delete(`/kb/articles/${id}`);
  },

  async rateArticle(id: string, helpful: boolean) {
    const response = await api.post(`/kb/articles/${id}/rate`, { helpful });
    return response.data;
  },

  async trackView(id: string) {
    await api.post(`/kb/articles/${id}/view`);
  },

  async searchArticles(query: string, limit = 10) {
    const response = await api.get<KBArticle[]>('/kb/search', {
      params: { q: query, limit },
    });
    return response.data;
  },
};
```

#### 3.4 Create Activity Tracking Service
`src/services/activityTracking.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { WebsiteSession, ActivityHeatmap, ApiResponse } from '@/types';

export const activityTrackingService = {
  async getSessions(params?: {
    page?: number;
    limit?: number;
    lead_id?: string;
    active_only?: boolean;
  }) {
    const response = await api.get<ApiResponse<WebsiteSession[]>>('/tracking/sessions', {
      params,
    });
    return response.data;
  },

  async getSession(id: string) {
    const response = await api.get<WebsiteSession>(`/tracking/sessions/${id}`);
    return response.data;
  },

  async getLiveVisitors() {
    const response = await api.get<WebsiteSession[]>('/tracking/live');
    return response.data;
  },

  async getHeatmapData(pageUrl: string, dateRange?: { start: string; end: string }) {
    const response = await api.get<ActivityHeatmap>('/tracking/heatmap', {
      params: { page_url: pageUrl, ...dateRange },
    });
    return response.data;
  },

  async identifyVisitor(visitorId: string, leadId: string) {
    const response = await api.post('/tracking/identify', {
      visitor_id: visitorId,
      lead_id: leadId,
    });
    return response.data;
  },

  async getVisitorTimeline(visitorId: string) {
    const response = await api.get(`/tracking/visitors/${visitorId}/timeline`);
    return response.data;
  },
};
```

### 4. AI Lead Scoring Components

#### 4.1 Create AI Score Display Component
`src/components/features/AIScoreDisplay.tsx`:
```typescript
import { useState } from 'react';
import { Brain, TrendingUp, AlertCircle, CheckCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { AIScoreResult } from '@/types/custom';
import { cn } from '@/lib/utils';

interface AIScoreDisplayProps {
  score?: AIScoreResult;
  isLoading?: boolean;
  onRefresh?: () => void;
}

export function AIScoreDisplay({ score, isLoading, onRefresh }: AIScoreDisplayProps) {
  const [showDetails, setShowDetails] = useState(false);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            AI Lead Score
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-32 w-full" />
        </CardContent>
      </Card>
    );
  }

  if (!score) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            AI Lead Score
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground">No score available</p>
          {onRefresh && (
            <Button onClick={onRefresh} variant="outline" size="sm" className="mt-2">
              Calculate Score
            </Button>
          )}
        </CardContent>
      </Card>
    );
  }

  const getScoreColor = (value: number) => {
    if (value >= 80) return 'text-green-600';
    if (value >= 60) return 'text-yellow-600';
    if (value >= 40) return 'text-orange-600';
    return 'text-red-600';
  };

  const getScoreLabel = (value: number) => {
    if (value >= 80) return 'Hot Lead';
    if (value >= 60) return 'Warm Lead';
    if (value >= 40) return 'Cool Lead';
    return 'Cold Lead';
  };

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            AI Lead Score
          </CardTitle>
          {onRefresh && (
            <Button onClick={onRefresh} variant="ghost" size="sm">
              Refresh
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Main Score */}
        <div className="text-center">
          <div className={cn("text-5xl font-bold", getScoreColor(score.score))}>
            {score.score}
          </div>
          <Badge variant="secondary" className="mt-1">
            {getScoreLabel(score.score)}
          </Badge>
          <p className="text-sm text-muted-foreground mt-1">
            Confidence: {Math.round(score.confidence * 100)}%
          </p>
        </div>

        {/* Score Factors */}
        <div className="space-y-2">
          <button
            onClick={() => setShowDetails(!showDetails)}
            className="w-full text-left text-sm font-medium hover:text-primary"
          >
            Score Breakdown {showDetails ? '▼' : '▶'}
          </button>
          
          {showDetails && (
            <div className="space-y-2 pt-2">
              {Object.entries(score.factors).map(([key, value]) => (
                <div key={key} className="flex items-center justify-between">
                  <span className="text-sm capitalize">
                    {key.replace(/_/g, ' ')}
                  </span>
                  <div className="flex items-center gap-2">
                    <Progress value={(value / 20) * 100} className="w-24" />
                    <span className="text-sm font-medium w-8">{value}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* AI Insights */}
        {score.insights.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium">AI Insights</h4>
            {score.insights.map((insight, index) => (
              <div key={index} className="flex items-start gap-2">
                <AlertCircle className="h-4 w-4 text-yellow-500 mt-0.5" />
                <p className="text-sm text-muted-foreground">{insight}</p>
              </div>
            ))}
          </div>
        )}

        {/* Recommended Actions */}
        {score.recommended_actions.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium">Recommended Actions</h4>
            {score.recommended_actions.map((action, index) => (
              <div key={index} className="flex items-start gap-2">
                <CheckCircle className="h-4 w-4 text-green-500 mt-0.5" />
                <p className="text-sm text-muted-foreground">{action}</p>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
```

#### 4.2 Create Lead Scoring Dashboard
`src/pages/Leads/LeadScoringDashboard.tsx`:
```typescript
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Brain, RefreshCw, TrendingUp, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { AIScoreDisplay } from '@/components/features/AIScoreDisplay';
import { leadService } from '@/services/lead.service';
import { aiService } from '@/services/ai.service';
import { useToast } from '@/components/ui/use-toast';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

export function LeadScoringDashboard() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [selectedLeadId, setSelectedLeadId] = useState<string | null>(null);

  const { data: leads, isLoading: isLoadingLeads } = useQuery({
    queryKey: ['leads', 'unscored'],
    queryFn: () => leadService.getAll({ filter: 'ai_score:null', limit: 100 }),
  });

  const { data: selectedScore, isLoading: isLoadingScore } = useQuery({
    queryKey: ['ai-score', selectedLeadId],
    queryFn: () => aiService.scoreLead(selectedLeadId!),
    enabled: !!selectedLeadId,
  });

  const scoreMutation = useMutation({
    mutationFn: (leadId: string) => aiService.scoreLead(leadId),
    onSuccess: (data, leadId) => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      queryClient.setQueryData(['ai-score', leadId], data);
      toast({
        title: 'Lead scored successfully',
        description: `AI Score: ${data.score}/100`,
      });
    },
    onError: () => {
      toast({
        title: 'Scoring failed',
        description: 'Unable to calculate AI score. Please try again.',
        variant: 'destructive',
      });
    },
  });

  const batchScoreMutation = useMutation({
    mutationFn: (leadIds: string[]) => aiService.batchScoreLeads(leadIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      toast({
        title: 'Batch scoring complete',
        description: 'All selected leads have been scored.',
      });
    },
  });

  const unscoredLeads = leads?.data.filter(lead => !lead.ai_score) || [];
  const scoredLeads = leads?.data.filter(lead => lead.ai_score) || [];

  const scoreDistribution = [
    { range: '0-20', count: scoredLeads.filter(l => l.ai_score! <= 20).length },
    { range: '21-40', count: scoredLeads.filter(l => l.ai_score! > 20 && l.ai_score! <= 40).length },
    { range: '41-60', count: scoredLeads.filter(l => l.ai_score! > 40 && l.ai_score! <= 60).length },
    { range: '61-80', count: scoredLeads.filter(l => l.ai_score! > 60 && l.ai_score! <= 80).length },
    { range: '81-100', count: scoredLeads.filter(l => l.ai_score! > 80).length },
  ];

  const columns = [
    {
      accessorKey: 'name',
      header: 'Lead Name',
      cell: ({ row }) => (
        <div>
          <div className="font-medium">
            {row.original.first_name} {row.original.last_name}
          </div>
          <div className="text-sm text-muted-foreground">{row.original.email}</div>
        </div>
      ),
    },
    {
      accessorKey: 'account_name',
      header: 'Company',
    },
    {
      accessorKey: 'ai_score',
      header: 'AI Score',
      cell: ({ row }) => {
        const score = row.original.ai_score;
        if (!score) {
          return (
            <Button
              size="sm"
              variant="outline"
              onClick={() => scoreMutation.mutate(row.original.id)}
              disabled={scoreMutation.isPending}
            >
              {scoreMutation.isPending ? (
                <RefreshCw className="h-4 w-4 animate-spin" />
              ) : (
                'Calculate'
              )}
            </Button>
          );
        }
        return (
          <div className="flex items-center gap-2">
            <div className={`font-bold ${
              score >= 80 ? 'text-green-600' :
              score >= 60 ? 'text-yellow-600' :
              score >= 40 ? 'text-orange-600' :
              'text-red-600'
            }`}>
              {score}
            </div>
            <Button
              size="sm"
              variant="ghost"
              onClick={() => setSelectedLeadId(row.original.id)}
            >
              View
            </Button>
          </div>
        );
      },
    },
    {
      id: 'actions',
      cell: ({ row }) => (
        <Button
          size="sm"
          variant="ghost"
          onClick={() => setSelectedLeadId(row.original.id)}
        >
          Details
        </Button>
      ),
    },
  ];

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold flex items-center gap-2">
          <Brain className="h-6 w-6" />
          AI Lead Scoring
        </h1>
        <Button
          onClick={() => {
            const unscored = unscoredLeads.map(l => l.id);
            if (unscored.length > 0) {
              batchScoreMutation.mutate(unscored);
            }
          }}
          disabled={unscoredLeads.length === 0 || batchScoreMutation.isPending}
        >
          {batchScoreMutation.isPending && (
            <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
          )}
          Score All Unscored ({unscoredLeads.length})
        </Button>
      </div>

      {/* Metrics */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Leads</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{leads?.data.length || 0}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Scored Leads</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{scoredLeads.length}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Avg Score</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {scoredLeads.length > 0
                ? Math.round(
                    scoredLeads.reduce((sum, l) => sum + (l.ai_score || 0), 0) /
                    scoredLeads.length
                  )
                : 0}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Hot Leads</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">
              {scoredLeads.filter(l => l.ai_score! >= 80).length}
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Score Distribution */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Score Distribution</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={scoreDistribution}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="range" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="count" fill="#8884d8" />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Selected Lead Score */}
        <div>
          {selectedLeadId && (
            <AIScoreDisplay
              score={selectedScore}
              isLoading={isLoadingScore}
              onRefresh={() => scoreMutation.mutate(selectedLeadId)}
            />
          )}
        </div>
      </div>

      {/* Leads Table */}
      <Card>
        <CardHeader>
          <CardTitle>All Leads</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={leads?.data || []}
            isLoading={isLoadingLeads}
          />
        </CardContent>
      </Card>
    </div>
  );
}
```

### 5. Form Builder Implementation

#### 5.1 Create Form Field Component
`src/components/features/FormBuilder/FormField.tsx`:
```typescript
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, X, Settings } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { FormField as FormFieldType } from '@/types/custom';

interface FormFieldProps {
  field: FormFieldType;
  onUpdate: (field: FormFieldType) => void;
  onDelete: (id: string) => void;
  isPreview?: boolean;
}

export function FormField({ field, onUpdate, onDelete, isPreview }: FormFieldProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: field.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  if (isPreview) {
    return (
      <div className="space-y-2">
        <Label htmlFor={field.id}>
          {field.label}
          {field.required && <span className="text-red-500 ml-1">*</span>}
        </Label>
        {renderFieldInput(field)}
      </div>
    );
  }

  return (
    <div ref={setNodeRef} style={style}>
      <Card className="p-4">
        <div className="flex items-start gap-2">
          <div
            {...attributes}
            {...listeners}
            className="cursor-grab active:cursor-grabbing"
          >
            <GripVertical className="h-5 w-5 text-muted-foreground" />
          </div>
          
          <div className="flex-1 space-y-2">
            <div className="flex items-center justify-between">
              <Input
                value={field.label}
                onChange={(e) => onUpdate({ ...field, label: e.target.value })}
                placeholder="Field Label"
                className="font-medium"
              />
              <div className="flex items-center gap-1">
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {/* Open field settings */}}
                >
                  <Settings className="h-4 w-4" />
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => onDelete(field.id)}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            </div>
            
            <div className="text-sm text-muted-foreground">
              Type: {field.type} | Required: {field.required ? 'Yes' : 'No'}
            </div>
          </div>
        </div>
      </Card>
    </div>
  );
}

function renderFieldInput(field: FormFieldType) {
  switch (field.type) {
    case 'text':
    case 'email':
    case 'tel':
      return (
        <Input
          id={field.id}
          type={field.type}
          placeholder={field.placeholder}
          required={field.required}
        />
      );
    case 'textarea':
      return (
        <textarea
          id={field.id}
          className="w-full rounded-md border border-input bg-background px-3 py-2"
          rows={4}
          placeholder={field.placeholder}
          required={field.required}
        />
      );
    case 'select':
      return (
        <select
          id={field.id}
          className="w-full rounded-md border border-input bg-background px-3 py-2"
          required={field.required}
        >
          <option value="">Select an option</option>
          {field.options?.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      );
    case 'checkbox':
      return (
        <div className="flex items-center space-x-2">
          <input
            id={field.id}
            type="checkbox"
            className="rounded border-gray-300"
            required={field.required}
          />
          <label htmlFor={field.id} className="text-sm">
            {field.label}
          </label>
        </div>
      );
    default:
      return null;
  }
}
```

#### 5.2 Create Form Builder Page
`src/pages/FormBuilder/FormBuilderPage.tsx`:
```typescript
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  DndContext,
  DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Plus, Save, Eye, Code, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { FormField } from '@/components/features/FormBuilder/FormField';
import { formBuilderService } from '@/services/formBuilder.service';
import { useToast } from '@/components/ui/use-toast';
import type { Form, FormField as FormFieldType } from '@/types/custom';

const fieldTypes = [
  { type: 'text', label: 'Text Field' },
  { type: 'email', label: 'Email Field' },
  { type: 'tel', label: 'Phone Field' },
  { type: 'textarea', label: 'Text Area' },
  { type: 'select', label: 'Dropdown' },
  { type: 'checkbox', label: 'Checkbox' },
  { type: 'radio', label: 'Radio Buttons' },
];

export function FormBuilderPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const isEdit = Boolean(id);

  const [form, setForm] = useState<Partial<Form>>({
    name: '',
    description: '',
    fields: [],
    settings: {
      submitButtonText: 'Submit',
      successMessage: 'Thank you for your submission!',
      styling: {
        theme: 'light',
        primaryColor: '#3b82f6',
        fontFamily: 'Inter',
      },
    },
  });

  const { isLoading } = useQuery({
    queryKey: ['form', id],
    queryFn: () => formBuilderService.getForm(id!),
    enabled: isEdit,
    onSuccess: (data) => {
      setForm(data);
    },
  });

  const saveMutation = useMutation({
    mutationFn: (data: Partial<Form>) => {
      if (isEdit && id) {
        return formBuilderService.updateForm(id, data);
      }
      return formBuilderService.createForm(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['forms'] });
      toast({
        title: 'Form saved',
        description: 'Your form has been saved successfully.',
      });
      navigate('/forms');
    },
  });

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      setForm((prev) => {
        const oldIndex = prev.fields!.findIndex((f) => f.id === active.id);
        const newIndex = prev.fields!.findIndex((f) => f.id === over.id);
        
        return {
          ...prev,
          fields: arrayMove(prev.fields!, oldIndex, newIndex),
        };
      });
    }
  };

  const addField = (type: string) => {
    const newField: FormFieldType = {
      id: `field_${Date.now()}`,
      type: type as any,
      label: `New ${type} field`,
      required: false,
      placeholder: '',
    };

    setForm((prev) => ({
      ...prev,
      fields: [...(prev.fields || []), newField],
    }));
  };

  const updateField = (field: FormFieldType) => {
    setForm((prev) => ({
      ...prev,
      fields: prev.fields?.map((f) => (f.id === field.id ? field : f)),
    }));
  };

  const deleteField = (id: string) => {
    setForm((prev) => ({
      ...prev,
      fields: prev.fields?.filter((f) => f.id !== id),
    }));
  };

  if (isLoading) {
    return <div>Loading...</div>;
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate('/forms')}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <h1 className="text-2xl font-semibold">
            {isEdit ? 'Edit Form' : 'Create Form'}
          </h1>
        </div>
        <Button
          onClick={() => saveMutation.mutate(form)}
          disabled={saveMutation.isPending}
        >
          <Save className="mr-2 h-4 w-4" />
          Save Form
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Form Settings */}
        <div className="lg:col-span-1 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Form Settings</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Form Name</Label>
                <Input
                  id="name"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder="Contact Form"
                />
              </div>
              
              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  placeholder="Optional description"
                  rows={3}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="submitButton">Submit Button Text</Label>
                <Input
                  id="submitButton"
                  value={form.settings?.submitButtonText}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      settings: {
                        ...form.settings!,
                        submitButtonText: e.target.value,
                      },
                    })
                  }
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="successMessage">Success Message</Label>
                <Textarea
                  id="successMessage"
                  value={form.settings?.successMessage}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      settings: {
                        ...form.settings!,
                        successMessage: e.target.value,
                      },
                    })
                  }
                  rows={2}
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Add Fields</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 gap-2">
                {fieldTypes.map((fieldType) => (
                  <Button
                    key={fieldType.type}
                    variant="outline"
                    size="sm"
                    onClick={() => addField(fieldType.type)}
                  >
                    <Plus className="mr-1 h-3 w-3" />
                    {fieldType.label}
                  </Button>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Form Builder */}
        <div className="lg:col-span-2">
          <Tabs defaultValue="builder">
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="builder">Builder</TabsTrigger>
              <TabsTrigger value="preview">
                <Eye className="mr-2 h-4 w-4" />
                Preview
              </TabsTrigger>
              <TabsTrigger value="embed">
                <Code className="mr-2 h-4 w-4" />
                Embed
              </TabsTrigger>
            </TabsList>

            <TabsContent value="builder" className="mt-4">
              <Card>
                <CardContent className="p-6">
                  {form.fields?.length === 0 ? (
                    <div className="text-center py-12 text-muted-foreground">
                      <p>No fields added yet.</p>
                      <p className="text-sm">Click "Add Fields" to get started.</p>
                    </div>
                  ) : (
                    <DndContext
                      sensors={sensors}
                      collisionDetection={closestCenter}
                      onDragEnd={handleDragEnd}
                    >
                      <SortableContext
                        items={form.fields?.map((f) => f.id) || []}
                        strategy={verticalListSortingStrategy}
                      >
                        <div className="space-y-4">
                          {form.fields?.map((field) => (
                            <FormField
                              key={field.id}
                              field={field}
                              onUpdate={updateField}
                              onDelete={deleteField}
                            />
                          ))}
                        </div>
                      </SortableContext>
                    </DndContext>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="preview" className="mt-4">
              <Card>
                <CardContent className="p-6">
                  <form className="space-y-4">
                    {form.fields?.map((field) => (
                      <FormField
                        key={field.id}
                        field={field}
                        onUpdate={() => {}}
                        onDelete={() => {}}
                        isPreview
                      />
                    ))}
                    {form.fields?.length > 0 && (
                      <Button type="submit" className="w-full">
                        {form.settings?.submitButtonText}
                      </Button>
                    )}
                  </form>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="embed" className="mt-4">
              <Card>
                <CardContent className="p-6">
                  <div className="space-y-4">
                    <div>
                      <h3 className="font-medium mb-2">Script Embed</h3>
                      <pre className="bg-gray-100 p-4 rounded-md overflow-x-auto">
                        <code>{`<script src="https://yourcrm.com/forms/embed.js"></script>
<div data-form-id="${id || 'FORM_ID'}" data-form-container></div>`}</code>
                      </pre>
                    </div>
                    
                    <div>
                      <h3 className="font-medium mb-2">iFrame Embed</h3>
                      <pre className="bg-gray-100 p-4 rounded-md overflow-x-auto">
                        <code>{`<iframe 
  src="https://yourcrm.com/forms/embed/${id || 'FORM_ID'}"
  width="100%"
  height="500"
  frameborder="0">
</iframe>`}</code>
                      </pre>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </div>
  );
}
```

### 6. Knowledge Base Implementation

#### 6.1 Create Article Editor Component
`src/components/features/KnowledgeBase/ArticleEditor.tsx`:
```typescript
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import {
  Bold,
  Italic,
  List,
  ListOrdered,
  Quote,
  Undo,
  Redo,
  Link as LinkIcon,
  Image as ImageIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';

interface ArticleEditorProps {
  content: string;
  onChange: (content: string) => void;
  placeholder?: string;
}

export function ArticleEditor({ content, onChange, placeholder }: ArticleEditorProps) {
  const editor = useEditor({
    extensions: [
      StarterKit,
      Link.configure({
        openOnClick: false,
      }),
      Image,
    ],
    content,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
  });

  if (!editor) {
    return null;
  }

  const addLink = (url: string) => {
    if (url) {
      editor.chain().focus().setLink({ href: url }).run();
    }
  };

  const addImage = (url: string) => {
    if (url) {
      editor.chain().focus().setImage({ src: url }).run();
    }
  };

  return (
    <div className="border rounded-md">
      <div className="border-b p-2 flex items-center gap-1 flex-wrap">
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().toggleBold().run()}
          className={editor.isActive('bold') ? 'bg-muted' : ''}
        >
          <Bold className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().toggleItalic().run()}
          className={editor.isActive('italic') ? 'bg-muted' : ''}
        >
          <Italic className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          className={editor.isActive('bulletList') ? 'bg-muted' : ''}
        >
          <List className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          className={editor.isActive('orderedList') ? 'bg-muted' : ''}
        >
          <ListOrdered className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().toggleBlockquote().run()}
          className={editor.isActive('blockquote') ? 'bg-muted' : ''}
        >
          <Quote className="h-4 w-4" />
        </Button>
        
        <Popover>
          <PopoverTrigger asChild>
            <Button size="sm" variant="ghost">
              <LinkIcon className="h-4 w-4" />
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-80">
            <Input
              placeholder="Enter URL"
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  addLink(e.currentTarget.value);
                  e.currentTarget.value = '';
                }
              }}
            />
          </PopoverContent>
        </Popover>
        
        <Popover>
          <PopoverTrigger asChild>
            <Button size="sm" variant="ghost">
              <ImageIcon className="h-4 w-4" />
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-80">
            <Input
              placeholder="Enter image URL"
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  addImage(e.currentTarget.value);
                  e.currentTarget.value = '';
                }
              }}
            />
          </PopoverContent>
        </Popover>
        
        <div className="flex-1" />
        
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().undo().run()}
          disabled={!editor.can().undo()}
        >
          <Undo className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="ghost"
          onClick={() => editor.chain().focus().redo().run()}
          disabled={!editor.can().redo()}
        >
          <Redo className="h-4 w-4" />
        </Button>
      </div>
      
      <EditorContent
        editor={editor}
        className="prose prose-sm max-w-none p-4 min-h-[300px] focus:outline-none"
        placeholder={placeholder}
      />
    </div>
  );
}
```

#### 6.2 Create Knowledge Base Admin Page
`src/pages/KnowledgeBase/KnowledgeBaseAdmin.tsx`:
```typescript
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Plus, Search, Eye, Edit, Trash, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { knowledgeBaseService } from '@/services/knowledgeBase.service';
import { formatDate } from '@/lib/utils';
import { useToast } from '@/components/ui/use-toast';

export function KnowledgeBaseAdmin() {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: articles, isLoading: isLoadingArticles } = useQuery({
    queryKey: ['kb-articles', searchTerm, selectedCategory],
    queryFn: () =>
      knowledgeBaseService.getArticles({
        search: searchTerm,
        category_id: selectedCategory || undefined,
      }),
  });

  const { data: categories } = useQuery({
    queryKey: ['kb-categories'],
    queryFn: knowledgeBaseService.getCategories,
  });

  const deleteMutation = useMutation({
    mutationFn: knowledgeBaseService.deleteArticle,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb-articles'] });
      toast({
        title: 'Article deleted',
        description: 'The article has been deleted successfully.',
      });
    },
  });

  const columns = [
    {
      header: 'Title',
      cell: (article: any) => (
        <div>
          <div className="font-medium">{article.title}</div>
          <div className="text-sm text-muted-foreground">{article.category_name}</div>
        </div>
      ),
    },
    {
      header: 'Status',
      cell: (article: any) => (
        <div className="flex items-center gap-2">
          <Badge variant={article.is_public ? 'default' : 'secondary'}>
            {article.is_public ? 'Public' : 'Private'}
          </Badge>
          {article.is_featured && (
            <Badge variant="outline">Featured</Badge>
          )}
        </div>
      ),
    },
    {
      header: 'Stats',
      cell: (article: any) => (
        <div className="text-sm">
          <div>{article.views} views</div>
          <div className="text-muted-foreground">
            👍 {article.helpful_yes} 👎 {article.helpful_no}
          </div>
        </div>
      ),
    },
    {
      header: 'Modified',
      cell: (article: any) => (
        <div className="text-sm">
          <div>{formatDate(article.date_modified)}</div>
          <div className="text-muted-foreground">by {article.author_name}</div>
        </div>
      ),
    },
    {
      header: 'Actions',
      cell: (article: any) => (
        <div className="flex items-center gap-2">
          <Button size="sm" variant="ghost" asChild>
            <Link to={`/kb/preview/${article.id}`}>
              <Eye className="h-4 w-4" />
            </Link>
          </Button>
          <Button size="sm" variant="ghost" asChild>
            <Link to={`/kb/edit/${article.id}`}>
              <Edit className="h-4 w-4" />
            </Link>
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => {
              if (confirm('Are you sure you want to delete this article?')) {
                deleteMutation.mutate(article.id);
              }
            }}
          >
            <Trash className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold flex items-center gap-2">
          <BookOpen className="h-6 w-6" />
          Knowledge Base
        </h1>
        <div className="flex items-center gap-2">
          <Button asChild>
            <Link to="/kb/new">
              <Plus className="mr-2 h-4 w-4" />
              New Article
            </Link>
          </Button>
          <Button variant="outline" asChild>
            <Link to="/kb/categories">
              Manage Categories
            </Link>
          </Button>
        </div>
      </div>

      <Tabs defaultValue="articles" className="space-y-4">
        <TabsList>
          <TabsTrigger value="articles">Articles</TabsTrigger>
          <TabsTrigger value="analytics">Analytics</TabsTrigger>
        </TabsList>

        <TabsContent value="articles" className="space-y-4">
          <div className="flex items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <Input
                placeholder="Search articles..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <select
              className="rounded-md border border-input bg-background px-3 py-2"
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
            >
              <option value="">All Categories</option>
              {categories?.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </div>

          {isLoadingArticles ? (
            <div>Loading articles...</div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    {columns.map((column, index) => (
                      <TableHead key={index}>{column.header}</TableHead>
                    ))}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {articles?.data.map((article) => (
                    <TableRow key={article.id}>
                      {columns.map((column, index) => (
                        <TableCell key={index}>
                          {column.cell(article)}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </TabsContent>

        <TabsContent value="analytics">
          <div className="grid gap-4 md:grid-cols-3">
            <Card>
              <CardHeader>
                <CardTitle>Total Articles</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{articles?.data.length || 0}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Total Views</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {articles?.data.reduce((sum, a) => sum + a.views, 0) || 0}
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Helpfulness Rate</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {articles?.data.length > 0
                    ? Math.round(
                        (articles.data.reduce((sum, a) => sum + a.helpful_yes, 0) /
                          (articles.data.reduce((sum, a) => sum + a.helpful_yes + a.helpful_no, 0) || 1)) *
                          100
                      )
                    : 0}
                  %
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}
```

### 7. AI Chatbot Implementation

#### 7.1 Create Chat Widget Component
`src/components/features/Chatbot/ChatWidget.tsx`:
```typescript
import { useState, useRef, useEffect } from 'react';
import { MessageCircle, X, Send, Loader } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { aiService } from '@/services/ai.service';
import ReactMarkdown from 'react-markdown';
import type { ChatMessage } from '@/types/custom';
import { cn } from '@/lib/utils';

interface ChatWidgetProps {
  onLeadCapture?: (leadInfo: any) => void;
  position?: 'bottom-right' | 'bottom-left';
}

export function ChatWidget({ onLeadCapture, position = 'bottom-right' }: ChatWidgetProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      id: '1',
      role: 'assistant',
      content: "Hi! I'm here to help. What can I assist you with today?",
      timestamp: new Date().toISOString(),
    },
  ]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages]);

  const sendMessage = async () => {
    if (!input.trim() || isLoading) return;

    const userMessage: ChatMessage = {
      id: Date.now().toString(),
      role: 'user',
      content: input,
      timestamp: new Date().toISOString(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setIsLoading(true);

    try {
      const response = await aiService.chatCompletion(
        [...messages, userMessage],
        {
          page_url: window.location.href,
          user_agent: navigator.userAgent,
        }
      );

      const assistantMessage: ChatMessage = {
        id: Date.now().toString(),
        role: 'assistant',
        content: response.content,
        timestamp: new Date().toISOString(),
        metadata: response.metadata,
      };

      setMessages((prev) => [...prev, assistantMessage]);

      // Check if we should capture lead info
      if (response.metadata?.lead_score && response.metadata.lead_score > 60) {
        onLeadCapture?.(response.lead_info);
      }
    } catch (error) {
      const errorMessage: ChatMessage = {
        id: Date.now().toString(),
        role: 'assistant',
        content: "I'm sorry, I encountered an error. Please try again.",
        timestamp: new Date().toISOString(),
      };
      setMessages((prev) => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const positionClasses = {
    'bottom-right': 'bottom-4 right-4',
    'bottom-left': 'bottom-4 left-4',
  };

  return (
    <>
      {/* Chat Button */}
      {!isOpen && (
        <Button
          onClick={() => setIsOpen(true)}
          className={cn(
            'fixed z-50 h-14 w-14 rounded-full shadow-lg',
            positionClasses[position]
          )}
          size="icon"
        >
          <MessageCircle className="h-6 w-6" />
        </Button>
      )}

      {/* Chat Window */}
      {isOpen && (
        <Card
          className={cn(
            'fixed z-50 w-96 h-[600px] flex flex-col shadow-2xl',
            positionClasses[position]
          )}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b">
            <div>
              <h3 className="font-semibold">Support Chat</h3>
              <p className="text-sm text-muted-foreground">We typically reply in minutes</p>
            </div>
            <Button
              size="icon"
              variant="ghost"
              onClick={() => setIsOpen(false)}
            >
              <X className="h-4 w-4" />
            </Button>
          </div>

          {/* Messages */}
          <ScrollArea className="flex-1 p-4" ref={scrollRef}>
            <div className="space-y-4">
              {messages.map((message) => (
                <div
                  key={message.id}
                  className={cn(
                    'flex',
                    message.role === 'user' ? 'justify-end' : 'justify-start'
                  )}
                >
                  <div
                    className={cn(
                      'max-w-[80%] rounded-lg px-4 py-2',
                      message.role === 'user'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted'
                    )}
                  >
                    <ReactMarkdown className="text-sm prose prose-sm dark:prose-invert">
                      {message.content}
                    </ReactMarkdown>
                  </div>
                </div>
              ))}
              {isLoading && (
                <div className="flex justify-start">
                  <div className="bg-muted rounded-lg px-4 py-2">
                    <Loader className="h-4 w-4 animate-spin" />
                  </div>
                </div>
              )}
            </div>
          </ScrollArea>

          {/* Input */}
          <div className="p-4 border-t">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                sendMessage();
              }}
              className="flex gap-2"
            >
              <Input
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder="Type your message..."
                disabled={isLoading}
              />
              <Button type="submit" size="icon" disabled={isLoading}>
                <Send className="h-4 w-4" />
              </Button>
            </form>
          </div>
        </Card>
      )}
    </>
  );
}
```

#### 7.2 Create Embeddable Chat Script
`src/components/features/Chatbot/EmbedScript.tsx`:
```typescript
import { useState } from 'react';
import { Copy, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useToast } from '@/components/ui/use-toast';
import CopyToClipboard from 'react-copy-to-clipboard';

interface EmbedScriptProps {
  siteId: string;
  apiKey?: string;
}

export function EmbedScript({ siteId, apiKey }: EmbedScriptProps) {
  const { toast } = useToast();
  const [copiedTab, setCopiedTab] = useState<string | null>(null);

  const handleCopy = (tab: string) => {
    setCopiedTab(tab);
    toast({
      title: 'Copied to clipboard',
      description: 'The embed code has been copied to your clipboard.',
    });
    setTimeout(() => setCopiedTab(null), 2000);
  };

  const basicScript = `<!-- CRM Chat Widget -->
<script>
  (function(w,d,s,l,i){
    w[l]=w[l]||[];
    w[l].push({'chat.start': new Date().getTime(), site: i});
    var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s);j.async=true;
    j.src='https://yourcrm.com/chat-widget.js';
    f.parentNode.insertBefore(j,f);
  })(window,document,'script','crmChat','${siteId}');
</script>`;

  const advancedScript = `<!-- CRM Chat Widget with Custom Configuration -->
<script>
  (function(w,d,s,l,i){
    w[l]=w[l]||[];
    w[l].push({
      'chat.start': new Date().getTime(),
      site: i,
      config: {
        position: 'bottom-right',
        primaryColor: '#3b82f6',
        greeting: 'Hi! How can we help you today?',
        offlineMessage: 'We are currently offline. Please leave a message.',
        departments: ['sales', 'support'],
        customFields: {
          company: 'required',
          phone: 'optional'
        }
      }
    });
    var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s);j.async=true;
    j.src='https://yourcrm.com/chat-widget.js';
    f.parentNode.insertBefore(j,f);
  })(window,document,'script','crmChat','${siteId}');
</script>`;

  const apiIntegration = `// Initialize chat widget with API
const crmChat = new CRMChat({
  siteId: '${siteId}',
  apiKey: '${apiKey || 'YOUR_API_KEY'}',
  onLeadCapture: (lead) => {
    console.log('Lead captured:', lead);
    // Send to your backend
  },
  onChatStart: (session) => {
    console.log('Chat started:', session);
  },
  onChatEnd: (session) => {
    console.log('Chat ended:', session);
  }
});

// Identify user
crmChat.identify({
  email: 'user@example.com',
  name: 'John Doe',
  company: 'Acme Corp'
});

// Custom events
crmChat.track('viewed_pricing');`;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Chat Widget Installation</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="basic">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="basic">Basic</TabsTrigger>
            <TabsTrigger value="advanced">Advanced</TabsTrigger>
            <TabsTrigger value="api">API</TabsTrigger>
          </TabsList>

          <TabsContent value="basic" className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Add this code before the closing &lt;/body&gt; tag on your website.
            </p>
            <div className="relative">
              <pre className="bg-gray-100 p-4 rounded-md overflow-x-auto text-sm">
                <code>{basicScript}</code>
              </pre>
              <CopyToClipboard text={basicScript} onCopy={() => handleCopy('basic')}>
                <Button
                  size="sm"
                  variant="outline"
                  className="absolute top-2 right-2"
                >
                  {copiedTab === 'basic' ? (
                    <Check className="h-4 w-4" />
                  ) : (
                    <Copy className="h-4 w-4" />
                  )}
                </Button>
              </CopyToClipboard>
            </div>
          </TabsContent>

          <TabsContent value="advanced" className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Customize the chat widget appearance and behavior.
            </p>
            <div className="relative">
              <pre className="bg-gray-100 p-4 rounded-md overflow-x-auto text-sm">
                <code>{advancedScript}</code>
              </pre>
              <CopyToClipboard text={advancedScript} onCopy={() => handleCopy('advanced')}>
                <Button
                  size="sm"
                  variant="outline"
                  className="absolute top-2 right-2"
                >
                  {copiedTab === 'advanced' ? (
                    <Check className="h-4 w-4" />
                  ) : (
                    <Copy className="h-4 w-4" />
                  )}
                </Button>
              </CopyToClipboard>
            </div>
          </TabsContent>

          <TabsContent value="api" className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Programmatically control the chat widget and capture events.
            </p>
            <div className="relative">
              <pre className="bg-gray-100 p-4 rounded-md overflow-x-auto text-sm">
                <code>{apiIntegration}</code>
              </pre>
              <CopyToClipboard text={apiIntegration} onCopy={() => handleCopy('api')}>
                <Button
                  size="sm"
                  variant="outline"
                  className="absolute top-2 right-2"
                >
                  {copiedTab === 'api' ? (
                    <Check className="h-4 w-4" />
                  ) : (
                    <Copy className="h-4 w-4" />
                  )}
                </Button>
              </CopyToClipboard>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}
```

### 8. Activity Tracking Dashboard

#### 8.1 Create Live Visitors Component
`src/components/features/ActivityTracking/LiveVisitors.tsx`:
```typescript
import { useQuery } from '@tanstack/react-query';
import { Eye, Clock, MousePointer, Globe } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { activityTrackingService } from '@/services/activityTracking.service';
import { formatDistanceToNow } from 'date-fns';
import type { WebsiteSession } from '@/types/custom';

interface LiveVisitorsProps {
  onVisitorClick?: (session: WebsiteSession) => void;
}

export function LiveVisitors({ onVisitorClick }: LiveVisitorsProps) {
  const { data: sessions, isLoading } = useQuery({
    queryKey: ['live-visitors'],
    queryFn: activityTrackingService.getLiveVisitors,
    refetchInterval: 5000, // Refresh every 5 seconds
  });

  const getPageIcon = (url: string) => {
    if (url.includes('/pricing')) return '💰';
    if (url.includes('/features')) return '✨';
    if (url.includes('/docs')) return '📚';
    if (url.includes('/contact')) return '📧';
    return '📄';
  };

  const getEngagementLevel = (session: WebsiteSession) => {
    const pageViews = session.pages_viewed.length;
    const totalTime = session.total_time;
    
    if (pageViews > 5 || totalTime > 300) return 'high';
    if (pageViews > 2 || totalTime > 120) return 'medium';
    return 'low';
  };

  if (isLoading) {
    return <div>Loading visitors...</div>;
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Eye className="h-5 w-5" />
            Live Visitors
          </CardTitle>
          <Badge variant="secondary">{sessions?.length || 0} Active</Badge>
        </div>
      </CardHeader>
      <CardContent>
        <ScrollArea className="h-[400px]">
          <div className="space-y-4">
            {sessions?.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                No active visitors at the moment
              </p>
            ) : (
              sessions?.map((session) => {
                const currentPage = session.pages_viewed[session.pages_viewed.length - 1];
                const engagement = getEngagementLevel(session);
                
                return (
                  <div
                    key={session.id}
                    className="flex items-start justify-between p-3 rounded-lg border hover:bg-muted/50 cursor-pointer"
                    onClick={() => onVisitorClick?.(session)}
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-medium">
                          {session.lead_id ? (
                            <Badge variant="outline" className="mr-2">
                              Known Lead
                            </Badge>
                          ) : (
                            'Anonymous Visitor'
                          )}
                        </span>
                        <Badge
                          variant={
                            engagement === 'high'
                              ? 'default'
                              : engagement === 'medium'
                              ? 'secondary'
                              : 'outline'
                          }
                        >
                          {engagement} engagement
                        </Badge>
                      </div>
                      
                      <div className="flex items-center gap-4 text-sm text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <MousePointer className="h-3 w-3" />
                          {getPageIcon(currentPage.url)} {currentPage.title}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {Math.round(session.total_time / 60)}m
                        </span>
                        <span className="flex items-center gap-1">
                          <Eye className="h-3 w-3" />
                          {session.pages_viewed.length} pages
                        </span>
                      </div>
                      
                      {session.location && (
                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                          <Globe className="h-3 w-3" />
                          {session.location.city}, {session.location.country}
                        </div>
                      )}
                    </div>
                    
                    <div className="text-right">
                      <p className="text-xs text-muted-foreground">
                        {formatDistanceToNow(new Date(session.date_created), {
                          addSuffix: true,
                        })}
                      </p>
                      {session.referrer && (
                        <p className="text-xs text-muted-foreground mt-1">
                          from {new URL(session.referrer).hostname}
                        </p>
                      )}
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </ScrollArea>
      </CardContent>
    </Card>
  );
}
```

### 9. Update App Router with New Routes

`src/App.tsx` (update with Phase 3 routes):
```typescript
// Add to imports
import { LeadScoringDashboard } from '@/pages/Leads/LeadScoringDashboard';
import { FormBuilderPage } from '@/pages/FormBuilder/FormBuilderPage';
import { FormsList } from '@/pages/FormBuilder/FormsList';
import { KnowledgeBaseAdmin } from '@/pages/KnowledgeBase/KnowledgeBaseAdmin';
import { ArticleEditor } from '@/pages/KnowledgeBase/ArticleEditor';
import { KnowledgeBasePublic } from '@/pages/KnowledgeBase/KnowledgeBasePublic';
import { ActivityTrackingDashboard } from '@/pages/ActivityTracking/ActivityTrackingDashboard';
import { ChatbotSettings } from '@/pages/Chatbot/ChatbotSettings';

// Add to routes inside the protected route
{/* AI Lead Scoring */}
<Route path="leads/scoring" element={<LeadScoringDashboard />} />

{/* Form Builder */}
<Route path="forms" element={<FormsList />} />
<Route path="forms/new" element={<FormBuilderPage />} />
<Route path="forms/:id" element={<FormBuilderPage />} />

{/* Knowledge Base */}
<Route path="kb" element={<KnowledgeBaseAdmin />} />
<Route path="kb/new" element={<ArticleEditor />} />
<Route path="kb/edit/:id" element={<ArticleEditor />} />
<Route path="kb/preview/:id" element={<KnowledgeBasePublic />} />
<Route path="kb/categories" element={<CategoryManager />} />

{/* Activity Tracking */}
<Route path="tracking" element={<ActivityTrackingDashboard />} />
<Route path="tracking/sessions/:id" element={<SessionDetail />} />

{/* Chatbot */}
<Route path="chatbot" element={<ChatbotSettings />} />

{/* Public KB Route (outside protected routes) */}
<Route path="/kb/public/*" element={<KnowledgeBasePublic />} />
```

### 10. Update Navigation

`src/components/layout/AppLayout.tsx` (add new navigation items):
```typescript
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'Leads', href: '/leads', icon: Users },
  { name: 'Accounts', href: '/accounts', icon: Building2 },
  { name: 'Opportunities', href: '/opportunities', icon: TrendingDollar },
  { name: 'Activities', href: '/activities', icon: Calendar },
  { name: 'Cases', href: '/cases', icon: Headphones },
  { name: 'AI Scoring', href: '/leads/scoring', icon: Brain },
  { name: 'Forms', href: '/forms', icon: FileText },
  { name: 'Knowledge Base', href: '/kb', icon: BookOpen },
  { name: 'Activity Tracking', href: '/tracking', icon: Activity },
  { name: 'Chatbot', href: '/chatbot', icon: MessageCircle },
];
```

## Testing Setup

### Integration Tests for AI Features
`tests/frontend/integration/ai-features.test.tsx`:
```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { LeadScoringDashboard } from '@/pages/Leads/LeadScoringDashboard';
import { aiService } from '@/services/ai.service';

vi.mock('@/services/ai.service');

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
  },
});

describe('AI Lead Scoring', () => {
  it('calculates and displays lead score', async () => {
    const mockScore = {
      score: 85,
      factors: {
        company_size: 18,
        industry_match: 15,
        behavior_score: 22,
        engagement: 15,
        budget_signals: 15,
      },
      insights: ['High engagement on pricing page', 'Multiple decision makers identified'],
      recommended_actions: ['Schedule demo within 24 hours', 'Send enterprise pricing'],
      confidence: 0.92,
    };

    (aiService.scoreLead as jest.Mock).mockResolvedValue(mockScore);

    render(
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <LeadScoringDashboard />
        </BrowserRouter>
      </QueryClientProvider>
    );

    // Find and click calculate button
    const calculateButton = await screen.findByText('Calculate');
    fireEvent.click(calculateButton);

    // Wait for score to appear
    await waitFor(() => {
      expect(screen.getByText('85')).toBeInTheDocument();
      expect(screen.getByText('Hot Lead')).toBeInTheDocument();
    });

    // Verify insights are shown
    expect(screen.getByText(/High engagement on pricing page/)).toBeInTheDocument();
  });
});
```

## Definition of Success

### ✅ Phase 3 Frontend Success Criteria:

1. **AI Lead Scoring**
   - [ ] Lead scoring dashboard displays all leads
   - [ ] Individual score calculation works
   - [ ] Batch scoring processes multiple leads
   - [ ] Score breakdown shows all factors
   - [ ] AI insights and recommendations display
   - [ ] Score distribution chart renders

2. **Form Builder**
   - [ ] Drag-drop field arrangement works
   - [ ] All field types can be added
   - [ ] Form preview renders correctly
   - [ ] Embed code generation works
   - [ ] Form submissions create leads
   - [ ] Field validation settings apply

3. **Knowledge Base**
   - [ ] Article creation with rich text editor
   - [ ] Categories can be managed
   - [ ] Search functionality works
   - [ ] Public/private toggle works
   - [ ] Article analytics tracked
   - [ ] Related articles shown

4. **AI Chatbot**
   - [ ] Chat widget renders on page
   - [ ] Messages send and receive
   - [ ] KB article suggestions work
   - [ ] Lead qualification flow works
   - [ ] Embed script customizable
   - [ ] Chat sessions tracked

5. **Activity Tracking**
   - [ ] Live visitors display in real-time
   - [ ] Session timeline shows page progression
   - [ ] Engagement levels calculated
   - [ ] Visitor identification works
   - [ ] Heatmap visualization renders
   - [ ] Known leads highlighted

6. **Testing**
   - [ ] AI scoring tests pass
   - [ ] Form builder drag-drop tests pass
   - [ ] Chat message flow tests pass
   - [ ] KB search tests pass
   - [ ] Activity tracking tests pass

### Manual Verification Steps:
1. Navigate to AI Lead Scoring dashboard
2. Calculate score for a lead and verify breakdown
3. Create a form with multiple field types
4. Preview form and copy embed code
5. Create a KB article with formatting
6. Search for article and verify results
7. Open chat widget and send messages
8. View live visitors in activity tracking
9. Click on visitor to see session details
10. Verify all features integrate properly

### Integration Points:
- AI scoring updates lead records
- Form submissions create new leads with scores
- Chat captures lead information
- KB articles referenced in chat responses
- Activity data linked to known leads
- All features accessible from main navigation

### Next Phase Preview:
Phase 4 will add:
- Marketing website with embedded features
- Customer health scoring implementation
- Advanced chatbot with meeting scheduling
- Enhanced activity tracking with alerts
- Complete demo data and documentation