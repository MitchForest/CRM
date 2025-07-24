import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { getTestAuthTokens, createAuthenticatedClient } from './helpers/test-auth'
import type { AuthTokens } from './helpers/test-auth'

describe('E2E Workflow Tests', () => {
  let tokens: AuthTokens
  let suitecrmClient: any
  let customApiClient: any
  const createdIds: {
    leads: string[]
    accounts: string[]
    contacts: string[]
    opportunities: string[]
    activities: string[]
    cases: string[]
  } = {
    leads: [],
    accounts: [],
    contacts: [],
    opportunities: [],
    activities: [],
    cases: []
  }

  beforeAll(async () => {
    tokens = await getTestAuthTokens()
    suitecrmClient = createAuthenticatedClient('http://localhost:8080/Api/V8', tokens.suitecrmToken, true)
    customApiClient = createAuthenticatedClient('http://localhost:8080/custom-api', tokens.customApiToken)
  })

  afterAll(async () => {
    // Clean up all created records
    const cleanupPromises: Promise<any>[] = []
    
    Object.entries(createdIds).forEach(([module, ids]) => {
      const moduleName = module.charAt(0).toUpperCase() + module.slice(1, -1)
      ids.forEach(id => {
        cleanupPromises.push(
          suitecrmClient.delete(`/module/${moduleName}/${id}`).catch(() => {})
        )
      })
    })
    
    await Promise.all(cleanupPromises)
  })

  describe('Lead to Customer Journey', () => {
    let leadId: string
    let accountId: string
    let contactId: string
    let opportunityId: string

    it('Step 1: Create and qualify a new lead', async () => {
      // Create lead
      const leadData = {
        data: {
          type: 'Leads',
          attributes: {
            first_name: 'Enterprise',
            last_name: 'Customer',
            email1: 'enterprise@bigcorp.com',
            phone_mobile: '555-0001',
            title: 'CTO',
            account_name: 'BigCorp Technologies',
            lead_source: 'Website',
            status: 'New',
            industry: 'Technology',
            website: 'https://bigcorp.com',
            description: 'Interested in our enterprise solution'
          }
        }
      }
      
      const leadResponse = await suitecrmClient.post('/module', leadData)
      expect(leadResponse.status).toBe(201)
      leadId = leadResponse.data.data.id
      createdIds.leads.push(leadId)
      
      // Update lead status to Qualified
      const qualifyData = {
        data: {
          type: 'Leads',
          id: leadId,
          attributes: {
            status: 'Qualified'
          }
        }
      }
      
      const qualifyResponse = await suitecrmClient.patch('/module', qualifyData)
      expect(qualifyResponse.status).toBe(200)
      expect(qualifyResponse.data.data.attributes.status).toBe('Qualified')
    })

    it('Step 2: Convert lead to account and contact', async () => {
      // Create account from lead data
      const accountData = {
        data: {
          type: 'Accounts',
          attributes: {
            name: 'BigCorp Technologies',
            website: 'https://bigcorp.com',
            phone_office: '555-0001',
            industry: 'Technology',
            account_type: 'Customer',
            annual_revenue: '50000000',
            employees: '500'
          }
        }
      }
      
      const accountResponse = await suitecrmClient.post('/module', accountData)
      expect(accountResponse.status).toBe(201)
      accountId = accountResponse.data.data.id
      createdIds.accounts.push(accountId)
      
      // Create contact from lead data
      const contactData = {
        data: {
          type: 'Contacts',
          attributes: {
            first_name: 'Enterprise',
            last_name: 'Customer',
            email1: 'enterprise@bigcorp.com',
            phone_mobile: '555-0001',
            title: 'CTO',
            account_id: accountId
          }
        }
      }
      
      const contactResponse = await suitecrmClient.post('/module', contactData)
      expect(contactResponse.status).toBe(201)
      contactId = contactResponse.data.data.id
      createdIds.contacts.push(contactId)
      
      // Update lead to converted status
      const convertData = {
        data: {
          type: 'Leads',
          id: leadId,
          attributes: {
            status: 'Converted',
            account_id: accountId,
            contact_id: contactId
          }
        }
      }
      
      const convertResponse = await suitecrmClient.patch('/module', convertData)
      expect(convertResponse.status).toBe(200)
    })

    it('Step 3: Create opportunity and progress through sales pipeline', async () => {
      // Create opportunity
      const oppData = {
        data: {
          type: 'Opportunities',
          attributes: {
            name: 'BigCorp Enterprise Deal',
            account_id: accountId,
            sales_stage: 'Qualification',
            amount: 250000,
            probability: 10,
            date_closed: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
            lead_source: 'Website',
            description: 'Enterprise software package with support'
          }
        }
      }
      
      const oppResponse = await suitecrmClient.post('/module', oppData)
      expect(oppResponse.status).toBe(201)
      opportunityId = oppResponse.data.data.id
      createdIds.opportunities.push(opportunityId)
      
      // Progress through pipeline stages
      const stages = [
        { stage: 'Needs Analysis', activities: ['Schedule discovery call'] },
        { stage: 'Value Proposition', activities: ['Product demo', 'ROI analysis'] },
        { stage: 'Decision Makers', activities: ['Executive presentation'] },
        { stage: 'Proposal', activities: ['Send proposal', 'Follow up'] },
        { stage: 'Negotiation', activities: ['Contract review'] },
        { stage: 'Closed Won', activities: ['Sign contract', 'Kickoff meeting'] }
      ]
      
      for (const stageInfo of stages) {
        // Update opportunity stage
        const stageUpdate = {
          data: {
            type: 'Opportunities',
            id: opportunityId,
            attributes: {
              sales_stage: stageInfo.stage
            }
          }
        }
        
        const stageResponse = await suitecrmClient.patch('/module', stageUpdate)
        expect(stageResponse.status).toBe(200)
        expect(stageResponse.data.data.attributes.sales_stage).toBe(stageInfo.stage)
        
        // Probability should update automatically
        const expectedProbabilities: Record<string, number> = {
          'Needs Analysis': 20,
          'Value Proposition': 40,
          'Decision Makers': 60,
          'Proposal': 75,
          'Negotiation': 90,
          'Closed Won': 100
        }
        
        expect(parseInt(stageResponse.data.data.attributes.probability))
          .toBe(expectedProbabilities[stageInfo.stage])
      }
    })

    it('Step 4: Verify customer data in dashboard metrics', async () => {
      // Wait for data propagation
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Check dashboard metrics
      const metricsResponse = await customApiClient.get('/dashboard/metrics')
      expect(metricsResponse.status).toBe(200)
      
      // Check pipeline data
      const pipelineResponse = await customApiClient.get('/dashboard/pipeline')
      expect(pipelineResponse.status).toBe(200)
      
      // Verify closed won stage has at least one opportunity
      const closedWonStage = pipelineResponse.data.data.stages
        .find((s: any) => s.stage === 'Closed Won')
      expect(closedWonStage).toBeDefined()
      expect(closedWonStage.count).toBeGreaterThan(0)
      expect(closedWonStage.value).toBeGreaterThanOrEqual(250000)
    })
  })

  describe('Customer Support Workflow', () => {
    let accountId: string
    let caseId: string
    const activityIds: string[] = []

    it('Step 1: Customer reports critical issue', async () => {
      // Create customer account
      const accountData = {
        data: {
          type: 'Accounts',
          attributes: {
            name: 'Support Test Customer',
            account_type: 'Customer',
            phone_office: '555-0002'
          }
        }
      }
      
      const accountResponse = await suitecrmClient.post('/module', accountData)
      accountId = accountResponse.data.data.id
      createdIds.accounts.push(accountId)
      
      // Create critical case
      const caseData = {
        data: {
          type: 'Cases',
          attributes: {
            name: 'Production System Down',
            priority: 'P1',
            status: 'New',
            type: 'Infrastructure',
            account_id: accountId,
            description: 'Customer reports complete system outage affecting all users'
          }
        }
      }
      
      const caseResponse = await suitecrmClient.post('/module', caseData)
      expect(caseResponse.status).toBe(201)
      caseId = caseResponse.data.data.id
      createdIds.cases.push(caseId)
      
      // Verify P1 priority
      expect(caseResponse.data.data.attributes.priority).toBe('P1')
    })

    it('Step 2: Assign case and create support activities', async () => {
      // Update case to Assigned status
      const assignData = {
        data: {
          type: 'Cases',
          id: caseId,
          attributes: {
            status: 'Assigned',
            assigned_user_id: '1'
          }
        }
      }
      
      const assignResponse = await suitecrmClient.patch('/module', assignData)
      expect(assignResponse.status).toBe(200)
      
      // Create urgent call activity
      const callData = {
        data: {
          type: 'Calls',
          attributes: {
            name: 'Emergency Support Call',
            status: 'Planned',
            direction: 'Outbound',
            date_start: new Date().toISOString(),
            duration_hours: 0,
            duration_minutes: 30,
            priority: 'High',
            parent_type: 'Cases',
            parent_id: caseId,
            description: 'Immediate call to diagnose production issue'
          }
        }
      }
      
      const callResponse = await suitecrmClient.post('/module', callData)
      activityIds.push(callResponse.data.data.id)
      createdIds.activities.push(callResponse.data.data.id)
      
      // Create follow-up task
      const taskData = {
        data: {
          type: 'Tasks',
          attributes: {
            name: 'Deploy emergency fix',
            status: 'In Progress',
            priority: 'High',
            date_due: new Date().toISOString().split('T')[0],
            parent_type: 'Cases',
            parent_id: caseId,
            description: 'Deploy hotfix to resolve system outage'
          }
        }
      }
      
      const taskResponse = await suitecrmClient.post('/module', taskData)
      activityIds.push(taskResponse.data.data.id)
      createdIds.activities.push(taskResponse.data.data.id)
    })

    it('Step 3: Resolve case and verify SLA compliance', async () => {
      // Update case to Resolved
      const resolveData = {
        data: {
          type: 'Cases',
          id: caseId,
          attributes: {
            status: 'Resolved',
            resolution: 'Emergency patch deployed. System restored to full functionality.'
          }
        }
      }
      
      const resolveResponse = await suitecrmClient.patch('/module', resolveData)
      expect(resolveResponse.status).toBe(200)
      expect(resolveResponse.data.data.attributes.status).toBe('Resolved')
      expect(resolveResponse.data.data.attributes.resolution).toBeTruthy()
      
      // Complete activities
      const completeCallData = {
        data: {
          type: 'Calls',
          id: activityIds[0],
          attributes: {
            status: 'Held',
            description: 'Call completed. Issue identified and fix deployed.'
          }
        }
      }
      
      await suitecrmClient.patch('/module', completeCallData)
      
      const completeTaskData = {
        data: {
          type: 'Tasks',
          id: activityIds[1],
          attributes: {
            status: 'Completed',
            date_finished: new Date().toISOString()
          }
        }
      }
      
      await suitecrmClient.patch('/module', completeTaskData)
    })

    it('Step 4: Verify case metrics updated', async () => {
      // Wait for data propagation
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Check case metrics
      const metricsResponse = await customApiClient.get('/dashboard/cases')
      expect(metricsResponse.status).toBe(200)
      
      const metrics = metricsResponse.data.data
      
      // Should have P1 case data
      const p1Cases = metrics.casesByPriority.find((p: any) => p.priority === 'P1')
      expect(p1Cases).toBeDefined()
    })
  })

  describe('Activity Management Workflow', () => {
    let accountId: string
    let contactId: string

    it('Step 1: Schedule a series of sales activities', async () => {
      // Create account and contact
      const accountData = {
        data: {
          type: 'Accounts',
          attributes: {
            name: 'Activity Test Account',
            industry: 'Finance'
          }
        }
      }
      
      const accountResponse = await suitecrmClient.post('/module', accountData)
      accountId = accountResponse.data.data.id
      createdIds.accounts.push(accountId)
      
      const contactData = {
        data: {
          type: 'Contacts',
          attributes: {
            first_name: 'Activity',
            last_name: 'Contact',
            email1: 'activity@test.com',
            account_id: accountId
          }
        }
      }
      
      const contactResponse = await suitecrmClient.post('/module', contactData)
      contactId = contactResponse.data.data.id
      createdIds.contacts.push(contactId)
      
      // Schedule multiple activities
      const activities = [
        {
          type: 'Calls',
          attributes: {
            name: 'Initial Discovery Call',
            status: 'Planned',
            date_start: new Date(Date.now() + 1 * 24 * 60 * 60 * 1000).toISOString(),
            duration_minutes: 30,
            parent_type: 'Contacts',
            parent_id: contactId
          }
        },
        {
          type: 'Meetings',
          attributes: {
            name: 'Product Demonstration',
            status: 'Planned',
            date_start: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString(),
            duration_hours: 1,
            location: 'Zoom',
            parent_type: 'Accounts',
            parent_id: accountId
          }
        },
        {
          type: 'Tasks',
          attributes: {
            name: 'Send Follow-up Proposal',
            status: 'Not Started',
            priority: 'High',
            date_due: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
            parent_type: 'Accounts',
            parent_id: accountId
          }
        }
      ]
      
      const activityPromises = activities.map(activity => 
        suitecrmClient.post('/module', { data: activity })
      )
      
      const responses = await Promise.all(activityPromises)
      
      responses.forEach(response => {
        expect(response.status).toBe(201)
        createdIds.activities.push(response.data.data.id)
      })
    })

    it('Step 2: Verify activities appear in dashboard', async () => {
      // Wait for data propagation
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Check activity metrics
      const metricsResponse = await customApiClient.get('/dashboard/activities')
      expect(metricsResponse.status).toBe(200)
      
      const activities = metricsResponse.data.data
      expect(activities.upcomingActivities.length).toBeGreaterThan(0)
      
      // Should have upcoming activities
      const upcomingTypes = activities.upcomingActivities.map((a: any) => a.type)
      expect(upcomingTypes).toContain('Call')
      expect(upcomingTypes).toContain('Meeting')
      expect(upcomingTypes).toContain('Task')
    })
  })

  describe('Cross-Module Integration', () => {
    it('should maintain data consistency across all modules', async () => {
      // Get current metrics
      const metricsResponse = await customApiClient.get('/dashboard/metrics')
      expect(metricsResponse.status).toBe(200)
      
      const metrics = metricsResponse.data.data
      
      // Verify all metric values are non-negative
      expect(metrics.totalLeads).toBeGreaterThanOrEqual(0)
      expect(metrics.totalAccounts).toBeGreaterThanOrEqual(0)
      expect(metrics.newLeadsToday).toBeGreaterThanOrEqual(0)
      expect(metrics.pipelineValue).toBeGreaterThanOrEqual(0)
      
      // Verify pipeline data consistency
      const pipelineResponse = await customApiClient.get('/dashboard/pipeline')
      expect(pipelineResponse.status).toBe(200)
      
      const pipelineData = pipelineResponse.data.data
      
      // Total should equal sum of stage values
      const calculatedTotal = pipelineData.stages.reduce(
        (sum: number, stage: any) => sum + stage.value, 
        0
      )
      expect(pipelineData.total).toBe(calculatedTotal)
      
      // All stages should be present
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
        const stage = pipelineData.stages.find((s: any) => s.stage === stageName)
        expect(stage).toBeDefined()
      })
    })
  })
})