// Generated from database schema on 2025-07-27 21:19:48

export interface LeadDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  salutation: string | null;
  first_name: string | null;
  last_name: string | null;
  title: string | null;
  department: string | null;
  phone_work: string | null;
  phone_mobile: string | null;
  email1: string | null;
  primary_address_street: string | null;
  primary_address_city: string | null;
  primary_address_state: string | null;
  primary_address_postalcode: string | null;
  primary_address_country: string | null;
  status: string | null;
  status_description: string | null;
  lead_source: string | null;
  lead_source_description: string | null;
  description: string | null;
  account_name: string | null;
  website: string | null;
  ai_score: number | null;
  ai_score_date: Date | string | null;
  ai_insights: Record<string, any> | null;
  ai_next_best_action: string | null;
}

export type LeadCreateRequest = Pick<LeadDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'salutation' | 'first_name' | 'last_name' | 'title' | 'department' | 'phone_work' | 'phone_mobile' | 'email1' | 'primary_address_street' | 'primary_address_city' | 'primary_address_state' | 'primary_address_postalcode' | 'primary_address_country' | 'status' | 'status_description' | 'lead_source' | 'lead_source_description' | 'description' | 'account_name' | 'website' | 'ai_score' | 'ai_score_date' | 'ai_insights' | 'ai_next_best_action'
>;

export type LeadUpdateRequest = Partial<LeadCreateRequest>;

export type LeadResponse = LeadDB;

export interface ContactDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  account_id: string | null;
  deleted: number | null;
  salutation: string | null;
  first_name: string | null;
  last_name: string | null;
  title: string | null;
  department: string | null;
  phone_work: string | null;
  phone_mobile: string | null;
  email1: string | null;
  primary_address_street: string | null;
  primary_address_city: string | null;
  primary_address_state: string | null;
  primary_address_postalcode: string | null;
  primary_address_country: string | null;
  description: string | null;
  lead_source: string | null;
  lifetime_value: number | null;
  engagement_score: number | null;
  last_activity_date: Date | string | null;
}

export type ContactCreateRequest = Pick<ContactDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'account_id' | 'salutation' | 'first_name' | 'last_name' | 'title' | 'department' | 'phone_work' | 'phone_mobile' | 'email1' | 'primary_address_street' | 'primary_address_city' | 'primary_address_state' | 'primary_address_postalcode' | 'primary_address_country' | 'description' | 'lead_source' | 'lifetime_value' | 'engagement_score' | 'last_activity_date'
>;

export type ContactUpdateRequest = Partial<ContactCreateRequest>;

export type ContactResponse = ContactDB;

export interface AccountDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  name: string | null;
  account_type: string | null;
  industry: string | null;
  annual_revenue: string | null;
  phone_office: string | null;
  website: string | null;
  employees: string | null;
  billing_address_street: string | null;
  billing_address_city: string | null;
  billing_address_state: string | null;
  billing_address_postalcode: string | null;
  billing_address_country: string | null;
  description: string | null;
}

export type AccountCreateRequest = Pick<AccountDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'name' | 'account_type' | 'industry' | 'annual_revenue' | 'phone_office' | 'website' | 'employees' | 'billing_address_street' | 'billing_address_city' | 'billing_address_state' | 'billing_address_postalcode' | 'billing_address_country' | 'description'
>;

export type AccountUpdateRequest = Partial<AccountCreateRequest>;

export type AccountResponse = AccountDB;

export interface OpportunityDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  name: string | null;
  opportunity_type: string | null;
  account_id: string | null;
  lead_source: string | null;
  amount: number | null;
  amount_usdollar: number | null;
  currency_id: string | null;
  date_closed: Date | string | null;
  next_step: string | null;
  sales_stage: string | null;
  probability: number | null;
  description: string | null;
  ai_close_probability: number | null;
  ai_risk_factors: Record<string, any> | null;
  ai_recommendations: Record<string, any> | null;
}

export type OpportunityCreateRequest = Pick<OpportunityDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'name' | 'opportunity_type' | 'account_id' | 'lead_source' | 'amount' | 'amount_usdollar' | 'currency_id' | 'date_closed' | 'next_step' | 'sales_stage' | 'probability' | 'description' | 'ai_close_probability' | 'ai_risk_factors' | 'ai_recommendations'
>;

export type OpportunityUpdateRequest = Partial<OpportunityCreateRequest>;

export type OpportunityResponse = OpportunityDB;

export interface CaseDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  case_number: number;
  name: string | null;
  account_id: string | null;
  contact_id: string | null;
  status: string | null;
  priority: string | null;
  type: string | null;
  description: string | null;
  resolution: string | null;
}

export type CaseCreateRequest = Pick<CaseDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'case_number' | 'name' | 'account_id' | 'contact_id' | 'status' | 'priority' | 'type' | 'description' | 'resolution'
>;

export type CaseUpdateRequest = Partial<CaseCreateRequest>;

export type CaseResponse = CaseDB;

export interface UserDB {
  id: string;
  user_name: string | null;
  user_hash: string | null;
  first_name: string | null;
  last_name: string | null;
  department: string | null;
  title: string | null;
  phone_work: string | null;
  email1: string | null;
  status: string | null;
  is_admin: number | null;
  deleted: number | null;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
}

export type UserCreateRequest = Pick<UserDB,
  'user_name' | 'user_hash' | 'first_name' | 'last_name' | 'department' | 'title' | 'phone_work' | 'email1' | 'status' | 'is_admin' | 'created_by'
>;

export type UserUpdateRequest = Partial<UserCreateRequest>;

export type UserResponse = UserDB;

export interface TaskDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  name: string | null;
  status: string | null;
  priority: string | null;
  parent_type: string | null;
  parent_id: string | null;
  contact_id: string | null;
  date_due: Date | string | null;
  description: string | null;
}

export type TaskCreateRequest = Pick<TaskDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'name' | 'status' | 'priority' | 'parent_type' | 'parent_id' | 'contact_id' | 'date_due' | 'description'
>;

export type TaskUpdateRequest = Partial<TaskCreateRequest>;

export type TaskResponse = TaskDB;

export interface CallDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  name: string | null;
  duration_hours: number | null;
  duration_minutes: number | null;
  date_start: Date | string | null;
  status: string | null;
  direction: string | null;
  parent_type: string | null;
  parent_id: string | null;
  contact_id: string | null;
  description: string | null;
}

export type CallCreateRequest = Pick<CallDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'name' | 'duration_hours' | 'duration_minutes' | 'date_start' | 'status' | 'direction' | 'parent_type' | 'parent_id' | 'contact_id' | 'description'
>;

export type CallUpdateRequest = Partial<CallCreateRequest>;

export type CallResponse = CallDB;

export interface MeetingDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  name: string | null;
  location: string | null;
  duration_hours: number | null;
  duration_minutes: number | null;
  date_start: Date | string | null;
  date_end: Date | string | null;
  status: string | null;
  parent_type: string | null;
  parent_id: string | null;
  contact_id: string | null;
  description: string | null;
}

export type MeetingCreateRequest = Pick<MeetingDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'name' | 'location' | 'duration_hours' | 'duration_minutes' | 'date_start' | 'date_end' | 'status' | 'parent_type' | 'parent_id' | 'contact_id' | 'description'
>;

export type MeetingUpdateRequest = Partial<MeetingCreateRequest>;

export type MeetingResponse = MeetingDB;

export interface NoteDB {
  id: string;
  date_entered: Date | string | null;
  date_modified: Date | string | null;
  created_by: string | null;
  modified_user_id: string | null;
  assigned_user_id: string | null;
  deleted: number | null;
  name: string | null;
  parent_type: string | null;
  parent_id: string | null;
  contact_id: string | null;
  description: string | null;
}

export type NoteCreateRequest = Pick<NoteDB,
  'created_by' | 'modified_user_id' | 'assigned_user_id' | 'name' | 'parent_type' | 'parent_id' | 'contact_id' | 'description'
>;

export type NoteUpdateRequest = Partial<NoteCreateRequest>;

export type NoteResponse = NoteDB;

