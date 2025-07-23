/**
 * Auto-generated Zod schemas from PHP DTOs
 * Generated: 2025-07-23 02:05:11
 * DO NOT EDIT MANUALLY
 */

import { z } from 'zod';

// Base Schemas
export const PaginationSchema = z.object({
  page: z.number().int().positive(),
  pageSize: z.number().int().positive(),
  totalPages: z.number().int().nonnegative(),
  totalCount: z.number().int().nonnegative(),
  hasNext: z.boolean(),
  hasPrevious: z.boolean()
});

export const ErrorResponseSchema = z.object({
  error: z.string(),
  code: z.string(),
  details: z.any().optional(),
  timestamp: z.string().optional()
});

// Entity Schemas
export const ContactSchema = z.object({
  id: z.string().optional(),
  firstName: z.string().min(1),
  lastName: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  mobile: z.string().optional(),
  title: z.string().optional(),
  birthDate: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  customerNumber: z.string().optional(),
  preferredContactMethod: z.enum(['email', 'phone', 'sms']).optional(),
  tags: z.array(z.string()).optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const LeadSchema = z.object({
  id: z.string().optional(),
  firstName: z.string().min(1),
  lastName: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  mobile: z.string().optional(),
  title: z.string().optional(),
  company: z.string().optional(),
  website: z.string().url().optional(),
  description: z.string().optional(),
  status: z.enum(['New', 'Contacted', 'Qualified', 'Converted', 'Dead']),
  source: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  convertedContactId: z.string().optional(),
  convertedAt: z.string().optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const OpportunitySchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  amount: z.number().nonnegative(),
  currency: z.string().optional(),
  probability: z.number().min(0).max(100).optional(),
  salesStage: z.string(),
  closeDate: z.string(),
  description: z.string().optional(),
  nextStep: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  aiInsights: z.string().optional(),
  aiScore: z.number().min(0).max(100).optional(),
  aiRecommendations: z.array(z.string()).optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const TaskSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  status: z.enum(['Not Started', 'In Progress', 'Completed', 'Pending Input', 'Deferred']),
  priority: z.enum(['High', 'Medium', 'Low']),
  dueDate: z.string().optional(),
  startDate: z.string().optional(),
  percentComplete: z.number().min(0).max(100).optional(),
  description: z.string().optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  reminderTime: z.string().optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const CaseSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  caseNumber: z.string().optional(),
  type: z.string().optional(),
  status: z.enum(['New', 'Assigned', 'Closed', 'Pending Input', 'Rejected', 'Duplicate']),
  priority: z.enum(['High', 'Medium', 'Low']),
  resolution: z.string().optional(),
  description: z.string().optional(),
  subject: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  attachments: z.array(z.object({
    id: z.string(),
    name: z.string(),
    mimeType: z.string(),
    size: z.number()
  })).optional(),
  updates: z.array(z.object({
    id: z.string(),
    text: z.string(),
    internal: z.boolean(),
    createdBy: z.string(),
    createdAt: z.string()
  })).optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const AddressSchema = z.object({
  street: z.string().optional(),
  city: z.string().optional(),
  state: z.string().optional(),
  postalCode: z.string().optional(),
  country: z.string().optional()
});

export const LineItemSchema = z.object({
  id: z.string().optional(),
  productId: z.string().optional(),
  name: z.string().min(1),
  description: z.string().optional(),
  quantity: z.number().positive(),
  unitPrice: z.number().nonnegative(),
  discount: z.number().nonnegative().optional(),
  tax: z.number().nonnegative().optional(),
  total: z.number().nonnegative().optional()
});

export const QuoteSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  quoteNumber: z.string().optional(),
  stage: z.enum(['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired']),
  validUntil: z.string().optional(),
  opportunityId: z.string().optional(),
  opportunityName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  billingAddress: AddressSchema.optional(),
  shippingAddress: AddressSchema.optional(),
  currency: z.string().optional(),
  subtotal: z.number().nonnegative().optional(),
  tax: z.number().nonnegative().optional(),
  shipping: z.number().nonnegative().optional(),
  total: z.number().nonnegative().optional(),
  discount: z.number().nonnegative().optional(),
  discountType: z.enum(['Percentage', 'Amount']).optional(),
  lineItems: z.array(LineItemSchema).optional(),
  terms: z.string().optional(),
  description: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const EmailAddressSchema = z.object({
  email: z.string().email(),
  name: z.string().optional()
});

export const AttachmentSchema = z.object({
  id: z.string(),
  name: z.string(),
  mimeType: z.string(),
  size: z.number(),
  content: z.string().optional() // Base64
});

export const EmailSchema = z.object({
  id: z.string().optional(),
  subject: z.string().min(1),
  body: z.string(),
  bodyHtml: z.string().optional(),
  from: EmailAddressSchema,
  to: z.array(EmailAddressSchema).min(1),
  cc: z.array(EmailAddressSchema).optional(),
  bcc: z.array(EmailAddressSchema).optional(),
  replyTo: EmailAddressSchema.optional(),
  status: z.enum(['draft', 'sent', 'received', 'archived']),
  type: z.enum(['inbound', 'outbound', 'draft']),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  attachments: z.array(AttachmentSchema).optional(),
  messageId: z.string().optional(),
  inReplyTo: z.string().optional(),
  importance: z.enum(['high', 'normal', 'low']).optional(),
  flags: z.array(z.string()).optional(),
  folder: z.string().optional(),
  threadId: z.string().optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const RecurrenceRuleSchema = z.object({
  frequency: z.enum(['daily', 'weekly', 'monthly', 'yearly']),
  interval: z.number().positive().optional(),
  count: z.number().positive().optional(),
  until: z.string().optional(),
  byDay: z.array(z.string()).optional(),
  byMonth: z.array(z.number()).optional(),
  byMonthDay: z.array(z.number()).optional()
});

export const CallSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  direction: z.enum(['Inbound', 'Outbound']),
  status: z.enum(['Planned', 'Held', 'Cancelled']),
  startDate: z.string(),
  duration: z.number().nonnegative().optional(),
  phoneNumber: z.string().optional(),
  description: z.string().optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  reminderTime: z.string().optional(),
  result: z.string().optional(),
  recordingUrl: z.string().url().optional(),
  recurrence: RecurrenceRuleSchema.optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const InviteeSchema = z.object({
  id: z.string(),
  type: z.enum(['contact', 'lead', 'user']),
  name: z.string(),
  email: z.string().email().optional(),
  status: z.enum(['invited', 'accepted', 'declined', 'tentative'])
});

export const MeetingSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  status: z.enum(['Planned', 'Held', 'Cancelled']),
  type: z.enum(['In Person', 'Virtual', 'Phone']),
  startDate: z.string(),
  endDate: z.string(),
  duration: z.number().nonnegative().optional(),
  location: z.string().optional(),
  meetingUrl: z.string().url().optional(),
  description: z.string().optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  invitees: z.array(InviteeSchema).optional(),
  reminderTime: z.string().optional(),
  agenda: z.string().optional(),
  minutes: z.string().optional(),
  recurrence: RecurrenceRuleSchema.optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const NoteSchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1),
  description: z.string().optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  attachment: AttachmentSchema.optional(),
  tags: z.array(z.string()).optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional()
});

export const ActivitySchema = z.object({
  id: z.string(),
  type: z.enum(['Call', 'Meeting', 'Email', 'Task', 'Note']),
  subject: z.string(),
  description: z.string().optional(),
  date: z.string(),
  status: z.string().optional(),
  relatedTo: z.string().optional(),
  relatedType: z.string().optional(),
  relatedId: z.string().optional(),
  assignedTo: z.string().optional(),
  assignedToName: z.string().optional(),
  createdBy: z.string().optional(),
  createdByName: z.string().optional(),
  createdAt: z.string(),
  updatedAt: z.string().optional()
});

// Auth Schemas
export const LoginRequestSchema = z.object({
  username: z.string().min(1),
  password: z.string().min(1)
});

export const LoginResponseSchema = z.object({
  accessToken: z.string(),
  refreshToken: z.string(),
  expiresIn: z.number(),
  tokenType: z.string(),
  user: z.object({
    id: z.string(),
    username: z.string(),
    email: z.string().email(),
    firstName: z.string(),
    lastName: z.string()
  })
});

export const RefreshTokenRequestSchema = z.object({
  refreshToken: z.string()
});

export const RefreshTokenResponseSchema = z.object({
  accessToken: z.string(),
  refreshToken: z.string(),
  expiresIn: z.number(),
  tokenType: z.string()
});

// Response Schemas
export const ApiResponseSchema = <T extends z.ZodType>(dataSchema: T) =>
  z.object({
    success: z.boolean(),
    data: dataSchema.optional(),
    error: ErrorResponseSchema.optional(),
    pagination: PaginationSchema.optional()
  });

export const ListResponseSchema = <T extends z.ZodType>(itemSchema: T) =>
  z.object({
    data: z.array(itemSchema),
    pagination: PaginationSchema
  });

// Utility Schemas
export const FilterOperatorSchema = z.enum(['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'like', 'in', 'between']);

export const FilterSchema = z.object({
  field: z.string(),
  operator: FilterOperatorSchema,
  value: z.any()
});

export const SortSchema = z.object({
  field: z.string(),
  direction: z.enum(['asc', 'desc'])
});

export const QueryParamsSchema = z.object({
  page: z.number().int().positive().optional(),
  pageSize: z.number().int().positive().optional(),
  filters: z.array(FilterSchema).optional(),
  sort: z.array(SortSchema).optional(),
  search: z.string().optional(),
  fields: z.array(z.string()).optional()
});

// Type inference helpers
export type Contact = z.infer<typeof ContactSchema>;
export type Lead = z.infer<typeof LeadSchema>;
export type Opportunity = z.infer<typeof OpportunitySchema>;
export type Task = z.infer<typeof TaskSchema>;
export type Case = z.infer<typeof CaseSchema>;
export type Quote = z.infer<typeof QuoteSchema>;
export type Email = z.infer<typeof EmailSchema>;
export type Call = z.infer<typeof CallSchema>;
export type Meeting = z.infer<typeof MeetingSchema>;
export type Note = z.infer<typeof NoteSchema>;
export type Activity = z.infer<typeof ActivitySchema>;
export type Pagination = z.infer<typeof PaginationSchema>;
export type ErrorResponse = z.infer<typeof ErrorResponseSchema>;
export type LoginRequest = z.infer<typeof LoginRequestSchema>;
export type LoginResponse = z.infer<typeof LoginResponseSchema>;
export type RefreshTokenRequest = z.infer<typeof RefreshTokenRequestSchema>;
export type RefreshTokenResponse = z.infer<typeof RefreshTokenResponseSchema>;
