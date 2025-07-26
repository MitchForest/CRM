/**
 * Phase 2 specific types and enhancements
 * These types extend the base types with additional functionality for Phase 2
 */

import type { Opportunity } from './api.generated';

// Opportunity Pipeline Types
export type OpportunityStage = 
  | 'Qualified'
  | 'Proposal'
  | 'Negotiation'
  | 'Won'
  | 'Lost';

export interface PipelineStageData {
  stage: OpportunityStage;
  count: number;
  value: number;
  opportunities: Opportunity[];
}

export const STAGE_PROBABILITIES: Record<OpportunityStage, number> = {
  'Qualified': 30,
  'Proposal': 60,
  'Negotiation': 80,
  'Won': 100,
  'Lost': 0,
};

// Activity Types
export type ActivityType = 'Call' | 'Meeting' | 'Task' | 'Note';

export interface BaseActivity {
  id: string;
  name: string;
  status: string;
  priority?: string;
  parentType?: string;
  parentId?: string;
  parentName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  dateEntered: string;
  dateModified: string;
  description?: string;
  type?: ActivityType;
}

export interface ActivityMetrics {
  callsToday: number;
  meetingsToday: number;
  tasksOverdue: number;
  upcomingActivities: BaseActivity[];
}

// Case Priority Types
export type CasePriority = 'P1' | 'P2' | 'P3';
export type CasePriorityLabel = 'High' | 'Medium' | 'Low';

export const CASE_PRIORITY_MAP: Record<CasePriority, CasePriorityLabel> = {
  'P1': 'High',
  'P2': 'Medium',
  'P3': 'Low',
};

export interface CaseMetrics {
  openCases: number;
  criticalCases: number;
  avgResolutionTime: number;
  casesByPriority: { priority: CasePriority; count: number }[];
}

// Enhanced Dashboard Types
export interface DashboardMetrics {
  totalLeads: number;
  totalAccounts: number;
  newLeadsToday: number;
  pipelineValue: number;
  conversionRate: number;
  avgDealSize: number;
}

export interface PipelineData {
  stages: Array<{
    stage: OpportunityStage;
    count: number;
    value: number;
  }>;
  total: number;
}

// Module Permission Types
export type CRMModule = 'Leads' | 'Accounts' | 'Opportunities' | 'Cases' | 'Activities';
export type CRMAction = 'view' | 'create' | 'edit' | 'delete';

export interface ModulePermission {
  module: CRMModule;
  actions: CRMAction[];
}

export interface UserRole {
  id: string;
  name: string;
  permissions: ModulePermission[];
}

// View State Types
export type ViewMode = 'list' | 'grid' | 'kanban' | 'calendar';

export interface ViewState {
  mode: ViewMode;
  filters: Record<string, unknown>;
  sort: {
    field: string;
    direction: 'asc' | 'desc';
  };
  pagination: {
    page: number;
    pageSize: number;
  };
}

// Drag and Drop Types
export interface DragItem {
  id: string;
  type: string;
  data: unknown;
}

export interface DropResult {
  droppableId: string;
  index: number;
}

// Chart Data Types
export interface ChartDataPoint {
  label: string;
  value: number;
  color?: string;
}

export interface TimeSeriesData {
  date: string;
  value: number;
  category?: string;
}

// Notification Types
export interface ActivityNotification {
  id: string;
  type: 'overdue' | 'upcoming' | 'completed';
  activity: BaseActivity;
  message: string;
  timestamp: Date;
}

// Filter Types for Phase 2 modules
export interface OpportunityFilters {
  stage?: OpportunityStage;
  minAmount?: number;
  maxAmount?: number;
  probability?: number;
  assignedUserId?: string;
  dateRange?: {
    start: Date;
    end: Date;
  };
}

export interface CaseFilters {
  status?: string;
  priority?: CasePriority;
  assignedUserId?: string;
  accountId?: string;
  dateRange?: {
    start: Date;
    end: Date;
  };
}

export interface ActivityFilters {
  type?: ActivityType;
  status?: string;
  assignedUserId?: string;
  parentType?: string;
  parentId?: string;
  dateRange?: {
    start: Date;
    end: Date;
  };
  overdue?: boolean;
}

// Form Types
export interface OpportunityFormData {
  name: string;
  accountId: string;
  salesStage: OpportunityStage;
  amount: number;
  probability: number;
  closeDate: Date;
  leadSource?: string;
  nextStep?: string;
  description?: string;
}

export interface TaskFormData {
  name: string;
  status: string;
  priority: 'High' | 'Medium' | 'Low';
  dueDate?: Date;
  startDate?: Date;
  description?: string;
  parentType?: string;
  parentId?: string;
}

export interface CaseFormData {
  name: string;
  type?: string;
  status: string;
  priority: CasePriority;
  description?: string;
  accountId?: string;
  contactId?: string;
}

// API Response Types
export interface ApiListResponse<T> {
  data: T[];
  meta: {
    total: number;
    page: number;
    pageSize: number;
  };
}

export interface ApiErrorResponse {
  error: string;
  message: string;
  details?: Record<string, unknown>;
}

// Utility Types
export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};

export type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>;

// Status Maps
export const OPPORTUNITY_STATUS_COLORS: Record<OpportunityStage, string> = {
  'Qualified': 'bg-blue-100 text-blue-800',
  'Proposal': 'bg-orange-100 text-orange-800',
  'Negotiation': 'bg-yellow-100 text-yellow-800',
  'Won': 'bg-green-100 text-green-800',
  'Lost': 'bg-red-100 text-red-800',
};

export const CASE_STATUS_COLORS: Record<string, string> = {
  'Open': 'bg-blue-100 text-blue-800',
  'In Progress': 'bg-yellow-100 text-yellow-800',
  'Resolved': 'bg-green-100 text-green-800',
  'Closed': 'bg-gray-100 text-gray-800',
};