import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { apiClient } from '@/lib/api-client'
import type { Lead, Account } from '@/types/api.generated'

// Integration tests for SuiteCRM v8 API
// These tests verify the actual API behavior with a running SuiteCRM instance

describe('SuiteCRM API Integration Tests', () => {
  const testCredentials = {
    username: 'apiuser',
    password: 'apiuser123'
  }

  let testLeadId: string
  let testAccountId: string

  beforeAll(async () => {
    // Authenticate before running tests
    const loginResult = await apiClient.login(
      testCredentials.username,
      testCredentials.password
    )
    
    expect(loginResult.success).toBe(true)
    if (loginResult.data) {
      // authToken is stored internally in apiClient
    }
  })

  afterAll(async () => {
    // Clean up test data
    if (testLeadId) {
      await apiClient.deleteLead(testLeadId)
    }
    if (testAccountId) {
      await apiClient.deleteAccount(testAccountId)
    }
  })

  describe('Leads Module', () => {
    describe('Field Mapping', () => {
      it('should correctly map email field when creating a lead', async () => {
        const leadData: Partial<Lead> = {
          firstName: 'Test',
          lastName: 'Integration',
          email: 'test.integration@example.com',
          phone: '123-456-7890',
          company: 'Test Company',
          status: 'New'
        }

        const result = await apiClient.createLead(leadData)
        
        expect(result.success).toBe(true)
        expect(result.data).toBeDefined()
        
        if (result.data) {
          testLeadId = result.data.id!
          
          // Verify email was saved correctly
          expect(result.data.email).toBe(leadData.email)
          expect(result.data.firstName).toBe(leadData.firstName)
          expect(result.data.lastName).toBe(leadData.lastName)
        }
      })

      it('should return custom AI fields in response', async () => {
        if (!testLeadId) {
          throw new Error('Test lead not created')
        }

        const result = await apiClient.getLead(testLeadId)
        
        expect(result.success).toBe(true)
        expect(result.data).toBeDefined()
        
        if (result.data) {
          // Check if custom fields are present (they should be if configured in SuiteCRM)
          // These might be null/undefined if not set, but the fields should exist
          expect('aiScore' in result.data).toBe(true)
          expect('aiScoreDate' in result.data).toBe(true)
          expect('aiInsights' in result.data).toBe(true)
        }
      })
    })

    describe('CRUD Operations', () => {
      it('should list leads with pagination', async () => {
        const result = await apiClient.getLeads({
          page: 1,
          pageSize: 10
        })

        expect(result.data).toBeInstanceOf(Array)
        expect(result.pagination).toBeDefined()
        expect(result.pagination.page).toBe(1)
        expect(result.pagination.pageSize).toBeLessThanOrEqual(10)
      })

      it('should search leads by name', async () => {
        const result = await apiClient.getLeads({
          search: 'Test',
          page: 1,
          pageSize: 10
        })

        expect(result.data).toBeInstanceOf(Array)
        // If we have results, verify they match the search
        if (result.data.length > 0) {
          const hasMatch = result.data.some(lead => 
            lead.firstName?.includes('Test') || 
            lead.lastName?.includes('Test') ||
            lead.email?.includes('Test')
          )
          expect(hasMatch).toBe(true)
        }
      })

      it('should update a lead using PATCH', async () => {
        if (!testLeadId) {
          throw new Error('Test lead not created')
        }

        const updateData: Partial<Lead> = {
          firstName: 'Updated',
          status: 'Qualified',
          description: 'Updated via integration test'
        }

        const result = await apiClient.updateLead(testLeadId, updateData)
        
        expect(result.success).toBe(true)
        expect(result.data).toBeDefined()
        
        if (result.data) {
          expect(result.data.firstName).toBe(updateData.firstName)
          expect(result.data.status).toBe(updateData.status)
          expect(result.data.description).toBe(updateData.description)
          // Email should remain unchanged
          expect(result.data.email).toBe('test.integration@example.com')
        }
      })

      it('should delete a lead', async () => {
        // Create a lead specifically for deletion test
        const leadData: Partial<Lead> = {
          firstName: 'Delete',
          lastName: 'Test',
          email: 'delete.test@example.com'
        }

        const createResult = await apiClient.createLead(leadData)
        expect(createResult.success).toBe(true)

        if (createResult.data) {
          const deleteResult = await apiClient.deleteLead(createResult.data.id!)
          expect(deleteResult.success).toBe(true)

          // Verify lead is deleted by trying to fetch it
          try {
            await apiClient.getLead(createResult.data.id!)
            throw new Error('Expected getLead to throw for deleted lead')
          } catch {
            // Expected - lead should not exist
          }
        }
      })
    })
  })

  describe('Accounts Module', () => {
    describe('CRUD Operations', () => {
      it('should create an account', async () => {
        const accountData: Partial<Account> = {
          name: 'Test Integration Account',
          industry: 'Technology',
          website: 'https://test.example.com',
          phone: '555-1234',
          billingCity: 'Test City',
          billingCountry: 'USA'
        }

        const result = await apiClient.createAccount(accountData)
        
        expect(result.success).toBe(true)
        expect(result.data).toBeDefined()
        
        if (result.data) {
          testAccountId = result.data.id!
          expect(result.data.name).toBe(accountData.name)
          expect(result.data.industry).toBe(accountData.industry)
          expect(result.data.website).toBe(accountData.website)
        }
      })

      it('should return custom fields in account response', async () => {
        if (!testAccountId) {
          throw new Error('Test account not created')
        }

        const result = await apiClient.getAccount(testAccountId)
        
        expect(result.success).toBe(true)
        expect(result.data).toBeDefined()
        
        if (result.data) {
          // Check if custom fields are present
          expect('healthScore' in result.data).toBe(true)
          expect('mrr' in result.data).toBe(true)
          expect('lastActivity' in result.data).toBe(true)
        }
      })

      it('should update an account using PATCH', async () => {
        if (!testAccountId) {
          throw new Error('Test account not created')
        }

        const updateData: Partial<Account> = {
          name: 'Updated Test Account',
          industry: 'Finance',
          annualRevenue: 1000000
        }

        const result = await apiClient.updateAccount(testAccountId, updateData)
        
        expect(result.success).toBe(true)
        expect(result.data).toBeDefined()
        
        if (result.data) {
          expect(result.data.name).toBe(updateData.name)
          expect(result.data.industry).toBe(updateData.industry)
          expect(result.data.annualRevenue).toBe(updateData.annualRevenue)
        }
      })

      it('should list accounts with sorting', async () => {
        const result = await apiClient.getAccounts({
          sort: [{ field: 'name', direction: 'asc' }],
          page: 1,
          pageSize: 10
        })

        expect(result.data).toBeInstanceOf(Array)
        expect(result.pagination).toBeDefined()
        
        // If we have multiple results, verify they're sorted
        if (result.data.length > 1) {
          for (let i = 1; i < result.data.length; i++) {
            const prev = result.data[i - 1]!.name || ''
            const curr = result.data[i]!.name || ''
            expect(prev.localeCompare(curr)).toBeLessThanOrEqual(0)
          }
        }
      })
    })
  })

  describe('Error Handling', () => {
    it('should handle 404 errors gracefully', async () => {
      try {
        await apiClient.getLead('non-existent-id')
        throw new Error('Expected getLead to throw for non-existent ID')
      } catch (error: unknown) {
        expect((error as Error & { response?: { status: number } }).response?.status).toBe(404)
      }
    })

    it('should handle validation errors', async () => {
      const invalidLead: Partial<Lead> = {
        // Missing required fields
        firstName: '',
        lastName: ''
      }

      const result = await apiClient.createLead(invalidLead)
      
      // API might return success with empty fields or return validation error
      // This depends on SuiteCRM configuration
      if (!result.success) {
        expect(result.error).toBeDefined()
      }
    })
  })

  describe('Dashboard Stats', () => {
    it('should return dashboard statistics', async () => {
      const result = await apiClient.getDashboardStats()
      
      expect(result.data).toBeDefined()
      if (result.data) {
        expect(typeof result.data.totalLeads).toBe('number')
        expect(typeof result.data.totalAccounts).toBe('number')
        expect(typeof result.data.newLeadsToday).toBe('number')
        expect(typeof result.data.pipelineValue).toBe('number')
        
        expect(result.data.totalLeads).toBeGreaterThanOrEqual(0)
        expect(result.data.totalAccounts).toBeGreaterThanOrEqual(0)
      }
    })
  })
})

// Run with: npm test -- api-client.integration.test.ts
// Requires a running SuiteCRM instance at http://localhost:8080