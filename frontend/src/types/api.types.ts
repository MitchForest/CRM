/**
 * API-specific types that aren't database models
 * These types handle API requests, responses, and utilities
 */

// Query parameters for list endpoints
export interface QueryParams {
  page?: number;
  limit?: number;  // Backend uses 'limit' not 'pageSize'
  search?: string;
  orderBy?: string;
  orderDir?: 'ASC' | 'DESC';
  filter?: Record<string, any>;
}

// Generic API response wrapper
export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: {
    error: string;
    code?: string;
    details?: any;
  };
}

// List response with pagination based on actual backend response
export interface ListResponse<T> {
  data: T[];
  pagination: {
    page: number;
    limit: number;  // Backend returns 'limit' not 'pageSize'
    total: number;
    totalPages: number;
  };
}

// Login response based on actual AuthController
export interface LoginResponse {
  accessToken: string;
  refreshToken: string;
  user: {
    id: string;
    username: string;
    email: string;
    firstName: string;  // Backend returns camelCase for user fields in auth
    lastName: string;
  };
}

// Dashboard metrics types based on actual backend response
export interface DashboardMetrics {
  total_leads: number;
  total_accounts: number;
  new_leads_today: number;
  pipeline_value: number;
}

// Pipeline data based on actual backend response
export interface PipelineData {
  stage: string;
  count: number;
  value: number;
}

export interface ActivityMetrics {
  calls_today: number;
  meetings_today: number;
  tasks_overdue: number;
  upcoming_activities?: Array<{
    id: string;
    name: string;
    type: 'Call' | 'Meeting' | 'Task' | 'Note';
    date_start: string;
    related_to: string;
  }>;
}

export interface CaseMetrics {
  open_cases: number;
  closed_this_month: number;
  high_priority: number;
  avg_resolution_days: number;
  cases_by_priority?: Array<{
    priority: string;
    count: number;
  }>;
}

// Activity types
export type ActivityType = 'call' | 'meeting' | 'task' | 'note';

export interface BaseActivity {
  id: string;
  type: ActivityType;
  subject?: string;
  name?: string | null;
  description?: string | null;
  date?: string;
  date_start?: string;
  date_entered?: string;
  date_modified?: string;
  status?: string;
  parent_type?: string | null;
  parent_id?: string | null;
  assigned_user_id?: string;
}

// Case priority types
export type CasePriority = 'P1' | 'P2' | 'P3' | 'Low' | 'Medium' | 'High';
export type CasePriorityLabel = 'Low' | 'Medium' | 'High';

// Knowledge base types (Phase 3)
export interface KBCategory {
  id: string;
  name: string;
  description?: string;
  parent_id?: string;
  articles?: KBArticle[];
  icon?: string;
  article_count?: number;
}

export interface KBArticle {
  id: string;
  title: string;
  content: string;
  category_id: string;
  status: 'draft' | 'published' | 'archived';
  views: number;
  helpful_count: number;
  created_at: string;
  updated_at: string;
  // Additional fields used by components
  category_name?: string;
  author_name?: string;
  date_modified?: string;
  date_created?: string;
  tags?: string[];
  helpful_yes?: number;
  helpful_no?: number;
  slug?: string;
  excerpt?: string;
  is_public?: boolean;
  is_featured?: boolean;
}

// Form builder types (Phase 3)
export interface Form {
  id: string;
  name: string;
  description?: string;
  fields: FormField[];
  settings: {
    submitButtonText?: string;
    successMessage?: string;
    redirectUrl?: string;
  };
  created_at: string;
  updated_at: string;
  date_created?: string;
  submissions_count?: number;
  embed_code?: string;
}

export interface FormField {
  id: string;
  type: 'text' | 'email' | 'tel' | 'textarea' | 'select' | 'checkbox' | 'radio' | 'number' | 'date';
  name: string;
  label: string;
  placeholder?: string;
  required: boolean;
  options?: Array<{ value: string; label: string }>;
  validation?: Record<string, any>;
}

// Chat types (Phase 3)
export interface ChatMessage {
  id: string;
  message?: string;
  content?: string;
  sender?: 'user' | 'bot';
  role?: 'user' | 'assistant' | 'system';
  timestamp: string;
  metadata?: Record<string, any>;
}

// AI Scoring types (Phase 3)
export interface AIScoreResult {
  score: number;
  confidence: number;
  factors: Record<string, any>;
  insights?: string[];
  recommended_actions?: string[];
  created_at?: string;
  last_updated?: string;
}

export interface AIScoreHistory {
  id: string;
  score: number;
  date_scored: string;
  factors: Record<string, any>;
}

// Chat types
export interface ChatSession {
  conversation_id: string;
  messages: ChatMessage[];
  started_at: string;
  visitor_id?: string;
  lead_id?: string;
  contact_id?: string;
}

// Knowledge base search
export interface KBSearchResult {
  id: string;
  title: string;
  content: string;
  relevance_score: number;
  category: string;
  url?: string;
  // Additional fields used by components
  article?: KBArticle;
  similarity?: number;
}

// Website tracking types
export interface WebsiteSession {
  id: string;
  visitor_id: string;
  session_id: string;
  lead_id?: string;
  started_at?: string;
  ended_at?: string;
  date_created: string;
  page_count?: number;
  total_duration?: number;
  total_time?: number;
  pages_viewed?: Array<{
    url: string;
    title: string;
    visited_at: string;
    duration: number;
  }>;
  location?: {
    city: string;
    country: string;
  };
  referrer?: string;
}

// Customer health types
export interface CustomerHealthScore {
  id: string;
  contact_id: string;
  score: number;
  factors: {
    engagement: number;
    satisfaction: number;
    usage: number;
    value: number;
  };
  trend: 'improving' | 'stable' | 'declining';
  last_calculated: string;
}

// Additional types needed by services
export interface FormSubmission {
  id: string;
  form_id: string;
  data: Record<string, any>;
  submitted_at: string;
  lead_id?: string;
  contact_id?: string;
}

export interface HealthScore {
  score: number;
  factors: Record<string, number>;
  trend: string;
  last_calculated: string;
}

export interface HealthDashboard {
  overall_score: number;
  accounts_at_risk: number;
  improving_accounts: number;
  stable_accounts: number;
  metrics: HealthMetric[];
}

export interface HealthMetric {
  name: string;
  value: number;
  trend: string;
  change: number;
}

export interface ActivityHeatmap {
  data: Array<{
    hour: number;
    day: number;
    count: number;
  }>;
  max_count: number;
}

export interface TrackingEvent {
  type: string;
  data: Record<string, any>;
  timestamp: string;
  visitor_id?: string;
  session_id?: string;
}