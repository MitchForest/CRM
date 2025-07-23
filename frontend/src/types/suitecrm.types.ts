// SuiteCRM types with snake_case field names matching the CRM's database schema

// Common fields shared across all SuiteCRM modules
export interface SuiteCRMBaseFields {
  id: string;
  name?: string;
  date_entered: string;
  date_modified: string;
  modified_user_id: string;
  created_by: string;
  description?: string;
  deleted: number;
  assigned_user_id?: string;
}

// Contact module fields
export interface SuiteCRMContact extends SuiteCRMBaseFields {
  salutation?: string;
  first_name?: string;
  last_name?: string;
  title?: string;
  department?: string;
  do_not_call?: number;
  phone_home?: string;
  phone_mobile?: string;
  phone_work?: string;
  phone_other?: string;
  phone_fax?: string;
  email1?: string;
  email2?: string;
  primary_address_street?: string;
  primary_address_city?: string;
  primary_address_state?: string;
  primary_address_postalcode?: string;
  primary_address_country?: string;
  alt_address_street?: string;
  alt_address_city?: string;
  alt_address_state?: string;
  alt_address_postalcode?: string;
  alt_address_country?: string;
  assistant?: string;
  assistant_phone?: string;
  lead_source?: string;
  account_id?: string;
  account_name?: string;
  birthdate?: string;
  portal_name?: string;
  portal_active?: number;
  portal_password?: string;
}

// Lead module fields
export interface SuiteCRMLead extends SuiteCRMBaseFields {
  salutation?: string;
  first_name?: string;
  last_name?: string;
  title?: string;
  department?: string;
  do_not_call?: number;
  phone_home?: string;
  phone_mobile?: string;
  phone_work?: string;
  phone_other?: string;
  phone_fax?: string;
  email1?: string;
  email2?: string;
  primary_address_street?: string;
  primary_address_city?: string;
  primary_address_state?: string;
  primary_address_postalcode?: string;
  primary_address_country?: string;
  alt_address_street?: string;
  alt_address_city?: string;
  alt_address_state?: string;
  alt_address_postalcode?: string;
  alt_address_country?: string;
  assistant?: string;
  assistant_phone?: string;
  converted?: number;
  refered_by?: string;
  lead_source?: string;
  lead_source_description?: string;
  status?: string;
  status_description?: string;
  reports_to_id?: string;
  account_name?: string;
  account_description?: string;
  contact_id?: string;
  account_id?: string;
  opportunity_id?: string;
  opportunity_name?: string;
  opportunity_amount?: string;
  campaign_id?: string;
  website?: string;
}

// Account module fields
export interface SuiteCRMAccount extends SuiteCRMBaseFields {
  account_type?: string;
  industry?: string;
  annual_revenue?: string;
  phone_fax?: string;
  billing_address_street?: string;
  billing_address_city?: string;
  billing_address_state?: string;
  billing_address_postalcode?: string;
  billing_address_country?: string;
  rating?: string;
  phone_office?: string;
  phone_alternate?: string;
  website?: string;
  ownership?: string;
  employees?: string;
  ticker_symbol?: string;
  shipping_address_street?: string;
  shipping_address_city?: string;
  shipping_address_state?: string;
  shipping_address_postalcode?: string;
  shipping_address_country?: string;
  parent_id?: string;
  sic_code?: string;
  campaign_id?: string;
}

// Opportunity module fields
export interface SuiteCRMOpportunity extends SuiteCRMBaseFields {
  opportunity_type?: string;
  account_name?: string;
  account_id?: string;
  campaign_id?: string;
  lead_source?: string;
  amount?: string;
  amount_usdollar?: string;
  currency_id?: string;
  date_closed?: string;
  next_step?: string;
  sales_stage?: string;
  probability?: string;
}

// Case module fields
export interface SuiteCRMCase extends SuiteCRMBaseFields {
  case_number?: string;
  type?: string;
  status?: string;
  priority?: string;
  resolution?: string;
  work_log?: string;
  account_id?: string;
  account_name?: string;
  contact_id?: string;
}

// Task module fields
export interface SuiteCRMTask extends SuiteCRMBaseFields {
  status?: string;
  date_start?: string;
  date_due?: string;
  time_due?: string;
  parent_type?: string;
  parent_id?: string;
  contact_id?: string;
  priority?: string;
}

// Meeting module fields
export interface SuiteCRMMeeting extends SuiteCRMBaseFields {
  location?: string;
  duration_hours?: number;
  duration_minutes?: number;
  date_start?: string;
  date_end?: string;
  parent_type?: string;
  parent_id?: string;
  status?: string;
  type?: string;
  direction?: string;
  reminder_time?: number;
  email_reminder_time?: number;
  email_reminder_sent?: number;
  outlook_id?: string;
  sequence?: number;
}

// Call module fields
export interface SuiteCRMCall extends SuiteCRMBaseFields {
  duration_hours?: number;
  duration_minutes?: number;
  date_start?: string;
  date_end?: string;
  parent_type?: string;
  parent_id?: string;
  status?: string;
  direction?: string;
  reminder_time?: number;
  email_reminder_time?: number;
  email_reminder_sent?: number;
  outlook_id?: string;
}

// Note module fields
export interface SuiteCRMNote extends SuiteCRMBaseFields {
  file_mime_type?: string;
  filename?: string;
  parent_type?: string;
  parent_id?: string;
  contact_id?: string;
  portal_flag?: number;
  embed_flag?: number;
}

// Email module fields
export interface SuiteCRMEmail extends SuiteCRMBaseFields {
  date_sent?: string;
  message_id?: string;
  message_uid?: string;
  intent?: string;
  mailbox_id?: string;
  from_addr?: string;
  from_name?: string;
  to_addrs?: string;
  cc_addrs?: string;
  bcc_addrs?: string;
  reply_to_addr?: string;
  parent_type?: string;
  parent_id?: string;
  status?: string;
  type?: string;
  flagged?: number;
  reply_to_status?: number;
}

// Quote module fields
export interface SuiteCRMQuote extends SuiteCRMBaseFields {
  quote_num?: string;
  quote_stage?: string;
  purchase_order_num?: string;
  quote_date?: string;
  calc_grand_total?: string;
  billing_account_id?: string;
  billing_contact_id?: string;
  billing_address_street?: string;
  billing_address_city?: string;
  billing_address_state?: string;
  billing_address_postalcode?: string;
  billing_address_country?: string;
  shipping_account_id?: string;
  shipping_contact_id?: string;
  shipping_address_street?: string;
  shipping_address_city?: string;
  shipping_address_state?: string;
  shipping_address_postalcode?: string;
  shipping_address_country?: string;
  expiration?: string;
  opportunity_id?: string;
}

// Union type for all SuiteCRM modules
export type SuiteCRMRecord = 
  | SuiteCRMContact 
  | SuiteCRMLead 
  | SuiteCRMAccount 
  | SuiteCRMOpportunity 
  | SuiteCRMCase 
  | SuiteCRMTask 
  | SuiteCRMMeeting 
  | SuiteCRMCall 
  | SuiteCRMNote 
  | SuiteCRMEmail 
  | SuiteCRMQuote;

// Module name mapping
export const SUITECRM_MODULES = {
  CONTACTS: 'Contacts',
  LEADS: 'Leads',
  ACCOUNTS: 'Accounts',
  OPPORTUNITIES: 'Opportunities',
  CASES: 'Cases',
  TASKS: 'Tasks',
  MEETINGS: 'Meetings',
  CALLS: 'Calls',
  NOTES: 'Notes',
  EMAILS: 'Emails',
  QUOTES: 'Quotes'
} as const;

export type SuiteCRMModuleName = typeof SUITECRM_MODULES[keyof typeof SUITECRM_MODULES];