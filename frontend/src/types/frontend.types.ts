/**
 * Frontend types with camelCase field names
 * These types are used throughout the frontend application
 * and are automatically converted to/from SuiteCRM's snake_case format
 */

// Base fields common to all entities
export interface BaseEntity {
  id: string;
  name?: string;
  dateEntered: string;
  dateModified: string;
  modifiedUserId: string;
  createdBy: string;
  description?: string;
  deleted: number;
  assignedUserId?: string;
}

// Contact type for frontend use
export interface Contact extends BaseEntity {
  salutation?: string;
  firstName?: string;
  lastName?: string;
  title?: string;
  department?: string;
  doNotCall?: number;
  phoneHome?: string;
  phoneMobile?: string;
  phoneWork?: string;
  phoneOther?: string;
  phoneFax?: string;
  email1?: string;
  email2?: string;
  primaryAddressStreet?: string;
  primaryAddressCity?: string;
  primaryAddressState?: string;
  primaryAddressPostalcode?: string;
  primaryAddressCountry?: string;
  altAddressStreet?: string;
  altAddressCity?: string;
  altAddressState?: string;
  altAddressPostalcode?: string;
  altAddressCountry?: string;
  assistant?: string;
  assistantPhone?: string;
  leadSource?: string;
  accountId?: string;
  accountName?: string;
  birthdate?: string;
  portalName?: string;
  portalActive?: number;
  portalPassword?: string;
}

// Lead type for frontend use
export interface Lead extends BaseEntity {
  salutation?: string;
  firstName?: string;
  lastName?: string;
  title?: string;
  department?: string;
  doNotCall?: number;
  phoneHome?: string;
  phoneMobile?: string;
  phoneWork?: string;
  phoneOther?: string;
  phoneFax?: string;
  email1?: string;
  email2?: string;
  primaryAddressStreet?: string;
  primaryAddressCity?: string;
  primaryAddressState?: string;
  primaryAddressPostalcode?: string;
  primaryAddressCountry?: string;
  altAddressStreet?: string;
  altAddressCity?: string;
  altAddressState?: string;
  altAddressPostalcode?: string;
  altAddressCountry?: string;
  assistant?: string;
  assistantPhone?: string;
  converted?: number;
  referedBy?: string;
  leadSource?: string;
  leadSourceDescription?: string;
  status?: string;
  statusDescription?: string;
  reportsToId?: string;
  accountName?: string;
  accountDescription?: string;
  contactId?: string;
  accountId?: string;
  opportunityId?: string;
  opportunityName?: string;
  opportunityAmount?: string;
  campaignId?: string;
  website?: string;
}

// Account type for frontend use
export interface Account extends BaseEntity {
  accountType?: string;
  industry?: string;
  annualRevenue?: string;
  phoneFax?: string;
  billingAddressStreet?: string;
  billingAddressCity?: string;
  billingAddressState?: string;
  billingAddressPostalcode?: string;
  billingAddressCountry?: string;
  rating?: string;
  phoneOffice?: string;
  phoneAlternate?: string;
  website?: string;
  ownership?: string;
  employees?: string;
  tickerSymbol?: string;
  shippingAddressStreet?: string;
  shippingAddressCity?: string;
  shippingAddressState?: string;
  shippingAddressPostalcode?: string;
  shippingAddressCountry?: string;
  parentId?: string;
  sicCode?: string;
  campaignId?: string;
}

// Opportunity type for frontend use
export interface Opportunity extends BaseEntity {
  opportunityType?: string;
  accountName?: string;
  accountId?: string;
  campaignId?: string;
  leadSource?: string;
  amount?: string;
  amountUsdollar?: string;
  currencyId?: string;
  dateClosed?: string;
  nextStep?: string;
  salesStage?: string;
  probability?: string;
}

// Case type for frontend use
export interface Case extends BaseEntity {
  caseNumber?: string;
  type?: string;
  status?: string;
  priority?: string;
  resolution?: string;
  workLog?: string;
  accountId?: string;
  accountName?: string;
  contactId?: string;
}

// Task type for frontend use
export interface Task extends BaseEntity {
  status?: string;
  dateStart?: string;
  dateDue?: string;
  timeDue?: string;
  parentType?: string;
  parentId?: string;
  contactId?: string;
  priority?: string;
}

// Meeting type for frontend use
export interface Meeting extends BaseEntity {
  location?: string;
  durationHours?: number;
  durationMinutes?: number;
  dateStart?: string;
  dateEnd?: string;
  parentType?: string;
  parentId?: string;
  status?: string;
  type?: string;
  direction?: string;
  reminderTime?: number;
  emailReminderTime?: number;
  emailReminderSent?: number;
  outlookId?: string;
  sequence?: number;
}

// Call type for frontend use
export interface Call extends BaseEntity {
  durationHours?: number;
  durationMinutes?: number;
  dateStart?: string;
  dateEnd?: string;
  parentType?: string;
  parentId?: string;
  status?: string;
  direction?: string;
  reminderTime?: number;
  emailReminderTime?: number;
  emailReminderSent?: number;
  outlookId?: string;
}

// Note type for frontend use
export interface Note extends BaseEntity {
  fileMimeType?: string;
  filename?: string;
  parentType?: string;
  parentId?: string;
  contactId?: string;
  portalFlag?: number;
  embedFlag?: number;
}

// Email type for frontend use
export interface Email extends BaseEntity {
  dateSent?: string;
  messageId?: string;
  messageUid?: string;
  intent?: string;
  mailboxId?: string;
  fromAddr?: string;
  fromName?: string;
  toAddrs?: string;
  ccAddrs?: string;
  bccAddrs?: string;
  replyToAddr?: string;
  parentType?: string;
  parentId?: string;
  status?: string;
  type?: string;
  flagged?: number;
  replyToStatus?: number;
}

// Quote type for frontend use
export interface Quote extends BaseEntity {
  quoteNum?: string;
  quoteStage?: string;
  purchaseOrderNum?: string;
  quoteDate?: string;
  calcGrandTotal?: string;
  billingAccountId?: string;
  billingContactId?: string;
  billingAddressStreet?: string;
  billingAddressCity?: string;
  billingAddressState?: string;
  billingAddressPostalcode?: string;
  billingAddressCountry?: string;
  shippingAccountId?: string;
  shippingContactId?: string;
  shippingAddressStreet?: string;
  shippingAddressCity?: string;
  shippingAddressState?: string;
  shippingAddressPostalcode?: string;
  shippingAddressCountry?: string;
  expiration?: string;
  opportunityId?: string;
}

// Union type for all entity types
export type CRMEntity = 
  | Contact 
  | Lead 
  | Account 
  | Opportunity 
  | Case 
  | Task 
  | Meeting 
  | Call 
  | Note 
  | Email 
  | Quote;

// Pagination response wrapper
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    page: number;
    pageSize: number;
    totalPages: number;
    totalCount: number;
    hasNext: boolean;
    hasPrevious: boolean;
  };
  links?: {
    first?: string;
    last?: string;
    prev?: string;
    next?: string;
  };
}

// API error response
export interface ApiError {
  message: string;
  code?: string;
  details?: unknown;
}

// Filter operators
export type FilterOperator = 'eq' | 'ne' | 'lt' | 'lte' | 'gt' | 'gte' | 'like' | 'in' | 'not_in';

// Filter structure
export interface Filter {
  field: string;
  operator: FilterOperator;
  value: unknown;
}

// Query parameters for list endpoints
export interface QueryParams {
  page?: number;
  pageSize?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
  filters?: Record<string, unknown>;
  search?: string;
  include?: string[];
}