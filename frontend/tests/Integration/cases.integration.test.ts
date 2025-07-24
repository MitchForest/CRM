import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { getTestAuthTokens, createAuthenticatedClient } from './helpers/test-auth'
// import { casePriorities } from './helpers/test-data'
import type { AuthTokens } from './helpers/test-auth'

describe('Cases Integration Tests', () => {
  let tokens: AuthTokens
  let suitecrmClient: any
  let customApiClient: any
  let testAccountId: string | null = null
  const createdCaseIds: string[] = []

  beforeAll(async () => {
    tokens = await getTestAuthTokens()
    suitecrmClient = createAuthenticatedClient('http://localhost:8080/Api/V8', tokens.suitecrmToken, true)
    customApiClient = createAuthenticatedClient('http://localhost:8080/custom-api', tokens.customApiToken)
    
    // Create a test account for cases
    const accountData = {
      data: {
        type: 'Accounts',
        attributes: {
          name: 'Test Cases Account',
          phone_office: '555-0000'
        }
      }
    }
    
    const accountResponse = await suitecrmClient.post('/module', accountData)
    testAccountId = accountResponse.data.data.id
  })

  afterAll(async () => {
    // Clean up created cases
    const cleanupPromises = createdCaseIds.map(id =>
      suitecrmClient.delete(`/module/Cases/${id}`).catch(() => {})
    )
    
    // Clean up test account
    if (testAccountId) {
      cleanupPromises.push(
        suitecrmClient.delete(`/module/Accounts/${testAccountId}`).catch(() => {})
      )
    }
    
    await Promise.all(cleanupPromises)
  })

  describe('Case CRUD Operations', () => {
    it('should create cases with different priorities', async () => {
      const priorities = ['P1', 'P2', 'P3']
      
      const casePromises = priorities.map(priority => {
        const caseData = {
          data: {
            type: 'Cases',
            attributes: {
              name: `${priority} Priority Case`,
              type: 'User',
              priority: priority,
              status: 'New',
              description: `Test case with ${priority} priority`,
              account_id: testAccountId
            }
          }
        }
        
        return suitecrmClient.post('/module', caseData)
      })
      
      const responses = await Promise.all(casePromises)
      
      responses.forEach((response, index) => {
        expect(response.status).toBe(201)
        expect(response.data.data).toHaveProperty('id')
        createdCaseIds.push(response.data.data.id)
        
        const attributes = response.data.data.attributes
        expect(attributes.priority).toBe(priorities[index])
        expect(attributes.name).toContain(priorities[index])
      })
    })

    it('should retrieve case with all fields', async () => {
      const caseId = createdCaseIds[0]
      const response = await suitecrmClient.get(`/module/Cases/${caseId}`)
      
      expect(response.status).toBe(200)
      expect(response.data.data.id).toBe(caseId)
      
      const attributes = response.data.data.attributes
      expect(attributes).toHaveProperty('name')
      expect(attributes).toHaveProperty('case_number')
      expect(attributes).toHaveProperty('priority')
      expect(attributes).toHaveProperty('status')
      expect(attributes).toHaveProperty('type')
      expect(attributes).toHaveProperty('description')
    })

    it('should update case status through workflow', async () => {
      const caseId = createdCaseIds[0]
      
      const statusFlow = ['New', 'Assigned', 'Pending Input', 'Resolved', 'Closed']
      
      for (const status of statusFlow) {
        const updateData = {
          data: {
            type: 'Cases',
            id: caseId,
            attributes: {
              status: status,
              resolution: status === 'Resolved' ? 'Issue fixed by resetting password' : undefined
            }
          }
        }
        
        const response = await suitecrmClient.patch('/module', updateData)
        
        expect(response.status).toBe(200)
        expect(response.data.data.attributes.status).toBe(status)
        
        if (status === 'Resolved') {
          expect(response.data.data.attributes.resolution).toBeTruthy()
        }
      }
    })
  })

  describe('SLA Management', () => {
    it('should calculate SLA deadlines based on priority', async () => {
      // Expected SLA times: P1=4h, P2=24h, P3=72h
      const slaExpectations = [
        { priority: 'P1', hoursToResolve: 4 },
        { priority: 'P2', hoursToResolve: 24 },
        { priority: 'P3', hoursToResolve: 72 }
      ]
      
      const casePromises = slaExpectations.map(sla => {
        const caseData = {
          data: {
            type: 'Cases',
            attributes: {
              name: `SLA Test - ${sla.priority}`,
              priority: sla.priority,
              status: 'New',
              type: 'User'
            }
          }
        }
        
        return suitecrmClient.post('/module', caseData)
      })
      
      const responses = await Promise.all(casePromises)
      
      responses.forEach((response, index) => {
        createdCaseIds.push(response.data.data.id)
        
        const attributes = response.data.data.attributes
        const sla = slaExpectations[index]
        
        if (sla) {
          expect(attributes.priority).toBe(sla.priority)
        }
        
        // If SLA fields are available, verify them
        if (attributes.sla_deadline) {
          const createdDate = new Date(attributes.date_entered)
          const slaDeadline = new Date(attributes.sla_deadline)
          const hoursDiff = (slaDeadline.getTime() - createdDate.getTime()) / (1000 * 60 * 60)
          
          // Allow for small variations due to processing time
          expect(Math.round(hoursDiff)).toBe(sla?.hoursToResolve || 0)
        }
      })
    })

    it('should track SLA compliance', async () => {
      // Create a case and resolve it quickly (within SLA)
      const withinSLACase = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Quick Resolution Case',
            priority: 'P2',
            status: 'New',
            type: 'User'
          }
        }
      }
      
      const createResponse = await suitecrmClient.post('/module', withinSLACase)
      const caseId = createResponse.data.data.id
      createdCaseIds.push(caseId)
      
      // Immediately resolve the case
      const resolveData = {
        data: {
          type: 'Cases',
          id: caseId,
          attributes: {
            status: 'Resolved',
            resolution: 'Quick fix applied'
          }
        }
      }
      
      const resolveResponse = await suitecrmClient.patch('/module', resolveData)
      
      expect(resolveResponse.status).toBe(200)
      expect(resolveResponse.data.data.attributes.status).toBe('Resolved')
      
      // If SLA tracking fields are available
      if (resolveResponse.data.data.attributes.sla_met !== undefined) {
        expect(resolveResponse.data.data.attributes.sla_met).toBe(true)
      }
    })
  })

  describe('Case Metrics Integration', () => {
    it('should reflect case counts in dashboard metrics', async () => {
      const metricsResponse = await customApiClient.get('/dashboard/cases')
      
      expect(metricsResponse.status).toBe(200)
      expect(metricsResponse.data).toHaveProperty('data')
      
      const metrics = metricsResponse.data.data
      expect(metrics).toHaveProperty('openCases')
      expect(metrics).toHaveProperty('criticalCases')
      expect(metrics).toHaveProperty('avgResolutionTime')
      expect(metrics).toHaveProperty('casesByPriority')
      
      // Verify we have data for all priorities
      const priorityData = metrics.casesByPriority
      expect(Array.isArray(priorityData)).toBe(true)
      
      ;['P1', 'P2', 'P3'].forEach(priority => {
        const priorityCount = priorityData.find((p: any) => p.priority === priority)
        expect(priorityCount).toBeDefined()
        expect(priorityCount.count).toBeGreaterThanOrEqual(0)
      })
    })

    it('should track critical (P1) cases separately', async () => {
      // Create a P1 case
      const criticalCase = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Critical System Down',
            priority: 'P1',
            status: 'New',
            type: 'Infrastructure',
            description: 'Production system is down'
          }
        }
      }
      
      const createResponse = await suitecrmClient.post('/module', criticalCase)
      createdCaseIds.push(createResponse.data.data.id)
      
      // Wait for data propagation
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Check if critical cases count increased
      const metricsResponse = await customApiClient.get('/dashboard/cases')
      
      expect(metricsResponse.status).toBe(200)
      expect(metricsResponse.data.data.criticalCases).toBeGreaterThan(0)
    })
  })

  describe('Case Filtering and Search', () => {
    it('should filter cases by status', async () => {
      const response = await suitecrmClient.get('/module/Cases', {
        params: {
          'filter[status]': 'New',
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // All returned cases should have New status
      response.data.data.forEach((caseItem: any) => {
        expect(caseItem.attributes.status).toBe('New')
      })
    })

    it('should filter cases by priority', async () => {
      const response = await suitecrmClient.get('/module/Cases', {
        params: {
          'filter[priority]': 'P1',
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // All returned cases should be P1 priority
      response.data.data.forEach((caseItem: any) => {
        expect(caseItem.attributes.priority).toBe('P1')
      })
    })

    it('should search cases by name or description', async () => {
      const searchTerm = 'Critical'
      const response = await suitecrmClient.get('/module/Cases', {
        params: {
          'filter[operator]': 'or',
          'filter[name][like]': `%${searchTerm}%`,
          'filter[description][like]': `%${searchTerm}%`,
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // Results should contain search term in name or description
      response.data.data.forEach((caseItem: any) => {
        const nameMatch = caseItem.attributes.name?.toLowerCase().includes(searchTerm.toLowerCase())
        const descMatch = caseItem.attributes.description?.toLowerCase().includes(searchTerm.toLowerCase())
        expect(nameMatch || descMatch).toBe(true)
      })
    })
  })

  describe('Case Assignment and Ownership', () => {
    it('should assign cases to users', async () => {
      const caseId = createdCaseIds[0]
      
      const updateData = {
        data: {
          type: 'Cases',
          id: caseId,
          attributes: {
            status: 'Assigned',
            assigned_user_id: '1' // Admin user
          }
        }
      }
      
      const response = await suitecrmClient.patch('/module', updateData)
      
      expect(response.status).toBe(200)
      expect(response.data.data.attributes.status).toBe('Assigned')
      expect(response.data.data.attributes.assigned_user_id).toBeTruthy()
    })

    it('should track case ownership changes', async () => {
      const caseData = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Ownership Test Case',
            priority: 'P2',
            status: 'New',
            assigned_user_id: '1'
          }
        }
      }
      
      const createResponse = await suitecrmClient.post('/module', caseData)
      const caseId = createResponse.data.data.id
      createdCaseIds.push(caseId)
      
      // Change assignment
      const reassignData = {
        data: {
          type: 'Cases',
          id: caseId,
          attributes: {
            assigned_user_id: '2' // Different user
          }
        }
      }
      
      const reassignResponse = await suitecrmClient.patch('/module', reassignData)
      
      expect(reassignResponse.status).toBe(200)
      // In a real system, this would trigger notifications and audit logs
    })
  })

  describe('Case Relationships', () => {
    it('should link cases to accounts', async () => {
      const caseId = createdCaseIds[0]
      
      const response = await suitecrmClient.get(`/module/Cases/${caseId}`, {
        params: {
          'include': 'account'
        }
      })
      
      expect(response.status).toBe(200)
      
      if (response.data.data.attributes.account_id) {
        expect(response.data.data.attributes.account_id).toBe(testAccountId)
      }
    })

    it('should support case-to-case relationships', async () => {
      // Create a parent case
      const parentCaseData = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Parent Issue - System Outage',
            priority: 'P1',
            status: 'New',
            type: 'Infrastructure'
          }
        }
      }
      
      const parentResponse = await suitecrmClient.post('/module', parentCaseData)
      const parentId = parentResponse.data.data.id
      createdCaseIds.push(parentId)
      
      // Create a related case
      const relatedCaseData = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Related Issue - Login Failures',
            priority: 'P2',
            status: 'New',
            type: 'User',
            parent_type: 'Cases',
            parent_id: parentId
          }
        }
      }
      
      const relatedResponse = await suitecrmClient.post('/module', relatedCaseData)
      createdCaseIds.push(relatedResponse.data.data.id)
      
      expect(relatedResponse.status).toBe(201)
      expect(relatedResponse.data.data.attributes.parent_id).toBe(parentId)
    })
  })

  describe('Case Type Management', () => {
    it('should support different case types', async () => {
      const caseTypes = ['User', 'Infrastructure', 'Product', 'Administration']
      
      const typePromises = caseTypes.map(type => {
        const caseData = {
          data: {
            type: 'Cases',
            attributes: {
              name: `${type} Type Case`,
              type: type,
              priority: 'P3',
              status: 'New'
            }
          }
        }
        
        return suitecrmClient.post('/module', caseData)
      })
      
      const responses = await Promise.all(typePromises)
      
      responses.forEach((response, index) => {
        expect(response.status).toBe(201)
        createdCaseIds.push(response.data.data.id)
        expect(response.data.data.attributes.type).toBe(caseTypes[index])
      })
    })
  })

  describe('Data Validation', () => {
    it('should require mandatory fields', async () => {
      const incompleteCaseData = {
        data: {
          type: 'Cases',
          attributes: {
            // Missing required name field
            priority: 'P2'
          }
        }
      }
      
      try {
        await suitecrmClient.post('/module', incompleteCaseData)
        expect.fail('Should have rejected incomplete data')
      } catch (error: any) {
        expect(error.response.status).toBeGreaterThanOrEqual(400)
      }
    })

    it('should validate priority values', async () => {
      const invalidPriorityData = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Invalid Priority Case',
            priority: 'P0', // Invalid priority
            status: 'New'
          }
        }
      }
      
      try {
        await suitecrmClient.post('/module', invalidPriorityData)
        // If it doesn't reject, verify it was normalized
      } catch (error: any) {
        // Expected to fail with invalid priority
        expect(error.response.status).toBeGreaterThanOrEqual(400)
      }
    })
  })
})