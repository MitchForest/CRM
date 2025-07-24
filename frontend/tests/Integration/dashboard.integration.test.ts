import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { getTestAuthTokens, createAuthenticatedClient } from './helpers/test-auth'
import type { AuthTokens } from './helpers/test-auth'

describe('Dashboard Integration Tests', () => {
  let tokens: AuthTokens
  let customApiClient: any
  let suitecrmClient: any

  beforeAll(async () => {
    // Get authentication tokens
    tokens = await getTestAuthTokens()
    
    // Create authenticated clients
    customApiClient = createAuthenticatedClient('http://localhost:8080/custom-api', tokens.customApiToken)
    suitecrmClient = createAuthenticatedClient('http://localhost:8080/Api/V8', tokens.suitecrmToken, true)
  })

  describe('Dashboard Metrics API', () => {
    it('should fetch dashboard metrics successfully', async () => {
      const response = await customApiClient.get('/dashboard/metrics')
      
      expect(response.status).toBe(200)
      expect(response.data).toHaveProperty('data')
      
      const metrics = response.data.data
      expect(metrics).toHaveProperty('totalLeads')
      expect(metrics).toHaveProperty('totalAccounts')
      expect(metrics).toHaveProperty('newLeadsToday')
      expect(metrics).toHaveProperty('pipelineValue')
      
      // Verify data types
      expect(typeof metrics.totalLeads).toBe('number')
      expect(typeof metrics.totalAccounts).toBe('number')
      expect(typeof metrics.newLeadsToday).toBe('number')
      expect(typeof metrics.pipelineValue).toBe('number')
    })

    it('should return non-negative values for all metrics', async () => {
      const response = await customApiClient.get('/dashboard/metrics')
      const metrics = response.data.data
      
      expect(metrics.totalLeads).toBeGreaterThanOrEqual(0)
      expect(metrics.totalAccounts).toBeGreaterThanOrEqual(0)
      expect(metrics.newLeadsToday).toBeGreaterThanOrEqual(0)
      expect(metrics.pipelineValue).toBeGreaterThanOrEqual(0)
    })
  })

  describe('Pipeline Data API', () => {
    it('should fetch pipeline data with all sales stages', async () => {
      const response = await customApiClient.get('/dashboard/pipeline')
      
      expect(response.status).toBe(200)
      expect(response.data).toHaveProperty('data')
      expect(response.data.data).toHaveProperty('stages')
      
      const stages = response.data.data.stages
      expect(Array.isArray(stages)).toBe(true)
      
      // Verify all B2B stages are present
      const expectedStages = [
        'Qualification',
        'Needs Analysis',
        'Value Proposition',
        'Decision Makers',
        'Proposal',
        'Negotiation',
        'Closed Won',
        'Closed Lost'
      ]
      
      expectedStages.forEach(stageName => {
        const stage = stages.find((s: any) => s.stage === stageName)
        expect(stage).toBeDefined()
        expect(stage).toHaveProperty('count')
        expect(stage).toHaveProperty('value')
        expect(typeof stage.count).toBe('number')
        expect(typeof stage.value).toBe('number')
      })
    })

    it('should calculate total pipeline value correctly', async () => {
      const response = await customApiClient.get('/dashboard/pipeline')
      const data = response.data.data
      
      expect(data).toHaveProperty('total')
      expect(typeof data.total).toBe('number')
      
      // Verify total matches sum of stage values
      const calculatedTotal = data.stages.reduce((sum: number, stage: any) => sum + stage.value, 0)
      expect(data.total).toBe(calculatedTotal)
    })
  })

  describe('Activity Metrics API', () => {
    it('should fetch activity metrics successfully', async () => {
      const response = await customApiClient.get('/dashboard/activities')
      
      expect(response.status).toBe(200)
      expect(response.data).toHaveProperty('data')
      
      const activities = response.data.data
      expect(activities).toHaveProperty('callsToday')
      expect(activities).toHaveProperty('meetingsToday')
      expect(activities).toHaveProperty('tasksOverdue')
      expect(activities).toHaveProperty('upcomingActivities')
      
      expect(typeof activities.callsToday).toBe('number')
      expect(typeof activities.meetingsToday).toBe('number')
      expect(typeof activities.tasksOverdue).toBe('number')
      expect(Array.isArray(activities.upcomingActivities)).toBe(true)
    })

    it('should return valid upcoming activities', async () => {
      const response = await customApiClient.get('/dashboard/activities')
      const activities = response.data.data.upcomingActivities
      
      activities.forEach((activity: any) => {
        expect(activity).toHaveProperty('id')
        expect(activity).toHaveProperty('name')
        expect(activity).toHaveProperty('type')
        expect(activity).toHaveProperty('date')
        expect(activity).toHaveProperty('assignedTo')
        
        // Verify activity type is valid
        expect(['Call', 'Meeting', 'Task']).toContain(activity.type)
      })
    })
  })

  describe('Case Metrics API', () => {
    it('should fetch case metrics successfully', async () => {
      const response = await customApiClient.get('/dashboard/cases')
      
      expect(response.status).toBe(200)
      expect(response.data).toHaveProperty('data')
      
      const cases = response.data.data
      expect(cases).toHaveProperty('openCases')
      expect(cases).toHaveProperty('criticalCases')
      expect(cases).toHaveProperty('avgResolutionTime')
      expect(cases).toHaveProperty('casesByPriority')
      
      expect(typeof cases.openCases).toBe('number')
      expect(typeof cases.criticalCases).toBe('number')
      expect(typeof cases.avgResolutionTime).toBe('number')
      expect(Array.isArray(cases.casesByPriority)).toBe(true)
    })

    it('should return cases grouped by priority', async () => {
      const response = await customApiClient.get('/dashboard/cases')
      const casesByPriority = response.data.data.casesByPriority
      
      // Verify P1, P2, P3 priorities are present
      const priorities = ['P1', 'P2', 'P3']
      priorities.forEach(priority => {
        const priorityData = casesByPriority.find((p: any) => p.priority === priority)
        expect(priorityData).toBeDefined()
        expect(priorityData).toHaveProperty('count')
        expect(typeof priorityData.count).toBe('number')
        expect(priorityData.count).toBeGreaterThanOrEqual(0)
      })
    })
  })

  describe('Cross-API Data Consistency', () => {
    it('should have consistent lead counts between metrics and module API', async () => {
      // Get lead count from dashboard metrics
      const metricsResponse = await customApiClient.get('/dashboard/metrics')
      const metricsLeadCount = metricsResponse.data.data.totalLeads
      
      // Get lead count from SuiteCRM V8 API
      const leadsResponse = await suitecrmClient.get('/module/Leads', {
        params: { 'page[size]': 1 }
      })
      const apiLeadCount = leadsResponse.data.meta?.['total-count'] || 0
      
      // Allow for small discrepancies due to timing
      expect(Math.abs(metricsLeadCount - apiLeadCount)).toBeLessThanOrEqual(5)
    })

    it('should have consistent account counts between APIs', async () => {
      // Get account count from dashboard metrics
      const metricsResponse = await customApiClient.get('/dashboard/metrics')
      const metricsAccountCount = metricsResponse.data.data.totalAccounts
      
      // Get account count from SuiteCRM V8 API
      const accountsResponse = await suitecrmClient.get('/module/Accounts', {
        params: { 'page[size]': 1 }
      })
      const apiAccountCount = accountsResponse.data.meta?.['total-count'] || 0
      
      // Allow for small discrepancies due to timing
      expect(Math.abs(metricsAccountCount - apiAccountCount)).toBeLessThanOrEqual(5)
    })
  })

  describe('Performance Tests', () => {
    it('should return dashboard metrics within acceptable time', async () => {
      const startTime = Date.now()
      await customApiClient.get('/dashboard/metrics')
      const endTime = Date.now()
      
      const responseTime = endTime - startTime
      expect(responseTime).toBeLessThan(1000) // Should respond within 1 second
    })

    it('should handle concurrent dashboard requests', async () => {
      const requests = [
        customApiClient.get('/dashboard/metrics'),
        customApiClient.get('/dashboard/pipeline'),
        customApiClient.get('/dashboard/activities'),
        customApiClient.get('/dashboard/cases')
      ]
      
      const responses = await Promise.all(requests)
      
      responses.forEach(response => {
        expect(response.status).toBe(200)
        expect(response.data).toHaveProperty('data')
      })
    })
  })

  describe('Error Handling', () => {
    it('should handle unauthorized requests gracefully', async () => {
      const unauthClient = createAuthenticatedClient('http://localhost:8080/custom-api', 'invalid-token')
      
      try {
        await unauthClient.get('/dashboard/metrics')
        expect.fail('Should have thrown an error')
      } catch (error: any) {
        expect(error.response.status).toBe(401)
      }
    })
  })
})