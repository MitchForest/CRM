/**
 * API Client Wrapper
 * 
 * This exports the manual API client until the generated one is available
 */

import { apiClient } from '@/lib/api-client';

// Export the manual API client instance
export default apiClient;

// Export API methods as named exports for compatibility
export const leadsApi = apiClient;
export const contactsApi = apiClient;
export const opportunitiesApi = apiClient;
export const casesApi = apiClient;
export const activitiesApi = apiClient;
export const authApi = apiClient;
export const dashboardApi = apiClient;