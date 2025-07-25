// Phase 3 Type Definitions for AI-powered CRM features

// AI Lead Scoring
export interface AIScoreResult {
  lead_id: string;
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
  created_at?: string;
}

export interface AIScoreHistory {
  id: string;
  lead_id: string;
  score: number;
  factors: Record<string, number>;
  created_at: string;
}

// Form Builder
export interface FormField {
  id: string;
  name: string;
  type: 'text' | 'email' | 'tel' | 'select' | 'checkbox' | 'radio' | 'textarea' | 'number' | 'date';
  label: string;
  placeholder?: string;
  required: boolean;
  options?: { label: string; value: string }[];
  validation?: {
    pattern?: string;
    minLength?: number;
    maxLength?: number;
    min?: number;
    max?: number;
  };
  conditionalLogic?: {
    field: string;
    operator: 'equals' | 'not_equals' | 'contains' | 'greater_than' | 'less_than';
    value: string;
  };
  order?: number;
}

export interface Form {
  id: string;
  name: string;
  description?: string;
  fields: FormField[];
  settings: {
    submit_button_text: string;
    success_message: string;
    redirect_url?: string;
    notification_email?: string;
    webhook_url?: string;
    styling?: {
      theme: 'light' | 'dark' | 'custom';
      primary_color: string;
      font_family: string;
      custom_css?: string;
    };
  };
  embed_code?: string;
  created_by: string;
  created_by_name?: string;
  date_created: string;
  date_modified?: string;
  submissions_count: number;
  is_active: boolean;
  allowed_domains?: string[];
}

export interface FormSubmission {
  id: string;
  form_id: string;
  form_name?: string;
  data: Record<string, any>;
  ip_address: string;
  user_agent: string;
  date_submitted: string;
  lead_id?: string;
  visitor_id?: string;
  page_url?: string;
  referrer?: string;
}

// Knowledge Base
export interface KBCategory {
  id: string;
  name: string;
  slug: string;
  description?: string;
  parent_id?: string;
  parent_name?: string;
  article_count: number;
  icon?: string;
  order?: number;
  is_active: boolean;
}

export interface KBArticle {
  id: string;
  title: string;
  slug: string;
  content: string;
  excerpt?: string;
  category_id?: string;
  category_name?: string;
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
  similarity?: number; // For search results
  meta_description?: string;
  meta_keywords?: string[];
}

export interface KBSearchResult {
  article: KBArticle;
  similarity: number;
  highlights?: string[];
}

// AI Chatbot
export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: string;
  metadata?: {
    intent?: 'support' | 'sales' | 'general' | 'qualification';
    confidence?: number;
    suggested_articles?: string[];
    suggested_actions?: string[];
    lead_score?: number;
    entities?: Record<string, any>;
  };
}

export interface ChatSession {
  id: string;
  conversation_id?: string;
  visitor_id: string;
  lead_id?: string;
  messages: ChatMessage[];
  context: {
    page_url?: string;
    referrer?: string;
    user_info?: {
      name?: string;
      email?: string;
      company?: string;
      phone?: string;
    };
    custom_data?: Record<string, any>;
  };
  status: 'active' | 'ended' | 'transferred';
  started_at: string;
  ended_at?: string;
  transferred_to?: string;
  satisfaction_rating?: number;
}

export interface ChatWidget {
  position: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';
  theme: 'light' | 'dark' | 'auto';
  primaryColor: string;
  greeting: string;
  offlineMessage?: string;
  departments?: string[];
  customFields?: {
    name: string;
    type: 'text' | 'email' | 'tel' | 'select';
    required: boolean;
    options?: string[];
  }[];
}

// Activity Tracking
export interface PageView {
  id?: string;
  url: string;
  title: string;
  timestamp: string;
  duration: number;
  scroll_depth: number;
  clicks: number;
  exit_intent?: boolean;
  custom_events?: {
    event: string;
    value?: any;
    timestamp: string;
  }[];
}

export interface WebsiteSession {
  id: string;
  visitor_id: string;
  lead_id?: string;
  contact_id?: string;
  ip_address: string;
  user_agent: string;
  referrer?: string;
  utm_source?: string;
  utm_medium?: string;
  utm_campaign?: string;
  pages_viewed: PageView[];
  total_time: number;
  is_active: boolean;
  date_created: string;
  date_last_active?: string;
  location?: {
    country: string;
    country_code: string;
    city: string;
    region?: string;
    timezone?: string;
  };
  device?: {
    type: 'desktop' | 'mobile' | 'tablet';
    browser: string;
    os: string;
  };
  engagement_score?: number;
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
  attention_data?: Array<{
    element: string;
    time_spent: number;
  }>;
  date_range: {
    start: string;
    end: string;
  };
  total_views: number;
}

export interface TrackingEvent {
  event: string;
  properties?: Record<string, any>;
  timestamp: string;
  visitor_id: string;
  session_id?: string;
}

// Customer Health Scoring
export interface HealthScore {
  id?: string;
  account_id: string;
  account_name?: string;
  score: number;
  previous_score?: number;
  factors: {
    usage_score: number;
    support_score: number;
    engagement_score: number;
    financial_score: number;
    custom_scores?: Record<string, number>;
  };
  trend: 'improving' | 'stable' | 'declining';
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  recommended_actions: string[];
  alerts?: {
    type: 'warning' | 'danger' | 'info';
    message: string;
    created_at: string;
  }[];
  last_calculated: string;
  next_review_date?: string;
}

export interface HealthMetric {
  name: string;
  value: number;
  weight: number;
  trend: 'up' | 'down' | 'stable';
  threshold: {
    min: number;
    max: number;
  };
}

export interface HealthDashboard {
  total_accounts: number;
  healthy_accounts: number;
  at_risk_accounts: number;
  critical_accounts: number;
  average_score: number;
  score_distribution: {
    range: string;
    count: number;
    percentage: number;
  }[];
  trends: {
    period: string;
    average_score: number;
    improved: number;
    declined: number;
  }[];
}

// API Response Types
export interface Phase3ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  error?: string;
  meta?: {
    total?: number;
    page?: number;
    limit?: number;
  };
}

export interface Phase3ApiError {
  success: false;
  error: string;
  message?: string;
  details?: Record<string, any>;
}

// Utility Types
export type FormFieldValue = string | number | boolean | string[] | Date | null;

export interface EmbedConfig {
  container: string;
  apiUrl: string;
  theme?: 'light' | 'dark' | 'auto';
  onSubmit?: (data: Record<string, FormFieldValue>) => void;
  onError?: (error: Error) => void;
}

export interface TrackingConfig {
  apiUrl: string;
  visitorId?: string;
  sessionId?: string;
  enableAutoTracking?: boolean;
  trackClicks?: boolean;
  trackScrollDepth?: boolean;
  customEvents?: string[];
}

export interface ChatConfig {
  apiUrl: string;
  position?: ChatWidget['position'];
  theme?: ChatWidget['theme'];
  primaryColor?: string;
  greeting?: string;
  onLeadCapture?: (lead: any) => void;
  onChatEnd?: (session: ChatSession) => void;
}