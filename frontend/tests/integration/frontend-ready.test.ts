/**
 * Frontend Readiness Test
 * Verifies the frontend is production-ready regardless of backend state
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { apiClient } from '@/lib/api-client';
import type { LeadDB, LeadCreateRequest } from '@/types/database.types';

// Mock the fetch globally
global.fetch = vi.fn();

describe('Frontend Production Readiness', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('API Client Structure', () => {
    it('should have all required API methods', () => {
      // Verify API client has all methods
      expect(apiClient).toHaveProperty('getLeads');
      expect(apiClient).toHaveProperty('getLead');
      expect(apiClient).toHaveProperty('createLead');
      expect(apiClient).toHaveProperty('updateLead');
      expect(apiClient).toHaveProperty('deleteLead');

      expect(apiClient).toHaveProperty('getContacts');
      expect(apiClient).toHaveProperty('getContact');
      expect(apiClient).toHaveProperty('createContact');
      expect(apiClient).toHaveProperty('updateContact');

      expect(apiClient).toHaveProperty('getDashboardMetrics');
      expect(apiClient).toHaveProperty('getActivityMetrics');
    });
  });

  describe('API Client Requests', () => {
    it('should make correct GET request for leads', async () => {
      const mockResponse = {
        data: [],
        pagination: { page: 1, limit: 20, total: 0, totalPages: 0 }
      };

      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const result = await apiClient.getLeads({ page: 1, limit: 20 });

      expect(fetch).toHaveBeenCalledWith(
        '/api/crm/leads?page=1&limit=20',
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json'
          })
        })
      );

      expect(result).toEqual(mockResponse);
    });

    it('should make correct POST request for creating lead', async () => {
      const newLead: LeadCreateRequest = {
        first_name: 'Test',
        last_name: 'User',
        email1: 'test@example.com',
        status: 'new',
        created_by: null,
        modified_user_id: null,
        assigned_user_id: null,
        salutation: null,
        title: null,
        department: null,
        phone_work: null,
        phone_mobile: null,
        primary_address_street: null,
        primary_address_city: null,
        primary_address_state: null,
        primary_address_postalcode: null,
        primary_address_country: null,
        status_description: null,
        lead_source: null,
        lead_source_description: null,
        description: null,
        account_name: null,
        website: null,
        ai_score: null,
        ai_score_date: null,
        ai_insights: null,
        ai_next_best_action: null
      };

      const mockResponse = {
        data: { ...newLead, id: '123' }
      };

      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const result = await apiClient.createLead(newLead);

      expect(fetch).toHaveBeenCalledWith(
        '/api/crm/leads',
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Content-Type': 'application/json'
          }),
          body: JSON.stringify(newLead)
        })
      );

      expect(result).toEqual(mockResponse);
    });

    it('should handle auth token in requests', async () => {
      // Mock auth store
      vi.mock('@/stores/auth-store', () => ({
        getStoredAuth: () => ({
          accessToken: 'test-token-123'
        })
      }));

      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: [] })
      });

      await apiClient.getLeads();

      expect(fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Authorization': 'Bearer test-token-123'
          })
        })
      );
    });

    it('should handle API errors correctly', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: async () => ({ message: 'Unauthorized' })
      });

      await expect(apiClient.getLeads()).rejects.toThrow('Unauthorized');
    });
  });

  describe('Type Safety', () => {
    it('should enforce snake_case field names', () => {
      const lead: LeadDB = {
        id: '123',
        first_name: 'John',
        last_name: 'Doe',
        email1: 'john@example.com',
        phone_work: '555-1234',
        date_entered: '2024-01-01',
        date_modified: '2024-01-01',
        // All other fields are nullable
        created_by: null,
        modified_user_id: null,
        assigned_user_id: null,
        deleted: null,
        salutation: null,
        title: null,
        department: null,
        phone_mobile: null,
        primary_address_street: null,
        primary_address_city: null,
        primary_address_state: null,
        primary_address_postalcode: null,
        primary_address_country: null,
        status: null,
        status_description: null,
        lead_source: null,
        lead_source_description: null,
        description: null,
        account_name: null,
        website: null,
        ai_score: null,
        ai_score_date: null,
        ai_insights: null,
        ai_next_best_action: null
      };

      // TypeScript will error if we try to use camelCase
      // @ts-expect-error - Testing that camelCase fields don't exist
      lead.firstName = 'Jane';
      // @ts-expect-error - Testing that camelCase fields don't exist
      lead.dateEntered = '2024-01-01';

      expect(lead.first_name).toBe('John');
    });
  });

  describe('Response Format', () => {
    it('should handle list responses correctly', async () => {
      const mockLeads: LeadDB[] = [
        {
          id: '1',
          first_name: 'Lead',
          last_name: 'One',
          email1: 'lead1@example.com',
          date_entered: '2024-01-01',
          date_modified: '2024-01-01',
          // ... other fields null
        } as LeadDB
      ];

      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: mockLeads,
          pagination: {
            page: 1,
            limit: 20,
            total: 1,
            totalPages: 1
          }
        })
      });

      const response = await apiClient.getLeads();

      expect(response.data).toHaveLength(1);
      expect(response.data[0]).toHaveProperty('first_name', 'Lead');
      expect(response.pagination).toHaveProperty('page', 1);
      expect(response.pagination).toHaveProperty('limit', 20);
    });

    it('should handle single item responses', async () => {
      const mockLead: LeadDB = {
        id: '123',
        first_name: 'John',
        last_name: 'Doe',
        email1: 'john@example.com',
        date_entered: '2024-01-01',
        date_modified: '2024-01-01'
      } as LeadDB;

      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: mockLead
        })
      });

      const response = await apiClient.getLead('123');

      expect(response.data).toHaveProperty('id', '123');
      expect(response.data).toHaveProperty('first_name', 'John');
    });
  });
});

// Run this to check if frontend is ready
export async function checkFrontendReadiness() {
  const checks = {
    typescript: false,
    apiClient: false,
    types: false,
    build: false
  };

  // This function would be run separately, not as part of the test suite

  try {
    // Check if API client exists
    await import('@/lib/api-client');
    checks.apiClient = true;
  } catch (e) {
    console.error('❌ API client not found');
  }

  try {
    // Check if types exist
    await import('@/types/database.generated');
    checks.types = true;
  } catch (e) {
    console.error('❌ Database types not generated');
  }

  // Build check would be run separately

  const ready = Object.values(checks).every(v => v);
  
  console.log('\n📊 Frontend Readiness Check:');
  console.log(`TypeScript: ${checks.typescript ? '✅' : '❌'}`);
  console.log(`API Client: ${checks.apiClient ? '✅' : '❌'}`);
  console.log(`Types Generated: ${checks.types ? '✅' : '❌'}`);
  console.log(`Build Works: ${checks.build ? '✅' : '❌'}`);
  console.log(`\n${ready ? '✅ Frontend is READY for production!' : '❌ Frontend needs fixes'}`);
  
  return ready;
}