/**
 * API Client Wrapper
 * 
 * This wraps the generated API client with our authentication logic
 */

import { Configuration } from './generated';
import * as APIs from './generated/api';
import { getStoredAuth } from '@/stores/auth-store';

// Create configuration with auth
function createConfiguration(): Configuration {
  const auth = getStoredAuth();
  
  return new Configuration({
    basePath: '/api',
    accessToken: auth?.accessToken,
    headers: {
      'Content-Type': 'application/json',
    }
  });
}

// Export wrapped API instances
export const leadsApi = new APIs.LeadsApi(createConfiguration());
export const contactsApi = new APIs.ContactsApi(createConfiguration());
export const opportunitiesApi = new APIs.OpportunitiesApi(createConfiguration());
export const casesApi = new APIs.CasesApi(createConfiguration());
export const activitiesApi = new APIs.ActivitiesApi(createConfiguration());
export const authApi = new APIs.AuthenticationApi(createConfiguration());
export const dashboardApi = new APIs.DashboardApi(createConfiguration());

// Re-export types
export * from './generated/models';
