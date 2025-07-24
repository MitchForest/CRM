import { describe, it, expect, beforeAll, afterAll, beforeEach } from 'vitest'
import { getTestAuthTokens, createAuthenticatedClient } from './helpers/test-auth'
import { testData, opportunityStages } from './helpers/test-data'
import type { AuthTokens } from './helpers/test-auth'

describe('Opportunities Integration Tests', () => {
  let tokens: AuthTokens
  let suitecrmClient: any
  let customApiClient: any
  let testOpportunityId: string | null = null
  let testAccountId: string | null = null

  beforeAll(async () => {
    tokens = await getTestAuthTokens()
    suitecrmClient = createAuthenticatedClient('http://localhost:8080/Api/V8', tokens.suitecrmToken, true)
    customApiClient = createAuthenticatedClient('http://localhost:8080/custom-api', tokens.customApiToken)
    
    // Create a test account for opportunities
    const accountData = {
      data: {
        type: 'Accounts',
        attributes: testData.account
      }
    }
    
    const accountResponse = await suitecrmClient.post('/module', accountData)
    testAccountId = accountResponse.data.data.id
  })

  afterAll(async () => {
    // Clean up test data
    if (testOpportunityId) {
      await suitecrmClient.delete(`/module/Opportunities/${testOpportunityId}`)
    }
    if (testAccountId) {
      await suitecrmClient.delete(`/module/Accounts/${testAccountId}`)
    }
  })

  describe('Opportunity CRUD Operations', () => {
    it('should create a new opportunity', async () => {
      const opportunityData = {
        data: {
          type: 'Opportunities',
          attributes: {
            ...testData.opportunity,
            account_id: testAccountId
          }
        }
      }
      
      const response = await suitecrmClient.post('/module', opportunityData)
      
      expect(response.status).toBe(201)
      expect(response.data.data).toHaveProperty('id')
      expect(response.data.data.type).toBe('Opportunities')
      
      testOpportunityId = response.data.data.id
      
      // Verify created opportunity
      const attributes = response.data.data.attributes
      expect(attributes.name).toBe(testData.opportunity.name)
      expect(attributes.sales_stage).toBe('Qualification')
      expect(parseFloat(attributes.amount)).toBe(50000)
    })

    it('should retrieve opportunity with all fields', async () => {
      const response = await suitecrmClient.get(`/module/Opportunities/${testOpportunityId}`)
      
      expect(response.status).toBe(200)
      expect(response.data.data.id).toBe(testOpportunityId)
      
      const attributes = response.data.data.attributes
      expect(attributes).toHaveProperty('name')
      expect(attributes).toHaveProperty('sales_stage')
      expect(attributes).toHaveProperty('amount')
      expect(attributes).toHaveProperty('probability')
      expect(attributes).toHaveProperty('date_closed')
    })

    it('should update opportunity details', async () => {
      const updateData = {
        data: {
          type: 'Opportunities',
          id: testOpportunityId,
          attributes: {
            amount: 75000,
            description: 'Updated deal size after negotiation'
          }
        }
      }
      
      const response = await suitecrmClient.patch('/module', updateData)
      
      expect(response.status).toBe(200)
      expect(parseFloat(response.data.data.attributes.amount)).toBe(75000)
      expect(response.data.data.attributes.description).toContain('Updated deal size')
    })

    it('should list opportunities with pagination', async () => {
      const response = await suitecrmClient.get('/module/Opportunities', {
        params: {
          'page[size]': 10,
          'page[number]': 1,
          'sort': '-date_entered'
        }
      })
      
      expect(response.status).toBe(200)
      expect(Array.isArray(response.data.data)).toBe(true)
      expect(response.data.meta).toHaveProperty('total-count')
      expect(response.data.meta).toHaveProperty('page-count')
    })
  })

  describe('Kanban Board - Stage Management', () => {
    it('should update opportunity stage and probability automatically', async () => {
      // Test moving through each stage
      const stageTests = [
        { stage: 'Needs Analysis', expectedProbability: 20 },
        { stage: 'Value Proposition', expectedProbability: 40 },
        { stage: 'Decision Makers', expectedProbability: 60 },
        { stage: 'Proposal', expectedProbability: 75 },
        { stage: 'Negotiation', expectedProbability: 90 }
      ]
      
      for (const test of stageTests) {
        const updateData = {
          data: {
            type: 'Opportunities',
            id: testOpportunityId,
            attributes: {
              sales_stage: test.stage
            }
          }
        }
        
        const response = await suitecrmClient.patch('/module', updateData)
        
        expect(response.status).toBe(200)
        expect(response.data.data.attributes.sales_stage).toBe(test.stage)
        expect(parseInt(response.data.data.attributes.probability)).toBe(test.expectedProbability)
      }
    })

    it('should handle closing opportunities (Won/Lost)', async () => {
      // Test Closed Won
      let updateData = {
        data: {
          type: 'Opportunities',
          id: testOpportunityId,
          attributes: {
            sales_stage: 'Closed Won'
          }
        }
      }
      
      let response = await suitecrmClient.patch('/module', updateData)
      
      expect(response.status).toBe(200)
      expect(response.data.data.attributes.sales_stage).toBe('Closed Won')
      expect(parseInt(response.data.data.attributes.probability)).toBe(100)
      
      // Test Closed Lost
      updateData = {
        data: {
          type: 'Opportunities',
          id: testOpportunityId,
          attributes: {
            sales_stage: 'Closed Lost'
          }
        }
      }
      
      response = await suitecrmClient.patch('/module', updateData)
      
      expect(response.status).toBe(200)
      expect(response.data.data.attributes.sales_stage).toBe('Closed Lost')
      expect(parseInt(response.data.data.attributes.probability)).toBe(0)
    })
  })

  describe('Pipeline Analytics Integration', () => {
    it('should reflect opportunity changes in pipeline data', async () => {
      // Get initial pipeline data
      const initialPipeline = await customApiClient.get('/dashboard/pipeline')
      const initialQualificationCount = initialPipeline.data.data.stages
        .find((s: any) => s.stage === 'Qualification')?.count || 0
      
      // Create a new opportunity in Qualification stage
      const newOppData = {
        data: {
          type: 'Opportunities',
          attributes: {
            name: 'Pipeline Test Opportunity',
            sales_stage: 'Qualification',
            amount: 100000,
            probability: 10,
            date_closed: testData.opportunity.date_closed,
            account_id: testAccountId
          }
        }
      }
      
      const createResponse = await suitecrmClient.post('/module', newOppData)
      const newOppId = createResponse.data.data.id
      
      // Wait a moment for data to propagate
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Get updated pipeline data
      const updatedPipeline = await customApiClient.get('/dashboard/pipeline')
      const updatedQualificationCount = updatedPipeline.data.data.stages
        .find((s: any) => s.stage === 'Qualification')?.count || 0
      
      expect(updatedQualificationCount).toBe(initialQualificationCount + 1)
      
      // Clean up
      await suitecrmClient.delete(`/module/Opportunities/${newOppId}`)
    })
  })

  describe('Opportunity Filtering and Search', () => {
    it('should filter opportunities by stage', async () => {
      const response = await suitecrmClient.get('/module/Opportunities', {
        params: {
          'filter[sales_stage]': 'Qualification',
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // All returned opportunities should be in Qualification stage
      response.data.data.forEach((opp: any) => {
        expect(opp.attributes.sales_stage).toBe('Qualification')
      })
    })

    it('should search opportunities by name', async () => {
      const searchTerm = 'Deal'
      const response = await suitecrmClient.get('/module/Opportunities', {
        params: {
          'filter[name][like]': `%${searchTerm}%`,
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // All returned opportunities should contain search term
      response.data.data.forEach((opp: any) => {
        expect(opp.attributes.name.toLowerCase()).toContain(searchTerm.toLowerCase())
      })
    })

    it('should filter opportunities by amount range', async () => {
      const response = await suitecrmClient.get('/module/Opportunities', {
        params: {
          'filter[amount][gte]': '50000',
          'filter[amount][lte]': '100000',
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // All returned opportunities should be within amount range
      response.data.data.forEach((opp: any) => {
        const amount = parseFloat(opp.attributes.amount)
        expect(amount).toBeGreaterThanOrEqual(50000)
        expect(amount).toBeLessThanOrEqual(100000)
      })
    })
  })

  describe('Opportunity Relationships', () => {
    it('should link opportunity to account', async () => {
      const response = await suitecrmClient.get(`/module/Opportunities/${testOpportunityId}`, {
        params: {
          'fields[Opportunities]': 'name,sales_stage,amount',
          'include': 'account'
        }
      })
      
      expect(response.status).toBe(200)
      expect(response.data.data.relationships).toHaveProperty('account')
      expect(response.data.data.relationships.account.data).toHaveProperty('id')
      
      // If included, verify account data
      if (response.data.included && response.data.included.length > 0) {
        const includedAccount = response.data.included.find((item: any) => 
          item.type === 'Accounts' && item.id === testAccountId
        )
        expect(includedAccount).toBeDefined()
      }
    })
  })

  describe('Data Validation', () => {
    it('should reject invalid sales stage', async () => {
      const invalidData = {
        data: {
          type: 'Opportunities',
          attributes: {
            name: 'Invalid Stage Test',
            sales_stage: 'Invalid Stage',
            amount: 10000
          }
        }
      }
      
      try {
        await suitecrmClient.post('/module', invalidData)
        expect.fail('Should have rejected invalid stage')
      } catch (error: any) {
        expect(error.response.status).toBeGreaterThanOrEqual(400)
      }
    })

    it('should require mandatory fields', async () => {
      const incompleteData = {
        data: {
          type: 'Opportunities',
          attributes: {
            // Missing name
            sales_stage: 'Qualification'
          }
        }
      }
      
      try {
        await suitecrmClient.post('/module', incompleteData)
        expect.fail('Should have rejected incomplete data')
      } catch (error: any) {
        expect(error.response.status).toBeGreaterThanOrEqual(400)
      }
    })
  })
})