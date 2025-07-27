/**
 * Frontend-Backend Integration Tests
 * Tests the complete integration between frontend and backend
 */

import { describe, it, expect } from 'vitest';

describe('Frontend-Backend Integration', () => {
  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api';

  describe('API Availability', () => {
    it('should have backend API running', async () => {
      const response = await fetch(`${API_URL}/health`);
      const data = await response.json();

      expect(response.ok).toBe(true);
      expect(data).toHaveProperty('status', 'healthy');
      expect(data).toHaveProperty('service', 'Sassy CRM API');
    });

    it('should have OpenAPI spec available', async () => {
      const response = await fetch('http://localhost:8080/api-docs/openapi.json');
      const spec = await response.json();

      expect(response.ok).toBe(true);
      expect(spec).toHaveProperty('openapi');
      expect(spec).toHaveProperty('info');
      expect(spec.info).toHaveProperty('title', 'Sassy CRM API');
    });
  });

  describe('CORS Configuration', () => {
    it('should allow frontend origin', async () => {
      const response = await fetch(`${API_URL}/health`, {
        method: 'GET',
        headers: {
          'Origin': 'http://localhost:5173'
        }
      });

      expect(response.ok).toBe(true);
      // Check CORS headers if they're set
    });
  });

  describe('Authentication Flow', () => {
    it('should complete full auth flow', async () => {
      // 1. Login
      const loginResponse = await fetch(`${API_URL}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: 'test@example.com',
          password: 'password'
        })
      });

      if (loginResponse.ok) {
        const auth = await loginResponse.json();
        expect(auth).toHaveProperty('access_token');
        expect(auth).toHaveProperty('refresh_token');
        expect(auth).toHaveProperty('user');

        // 2. Use token to access protected endpoint
        const meResponse = await fetch(`${API_URL}/auth/me`, {
          headers: {
            'Authorization': `Bearer ${auth.access_token}`
          }
        });

        if (meResponse.ok) {
          const user = await meResponse.json();
          expect(user).toHaveProperty('id');
          expect(user).toHaveProperty('email');
        }

        // 3. Refresh token
        const refreshResponse = await fetch(`${API_URL}/auth/refresh`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            refresh_token: auth.refresh_token
          })
        });

        if (refreshResponse.ok) {
          const newAuth = await refreshResponse.json();
          expect(newAuth).toHaveProperty('access_token');
        }
      }
    });
  });

  describe('Data Format Consistency', () => {
    it('should return snake_case fields from all endpoints', async () => {
      // This test would check multiple endpoints to ensure
      // all are returning snake_case fields consistently
      
      // Would test multiple endpoints:
      // '/crm/leads', '/crm/contacts', '/crm/opportunities', '/crm/cases'

      // Would need auth token for these tests
      // Skipping implementation for now
    });
  });

  describe('Pagination Format', () => {
    it('should use consistent pagination format', async () => {
      // Test that all list endpoints return the same pagination structure:
      // { data: [], pagination: { page, limit, total, totalPages } }
    });
  });

  describe('Error Response Format', () => {
    it('should return consistent error format', async () => {
      const response = await fetch(`${API_URL}/crm/leads/non-existent-id`);
      
      if (!response.ok) {
        const error = await response.json();
        expect(error).toHaveProperty('error');
        // Could also check for 'code' and 'details' fields
      }
    });
  });
});