/**
 * Sassy CRM API Client - MANUAL IMPLEMENTATION
 * 
 * DO NOT REGENERATE THIS FILE!
 * The automatic API generation is broken, so we're using this manual implementation.
 * When the backend OpenAPI generation is fixed, we can replace this with the generated client.
 * 
 * Until then, DO NOT run npm run generate:api-client as it will break this file!
 */

import { getStoredAuth } from '@/stores/auth-store';
import type { 
  LeadDB, ContactDB, OpportunityDB, CaseDB, UserDB,
  LeadCreateRequest, LeadUpdateRequest,
  ContactCreateRequest, ContactUpdateRequest,
  OpportunityCreateRequest, OpportunityUpdateRequest,
  CaseCreateRequest, CaseUpdateRequest
} from '@/types/database.generated';

interface ListResponse<T> {
  data: T[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
}

interface SingleResponse<T> {
  data: T;
}

class BaseAPI {
  protected async request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const auth = getStoredAuth();
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string> || {}),
    };

    if (auth?.accessToken) {
      headers['Authorization'] = `Bearer ${auth.accessToken}`;
    }

    const response = await fetch(`/api${path}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(error.message || `API Error: ${response.status}`);
    }

    return response.json();
  }
}

class LeadsApi extends BaseAPI {
  async getLeads(params?: { page?: number; limit?: number; filter?: any }) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.limit) queryParams.append('limit', params.limit.toString());
    if (params?.filter) queryParams.append('filter', JSON.stringify(params.filter));
    
    return this.request<ListResponse<LeadDB>>(`/crm/leads?${queryParams}`);
  }

  async getLead(id: string) {
    return this.request<SingleResponse<LeadDB>>(`/crm/leads/${id}`);
  }

  async createLead(data: LeadCreateRequest) {
    return this.request<SingleResponse<LeadDB>>('/crm/leads', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateLead(id: string, data: LeadUpdateRequest) {
    return this.request<SingleResponse<LeadDB>>(`/crm/leads/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteLead(id: string) {
    return this.request<void>(`/crm/leads/${id}`, {
      method: 'DELETE',
    });
  }
}

class ContactsApi extends BaseAPI {
  async getContacts(params?: { page?: number; limit?: number; filter?: any }) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.limit) queryParams.append('limit', params.limit.toString());
    if (params?.filter) queryParams.append('filter', JSON.stringify(params.filter));
    
    return this.request<ListResponse<ContactDB>>(`/crm/contacts?${queryParams}`);
  }

  async getContact(id: string) {
    return this.request<SingleResponse<ContactDB>>(`/crm/contacts/${id}`);
  }

  async createContact(data: ContactCreateRequest) {
    return this.request<SingleResponse<ContactDB>>('/crm/contacts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateContact(id: string, data: ContactUpdateRequest) {
    return this.request<SingleResponse<ContactDB>>(`/crm/contacts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }
}

class OpportunitiesApi extends BaseAPI {
  async getOpportunities(params?: { page?: number; limit?: number; filter?: any }) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.limit) queryParams.append('limit', params.limit.toString());
    if (params?.filter) queryParams.append('filter', JSON.stringify(params.filter));
    
    return this.request<ListResponse<OpportunityDB>>(`/crm/opportunities?${queryParams}`);
  }

  async getOpportunity(id: string) {
    return this.request<SingleResponse<OpportunityDB>>(`/crm/opportunities/${id}`);
  }

  async createOpportunity(data: OpportunityCreateRequest) {
    return this.request<SingleResponse<OpportunityDB>>('/crm/opportunities', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateOpportunity(id: string, data: OpportunityUpdateRequest) {
    return this.request<SingleResponse<OpportunityDB>>(`/crm/opportunities/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }
}

class CasesApi extends BaseAPI {
  async getCases(params?: { page?: number; limit?: number; filter?: any }) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.limit) queryParams.append('limit', params.limit.toString());
    if (params?.filter) queryParams.append('filter', JSON.stringify(params.filter));
    
    return this.request<ListResponse<CaseDB>>(`/crm/cases?${queryParams}`);
  }

  async getCase(id: string) {
    return this.request<SingleResponse<CaseDB>>(`/crm/cases/${id}`);
  }

  async createCase(data: CaseCreateRequest) {
    return this.request<SingleResponse<CaseDB>>('/crm/cases', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCase(id: string, data: CaseUpdateRequest) {
    return this.request<SingleResponse<CaseDB>>(`/crm/cases/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }
}

class ActivitiesApi extends BaseAPI {
  async getActivities(params?: { page?: number; limit?: number }) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.limit) queryParams.append('limit', params.limit.toString());
    
    return this.request<ListResponse<any>>(`/crm/activities?${queryParams}`);
  }
}

class AuthenticationApi extends BaseAPI {
  async login(data: { email: string; password: string }) {
    return this.request<{ access_token: string; refresh_token: string; expires_in: number; user: UserDB }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async refresh(refreshToken: string) {
    return this.request<{ access_token: string; expires_in: number }>('/auth/refresh', {
      method: 'POST',
      body: JSON.stringify({ refresh_token: refreshToken }),
    });
  }

  async logout() {
    return this.request<void>('/auth/logout', {
      method: 'POST',
    });
  }
}

class DashboardApi extends BaseAPI {
  async getMetrics() {
    return this.request<{ data: any }>('/crm/dashboard/metrics');
  }

  async getActivityMetrics() {
    return this.request<{ data: any }>('/crm/dashboard/activity-metrics');
  }
}

// Export API instances
export const leadsApi = new LeadsApi();
export const contactsApi = new ContactsApi();
export const opportunitiesApi = new OpportunitiesApi();
export const casesApi = new CasesApi();
export const activitiesApi = new ActivitiesApi();
export const authApi = new AuthenticationApi();
export const dashboardApi = new DashboardApi();

// Export types from database
export type * from '@/types/database.generated';