// Generated from database schema on 2025-07-27T13:41:05.540Z

import { z } from 'zod';

export const LeadCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  salutation: z.enum(["Mr.", "Ms.", "Mrs.", "Dr.", "Prof."]).optional(),
  first_name: z.string().max(100).optional(),
  last_name: z.string().max(100).optional(),
  title: z.string().max(100).optional(),
  department: z.string().max(100).optional(),
  phone_work: z.string().max(100).optional(),
  phone_mobile: z.string().max(100).optional(),
  email1: z.string().max(100).email().optional(),
  primary_address_street: z.string().max(150).optional(),
  primary_address_city: z.string().max(100).optional(),
  primary_address_state: z.string().max(100).optional(),
  primary_address_postalcode: z.string().max(20).optional(),
  primary_address_country: z.string().max(255).optional(),
  status: z.enum(["new", "contacted", "qualified", "converted", "dead"]).optional(),
  status_description: z.string().max(65535).optional(),
  lead_source: z.enum(["website", "referral", "cold_call", "conference", "advertisement"]).optional(),
  lead_source_description: z.string().max(65535).optional(),
  description: z.string().max(65535).optional(),
  account_name: z.string().max(255).optional(),
  website: z.string().max(255).url().optional(),
  ai_score: z.number().int().optional(),
  ai_score_date: z.string().datetime().optional(),
  ai_insights: z.array(z.any()).optional(),
  ai_next_best_action: z.string().max(255).optional(),
});

export type LeadCreateInput = z.infer<typeof LeadCreateSchema>;

export const LeadUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  salutation: z.enum(["Mr.", "Ms.", "Mrs.", "Dr.", "Prof."]).optional(),
  first_name: z.string().max(100).optional(),
  last_name: z.string().max(100).optional(),
  title: z.string().max(100).optional(),
  department: z.string().max(100).optional(),
  phone_work: z.string().max(100).optional(),
  phone_mobile: z.string().max(100).optional(),
  email1: z.string().max(100).email().optional(),
  primary_address_street: z.string().max(150).optional(),
  primary_address_city: z.string().max(100).optional(),
  primary_address_state: z.string().max(100).optional(),
  primary_address_postalcode: z.string().max(20).optional(),
  primary_address_country: z.string().max(255).optional(),
  status: z.enum(["new", "contacted", "qualified", "converted", "dead"]).optional(),
  status_description: z.string().max(65535).optional(),
  lead_source: z.enum(["website", "referral", "cold_call", "conference", "advertisement"]).optional(),
  lead_source_description: z.string().max(65535).optional(),
  description: z.string().max(65535).optional(),
  account_name: z.string().max(255).optional(),
  website: z.string().max(255).url().optional(),
  ai_score: z.number().int().optional(),
  ai_score_date: z.string().datetime().optional(),
  ai_insights: z.array(z.any()).optional(),
  ai_next_best_action: z.string().max(255).optional(),
});

export type LeadUpdateInput = z.infer<typeof LeadUpdateSchema>;

export const ContactCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  salutation: z.enum(["Mr.", "Ms.", "Mrs.", "Dr.", "Prof."]).optional(),
  first_name: z.string().max(100).optional(),
  last_name: z.string().max(100).optional(),
  title: z.string().max(100).optional(),
  department: z.string().max(255).optional(),
  phone_work: z.string().max(100).optional(),
  phone_mobile: z.string().max(100).optional(),
  email1: z.string().max(100).email().optional(),
  primary_address_street: z.string().max(150).optional(),
  primary_address_city: z.string().max(100).optional(),
  primary_address_state: z.string().max(100).optional(),
  primary_address_postalcode: z.string().max(20).optional(),
  primary_address_country: z.string().max(255).optional(),
  description: z.string().max(65535).optional(),
  lead_source: z.enum(["website", "referral", "conversion", "other"]).optional(),
  lifetime_value: z.number().optional(),
  engagement_score: z.number().int().optional(),
  last_activity_date: z.string().datetime().optional(),
});

export type ContactCreateInput = z.infer<typeof ContactCreateSchema>;

export const ContactUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  salutation: z.enum(["Mr.", "Ms.", "Mrs.", "Dr.", "Prof."]).optional(),
  first_name: z.string().max(100).optional(),
  last_name: z.string().max(100).optional(),
  title: z.string().max(100).optional(),
  department: z.string().max(255).optional(),
  phone_work: z.string().max(100).optional(),
  phone_mobile: z.string().max(100).optional(),
  email1: z.string().max(100).email().optional(),
  primary_address_street: z.string().max(150).optional(),
  primary_address_city: z.string().max(100).optional(),
  primary_address_state: z.string().max(100).optional(),
  primary_address_postalcode: z.string().max(20).optional(),
  primary_address_country: z.string().max(255).optional(),
  description: z.string().max(65535).optional(),
  lead_source: z.enum(["website", "referral", "conversion", "other"]).optional(),
  lifetime_value: z.number().optional(),
  engagement_score: z.number().int().optional(),
  last_activity_date: z.string().datetime().optional(),
});

export type ContactUpdateInput = z.infer<typeof ContactUpdateSchema>;

export const AccountCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(150).optional(),
  account_type: z.string().max(50).optional(),
  industry: z.string().max(50).optional(),
  annual_revenue: z.string().max(100).optional(),
  phone_office: z.string().max(100).optional(),
  website: z.string().max(255).url().optional(),
  employees: z.string().max(10).optional(),
  billing_address_street: z.string().max(150).optional(),
  billing_address_city: z.string().max(100).optional(),
  billing_address_state: z.string().max(100).optional(),
  billing_address_postalcode: z.string().max(20).optional(),
  billing_address_country: z.string().max(255).optional(),
  description: z.string().max(65535).optional(),
});

export type AccountCreateInput = z.infer<typeof AccountCreateSchema>;

export const AccountUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(150).optional(),
  account_type: z.string().max(50).optional(),
  industry: z.string().max(50).optional(),
  annual_revenue: z.string().max(100).optional(),
  phone_office: z.string().max(100).optional(),
  website: z.string().max(255).url().optional(),
  employees: z.string().max(10).optional(),
  billing_address_street: z.string().max(150).optional(),
  billing_address_city: z.string().max(100).optional(),
  billing_address_state: z.string().max(100).optional(),
  billing_address_postalcode: z.string().max(20).optional(),
  billing_address_country: z.string().max(255).optional(),
  description: z.string().max(65535).optional(),
});

export type AccountUpdateInput = z.infer<typeof AccountUpdateSchema>;

export const OpportunitieCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  opportunity_type: z.enum(["new_business", "existing_business", "renewal"]).optional(),
  account_id: z.string().max(36).optional(),
  lead_source: z.string().max(50).optional(),
  amount: z.number().optional(),
  amount_usdollar: z.number().optional(),
  currency_id: z.string().max(36).optional(),
  date_closed: z.string().datetime().optional(),
  next_step: z.string().max(100).optional(),
  sales_stage: z.enum(["prospecting", "qualification", "needs_analysis", "value_proposition", "decision_makers", "perception_analysis", "proposal", "negotiation", "closed_won", "closed_lost"]).optional(),
  probability: z.number().optional(),
  description: z.string().max(65535).optional(),
  ai_close_probability: z.number().optional(),
  ai_risk_factors: z.array(z.any()).optional(),
  ai_recommendations: z.array(z.any()).optional(),
});

export type OpportunitieCreateInput = z.infer<typeof OpportunitieCreateSchema>;

export const OpportunitieUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  opportunity_type: z.enum(["new_business", "existing_business", "renewal"]).optional(),
  account_id: z.string().max(36).optional(),
  lead_source: z.string().max(50).optional(),
  amount: z.number().optional(),
  amount_usdollar: z.number().optional(),
  currency_id: z.string().max(36).optional(),
  date_closed: z.string().datetime().optional(),
  next_step: z.string().max(100).optional(),
  sales_stage: z.enum(["prospecting", "qualification", "needs_analysis", "value_proposition", "decision_makers", "perception_analysis", "proposal", "negotiation", "closed_won", "closed_lost"]).optional(),
  probability: z.number().optional(),
  description: z.string().max(65535).optional(),
  ai_close_probability: z.number().optional(),
  ai_risk_factors: z.array(z.any()).optional(),
  ai_recommendations: z.array(z.any()).optional(),
});

export type OpportunitieUpdateInput = z.infer<typeof OpportunitieUpdateSchema>;

export const CaseCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  case_number: z.number().int(),
  name: z.string().max(255).optional(),
  account_id: z.string().max(36).optional(),
  contact_id: z.string().max(36).optional(),
  status: z.enum(["new", "assigned", "pending", "resolved", "closed"]).optional(),
  priority: z.enum(["P1", "P2", "P3"]).optional(),
  type: z.enum(["bug", "feature_request", "question", "other"]).optional(),
  description: z.string().max(65535).optional(),
  resolution: z.string().max(65535).optional(),
});

export type CaseCreateInput = z.infer<typeof CaseCreateSchema>;

export const CaseUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  case_number: z.number().int().optional(),
  name: z.string().max(255).optional(),
  account_id: z.string().max(36).optional(),
  contact_id: z.string().max(36).optional(),
  status: z.enum(["new", "assigned", "pending", "resolved", "closed"]).optional(),
  priority: z.enum(["P1", "P2", "P3"]).optional(),
  type: z.enum(["bug", "feature_request", "question", "other"]).optional(),
  description: z.string().max(65535).optional(),
  resolution: z.string().max(65535).optional(),
});

export type CaseUpdateInput = z.infer<typeof CaseUpdateSchema>;

export const UserCreateSchema = z.object({
  user_name: z.string().max(60).optional(),
  user_hash: z.string().max(255).optional(),
  first_name: z.string().max(255).optional(),
  last_name: z.string().max(255).optional(),
  department: z.string().max(50).optional(),
  title: z.string().max(50).optional(),
  phone_work: z.string().max(50).optional(),
  email1: z.string().max(100).email().optional(),
  status: z.enum(["active", "inactive"]).optional(),
  is_admin: z.number().int().optional(),
  created_by: z.string().max(36).optional(),
});

export type UserCreateInput = z.infer<typeof UserCreateSchema>;

export const UserUpdateSchema = z.object({
  user_name: z.string().max(60).optional(),
  user_hash: z.string().max(255).optional(),
  first_name: z.string().max(255).optional(),
  last_name: z.string().max(255).optional(),
  department: z.string().max(50).optional(),
  title: z.string().max(50).optional(),
  phone_work: z.string().max(50).optional(),
  email1: z.string().max(100).email().optional(),
  status: z.enum(["active", "inactive"]).optional(),
  is_admin: z.number().int().optional(),
  created_by: z.string().max(36).optional(),
});

export type UserUpdateInput = z.infer<typeof UserUpdateSchema>;

export const TaskCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  status: z.string().max(100).optional(),
  priority: z.string().max(100).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  contact_id: z.string().max(36).optional(),
  date_due: z.string().datetime().optional(),
  description: z.string().max(65535).optional(),
});

export type TaskCreateInput = z.infer<typeof TaskCreateSchema>;

export const TaskUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  status: z.string().max(100).optional(),
  priority: z.string().max(100).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  contact_id: z.string().max(36).optional(),
  date_due: z.string().datetime().optional(),
  description: z.string().max(65535).optional(),
});

export type TaskUpdateInput = z.infer<typeof TaskUpdateSchema>;

export const CallCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  duration_hours: z.number().int().optional(),
  duration_minutes: z.number().int().optional(),
  date_start: z.string().datetime().optional(),
  status: z.string().max(100).optional(),
  direction: z.string().max(100).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  description: z.string().max(65535).optional(),
});

export type CallCreateInput = z.infer<typeof CallCreateSchema>;

export const CallUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  duration_hours: z.number().int().optional(),
  duration_minutes: z.number().int().optional(),
  date_start: z.string().datetime().optional(),
  status: z.string().max(100).optional(),
  direction: z.string().max(100).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  description: z.string().max(65535).optional(),
});

export type CallUpdateInput = z.infer<typeof CallUpdateSchema>;

export const MeetingCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  location: z.string().max(50).optional(),
  duration_hours: z.number().int().optional(),
  duration_minutes: z.number().int().optional(),
  date_start: z.string().datetime().optional(),
  date_end: z.string().datetime().optional(),
  status: z.string().max(100).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  description: z.string().max(65535).optional(),
});

export type MeetingCreateInput = z.infer<typeof MeetingCreateSchema>;

export const MeetingUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(50).optional(),
  location: z.string().max(50).optional(),
  duration_hours: z.number().int().optional(),
  duration_minutes: z.number().int().optional(),
  date_start: z.string().datetime().optional(),
  date_end: z.string().datetime().optional(),
  status: z.string().max(100).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  description: z.string().max(65535).optional(),
});

export type MeetingUpdateInput = z.infer<typeof MeetingUpdateSchema>;

export const NoteCreateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(255).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  contact_id: z.string().max(36).optional(),
  description: z.string().max(65535).optional(),
});

export type NoteCreateInput = z.infer<typeof NoteCreateSchema>;

export const NoteUpdateSchema = z.object({
  created_by: z.string().max(36).optional(),
  modified_user_id: z.string().max(36).optional(),
  assigned_user_id: z.string().max(36).optional(),
  name: z.string().max(255).optional(),
  parent_type: z.string().max(100).optional(),
  parent_id: z.string().max(36).optional(),
  contact_id: z.string().max(36).optional(),
  description: z.string().max(65535).optional(),
});

export type NoteUpdateInput = z.infer<typeof NoteUpdateSchema>;

