/**
 * API Client Integration Tests
 * Tests the manual API client implementation with the backend
 */

import { describe, it, expect } from 'vitest';
import { apiClient } from '@/lib/api-client';
import type { LeadCreateRequest } from '@/types/database.types';

describe('API Client Integration', () => {

  describe('Authentication', () => {
    it('should handle login with valid credentials', async () => {
      // This will need real test credentials
      const response = await apiClient.login('test@example.com', 'test123');

      expect(response.success).toBe(true);
      if (response.success) {
        expect(response.data).toHaveProperty('accessToken');
        expect(response.data).toHaveProperty('user');
      }
      // Token stored in response
    });

    it('should handle login failure with invalid credentials', async () => {
      const response = await apiClient.login('invalid@example.com', 'wrong');
      expect(response.success).toBe(false);
      expect(response.error).toBeDefined();
    });
  });

  describe('Leads API', () => {
    it('should fetch leads with pagination', async () => {
      const response = await apiClient.getLeads({
        page: 1,
        limit: 10
      });

      expect(response).toHaveProperty('data');
      expect(response).toHaveProperty('pagination');
      expect(response.pagination).toHaveProperty('page', 1);
      expect(response.pagination).toHaveProperty('limit', 10);
      expect(Array.isArray(response.data)).toBe(true);
    });

    it('should create a new lead', async () => {
      const newLead: LeadCreateRequest = {
        first_name: 'Test',
        last_name: 'Lead',
        email1: 'testlead@example.com',
        phone_work: '555-0123',
        status: 'new',
        lead_source: 'website',
        created_by: null,
        modified_user_id: null,
        assigned_user_id: null,
        salutation: null,
        title: null,
        department: null,
        phone_mobile: null,
        primary_address_street: null,
        primary_address_city: null,
        primary_address_state: null,
        primary_address_postalcode: null,
        primary_address_country: null,
        status_description: null,
        lead_source_description: null,
        description: null,
        account_name: null,
        website: null,
        ai_score: null,
        ai_score_date: null,
        ai_insights: null,
        ai_next_best_action: null
      };

      const response = await apiClient.createLead(newLead);
      expect(response.success).toBe(true);
      if (response.success) {
        expect(response.data).toHaveProperty('id');
      }
    });

    it('should fetch a single lead by ID', async () => {
      const leadsResponse = await apiClient.getLeads({ limit: 1 });
      if (leadsResponse.data.length > 0) {
        const leadId = leadsResponse.data[0]!.id;
        const response = await apiClient.getLead(leadId);
        
        expect(response.success).toBe(true);
        if (response.success) {
          expect(response.data).toHaveProperty('id', leadId);
        }
      }
    });
  });

  describe('Dashboard API', () => {
    it('should fetch dashboard metrics', async () => {
      const response = await apiClient.getDashboardMetrics();
      
      expect(response.success).toBe(true);
      if (response.success) {
        expect(response.data).toHaveProperty('totalLeads');
        expect(response.data).toHaveProperty('newLeads');
        expect(response.data).toHaveProperty('conversionRate');
      }
    });

    it('should fetch activity metrics', async () => {
      const response = await apiClient.getActivityMetrics();
      
      expect(response.success).toBe(true);
      // Add specific assertions based on expected activity metrics structure
    });
  });

  describe('Field Naming Convention', () => {
    it('should use snake_case for all database fields', async () => {
      const response = await apiClient.getLeads({ limit: 1 });
      
      if (response.data.length > 0) {
        const lead = response.data[0];
        
        // Check that snake_case fields exist
        expect(lead).toHaveProperty('first_name');
        expect(lead).toHaveProperty('last_name');
        expect(lead).toHaveProperty('email1');
        expect(lead).toHaveProperty('phone_work');
        expect(lead).toHaveProperty('date_entered');
        expect(lead).toHaveProperty('date_modified');
        
        // Ensure camelCase fields don't exist
        expect(lead).not.toHaveProperty('firstName');
        expect(lead).not.toHaveProperty('lastName');
        expect(lead).not.toHaveProperty('dateEntered');
        expect(lead).not.toHaveProperty('dateModified');
      }
    });
  });

  describe('Error Handling', () => {
    it('should handle 404 errors gracefully', async () => {
      const response = await apiClient.getLead('non-existent-id');
      expect(response.success).toBe(false);
      expect(response.error).toBeDefined();
    });

    it('should handle network errors', async () => {
      // This would test with a mocked failed network request
      // For now, we'll skip implementation
    });
  });
});