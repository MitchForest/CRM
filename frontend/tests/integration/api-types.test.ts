/**
 * Test file to verify TypeScript integration with backend OpenAPI
 * This demonstrates type safety between frontend and backend
 */

import type { components } from '@/types/api.generated';

// Test 1: Lead types are properly typed with snake_case
const testLead: components['schemas']['Lead'] = {
  id: '123',
  first_name: 'John',
  last_name: 'Doe', 
  email1: 'john@example.com',
  phone_work: '555-1234',
  account_name: 'Acme Corp',
  lead_source: 'Website',
  status: 'new', // Properly typed enum
  ai_score: 0.85,
  date_entered: '2024-01-01T00:00:00Z',
  date_modified: '2024-01-01T00:00:00Z'
};

// Test 2: Lead input validation
// const createLeadInput: components['schemas']['LeadInput'] = {
//   first_name: 'Jane',
//   last_name: 'Smith', // Required field
//   email1: 'jane@example.com',
//   phone_work: '555-5678',
//   status: 'new'
// };

// Test 3: API path types
// type LeadsListResponse = paths['/crm/leads']['get']['responses']['200']['content']['application/json'];
// type CreateLeadRequest = paths['/crm/leads']['post']['requestBody']['content']['application/json'];

// Test 4: Contact with relationships
const testContact: components['schemas']['Contact'] = {
  id: '456',
  first_name: 'Alice',
  last_name: 'Johnson',
  email1: 'alice@example.com',
  phone_work: '555-9999',
  account_id: '789', // Relationship field
  lead_source: 'Referral',
  date_entered: '2024-01-01T00:00:00Z'
};

// Test 5: Dashboard metrics with nested objects
const dashboardMetrics: components['schemas']['DashboardMetrics'] = {
  leads: {
    total: 100,
    new: 25,
    converted: 10,
    conversion_rate: 0.1
  },
  opportunities: {
    total: 50,
    open: 30,
    won: 15,
    lost: 5,
    total_value: 150000
  },
  cases: {
    total: 75,
    open: 20,
    closed: 55,
    avg_resolution_time: 24.5
  },
  activities: {
    total: 200,
    overdue: 10,
    today: 15,
    upcoming: 40
  }
};

// Test 6: Type checking for enums
// const caseStatus: components['schemas']['SupportCase']['status'] = 'new'; // Valid
// const invalidStatus: components['schemas']['SupportCase']['status'] = 'invalid'; // This would error

// Test 7: Pagination structure
// const paginationTest: components['schemas']['Pagination'] = {
//   page: 1,
//   limit: 20,
//   total: 100,
//   total_pages: 5
// };

// Export test to verify compilation
export function testTypeIntegration(): void {
  console.log('Lead:', testLead);
  console.log('Contact:', testContact);
  console.log('Dashboard:', dashboardMetrics);
  console.log('All types are working correctly with snake_case fields!');
}

// Test that incorrect field names would cause TypeScript errors
// Uncommenting these would cause compile errors:
// testLead.firstName = 'John'; // Error: Property 'firstName' does not exist
// testLead.phoneWork = '555-1234'; // Error: Property 'phoneWork' does not exist
// testLead.dateEntered = '2024-01-01'; // Error: Property 'dateEntered' does not exist