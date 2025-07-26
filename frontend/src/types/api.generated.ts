/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * Auto-generated TypeScript types from PHP DTOs
 * Generated: 2025-07-23 02:05:11
 * DO NOT EDIT MANUALLY
 */

// Base Types
export interface Pagination {
  page: number;
  pageSize: number;
  totalPages: number;
  totalCount: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export interface ErrorResponse {
  error: string;
  code: string;
  details?: any;
  timestamp?: string;
}

// Entity Types
export interface Contact {
  id?: string;
  firstName: string;
  lastName: string;
  email: string;
  phone?: string;
  mobile?: string;
  title?: string;
  birthDate?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  customerNumber?: string;
  preferredContactMethod?: 'email' | 'phone' | 'sms';
  tags?: string[];
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Account {
  id?: string;
  name: string;
  phone?: string;
  website?: string;
  industry?: string;
  annualRevenue?: number;
  employees?: number;
  billingStreet?: string;
  billingCity?: string;
  billingState?: string;
  billingPostalCode?: string;
  billingCountry?: string;
  shippingStreet?: string;
  shippingCity?: string;
  shippingState?: string;
  shippingPostalCode?: string;
  shippingCountry?: string;
  description?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Lead {
  id?: string;
  firstName: string;
  lastName: string;
  email: string;
  phone?: string;
  mobile?: string;
  title?: string;
  company?: string;
  website?: string;
  description?: string;
  status: 'New' | 'Contacted' | 'Qualified' | 'Converted' | 'Dead';
  source?: string;
  accountName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  convertedContactId?: string;
  convertedAt?: string;
  // AI custom fields
  aiScore?: number;
  aiScoreDate?: string;
  aiInsights?: string;
  // SuiteCRM metadata fields
  dateEntered?: string;
  dateModified?: string;
}

export interface Opportunity {
  id?: string;
  name: string;
  amount: number;
  currency?: string;
  probability?: number;
  salesStage: string;
  closeDate: string;
  description?: string;
  nextStep?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  aiInsights?: string;
  aiScore?: number;
  aiRecommendations?: string[];
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Task {
  id?: string;
  name: string;
  status: 'Not Started' | 'In Progress' | 'Completed' | 'Pending Input' | 'Deferred';
  priority: 'High' | 'Medium' | 'Low';
  dueDate?: string;
  startDate?: string;
  percentComplete?: number;
  description?: string;
  parentType?: string;
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  reminderTime?: string;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Case {
  id?: string;
  name: string;
  caseNumber?: string;
  type?: string;
  status: 'Open' | 'In Progress' | 'Resolved' | 'Closed';
  priority: 'High' | 'Medium' | 'Low';
  resolution?: string;
  description?: string;
  subject?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  attachments?: Array<{
    id: string;
    name: string;
    mimeType: string;
    size: number;
  }>;
  updates?: Array<{
    id: string;
    text: string;
    internal: boolean;
    createdBy: string;
    createdAt: string;
  }>;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Quote {
  id?: string;
  name: string;
  quoteNumber?: string;
  stage: 'Draft' | 'Sent' | 'Accepted' | 'Rejected' | 'Expired';
  validUntil?: string;
  opportunityId?: string;
  opportunityName?: string;
  contactId?: string;
  contactName?: string;
  billingAddress?: Address;
  shippingAddress?: Address;
  currency?: string;
  subtotal?: number;
  tax?: number;
  shipping?: number;
  total?: number;
  discount?: number;
  discountType?: 'Percentage' | 'Amount';
  lineItems?: LineItem[];
  terms?: string;
  description?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Address {
  street?: string;
  city?: string;
  state?: string;
  postalCode?: string;
  country?: string;
}

export interface LineItem {
  id?: string;
  productId?: string;
  name: string;
  description?: string;
  quantity: number;
  unitPrice: number;
  discount?: number;
  tax?: number;
  total?: number;
}

export interface Email {
  id?: string;
  subject: string;
  body: string;
  bodyHtml?: string;
  from: EmailAddress;
  to: EmailAddress[];
  cc?: EmailAddress[];
  bcc?: EmailAddress[];
  replyTo?: EmailAddress;
  status: 'draft' | 'sent' | 'received' | 'archived';
  type: 'inbound' | 'outbound' | 'draft';
  parentType?: string;
  parentId?: string;
  parentName?: string;
  attachments?: Attachment[];
  messageId?: string;
  inReplyTo?: string;
  importance?: 'high' | 'normal' | 'low';
  flags?: string[];
  folder?: string;
  threadId?: string;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface EmailAddress {
  email: string;
  name?: string;
}

export interface Attachment {
  id: string;
  name: string;
  mimeType: string;
  size: number;
  content?: string; // Base64 encoded
}

export interface Call {
  id?: string;
  name: string;
  direction: 'Inbound' | 'Outbound';
  status: 'Planned' | 'Held' | 'Cancelled';
  startDate: string;
  duration?: number; // in minutes
  phoneNumber?: string;
  description?: string;
  parentType?: string;
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  reminderTime?: string;
  result?: string;
  recordingUrl?: string;
  recurrence?: RecurrenceRule;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface RecurrenceRule {
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly';
  interval?: number;
  count?: number;
  until?: string;
  byDay?: string[];
  byMonth?: number[];
  byMonthDay?: number[];
}

export interface Meeting {
  id?: string;
  name: string;
  status: 'Planned' | 'Held' | 'Cancelled';
  type: 'In Person' | 'Virtual' | 'Phone';
  startDate: string;
  endDate: string;
  duration?: number; // in minutes
  location?: string;
  meetingUrl?: string;
  description?: string;
  parentType?: string;
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  invitees?: Invitee[];
  reminderTime?: string;
  agenda?: string;
  minutes?: string;
  recurrence?: RecurrenceRule;
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Invitee {
  id: string;
  type: 'contact' | 'lead' | 'user';
  name: string;
  email?: string;
  status: 'invited' | 'accepted' | 'declined' | 'tentative';
}

export interface Note {
  id?: string;
  name: string;
  description?: string;
  parentType?: string;
  parentId?: string;
  parentName?: string;
  contactId?: string;
  contactName?: string;
  assignedUserId?: string;
  assignedUserName?: string;
  attachment?: Attachment;
  tags?: string[];
  customFields?: Record<string, any>;
  createdAt?: string;
  updatedAt?: string;
}

export interface Activity {
  id: string;
  type: 'Call' | 'Meeting' | 'Email' | 'Task' | 'Note';
  subject: string;
  description?: string;
  date: string;
  status?: string;
  relatedTo?: string;
  relatedType?: string;
  relatedId?: string;
  assignedTo?: string;
  assignedToName?: string;
  createdBy?: string;
  createdByName?: string;
  createdAt: string;
  updatedAt?: string;
}

// Auth Types
export interface LoginRequest {
  username: string;
  password: string;
}

export interface LoginResponse {
  accessToken: string;
  refreshToken: string;
  expiresIn: number;
  tokenType: string;
  user: {
    id: string;
    username: string;
    email: string;
    firstName: string;
    lastName: string;
  };
}

export interface RefreshTokenRequest {
  refreshToken: string;
}

export interface RefreshTokenResponse {
  accessToken: string;
  refreshToken: string;
  expiresIn: number;
  tokenType: string;
}

// Response Types
export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: ErrorResponse;
  pagination?: Pagination;
}

export interface ListResponse<T> {
  data: T[];
  pagination: Pagination;
}

// Utility Types
export type FilterOperator = 'eq' | 'ne' | 'gt' | 'gte' | 'lt' | 'lte' | 'like' | 'in' | 'between';

export interface Filter {
  field: string;
  operator: FilterOperator;
  value: any;
}

export interface Sort {
  field: string;
  direction: 'asc' | 'desc';
}

export interface QueryParams {
  page?: number;
  pageSize?: number;
  filters?: Filter[];
  sort?: Sort[];
  search?: string;
  fields?: string[];
}
