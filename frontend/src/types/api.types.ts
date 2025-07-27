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
  totalLeads: number;
  totalAccounts: number;
  newLeadsToday: number;
  pipelineValue: number;
}

// Pipeline data based on actual backend response
export interface PipelineData {
  stage: string;
  count: number;
  value: number;
}

export interface ActivityMetrics {
  recentActivities: Array<{
    id: string;
    type: 'call' | 'meeting' | 'task' | 'note';
    subject: string;
    date: string;
    relatedTo: string;
    relatedType: string;
  }>;
  upcomingTasks: Array<{
    id: string;
    subject: string;
    dueDate: string;
    priority: string;
  }>;
}

export interface CaseMetrics {
  byStatus: Record<string, number>;
  byPriority: Record<string, number>;
  recentCases: Array<{
    id: string;
    caseNumber: string;
    subject: string;
    status: string;
    priority: string;
    createdAt: string;
  }>;
}

// Activity types
export type ActivityType = 'call' | 'meeting' | 'task' | 'note';

export interface BaseActivity {
  id: string;
  type: ActivityType;
  subject?: string;
  name?: string;
  description?: string;
  date?: string;
  date_start?: string;
  date_entered?: string;
  status?: string;
  parent_type?: string;
  parent_id?: string;
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
}

export interface FormField {
  id: string;
  type: 'text' | 'email' | 'tel' | 'textarea' | 'select' | 'checkbox' | 'radio';
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
  message: string;
  sender: 'user' | 'bot';
  timestamp: string;
  metadata?: Record<string, any>;
}

// AI Scoring types (Phase 3)
export interface AIScoreResult {
  score: number;
  factors: Array<{
    name: string;
    value: number;
    weight: number;
  }>;
  recommendations: string[];
  lastUpdated: string;
}

export interface AIScoreHistory {
  id: string;
  score: number;
  scored_at: string;
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
}

// Website tracking types
export interface WebsiteSession {
  id: string;
  visitor_id: string;
  session_id: string;
  started_at: string;
  ended_at?: string;
  page_count: number;
  total_duration: number;
  pages: Array<{
    url: string;
    title: string;
    visited_at: string;
    duration: number;
  }>;
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