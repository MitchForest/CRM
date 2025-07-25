// Export SuiteCRM types (snake_case) - these are mostly for internal use
export * from './suitecrm.types';

// Export API schemas with aliases to avoid conflicts
export * as schemas from './api.schemas';

// Export generated API types - these are the primary types to use
export * from './api.generated';

// Re-export frontend types with aliases to avoid conflicts
export type {
  BaseEntity as FrontendBaseEntity,
  CRMEntity as FrontendCRMEntity,
  PaginatedResponse as FrontendPaginatedResponse,
  ApiError as FrontendApiError,
  Contact as FrontendContact,
  Lead as FrontendLead,
  Account as FrontendAccount,
  Opportunity as FrontendOpportunity,
  Case as FrontendCase,
  Task as FrontendTask,
  Meeting as FrontendMeeting,
  Call as FrontendCall,
  Note as FrontendNote,
  Email as FrontendEmail,
  Quote as FrontendQuote,
  QueryParams as FrontendQueryParams,
  Filter as FrontendFilter,
  FilterOperator as FrontendFilterOperator
} from './frontend.types';

export type {
  SuiteCRMContact,
  SuiteCRMLead,
  SuiteCRMAccount,
  SuiteCRMOpportunity,
  SuiteCRMCase,
  SuiteCRMTask,
  SuiteCRMMeeting,
  SuiteCRMCall,
  SuiteCRMNote,
  SuiteCRMEmail,
  SuiteCRMQuote,
  SuiteCRMRecord,
  SuiteCRMModuleName,
  SUITECRM_MODULES
} from './suitecrm.types';

// Export Phase 2 specific types
export * from './phase2.types';

// Export Phase 3 specific types
export * from './phase3.types';