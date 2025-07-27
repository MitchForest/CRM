/**
 * Test Helper Utilities
 */

import { vi } from 'vitest';
import type { LeadDB, ContactDB } from '@/types/database.generated';

/**
 * Create mock lead data with snake_case fields
 */
export function createMockLead(overrides?: Partial<LeadDB>): LeadDB {
  return {
    id: '123e4567-e89b-12d3-a456-426614174000',
    date_entered: '2024-01-01T00:00:00Z',
    date_modified: '2024-01-01T00:00:00Z',
    created_by: 'user-123',
    modified_user_id: 'user-123',
    assigned_user_id: 'user-456',
    deleted: 0,
    salutation: 'Mr.',
    first_name: 'John',
    last_name: 'Doe',
    title: 'CEO',
    department: 'Executive',
    phone_work: '555-1234',
    phone_mobile: '555-5678',
    email1: 'john.doe@example.com',
    primary_address_street: '123 Main St',
    primary_address_city: 'San Francisco',
    primary_address_state: 'CA',
    primary_address_postalcode: '94105',
    primary_address_country: 'USA',
    status: 'new',
    status_description: null,
    lead_source: 'Website',
    lead_source_description: null,
    description: 'Interested in our product',
    account_name: 'Acme Corp',
    website: 'https://example.com',
    ai_score: 0.85,
    ai_score_date: '2024-01-01T00:00:00Z',
    ai_insights: { intent: 'high', budget: 'enterprise' },
    ai_next_best_action: 'Schedule demo',
    ...overrides
  };
}

/**
 * Create mock contact data with snake_case fields
 */
export function createMockContact(overrides?: Partial<ContactDB>): ContactDB {
  return {
    id: '456e7890-e89b-12d3-a456-426614174000',
    date_entered: '2024-01-01T00:00:00Z',
    date_modified: '2024-01-01T00:00:00Z',
    created_by: 'user-123',
    modified_user_id: 'user-123',
    assigned_user_id: 'user-456',
    deleted: 0,
    salutation: 'Ms.',
    first_name: 'Jane',
    last_name: 'Smith',
    title: 'VP Sales',
    department: 'Sales',
    phone_work: '555-9999',
    phone_mobile: '555-8888',
    email1: 'jane.smith@example.com',
    primary_address_street: '456 Oak Ave',
    primary_address_city: 'New York',
    primary_address_state: 'NY',
    primary_address_postalcode: '10001',
    primary_address_country: 'USA',
    description: 'Key decision maker',
    lead_source: 'Referral',
    account_id: null,
    lifetime_value: 50000,
    engagement_score: 85,
    last_activity_date: '2024-01-01T00:00:00Z',
    ...overrides
  };
}

/**
 * Create mock API response with pagination
 */
export function createMockListResponse<T>(items: T[], page = 1, limit = 20) {
  return {
    data: items,
    pagination: {
      page,
      limit,
      total: items.length,
      totalPages: Math.ceil(items.length / limit)
    }
  };
}

/**
 * Create mock API error response
 */
export function createMockErrorResponse(message: string, code?: string) {
  return {
    error: message,
    code,
    details: {}
  };
}

/**
 * Mock fetch for testing
 */
export function mockFetch(responses: Record<string, any>) {
  global.fetch = vi.fn((url: string | URL | Request) => {
    const urlStr = typeof url === 'string' ? url : url.toString();
    
    for (const [pattern, response] of Object.entries(responses)) {
      if (urlStr.includes(pattern)) {
        return Promise.resolve({
          ok: response.ok ?? true,
          status: response.status ?? 200,
          json: () => Promise.resolve(response.data),
          headers: new Headers(response.headers || {})
        } as Response);
      }
    }
    
    return Promise.resolve({
      ok: false,
      status: 404,
      json: () => Promise.resolve({ error: 'Not found' })
    } as Response);
  });
}

/**
 * Wait for async operations
 */
export async function waitFor(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Get test auth token
 */
export function getTestAuthToken(): string {
  return 'test-jwt-token-123456789';
}

/**
 * Setup mock auth
 */
export function setupMockAuth() {
  const mockAuth = {
    accessToken: getTestAuthToken(),
    refreshToken: 'test-refresh-token',
    user: {
      id: 'user-123',
      email: 'test@example.com',
      first_name: 'Test',
      last_name: 'User'
    }
  };

  // Mock the auth store
  vi.mock('@/stores/auth-store', () => ({
    getStoredAuth: () => mockAuth,
    setStoredAuth: vi.fn(),
    clearStoredAuth: vi.fn()
  }));

  return mockAuth;
}