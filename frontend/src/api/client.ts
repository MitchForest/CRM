/**
 * API Client Export
 * 
 * This exports the API client from lib/api-client.ts for backward compatibility
 */

export { apiClient } from '@/lib/api-client';

// Re-export types from database types
export type {
  LeadDB,
  ContactDB,
  OpportunityDB,
  CaseDB,
  AccountDB,
  TaskDB,
  CallDB,
  MeetingDB,
  NoteDB
} from '@/types/database.types';