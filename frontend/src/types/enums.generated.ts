// Generated enum constants from database

export const LEADS_STATUS = {
  NEW: 'new',
  CONTACTED: 'contacted',
  QUALIFIED: 'qualified',
  CONVERTED: 'converted',
  DEAD: 'dead',
} as const;

export type LEADS_STATUS_TYPE = typeof LEADS_STATUS[keyof typeof LEADS_STATUS];

export const LEADS_LEAD_SOURCE = {
  WEBSITE: 'website',
  REFERRAL: 'referral',
  COLD_CALL: 'cold_call',
  CONFERENCE: 'conference',
  ADVERTISEMENT: 'advertisement',
} as const;

export type LEADS_LEAD_SOURCE_TYPE = typeof LEADS_LEAD_SOURCE[keyof typeof LEADS_LEAD_SOURCE];

export const LEADS_SALUTATION = {
  MR: 'Mr.',
  MS: 'Ms.',
  MRS: 'Mrs.',
  DR: 'Dr.',
  PROF: 'Prof.',
} as const;

export type LEADS_SALUTATION_TYPE = typeof LEADS_SALUTATION[keyof typeof LEADS_SALUTATION];

export const CONTACTS_SALUTATION = {
  MR: 'Mr.',
  MS: 'Ms.',
  MRS: 'Mrs.',
  DR: 'Dr.',
  PROF: 'Prof.',
} as const;

export type CONTACTS_SALUTATION_TYPE = typeof CONTACTS_SALUTATION[keyof typeof CONTACTS_SALUTATION];

export const CONTACTS_LEAD_SOURCE = {
  WEBSITE: 'website',
  REFERRAL: 'referral',
  CONVERSION: 'conversion',
  OTHER: 'other',
} as const;

export type CONTACTS_LEAD_SOURCE_TYPE = typeof CONTACTS_LEAD_SOURCE[keyof typeof CONTACTS_LEAD_SOURCE];

export const OPPORTUNITIES_SALES_STAGE = {
  PROSPECTING: 'prospecting',
  QUALIFICATION: 'qualification',
  NEEDS_ANALYSIS: 'needs_analysis',
  VALUE_PROPOSITION: 'value_proposition',
  DECISION_MAKERS: 'decision_makers',
  PERCEPTION_ANALYSIS: 'perception_analysis',
  PROPOSAL: 'proposal',
  NEGOTIATION: 'negotiation',
  CLOSED_WON: 'closed_won',
  CLOSED_LOST: 'closed_lost',
} as const;

export type OPPORTUNITIES_SALES_STAGE_TYPE = typeof OPPORTUNITIES_SALES_STAGE[keyof typeof OPPORTUNITIES_SALES_STAGE];

export const OPPORTUNITIES_OPPORTUNITY_TYPE = {
  NEW_BUSINESS: 'new_business',
  EXISTING_BUSINESS: 'existing_business',
  RENEWAL: 'renewal',
} as const;

export type OPPORTUNITIES_OPPORTUNITY_TYPE_TYPE = typeof OPPORTUNITIES_OPPORTUNITY_TYPE[keyof typeof OPPORTUNITIES_OPPORTUNITY_TYPE];

export const CASES_STATUS = {
  NEW: 'new',
  ASSIGNED: 'assigned',
  PENDING: 'pending',
  RESOLVED: 'resolved',
  CLOSED: 'closed',
} as const;

export type CASES_STATUS_TYPE = typeof CASES_STATUS[keyof typeof CASES_STATUS];

export const CASES_PRIORITY = {
  P1: 'P1',
  P2: 'P2',
  P3: 'P3',
} as const;

export type CASES_PRIORITY_TYPE = typeof CASES_PRIORITY[keyof typeof CASES_PRIORITY];

export const CASES_TYPE = {
  BUG: 'bug',
  FEATURE_REQUEST: 'feature_request',
  QUESTION: 'question',
  OTHER: 'other',
} as const;

export type CASES_TYPE_TYPE = typeof CASES_TYPE[keyof typeof CASES_TYPE];

export const USERS_STATUS = {
  ACTIVE: 'active',
  INACTIVE: 'inactive',
} as const;

export type USERS_STATUS_TYPE = typeof USERS_STATUS[keyof typeof USERS_STATUS];

