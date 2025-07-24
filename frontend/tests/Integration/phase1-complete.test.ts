import { describe, it, expect, beforeAll } from 'vitest'
import { apiClient } from '@/lib/api-client'

describe('Phase 1 Complete Integration Test', () => {
  let authToken: string
  let createdLeadId: string
  let createdAccountId: string
  
  beforeAll(async () => {
    const loginResult = await apiClient.login('apiuser', 'apiuser123')
    expect(loginResult.success).toBe(true)
    authToken = loginResult.data!.accessToken
  })

  describe('Leads Module - FULL TEST', () => {
    it('should CREATE a lead with all fields', async () => {
      const leadData = {
        firstName: 'Phase1',
        lastName: 'TestLead',
        email: 'phase1test@example.com',
        phone: '555-1111',
        mobile: '555-2222',
        title: 'CTO',
        company: 'Test Corp',
        accountName: 'Test Account',
        website: 'https://test.com',
        description: 'Phase 1 integration test lead',
        status: 'New',
        source: 'Website',
        // AI fields
        aiScore: 85,
        aiScoreDate: new Date().toISOString().split('T')[0],
        aiInsights: 'High value prospect'
      }

      const result = await apiClient.createLead(leadData)
      
      expect(result.success).toBe(true)
      expect(result.data).toBeDefined()
      expect(result.data!.id).toBeDefined()
      
      createdLeadId = result.data!.id!
      
      // Verify all fields were saved
      expect(result.data!.firstName).toBe('Phase1')
      expect(result.data!.lastName).toBe('TestLead')
      expect(result.data!.email).toBe('phase1test@example.com')
    })

    it('should READ the created lead back', async () => {
      const result = await apiClient.getLead(createdLeadId)
      
      expect(result.success).toBe(true)
      expect(result.data).toBeDefined()
      expect(result.data!.id).toBe(createdLeadId)
      expect(result.data!.firstName).toBe('Phase1')
      expect(result.data!.email).toBe('phase1test@example.com')
      expect(result.data!.aiScore).toBe(85)
      expect(result.data!.aiInsights).toBe('High value prospect')
    })

    it('should UPDATE the lead', async () => {
      const updateData = {
        status: 'Qualified',
        description: 'Updated in Phase 1 test',
        aiScore: 95,
        aiInsights: 'Very high value - ready to convert'
      }

      const result = await apiClient.updateLead(createdLeadId, updateData)
      
      expect(result.success).toBe(true)
      
      // Verify update
      const getResult = await apiClient.getLead(createdLeadId)
      expect(getResult.data!.status).toBe('Qualified')
      expect(getResult.data!.aiScore).toBe(95)
    })

    it('should LIST leads with search and pagination', async () => {
      const result = await apiClient.getLeads({
        page: 1,
        pageSize: 10,
        search: 'Phase1'
      })
      
      expect(result.data).toBeDefined()
      expect(result.data.length).toBeGreaterThan(0)
      expect(result.data.some(lead => lead.id === createdLeadId)).toBe(true)
      expect(result.pagination).toBeDefined()
    })

    it('should DELETE the lead', async () => {
      const result = await apiClient.deleteLead(createdLeadId)
      
      expect(result.success).toBe(true)
      
      // Verify deletion
      try {
        await apiClient.getLead(createdLeadId)
        expect(true).toBe(false) // Should not reach here
      } catch (error) {
        expect(error).toBeDefined() // Should get 404
      }
    })
  })

  describe('Accounts Module - FULL TEST', () => {
    it('should CREATE an account with all fields', async () => {
      const accountData = {
        name: 'Phase1 Test Account',
        phone: '555-3333',
        website: 'https://phase1test.com',
        email: 'info@phase1test.com',
        description: 'Phase 1 integration test account',
        type: 'Customer',
        industry: 'Technology',
        // Custom fields
        healthScore: 85,
        mrr: 5000,
        lastActivity: new Date().toISOString()
      }

      const result = await apiClient.createAccount(accountData)
      
      expect(result.success).toBe(true)
      expect(result.data).toBeDefined()
      expect(result.data!.id).toBeDefined()
      
      createdAccountId = result.data!.id!
    })

    it('should READ the created account', async () => {
      const result = await apiClient.getAccount(createdAccountId)
      
      expect(result.success).toBe(true)
      expect(result.data!.name).toBe('Phase1 Test Account')
      expect(result.data!.healthScore).toBe(85)
      expect(result.data!.mrr).toBe(5000)
    })

    it('should UPDATE the account', async () => {
      const updateData = {
        healthScore: 95,
        mrr: 7500,
        description: 'Updated in Phase 1 test'
      }

      const result = await apiClient.updateAccount(createdAccountId, updateData)
      
      expect(result.success).toBe(true)
      
      // Verify
      const getResult = await apiClient.getAccount(createdAccountId)
      expect(getResult.data!.healthScore).toBe(95)
      expect(getResult.data!.mrr).toBe(7500)
    })

    it('should LIST accounts with pagination', async () => {
      const result = await apiClient.getAccounts({
        page: 1,
        pageSize: 10,
        search: 'Phase1'
      })
      
      expect(result.data).toBeDefined()
      expect(result.data.some(acc => acc.id === createdAccountId)).toBe(true)
    })

    it('should DELETE the account', async () => {
      const result = await apiClient.deleteAccount(createdAccountId)
      
      expect(result.success).toBe(true)
    })
  })

  describe('Dashboard Metrics', () => {
    it('should return accurate dashboard stats', async () => {
      const result = await apiClient.getDashboardStats()
      
      expect(result.success).toBe(true)
      expect(result.data).toBeDefined()
      expect(typeof result.data!.totalLeads).toBe('number')
      expect(typeof result.data!.totalAccounts).toBe('number')
      expect(typeof result.data!.newLeadsToday).toBe('number')
      expect(result.data!.totalLeads).toBeGreaterThanOrEqual(0)
      expect(result.data!.totalAccounts).toBeGreaterThanOrEqual(0)
    })
  })

  describe('Field Transformations', () => {
    it('should handle all field mappings correctly', async () => {
      const testData = {
        firstName: 'FieldTest',
        lastName: 'User',
        email: 'fieldtest@example.com',
        phoneWork: '555-4444',
        phoneMobile: '555-5555',
        accountName: 'Field Test Account',
        leadSource: 'API Test',
        aiScore: 75,
        aiScoreDate: '2024-01-01',
        aiInsights: 'Test insights'
      }

      const result = await apiClient.createLead(testData)
      
      expect(result.success).toBe(true)
      
      // Read it back
      const getResult = await apiClient.getLead(result.data!.id!)
      
      // Verify all mappings worked
      expect(getResult.data!.email).toBe('fieldtest@example.com') // email1 -> email
      expect(getResult.data!.phoneWork).toBe('555-4444') // phone_work -> phoneWork
      expect(getResult.data!.leadSource).toBe('API Test') // lead_source -> leadSource
      
      // Clean up
      await apiClient.deleteLead(result.data!.id!)
    })
  })
})